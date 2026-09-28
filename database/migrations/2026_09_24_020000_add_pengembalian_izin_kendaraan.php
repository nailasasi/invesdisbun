<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('izin_kendaraan', function (Blueprint $table) {
            $table->text('catatan_pengembalian')->nullable()->after('file_surat');
            $table->string('foto_pengembalian', 255)->nullable()->after('catatan_pengembalian');
            $table->timestamp('waktu_pengembalian')->nullable()->after('foto_pengembalian');
        });

        Schema::table('kendaraan', function (Blueprint $table) {
            $table->string('status_penggunaan', 20)->default('Tersedia')->after('foto');
        });

        // Kendaraan yang sedang memiliki pengajuan disetujui dianggap "Digunakan".
        DB::table('kendaraan as k')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('izin_kendaraan as i')
                    ->whereColumn('i.id_kendaraan', 'k.id_kendaraan')
                    ->where('i.status_approval', 'Disetujui');
            })
            ->update(['status_penggunaan' => 'Digunakan']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('izin_kendaraan', function (Blueprint $table) {
            $table->dropColumn(['catatan_pengembalian', 'foto_pengembalian', 'waktu_pengembalian']);
        });

        Schema::table('kendaraan', function (Blueprint $table) {
            $table->dropColumn('status_penggunaan');
        });
    }
};
