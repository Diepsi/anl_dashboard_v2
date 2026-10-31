<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sumber memuat nilai bebas seperti "belum update pada TMS" (21 karakter),
     * sedangkan kolom lama hanya varchar(20) sehingga sinkronisasi gagal dengan
     * SQLSTATE[22001] Data too long. Kolom dilebarkan agar nilai tetap utuh.
     */
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            $table->string('status_instalasi', 255)->default('BELUM')->change();
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            $table->string('status_instalasi', 20)->default('BELUM')->change();
        });
    }
};
