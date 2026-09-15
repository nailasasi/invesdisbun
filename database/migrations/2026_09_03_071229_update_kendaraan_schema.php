<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kendaraan', function (Blueprint $table) {
            $table->string('pemegang', 150)->nullable()->after('merk');
            $table->string('foto', 255)->nullable()->after('pemegang');
        });

        Schema::table('pajak_kendaraan', function (Blueprint $table) {
            $table->decimal('total_pajak', 15, 2)->nullable()->after('nominal');
            $table->boolean('pajak_5_tahunan')->default(false)->after('total_pajak');
            $table->string('status', 20)->default('Aktif')->after('pajak_5_tahunan');
        });

        Schema::table('riwayat_plat', function (Blueprint $table) {
            $table->boolean('ganti_plat')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('kendaraan', function (Blueprint $table) {
            $table->dropColumn(['pemegang', 'foto']);
        });

        Schema::table('pajak_kendaraan', function (Blueprint $table) {
            $table->dropColumn(['total_pajak', 'pajak_5_tahunan', 'status']);
        });

        Schema::table('riwayat_plat', function (Blueprint $table) {
            $table->dropColumn('ganti_plat');
        });
    }
};
