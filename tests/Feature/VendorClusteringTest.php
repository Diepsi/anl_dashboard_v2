<?php

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\User;
use App\Services\VendorClusteringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorClusteringTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bulk insert supaya seed 100+ baris per vendor tidak lambat.
     */
    private function seedVendor(string $vendor, int $count, int $meet, int $aging): void
    {
        $rows = [];

        for ($index = 0; $index < $count; $index++) {
            $rows[] = [
                'no_resi' => strtoupper($vendor).'-'.$index,
                'resi_valid' => true,
                'nama_sekolah' => 'Sekolah '.$index,
                'nama_funder' => 'DB Logistics',
                'vendor_lm' => $vendor,
                'status_akhir' => 'Completed',
                'stagging' => 'DELIVER',
                'sla_result' => $index < $meet ? 'Meet SLA' : 'Out SLA',
                'tanggal_manifest' => '2026-01-10',
                'tanggal_efektif' => '2026-01-10',
                'completed_date' => '2026-01-15',
                'aging' => $aging,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Shipment::insert($rows);
    }

    public function test_zscore_normalizes_to_zero_mean_and_unit_standard_deviation(): void
    {
        $service = app(VendorClusteringService::class);

        $standardized = $service->standardizeMatrix([
            [10.0, 100.0],
            [20.0, 50.0],
            [30.0, 0.0],
        ]);

        $this->assertCount(3, $standardized);

        foreach ([0, 1] as $column) {
            $values = array_column($standardized, $column);

            $mean = array_sum($values) / count($values);
            $variance = array_sum(array_map(fn ($v) => pow($v - $mean, 2), $values)) / count($values);

            $this->assertEqualsWithDelta(0.0, $mean, 0.0001, "kolom {$column} mean harus 0");
            $this->assertEqualsWithDelta(1.0, sqrt($variance), 0.0001, "kolom {$column} std dev harus 1");
        }
    }

    public function test_zscore_survives_a_column_with_zero_variance(): void
    {
        $service = app(VendorClusteringService::class);

        $standardized = $service->standardizeMatrix([
            [500.0, 10.0],
            [500.0, 20.0],
            [500.0, 30.0],
        ]);

        $this->assertSame([0.0, 0.0, 0.0], array_column($standardized, 0));
        $this->assertNotSame([0.0, 0.0, 0.0], array_column($standardized, 1));
    }

    public function test_kmeans_returns_the_same_assignment_for_the_same_input(): void
    {
        $service = app(VendorClusteringService::class);

        $points = [
            [-1.2, 1.1],
            [-1.0, 0.9],
            [0.1, 0.2],
            [1.3, -1.2],
            [1.1, -0.9],
            [0.2, 0.3],
        ];

        $first = $service->kmeans($points, 3);
        $second = $service->kmeans($points, 3);

        $this->assertSame($first, $second);
        $this->assertCount(6, $first);
    }

    /**
     * Farthest-point init bergantung pada titik awal, dan K-Means hanya
     * menjamin local optimum. Karena itu build() memakai kmeansBest yang
     * mencoba semua start dan memilih inertia terendah — bukan start pertama
     * yang kebetulan datang duluan dari urutan alfabet vendor.
     */
    public function test_kmeans_best_picks_the_lowest_inertia_across_all_starts(): void
    {
        $service = app(VendorClusteringService::class);

        $points = [
            [-1.4, 1.2],
            [-1.1, 0.9],
            [-0.9, 1.3],
            [0.0, 0.1],
            [0.2, -0.1],
            [1.2, -1.1],
            [1.4, -0.9],
            [1.0, -1.3],
        ];

        $best = $service->kmeansBest($points, 3);
        $bestInertia = $service->inertia($points, $best, 3);

        $inertias = [];

        for ($start = 0; $start < count($points); $start++) {
            $inertias[] = $service->inertia($points, $service->kmeans($points, 3, $start), 3);
        }

        $this->assertEqualsWithDelta(min($inertias), $bestInertia, 0.0001);
        $this->assertSame($best, $service->kmeansBest($points, 3));
    }

    public function test_every_clustered_vendor_gets_exactly_one_cluster(): void
    {
        $this->seedVendor('ALPHA', 150, 150, 5);
        $this->seedVendor('BRAVO', 150, 90, 400);
        $this->seedVendor('CHARLIE', 150, 15, 700);

        $payload = app(VendorClusteringService::class)->build();

        $labels = [];

        foreach ($payload['clusters'] as $cluster) {
            foreach ($cluster['members'] as $member) {
                $labels[] = $member['vendor'];
            }
        }

        sort($labels);

        $this->assertSame(['ALPHA', 'BRAVO', 'CHARLIE'], $labels);
        $this->assertCount(0, $payload['undecided']);
    }

    public function test_cluster_with_the_lowest_sla_is_labeled_berisiko_and_the_highest_elite(): void
    {
        $this->seedVendor('ALPHA', 150, 150, 5);
        $this->seedVendor('BRAVO', 150, 90, 400);
        $this->seedVendor('CHARLIE', 150, 15, 700);

        $payload = app(VendorClusteringService::class)->build();

        $slaByLabel = [];

        foreach ($payload['clusters'] as $cluster) {
            $slaByLabel[$cluster['label']] = $cluster['sla_success_rate'];
        }

        $this->assertArrayHasKey('Elite', $slaByLabel);
        $this->assertArrayHasKey('Standar', $slaByLabel);
        $this->assertArrayHasKey('Berisiko', $slaByLabel);

        $this->assertGreaterThan($slaByLabel['Standar'], $slaByLabel['Elite']);
        $this->assertGreaterThan($slaByLabel['Berisiko'], $slaByLabel['Standar']);

        $membersByLabel = [];

        foreach ($payload['clusters'] as $cluster) {
            $membersByLabel[$cluster['label']] = array_column($cluster['members'], 'vendor');
        }

        $this->assertSame(['ALPHA'], $membersByLabel['Elite']);
        $this->assertSame(['CHARLIE'], $membersByLabel['Berisiko']);
    }

    /**
     * Aging berbeda ekstrem (10 hari vs 900 hari) pada vendor dengan volume dan
     * SLA identik. Kalau avg_aging ikut jadi fitur, keduanya harusnya terpecah.
     * Karena fitur clustering hanya volume + SLA, keduanya wajib berada di
     * cluster yang sama.
     */
    public function test_avg_aging_does_not_influence_cluster_assignment(): void
    {
        $this->seedVendor('RAJAMUDA', 150, 150, 10);
        $this->seedVendor('RAJATUA', 150, 150, 900);
        $this->seedVendor('PENENGGAH', 150, 90, 500);
        $this->seedVendor('RAJABURUK', 150, 15, 500);

        $payload = app(VendorClusteringService::class)->build();

        $clusterOfVendor = [];

        foreach ($payload['clusters'] as $cluster) {
            foreach ($cluster['members'] as $member) {
                $clusterOfVendor[$member['vendor']] = $cluster['label'];
            }
        }

        $this->assertArrayHasKey('RAJAMUDA', $clusterOfVendor);
        $this->assertArrayHasKey('RAJATUA', $clusterOfVendor);
        $this->assertSame(
            $clusterOfVendor['RAJAMUDA'],
            $clusterOfVendor['RAJATUA'],
            'Vendor dengan volume & SLA identik harus cluster sama walau aging berbeda 890 hari'
        );

        $this->assertNotContains('avg_aging', $payload['method']['features']);
        $this->assertContains('avg_aging', $payload['method']['excluded_features']);
    }

    public function test_vendors_below_the_minimum_sla_sample_are_reported_as_undecided(): void
    {
        $this->seedVendor('BESAR', 250, 250, 30);
        $this->seedVendor('SEDANG', 150, 150, 30);
        $this->seedVendor('KECIL', 40, 40, 30);

        $payload = app(VendorClusteringService::class)->build();

        $clustered = [];

        foreach ($payload['clusters'] as $cluster) {
            foreach ($cluster['members'] as $member) {
                $clustered[] = $member['vendor'];
            }
        }

        $this->assertNotContains('KECIL', $clustered);
        $this->assertSame(['KECIL'], array_column($payload['undecided'], 'vendor'));
        $this->assertSame(40, $payload['undecided'][0]['total_shipment']);
        $this->assertSame(VendorClusteringService::MIN_SLA_SAMPLE, $payload['method']['min_sla_sample']);
    }

    /**
     * Baris tanpa nilai SLA tidak boleh ikut menurunkan persentase. 100 Meet +
     * 100 NULL = 100%, bukan 50%.
     */
    public function test_sla_rate_uses_only_measured_rows_as_the_denominator(): void
    {
        $this->seedVendor('ADA_NULL', 200, 100, 30);
        Shipment::where('vendor_lm', 'ADA_NULL')->where('sla_result', 'Out SLA')->update(['sla_result' => null]);
        $this->seedVendor('PENUH', 150, 150, 30);
        $this->seedVendor('TENGAH', 150, 75, 30);

        $payload = app(VendorClusteringService::class)->build();

        $measured = $this->aggregatedRow($payload, 'ADA_NULL');

        $this->assertNotNull($measured);
        $this->assertSame(200, $measured['total_shipment']);
        $this->assertSame(100, $measured['sla_denominator']);
        $this->assertSame(100.0, $measured['sla_success_rate']);
    }

    /**
     * Sumber menyimpan nama vendor dengan kapitalisasi berbeda (SIGO/Sigo,
     * RAJAB/Rajab/rajab). Tanpa kanonisasi, jumlah vendor ikut berubah.
     */
    public function test_vendor_name_casing_variants_are_merged_into_one_vendor(): void
    {
        $this->seedVendor('SIGO', 120, 110, 100);
        $this->seedVendor('Sigo', 120, 60, 100);
        $this->seedVendor('sigo', 120, 20, 100);

        $aggregated = app(VendorClusteringService::class)->aggregate();

        $this->assertCount(1, $aggregated);
        $this->assertSame('SIGO', $aggregated[0]['vendor']);
        $this->assertSame(360, $aggregated[0]['total_shipment']);
        $this->assertSame(190, $aggregated[0]['meet']);
        $this->assertSame(360, $aggregated[0]['sla_denominator']);
    }

    public function test_analytics_page_redirects_guests_to_login(): void
    {
        $response = $this->get('/analytics/vendors');

        $response->assertRedirect(route('login'));
    }

    public function test_analytics_page_renders_for_authenticated_users(): void
    {
        $this->seedVendor('ALPHA', 150, 150, 5);
        $this->seedVendor('BRAVO', 150, 90, 400);
        $this->seedVendor('CHARLIE', 150, 15, 700);
        $this->seedVendor('KECIL', 40, 40, 30);

        $response = $this->actingAs(User::factory()->create())->get('/analytics/vendors');

        $response->assertOk();
        $response->assertSee('Vendor Performance Clustering');
        $response->assertSee('Elite');
        $response->assertSee('Standar');
        $response->assertSee('Berisiko');
        $response->assertSee('Belum Cukup Data');
        $response->assertSee('ALPHA');
        $response->assertSee('CHARLIE');
        $response->assertSee('KECIL');
    }

    public function test_sidebar_links_to_the_vendor_clustering_page(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/');

        $response->assertOk();
        $response->assertSee(route('analytics.vendors'), escape: false);
        $response->assertSee('Vendor Clustering');
    }

    public function test_the_route_is_named_and_registered(): void
    {
        $this->assertStringContainsString('/analytics/vendors', route('analytics.vendors'));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function aggregatedRow(array $payload, string $vendor): ?array
    {
        foreach ($payload['clusters'] as $cluster) {
            foreach ($cluster['members'] as $member) {
                if ($member['vendor'] === $vendor) {
                    return $member;
                }
            }
        }

        return null;
    }
}
