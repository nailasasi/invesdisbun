<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\Kendaraan;
use App\Models\Lokasi;
use App\Models\Pegawai;
use App\Models\PenempatanAset;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\Skpd;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Verifikasi Tahap C: Penempatan Awal & Finalisasi Kendaraan.
 * Hanya memakai data yang ada; tidak membuat migration/backfill permanen.
 */
class KendaraanPenempatanFinalisasiTest extends TestCase
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
        return $this->namaDatabasePengujian();
    }

    private function adminAset(): User
    {
        return User::whereHas('role', fn ($r) => $r->where('nama_role', 'Admin Aset'))->first();
    }

    public function test_penempatan_awal_berhasil_dan_status_0(): void
    {
        $this->actingAs($this->adminAset());

        // Cari kendaraan aktif tanpa penempatan aktif dan tanpa pemegang aktif
        $kendaraan = Kendaraan::whereHas('aset', function ($q) {
            $q->where('status_aset', 'aktif')->where('is_kendaraan', 1);
        })
            ->where(function ($q) {
                $q->whereNull('pemegang')->orWhere('pemegang', '');
            })
            ->get()
            ->first(function ($k) {
                $aset = $k->aset;
                if (! $aset) return false;
                return ! $aset->penempatanAktif()->exists() && ! $aset->pemegangSaatIni()->exists();
            });

        $this->assertNotNull($kendaraan, 'Tidak ditemukan kendaraan aktif tanpa penempatan/pemegang aktif.');

        $pegawai = Pegawai::whereNotNull('id_ruangan')->first();
        $this->assertNotNull($pegawai);

        $res = $this->postJson(route('kendaraan.penempatan-awal', $kendaraan), [
            'mode' => 'pegawai',
            'id_pegawai' => $pegawai->id_pegawai,
        ]);

        $res->assertOk()->assertJson(['success' => true]);
        $pen = $kendaraan->aset->penempatanAktif()->first();
        $this->assertNotNull($pen);
        $this->assertSame(0, (int) $pen->is_finalized);
    }

    public function test_penempatan_awal_ditolak_jika_sudah_ada_penempatan_aktif(): void
    {
        $this->actingAs($this->adminAset());

        $kendaraan = Kendaraan::whereHas('aset', function ($q) {
            $q->where('status_aset', 'aktif')->where('is_kendaraan', 1)->whereHas('penempatanAktif');
        })->first();

        if (! $kendaraan) {
            $role = Role::firstOrCreate(['nama_role' => 'Admin Aset'], ['deskripsi' => 'Admin Aset']);
            $lokasi = \App\Models\Lokasi::firstOrCreate(['nama_lokasi' => 'L'.uniqid()]);
            $skpd = \App\Models\Skpd::firstOrCreate(['nama_skpd' => 'S'.uniqid()], ['id_lokasi' => $lokasi->id_lokasi]);
            $pegawaiAdmin = Pegawai::create(['nama_pegawai' => 'PA '.uniqid(), 'nip' => 'N'.rand(100000, 999999), 'id_skpd' => $skpd->id_skpd]);
            $user = User::create(['username' => 't_admin_'.uniqid(), 'password' => bcrypt('password'), 'id_role' => $role->id_role, 'id_pegawai' => $pegawaiAdmin->id_pegawai]);
            $ruangan = Ruangan::firstOrCreate(['nama_ruangan' => 'R'.uniqid()], ['id_lokasi' => $lokasi->id_lokasi, 'id_skpd' => $skpd->id_skpd, 'status' => 'Aktif']);
            $aset = Aset::create(['nomor_kartu_barang' => 'K'.uniqid(), 'nilai_perolehan' => 1000, 'kondisi' => 'Baik', 'status_aset' => 'aktif', 'is_kendaraan' => true]);
            $kendaraan = Kendaraan::create(['id_aset' => $aset->id_aset, 'nama_kendaraan' => 'KT'.uniqid(), 'jenis_kendaraan' => 'Roda Empat (Mobil)', 'nomor_rangka' => 'RR'.uniqid(), 'nomor_mesin' => 'MM'.uniqid(), 'pemegang' => null, 'merk' => 'T']);
            PenempatanAset::create(['id_aset' => $aset->id_aset, 'id_ruangan' => $ruangan->id_ruangan, 'tanggal_mulai' => now()->toDateString(), 'status' => 'aktif', 'is_finalized' => 0]);
        }

        $this->assertNotNull($kendaraan);

        $pegawai = Pegawai::first();
        $res = $this->postJson(route('kendaraan.penempatan-awal', $kendaraan), [
            'mode' => 'pegawai',
            'id_pegawai' => $pegawai->id_pegawai,
        ]);

        $res->assertStatus(422);
    }

    public function test_finalisasi_individual_0_menjadi_1_dengan_timestamp(): void
    {
        $this->actingAs($this->adminAset());

        // Cari penempatan aktif berstatus 0 pada kendaraan
        $pen = PenempatanAset::where('status', 'aktif')->where('is_finalized', 0)->whereHas('aset.kendaraan')->first();
        if (! $pen) {
            $role = Role::firstOrCreate(['nama_role' => 'Admin Aset'], ['deskripsi' => 'Admin Aset']);
            $lokasi = \App\Models\Lokasi::firstOrCreate(['nama_lokasi' => 'L2'.uniqid()]);
            $skpd = \App\Models\Skpd::firstOrCreate(['nama_skpd' => 'S2'.uniqid()], ['id_lokasi' => $lokasi->id_lokasi]);
            $pegawaiAdmin = Pegawai::create(['nama_pegawai' => 'PA2 '.uniqid(), 'nip' => 'N2'.rand(100000, 999999), 'id_skpd' => $skpd->id_skpd]);
            $user = User::create(['username' => 't_admin2_'.uniqid(), 'password' => bcrypt('password'), 'id_role' => $role->id_role, 'id_pegawai' => $pegawaiAdmin->id_pegawai]);
            $ruangan = Ruangan::firstOrCreate(['nama_ruangan' => 'R2'.uniqid()], ['id_lokasi' => $lokasi->id_lokasi, 'id_skpd' => $skpd->id_skpd, 'status' => 'Aktif']);
            $aset = Aset::create(['nomor_kartu_barang' => 'K2'.uniqid(), 'nilai_perolehan' => 1000, 'kondisi' => 'Baik', 'status_aset' => 'aktif', 'is_kendaraan' => true]);
            $kendaraan = Kendaraan::create(['id_aset' => $aset->id_aset, 'nama_kendaraan' => 'KT2'.uniqid(), 'jenis_kendaraan' => 'Roda Empat (Mobil)', 'nomor_rangka' => 'RR2'.uniqid(), 'nomor_mesin' => 'MM2'.uniqid(), 'pemegang' => null, 'merk' => 'T']);
            $pen = PenempatanAset::create(['id_aset' => $aset->id_aset, 'id_ruangan' => $ruangan->id_ruangan, 'tanggal_mulai' => now()->toDateString(), 'status' => 'aktif', 'is_finalized' => 0]);
        } else {
            $kendaraan = $pen->aset->kendaraan;
        }

        $this->assertNotNull($pen);
        $this->assertNotNull($kendaraan);

        $res = $this->postJson(route('kendaraan.finalisasi-penempatan', $kendaraan));
        $res->assertOk()->assertJson(['success' => true]);

        $pen->refresh();
        $this->assertSame(1, (int) $pen->is_finalized);
        $this->assertNotNull($pen->finalized_at);
        $this->assertNotNull($pen->finalized_by);
    }

    public function test_finalisasi_individual_menolak_null_dan_1(): void
    {
        $this->actingAs($this->adminAset());

        // NULL
        $penNull = PenempatanAset::where('status', 'aktif')->whereNull('is_finalized')->first();
        if ($penNull) {
            $kendaraan = Kendaraan::where('id_aset', $penNull->id_aset)->first();
            if ($kendaraan) {
                $res = $this->postJson(route('kendaraan.finalisasi-penempatan', $kendaraan));
                $res->assertStatus(422);
            }
        }

        // 1
        $penOne = PenempatanAset::where('status', 'aktif')->where('is_finalized', 1)->first();
        if ($penOne) {
            $kendaraan = Kendaraan::where('id_aset', $penOne->id_aset)->first();
            if ($kendaraan) {
                $res = $this->postJson(route('kendaraan.finalisasi-penempatan', $kendaraan));
                $res->assertStatus(422);
            }
        }
    }

    public function test_finalisasi_massal_hanya_valid_dan_lapor(): void
    {
        $this->actingAs($this->adminAset());

        $penValid = PenempatanAset::where('status', 'aktif')->where('is_finalized', 0)->whereHas('aset.kendaraan')->with('aset.kendaraan')->first();
        if (! $penValid || ! $penValid->aset || ! $penValid->aset->kendaraan) {
            $role = Role::firstOrCreate(['nama_role' => 'Admin Aset'], ['deskripsi' => 'Admin Aset']);
            $lokasi = \App\Models\Lokasi::firstOrCreate(['nama_lokasi' => 'L3'.uniqid()]);
            $skpd = \App\Models\Skpd::firstOrCreate(['nama_skpd' => 'S3'.uniqid()], ['id_lokasi' => $lokasi->id_lokasi]);
            $pegawaiAdmin = Pegawai::create(['nama_pegawai' => 'PA3 '.uniqid(), 'nip' => 'N3'.rand(100000, 999999), 'id_skpd' => $skpd->id_skpd]);
            $user = User::create(['username' => 't_admin3_'.uniqid(), 'password' => bcrypt('password'), 'id_role' => $role->id_role, 'id_pegawai' => $pegawaiAdmin->id_pegawai]);
            $ruangan = Ruangan::firstOrCreate(['nama_ruangan' => 'R3'.uniqid()], ['id_lokasi' => $lokasi->id_lokasi, 'id_skpd' => $skpd->id_skpd, 'status' => 'Aktif']);
            $aset = Aset::create(['nomor_kartu_barang' => 'K3'.uniqid(), 'nilai_perolehan' => 1000, 'kondisi' => 'Baik', 'status_aset' => 'aktif', 'is_kendaraan' => true]);
            $kendaraan = Kendaraan::create(['id_aset' => $aset->id_aset, 'nama_kendaraan' => 'KT3'.uniqid(), 'jenis_kendaraan' => 'Roda Empat (Mobil)', 'nomor_rangka' => 'RR3'.uniqid(), 'nomor_mesin' => 'MM3'.uniqid(), 'pemegang' => null, 'merk' => 'T']);
            $penValid = PenempatanAset::create(['id_aset' => $aset->id_aset, 'id_ruangan' => $ruangan->id_ruangan, 'tanggal_mulai' => now()->toDateString(), 'status' => 'aktif', 'is_finalized' => 0]);
        }

        $kendaraanValid = $penValid->aset->kendaraan ?? Kendaraan::where('id_aset', $penValid->id_aset)->first();
        $this->assertNotNull($kendaraanValid);

        // tambah satu yang akan dilewati (sudah 1)
        $penSkip = PenempatanAset::where('status', 'aktif')->where('is_finalized', 1)->with('aset.kendaraan')->first();
        if (! $penSkip || ! $penSkip->aset || ! $penSkip->aset->kendaraan) {
            $lokasi = \App\Models\Lokasi::firstOrCreate(['nama_lokasi' => 'L4'.uniqid()]);
            $skpd = \App\Models\Skpd::firstOrCreate(['nama_skpd' => 'S4'.uniqid()], ['id_lokasi' => $lokasi->id_lokasi]);
            $ruangan = Ruangan::firstOrCreate(['nama_ruangan' => 'R4'.uniqid()], ['id_lokasi' => $lokasi->id_lokasi, 'id_skpd' => $skpd->id_skpd, 'status' => 'Aktif']);
            $aset = Aset::create(['nomor_kartu_barang' => 'K4'.uniqid(), 'nilai_perolehan' => 1000, 'kondisi' => 'Baik', 'status_aset' => 'aktif', 'is_kendaraan' => true]);
            $kendaraanSkip = Kendaraan::create(['id_aset' => $aset->id_aset, 'nama_kendaraan' => 'KT4'.uniqid(), 'jenis_kendaraan' => 'Roda Empat (Mobil)', 'nomor_rangka' => 'RR4'.uniqid(), 'nomor_mesin' => 'MM4'.uniqid(), 'pemegang' => null, 'merk' => 'T']);
            $penSkip = PenempatanAset::create(['id_aset' => $aset->id_aset, 'id_ruangan' => $ruangan->id_ruangan, 'tanggal_mulai' => now()->toDateString(), 'status' => 'aktif', 'is_finalized' => 1]);
            $kendaraanSkipCreated = $kendaraanSkip;
        }

        $ids = [$kendaraanValid->id_kendaraan];
        if (isset($kendaraanSkipCreated)) {
            $ids[] = $kendaraanSkipCreated->id_kendaraan;
        } elseif ($penSkip && $penSkip->aset && $penSkip->aset->kendaraan) {
            $ids[] = $penSkip->aset->kendaraan->id_kendaraan;
        }

        $res = $this->postJson(route('kendaraan.finalisasi-penempatan-massal'), ['ids' => $ids]);
        $res->assertOk();
        $data = $res->json();
        $this->assertGreaterThanOrEqual(1, $data['diproses'] ?? $data['finalized'] ?? 0);

        $penValid->refresh();
        $this->assertSame(1, (int) $penValid->is_finalized);
    }

    public function test_mutasi_ditolak_jika_penempatan_0_atau_null(): void
    {
        $this->actingAs($this->adminAset());

        // penempatan 0
        $pen0 = PenempatanAset::where('status', 'aktif')->where('is_finalized', 0)->with('aset.kendaraan')->first();
        if ($pen0 && $pen0->aset && $pen0->aset->kendaraan) {
            $k = $pen0->aset->kendaraan;
            $pegawaiBaru = Pegawai::first();
            $res = $this->post(route('kendaraan.mutasi-pemegang.store', $k), ['id_pegawai_baru' => $pegawaiBaru->id_pegawai]);
            $this->assertStringContainsString('finalisasi', strtolower(session('error') ?? ''));
        }

        // penempatan null
        $penNull = PenempatanAset::where('status', 'aktif')->whereNull('is_finalized')->with('aset.kendaraan')->first();
        if ($penNull && $penNull->aset && $penNull->aset->kendaraan) {
            $k = $penNull->aset->kendaraan;
            $pegawaiBaru = Pegawai::first();
            $res = $this->post(route('kendaraan.mutasi-pemegang.store', $k), ['id_pegawai_baru' => $pegawaiBaru->id_pegawai]);
            $this->assertStringContainsString('verifikasi', strtolower(session('error') ?? ''));
        }
    }

    public function test_mutasi_bisa_dilakukan_jika_penempatan_1(): void
    {
        $this->actingAs($this->adminAset());

        $pen1 = PenempatanAset::where('status', 'aktif')->where('is_finalized', 1)->with('aset.kendaraan')->first();
        if (! $pen1 || ! $pen1->aset || ! $pen1->aset->kendaraan) {
            $this->markTestSkipped('Tidak ada kendaraan dengan penempatan aktif 1.');
            return;
        }

        $k = $pen1->aset->kendaraan;
        // cari pegawai berbeda
        $pegawaiLamaId = $pen1->aset->pemegangSaatIni?->id_pegawai;
        $pegawaiBaru = Pegawai::when($pegawaiLamaId, fn($q) => $q->where('id_pegawai', '!=', $pegawaiLamaId))->first();
        if (! $pegawaiBaru) {
            $pegawaiBaru = Pegawai::first();
        }

        $res = $this->post(route('kendaraan.mutasi-pemegang.store', $k), [
            'id_pegawai_baru' => $pegawaiBaru->id_pegawai,
            'tanggal_mutasi' => now()->toDateString(),
        ]);

        $res->assertStatus(302);
        $k->refresh();
        $this->assertSame($pegawaiBaru->nama_pegawai, $k->pemegang);
    }

    /**
     * Mutasi Ganti Pemegang: PemegangAset lama ditutup, pemegang baru menjadi
     * SATU-SATUNYA pemegang aktif, dan SKPD aset mengikuti pemegang baru.
     */
    public function test_mutasi_menutup_pemegang_lama_dan_membuat_pemegang_aktif_baru(): void
    {
        $this->actingAs($this->adminAset());

        $pen1 = PenempatanAset::where('status', 'aktif')->where('is_finalized', 1)->with('aset.kendaraan')->first();
        if (! $pen1 || ! $pen1->aset || ! $pen1->aset->kendaraan) {
            $this->markTestSkipped('Tidak ada kendaraan dengan penempatan aktif 1.');
        }

        $aset = $pen1->aset;
        $kendaraan = $aset->kendaraan;

        $pegawaiLamaId = $aset->pemegangSaatIni?->id_pegawai;
        $pegawaiBaru = Pegawai::when($pegawaiLamaId, fn ($q) => $q->where('id_pegawai', '!=', $pegawaiLamaId))->first();
        if (! $pegawaiBaru) {
            $this->markTestSkipped('Tidak ada pegawai lain untuk mutasi.');
        }

        $this->post(route('kendaraan.mutasi-pemegang.store', $kendaraan), [
            'id_pegawai_baru' => $pegawaiBaru->id_pegawai,
            'tanggal_mutasi' => now()->toDateString(),
        ])->assertStatus(302);

        // Hanya satu pemegang aktif, dan itu pemegang baru.
        $aktif = \App\Models\PemegangAset::where('id_aset', $aset->id_aset)
            ->where('status', 'aktif')
            ->get();
        $this->assertCount(1, $aktif, 'Harus ada tepat satu PemegangAset aktif setelah mutasi.');
        $this->assertSame((int) $pegawaiBaru->id_pegawai, (int) $aktif->first()->id_pegawai);
        $this->assertNotNull($aktif->first()->tanggal_mulai);

        // Pemegang lama (bila ada) tertutup.
        if ($pegawaiLamaId) {
            $lama = \App\Models\PemegangAset::where('id_aset', $aset->id_aset)
                ->where('id_pegawai', $pegawaiLamaId)
                ->where('status', 'aktif')
                ->exists();
            $this->assertFalse($lama, 'Pemegang lama tidak boleh tetap aktif.');
        }

        // Data kendaraan konsisten dengan relasi.
        $kendaraan->refresh();
        $this->assertSame($pegawaiBaru->nama_pegawai, $kendaraan->pemegang);

        // SKPD aset mengikuti pemegang baru.
        if ($pegawaiBaru->id_skpd) {
            $this->assertSame(
                (int) $pegawaiBaru->id_skpd,
                (int) $aset->fresh()->id_skpd,
                'SKPD aset harus mengikuti pemegang baru.'
            );
        }
    }

    public function test_edit_pemegang_penempatan_berhasil_sebelum_finalisasi(): void
    {
        $this->actingAs($this->adminAset());

        // Cari penempatan aktif is_finalized 0 yang asetnya kendaraan &
        // sudah punya pemegang aktif (edit pemegang hanya untuk kendaraan
        // berpemegang dan belum difinalisasi).
        $kendaraan = Kendaraan::whereHas('aset', function ($q) {
            $q->where('is_kendaraan', 1)->whereHas('penempatanAktif', fn ($p) => $p->where('status', 'aktif')->where('is_finalized', 0))
                ->whereHas('pemegangSaatIni');
        })->first();

        if (! $kendaraan) {
            $this->markTestSkipped('Tidak ada kendaraan dengan penempatan aktif 0 + pemegang aktif.');
        }

        $pegawaiLamaId = $kendaraan->aset->pemegangSaatIni?->id_pegawai;
        $pegawaiBaru = Pegawai::where('id_pegawai', '!=', $pegawaiLamaId)->first();
        $this->assertNotNull($pegawaiBaru);

        $res = $this->postJson(route('kendaraan.edit-pemegang-penempatan', $kendaraan), [
            'id_pegawai' => $pegawaiBaru->id_pegawai,
        ]);

        $res->assertOk()->assertJson(['success' => true]);

        $aktif = \App\Models\PemegangAset::where('id_aset', $kendaraan->id_aset)
            ->where('status', 'aktif')
            ->get();
        $this->assertCount(1, $aktif, 'Edit pemegang tidak boleh membuat record pemegang baru.');
        $this->assertSame((int) $pegawaiBaru->id_pegawai, (int) $aktif->first()->id_pegawai);

        $pen = $kendaraan->aset->penempatanAktif()->first();
        $this->assertSame(0, (int) $pen->is_finalized, 'Edit pemegang tidak mengubah status finalisasi.');
    }

    public function test_edit_pemegang_penempatan_ditolak_jika_sudah_difinalisasi(): void
    {
        $this->actingAs($this->adminAset());

        $kendaraan = Kendaraan::whereHas('aset', function ($q) {
            $q->where('is_kendaraan', 1)->whereHas('penempatanAktif', fn ($p) => $p->where('status', 'aktif')->where('is_finalized', 1));
        })->first();

        if (! $kendaraan) {
            $this->markTestSkipped('Tidak ada kendaraan dengan penempatan aktif 1.');
        }

        $pegawaiLamaId = $kendaraan->aset->pemegangSaatIni?->id_pegawai;
        $pegawaiBaru = Pegawai::where('id_pegawai', '!=', $pegawaiLamaId)->first();
        if (! $pegawaiBaru) {
            $this->markTestSkipped('Tidak ada pegawai lain.');
        }

        $res = $this->postJson(route('kendaraan.edit-pemegang-penempatan', $kendaraan), [
            'id_pegawai' => $pegawaiBaru->id_pegawai,
        ]);

        $res->assertStatus(422);
    }
}
