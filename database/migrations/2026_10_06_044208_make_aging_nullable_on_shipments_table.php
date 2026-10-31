<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `aging` di sumber bisa bernilai negatif (mis. -45960 pada baris Undelivered
     * tanpa Completed date). Nilai negatif merusak rata-rata aging di dashboard,
     * jadi kolom ini dibuat nullable agar hasil sanitize dapat menyimpan NULL
     * dan MySQL mengabaikannya pada fungsi AVG().
     */
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            $table->integer('aging')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            $table->integer('aging')->default(0)->change();
        });
    }
};
