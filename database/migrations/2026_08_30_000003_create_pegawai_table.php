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
        Schema::create('pegawai', function (Blueprint $table) {
            $table->increments('id_pegawai');
            $table->string('nip', 30)->unique()->nullable();
            $table->string('nama_pegawai', 100)->notNull();
            $table->string('jabatan', 100)->nullable();
            $table->unsignedInteger('id_skpd')->nullable();
            $table->timestamps();

            $table->foreign('id_skpd')->references('id_skpd')->on('skpd')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pegawai');
    }
};
