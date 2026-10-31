<?php

namespace App\Services;

use App\Models\Shipment;
use Illuminate\Support\Collection;

/**
 * Klasifikasi kinerja vendor memakai K-Means pada Z-score dua fitur:
 * total shipment dan kepatuhan SLA.
 *
 * `aging` sengaja TIDAK dipakai sebagai fitur. Nilainya nyaris konstan per
 * vendor (JAR 293-295, JES 320-322, CLE 393-398) sehingga hanya menambah
 * noise, dan BOMA menjadi outlier -4,49 sigma yang memaksa K-Means memisahkan
 * dirinya sendiri tanpa alasan kinerja.
 *
 * Vendor dengan sampel SLA terlalu sedikit dikecualikan dari cluster agar
 * persentase rapuh (misal SAUTAN 20/20 = 100%) tidak mengalahkan vendor
 * dengan ribuan kiriman.
 */
class VendorClusteringService
{
    /**
     * Jumlah minimum baris dengan SLA terukur (Meet SLA / Out SLA) agar vendor
     * masuk clustering. Di bawah ini, persentase SLA belum bisa diandalkan.
     */
    public const MIN_SLA_SAMPLE = 100;

    public const CLUSTER_COUNT = 3;

    /**
     * Label mengikuti urutan kualitas cluster (SLA tertinggi → terendah),
     * BUKAN nomor indeks K-Means yang tidak bermakna apa pun.
     */
    public const LABELS = ['Elite', 'Standar', 'Berisiko'];

    /**
     * @return array{clusters: list<array<string, mixed>>, undecided: list<array<string, mixed>>, method: array<string, mixed>}
     */
    public function build(): array
    {
        $rows = $this->aggregate();

        $measured = $rows
            ->filter(fn (array $row) => $row['sla_denominator'] >= self::MIN_SLA_SAMPLE)
            ->values();

        $undecided = $rows
            ->filter(fn (array $row) => $row['sla_denominator'] < self::MIN_SLA_SAMPLE)
            ->map(fn (array $row) => [
                'vendor' => $row['vendor'],
                'total_shipment' => $row['total_shipment'],
                'avg_aging' => $row['avg_aging'],
                'sla_success_rate' => $row['sla_success_rate'],
                'sla_denominator' => $row['sla_denominator'],
            ])
            ->values()
            ->all();

        if ($measured->isEmpty()) {
            return [
                'clusters' => [],
                'undecided' => $undecided,
                'method' => $this->methodMeta(0),
            ];
        }

        $features = $this->standardizeMatrix(
            $measured->map(fn (array $row) => [
                $row['total_shipment'],
                $row['sla_success_rate'],
            ])->all()
        );

        $assignment = $this->kmeansBest($features, min(self::CLUSTER_COUNT, $measured->count()));

        $clusters = $this->labelClusters($measured, $assignment);

        return [
            'clusters' => $clusters,
            'undecided' => $undecided,
            'method' => $this->methodMeta($measured->count()),
        ];
    }

    /**
     * Satu query agregat untuk semua vendor. Kanonisasi nama vendor dengan
     * UPPER(TRIM(...)) supaya varian kapitalisasi (SIGO/Sigo, RAJAB/Rajab)
     * tidak membuat jumlah vendor berubah antar-halaman.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function aggregate(): Collection
    {
        return Shipment::query()
            ->whereNotNull('vendor_lm')
            ->where('vendor_lm', '!=', '')
            ->selectRaw('UPPER(TRIM(vendor_lm)) as vendor')
            ->selectRaw('COUNT(*) as total_shipment')
            ->selectRaw('AVG(aging) as avg_aging')
            ->selectRaw("SUM(CASE WHEN sla_result = 'Meet SLA' THEN 1 ELSE 0 END) as meet")
            ->selectRaw("SUM(CASE WHEN sla_result IN ('Meet SLA', 'Out SLA') THEN 1 ELSE 0 END) as sla_denominator")
            ->groupByRaw('UPPER(TRIM(vendor_lm))')
            ->orderBy('vendor')
            ->get()
            ->map(function (object $row): array {
                $total = (int) $row->total_shipment;
                $denominator = (int) $row->sla_denominator;

                return [
                    'vendor' => (string) $row->vendor,
                    'total_shipment' => $total,
                    'avg_aging' => $row->avg_aging !== null ? round((float) $row->avg_aging, 1) : null,
                    'meet' => (int) $row->meet,
                    'sla_denominator' => $denominator,
                    // Penyebut hanya baris SLA terukur. Membagi dengan total
                    // menurunkan persentase tanpa sebab saat ada baris NULL.
                    'sla_success_rate' => $denominator > 0
                        ? round(((int) $row->meet / $denominator) * 100, 1)
                        : null,
                ];
            })
            ->values();
    }

    /**
     * Z-score per kolom dengan standard deviation populasi (/n), bukan sampel.
     * Kolom dengan variance nol menghasilkan nol, bukan pembagian nol.
     *
     * @param  array<int, array<int, float>>  $rows
     * @return array<int, array<int, float>>
     */
    public function standardizeMatrix(array $rows): array
    {
        $columnCount = count($rows[0] ?? []);

        if ($columnCount === 0) {
            return [];
        }

        $means = [];
        $deviations = [];

        for ($column = 0; $column < $columnCount; $column++) {
            $sum = 0;

            foreach ($rows as $row) {
                $sum += $row[$column];
            }

            $mean = $sum / count($rows);
            $means[$column] = $mean;

            $variance = 0;

            foreach ($rows as $row) {
                $variance += pow($row[$column] - $mean, 2);
            }

            $deviations[$column] = sqrt($variance / count($rows));
        }

        return array_map(fn (array $row) => array_map(
            fn (float $value, int $column) => $deviations[$column] > 0
                ? ($value - $means[$column]) / $deviations[$column]
                : 0.0,
            $row,
            array_keys($row)
        ), $rows);
    }

    /**
     * K-Means dengan inisialisasi farthest-point. Deterministik: titik pertama
     * adalah `$start`, titik berikutnya adalah yang terjauh dari centroid yang
     * sudah ada. Init acak akan membuat tabel berubah tiap halaman dimuat.
     *
     * @param  array<int, array<int, float>>  $points
     * @return array<int, int> indeks cluster per titik
     */
    public function kmeans(array $points, int $k, int $start = 0): array
    {
        $count = count($points);

        if ($count === 0 || $k <= 1) {
            return array_fill(0, $count, 0);
        }

        $centroids = $this->initialCentroids($points, $k, $start);
        $assignment = array_fill(0, $count, -1);

        for ($iteration = 0; $iteration < 100; $iteration++) {
            $changed = false;

            for ($index = 0; $index < $count; $index++) {
                $nearest = 0;
                $nearestDistance = INF;

                foreach ($centroids as $cluster => $centroid) {
                    $distance = $this->squaredEuclideanDistance($points[$index], $centroid);

                    if ($distance < $nearestDistance) {
                        $nearestDistance = $distance;
                        $nearest = $cluster;
                    }
                }

                if ($assignment[$index] !== $nearest) {
                    $assignment[$index] = $nearest;
                    $changed = true;
                }
            }

            foreach ($this->groupIndices($assignment, $k) as $cluster => $indices) {
                if ($indices === []) {
                    continue;
                }

                $dimensionCount = count($points[0]);
                $centroid = array_fill(0, $dimensionCount, 0.0);

                foreach ($indices as $index) {
                    for ($dimension = 0; $dimension < $dimensionCount; $dimension++) {
                        $centroid[$dimension] += $points[$index][$dimension];
                    }
                }

                foreach ($centroid as $dimension => $value) {
                    $centroid[$dimension] = $value / count($indices);
                }

                $centroids[$cluster] = $centroid;
            }

            if (! $changed) {
                break;
            }
        }

        return $this->fillEmptyClusters($points, $centroids, $assignment, $k);
    }

    /**
     * Nol kemenangan: urutkan cluster berdasarkan SLA, baru tempel label. Nomor
     * cluster K-Means tidak punya makna, jadi label harus diturunkan dari hasil.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<int, int>  $assignment
     * @return list<array<string, mixed>>
     */
    private function labelClusters(Collection $rows, array $assignment): array
    {
        $grouped = [];

        foreach ($rows->all() as $index => $row) {
            $grouped[$assignment[$index]]['rows'][] = $row;
        }

        $summaries = [];

        foreach ($grouped as $cluster => $group) {
            $members = $group['rows'];
            $shipmentTotal = array_sum(array_column($members, 'total_shipment'));

            $slaWeighted = array_sum(array_map(
                fn (array $row) => $row['sla_success_rate'] * $row['sla_denominator'],
                $members
            ));
            $denominatorTotal = array_sum(array_column($members, 'sla_denominator'));

            $agingValues = array_filter(
                array_column($members, 'avg_aging'),
                fn ($value) => $value !== null
            );

            $summaries[] = [
                'source_cluster' => $cluster,
                'vendor_count' => count($members),
                'total_shipment' => $shipmentTotal,
                'avg_aging' => $agingValues === [] ? null : round(array_sum($agingValues) / count($agingValues), 1),
                'sla_success_rate' => $denominatorTotal > 0
                    ? round($slaWeighted / $denominatorTotal, 1)
                    : null,
                'members' => $members,
            ];
        }

        usort($summaries, function (array $left, array $right): int {
            $bySla = ($right['sla_success_rate'] <=> $left['sla_success_rate']);

            return $bySla !== 0 ? $bySla : ($right['total_shipment'] <=> $left['total_shipment']);
        });

        return array_values(array_map(function (array $summary, int $position): array {
            $label = self::LABELS[$position] ?? self::LABELS[count(self::LABELS) - 1];

            return [
                'label' => $label,
                'key' => mb_strtolower($label),
                'position' => $position,
                'source_cluster' => $summary['source_cluster'],
                'vendor_count' => $summary['vendor_count'],
                'total_shipment' => $summary['total_shipment'],
                'avg_aging' => $summary['avg_aging'],
                'sla_success_rate' => $summary['sla_success_rate'],
                'members' => $summary['members'],
            ];
        }, $summaries, array_keys($summaries)));
    }

    /**
     * Multi-start: farthest-point init bergantung pada titik pertama, dan
     * K-Means hanya menjamin local optimum. Dengan 22 vendor ada 4 partition
     * berbeda dari 22 kemungkinan start. Mencoba SEMUA start lalu memilih
     * inertia terendah membuat hasil deterministik sekaligus optimum terbaik
     * yang bisa dicapai tanpa random.
     *
     * @param  array<int, array<int, float>>  $points
     * @return array<int, int>
     */
    public function kmeansBest(array $points, int $k): array
    {
        $count = count($points);

        if ($count === 0 || $k <= 1) {
            return array_fill(0, $count, 0);
        }

        $bestAssignment = null;
        $bestInertia = INF;

        for ($start = 0; $start < $count; $start++) {
            $assignment = $this->kmeans($points, $k, $start);
            $inertia = $this->inertia($points, $assignment, $k);

            if ($inertia < $bestInertia) {
                $bestInertia = $inertia;
                $bestAssignment = $assignment;
            }
        }

        return $bestAssignment ?? $this->kmeans($points, $k, 0);
    }

    /**
     * Total jarak kuadrat setiap titik ke centroid cluster-nya — ukuran baku
     * kualitas partition K-Means. Yang lebih kecil lebih baik.
     *
     * @param  array<int, array<int, float>>  $points
     * @param  array<int, int>  $assignment
     */
    public function inertia(array $points, array $assignment, int $k): float
    {
        $grouped = [];

        foreach ($points as $index => $point) {
            $cluster = $assignment[$index] ?? 0;
            $grouped[$cluster][] = $index;
        }

        $total = 0.0;

        foreach ($grouped as $indices) {
            $dimensionCount = count($points[0]);
            $centroid = array_fill(0, $dimensionCount, 0.0);

            foreach ($indices as $index) {
                for ($dimension = 0; $dimension < $dimensionCount; $dimension++) {
                    $centroid[$dimension] += $points[$index][$dimension];
                }
            }

            foreach ($centroid as $dimension => $value) {
                $centroid[$dimension] = $value / count($indices);
            }

            foreach ($indices as $index) {
                $total += $this->squaredEuclideanDistance($points[$index], $centroid);
            }
        }

        return $total;
    }

    /**
     * @param  array<int, array<int, float>>  $points
     * @return list<array<int, float>>
     */
    private function initialCentroids(array $points, int $k, int $start = 0): array
    {
        $first = $points[$start] ?? $points[0];
        $centroids = [$first];

        while (count($centroids) < $k) {
            $farthest = null;
            $farthestDistance = -1.0;

            foreach ($points as $point) {
                $distance = INF;

                foreach ($centroids as $centroid) {
                    $distance = min($distance, $this->squaredEuclideanDistance($point, $centroid));
                }

                if ($distance > $farthestDistance) {
                    $farthestDistance = $distance;
                    $farthest = $point;
                }
            }

            $centroids[] = $farthest ?? $points[0];
        }

        return $centroids;
    }

    /**
     * Cluster yang kehilangan seluruh anggotanya tidak boleh tetap kosong:
     * centroid dihidupkan ulang di titik terjauh dari centroid lain agar iterasi
     * berikutnya punya penerimaan, bukan pembagian nol.
     *
     * @param  array<int, array<int, float>>  $points
     * @param  list<array<int, float>>  $centroids
     * @param  array<int, int>  $assignment
     * @return array<int, int>
     */
    private function fillEmptyClusters(array $points, array $centroids, array $assignment, int $k): array
    {
        $grouped = $this->groupIndices($assignment, $k);

        foreach ($grouped as $cluster => $indices) {
            if ($indices !== []) {
                continue;
            }

            $farthest = null;
            $farthestDistance = -1.0;

            foreach ($points as $index => $point) {
                if ($assignment[$index] === $cluster) {
                    continue;
                }

                $distance = INF;

                foreach ($centroids as $otherCluster => $centroid) {
                    if ($otherCluster === $cluster) {
                        continue;
                    }

                    $distance = min($distance, $this->squaredEuclideanDistance($point, $centroid));
                }

                if ($distance > $farthestDistance) {
                    $farthestDistance = $distance;
                    $farthest = $index;
                }
            }

            if ($farthest !== null) {
                $assignment[$farthest] = $cluster;
            }
        }

        return $assignment;
    }

    /**
     * @param  array<int, int>  $assignment
     * @return array<int, list<int>>
     */
    private function groupIndices(array $assignment, int $k): array
    {
        $grouped = array_fill(0, $k, []);

        foreach ($assignment as $index => $cluster) {
            if (isset($grouped[$cluster])) {
                $grouped[$cluster][] = $index;
            }
        }

        return $grouped;
    }

    /**
     * @param  array<int, float>  $left
     * @param  array<int, float>  $right
     */
    private function squaredEuclideanDistance(array $left, array $right): float
    {
        $distance = 0.0;

        foreach ($left as $dimension => $value) {
            $distance += pow($value - ($right[$dimension] ?? 0.0), 2);
        }

        return $distance;
    }

    /**
     * @return array<string, mixed>
     */
    private function methodMeta(int $measuredCount): array
    {
        return [
            'features' => ['total_shipment', 'sla_success_rate'],
            'excluded_features' => ['avg_aging'],
            'min_sla_sample' => self::MIN_SLA_SAMPLE,
            'measured_count' => $measuredCount,
        ];
    }
}
