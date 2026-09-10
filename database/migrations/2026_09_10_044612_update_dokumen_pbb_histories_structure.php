<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('dokumen_pbb_histories', function (Blueprint $table) {

            // tambah relasi ke tanah
            $table->unsignedInteger('id_tanah')
                ->after('id_history');


            // hapus kolom yang tidak diperlukan
            $table->dropColumn([
                'file_lama',
                'file_baru'
            ]);


            // ganti dengan nama file saja
            $table->string('nama_file')
                ->nullable()
                ->after('aksi');

        });
    }



    public function down(): void
    {
        Schema::table('dokumen_pbb_histories', function (Blueprint $table) {


            $table->dropColumn([
                'id_tanah',
                'nama_file'
            ]);


            $table->string('file_lama')
                ->nullable();


            $table->string('file_baru')
                ->nullable();

        });
    }

};