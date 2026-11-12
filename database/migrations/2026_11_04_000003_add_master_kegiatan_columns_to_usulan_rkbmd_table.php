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
        Schema::table('usulan_rkbmd', function (Blueprint $table) {
            $table->foreignId('id_kegiatan')
                ->nullable()
                ->after('program_kegiatan')
                ->constrained('master_kegiatan_rkbmd', 'id_kegiatan')
                ->nullOnDelete();

            $table->string('bidang_urusan', 150)->nullable()->after('id_kegiatan');
            $table->string('nama_program', 200)->nullable()->after('bidang_urusan');
            $table->string('nama_sub_kegiatan', 255)->nullable()->after('nama_program');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('usulan_rkbmd', function (Blueprint $table) {
            $table->dropForeign(['id_kegiatan']);
            $table->dropColumn([
                'id_kegiatan',
                'bidang_urusan',
                'nama_program',
                'nama_sub_kegiatan',
            ]);
        });
    }
};

