<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DashboardBannerTest extends TestCase
{
    use RefreshDatabase;

    private function stats(array $overrides = []): array
    {
        return array_merge([
            'raw_rows' => 31709,
            'valid_rows' => 31709,
            'stored_rows' => 31709,
            'dup_extra' => 646,
            'dup_total_rows' => 671,
            'dup_keys' => 25,
            'unique_rows' => 31063,
            'duplicates' => ['19301115133501' => 3],
            'resi_kosong' => 0,
            'resi_no_fungsi' => 38,
            'resi_nomor_rusak' => 586,
            'resi_format' => 1,
            'resi_duplikat' => 47,
            'tanggal_ho_vendor' => 3369,
            'tanggal_sartrans' => 0,
            'tanggal_completed' => 28292,
            'tanggal_tidak_ada' => 48,
        ], $overrides);
    }

    private function render(array $overrides = []): TestResponse
    {
        cache()->put('shipments.source_stats', $this->stats($overrides));

        return $this->actingAs(User::factory()->create())->get('/');
    }

    public function test_banner_reports_duplicate_rows_split_between_labels(): void
    {
        $response = $this->render();

        $response->assertOk();
        $response->assertSee('671');
        $response->assertSee('25 nomor resi');
        $response->assertSee('47 di antaranya berformat valid');
        $response->assertSee('624 sisanya sudah termasuk');
    }

    public function test_banner_does_not_claim_any_rows_were_skipped(): void
    {
        $response = $this->render();

        $response->assertOk();
        $response->assertDontSee('diabaikan');
        $response->assertDontSee('baris tanpa No Resi');
    }

    public function test_banner_is_hidden_without_source_stats(): void
    {
        cache()->forget('shipments.source_stats');

        $response = $this->actingAs(User::factory()->create())->get('/');

        $response->assertOk();
        $response->assertDontSee('Sumber GSheet:');
    }
}
