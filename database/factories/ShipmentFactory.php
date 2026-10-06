<?php

namespace Database\Factories;

use App\Services\ShipmentSyncService;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShipmentFactory extends Factory
{
    public function definition(): array
    {
        $manifestDate = fake()->dateTimeBetween('-1 year')->format('Y-m-d');

        return [
            'no_resi' => fake()->unique()->bothify('RESI-#######'),
            'resi_valid' => true,
            'nama_sekolah' => fake()->company().' School',
            'nama_penerima' => fake()->name(),
            'provinsi' => fake()->randomElement(['Aceh', 'Jakarta', 'Jawa Barat', 'Jawa Timur']),
            'nama_funder' => fake()->company(),
            'vendor_lm' => fake()->randomElement(['Vendor A', 'Vendor B', 'Vendor C']),
            'tanggal_manifest' => $manifestDate,
            'tanggal_efektif' => $manifestDate,
            'tanggal_sumber' => ShipmentSyncService::SUMBER_TANGGAL_HO_VENDOR,
            'status_akhir' => fake()->randomElement(['Completed', 'On Delivery', 'Hold', 'Undelivered']),
            'stagging' => fake()->randomElement(['Manifest', 'Delivery', 'Selesai']),
            'sla_result' => fake()->randomElement(['Meet SLA', 'Out SLA']),
        ];
    }
}
