<?php

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardVendorFilterTest extends TestCase
{
    use RefreshDatabase;

    private function seedShipments(): void
    {
        Shipment::factory()->create([
            'no_resi' => 'A-1', 'vendor_lm' => 'VENDOR A', 'status_akhir' => 'Completed',
            'sla_result' => 'Meet SLA', 'stagging' => 'DELIVER', 'provinsi' => 'Aceh',
            'tanggal_manifest' => '2026-01-10', 'tanggal_efektif' => '2026-01-10',
            'completed_date' => '2026-01-15', 'aging' => 5,
        ]);
        Shipment::factory()->create([
            'no_resi' => 'A-2', 'vendor_lm' => 'VENDOR A', 'status_akhir' => 'Completed',
            'sla_result' => 'Meet SLA', 'stagging' => 'DELIVER', 'provinsi' => 'Aceh',
            'tanggal_manifest' => '2026-01-11', 'tanggal_efektif' => '2026-01-11',
            'completed_date' => '2026-01-16', 'aging' => 5,
        ]);
        Shipment::factory()->create([
            'no_resi' => 'A-3', 'vendor_lm' => 'VENDOR A', 'status_akhir' => 'Undelivered',
            'sla_result' => 'Out SLA', 'stagging' => 'DELIVER', 'provinsi' => 'Riau',
            'tanggal_manifest' => '2026-01-12', 'tanggal_efektif' => '2026-01-12', 'aging' => 20,
        ]);
        Shipment::factory()->create([
            'no_resi' => 'B-1', 'vendor_lm' => 'VENDOR B', 'status_akhir' => 'Completed',
            'sla_result' => 'Meet SLA', 'stagging' => 'DELIVER', 'provinsi' => 'Aceh',
            'tanggal_manifest' => '2026-01-10', 'tanggal_efektif' => '2026-01-10',
            'completed_date' => '2026-01-18', 'aging' => 8,
        ]);
        Shipment::factory()->create([
            'no_resi' => 'B-2', 'vendor_lm' => 'VENDOR B', 'status_akhir' => 'Undelivered',
            'sla_result' => 'Out SLA', 'stagging' => 'DELIVER', 'provinsi' => 'Riau',
            'tanggal_manifest' => '2026-01-12', 'tanggal_efektif' => '2026-01-12', 'aging' => 25,
        ]);
    }

    public function test_dashboard_kpis_are_scoped_to_selected_vendor(): void
    {
        $this->seedShipments();

        $response = $this->actingAs(User::factory()->create())->get('/?vendor=VENDOR+A');

        $response->assertOk();
        $this->assertSame('VENDOR A', $response->viewData('vendor'));
        $this->assertSame(3, $response->viewData('totalShipment'));
        $this->assertSame(2, $response->viewData('completed'));
        $this->assertSame(1, $response->viewData('undeliveredCount'));
        $this->assertSame(2, $response->viewData('withinSla'));
        $this->assertSame(1, $response->viewData('overSla'));
        $this->assertSame(67.0, $response->viewData('slaPct'));
        $this->assertSame(67.0, $response->viewData('completionRate'));
    }

    public function test_dashboard_keeps_every_metric_unfiltered_without_vendor_parameter(): void
    {
        $this->seedShipments();

        $response = $this->actingAs(User::factory()->create())->get('/');

        $response->assertOk();
        $this->assertNull($response->viewData('vendor'));
        $this->assertSame(5, $response->viewData('totalShipment'));
    }

    public function test_dashboard_vendor_sections_collapse_to_selected_vendor(): void
    {
        $this->seedShipments();

        $response = $this->actingAs(User::factory()->create())->get('/?vendor=VENDOR+A');

        $response->assertOk();
        $this->assertSame(['VENDOR A' => 3], $response->viewData('topVendors')->all());

        $vendorStats = $response->viewData('vendorStats');
        $this->assertCount(1, $vendorStats);
        $this->assertSame('VENDOR A', $vendorStats->first()['vendor']);
        $this->assertSame(3, $vendorStats->first()['total']);
        $this->assertSame(67.0, $vendorStats->first()['onTimePct']);
    }

    public function test_dashboard_vendor_options_are_listed_with_volume(): void
    {
        $this->seedShipments();

        $response = $this->actingAs(User::factory()->create())->get('/?vendor=VENDOR+A');

        $response->assertOk();
        $this->assertSame(['VENDOR A' => 3, 'VENDOR B' => 2], $response->viewData('vendors')->all());
        $response->assertSee('<option value="VENDOR A" selected>', escape: false);
        $response->assertSee('VENDOR B (2)', escape: false);
    }

    public function test_dashboard_combines_vendor_with_range_filter(): void
    {
        $this->seedShipments();

        $response = $this->actingAs(User::factory()->create())->get('/?vendor=VENDOR+A&range=30');

        $response->assertOk();
        $this->assertSame(30, $response->viewData('range'));
        $this->assertSame('VENDOR A', $response->viewData('vendor'));
        $this->assertSame(3, $response->viewData('totalShipment'));
        $response->assertSee('vendor=VENDOR%20A&amp;range=30', escape: false);
        $response->assertSee('vendor=VENDOR%20A&amp;range=90', escape: false);
    }

    public function test_modal_endpoints_respect_vendor_filter(): void
    {
        $this->seedShipments();
        $user = User::factory()->create();

        $staging = $this->actingAs($user)->getJson('/api/shipments/staging/DELIVER?vendor=VENDOR+A');
        $staging->assertOk();
        $this->assertSame(['A-3', 'A-2', 'A-1'], array_column($staging->json(), 'no_resi'));

        $bottleneck = $this->actingAs($user)->getJson('/api/shipments/bottleneck?provinsi=Riau&vendor=VENDOR+A');
        $bottleneck->assertOk();
        $this->assertSame(['A-3'], array_column($bottleneck->json(), 'no_resi'));
    }

    public function test_modal_endpoints_return_all_vendors_without_filter(): void
    {
        $this->seedShipments();
        $user = User::factory()->create();

        $staging = $this->actingAs($user)->getJson('/api/shipments/staging/DELIVER');
        $staging->assertOk();
        $this->assertCount(5, $staging->json());
    }
}
