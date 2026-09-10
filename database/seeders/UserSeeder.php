<?php

namespace Database\Seeders;

use App\Models\KategoriAset;
use App\Models\Pegawai;
use App\Models\Role;
use App\Models\Skpd;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Seed roles, initial SKPD, categories, and default user accounts.
     */
    public function run(): void
    {
        // Roles
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
            Role::firstOrCreate([
                'nama_role' => $namaRole,
            ]);
        }

        // SKPD
        $daftarSkpd = [
            'Sekretariat',
            'Bidang Perlindungan Perkebunan',
            'Bidang Pengelolahan dan Pemasaran Hasil',
            'Bidang Produksi Tanaman Semusim',
            'Bidang Produksi Tanaman Tahunan',
            'UPT P2BTP',
            'UPT PSBP',
        ];

        foreach ($daftarSkpd as $namaSkpd) {
            Skpd::firstOrCreate([
                'nama_skpd' => $namaSkpd,
            ]);
        }

        // Kategori aset
        $daftarKategori = [
            'Tanah',
            'Peralatan dan Mesin',
            'Gedung dan Bangunan',
            'Kendaraan',
            'Barang Habis Pakai',
        ];

        foreach ($daftarKategori as $namaKategori) {
            KategoriAset::firstOrCreate([
                'nama_kategori' => $namaKategori,
            ]);
        }

        // Ambil role
        $adminRole = Role::where('nama_role', 'Admin Aset')->firstOrFail();
        $pegawaiRole = Role::where('nama_role', 'Pegawai')->firstOrFail();

        // Ambil SKPD Sekretariat
        $skpdSekretariat = Skpd::where('nama_skpd', 'Sekretariat')->firstOrFail();

        /*
         * ==========================================
         * AKUN ADMIN ASET
         * ==========================================
         */
        $nipAdmin = '111111111111111111';

        $adminPegawai = Pegawai::firstOrCreate(
            ['nip' => $nipAdmin],
            [
                'nama_pegawai' => 'Admin Aset',
                'jabatan' => 'Admin Aset',
                'id_skpd' => $skpdSekretariat->id_skpd,
            ]
        );

        User::firstOrCreate(
            ['username' => $nipAdmin],
            [
                'id_pegawai' => $adminPegawai->id_pegawai,
                'id_role' => $adminRole->id_role,
                'password' => 'admin123',
                'status_user' => 'aktif',
            ]
        );

        /*
         * ==========================================
         * AKUN PEGAWAI
         * ==========================================
         */
        $nipPegawai = '1234567891011121314';

        $pegawai = Pegawai::firstOrCreate(
            ['nip' => $nipPegawai],
            [
                'nama_pegawai' => 'Pegawai',
                'jabatan' => 'Pegawai',
                'id_skpd' => $skpdSekretariat->id_skpd,
            ]
        );

        User::firstOrCreate(
            ['username' => $nipPegawai],
            [
                'id_pegawai' => $pegawai->id_pegawai,
                'id_role' => $pegawaiRole->id_role,
                'password' => $nipPegawai,
                'status_user' => 'aktif',
            ]
        );

        /*
         * ==========================================
         * AKUN ADMINSUB
         * ==========================================
         *
         * Untuk sementara menggunakan role Admin Aset
         * sampai tersedia role Adminsub khusus.
         */
        $nipAdminsub = '222222222222222222';

        $adminsubPegawai = Pegawai::firstOrCreate(
            ['nip' => $nipAdminsub],
            [
                'nama_pegawai' => 'Admin Sub',
                'jabatan' => 'Admin Sub',
                'id_skpd' => $skpdSekretariat->id_skpd,
            ]
        );

        User::firstOrCreate(
            ['username' => $nipAdminsub],
            [
                'id_pegawai' => $adminsubPegawai->id_pegawai,
                'id_role' => $adminRole->id_role,
                'password' => $nipAdminsub,
                'status_user' => 'aktif',
            ]
        );

        $this->command->info('Akun default berhasil dibuat:');
        $this->command->info('Admin    : 111111111111111111 / admin123');
        $this->command->info('Pegawai  : 1234567891011121314 / 1234567891011121314');
        $this->command->info('Adminsub  : 222222222222222222 / 222222222222222222');
    }
}