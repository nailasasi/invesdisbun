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
        Schema::create('pajak_kendaraan', function (Blueprint $table) {
            $table->increments('id_pajak');
            $table->unsignedInteger('id_kendaraan')->nullable();
            $table->string('jenis_pajak', 50)->nullable();
            $table->year('tahun')->nullable();
            $table->date('tanggal_bayar')->nullable();
            $table->date('tanggal_berakhir')->nullable();
            $table->decimal('nominal', 15, 2)->nullable();
            $table->timestamps();

            $table->foreign('id_kendaraan')->references('id_kendaraan')->on('kendaraan')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pajak_kendaraan');
    }
};
