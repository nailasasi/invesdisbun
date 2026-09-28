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
        Schema::table('izin_kendaraan', function (Blueprint $table) {
            $table->unsignedInteger('id_pengurus_barang')->nullable()->after('id_pegawai_pengaju');
            $table->string('durasi', 50)->nullable()->after('tujuan');

            $table->foreign('id_pengurus_barang')->references('id_pegawai')->on('pegawai')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('izin_kendaraan', function (Blueprint $table) {
            $table->dropForeign(['id_pengurus_barang']);
            $table->dropColumn(['id_pengurus_barang', 'durasi']);
        });
    }
};
