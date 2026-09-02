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
        Schema::create('detail_usulan_rkbmd', function (Blueprint $table) {
            $table->increments('id_detail');
            $table->unsignedInteger('id_usulan')->nullable();
            $table->unsignedInteger('id_barang')->nullable();
            $table->integer('jumlah_usulan')->nullable();
            $table->integer('kebutuhan_riil')->nullable();
            $table->integer('kebutuhan_maksimum')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->foreign('id_usulan')->references('id_usulan')->on('usulan_rkbmd')->onDelete('cascade');
            $table->foreign('id_barang')->references('id_barang')->on('master_barang')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detail_usulan_rkbmd');
    }
};
