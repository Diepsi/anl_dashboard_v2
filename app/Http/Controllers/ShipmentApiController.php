<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use Illuminate\Http\Request;

class ShipmentApiController extends Controller
{
    public function staging(Request $request, string $stagging)
    {
        $shipments = Shipment::query()
            ->inRange($this->range($request))
            ->forVendor($request->query('vendor'))
            ->where('stagging', $stagging)
            ->orderByDesc('tanggal_efektif')
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'no_resi', 'resi_valid', 'resi_catatan', 'nama_sekolah', 'provinsi', 'kota_kabupaten', 'tanggal_efektif', 'status_akhir', 'sla_result']);

        return response()->json($shipments);
    }

    public function bottleneck(Request $request)
    {
        $provinsi = $request->query('provinsi');

        if (! $provinsi) {
            return response()->json(['message' => 'Parameter provinsi wajib diisi.'], 422);
        }

        $shipments = Shipment::query()
            ->inRange($this->range($request))
            ->forVendor($request->query('vendor'))
            ->where('sla_result', 'Out SLA')
            ->where('provinsi', $provinsi)
            ->orderByDesc('aging')
            ->orderByDesc('tanggal_efektif')
            ->limit(50)
            ->get(['id', 'no_resi', 'resi_valid', 'resi_catatan', 'nama_sekolah', 'kota_kabupaten', 'tanggal_efektif', 'aging', 'sla_result', 'status_akhir']);

        return response()->json($shipments);
    }

    /**
     * Detail diambil berdasarkan `id`, bukan `no_resi`, karena sumber memuat
     * resi duplikat maupun resi kosong (#N/A) sehingga no_resi tidak unik.
     */
    public function detail(Request $request)
    {
        $id = (int) $request->query('id', 0);

        if ($id <= 0) {
            return response()->json(['message' => 'Parameter id wajib diisi.'], 422);
        }

        $shipment = Shipment::query()
            ->whereKey($id)
            ->first();

        if (! $shipment) {
            return response()->json(['message' => 'Data pengiriman tidak ditemukan.'], 404);
        }

        return response()->json($shipment);
    }

    private function range(Request $request): int
    {
        $range = (int) $request->query('range', 0);

        return in_array($range, [0, 30, 90], true) ? $range : 0;
    }
}
