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
        Schema::create('riwayat_plat', function (Blueprint $table) {
            $table->increments('id_plat');
            $table->unsignedInteger('id_kendaraan')->nullable();
            $table->string('nomor_plat', 20)->nullable();
            $table->date('tanggal_berlaku')->nullable();
            $table->string('status', 50)->nullable();
            $table->timestamps();

            $table->foreign('id_kendaraan')->references('id_kendaraan')->on('kendaraan')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('riwayat_plat');
    }
};
