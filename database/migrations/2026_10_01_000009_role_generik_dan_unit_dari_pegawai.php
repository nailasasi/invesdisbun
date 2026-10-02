<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Role generik + unit dari pegawai.
 *
 * Sebelumnya role bernama per-unit ("Bidang Perlindungan Perkebunan",
 * "UPT P2BTP", dst). Nama itu sempat menggabungkan dua hal: tipe wewenang
 * DAN unit organisasi. Dipisah menjadi:
 *
 *   nama_role             -> tipe wewenang (4 role)
 *   pegawai.id_unit_kerja -> unit organisasi
 *
 * Pemetaan user lama -> role baru:
 *   Admin Aset                          -> Admin Aset (tidak diubah)
 *   unit jenis UPT                       -> Admin UPT
 *   unit jenis Bidang + awalan "Admin"  -> Admin Bidang
 *   selain itu                           -> Pegawai
 *
 * Catatan: data lama tidak pernah memakai awalan "Admin" pada role per-unit,
 * sehingga user bidang biasa menjadi "Pegawai". Admin UPT tetap "Admin UPT"
 * karena role lamanya memang per-UPT.
 */
return new class extends Migration
{
    public function up(): void
    {
        $adminAset = DB::table('role')->where('nama_role', 'Admin Aset')->value('id_role');
        $pegawai = DB::table('role')->where('nama_role', 'Pegawai')->value('id_role');

        // Pastikan dua role dasar ada.
        if (! $adminAset) {
            $adminAset = DB::table('role')->insertGetId([
                'nama_role' => 'Admin Aset',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! $pegawai) {
            $pegawai = DB::table('role')->insertGetId([
                'nama_role' => 'Pegawai',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $adminBidang = $this->ensureRole('Admin Bidang');
        $adminUpt = $this->ensureRole('Admin UPT');

        // ------------------------------------------------------------------
        // Remap setiap user berdasarkan unit kerjanya.
        // ------------------------------------------------------------------
        $unitJenis = DB::table('unit_kerja')->pluck('jenis_unit_kerja', 'id_unit_kerja');

        $users = DB::table('users')
            ->join('pegawai', 'pegawai.id_pegawai', '=', 'users.id_pegawai')
            ->select('users.id_user', 'users.id_role', 'pegawai.id_unit_kerja')
            ->get();

        foreach ($users as $u) {
            $roleLama = DB::table('role')->where('id_role', $u->id_role)->value('nama_role');

            // Admin Aset tidak pernah disentuh.
            if ($roleLama === 'Admin Aset') {
                continue;
            }

            $jenis = $u->id_unit_kerja ? ($unitJenis[$u->id_unit_kerja] ?? null) : null;
            $adalahUpt = $jenis === 'UPT';

            $baru = match (true) {
                $adalahUpt => $adminUpt,
                $jenis === 'Bidang' && str_starts_with((string) $roleLama, 'Admin') => $adminBidang,
                default => $pegawai,
            };

            if ($baru !== $u->id_role) {
                DB::table('users')->where('id_user', $u->id_user)->update([
                    'id_role' => $baru,
                    'updated_at' => now(),
                ]);
            }
        }

        // Role lama per-unit tidak lagi dipakai; dibersihkan setelah semua
        // user dipindah. 'Pegawai' & 'Admin Aset' tetap dipakai.
        DB::table('role')
            ->whereNotIn('nama_role', ['Admin Aset', 'Admin Bidang', 'Admin UPT', 'Pegawai'])
            ->delete();
    }

    public function down(): void
    {
        // Role per-unit tidak dapat direkonstruksi dengan aman — data asli
        // hanya ada di backup. Sengaja dibiarkan.
    }

    private function ensureRole(string $nama): int
    {
        $ada = DB::table('role')->where('nama_role', $nama)->value('id_role');

        if ($ada) {
            return $ada;
        }

        return DB::table('role')->insertGetId([
            'nama_role' => $nama,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};