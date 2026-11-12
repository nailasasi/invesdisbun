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
        Schema::create('sensus_aset', function (Blueprint $table) {
            $table->bigIncrements('id_sensus');
            $table->unsignedInteger('id_aset');
            $table->string('periode', 20)->index();
            $table->enum('hasil_sensus', ['Baik', 'Rusak Ringan', 'Rusak Berat']);
            $table->date('tanggal_sensus');
            $table->text('catatan')->nullable();
            $table->unsignedInteger('id_user')->nullable();
            $table->timestamps();

            $table->unique(['id_aset', 'periode'], 'sensus_aset_id_aset_periode_unique');

            $table->foreign('id_aset')
                ->references('id_aset')
                ->on('aset')
                ->onDelete('cascade');

            $table->foreign('id_user')
                ->references('id_user')
                ->on('users')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sensus_aset');
    }
};

