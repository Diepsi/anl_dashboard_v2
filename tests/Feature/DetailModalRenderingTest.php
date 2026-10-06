<?php

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\User;
use App\Services\ShipmentSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DetailModalRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_shipments_table_opens_detail_by_id(): void
    {
        $user = User::factory()->create();
        $shipment = Shipment::factory()->create(['no_resi' => 'RESI-TEPAT']);

        $response = $this->actingAs($user)->get('/pengiriman');

        $response->assertOk();
        $response->assertSee('data-shipment-id="'.$shipment->id.'"', escape: false);
        $response->assertSee("/api/shipments/detail?id=' + encodeURIComponent(id)", escape: false);
    }

    public function test_shipments_table_shows_resi_badges_instead_of_bare_resi(): void
    {
        $user = User::factory()->create();
        Shipment::factory()->create([
            'no_resi' => '1,01E+15',
            'resi_valid' => false,
            'resi_catatan' => ShipmentSyncService::RESI_CATATAN_NOMOR_RUSAK,
        ]);

        $response = $this->actingAs($user)->get('/pengiriman');

        $response->assertOk();
        $response->assertSee('Resi bermasalah');
        $response->assertSee('1,01E+15');
    }

    public function test_dashboard_modal_keys_rows_by_id(): void
    {
        $user = User::factory()->create();
        Shipment::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        // Baris modal harus dikunci dengan id karena no_resi bisa duplikat.
        $response->assertSee(':key="row.id"', escape: false);
        $response->assertDontSee(':key="row.no_resi"', escape: false);
    }
}
