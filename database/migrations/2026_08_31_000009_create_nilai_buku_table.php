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
        Schema::create('nilai_buku', function (Blueprint $table) {
            $table->increments('id_nilai');
            $table->unsignedInteger('id_aset')->nullable();
            $table->year('tahun')->nullable();
            $table->decimal('nilai_awal', 15, 2)->nullable();
            $table->decimal('penyusutan', 15, 2)->nullable();
            $table->decimal('nilai_akhir', 15, 2)->nullable();
            $table->timestamps();

            $table->foreign('id_aset')->references('id_aset')->on('aset')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nilai_buku');
    }
};
