<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tanah', function (Blueprint $table) {
            // Hapus hubungan Tanah dengan Aset Barang
            $table->dropForeign(['id_aset']);
            $table->dropColumn('id_aset');

            // Data KIB A Tanah
            $table->string('kib', 100)->nullable()->after('id_tanah');
            $table->date('tanggal_buku')->nullable()->after('kib');
            $table->date('tanggal_perolehan')->nullable()->after('tanggal_buku');
            $table->decimal('nilai_perolehan', 15, 2)->nullable()->after('tanggal_perolehan');
            $table->text('deskripsi_objek')->nullable()->after('nilai_perolehan');
            $table->string('ketkel', 100)->nullable()->after('alamat');
            $table->string('satuan', 50)->nullable()->after('google_maps');

            // Migration lama memiliki timestamps,
            // sedangkan model Tanah tidak menggunakannya.
            $table->dropTimestamps();
        });
    }

    public function down(): void
    {
        Schema::table('tanah', function (Blueprint $table) {
            $table->unsignedInteger('id_aset')->nullable()->after('id_tanah');

            $table->foreign('id_aset')
                ->references('id_aset')
                ->on('aset')
                ->onDelete('cascade');

            $table->dropColumn([
                'kib',
                'tanggal_buku',
                'tanggal_perolehan',
                'nilai_perolehan',
                'deskripsi_objek',
                'ketkel',
                'satuan',
            ]);

            $table->timestamps();
        });
    }
};