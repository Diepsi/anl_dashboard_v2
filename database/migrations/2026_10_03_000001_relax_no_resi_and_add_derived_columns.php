<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropUnique('shipments_no_resi_unique');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->date('tanggal_efektif')->nullable()->after('tanggal_manifest');
            $table->string('tanggal_sumber', 20)->nullable()->after('tanggal_efektif');
            $table->boolean('resi_valid')->default(true)->after('no_resi');
            $table->string('resi_catatan', 40)->nullable()->after('resi_valid');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->index('no_resi');
            $table->index('tanggal_efektif');
            $table->index('resi_valid');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex(['no_resi']);
            $table->dropIndex(['tanggal_efektif']);
            $table->dropIndex(['resi_valid']);
            $table->dropColumn(['tanggal_efektif', 'tanggal_sumber', 'resi_valid', 'resi_catatan']);
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->unique('no_resi');
        });
    }
};
