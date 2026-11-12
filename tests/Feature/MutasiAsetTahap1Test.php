<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\DetailMutasiAset;
use App\Models\Kendaraan;
use App\Models\MasterBarang;
use App\Models\MutasiAset;
use App\Models\Pegawai;
use App\Models\PemegangAset;
use App\Models\PenempatanAset;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\Skpd;
use App\Models\User;
use App\Services\AsetScope;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Test Suite Tahap 1: Keamanan Dokumen, Integritas Transaksi, dan Pencegahan Mutasi Bersamaan (Modul Mutasi Aset).
 */
class MutasiAsetTahap1Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $this->namaDatabase(),
        ]);

        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function namaDatabase(): string
    {
        foreach (file(base_path('.env')) as $line) {
            if (str_starts_with(trim($line), 'DB_DATABASE=')) {
                $nilai = trim(explode('=', $line, 2)[1]);

                return $nilai === '' ? 'invesdisbun' : $nilai;
            }
        }

        return 'invesdisbun';
    }

    private function loginSebagai(User $user): void
    {
        app()->forgetInstance(AsetScope::class);
        $this->actingAs($user);
    }

    private function ambilRole(string $nama): Role
    {
        return Role::firstOrCreate(
            ['nama_role' => $nama],
            ['deskripsi' => 'Role ' . $nama]
        );
    }

    private function buatUserDenganRole(string $namaRole, Skpd $skpd, ?Ruangan $ruangan = null): User
    {
        $role = $this->ambilRole($namaRole);

        $pegawai = Pegawai::create([
            'nama_pegawai' => $namaRole . ' ' . $skpd->id_skpd . ' ' . uniqid(),
            'nip' => '199' . rand(10000000, 99999999),
            'id_skpd' => $skpd->id_skpd,
            'id_ruangan' => $ruangan?->id_ruangan,
            'jabatan' => 'Staf ' . $namaRole,
        ]);

        return User::create([
            'username' => strtolower(str_replace(' ', '_', $namaRole)) . '_' . uniqid(),
            'password' => bcrypt('password123'),
            'id_pegawai' => $pegawai->id_pegawai,
            'id_role' => $role->id_role,
            'status_user' => 'aktif',
        ]);
    }

    private function ambilSkpdUptP2btp(): Skpd
    {
        $skpd = Skpd::where('jenis_skpd', 'UPT')
            ->where('nama_skpd', 'like', '%P2BTP%')
            ->first();

        if (! $skpd) {
            $skpd = Skpd::where('jenis_skpd', 'UPT')->firstOrFail();
        }

        return $skpd;
    }

    private function ambilSkpdUptPsbp(): Skpd
    {
        $skpd = Skpd::where('jenis_skpd', 'UPT')
            ->where('nama_skpd', 'like', '%PSBP%')
            ->first();

        if (! $skpd) {
            $p2btp = $this->ambilSkpdUptP2btp();
            $skpd = Skpd::where('jenis_skpd', 'UPT')->where('id_skpd', '!=', $p2btp->id_skpd)->firstOrFail();
        }

        return $skpd;
    }

    private function ambilSkpdBidang(): Skpd
    {
        return Skpd::where('jenis_skpd', '!=', 'UPT')->firstOrFail();
    }

    private function buatRuanganUntukSkpd(Skpd $skpd): Ruangan
    {
        $lokasi = \App\Models\Lokasi::untukSkpd($skpd->id_skpd) ?? \App\Models\Lokasi::dinas();

        return Ruangan::create([
            'id_skpd' => $skpd->id_skpd,
            'id_lokasi' => $lokasi->id_lokasi,
            'nama_ruangan' => 'Ruang Uji ' . $skpd->id_skpd . ' ' . uniqid(),
            'status' => 'Aktif',
        ]);
    }

    private function buatAsetBarang(Skpd $skpd, Ruangan $ruangan, ?Pegawai $pemegang = null): Aset
    {
        $mb = MasterBarang::firstOrCreate(
            ['nama_barang' => 'Laptop Mutasi Tahap 1'],
            ['kode_barang' => 'MB-MUT-1']
        );

        $aset = Aset::create([
            'id_master_barang' => $mb->id_master_barang,
            'id_skpd' => $skpd->id_skpd,
            'id_lokasi' => $ruangan->id_lokasi,
            'nomor_kartu_barang' => 'KIB-B-' . uniqid(),
            'merk' => 'Lenovo ThinkPad',
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'tanggal_pengadaan' => now()->toDateString(),
            'nilai_perolehan' => 12000000,
            'is_kendaraan' => false,
        ]);

        PenempatanAset::create([
            'id_aset' => $aset->id_aset,
            'id_ruangan' => $ruangan->id_ruangan,
            'tanggal_penempatan' => now()->toDateString(),
            'status' => 'aktif',
            'is_finalized' => 1,
            'finalized_at' => now(),
        ]);

        if ($pemegang) {
            PemegangAset::create([
                'id_aset' => $aset->id_aset,
                'id_pegawai' => $pemegang->id_pegawai,
                'tanggal_mulai' => now()->toDateString(),
                'status' => 'aktif',
            ]);
        }

        return $aset;
    }

    private function buatKendaraan(Pegawai $pemegang): Kendaraan
    {
        $skpd = $pemegang->skpd;
        $lokasi = \App\Models\Lokasi::dinas();

        $mb = MasterBarang::firstOrCreate(
            ['nama_barang' => 'Mobil Dinas Mutasi'],
            ['kode_barang' => 'KND-MUT-1']
        );

        $aset = Aset::create([
            'id_master_barang' => $mb->id_master_barang,
            'id_skpd' => $skpd->id_skpd,
            'id_lokasi' => $lokasi->id_lokasi,
            'nomor_kartu_barang' => 'KIB-A-' . uniqid(),
            'merk' => 'Toyota Avanza',
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'tanggal_pengadaan' => now()->toDateString(),
            'nilai_perolehan' => 200000000,
            'is_kendaraan' => true,
        ]);

        $ruangan = Ruangan::where('id_skpd', $skpd->id_skpd)->first() ?: $this->buatRuanganUntukSkpd($skpd);

        PenempatanAset::create([
            'id_aset' => $aset->id_aset,
            'id_ruangan' => $ruangan->id_ruangan,
            'tanggal_penempatan' => now()->toDateString(),
            'status' => 'aktif',
            'is_finalized' => 1,
            'finalized_at' => now(),
        ]);

        PemegangAset::create([
            'id_aset' => $aset->id_aset,
            'id_pegawai' => $pemegang->id_pegawai,
            'tanggal_mulai' => now()->toDateString(),
            'status' => 'aktif',
        ]);

        return Kendaraan::create([
            'id_aset' => $aset->id_aset,
            'jenis_kendaraan' => 'Mobil Dinas',
            'nomor_polisi' => 'KT ' . rand(1000, 9999) . ' AS',
            'nomor_rangka' => 'MHF' . uniqid(),
            'nomor_mesin' => '1NR' . uniqid(),
            'pemegang' => $pemegang->nama_pegawai,
        ]);
    }

    // =========================================================================
    // 1. Mutasi kendaraan berhasil menulis seluruh record dalam transaksi
    // =========================================================================
    public function test_mutasi_kendaraan_berhasil_menulis_seluruh_record_dalam_transaksi(): void
    {
        $skpdBidang = $this->ambilSkpdBidang();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $skpdBidang);
        $pegawaiLama = $adminAset->pegawai;

        $kendaraan = $this->buatKendaraan($pegawaiLama);

        $pegawaiBaru = Pegawai::create([
            'nama_pegawai' => 'Pegawai Baru ' . uniqid(),
            'nip' => '199' . rand(10000000, 99999999),
            'id_skpd' => $skpdBidang->id_skpd,
            'id_ruangan' => $kendaraan->aset->penempatanAktif->id_ruangan,
            'jabatan' => 'Penerima Kendaraan',
        ]);

        $this->loginSebagai($adminAset);

        $res = $this->post(route('kendaraan.mutasi-pemegang.store', $kendaraan->id_kendaraan), [
            'id_pegawai_baru' => $pegawaiBaru->id_pegawai,
            'tanggal_mutasi' => now()->toDateString(),
            'nomor_surat' => '028/MUT-KND/' . rand(100, 999),
            'keterangan' => 'Mutasi pemegang kendaraan dinas resmi',
        ]);

        $res->assertSessionHasNoErrors();
        $res->assertSessionHas('success');
        $res->assertSessionHas('mutasi_kendaraan_id');

        $mutasiId = session('mutasi_kendaraan_id');
        $this->assertNotNull($mutasiId);

        $mutasi = MutasiAset::findOrFail($mutasiId);
        $this->assertEquals('Ganti Pemegang', $mutasi->jenis_mutasi);
        $this->assertEquals('selesai', $mutasi->status_mutasi);

        $detail = DetailMutasiAset::where('id_mutasi', $mutasiId)->first();
        $this->assertNotNull($detail);
        $this->assertEquals($kendaraan->id_aset, $detail->id_aset);
        $this->assertEquals($pegawaiLama->id_pegawai, $detail->pegawai_lama);
        $this->assertEquals($pegawaiBaru->id_pegawai, $detail->pegawai_baru);

        // Pastikan tabel kendaraan terupdate
        $kendaraan->refresh();
        $this->assertEquals($pegawaiBaru->nama_pegawai, $kendaraan->pemegang);

        // Pastikan pemegang aktif adalah pegawai baru
        $pemegangAktif = PemegangAset::where('id_aset', $kendaraan->id_aset)->where('status', 'aktif')->first();
        $this->assertNotNull($pemegangAktif);
        $this->assertEquals($pegawaiBaru->id_pegawai, $pemegangAktif->id_pegawai);
    }

    // =========================================================================
    // 2. Mutasi kendaraan me-rollback seluruh record jika terjadi kesalahan
    // =========================================================================
    public function test_mutasi_kendaraan_rollback_seluruh_record_jika_terjadi_kesalahan(): void
    {
        $skpdBidang = $this->ambilSkpdBidang();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $skpdBidang);
        $pegawaiLama = $adminAset->pegawai;

        $kendaraan = $this->buatKendaraan($pegawaiLama);
        $oldPemegangName = $kendaraan->pemegang;

        $pegawaiBaru = Pegawai::create([
            'nama_pegawai' => 'Pegawai Baru Gagal ' . uniqid(),
            'nip' => '199' . rand(10000000, 99999999),
            'id_skpd' => $skpdBidang->id_skpd,
            'jabatan' => 'Penerima Kendaraan Gagal',
        ]);

        $this->loginSebagai($adminAset);

        // Simulasi error di tengah transaksi dengan event listener pada DetailMutasiAset
        DetailMutasiAset::creating(function ($model) {
            if ($model->keterangan_simulasi_error ?? false) {
                throw new \Exception('Simulasi kegagalan database di tengah transaksi mutasi kendaraan');
            }
        });

        // Trigger transaksi dengan menyuntikkan throwing hook
        DetailMutasiAset::saving(function () {
            throw new \RuntimeException('Simulasi kegagalan transaksi mutasi');
        });

        try {
            $this->withoutExceptionHandling()->post(route('kendaraan.mutasi-pemegang.store', $kendaraan->id_kendaraan), [
                'id_pegawai_baru' => $pegawaiBaru->id_pegawai,
                'tanggal_mutasi' => now()->toDateString(),
                'nomor_surat' => 'FAIL/001',
                'keterangan' => 'Simulasi gagal',
            ]);
        } catch (\Throwable $e) {
            // Exception tertangkap
        }

        // Hapus listener agar tidak mengganggu test berikutnya
        DetailMutasiAset::flushEventListeners();

        // Pastikan TIDAK ada record MutasiAset baru yang tersimpan
        $mutasiCount = MutasiAset::where('keterangan', 'like', '%FAIL/001%')->count();
        $this->assertEquals(0, $mutasiCount, 'Transaksi harus di-rollback: tidak boleh ada MutasiAset tertinggal.');

        // Pastikan pemegang kendaraan lama TIDAK berubah
        $kendaraan->refresh();
        $this->assertEquals($oldPemegangName, $kendaraan->pemegang);

        // Pastikan PemegangAset aktif tetap pegawai lama
        $pemegangAktif = PemegangAset::where('id_aset', $kendaraan->id_aset)->where('status', 'aktif')->first();
        $this->assertEquals($pegawaiLama->id_pegawai, $pemegangAktif->id_pegawai);
    }

    // =========================================================================
    // 3. Concurrency check: validasi ulang setelah row lock mendeteksi perubahan
    // =========================================================================
    public function test_concurrency_revalidasi_setelah_row_lock_mendeteksi_perubahan_kondisi_aset(): void
    {
        $skpdBidang = $this->ambilSkpdBidang();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $skpdBidang);
        $ruangan = $this->buatRuanganUntukSkpd($skpdBidang);
        $pegawai1 = $adminAset->pegawai;

        $aset = $this->buatAsetBarang($skpdBidang, $ruangan, $pegawai1);

        $pegawai2 = Pegawai::create([
            'nama_pegawai' => 'Pegawai Mutasi 2 ' . uniqid(),
            'nip' => '199' . rand(10000000, 99999999),
            'id_skpd' => $skpdBidang->id_skpd,
            'id_ruangan' => $ruangan->id_ruangan,
            'jabatan' => 'Staf',
        ]);

        $this->loginSebagai($adminAset);

        // Ubah status aset menjadi diusulkan_hapus untuk menguji validasi status_aset === 'aktif' di dalam lock
        $aset->update(['status_aset' => 'diusulkan_hapus']);

        $res = $this->postJson(route('aset-barang.aset.mutasi', $aset->id_aset), [
            'tipe' => 'pegawai',
            'id_pegawai' => $pegawai2->id_pegawai,
        ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['tipe']);
    }

    // =========================================================================
    // 4. Admin Aset dapat mengunduh BAST Aset Barang secara global
    // =========================================================================
    public function test_admin_aset_dapat_mengunduh_bast_aset_barang_secara_global(): void
    {
        $skpdBidang = $this->ambilSkpdBidang();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $skpdBidang);
        $ruangan = $this->buatRuanganUntukSkpd($skpdBidang);

        $p1 = $adminAset->pegawai;
        $p2 = Pegawai::create([
            'nama_pegawai' => 'Penerima BAST Global ' . uniqid(),
            'nip' => '199' . rand(10000000, 99999999),
            'id_skpd' => $skpdBidang->id_skpd,
            'id_ruangan' => $ruangan->id_ruangan,
            'jabatan' => 'Penerima Global',
        ]);

        $aset = $this->buatAsetBarang($skpdBidang, $ruangan, $p1);

        $mutasi = MutasiAset::create([
            'tanggal_mutasi' => now()->toDateString(),
            'jenis_mutasi' => 'Ganti Pemegang',
            'keterangan' => 'Mutasi global',
            'id_user_penginput' => $adminAset->id_user,
            'status_mutasi' => 'selesai',
        ]);

        DetailMutasiAset::create([
            'id_mutasi' => $mutasi->id_mutasi,
            'id_aset' => $aset->id_aset,
            'pegawai_lama' => $p1->id_pegawai,
            'pegawai_baru' => $p2->id_pegawai,
            'ruangan_lama' => $ruangan->id_ruangan,
            'ruangan_baru' => $ruangan->id_ruangan,
        ]);

        $this->loginSebagai($adminAset);

        $res = $this->get(route('mutasi-aset.bast.download', $mutasi->id_mutasi));

        $res->assertOk();
        $this->assertTrue(
            str_contains($res->headers->get('content-disposition', ''), '.docx'),
            'Response download BAST harus menghasilkan file docx.'
        );
    }

    // =========================================================================
    // 5. Admin UPT P2BTP dapat mengunduh BAST Aset Barang P2BTP
    // =========================================================================
    public function test_admin_upt_p2btp_dapat_mengunduh_bast_aset_barang_p2btp(): void
    {
        $p2btp = $this->ambilSkpdUptP2btp();
        $ruangan = $this->buatRuanganUntukSkpd($p2btp);
        $adminP2btp = $this->buatUserDenganRole('Admin UPT', $p2btp, $ruangan);

        $p1 = $adminP2btp->pegawai;
        $p2 = Pegawai::create([
            'nama_pegawai' => 'Penerima BAST P2BTP ' . uniqid(),
            'nip' => '199' . rand(10000000, 99999999),
            'id_skpd' => $p2btp->id_skpd,
            'id_ruangan' => $ruangan->id_ruangan,
            'jabatan' => 'Staf P2BTP',
        ]);

        $aset = $this->buatAsetBarang($p2btp, $ruangan, $p1);

        $mutasi = MutasiAset::create([
            'tanggal_mutasi' => now()->toDateString(),
            'jenis_mutasi' => 'Ganti Pemegang',
            'keterangan' => 'Mutasi P2BTP',
            'id_user_penginput' => $adminP2btp->id_user,
            'status_mutasi' => 'selesai',
        ]);

        DetailMutasiAset::create([
            'id_mutasi' => $mutasi->id_mutasi,
            'id_aset' => $aset->id_aset,
            'pegawai_lama' => $p1->id_pegawai,
            'pegawai_baru' => $p2->id_pegawai,
            'ruangan_lama' => $ruangan->id_ruangan,
            'ruangan_baru' => $ruangan->id_ruangan,
        ]);

        $this->loginSebagai($adminP2btp);

        $res = $this->get(route('mutasi-aset.bast.download', $mutasi->id_mutasi));

        $res->assertOk();
        $this->assertTrue(
            str_contains($res->headers->get('content-disposition', ''), '.docx'),
            'Admin UPT P2BTP berhak mengunduh BAST aset miliknya.'
        );
    }

    // =========================================================================
    // 6. Admin UPT PSBP dapat mengunduh BAST Aset Barang PSBP
    // =========================================================================
    public function test_admin_upt_psbp_dapat_mengunduh_bast_aset_barang_psbp(): void
    {
        $psbp = $this->ambilSkpdUptPsbp();
        $ruangan = $this->buatRuanganUntukSkpd($psbp);
        $adminPsbp = $this->buatUserDenganRole('Admin UPT', $psbp, $ruangan);

        $p1 = $adminPsbp->pegawai;
        $p2 = Pegawai::create([
            'nama_pegawai' => 'Penerima BAST PSBP ' . uniqid(),
            'nip' => '199' . rand(10000000, 99999999),
            'id_skpd' => $psbp->id_skpd,
            'id_ruangan' => $ruangan->id_ruangan,
            'jabatan' => 'Staf PSBP',
        ]);

        $aset = $this->buatAsetBarang($psbp, $ruangan, $p1);

        $mutasi = MutasiAset::create([
            'tanggal_mutasi' => now()->toDateString(),
            'jenis_mutasi' => 'Ganti Pemegang',
            'keterangan' => 'Mutasi PSBP',
            'id_user_penginput' => $adminPsbp->id_user,
            'status_mutasi' => 'selesai',
        ]);

        DetailMutasiAset::create([
            'id_mutasi' => $mutasi->id_mutasi,
            'id_aset' => $aset->id_aset,
            'pegawai_lama' => $p1->id_pegawai,
            'pegawai_baru' => $p2->id_pegawai,
            'ruangan_lama' => $ruangan->id_ruangan,
            'ruangan_baru' => $ruangan->id_ruangan,
        ]);

        $this->loginSebagai($adminPsbp);

        $res = $this->get(route('mutasi-aset.bast.download', $mutasi->id_mutasi));

        $res->assertOk();
        $this->assertTrue(
            str_contains($res->headers->get('content-disposition', ''), '.docx'),
            'Admin UPT PSBP berhak mengunduh BAST aset miliknya.'
        );
    }

    // =========================================================================
    // 7. Admin UPT P2BTP dilarang (403) mengunduh BAST Aset Barang PSBP (anti-IDOR)
    // =========================================================================
    public function test_admin_upt_p2btp_dilarang_mengunduh_bast_aset_barang_psbp_anti_idor(): void
    {
        $p2btp = $this->ambilSkpdUptP2btp();
        $ruanganP2btp = $this->buatRuanganUntukSkpd($p2btp);
        $adminP2btp = $this->buatUserDenganRole('Admin UPT', $p2btp, $ruanganP2btp);

        $psbp = $this->ambilSkpdUptPsbp();
        $ruanganPsbp = $this->buatRuanganUntukSkpd($psbp);
        $pegawaiPsbp = Pegawai::create([
            'nama_pegawai' => 'Staf PSBP Target ' . uniqid(),
            'nip' => '199' . rand(10000000, 99999999),
            'id_skpd' => $psbp->id_skpd,
            'id_ruangan' => $ruanganPsbp->id_ruangan,
            'jabatan' => 'Staf PSBP',
        ]);

        $asetPsbp = $this->buatAsetBarang($psbp, $ruanganPsbp, $pegawaiPsbp);

        $mutasiPsbp = MutasiAset::create([
            'tanggal_mutasi' => now()->toDateString(),
            'jenis_mutasi' => 'Ganti Pemegang',
            'keterangan' => 'Mutasi PSBP Rahasia',
            'id_user_penginput' => 1,
            'status_mutasi' => 'selesai',
        ]);

        DetailMutasiAset::create([
            'id_mutasi' => $mutasiPsbp->id_mutasi,
            'id_aset' => $asetPsbp->id_aset,
            'pegawai_lama' => $pegawaiPsbp->id_pegawai,
            'pegawai_baru' => $pegawaiPsbp->id_pegawai,
            'ruangan_lama' => $ruanganPsbp->id_ruangan,
            'ruangan_baru' => $ruanganPsbp->id_ruangan,
        ]);

        // Login sebagai Admin P2BTP lalu mencoba mengunduh BAST PSBP
        $this->loginSebagai($adminP2btp);

        $res = $this->get(route('mutasi-aset.bast.download', $mutasiPsbp->id_mutasi));

        $res->assertForbidden();
    }

    // =========================================================================
    // 8. Admin UPT PSBP dilarang (403) mengunduh BAST Aset Barang P2BTP (anti-IDOR)
    // =========================================================================
    public function test_admin_upt_psbp_dilarang_mengunduh_bast_aset_barang_p2btp_anti_idor(): void
    {
        $psbp = $this->ambilSkpdUptPsbp();
        $ruanganPsbp = $this->buatRuanganUntukSkpd($psbp);
        $adminPsbp = $this->buatUserDenganRole('Admin UPT', $psbp, $ruanganPsbp);

        $p2btp = $this->ambilSkpdUptP2btp();
        $ruanganP2btp = $this->buatRuanganUntukSkpd($p2btp);
        $pegawaiP2btp = Pegawai::create([
            'nama_pegawai' => 'Staf P2BTP Target ' . uniqid(),
            'nip' => '199' . rand(10000000, 99999999),
            'id_skpd' => $p2btp->id_skpd,
            'id_ruangan' => $ruanganP2btp->id_ruangan,
            'jabatan' => 'Staf P2BTP',
        ]);

        $asetP2btp = $this->buatAsetBarang($p2btp, $ruanganP2btp, $pegawaiP2btp);

        $mutasiP2btp = MutasiAset::create([
            'tanggal_mutasi' => now()->toDateString(),
            'jenis_mutasi' => 'Ganti Pemegang',
            'keterangan' => 'Mutasi P2BTP Rahasia',
            'id_user_penginput' => 1,
            'status_mutasi' => 'selesai',
        ]);

        DetailMutasiAset::create([
            'id_mutasi' => $mutasiP2btp->id_mutasi,
            'id_aset' => $asetP2btp->id_aset,
            'pegawai_lama' => $pegawaiP2btp->id_pegawai,
            'pegawai_baru' => $pegawaiP2btp->id_pegawai,
            'ruangan_lama' => $ruanganP2btp->id_ruangan,
            'ruangan_baru' => $ruanganP2btp->id_ruangan,
        ]);

        // Login sebagai Admin PSBP lalu mencoba mengunduh BAST P2BTP
        $this->loginSebagai($adminPsbp);

        $res = $this->get(route('mutasi-aset.bast.download', $mutasiP2btp->id_mutasi));

        $res->assertForbidden();
    }

    // =========================================================================
    // 9. Admin Bidang dan Pegawai dilarang (403) mengunduh BAST Aset Barang
    // =========================================================================
    public function test_admin_bidang_dan_pegawai_dilarang_mengunduh_bast_aset_barang(): void
    {
        $skpdBidang = $this->ambilSkpdBidang();
        $ruangan = $this->buatRuanganUntukSkpd($skpdBidang);
        $adminBidang = $this->buatUserDenganRole('Admin Bidang', $skpdBidang, $ruangan);
        $pegawaiBiasa = $this->buatUserDenganRole('Pegawai', $skpdBidang, $ruangan);

        $aset = $this->buatAsetBarang($skpdBidang, $ruangan, $adminBidang->pegawai);

        $mutasi = MutasiAset::create([
            'tanggal_mutasi' => now()->toDateString(),
            'jenis_mutasi' => 'Ganti Pemegang',
            'keterangan' => 'Mutasi Test',
            'id_user_penginput' => 1,
            'status_mutasi' => 'selesai',
        ]);

        DetailMutasiAset::create([
            'id_mutasi' => $mutasi->id_mutasi,
            'id_aset' => $aset->id_aset,
            'pegawai_lama' => $adminBidang->pegawai->id_pegawai,
            'pegawai_baru' => $pegawaiBiasa->pegawai->id_pegawai,
            'ruangan_lama' => $ruangan->id_ruangan,
            'ruangan_baru' => $ruangan->id_ruangan,
        ]);

        // Admin Bidang ditolak (403)
        $this->loginSebagai($adminBidang);
        $resBidang = $this->get(route('mutasi-aset.bast.download', $mutasi->id_mutasi));
        $resBidang->assertForbidden();

        // Pegawai biasa ditolak (403)
        $this->loginSebagai($pegawaiBiasa);
        $resPegawai = $this->get(route('mutasi-aset.bast.download', $mutasi->id_mutasi));
        $resPegawai->assertForbidden();
    }

    // =========================================================================
    // 10. Admin UPT dilarang (403) mengunduh dokumen kendaraan
    // =========================================================================
    public function test_admin_upt_dilarang_mengunduh_dokumen_kendaraan(): void
    {
        $skpdBidang = $this->ambilSkpdBidang();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $skpdBidang);
        $kendaraan = $this->buatKendaraan($adminAset->pegawai);

        $mutasi = MutasiAset::create([
            'tanggal_mutasi' => now()->toDateString(),
            'jenis_mutasi' => 'Ganti Pemegang',
            'keterangan' => 'Mutasi Kendaraan',
            'id_user_penginput' => $adminAset->id_user,
            'status_mutasi' => 'selesai',
        ]);

        DetailMutasiAset::create([
            'id_mutasi' => $mutasi->id_mutasi,
            'id_aset' => $kendaraan->id_aset,
            'pegawai_lama' => $adminAset->pegawai->id_pegawai,
            'pegawai_baru' => $adminAset->pegawai->id_pegawai,
            'ruangan_lama' => null,
            'ruangan_baru' => null,
        ]);

        $p2btp = $this->ambilSkpdUptP2btp();
        $ruanganP2btp = $this->buatRuanganUntukSkpd($p2btp);
        $adminUpt = $this->buatUserDenganRole('Admin UPT', $p2btp, $ruanganP2btp);

        $this->loginSebagai($adminUpt);

        // SPPKD mutasi via id
        $res1 = $this->get(route('kendaraan.sppkd.download', $mutasi->id_mutasi));
        $res1->assertForbidden();

        // BAST kendaraan mutasi via id
        $res2 = $this->get(route('kendaraan.bast.download', $mutasi->id_mutasi));
        $res2->assertForbidden();

        // SPPKD aktif kendaraan
        $res3 = $this->get(route('kendaraan.sppkd-aktif.download', $kendaraan->id_kendaraan));
        $res3->assertForbidden();
    }

    // =========================================================================
    // 11. Alur response mutasi aset barang pada Admin UPT menyediakan link BAST
    //     tanpa redirect otomatis
    // =========================================================================
    public function test_alur_response_mutasi_aset_barang_admin_upt_menyediakan_link_bast_tanpa_auto_redirect(): void
    {
        $p2btp = $this->ambilSkpdUptP2btp();
        $ruangan = $this->buatRuanganUntukSkpd($p2btp);
        $adminP2btp = $this->buatUserDenganRole('Admin UPT', $p2btp, $ruangan);

        $p1 = $adminP2btp->pegawai;
        $p2 = Pegawai::create([
            'nama_pegawai' => 'Pegawai Baru P2BTP ' . uniqid(),
            'nip' => '199' . rand(10000000, 99999999),
            'id_skpd' => $p2btp->id_skpd,
            'id_ruangan' => $ruangan->id_ruangan,
            'jabatan' => 'Penerima Aset P2BTP',
        ]);

        $aset = $this->buatAsetBarang($p2btp, $ruangan, $p1);

        $this->loginSebagai($adminP2btp);

        $res = $this->postJson(route('aset-barang.aset.mutasi', $aset->id_aset), [
            'tipe' => 'pegawai',
            'id_pegawai' => $p2->id_pegawai,
            'keterangan' => 'Mutasi ganti pemegang di lingkup P2BTP',
        ]);

        $res->assertOk();
        $res->assertJsonStructure([
            'success',
            'message',
            'download_bast_url',
            'id_mutasi',
        ]);

        $data = $res->json();
        $this->assertTrue($data['success']);
        $this->assertNotEmpty($data['download_bast_url']);

        // Pastikan session TIDAK menyimpan download_bast_url (tidak ada auto redirect)
        $this->assertFalse(session()->has('download_bast_url'), 'Session tidak boleh memiliki download_bast_url yang memicu auto redirect browser.');

        // Pastikan Admin UPT dapat langsung mengunduh dari URL BAST yang diberikan
        $downloadRes = $this->get($data['download_bast_url']);
        $downloadRes->assertOk();
        $this->assertTrue(
            str_contains($downloadRes->headers->get('content-disposition', ''), '.docx'),
            'URL BAST yang dikembalikan harus dapat diunduh langsung oleh Admin UPT terkait.'
        );
    }

    // =========================================================================
    // 12. Otorisasi BAST Historis: P2BTP dapat unduh BAST aset yang dimutasi ke Dinas
    // =========================================================================
    public function test_admin_upt_p2btp_berhasil_mengunduh_bast_aset_dimutasi_ke_dinas_arsip_historis(): void
    {
        $p2btp = $this->ambilSkpdUptP2btp();
        $dinas = $this->ambilSkpdBidang();

        $ruanganP2btp = $this->buatRuanganUntukSkpd($p2btp);
        $ruanganDinas = $this->buatRuanganUntukSkpd($dinas);

        $adminP2btp = $this->buatUserDenganRole('Admin UPT', $p2btp, $ruanganP2btp);
        $pegawaiP2btp = $adminP2btp->pegawai;
        $pegawaiDinas = $this->buatUserDenganRole('Pegawai', $dinas, $ruanganDinas)->pegawai;

        $aset = $this->buatAsetBarang($p2btp, $ruanganP2btp, $pegawaiP2btp);

        // Peristiwa mutasi: aset dipindahkan dari P2BTP ke Dinas
        $mutasi = MutasiAset::create([
            'tanggal_mutasi' => now()->toDateString(),
            'jenis_mutasi' => 'Ganti Pemegang',
            'keterangan' => 'Mutasi dari P2BTP ke kantor Dinas',
            'id_user_penginput' => $adminP2btp->id_user,
            'status_mutasi' => 'selesai',
        ]);

        DetailMutasiAset::create([
            'id_mutasi' => $mutasi->id_mutasi,
            'id_aset' => $aset->id_aset,
            'pegawai_lama' => $pegawaiP2btp->id_pegawai,
            'pegawai_baru' => $pegawaiDinas->id_pegawai,
            'ruangan_lama' => $ruanganP2btp->id_ruangan,
            'ruangan_baru' => $ruanganDinas->id_ruangan,
        ]);

        // Simulasikan kondisi aset pasca-mutasi: lokasi fisik dan penempatan saat ini sudah di Dinas
        $aset->update([
            'id_lokasi' => $ruanganDinas->id_lokasi,
            'id_skpd' => $dinas->id_skpd,
        ]);
        PenempatanAset::where('id_aset', $aset->id_aset)->update(['status' => 'tidak aktif']);
        PenempatanAset::create([
            'id_aset' => $aset->id_aset,
            'id_ruangan' => $ruanganDinas->id_ruangan,
            'tanggal_penempatan' => now()->toDateString(),
            'status' => 'aktif',
            'is_finalized' => 1,
            'finalized_at' => now(),
        ]);

        // Login sebagai Admin P2BTP dan unduh BAST historis
        $this->loginSebagai($adminP2btp);

        $res = $this->get(route('mutasi-aset.bast.download', $mutasi->id_mutasi));

        $res->assertOk();
        $this->assertTrue(
            str_contains($res->headers->get('content-disposition', ''), '.docx'),
            'Admin UPT P2BTP berhak mengunduh BAST mutasi historis aset yang berasal dari UPT-nya.'
        );
    }

    // =========================================================================
    // 13. Otorisasi BAST Historis: PSBP dapat unduh BAST aset yang dimutasi ke Dinas
    // =========================================================================
    public function test_admin_upt_psbp_berhasil_mengunduh_bast_aset_dimutasi_ke_dinas_arsip_historis(): void
    {
        $psbp = $this->ambilSkpdUptPsbp();
        $dinas = $this->ambilSkpdBidang();

        $ruanganPsbp = $this->buatRuanganUntukSkpd($psbp);
        $ruanganDinas = $this->buatRuanganUntukSkpd($dinas);

        $adminPsbp = $this->buatUserDenganRole('Admin UPT', $psbp, $ruanganPsbp);
        $pegawaiPsbp = $adminPsbp->pegawai;
        $pegawaiDinas = $this->buatUserDenganRole('Pegawai', $dinas, $ruanganDinas)->pegawai;

        $aset = $this->buatAsetBarang($psbp, $ruanganPsbp, $pegawaiPsbp);

        // Peristiwa mutasi: aset dipindahkan dari PSBP ke Dinas
        $mutasi = MutasiAset::create([
            'tanggal_mutasi' => now()->toDateString(),
            'jenis_mutasi' => 'Ganti Pemegang',
            'keterangan' => 'Mutasi dari PSBP ke kantor Dinas',
            'id_user_penginput' => $adminPsbp->id_user,
            'status_mutasi' => 'selesai',
        ]);

        DetailMutasiAset::create([
            'id_mutasi' => $mutasi->id_mutasi,
            'id_aset' => $aset->id_aset,
            'pegawai_lama' => $pegawaiPsbp->id_pegawai,
            'pegawai_baru' => $pegawaiDinas->id_pegawai,
            'ruangan_lama' => $ruanganPsbp->id_ruangan,
            'ruangan_baru' => $ruanganDinas->id_ruangan,
        ]);

        // Simulasikan kondisi aset pasca-mutasi: lokasi fisik dan penempatan saat ini sudah di Dinas
        $aset->update([
            'id_lokasi' => $ruanganDinas->id_lokasi,
            'id_skpd' => $dinas->id_skpd,
        ]);
        PenempatanAset::where('id_aset', $aset->id_aset)->update(['status' => 'tidak aktif']);
        PenempatanAset::create([
            'id_aset' => $aset->id_aset,
            'id_ruangan' => $ruanganDinas->id_ruangan,
            'tanggal_penempatan' => now()->toDateString(),
            'status' => 'aktif',
            'is_finalized' => 1,
            'finalized_at' => now(),
        ]);

        // Login sebagai Admin PSBP dan unduh BAST historis
        $this->loginSebagai($adminPsbp);

        $res = $this->get(route('mutasi-aset.bast.download', $mutasi->id_mutasi));

        $res->assertOk();
        $this->assertTrue(
            str_contains($res->headers->get('content-disposition', ''), '.docx'),
            'Admin UPT PSBP berhak mengunduh BAST mutasi historis aset yang berasal dari UPT-nya.'
        );
    }

    // =========================================================================
    // 14. Otorisasi BAST Historis: P2BTP ditolak mengunduh BAST mutasi Dinas - PSBP
    // =========================================================================
    public function test_admin_upt_p2btp_ditolak_mengunduh_bast_mutasi_hanya_melibatkan_dinas_dan_psbp(): void
    {
        $dinas = $this->ambilSkpdBidang();
        $psbp = $this->ambilSkpdUptPsbp();
        $p2btp = $this->ambilSkpdUptP2btp();

        $ruanganDinas = $this->buatRuanganUntukSkpd($dinas);
        $ruanganPsbp = $this->buatRuanganUntukSkpd($psbp);
        $ruanganP2btp = $this->buatRuanganUntukSkpd($p2btp);

        $adminP2btp = $this->buatUserDenganRole('Admin UPT', $p2btp, $ruanganP2btp);
        $pegawaiDinas = $this->buatUserDenganRole('Pegawai', $dinas, $ruanganDinas)->pegawai;
        $pegawaiPsbp = $this->buatUserDenganRole('Pegawai', $psbp, $ruanganPsbp)->pegawai;

        $aset = $this->buatAsetBarang($dinas, $ruanganDinas, $pegawaiDinas);

        $mutasi = MutasiAset::create([
            'tanggal_mutasi' => now()->toDateString(),
            'jenis_mutasi' => 'Ganti Pemegang',
            'keterangan' => 'Mutasi internal Dinas ke PSBP',
            'id_user_penginput' => 1,
            'status_mutasi' => 'selesai',
        ]);

        DetailMutasiAset::create([
            'id_mutasi' => $mutasi->id_mutasi,
            'id_aset' => $aset->id_aset,
            'pegawai_lama' => $pegawaiDinas->id_pegawai,
            'pegawai_baru' => $pegawaiPsbp->id_pegawai,
            'ruangan_lama' => $ruanganDinas->id_ruangan,
            'ruangan_baru' => $ruanganPsbp->id_ruangan,
        ]);

        $this->loginSebagai($adminP2btp);

        $res = $this->get(route('mutasi-aset.bast.download', $mutasi->id_mutasi));

        $res->assertForbidden();
    }

    // =========================================================================
    // 15. Otorisasi BAST Historis: PSBP ditolak mengunduh BAST mutasi Dinas - P2BTP
    // =========================================================================
    public function test_admin_upt_psbp_ditolak_mengunduh_bast_mutasi_hanya_melibatkan_dinas_dan_p2btp(): void
    {
        $dinas = $this->ambilSkpdBidang();
        $p2btp = $this->ambilSkpdUptP2btp();
        $psbp = $this->ambilSkpdUptPsbp();

        $ruanganDinas = $this->buatRuanganUntukSkpd($dinas);
        $ruanganP2btp = $this->buatRuanganUntukSkpd($p2btp);
        $ruanganPsbp = $this->buatRuanganUntukSkpd($psbp);

        $adminPsbp = $this->buatUserDenganRole('Admin UPT', $psbp, $ruanganPsbp);
        $pegawaiDinas = $this->buatUserDenganRole('Pegawai', $dinas, $ruanganDinas)->pegawai;
        $pegawaiP2btp = $this->buatUserDenganRole('Pegawai', $p2btp, $ruanganP2btp)->pegawai;

        $aset = $this->buatAsetBarang($dinas, $ruanganDinas, $pegawaiDinas);

        $mutasi = MutasiAset::create([
            'tanggal_mutasi' => now()->toDateString(),
            'jenis_mutasi' => 'Ganti Pemegang',
            'keterangan' => 'Mutasi internal Dinas ke P2BTP',
            'id_user_penginput' => 1,
            'status_mutasi' => 'selesai',
        ]);

        DetailMutasiAset::create([
            'id_mutasi' => $mutasi->id_mutasi,
            'id_aset' => $aset->id_aset,
            'pegawai_lama' => $pegawaiDinas->id_pegawai,
            'pegawai_baru' => $pegawaiP2btp->id_pegawai,
            'ruangan_lama' => $ruanganDinas->id_ruangan,
            'ruangan_baru' => $ruanganP2btp->id_ruangan,
        ]);

        $this->loginSebagai($adminPsbp);

        $res = $this->get(route('mutasi-aset.bast.download', $mutasi->id_mutasi));

        $res->assertForbidden();
    }

    // =========================================================================
    // 16. Otorisasi BAST Historis: Fail-closed jika lokasi fisik asal & tujuan null
    // =========================================================================
    public function test_admin_upt_ditolak_jika_lokasi_fisik_asal_dan_tujuan_tidak_tersedia_fail_closed(): void
    {
        $p2btp = $this->ambilSkpdUptP2btp();
        $ruanganP2btp = $this->buatRuanganUntukSkpd($p2btp);
        $adminP2btp = $this->buatUserDenganRole('Admin UPT', $p2btp, $ruanganP2btp);

        $aset = $this->buatAsetBarang($p2btp, $ruanganP2btp, $adminP2btp->pegawai);

        $mutasi = MutasiAset::create([
            'tanggal_mutasi' => now()->toDateString(),
            'jenis_mutasi' => 'Ganti Pemegang',
            'keterangan' => 'Mutasi data tanpa riwayat ruangan fisik',
            'id_user_penginput' => 1,
            'status_mutasi' => 'selesai',
        ]);

        DetailMutasiAset::create([
            'id_mutasi' => $mutasi->id_mutasi,
            'id_aset' => $aset->id_aset,
            'pegawai_lama' => $adminP2btp->pegawai->id_pegawai,
            'pegawai_baru' => $adminP2btp->pegawai->id_pegawai,
            'ruangan_lama' => null,
            'ruangan_baru' => null,
        ]);

        $this->loginSebagai($adminP2btp);

        $res = $this->get(route('mutasi-aset.bast.download', $mutasi->id_mutasi));

        $res->assertForbidden();
    }

    // =========================================================================
    // 17. Otorisasi BAST Historis: Ditolak jika skpd sama tapi lokasi fisik bukan UPT
    // =========================================================================
    public function test_admin_upt_ditolak_jika_skpd_sama_tetapi_lokasi_fisik_mutasi_di_luar_upt(): void
    {
        $p2btp = $this->ambilSkpdUptP2btp();
        $ruanganP2btp = $this->buatRuanganUntukSkpd($p2btp);
        $adminP2btp = $this->buatUserDenganRole('Admin UPT', $p2btp, $ruanganP2btp);

        // Buat ruangan yang memiliki id_skpd = P2BTP, tapi lokasi fisik berada di Dinas
        $lokasiDinas = \App\Models\Lokasi::dinas();
        $ruanganFisikDinas = Ruangan::create([
            'id_skpd' => $p2btp->id_skpd,
            'id_lokasi' => $lokasiDinas->id_lokasi,
            'nama_ruangan' => 'Ruangan Perwakilan Dinas ' . uniqid(),
            'status' => 'Aktif',
        ]);

        $aset = $this->buatAsetBarang($p2btp, $ruanganFisikDinas, $adminP2btp->pegawai);

        $mutasi = MutasiAset::create([
            'tanggal_mutasi' => now()->toDateString(),
            'jenis_mutasi' => 'Ganti Pemegang',
            'keterangan' => 'Mutasi fisik di kantor dinas',
            'id_user_penginput' => 1,
            'status_mutasi' => 'selesai',
        ]);

        DetailMutasiAset::create([
            'id_mutasi' => $mutasi->id_mutasi,
            'id_aset' => $aset->id_aset,
            'pegawai_lama' => $adminP2btp->pegawai->id_pegawai,
            'pegawai_baru' => $adminP2btp->pegawai->id_pegawai,
            'ruangan_lama' => $ruanganFisikDinas->id_ruangan,
            'ruangan_baru' => $ruanganFisikDinas->id_ruangan,
        ]);

        $this->loginSebagai($adminP2btp);

        $res = $this->get(route('mutasi-aset.bast.download', $mutasi->id_mutasi));

        // Harus ditolak (403) karena lokasi fisik bukan di wilayah UPT P2BTP,
        // meskipun id_skpd aset sama dengan id_skpd user.
        $res->assertForbidden();
    }

    // =========================================================================
    // 18. BAST Multi-Detail: 3 aset dalam cakupan P2BTP berhasil diunduh
    // =========================================================================
    public function test_bast_multi_detail_3_aset_dalam_cakupan_p2btp_berhasil_diunduh_dan_memuat_seluruh_aset(): void
    {
        $p2btp = $this->ambilSkpdUptP2btp();
        $ruangan = $this->buatRuanganUntukSkpd($p2btp);
        $adminP2btp = $this->buatUserDenganRole('Admin UPT', $p2btp, $ruangan);

        $p1 = $adminP2btp->pegawai;
        $p2 = Pegawai::create([
            'nama_pegawai' => 'Penerima Multi Aset ' . uniqid(),
            'nip' => '199' . rand(10000000, 99999999),
            'id_skpd' => $p2btp->id_skpd,
            'id_ruangan' => $ruangan->id_ruangan,
            'jabatan' => 'Staf Mutasi Multi',
        ]);

        $aset1 = $this->buatAsetBarang($p2btp, $ruangan, $p1);
        $aset2 = $this->buatAsetBarang($p2btp, $ruangan, $p1);
        $aset3 = $this->buatAsetBarang($p2btp, $ruangan, $p1);

        $mutasi = MutasiAset::create([
            'tanggal_mutasi' => now()->toDateString(),
            'jenis_mutasi' => 'Ganti Pemegang',
            'keterangan' => 'Mutasi serentak 3 aset barang',
            'id_user_penginput' => $adminP2btp->id_user,
            'status_mutasi' => 'selesai',
        ]);

        foreach ([$aset1, $aset2, $aset3] as $aset) {
            DetailMutasiAset::create([
                'id_mutasi' => $mutasi->id_mutasi,
                'id_aset' => $aset->id_aset,
                'pegawai_lama' => $p1->id_pegawai,
                'pegawai_baru' => $p2->id_pegawai,
                'ruangan_lama' => $ruangan->id_ruangan,
                'ruangan_baru' => $ruangan->id_ruangan,
            ]);
        }

        $this->loginSebagai($adminP2btp);

        $res = $this->get(route('mutasi-aset.bast.download', $mutasi->id_mutasi));

        $res->assertOk();
        $disp = $res->headers->get('content-disposition', '');
        $this->assertTrue(str_contains($disp, 'BAST_3_Aset_'), 'Nama file multi-detail harus memuat format BAST_3_Aset_.');
        $this->assertTrue(str_contains($disp, '.docx'));
    }

    // =========================================================================
    // 19. BAST Multi-Detail Campuran: Jika salah satu aset di luar cakupan, tolak seluruh dokumen
    // =========================================================================
    public function test_bast_multi_detail_campuran_upt_ditolak_seluruh_dokumen(): void
    {
        $p2btp = $this->ambilSkpdUptP2btp();
        $psbp = $this->ambilSkpdUptPsbp();

        $ruanganP2btp = $this->buatRuanganUntukSkpd($p2btp);
        $ruanganPsbp = $this->buatRuanganUntukSkpd($psbp);

        $adminP2btp = $this->buatUserDenganRole('Admin UPT', $p2btp, $ruanganP2btp);
        $adminPsbp = $this->buatUserDenganRole('Admin UPT', $psbp, $ruanganPsbp);

        $asetP2btp = $this->buatAsetBarang($p2btp, $ruanganP2btp, $adminP2btp->pegawai);
        $asetPsbp = $this->buatAsetBarang($psbp, $ruanganPsbp, $adminPsbp->pegawai);

        $mutasi = MutasiAset::create([
            'tanggal_mutasi' => now()->toDateString(),
            'jenis_mutasi' => 'Ganti Pemegang',
            'keterangan' => 'Mutasi campuran P2BTP dan PSBP',
            'id_user_penginput' => 1,
            'status_mutasi' => 'selesai',
        ]);

        // Detail 1: aset P2BTP
        DetailMutasiAset::create([
            'id_mutasi' => $mutasi->id_mutasi,
            'id_aset' => $asetP2btp->id_aset,
            'pegawai_lama' => $adminP2btp->pegawai->id_pegawai,
            'pegawai_baru' => $adminP2btp->pegawai->id_pegawai,
            'ruangan_lama' => $ruanganP2btp->id_ruangan,
            'ruangan_baru' => $ruanganP2btp->id_ruangan,
        ]);

        // Detail 2: aset PSBP
        DetailMutasiAset::create([
            'id_mutasi' => $mutasi->id_mutasi,
            'id_aset' => $asetPsbp->id_aset,
            'pegawai_lama' => $adminPsbp->pegawai->id_pegawai,
            'pegawai_baru' => $adminPsbp->pegawai->id_pegawai,
            'ruangan_lama' => $ruanganPsbp->id_ruangan,
            'ruangan_baru' => $ruanganPsbp->id_ruangan,
        ]);

        // Admin P2BTP mencoba mengunduh dokumen mutasi campuran
        $this->loginSebagai($adminP2btp);

        $res = $this->get(route('mutasi-aset.bast.download', $mutasi->id_mutasi));

        // Harus ditolak (403): Dokumen tidak boleh dibagikan jika salah satu detail di luar cakupan
        $res->assertForbidden();
    }

    // =========================================================================
    // 20. Pencegahan Lost Update pada Mutasi Pemegang Kendaraan
    // =========================================================================
    public function test_mutasi_pemegang_kendaraan_mencegah_lost_update_jika_pemegang_telah_berubah_oleh_proses_lain(): void
    {
        $skpdBidang = $this->ambilSkpdBidang();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $skpdBidang);
        $pegawaiA = $adminAset->pegawai;

        $kendaraan = $this->buatKendaraan($pegawaiA);

        $ruangan = $kendaraan->aset->penempatanAktif->ruangan;

        $pegawaiB = Pegawai::create([
            'nama_pegawai' => 'Pegawai B ' . uniqid(),
            'nip' => '199' . rand(10000000, 99999999),
            'id_skpd' => $skpdBidang->id_skpd,
            'id_ruangan' => $ruangan->id_ruangan,
            'jabatan' => 'Pemegang B',
        ]);

        $pegawaiC = Pegawai::create([
            'nama_pegawai' => 'Pegawai C ' . uniqid(),
            'nip' => '199' . rand(10000000, 99999999),
            'id_skpd' => $skpdBidang->id_skpd,
            'id_ruangan' => $ruangan->id_ruangan,
            'jabatan' => 'Pemegang C',
        ]);

        $this->loginSebagai($adminAset);

        // Simulasi transaksi konkuren: Proses lain telah mengubah pemegang dari A ke B
        $now = now()->toDateString();
        PemegangAset::where('id_aset', $kendaraan->id_aset)
            ->where('status', 'aktif')
            ->update(['status' => 'tidak aktif', 'tanggal_selesai' => $now]);

        PemegangAset::create([
            'id_aset' => $kendaraan->id_aset,
            'id_pegawai' => $pegawaiB->id_pegawai,
            'tanggal_mulai' => $now,
            'status' => 'aktif',
        ]);
        $kendaraan->update(['pemegang' => $pegawaiB->nama_pegawai]);

        // Request mutasi yang dikirim oleh pengguna yang membuka form saat pemegang masih A (stale baseline)
        $res = $this->post(route('kendaraan.mutasi-pemegang.store', $kendaraan->id_kendaraan), [
            'id_pegawai_baru' => $pegawaiC->id_pegawai,
            'id_pegawai_lama' => $pegawaiA->id_pegawai, // Baseline basi
            'tanggal_mutasi' => now()->toDateString(),
            'nomor_surat' => '028/MUT-STALE/01',
            'keterangan' => 'Mencoba memutasi dengan baseline pemegang yang sudah basi',
        ]);

        // Harus ditolak dengan pesan error lost update yang jelas
        $res->assertSessionHas('error', 'Data pemegang kendaraan telah berubah oleh proses lain. Silakan muat ulang halaman.');

        // Pastikan pemegang kendaraan TIDAK tertimpa (tetap Pegawai B)
        $kendaraan->refresh();
        $this->assertEquals($pegawaiB->nama_pegawai, $kendaraan->pemegang);

        // Pastikan PemegangAset aktif tetap Pegawai B
        $pemegangAktif = PemegangAset::where('id_aset', $kendaraan->id_aset)
            ->where('status', 'aktif')
            ->first();
        $this->assertNotNull($pemegangAktif);
        $this->assertEquals($pegawaiB->id_pegawai, $pemegangAktif->id_pegawai);
    }
}
