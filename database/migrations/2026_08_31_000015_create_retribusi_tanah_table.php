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
        Schema::create('retribusi_tanah', function (Blueprint $table) {
            $table->increments('id_retribusi');
            $table->unsignedInteger('id_tanah')->nullable();
            $table->year('tahun')->nullable();
            $table->string('status_pemanfaatan', 50)->nullable();
            $table->decimal('tarif_retribusi', 15, 2)->nullable();
            $table->decimal('target_penerimaan', 15, 2)->nullable();
            $table->decimal('realisasi_penerimaan', 15, 2)->nullable();
            $table->decimal('PAD', 15, 2)->nullable();
            $table->decimal('biaya_pengurusan', 15, 2)->nullable();
            $table->decimal('total_tarif_sewa', 15, 2)->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->foreign('id_tanah')->references('id_tanah')->on('tanah')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('retribusi_tanah');
    }
};
