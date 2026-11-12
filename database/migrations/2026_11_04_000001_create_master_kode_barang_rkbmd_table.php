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
        Schema::create('master_kode_barang_rkbmd', function (Blueprint $table) {
            $table->bigIncrements('id_master_barang');
            $table->string('kode_barang', 50)->unique('uk_mkbr_kode_barang');
            $table->string('nama_barang', 255)->index('idx_mkbr_nama_barang');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_kode_barang_rkbmd');
    }
};
