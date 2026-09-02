<?php

namespace Database\Seeders;

use App\Models\KategoriAset;
use App\Models\Pegawai;
use App\Models\Role;
use App\Models\Skpd;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed the 5 roles, initial SKPD, category, and the default Admin Aset account.
     */
    public function run(): void
    {
        // 7 role per unit + 1 role Pegawai (anggota biasa).
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
            Role::firstOrCreate(['nama_role' => $namaRole]);
        }

        $adminRole = Role::where('nama_role', 'Admin Aset')->firstOrFail();
        $pegawaiRole = Role::where('nama_role', 'Pegawai')->firstOrFail();

        // 7 unit kerja (SKPD). Terpisah dari role.
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
            Skpd::firstOrCreate(['nama_skpd' => $namaSkpd]);
        }

        KategoriAset::firstOrCreate(['nama_kategori' => 'Tanah']);
        KategoriAset::firstOrCreate(['nama_kategori' => 'Peralatan dan Mesin']);
        KategoriAset::firstOrCreate(['nama_kategori' => 'Gedung dan Bangunan']);
        KategoriAset::firstOrCreate(['nama_kategori' => 'Kendaraan']);
        KategoriAset::firstOrCreate(['nama_kategori' => 'Barang Habis Pakai']);

        $skpdSekretariat = Skpd::where('nama_skpd', 'Sekretariat')->firstOrFail();

        // Akun Admin Aset default (dummy). Login memakai NIP sebagai username,
        // password awal juga NIP (dapat diganti user nanti).
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
                'password' => Hash::make($nipAdmin),
                'status_user' => 'aktif',
            ]
        );

        $this->command->info("Akun Admin Aset (Sekretariat): username = NIP '{$nipAdmin}', password awal = '{$nipAdmin}'");
    }
}
