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
        Schema::create('mutasi_aset', function (Blueprint $table) {
            $table->increments('id_mutasi');
            $table->date('tanggal_mutasi')->nullable();
            $table->string('jenis_mutasi', 50)->nullable();
            $table->text('keterangan')->nullable();
            $table->unsignedInteger('id_user_penginput')->nullable();
            $table->string('status_mutasi', 50)->nullable();
            $table->timestamps();

            $table->foreign('id_user_penginput')->references('id_user')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mutasi_aset');
    }
};
