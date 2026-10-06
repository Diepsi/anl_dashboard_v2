<?php

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\User;
use App\Services\ShipmentSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentsFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_filter_limits_shipments_and_exposes_vendor_options(): void
    {
        $user = User::factory()->create();
        $matching = Shipment::factory()->create([
            'vendor_lm' => 'Vendor A',
        ]);
        Shipment::factory()->create([
            'vendor_lm' => 'Vendor B',
        ]);
        Shipment::factory()->create([
            'vendor_lm' => null,
        ]);
        Shipment::factory()->create([
            'vendor_lm' => '',
        ]);

        $response = $this->actingAs($user)
            ->get('/pengiriman?vendor=Vendor+A');

        $response->assertOk();
        $this->assertSame('Vendor A', $response->viewData('vendor'));
        $this->assertSame(
            [$matching->no_resi],
            $response->viewData('shipments')->getCollection()->pluck('no_resi')->all()
        );
        $this->assertEqualsCanonicalizing(
            ['Vendor A', 'Vendor B'],
            $response->viewData('vendors')->all()
        );
    }

    public function test_vendor_filter_combines_with_existing_filters(): void
    {
        $user = User::factory()->create();
        $matching = Shipment::factory()->create([
            'vendor_lm' => 'Vendor A',
            'status_akhir' => 'Completed',
        ]);
        Shipment::factory()->create([
            'vendor_lm' => 'Vendor A',
            'status_akhir' => 'On Delivery',
        ]);
        Shipment::factory()->create([
            'vendor_lm' => 'Vendor B',
            'status_akhir' => 'Completed',
        ]);

        $response = $this->actingAs($user)
            ->get('/pengiriman?vendor=Vendor+A&status=Completed');

        $response->assertOk();
        $this->assertSame(
            [$matching->no_resi],
            $response->viewData('shipments')->getCollection()->pluck('no_resi')->all()
        );
    }

    public function test_vendor_filter_is_accepted_by_export(): void
    {
        $user = User::factory()->create();
        Shipment::factory()->create([
            'vendor_lm' => 'Vendor A',
        ]);

        $response = $this->actingAs($user)
            ->post('/pengiriman/export', [
                'vendor' => 'Vendor A',
            ]);

        $response->assertDownload();
    }

    public function test_invalid_resi_filter_returns_only_flagged_rows(): void
    {
        $user = User::factory()->create();
        $invalid = Shipment::factory()->create([
            'no_resi' => '1,01E+15',
            'resi_valid' => false,
            'resi_catatan' => ShipmentSyncService::RESI_CATATAN_NOMOR_RUSAK,
        ]);
        Shipment::factory()->create([
            'no_resi' => 'RESI-SEHAT',
            'resi_valid' => true,
            'resi_catatan' => null,
        ]);
        Shipment::factory()->create([
            'no_resi' => 'RESI-DUP',
            'resi_valid' => true,
            'resi_catatan' => ShipmentSyncService::RESI_CATATAN_DUPLIKAT,
        ]);

        $response = $this->actingAs($user)->get('/pengiriman?resi=invalid');

        $response->assertOk();
        $this->assertSame(
            [$invalid->no_resi],
            $response->viewData('shipments')->getCollection()->pluck('no_resi')->all()
        );
    }

    public function test_duplicate_resi_filter_returns_only_flagged_rows(): void
    {
        $user = User::factory()->create();
        $duplicate = Shipment::factory()->create([
            'no_resi' => 'RESI-DUP',
            'resi_valid' => true,
            'resi_catatan' => ShipmentSyncService::RESI_CATATAN_DUPLIKAT,
        ]);
        Shipment::factory()->create([
            'no_resi' => 'RESI-SEHAT',
            'resi_valid' => true,
            'resi_catatan' => null,
        ]);
        Shipment::factory()->create([
            'no_resi' => '1,01E+15',
            'resi_valid' => false,
            'resi_catatan' => ShipmentSyncService::RESI_CATATAN_NOMOR_RUSAK,
        ]);

        $response = $this->actingAs($user)->get('/pengiriman?resi=duplicate');

        $response->assertOk();
        $this->assertSame(
            [$duplicate->no_resi],
            $response->viewData('shipments')->getCollection()->pluck('no_resi')->all()
        );
    }

    public function test_resi_filter_is_accepted_by_export(): void
    {
        $user = User::factory()->create();
        Shipment::factory()->create([
            'no_resi' => '1,01E+15',
            'resi_valid' => false,
            'resi_catatan' => ShipmentSyncService::RESI_CATATAN_NOMOR_RUSAK,
        ]);

        $response = $this->actingAs($user)
            ->post('/pengiriman/export', [
                'resi' => 'invalid',
            ]);

        $response->assertDownload();
    }

    public function test_shipments_table_shows_effective_date_and_resi_badges(): void
    {
        $user = User::factory()->create();
        Shipment::factory()->create([
            'no_resi' => '1,01E+15',
            'resi_valid' => false,
            'resi_catatan' => ShipmentSyncService::RESI_CATATAN_NOMOR_RUSAK,
            'tanggal_manifest' => '2026-01-10',
            'tanggal_efektif' => '2026-01-10',
        ]);

        $response = $this->actingAs($user)->get('/pengiriman');

        $response->assertOk();
        $response->assertSee('Tanggal Acuan');
        $response->assertSee('Resi bermasalah');
    }

    public function test_detail_endpoint_returns_the_row_matching_the_given_id(): void
    {
        $user = User::factory()->create();
        $first = Shipment::factory()->create(['no_resi' => 'RESI-SAMA']);
        $second = Shipment::factory()->create(['no_resi' => 'RESI-SAMA']);

        $response = $this->actingAs($user)
            ->getJson("/api/shipments/detail?id={$second->id}");

        $response->assertOk();
        // no_resi sengaja sama: endpoint harus membedakan lewat id.
        $this->assertSame($second->id, $response->json('id'));
        $this->assertNotSame($first->id, $response->json('id'));
    }

    public function test_detail_endpoint_requires_an_id(): void
    {
        $user = User::factory()->create();
        Shipment::factory()->create();

        $this->actingAs($user)->getJson('/api/shipments/detail')
            ->assertStatus(422);

        $this->actingAs($user)->getJson('/api/shipments/detail?id=0')
            ->assertStatus(422);
    }

    public function test_detail_endpoint_returns_404_for_unknown_id(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/shipments/detail?id=999999')
            ->assertStatus(404);
    }
}
