<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\DetailMutasiAset;
use App\Models\MutasiAset;
use App\Models\Pegawai;
use App\Models\PemegangAset;
use App\Models\PenempatanAset;
use App\Models\Ruangan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Aturan finalisasi penempatan pada alur mutasi di modul Aset Barang.
 *
 * Aturan: setiap mutasi yang membuat/mengganti penempatan aktif langsung
 * menghasilkan penempatan berstatus "Sudah Difinalisasi" (is_finalized = 1)
 * beserta jejak finalisasinya — mutasi tidak memerlukan finalisasi ulang.
 *
 * Yang TIDAK ikut aturan ini (tetap "Belum Difinalisasi", is_finalized = 0):
 *   - Penempatan Awal (tombol +),
 *   - Pasang Aset ke Pegawai,
 *   - Pasang Aset & Pasang Aset Massal (modul Ruangan).
 *
 * Memakai koneksi MySQL nyata dibungkus transaksi yang di-rollback.
 */
class MutasiAsetBarangFinalisasiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $this->namaDatabase(),
        ]);

        DB::beginTransaction();

        $this->actingAs($this->adminAset());
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

    /**
     * Aset tanpa pemegang aktif dengan penempatan aktif berstatus finalisasi
     * tertentu. NULL = "Perlu Verifikasi" (data lama).
     *
     * @return array{0:int,1:Ruangan,2:Ruangan}|null
     */
    private function asetTanpaPemegang(?int $nilaiFinalisasi = 1): ?array
    {
        $query = PenempatanAset::where('status', 'aktif')
            ->whereHas('aset', fn ($q) => $q->where('is_kendaraan', false)->whereDoesntHave('pemegangSaatIni'));

        if (is_null($nilaiFinalisasi)) {
            $query->whereNull('is_finalized');
        } else {
            $query->where('is_finalized', $nilaiFinalisasi);
        }

        $p = $query->first();

        if (! $p) {
            return null;
        }

        $asal = Ruangan::find($p->id_ruangan);

        if (! $asal) {
            return null;
        }

        $tujuan = Ruangan::where('status', 'Aktif')
            ->where('id_ruangan', '!=', $asal->id_ruangan)
            ->first();

        if (! $tujuan) {
            return null;
        }

        return [$p->id_aset, $asal, $tujuan];
    }

    /**
     * Aset yang sedang dipegang pegawai + pemegang baru yang punya ruang kerja.
     *
     * Penempatan aktif aset WAJIB sudah difinalisasi (atau belum ada sama
     * sekali) — kalau tidak, gerbang mutasi menolak sebelum alur berjalan.
     *
     * @return array{0:Aset,1:int,2:Pegawai}|null
     */
    private function asetDenganPemegang(): ?array
    {
        $baru = Pegawai::whereNotNull('id_ruangan')
            ->whereHas('ruangan', fn ($q) => $q->where('status', 'Aktif'))
            ->first();

        if (! $baru) {
            return null;
        }

        foreach (PemegangAset::where('status', 'aktif')->get() as $pemegang) {
            if ((int) $pemegang->id_pegawai === (int) $baru->id_pegawai) {
                continue;
            }

            $aset = Aset::find($pemegang->id_aset);

            if (! $aset || $aset->is_kendaraan) {
                continue;
            }

            $penempatan = PenempatanAset::where('id_aset', $aset->id_aset)
                ->where('status', 'aktif')
                ->first();

            if ($penempatan && (int) ($penempatan->is_finalized ?? 0) !== 1) {
                continue;
            }

            return [$aset, $pemegang->id_pegawai, $baru];
        }

        return null;
    }

    private function mutasi(int $idAset, array $payload): TestResponse
    {
        return $this->postJson(route('aset-barang.aset.mutasi', $idAset), $payload);
    }

    public function test_mutasi_pindah_ruangan_membuat_penempatan_baru_sudah_difinalisasi(): void
    {
        $sampel = $this->asetTanpaPemegang(1);

        if (! $sampel) {
            $this->markTestSkipped('Tidak ada aset tanpa pemegang dengan penempatan yang sudah difinalisasi.');
        }

        [$idAset, $asal, $tujuan] = $sampel;

        $mutasiAwal = MutasiAset::count();
        $detailAwal = DetailMutasiAset::count();

        $res = $this->mutasi($idAset, [
            'tipe' => 'ruangan',
            'id_ruangan' => $tujuan->id_ruangan,
            'keterangan' => 'uji mutasi pindah ruangan',
        ]);

        $res->assertOk();
        $res->assertJson(['success' => true]);

        // Satu penempatan aktif, di ruangan tujuan, langsung berstatus 1.
        $aktif = PenempatanAset::where('id_aset', $idAset)->where('status', 'aktif')->get();
        $this->assertSame(1, $aktif->count(), 'Tidak boleh ada penempatan aktif ganda.');
        $this->assertSame($tujuan->id_ruangan, $aktif->first()->id_ruangan);
        $this->assertSame(1, (int) $aktif->first()->is_finalized, 'Penempatan baru hasil mutasi wajib langsung berstatus 1.');
        $this->assertNotNull($aktif->first()->finalized_at, 'Jejak waktu finalisasi wajib terisi.');
        $this->assertSame((int) auth()->id(), (int) $aktif->first()->finalized_by, 'Jejak pelaku finalisasi wajib terisi.');

        // Riwayat mutasi tercatat tepat satu kali.
        $this->assertSame($mutasiAwal + 1, MutasiAset::count());
        $this->assertSame($detailAwal + 1, DetailMutasiAset::count());

        $detail = DetailMutasiAset::where('id_aset', $idAset)->latest('id_mutasi')->first();
        $this->assertSame($asal->id_ruangan, (int) $detail->ruangan_lama);
        $this->assertSame($tujuan->id_ruangan, (int) $detail->ruangan_baru);
        $this->assertSame(
            'Pindah Ruangan',
            MutasiAset::where('id_mutasi', $detail->id_mutasi)->value('jenis_mutasi')
        );
    }

    public function test_mutasi_ganti_pemegang_membuat_penempatan_baru_sudah_difinalisasi(): void
    {
        $sampel = $this->asetDenganPemegang();

        if (! $sampel) {
            $this->markTestSkipped('Tidak ada aset berpemegang untuk diuji mutasi Ganti Pemegang.');
        }

        [$aset, $pegawaiLama, $pegawaiBaru] = $sampel;

        $mutasiAwal = MutasiAset::count();
        $detailAwal = DetailMutasiAset::count();

        $res = $this->mutasi($aset->id_aset, [
            'tipe' => 'pegawai',
            'id_pegawai' => $pegawaiBaru->id_pegawai,
            'keterangan' => 'uji mutasi ganti pemegang',
        ]);

        $res->assertOk();
        $res->assertJson(['success' => true]);

        // Penempatan aktif baru di ruang kerja pemegang baru, langsung final.
        $aktif = PenempatanAset::where('id_aset', $aset->id_aset)->where('status', 'aktif')->get();
        $this->assertSame(1, $aktif->count(), 'Tidak boleh ada penempatan aktif ganda.');
        $this->assertSame((int) $pegawaiBaru->id_ruangan, (int) $aktif->first()->id_ruangan);
        $this->assertSame(1, (int) $aktif->first()->is_finalized, 'Penempatan baru hasil mutasi wajib langsung berstatus 1.');
        $this->assertNotNull($aktif->first()->finalized_at, 'Jejak waktu finalisasi wajib terisi.');
        $this->assertSame((int) auth()->id(), (int) $aktif->first()->finalized_by, 'Jejak pelaku finalisasi wajib terisi.');

        // Pemegang lama tertutup, pemegang baru aktif.
        $this->assertSame(
            'tidak aktif',
            PemegangAset::where('id_aset', $aset->id_aset)->where('id_pegawai', $pegawaiLama)->value('status')
        );
        $this->assertSame(
            'aktif',
            PemegangAset::where('id_aset', $aset->id_aset)->where('id_pegawai', $pegawaiBaru->id_pegawai)->value('status')
        );

        // Riwayat mutasi tercatat tepat satu kali.
        $this->assertSame($mutasiAwal + 1, MutasiAset::count());
        $this->assertSame($detailAwal + 1, DetailMutasiAset::count());

        $detail = DetailMutasiAset::where('id_aset', $aset->id_aset)->latest('id_mutasi')->first();
        $this->assertSame((int) $pegawaiLama, (int) $detail->pegawai_lama);
        $this->assertSame((int) $pegawaiBaru->id_pegawai, (int) $detail->pegawai_baru);
        $this->assertSame(
            'Ganti Pemegang',
            MutasiAset::where('id_mutasi', $detail->id_mutasi)->value('jenis_mutasi')
        );
    }

    public function test_mutasi_ganti_pemegang_tanpa_penempatan_awal_juga_difinalisasi(): void
    {
        // Aset dipegang pegawai yang BELUM punya ruang kerja: belum ada
        // penempatan aktif sama sekali, jadi tidak ada finalisasi lama yang
        // bisa diwariskan. Penempatan hasil mutasi tetap harus final.
        $pemegang = PemegangAset::where('status', 'aktif')->get()
            ->first(fn ($p) => ! Pegawai::find($p->id_pegawai)?->id_ruangan
                && ! PenempatanAset::where('id_aset', $p->id_aset)->where('status', 'aktif')->exists()
                && ! Aset::find($p->id_aset)?->is_kendaraan);

        if (! $pemegang) {
            $this->markTestSkipped('Tidak ada aset berpemegang tanpa ruang kerja untuk diuji.');
        }

        $aset = Aset::find($pemegang->id_aset);

        $baru = Pegawai::whereNotNull('id_ruangan')
            ->whereHas('ruangan', fn ($q) => $q->where('status', 'Aktif'))
            ->where('id_pegawai', '!=', $pemegang->id_pegawai)
            ->first();

        if (! $baru) {
            $this->markTestSkipped('Tidak ada pegawai dengan ruang kerja untuk pemegang baru.');
        }

        $res = $this->mutasi($aset->id_aset, [
            'tipe' => 'pegawai',
            'id_pegawai' => $baru->id_pegawai,
        ]);

        $res->assertOk();
        $res->assertJson(['success' => true]);

        $aktif = PenempatanAset::where('id_aset', $aset->id_aset)->where('status', 'aktif')->get();
        $this->assertSame(1, $aktif->count());
        $this->assertSame(1, (int) $aktif->first()->is_finalized, 'Penempatan hasil mutasi wajib langsung berstatus 1.');
        $this->assertNotNull($aktif->first()->finalized_at);
    }

    public function test_penempatan_awal_tetap_belum_difinalisasi(): void
    {
        // Aset tanpa pemegang aktif DAN tanpa penempatan aktif.
        $aset = Aset::where('is_kendaraan', false)
            ->whereDoesntHave('pemegangSaatIni')
            ->whereDoesntHave('penempatanAktif')
            ->first();

        if (! $aset) {
            $this->markTestSkipped('Tidak ada aset yang belum pernah ditempatkan.');
        }

        $ruangan = Ruangan::where('status', 'Aktif')->first();

        if (! $ruangan) {
            $this->markTestSkipped('Tidak ada ruangan aktif.');
        }

        $res = $this->post(route('aset-barang.aset.penempatan-awal', $aset->id_aset), [
            'mode' => 'ruangan',
            'id_ruangan' => $ruangan->id_ruangan,
        ]);

        $res->assertOk();
        $res->assertJson(['success' => true]);

        $aktif = PenempatanAset::where('id_aset', $aset->id_aset)->where('status', 'aktif')->get();
        $this->assertSame(1, $aktif->count());
        $this->assertSame(0, (int) $aktif->first()->is_finalized, 'Penempatan awal wajib tetap 0 (Belum Difinalisasi).');
    }

    public function test_pasang_aset_ke_pegawai_tetap_belum_difinalisasi(): void
    {
        $pegawai = Pegawai::whereNotNull('id_ruangan')
            ->whereHas('ruangan', fn ($q) => $q->where('status', 'Aktif'))
            ->first();

        if (! $pegawai) {
            $this->markTestSkipped('Tidak ada pegawai dengan ruang kerja.');
        }

        $aset = Aset::where('is_kendaraan', false)
            ->whereDoesntHave('pemegangSaatIni')
            ->whereDoesntHave('penempatanAktif')
            ->first();

        if (! $aset) {
            $this->markTestSkipped('Tidak ada aset yang belum pernah ditempatkan.');
        }

        $res = $this->post(route('aset-barang.aset.pasang.pegawai', [$pegawai->id_pegawai, $aset->id_aset]));

        $res->assertOk();
        $res->assertJson(['success' => true]);

        $aktif = PenempatanAset::where('id_aset', $aset->id_aset)->where('status', 'aktif')->get();
        $this->assertSame(1, $aktif->count());
        $this->assertSame(0, (int) $aktif->first()->is_finalized, 'Pasang Aset wajib tetap 0 (Belum Difinalisasi).');
    }

    public function test_setelah_mutasi_aset_tetap_bisa_dimutasi_lagi_tanpa_finalisasi_ulang(): void
    {
        $sampel = $this->asetTanpaPemegang(1);

        if (! $sampel) {
            $this->markTestSkipped('Tidak ada aset tanpa pemegang dengan penempatan yang sudah difinalisasi.');
        }

        [$idAset, $asal, $tujuan] = $sampel;

        $res = $this->mutasi($idAset, [
            'tipe' => 'ruangan',
            'id_ruangan' => $tujuan->id_ruangan,
        ]);

        $res->assertOk();

        // Penempatan hasil mutasi berstatus 1, jadi gerbang mutasi tetap lolos
        // dan aset boleh dimutasi lagi tanpa finalisasi ulang.
        $aktif = PenempatanAset::where('id_aset', $idAset)->where('status', 'aktif')->first();
        $this->assertSame(1, (int) $aktif->is_finalized);

        $tujuanLagi = Ruangan::where('status', 'Aktif')
            ->where('id_ruangan', '!=', $tujuan->id_ruangan)
            ->first();

        if (! $tujuanLagi) {
            $this->markTestSkipped('Tidak ada ruangan tujuan kedua.');
        }

        $res2 = $this->mutasi($idAset, [
            'tipe' => 'ruangan',
            'id_ruangan' => $tujuanLagi->id_ruangan,
        ]);

        $res2->assertOk();
        $res2->assertJson(['success' => true]);

        $aktif2 = PenempatanAset::where('id_aset', $idAset)->where('status', 'aktif')->get();
        $this->assertSame(1, $aktif2->count(), 'Tidak boleh ada penempatan aktif ganda.');
        $this->assertSame($tujuanLagi->id_ruangan, $aktif2->first()->id_ruangan);
        $this->assertSame(1, (int) $aktif2->first()->is_finalized);
    }

    public function test_perpindahan_aset_akibat_ruang_kerja_pegawai_berubah_juga_difinalisasi(): void
    {
        // Pegawai yang memegang aset dengan penempatan aktif yang SUDAH
        // difinalisasi; asetnya ikut pindah saat ruang kerjanya berubah.
        $pegawai = null;
        $idAset = null;

        foreach (PemegangAset::where('status', 'aktif')->get() as $pemegang) {
            $calon = Aset::find($pemegang->id_aset);

            if (! $calon || $calon->is_kendaraan) {
                continue;
            }

            $penempatan = PenempatanAset::where('id_aset', $calon->id_aset)
                ->where('status', 'aktif')
                ->first();

            if (! $penempatan || (int) ($penempatan->is_finalized ?? 0) !== 1) {
                continue;
            }

            $pegawai = Pegawai::find($pemegang->id_pegawai);
            $idAset = $calon->id_aset;

            break;
        }

        if (! $pegawai || ! $idAset) {
            $this->markTestSkipped('Tidak ada pegawai memegang aset dengan penempatan yang sudah difinalisasi.');
        }

        // Ruang bersama (tanpa SKPD) boleh dipakai pegawai mana pun.
        $ruanganBaru = Ruangan::where('status', 'Aktif')
            ->whereNull('id_skpd')
            ->where('id_ruangan', '!=', $pegawai->id_ruangan)
            ->first();

        if (! $ruanganBaru) {
            $this->markTestSkipped('Tidak ada ruang bersama aktif untuk dipakai.');
        }

        $mutasiAwal = MutasiAset::count();

        $res = $this->put(route('master-data.pegawai.update', $pegawai->id_pegawai), [
            'nip' => $pegawai->nip,
            'nama_pegawai' => $pegawai->nama_pegawai,
            'id_skpd' => $pegawai->id_skpd,
            'id_ruangan' => $ruanganBaru->id_ruangan,
        ]);

        $res->assertRedirect();

        // Penempatan baru ikut ruang kerja baru dan LANGSUNG difinalisasi.
        $aktif = PenempatanAset::where('id_aset', $idAset)->where('status', 'aktif')->get();
        $this->assertSame(1, $aktif->count(), 'Tidak boleh ada penempatan aktif ganda.');
        $this->assertSame((int) $ruanganBaru->id_ruangan, (int) $aktif->first()->id_ruangan);
        $this->assertSame(1, (int) $aktif->first()->is_finalized, 'Penempatan baru hasil mutasi wajib langsung berstatus 1.');
        $this->assertNotNull($aktif->first()->finalized_at, 'Jejak waktu finalisasi wajib terisi.');

        // Perpindahan ini dicatat sebagai mutasi 'Pindah Ruangan'.
        $this->assertSame($mutasiAwal + 1, MutasiAset::count());
        $mutasi = MutasiAset::where('jenis_mutasi', 'Pindah Ruangan')
            ->where('id_user_penginput', auth()->id())
            ->latest('id_mutasi')
            ->first();
        $this->assertNotNull($mutasi);
        $this->assertSame(
            1,
            DetailMutasiAset::where('id_mutasi', $mutasi->id_mutasi)->where('id_aset', $idAset)->count()
        );
    }

    public function test_penempatan_perlu_verifikasi_tidak_bisa_dimutasi(): void
    {
        $sampel = $this->asetTanpaPemegang(null);

        if (! $sampel) {
            $this->markTestSkipped('Tidak ada aset dengan penempatan berstatus Perlu Verifikasi.');
        }

        [$idAset, $asal, $tujuan] = $sampel;

        $res = $this->mutasi($idAset, [
            'tipe' => 'ruangan',
            'id_ruangan' => $tujuan->id_ruangan,
        ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors('tipe');

        // Penempatan lama tetap NULL dan tetap menjadi penempatan aktif.
        $aktif = PenempatanAset::where('id_aset', $idAset)->where('status', 'aktif')->get();
        $this->assertSame(1, $aktif->count());
        $this->assertNull($aktif->first()->is_finalized, 'Penempatan NULL tidak boleh berubah status.');
    }
}
