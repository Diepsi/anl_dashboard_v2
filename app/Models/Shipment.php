<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'no_resi',
        'resi_valid',
        'resi_catatan',
        'nomor_redock',
        'delivery_order',
        'nama_sekolah',
        'nama_penerima',
        'provinsi',
        'daerah',
        'kota_kabupaten',
        'kecamatan',
        'vendor_mm',
        'kode_funder',
        'nama_funder',
        'vendor_lm',
        'tanggal_manifest',
        'tanggal_efektif',
        'tanggal_sumber',
        'completed_date',
        'tgl_sampai_kota_tujuan',
        'aging',
        'status_akhir',
        'status_instalasi',
        'harga_per_shipment',
        'status_invoice',
        'stagging',
        'sla',
        'sla_result',
        'bast_tgl_balik',
        'bast_tgl_ke_finance',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tanggal_manifest' => 'date',
            'tanggal_efektif' => 'date',
            'completed_date' => 'date',
            'tgl_sampai_kota_tujuan' => 'date',
            'aging' => 'integer',
            'harga_per_shipment' => 'decimal:2',
            'sla' => 'integer',
            'bast_tgl_balik' => 'date',
            'bast_tgl_ke_finance' => 'date',
            'resi_valid' => 'boolean',
        ];
    }

    public function scopeForVendor($query, mixed $vendor)
    {
        if (! is_string($vendor) || trim($vendor) === '') {
            return $query;
        }

        return $query->where('vendor_lm', trim($vendor));
    }

    /**
     * Filter rentang tanggal memakai `tanggal_efektif` supaya baris yang hanya
     * punya `Completed date` tetap terlihat. Rentang dihitung mundur dari tanggal
     * TERAKHIR di data (bukan hari ini), agar dashboard tetap menampilkan data
     * aktif meski sumber GSheet belum diperbarui.
     */
    public function scopeInRange($query, int $days)
    {
        if ($days <= 0) {
            return $query;
        }

        $latest = $query->clone()->selectRaw('MAX(tanggal_efektif) as latest')->value('latest');

        if (! $latest) {
            return $query;
        }

        $anchor = ($latest instanceof \DateTimeInterface) ? $latest : Carbon::parse($latest);

        return $query->where('tanggal_efektif', '>=', $anchor->copy()->subDays($days - 1)->startOfDay());
    }

    /**
     * Tanggal HO asli saja (tanpa turunan dari Completed date). Dipakai untuk
     * perhitungan durasi pengiriman.
     */
    public function scopeWithRealManifestDate($query)
    {
        return $query->whereNotNull('tanggal_manifest');
    }
}
