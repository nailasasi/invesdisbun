<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('usulan_rkbmd', function (Blueprint $table) {
            $table->string('kode_barang', 50)->nullable()->after('nama_barang');
            $table->integer('kebutuhan_maksimum')->nullable()->after('jumlah');
            $table->integer('kebutuhan_riil')->nullable()->after('kebutuhan_maksimum');
            $table->text('barang_optimalisasi')->nullable()->after('kebutuhan_riil');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('usulan_rkbmd', function (Blueprint $table) {
            $table->dropColumn(['kode_barang', 'kebutuhan_maksimum', 'kebutuhan_riil', 'barang_optimalisasi']);
        });
    }
};