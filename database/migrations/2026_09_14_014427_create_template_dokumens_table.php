<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_dokumen', function (Blueprint $table) {
            $table->increments('id_template');
            $table->string('kode_template', 50)->unique(); // sppbi, bast_barang, bast_kendaraan, sppkd, label
            $table->string('nama_template', 100);
            $table->string('nama_file_asli')->nullable();
            $table->string('file_path')->nullable();
            $table->string('tipe_berkas', 20)->default('word'); // word | excel
            $table->string('status', 30)->default('Siap Digunakan');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_dokumen');
    }
};