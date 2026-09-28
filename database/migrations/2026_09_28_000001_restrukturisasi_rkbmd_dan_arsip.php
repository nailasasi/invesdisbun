<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restrukturisasi modul RKBMD agar selaras Permendagri No. 19/2016:
     * satu usulan = satu usulan barang/unit kerja, plus arsip dokumen sah.
     */
    public function up(): void
    {
        Schema::table('usulan_rkbmd', function (Blueprint $table) {
            $table->unsignedInteger('id_skpd')->nullable()->after('id_pegawai');
            $table->string('nama_barang', 255)->nullable()->after('program_kegiatan');
            $table->text('spesifikasi')->nullable()->after('nama_barang');
            $table->integer('jumlah')->nullable()->default(1)->after('spesifikasi');
            $table->string('satuan', 50)->nullable()->default('Unit')->after('jumlah');
            $table->text('alasan_kebutuhan')->nullable()->after('satuan');
            $table->text('catatan_pengurus')->nullable()->after('alasan_kebutuhan');
            $table->unsignedInteger('approved_by')->nullable()->after('catatan_pengurus');
            $table->timestamp('approved_at')->nullable()->after('approved_by');

            $table->foreign('id_skpd')->references('id_skpd')->on('skpd')->onDelete('set null');
            $table->foreign('approved_by')->references('id_user')->on('users')->onDelete('set null');
        });

        Schema::create('arsip_rkbmd', function (Blueprint $table) {
            $table->increments('id_arsip');
            $table->unsignedInteger('id_skpd')->nullable();
            $table->year('tahun_anggaran')->nullable();
            $table->string('nama_dokumen', 255)->nullable();
            $table->string('file_path', 255);
            $table->date('tanggal_pengesahan')->nullable();
            $table->unsignedInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->foreign('id_skpd')->references('id_skpd')->on('skpd')->onDelete('set null');
            $table->foreign('uploaded_by')->references('id_user')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arsip_rkbmd');

        Schema::table('usulan_rkbmd', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['id_skpd']);
            $table->dropColumn([
                'id_skpd', 'nama_barang', 'spesifikasi', 'jumlah', 'satuan',
                'alasan_kebutuhan', 'catatan_pengurus', 'approved_by', 'approved_at',
            ]);
        });
    }
};
