<?php

namespace Database\Seeders;

use App\Services\ShipmentSyncService;
use Illuminate\Database\Seeder;

class ShipmentSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(ShipmentSyncService::class);

        try {
            $count = $service->sync();

            $lastSync = $service->lastSyncedAt();

            $this->command?->info("Seeder selesai: {$count} row diimport.");
            $this->command?->warn('Terakhir sync: '.($lastSync?->format('d M Y H:i:s') ?? '-'));
        } catch (\Throwable $e) {
            $this->command?->error($e->getMessage());
        }
    }
}
