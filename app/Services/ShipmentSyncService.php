<?php

namespace App\Services;

use App\Models\Shipment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use SplFileObject;

class ShipmentSyncService
{
    private array $columnMap = [
        'no_resi' => ['No Resi'],
        'nomor_redock' => ['Redock', 'Nomor Redock'],
        'delivery_order' => ['Delivery Order', 'Manifest First Mile'],
        'nama_sekolah' => ['NAMA SEKOLAH'],
        'nama_penerima' => ['Nama Penerima', 'Nama Penerima (Alokasi)'],
        'provinsi' => ['Provinsi'],
        'daerah' => ['Daerah'],
        'kota_kabupaten' => ['Kabupaten/Kota', 'Kota/Kab Tujuan'],
        'kecamatan' => ['Kecamatan'],
        'vendor_mm' => ['Vendor MM'],
        'kode_funder' => ['Kode Funder', 'Code Funder'],
        'nama_funder' => ['Founder', 'Nama Funder'],
        'vendor_lm' => ['Vendor LM'],
        'tanggal_manifest' => ['Tanggal HO ke Vendor', 'Tgl HO dari SarTrans', 'Tanggal Manifest', 'Tanggal Handover ke Vendor'],
        'completed_date' => ['Completed date'],
        'tgl_sampai_kota_tujuan' => ['Actual ETA KAPAL', 'Tgl sampai di kota tujuan'],
        'aging' => ['Aging'],
        'status_akhir' => ['Status Akhir'],
        'status_instalasi' => ['Status Instalasi'],
        'harga_per_shipment' => ['Harga Per Shipment'],
        'status_invoice' => ['Status Invoice'],
        'stagging' => ['Update Stagging', 'Status update'],
        'sla' => ['SLA', 'SLA for DB'],
        'sla_result' => ['Result Delivery for Panthera', 'Result Delivery', 'Result Delivery for DB'],
        'bast_tgl_balik' => ['TGL BAST BALIK DARI VENDOR'],
        'bast_tgl_ke_finance' => ['TGL HO BAST KE FINANCE', 'TGL HO BAST KE FINENCE'],
    ];

    public const SUMBER_TANGGAL_HO_VENDOR = 'Tanggal HO Vendor';

    public const SUMBER_TANGGAL_SARTRANS = 'Tanggal HO SarTrans';

    public const SUMBER_TANGGAL_COMPLETED = 'Completed date';

    public const RESI_CATATAN_KOSONG = 'KOSONG';

    public const RESI_CATATAN_NO_FUNGSI = 'NO_FUNGSI';

    public const RESI_CATATAN_NOMOR_RUSAK = 'NOMOR_RUSAK';

    public const RESI_CATATAN_DUPLIKAT = 'DUPLIKAT';

    public const RESI_CATATAN_FORMAT = 'FORMAT_TIDAK_VALID';

    public const LAST_SYNC_CACHE_KEY = 'shipments.last_synced_at';

    public const SOURCE_STATS_CACHE_KEY = 'shipments.source_stats';

    /**
     * Nilai error bawaan Google Sheets/Excel. Sel ini bukan data kosong, melainkan
     * hasil rumus yang gagal, jadi harus disalin menjadi NULL agar tidak ikut
     * terhitung sebagai nilai kategorikal (mis. `sla_result = '#N/A'` akan
     * terlihat sebagai baris yang tidak lolos SLA).
     */
    private const EXCEL_ERROR_VALUES = [
        '#N/A',
        '#VALUE!',
        '#REF!',
        '#DIV/0!',
        '#NAME?',
        '#NULL!',
        '#NUM!',
        '#GETTING_DATA',
    ];

    public function lastSyncedAt(): ?Carbon
    {
        $value = Cache::get(self::LAST_SYNC_CACHE_KEY);

        return $value ? Carbon::parse($value) : null;
    }

    public function sourceStats(): ?array
    {
        $stats = Cache::get(self::SOURCE_STATS_CACHE_KEY);

        return is_array($stats) ? $stats : null;
    }

    /**
     * Selalu melakukan full replace agar jumlah baris di database selalu sama
     * dengan jumlah baris sumber. Duplikat pada sumber ditandai, bukan dibuang.
     */
    public function sync(): int
    {
        $url = config('services.google_sheets.csv_url');

        if (empty($url)) {
            throw new \RuntimeException('GOOGLE_SHEETS_CSV_URL belum dikonfigurasi di .env');
        }

        $response = Http::timeout(120)->get($url);

        if (! $response->successful() || $response->body() === '') {
            throw new \RuntimeException('Gagal mengunduh CSV (HTTP '.$response->status().').');
        }

        $temp = tempnam(sys_get_temp_dir(), 'anl_');
        file_put_contents($temp, $response->body());

        try {
            $result = DB::transaction(fn () => $this->importFromFile($temp));
            $count = $result['imported'];
        } finally {
            @unlink($temp);
        }

        $stats = $result['stats'];

        Cache::put(self::SOURCE_STATS_CACHE_KEY, $stats);
        Cache::put(self::LAST_SYNC_CACHE_KEY, now()->toISOString());

        return $count;
    }

    protected function importFromFile(string $file): array
    {
        $csv = new SplFileObject($file, 'r');
        $csv->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

        $index = null;

        while (! $csv->eof()) {
            $row = $csv->fgetcsv();

            if ($row === false) {
                continue;
            }

            $row = array_map(fn ($cell) => trim((string) $cell), $row);

            $index = $this->buildHeaderIndex($row);

            if (! isset($index['no resi'])) {
                throw new \RuntimeException('Kolom "No Resi" tidak ditemukan di header CSV.');
            }

            break;
        }

        // Full replace: tabel dikosongkan lebih dulu agar isi database selalu
        // identik dengan sheet, termasuk duplikat dan resi bermasalah.
        Shipment::query()->delete();

        $resiOccurrences = $this->countResiOccurrences($file, $index);

        $csv = new SplFileObject($file, 'r');
        $csv->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

        $chunks = [];
        $count = 0;
        $rawRows = 0;
        $skippedEmpty = 0;
        $duplicates = [];
        $resiCounts = [
            self::RESI_CATATAN_KOSONG => 0,
            self::RESI_CATATAN_NO_FUNGSI => 0,
            self::RESI_CATATAN_NOMOR_RUSAK => 0,
            self::RESI_CATATAN_FORMAT => 0,
            self::RESI_CATATAN_DUPLIKAT => 0,
        ];
        $sumberTanggal = [
            self::SUMBER_TANGGAL_HO_VENDOR => 0,
            self::SUMBER_TANGGAL_SARTRANS => 0,
            self::SUMBER_TANGGAL_COMPLETED => 0,
            'TIDAK_ADA' => 0,
        ];

        while (! $csv->eof()) {
            $row = $csv->fgetcsv();

            if ($row === false) {
                continue;
            }

            $row = array_map(fn ($cell) => trim((string) $cell), $row);

            $data = $this->mapRow($row, $index);

            // Pass kedua memindai ulang seluruh file, jadi baris header ikut
            // terbaca. Header bukan baris data: jangan dihitung sebagai rawRows
            // dan jangan dilaporkan sebagai baris tanpa No Resi yang diabaikan.
            if ($this->isRepeatedHeaderRow($data)) {
                continue;
            }

            // Sheet sumber memakai formula yang meluas (mis. `=SEQUENCE(20000,1)`
            // pada kolom "No"), sehingga ekspor CSV/Excel memuat ribuan baris
            // kosong tanpa No Resi maupun NAMA SEKOLAH. Baris seperti itu bukan
            // data dan tidak boleh ikut menambah total shipment.
            if ($this->isEmptyBusinessRow($data)) {
                $skippedEmpty++;

                continue;
            }

            $rawRows++;

            $noResi = (string) $data['no_resi'];
            $isDuplicate = ($resiOccurrences[$noResi] ?? 0) > 1;

            if ($isDuplicate) {
                $duplicates[$noResi] = $resiOccurrences[$noResi];
            }

            [$resiValid, $resiCatatan] = $this->classifyResi($noResi, $isDuplicate);

            if ($resiCatatan !== null) {
                $resiCounts[$resiCatatan]++;
            }

            $data['resi_valid'] = $resiValid;
            $data['resi_catatan'] = $resiCatatan;
            $data['created_at'] = now();
            $data['updated_at'] = now();

            $sumberTanggal[$data['tanggal_sumber'] ?? 'TIDAK_ADA']++;

            $chunks[] = $data;
            $count++;

            if (count($chunks) >= 500) {
                $this->flush($chunks);
                $chunks = [];
            }
        }

        if (! empty($chunks)) {
            $this->flush($chunks);
        }

        $stored = Shipment::query()->count();

        if ($stored !== $count) {
            throw new \RuntimeException(
                "Paritas sinkronisasi gagal: sumber {$count} baris, tabel shipments {$stored} baris."
            );
        }

        $dupTotalRows = array_sum($duplicates);
        $dupExtra = $dupTotalRows - count($duplicates);

        if (count($duplicates) > 50) {
            $duplicates = array_slice($duplicates, 0, 50, true);
        }

        return [
            'imported' => $count,
            'stats' => [
                'raw_rows' => $rawRows,
                'valid_rows' => $count,
                'stored_rows' => $stored,
                'skipped_empty' => $skippedEmpty,
                'dup_extra' => $dupExtra,
                'dup_total_rows' => $dupTotalRows,
                'dup_keys' => count($duplicates),
                'unique_rows' => $count - $dupExtra,
                'duplicates' => $duplicates,
                'resi_kosong' => $resiCounts[self::RESI_CATATAN_KOSONG],
                'resi_no_fungsi' => $resiCounts[self::RESI_CATATAN_NO_FUNGSI],
                'resi_nomor_rusak' => $resiCounts[self::RESI_CATATAN_NOMOR_RUSAK],
                'resi_format' => $resiCounts[self::RESI_CATATAN_FORMAT],
                'resi_duplikat' => $resiCounts[self::RESI_CATATAN_DUPLIKAT],
                'tanggal_ho_vendor' => $sumberTanggal[self::SUMBER_TANGGAL_HO_VENDOR],
                'tanggal_sartrans' => $sumberTanggal[self::SUMBER_TANGGAL_SARTRANS],
                'tanggal_completed' => $sumberTanggal[self::SUMBER_TANGGAL_COMPLETED],
                'tanggal_tidak_ada' => $sumberTanggal['TIDAK_ADA'],
            ],
        ];
    }

    protected function isRepeatedHeaderRow(array $data): bool
    {
        return $data['no_resi'] === 'No Resi'
            && strtolower((string) $data['nama_sekolah']) === 'nama sekolah';
    }

    /**
     * Baris tanpa `no_resi` maupun `NAMA SEKOLAH` adalah artefak ekspor dari
     * formula yang meluas ke area kosong, bukan kiriman. Baris dengan salah satu
     * kolom itu terisi tetap disimpan apa adanya (termasuk resi bermasalah).
     */
    protected function isEmptyBusinessRow(array $data): bool
    {
        return $data['no_resi'] === '' && $data['nama_sekolah'] === '';
    }

    /**
     * Hitung kemunculan tiap no resi pada sumber lewat satu pass penuh di file
     * (tanpa menyentuh database) supaya penandaan duplikat bisa langsung ditulis
     * saat insert. Pendekatan ini menghindari `whereIn('no_resi', ...)` yang
     * membuat MySQL mencoba konversi ke DOUBLE untuk nilai resi non-numerik.
     *
     * @return array<string, int>
     */
    protected function countResiOccurrences(string $file, array $index): array
    {
        $csv = new SplFileObject($file, 'r');
        $csv->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

        $occurrences = [];

        while (! $csv->eof()) {
            $row = $csv->fgetcsv();

            if ($row === false) {
                continue;
            }

            $row = array_map(fn ($cell) => trim((string) $cell), $row);

            if (! isset($row[0])) {
                continue;
            }

            $noResi = (string) ($this->pick($row, $index, $this->columnMap['no_resi']) ?? '');

            $occurrences[$noResi] = ($occurrences[$noResi] ?? 0) + 1;
        }

        return $occurrences;
    }

    /**
     * Singkatan yang harus tetap kapital agar tidak diubah Title Case.
     */
    private const PROVINSI_SINGKATAN = ['DKI', 'DIY', 'NTB', 'NTT'];

    /**
     * Sumber mencampur penulisan nama provinsi: sebagian memakai awalan
     * "Prov.", sebagian kapital penuh (ACEH, JAMBI). Normalisasi ini membuat
     * satu provinsi hanya punya satu nilai sehingga filter & grafik tidak
     * terpecah.
     */
    protected function normalizeProvinsi($value): ?string
    {
        $provinsi = $this->nullableString($value);

        if ($provinsi === null) {
            return null;
        }

        $provinsi = preg_replace('/^prov\.?\s+/iu', '', $provinsi);
        $provinsi = preg_replace('/\s+/u', ' ', trim((string) $provinsi));

        if ($provinsi === '') {
            return null;
        }

        $words = array_map(function (string $word): string {
            $upper = mb_strtoupper($word, 'UTF-8');

            if (in_array($upper, self::PROVINSI_SINGKATAN, true)) {
                return $upper;
            }

            return mb_convert_case($word, MB_CASE_TITLE, 'UTF-8');
        }, explode(' ', $provinsi));

        return implode(' ', $words);
    }

    /**
     * Klasifikasi nomor resi.
     *
     * Pendekatan blacklist dipakai, bukan whitelist, agar nomor resi berformat
     * huruf/strip tetap dianggap sah. Yang ditandai hanya nilai yang jelas
     * rusak: kosong, error sheet, scientific notation, atau mengandung
     * pemisah angka gaya Indonesia (`.` dan `,`).
     *
     * @return array{0: bool, 1: ?string}
     */
    protected function classifyResi(string $noResi, bool $isDuplicate = false): array
    {
        $resi = trim($noResi);

        if ($resi === '') {
            return [false, self::RESI_CATATAN_KOSONG];
        }

        if (strcasecmp($resi, '#N/A') === 0) {
            return [false, self::RESI_CATATAN_NO_FUNGSI];
        }

        if (preg_match('/^[+-]?[\d.,]+[eE][+-]?\d+$/', $resi) === 1) {
            return [false, self::RESI_CATATAN_NOMOR_RUSAK];
        }

        if (preg_match('/[\s.,;#]/', $resi) === 1) {
            return [false, self::RESI_CATATAN_FORMAT];
        }

        return [true, $isDuplicate ? self::RESI_CATATAN_DUPLIKAT : null];
    }

    protected function buildHeaderIndex(array $row): array
    {
        $map = [];

        foreach ($row as $col => $header) {
            if ($header !== '') {
                $map[strtolower($header)] = $col;
            }
        }

        return $map;
    }

    protected function mapRow(array $row, array $index): array
    {
        $rawStatusInvoice = $this->pick($row, $index, $this->columnMap['status_invoice']);

        if (is_string($rawStatusInvoice) && ! $this->isExcelError(trim($rawStatusInvoice))) {
            $normalized = strtoupper(trim($rawStatusInvoice));

            $statusInvoice = ($normalized === 'TRUE' || $normalized === 'PAID') ? 'PAID' : null;
        } else {
            $statusInvoice = null;
        }

        $namaFunder = $this->nullableString($this->pick($row, $index, $this->columnMap['nama_funder']));

        $tanggal = $this->resolveTanggal($row, $index);

        return [
            'no_resi' => (string) ($this->pick($row, $index, $this->columnMap['no_resi']) ?? ''),
            'nomor_redock' => $this->nullableString($this->pick($row, $index, $this->columnMap['nomor_redock'])),
            'delivery_order' => $this->nullableString($this->pick($row, $index, $this->columnMap['delivery_order'])),
            'nama_sekolah' => (string) ($this->pick($row, $index, $this->columnMap['nama_sekolah']) ?? ''),
            'nama_penerima' => $this->nullableString($this->pick($row, $index, $this->columnMap['nama_penerima'])),
            'provinsi' => $this->normalizeProvinsi($this->pick($row, $index, $this->columnMap['provinsi'])),
            'daerah' => $this->nullableString($this->pick($row, $index, $this->columnMap['daerah'])),
            'kota_kabupaten' => $this->nullableString($this->pick($row, $index, $this->columnMap['kota_kabupaten'])),
            'kecamatan' => $this->nullableString($this->pick($row, $index, $this->columnMap['kecamatan'])),
            'vendor_mm' => $this->nullableString($this->pick($row, $index, $this->columnMap['vendor_mm'])),
            'kode_funder' => $this->nullableString($this->pick($row, $index, $this->columnMap['kode_funder'])),
            'nama_funder' => (string) ($namaFunder ?? 'Panthera'),
            'vendor_lm' => $this->nullableString($this->pick($row, $index, $this->columnMap['vendor_lm'])),
            'tanggal_manifest' => $tanggal['tanggal_manifest'],
            'tanggal_efektif' => $tanggal['tanggal_efektif'],
            'tanggal_sumber' => $tanggal['tanggal_sumber'],
            'completed_date' => $this->toDate($this->pick($row, $index, $this->columnMap['completed_date'])),
            'tgl_sampai_kota_tujuan' => $this->toDate($this->pick($row, $index, $this->columnMap['tgl_sampai_kota_tujuan'])),
            'aging' => $this->toNullableNonNegativeInt($this->pick($row, $index, $this->columnMap['aging'])),
            'status_akhir' => $this->enumString($this->pick($row, $index, $this->columnMap['status_akhir']), 'On Process'),
            'status_instalasi' => $this->enumString($this->pick($row, $index, $this->columnMap['status_instalasi']), 'BELUM'),
            'harga_per_shipment' => $this->toDecimal($this->pick($row, $index, $this->columnMap['harga_per_shipment'])),
            'status_invoice' => $statusInvoice,
            'stagging' => $this->nullableString($this->pick($row, $index, $this->columnMap['stagging'])),
            'sla' => $this->toNullableInt($this->pick($row, $index, $this->columnMap['sla'])),
            'sla_result' => $this->nullableString($this->pick($row, $index, $this->columnMap['sla_result'])),
            'bast_tgl_balik' => $this->toDate($this->pick($row, $index, $this->columnMap['bast_tgl_balik'])),
            'bast_tgl_ke_finance' => $this->toDate($this->pick($row, $index, $this->columnMap['bast_tgl_ke_finance'])),
        ];
    }

    /**
     * Tentukan tanggal acuan baris.
     *
     * `tanggal_manifest` hanya diisi dari tanggal HO ke vendor yang masuk akal.
     * Kolom primer `Tanggal HO ke Vendor` dipakai langsung, kecuali jelas lebih
     * baru dari `Completed date`. Alias cadangan (mis. `Tgl HO dari SarTrans`)
     * hanya diterima bila ada `Completed date` yang mengonfirmasinya, karena
     * kolom tersebut pada sumber berisi tanggal entri massal (mis. 13/08/2026
     * untuk ribuan baris) dan bukan tanggal HO per kiriman.
     *
     * Bila tidak ada tanggal HO yang lolos, `Completed date` dipakai sebagai
     * `tanggal_efektif` supaya rentang tanggal dashboard tetap terisi, dan
     * `tanggal_manifest` sengaja dibiarkan NULL agar KPI durasi tidak menghitung
     * turunan yang bukan tanggal HO sebenarnya.
     *
     * @return array{tanggal_manifest: ?string, tanggal_efektif: ?string, tanggal_sumber: ?string}
     */
    protected function resolveTanggal(array $row, array $index): array
    {
        $completedDate = $this->toDate($this->pick($row, $index, $this->columnMap['completed_date']));

        $headers = array_values($this->columnMap['tanggal_manifest']);

        foreach ($headers as $position => $header) {
            $candidate = $this->toDate($this->pick($row, $index, [$header]));

            if (! $this->isPlausibleDate($candidate)) {
                continue;
            }

            if ($position === 0) {
                if ($completedDate !== null && $candidate > $completedDate) {
                    continue;
                }
            } elseif ($completedDate === null || $candidate > $completedDate) {
                continue;
            }

            return [
                'tanggal_manifest' => $candidate,
                'tanggal_efektif' => $candidate,
                'tanggal_sumber' => $position === 0
                    ? self::SUMBER_TANGGAL_HO_VENDOR
                    : self::SUMBER_TANGGAL_SARTRANS,
            ];
        }

        if ($this->isPlausibleDate($completedDate)) {
            return [
                'tanggal_manifest' => null,
                'tanggal_efektif' => $completedDate,
                'tanggal_sumber' => self::SUMBER_TANGGAL_COMPLETED,
            ];
        }

        return [
            'tanggal_manifest' => null,
            'tanggal_efektif' => null,
            'tanggal_sumber' => null,
        ];
    }

    /**
     * Tolak tanggal dummy (mis. 01/01/1900) maupun tanggal yang secara fisik
     * tidak mungkin terjadi.
     */
    protected function isPlausibleDate(?string $date): bool
    {
        if ($date === null) {
            return false;
        }

        $year = (int) substr($date, 0, 4);

        return $year >= 2000 && $year <= (int) now()->addYear()->format('Y');
    }

    /**
     * Ambil nilai pertama yang benar-benar terisi. Kolom alias yang kosong tidak
     * boleh menghentikan proses fallback.
     */
    protected function pick(array $row, array $index, array $headers)
    {
        foreach ($headers as $header) {
            $col = $index[strtolower($header)] ?? null;

            if ($col === null || ! array_key_exists($col, $row)) {
                continue;
            }

            $value = $row[$col];

            if ($value !== null && trim((string) $value) !== '') {
                return $value;
            }
        }

        return null;
    }

    protected function flush(array $chunks): void
    {
        Shipment::query()->insert($chunks);
    }

    protected function nullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '' || $this->isExcelError($value)) {
            return null;
        }

        return $value;
    }

    protected function isExcelError(string $value): bool
    {
        return in_array(strtoupper($value), self::EXCEL_ERROR_VALUES, true);
    }

    /**
     * String yang boleh dipakai sebagai label enum: error Excel dipetakan ke
     * nilai bawaan kolom, bukan disimpan apa adanya.
     */
    protected function enumString($value, string $default): string
    {
        return $this->nullableString($value) ?? $default;
    }

    protected function toDate($value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $value = trim((string) $value);

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})/', $value, $m)) {
            $year = $m[3];
            if (strlen($year) === 2) {
                $year = '20'.$year;
            }

            $day = (int) $m[1];
            $month = (int) $m[2];

            if (! checkdate($month, $day, (int) $year)) {
                return null;
            }

            return sprintf('%04d-%02d-%02d', (int) $year, $month, $day);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value, $m)) {
            return $m[0];
        }

        return null;
    }

    /**
     * `Aging` pada sumber bisa bernilai negatif untuk baris tanpa tanggal acuan
     * (mis. -45960 pada Undelivered). Nilai negatif adalah artefak perhitungan
     * sheet, bukan umur pengiriman, jadi disimpan sebagai NULL agar MySQL
     * mengabaikannya pada `AVG(aging)`.
     */
    protected function toNullableNonNegativeInt($value): ?int
    {
        $value = trim((string) $value);

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        $number = (int) $value;

        return $number < 0 ? null : $number;
    }

    /**
     * Kolom SLA pada sumber berisi tanggal, bukan angka. Nilai non-numerik
     * disimpan sebagai NULL agar tidak terbaca sebagai SLA 0 hari.
     */
    protected function toNullableInt($value): ?int
    {
        if (is_numeric(trim((string) $value))) {
            return (int) $value;
        }

        return null;
    }

    protected function toDecimal($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        return 0;
    }

    /**
     * `no_resi` sengaja tidak disanitasi. Sumber memuat nilai yang rusak
     * (`1,01E+15`, `#N/A`, harga) yang perlu tetap tersimpan agar bisa ditelusuri
     * kembali ke baris sheet asalnya; abnormality ditandai lewat `resi_catatan`.
     * Memangkas karakter non-alfanumerik justru mengubah `1,01E+15` menjadi resi
     * palsu `10115` dan merusak deteksi baris header yang membandingkan
     * `no_resi` dengan nilai literal `No Resi`.
     */
}
