<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Master lokasi fisik.
 *
 * Dipisahkan dari unit_kerja karena keduanya berbeda makna:
 * - unit_kerja  = unit organisasi penanggung jawab (Bidang / UPT)
 * - lokasi      = tempat fisik barang berada (kantor dinas / UPT)
 *
 * Aset milik Bidang di kantor dinas tetap bisa ditempatkan di UPT, atau
 * sebaliknya, jadi keduanya tidak boleh disatukan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lokasi')) {
            return;
        }

        Schema::create('lokasi', function (Blueprint $table) {
            $table->increments('id_lokasi');
            $table->string('nama_lokasi', 100)->unique();
            $table->string('jenis_lokasi', 30)->default('Dinas');
            $table->text('keterangan')->nullable();
            $table->enum('status', ['Aktif', 'Nonaktif'])->default('Aktif');
            $table->timestamps();
        });

        // Kantor dinas = tempat kerja seluruh Sekretariat & Bidang.
        DB::table('lokasi')->insert([
            'nama_lokasi' => 'Kantor Dinas Perkebunan Provinsi Jawa Timur',
            'jenis_lokasi' => 'Dinas',
            'keterangan' => 'Kantor pusat. Lokasi kerja Sekretariat dan seluruh Bidang.',
            'status' => 'Aktif',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('lokasi');
    }
};