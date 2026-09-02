<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Shape the role table for the unit-based model:
     *
     *   - Admin Aset          (Sekretariat)
     *   - Bidang Perlindungan Perkebunan
     *   - Bidang Pengelolahan dan Pemasaran Hasil
     *   - Bidang Produksi Tanaman Semusim
     *   - Bidang Produksi Tanaman Tahunan
     *   - UPT P2BTP
     *   - UPT PSBP
     *   - Pegawai             (anggota biasa, non-admin)
     *
     * Obsolete roles (Admin Bidang, Admin UPT P2BTP, Admin UPT PSBP) are removed.
     * Runs on a fresh DB (migrate:fresh) so the table is normally empty; it is
     * written idempotently so it is safe even if some roles already exist.
     */
    public function up(): void
    {
        // Remove obsolete roles that are no longer part of the model.
        DB::table('role')->whereIn('nama_role', [
            'admin',
            'operator',
            'pimpinan',
            'Admin Bidang',
            'Admin UPT P2BTP',
            'Admin UPT PSBP',
        ])->delete();

        // Ensure the 8 final roles exist (idempotent).
        $roles = [
            'Admin Aset',
            'Bidang Perlindungan Perkebunan',
            'Bidang Pengelolahan dan Pemasaran Hasil',
            'Bidang Produksi Tanaman Semusim',
            'Bidang Produksi Tanaman Tahunan',
            'UPT P2BTP',
            'UPT PSBP',
            'Pegawai',
        ];

        foreach ($roles as $namaRole) {
            if (! DB::table('role')->where('nama_role', $namaRole)->exists()) {
                DB::table('role')->insert([
                    'nama_role' => $namaRole,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the changes (best effort for a fresh install).
     */
    public function down(): void
    {
        DB::table('role')->whereIn('nama_role', [
            'Bidang Perlindungan Perkebunan',
            'Bidang Pengelolahan dan Pemasaran Hasil',
            'Bidang Produksi Tanaman Semusim',
            'Bidang Produksi Tanaman Tahunan',
            'UPT P2BTP',
            'UPT PSBP',
        ])->delete();
    }
};
