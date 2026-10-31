<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Services\ShipmentSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request, ShipmentSyncService $service)
    {
        $range = (int) $request->query('range', 0);

        if (! in_array($range, [0, 30, 90], true)) {
            $range = 0;
        }

        $vendor = $request->query('vendor');
        $vendor = is_string($vendor) && trim($vendor) !== '' ? trim($vendor) : null;

        $base = Shipment::query()->inRange($range)->forVendor($vendor);

        $vendors = Shipment::query()
            ->select('vendor_lm')
            ->selectRaw('COUNT(*) as total')
            ->whereNotNull('vendor_lm')
            ->where('vendor_lm', '!=', '')
            ->groupBy('vendor_lm')
            ->orderByDesc('total')
            ->orderBy('vendor_lm')
            ->pluck('total', 'vendor_lm');

        $activeStatuses = ['On Process', 'On Process Delivery', 'Dikirim', 'On Delivery', 'Undelivered', 'Hold'];

        $totalShipment = $base->clone()->count();

        /**
         * Metrik SLA dan durasi hanya bermakna untuk baris yang punya tanggal HO
         * asli. Pada sumber, kolom tanggal HO ke vendor hanya diisi untuk DB
         * Logistics; Panthera tidak punya tanggal serah terima sama sekali.
         * Menyertakan seluruh baris Panthera membuat angka durasi dan kepatuhan
         * SLA menyesatkan, jadi baris tanpa tanggal HO dikecualikan dari metrik
         * ini, bukan dari KPI volume.
         */
        $slaBase = $base->clone()->withRealManifestDate();

        $completed = $base->clone()
            ->where('status_akhir', 'Completed')
            ->count();

        $onDeliveryCount = $base->clone()->where('status_akhir', 'On Delivery')->count();
        $undeliveredCount = $base->clone()->where('status_akhir', 'Undelivered')->count();
        $holdCount = $base->clone()->where('status_akhir', 'Hold')->count();

        $withinSla = $slaBase->clone()
            ->where('sla_result', 'Meet SLA')
            ->count();

        $overSla = $slaBase->clone()
            ->where('sla_result', 'Out SLA')
            ->count();

        $slaDenom = $withinSla + $overSla;
        $slaPct = $slaDenom > 0 ? round(($withinSla / $slaDenom) * 100) : 0;

        $outSlaActive = $slaBase->clone()
            ->where('sla_result', 'Out SLA')
            ->where('status_akhir', '!=', 'Completed')
            ->count();

        $outSlaDone = $overSla - $outSlaActive;

        $completionRate = $totalShipment > 0 ? round(($completed / $totalShipment) * 100) : 0;
        $undeliveredRate = $totalShipment > 0 ? round(($undeliveredCount / $totalShipment) * 100) : 0;

        $avgLeadTime = round((float) ($slaBase->clone()
            ->whereNotNull('completed_date')
            ->where('status_akhir', 'Completed')
            ->selectRaw('AVG(DATEDIFF(completed_date, tanggal_manifest)) as avg_lead')
            ->value('avg_lead') ?? 0), 1);

        $leadTimeScope = (int) $slaBase->clone()->count();

        $statusBreakdown = $base->clone()
            ->selectRaw('status_akhir, COUNT(*) as total')
            ->groupBy('status_akhir')
            ->orderByDesc('total')
            ->pluck('total', 'status_akhir');

        $bastFinanceCount = $base->clone()
            ->whereNotNull('bast_tgl_ke_finance')
            ->count();

        $bastFinanceStats = collect([
            'Sudah handover' => $bastFinanceCount,
            'Belum' => $totalShipment - $bastFinanceCount,
        ])->filter(fn ($total) => $total > 0);

        $chartData = $base->clone()
            ->whereNotNull('tanggal_efektif')
            ->selectRaw('DATE(tanggal_efektif) as tanggal')
            ->selectRaw('COUNT(*) as volume_kirim')
            ->selectRaw('SUM(CASE WHEN status_akhir = "Completed" THEN 1 ELSE 0 END) as volume_selesai')
            ->groupByRaw('DATE(tanggal_efektif)')
            ->orderBy('tanggal')
            ->get();

        $outSlaDaily = $slaBase->clone()
            ->whereNotNull('tanggal_efektif')
            ->selectRaw('DATE(tanggal_efektif) as tanggal')
            ->selectRaw('COUNT(*) as volume_out')
            ->where('sla_result', 'Out SLA')
            ->groupByRaw('DATE(tanggal_efektif)')
            ->orderBy('tanggal')
            ->get()
            ->keyBy('tanggal');

        $slaComplianceDaily = $slaBase->clone()
            ->whereNotNull('tanggal_efektif')
            ->selectRaw('DATE(tanggal_efektif) as tanggal')
            ->selectRaw('SUM(CASE WHEN sla_result = "Meet SLA" THEN 1 ELSE 0 END) as meet')
            ->selectRaw('COUNT(CASE WHEN sla_result IS NOT NULL THEN 1 END) as total')
            ->groupByRaw('DATE(tanggal_efektif)')
            ->orderBy('tanggal')
            ->get()
            ->keyBy('tanggal');

        $staggingOrder = [
            'BERANGKAT DARI CROSSDOCK',
            'TIBA DI PORT ORIGIN',
            'BERANGKAT DARI PORT ORIGIN',
            'TIBA DI PORT TRANSIT',
            'BERANGKAT DARI PORT TRANSIT',
            'MENUJU HUB DESTINASI',
            'TIBA DI PORT DESTINASI',
            'PROCESS DOORING',
        ];

        $staggingStats = $base->clone()
            ->selectRaw('stagging, COUNT(*) as total')
            ->whereNotNull('stagging')
            ->groupBy('stagging')
            ->orderByDesc('total')
            ->get()
            ->keyBy('stagging');

        $staggingList = collect($staggingOrder)
            ->map(fn ($name) => [
                'name' => $name,
                'total' => (int) ($staggingStats[$name]->total ?? 0),
            ])
            ->filter(fn ($stage) => $stage['total'] > 0);

        $bottleneckStats = $slaBase->clone()
            ->selectRaw('provinsi, COUNT(*) as total')
            ->where('sla_result', 'Out SLA')
            ->whereNotNull('provinsi')
            ->groupBy('provinsi')
            ->orderByDesc('total')
            ->get();

        $topProvinces = $base->clone()
            ->selectRaw('provinsi, COUNT(*) as total')
            ->whereNotNull('provinsi')
            ->where('provinsi', '!=', '')
            ->groupBy('provinsi')
            ->orderByDesc('total')
            ->limit(5)
            ->pluck('total', 'provinsi');

        $topVendorsAll = $base->clone()
            ->groupBy('vendor_lm')
            ->selectRaw('COALESCE(NULLIF(vendor_lm, ""), "Belum diisi") as vendor, COUNT(*) as total')
            ->orderByDesc('total')
            ->get()
            ->pluck('total', 'vendor');

        $topVendors = $topVendorsAll->take(5);
        $otherVendorCount = $topVendorsAll->slice(5)->sum();

        if ($otherVendorCount > 0) {
            $topVendors->put('Lainnya', $otherVendorCount);
        }

        $vendorStats = $slaBase->clone()
            ->whereNotNull('vendor_lm')
            ->where('vendor_lm', '!=', '')
            ->selectRaw('vendor_lm')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN sla_result = "Meet SLA" THEN 1 ELSE 0 END) as meet')
            ->selectRaw('AVG(CASE WHEN tanggal_manifest IS NOT NULL THEN DATEDIFF(completed_date, tanggal_manifest) END) as avg_lead')
            ->selectRaw('AVG(aging) as avg_aging')
            ->groupBy('vendor_lm')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'vendor' => $r->vendor_lm,
                'total' => (int) $r->total,
                'onTimePct' => $r->total > 0 ? round(((int) $r->meet / (int) $r->total) * 100) : 0,
                'avgLead' => $r->avg_lead !== null ? round((float) $r->avg_lead, 1) : null,
                'avgAging' => $r->avg_aging !== null ? round((float) $r->avg_aging, 1) : null,
                'total' => (int) $r->total,
            ]);

        // Total baris vendor tanpa filter tanggal HO, untuk menandai cakupan tabel.
        $vendorTotalsAll = Shipment::query()
            ->whereNotNull('vendor_lm')
            ->where('vendor_lm', '!=', '')
            ->selectRaw('vendor_lm')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('vendor_lm')
            ->pluck('total', 'vendor_lm');

        $vendorStats = $vendorStats->map(function (array $row) use ($vendorTotalsAll): array {
            $all = (int) ($vendorTotalsAll[$row['vendor']] ?? 0);

            return $row + [
                'total_terukur' => $row['total'],
                'total_keseluruhan' => $all,
                'terukur_pct' => $all > 0 ? round(($row['total'] / $all) * 100) : 0,
            ];
        });

        $latestRawDate = Shipment::query()->max('tanggal_efektif');
        $earliestRawDate = Shipment::query()->min('tanggal_efektif');

        $latestManifest = $latestRawDate ? Carbon::parse($latestRawDate) : null;
        $earliestManifest = $earliestRawDate ? Carbon::parse($earliestRawDate) : null;

        $recentShipments = $base->clone()
            ->orderByDesc('tanggal_efektif')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $rangeLatestRaw = $base->clone()->max('tanggal_efektif');
        $agingAnchor = $rangeLatestRaw ? Carbon::parse($rangeLatestRaw)->toDateString() : Carbon::today()->toDateString();

        $agingBuckets = collect([
            '≤ 7 hari' => 0,
            '8–14 hari' => 0,
            '15–30 hari' => 0,
            '> 30 hari' => 0,
        ]);

        $agingRows = $base->clone()
            ->whereIn('status_akhir', $activeStatuses)
            ->whereNotNull('tanggal_efektif')
            ->selectRaw('CASE
                WHEN DATEDIFF(DATE("'.$agingAnchor.'"), DATE(tanggal_efektif)) <= 7 THEN "≤ 7 hari"
                WHEN DATEDIFF(DATE("'.$agingAnchor.'"), DATE(tanggal_efektif)) <= 14 THEN "8–14 hari"
                WHEN DATEDIFF(DATE("'.$agingAnchor.'"), DATE(tanggal_efektif)) <= 30 THEN "15–30 hari"
                ELSE "> 30 hari"
              END as bucket')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        foreach ($agingRows as $bucket => $total) {
            if ($agingBuckets->has($bucket)) {
                $agingBuckets[$bucket] = (int) $total;
            }
        }

        $attentionShipments = $base->clone()
            ->whereIn('status_akhir', $activeStatuses)
            ->selectRaw('*, DATEDIFF(DATE("'.$agingAnchor.'"), DATE(tanggal_efektif)) as days_open')
            ->where(function ($q) use ($agingAnchor) {
                $q->whereIn('status_akhir', ['Hold', 'Undelivered'])
                    ->orWhereRaw('DATEDIFF(DATE("'.$agingAnchor.'"), DATE(tanggal_efektif)) >= 14');
            })
            ->orderByRaw('COALESCE(days_open, 0) DESC')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        $staggingFilled = $base->clone()->whereNotNull('stagging')->where('stagging', '!=', '')->count();

        $resiInvalidCount = Shipment::query()->where('resi_valid', false)->count();
        $resiDuplicateCount = Shipment::query()->where('resi_catatan', ShipmentSyncService::RESI_CATATAN_DUPLIKAT)->count();

        $lastSync = $service->lastSyncedAt();
        $sourceStats = $service->sourceStats();
        $dbTotal = Shipment::count();

        return view('dashboard', compact(
            'range',
            'vendor',
            'vendors',
            'totalShipment',
            'completed',
            'onDeliveryCount',
            'undeliveredCount',
            'holdCount',
            'withinSla',
            'overSla',
            'slaPct',
            'outSlaActive',
            'outSlaDone',
            'completionRate',
            'undeliveredRate',
            'avgLeadTime',
            'leadTimeScope',
            'statusBreakdown',
            'bastFinanceStats',
            'chartData',
            'outSlaDaily',
            'slaComplianceDaily',
            'staggingList',
            'bottleneckStats',
            'topProvinces',
            'topVendors',
            'vendorStats',
            'latestManifest',
            'earliestManifest',
            'recentShipments',
            'agingBuckets',
            'attentionShipments',
            'lastSync',
            'sourceStats',
            'dbTotal',
            'staggingFilled',
            'resiInvalidCount',
            'resiDuplicateCount',
        ));
    }
}
