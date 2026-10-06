<?php

namespace Tests\Feature;

use App\Models\Shipment;
use App\Services\ShipmentSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ShipmentSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = [
        'No Resi',
        'NAMA SEKOLAH',
        'Provinsi',
        'Vendor LM',
        'Tanggal HO ke Vendor',
        'Tgl HO dari SarTrans',
        'Completed date',
        'Status Akhir',
        'Update Stagging',
        'SLA for DB',
        'TGL HO BAST KE FINANCE',
        'Founder',
        'Code Funder',
    ];

    private string $csv = '';

    protected function setUp(): void
    {
        parent::setUp();

        // Satu stub saja: memanggil Http::fake() berkali-kali tidak menimpa
        // stub sebelumnya, sehingga sinkronisasi kedua masih menerima CSV lama.
        Http::fake(fn () => Http::response($this->csv, 200));

        config(['services.google_sheets.csv_url' => 'https://example.test/sheet']);
    }

    private function fakeCsv(array $rows): void
    {
        $this->csv = implode("\n", array_map(
            fn (array $cells): string => implode(',', array_map(
                fn ($cell): string => '"'.str_replace('"', '""', (string) $cell).'"',
                $cells
            )),
            array_merge([self::HEADER], $rows)
        ));
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<int, string>
     */
    private function row(array $overrides = []): array
    {
        $defaults = [
            'No Resi' => 'RESI-1',
            'NAMA SEKOLAH' => 'SD Negeri 1',
            'Provinsi' => 'Prov. Jawa Barat',
            'Vendor LM' => 'BOMA',
            'Tanggal HO ke Vendor' => '10/10/2025',
            'Tgl HO dari SarTrans' => '08/10/2025',
            'Completed date' => '23/10/2025',
            'Status Akhir' => 'Completed',
            'Update Stagging' => 'BERANGKAT DARI CROSSDOCK',
            'SLA for DB' => '',
            'TGL HO BAST KE FINANCE' => '',
            'Founder' => 'DB Logistics',
            'Code Funder' => 'DB-01',
        ];

        return array_map(
            fn (string $header): string => (string) ($overrides[$header] ?? $defaults[$header]),
            self::HEADER
        );
    }

    private function sync(): int
    {
        return app(ShipmentSyncService::class)->sync();
    }

    public function test_sync_keeps_every_source_row_including_duplicates(): void
    {
        $this->fakeCsv([
            $this->row(['No Resi' => 'RESI-1']),
            $this->row(['No Resi' => 'RESI-1']),
            $this->row(['No Resi' => 'RESI-2']),
        ]);

        $this->assertSame(3, $this->sync());
        $this->assertSame(3, Shipment::count());
    }

    public function test_duplicate_resi_is_flagged_on_every_row(): void
    {
        $this->fakeCsv([
            $this->row(['No Resi' => 'RESI-1']),
            $this->row(['No Resi' => 'RESI-1']),
        ]);

        $this->sync();

        $this->assertSame(2, Shipment::where('resi_catatan', ShipmentSyncService::RESI_CATATAN_DUPLIKAT)->count());
        $this->assertSame(0, Shipment::where('resi_valid', false)->count());
    }

    public static function unusableResiProvider(): array
    {
        return [
            'scientific notation' => ['1,01E+15', ShipmentSyncService::RESI_CATATAN_NOMOR_RUSAK],
            'not available' => ['#N/A', ShipmentSyncService::RESI_CATATAN_NO_FUNGSI],
            'price pasted into resi' => ['1.009.400.821.166.140,00', ShipmentSyncService::RESI_CATATAN_FORMAT],
            'empty' => ['', ShipmentSyncService::RESI_CATATAN_KOSONG],
        ];
    }

    #[DataProvider('unusableResiProvider')]
    public function test_unusable_resi_is_stored_but_marked_invalid(string $noResi, string $expected): void
    {
        $this->fakeCsv([$this->row(['No Resi' => $noResi])]);

        $this->sync();

        $shipment = Shipment::firstOrFail();

        $this->assertSame(1, Shipment::count());
        $this->assertFalse($shipment->resi_valid);
        $this->assertSame($expected, $shipment->resi_catatan);
    }

    public function test_resi_with_numeric_suffix_is_treated_as_valid(): void
    {
        $this->fakeCsv([$this->row(['No Resi' => '20212771_1'])]);

        $this->sync();

        $shipment = Shipment::firstOrFail();

        $this->assertTrue($shipment->resi_valid);
        $this->assertNull($shipment->resi_catatan);
    }

    public function test_dummy_1900_date_from_source_is_never_stored(): void
    {
        $this->fakeCsv([
            $this->row([
                'No Resi' => 'RESI-1',
                'Tanggal HO ke Vendor' => '',
                'Tgl HO dari SarTrans' => '01/01/1900',
            ]),
        ]);

        $this->sync();

        $shipment = Shipment::firstOrFail();

        $this->assertNull($shipment->tanggal_manifest);
        $this->assertSame('2025-10-23', $shipment->tanggal_efektif->format('Y-m-d'));
        $this->assertSame(ShipmentSyncService::SUMBER_TANGGAL_COMPLETED, $shipment->tanggal_sumber);
    }

    public function test_manifest_after_completed_date_is_rejected_in_favour_of_completed_date(): void
    {
        $this->fakeCsv([
            $this->row([
                'No Resi' => 'RESI-1',
                'Tanggal HO ke Vendor' => '',
                'Tgl HO dari SarTrans' => '13/08/2026',
            ]),
        ]);

        $this->sync();

        $shipment = Shipment::firstOrFail();

        $this->assertNull($shipment->tanggal_manifest);
        $this->assertSame('2025-10-23', $shipment->tanggal_efektif->format('Y-m-d'));
        $this->assertSame(ShipmentSyncService::SUMBER_TANGGAL_COMPLETED, $shipment->tanggal_sumber);
    }

    public function test_real_ho_date_is_kept_and_becomes_the_effective_date(): void
    {
        $this->fakeCsv([$this->row()]);

        $this->sync();

        $shipment = Shipment::firstOrFail();

        $this->assertSame('2025-10-10', $shipment->tanggal_manifest->format('Y-m-d'));
        $this->assertSame('2025-10-10', $shipment->tanggal_efektif->format('Y-m-d'));
        $this->assertSame(ShipmentSyncService::SUMBER_TANGGAL_HO_VENDOR, $shipment->tanggal_sumber);
    }

    public function test_funder_and_code_funder_are_read_from_source_columns(): void
    {
        $this->fakeCsv([$this->row(['Founder' => 'DB Logistics', 'Code Funder' => 'DB-01'])]);

        $this->sync();

        $shipment = Shipment::firstOrFail();

        $this->assertSame('DB Logistics', $shipment->nama_funder);
        $this->assertSame('DB-01', $shipment->kode_funder);
    }

    public function test_provinsi_prefix_and_casing_are_normalized(): void
    {
        $this->fakeCsv([
            $this->row(['No Resi' => 'RESI-1', 'Provinsi' => 'Prov. Sumatera Utara']),
            $this->row(['No Resi' => 'RESI-2', 'Provinsi' => 'ACEH']),
            $this->row(['No Resi' => 'RESI-3', 'Provinsi' => 'DKI Jakarta']),
        ]);

        $this->sync();

        $this->assertEqualsCanonicalizing(
            ['Sumatera Utara', 'Aceh', 'DKI Jakarta'],
            Shipment::pluck('provinsi')->all()
        );
    }

    public function test_date_with_single_digit_day_and_month_is_parsed(): void
    {
        $this->fakeCsv([
            $this->row(['Tanggal HO ke Vendor' => '5/8/2025']),
            $this->row(['No Resi' => 'RESI-2', 'TGL HO BAST KE FINANCE' => '26/8/2026']),
        ]);

        $this->sync();

        $this->assertSame('2025-08-05', Shipment::where('no_resi', 'RESI-1')->firstOrFail()->tanggal_manifest->format('Y-m-d'));
        $this->assertSame('2026-08-26', Shipment::where('no_resi', 'RESI-2')->firstOrFail()->bast_tgl_ke_finance->format('Y-m-d'));
    }

    public function test_sla_column_holding_a_date_is_stored_as_null_instead_of_zero(): void
    {
        $this->fakeCsv([$this->row(['SLA for DB' => '26/8/2026'])]);

        $this->sync();

        $this->assertNull(Shipment::firstOrFail()->sla);
    }

    public function test_repeated_header_row_is_skipped(): void
    {
        $this->fakeCsv([
            $this->row(),
            array_merge(self::HEADER, ['No Resi', 'nama sekolah']),
        ]);

        $this->assertSame(1, $this->sync());
    }

    public function test_source_stats_count_only_data_rows(): void
    {
        $this->fakeCsv([
            $this->row(['No Resi' => 'RESI-1']),
            $this->row(['No Resi' => 'RESI-2']),
            array_merge(self::HEADER, ['No Resi', 'nama sekolah']),
        ]);

        $this->sync();

        $stats = app(ShipmentSyncService::class)->sourceStats();

        // Header yang ikut terbaca pada pass kedua tidak boleh dihitung
        // sebagai baris data.
        $this->assertSame(2, $stats['raw_rows']);
        $this->assertSame(2, $stats['valid_rows']);
        $this->assertSame(2, $stats['stored_rows']);
        $this->assertArrayNotHasKey('skipped_empty', $stats);
    }

    public function test_duplicate_stats_separate_valid_labels_from_invalid_ones(): void
    {
        $this->fakeCsv([
            $this->row(['No Resi' => 'RESI-1']),
            $this->row(['No Resi' => 'RESI-1']),
            $this->row(['No Resi' => '1,01E+15']),
            $this->row(['No Resi' => '1,01E+15']),
        ]);

        $this->sync();

        $stats = app(ShipmentSyncService::class)->sourceStats();

        $this->assertSame(4, $stats['dup_total_rows']);
        $this->assertSame(2, $stats['dup_keys']);
        $this->assertSame(2, $stats['resi_duplikat']);
        $this->assertSame(2, $stats['resi_nomor_rusak']);
        // 4 baris berbagi resi - 2 baris berlabel Duplikat = 2 yang sudah
        // tercakup kategori "resi bermasalah".
        $this->assertSame(2, $stats['dup_total_rows'] - $stats['resi_duplikat']);
    }

    public function test_lead_time_scope_excludes_rows_without_a_real_manifest_date(): void
    {
        $this->fakeCsv([
            $this->row([
                'No Resi' => 'RESI-1',
                'Tanggal HO ke Vendor' => '10/10/2025',
                'Completed date' => '20/10/2025',
            ]),
            $this->row([
                'No Resi' => 'RESI-2',
                'Tanggal HO ke Vendor' => '',
                'Tgl HO dari SarTrans' => '01/01/1900',
                'Completed date' => '23/10/2025',
            ]),
        ]);

        $this->sync();

        // Nilai DATEDIFF bertanda berbeda antara driver MySQL dan SQLite,
        // jadi yang diuji di sini adalah baris mana yang dihitung, bukan tandanya.
        $included = Shipment::withRealManifestDate()->pluck('no_resi')->all();

        $this->assertSame(['RESI-1'], $included);
        $this->assertNull(Shipment::where('no_resi', 'RESI-2')->firstOrFail()->tanggal_manifest);
        $this->assertSame(
            '2025-10-23',
            Shipment::where('no_resi', 'RESI-2')->firstOrFail()->tanggal_efektif->format('Y-m-d')
        );
    }

    public function test_sync_replaces_previous_rows_instead_of_merging_them(): void
    {
        $this->fakeCsv([$this->row(['No Resi' => 'RESI-1'])]);
        $this->sync();

        $this->fakeCsv([$this->row(['No Resi' => 'RESI-9'])]);
        $this->sync();

        $this->assertSame(1, Shipment::count());
        $this->assertSame('RESI-9', Shipment::firstOrFail()->no_resi);
    }
}
