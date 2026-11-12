<?php

namespace Tests\Feature;

use App\Http\Controllers\AsetRuanganController;
use App\Models\Aset;
use App\Models\DetailMutasiAset;
use App\Models\MutasiAset;
use App\Models\PenempatanAset;
use App\Models\Ruangan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Mutasi pindah ruangan untuk aset melekat, dari halaman Detail Ruangan.
 *
 * Menggunakan koneksi MySQL nyata dibungkus transaksi yang di-rollback, agar
 * validasi menyentuh data real tanpa perubahan permanen.
 */
class MutasiRuanganTest extends TestCase
{
    private AsetRuanganController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $this->namaDatabase(),
        ]);

        $this->controller = app(AsetRuanganController::class);

        DB::beginTransaction();

        $this->actingAs(User::whereHas('role', fn ($r) => $r->where('nama_role', 'Admin Aset'))->first());
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

    /**
     * Sample aset melekat (tanpa pemegang aktif) dengan status finalisasi
     * penempatan tertentu. NULL = "Perlu Verifikasi" (data lama).
     *
     * @return array{0:int,1:Ruangan,2:Ruangan}|null
     */
    private function asetMelekatSampel(?int $nilaiFinalisasi = 1): ?array
    {
        $query = PenempatanAset::with('aset')
            ->where('status', 'aktif')
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

    private function mutasi(int $idAset, Ruangan $asal, Ruangan $tujuan): array
    {
        $res = $this->controller->mutasiRuangan(
            Request::create('/', 'POST', ['id_ruangan_tujuan' => $tujuan->id_ruangan, 'keterangan' => 'uji']),
            $asal,
            Aset::find($idAset)
        );

        return $res->getData(true);
    }

    public function test_mutasi_memindahkan_aset_dan_catat_riwayat_sekali(): void
    {
        $sampel = $this->asetMelekatSampel(1);

        if (! $sampel) {
            $this->markTestSkipped('Tidak ada aset melekat yang penempatannya sudah difinalisasi.');
        }

        [$idAset, $asal, $tujuan] = $sampel;

        $mutasiAwal = MutasiAset::count();
        $detailAwal = DetailMutasiAset::count();

        $hasil = $this->mutasi($idAset, $asal, $tujuan);

        $this->assertTrue($hasil['success']);

        // Satu penempatan aktif, di ruangan tujuan, is_finalized = 1.
        $aktif = PenempatanAset::where('id_aset', $idAset)->where('status', 'aktif')->get();
        $this->assertSame(1, $aktif->count(), 'Tidak boleh ada penempatan aktif ganda.');
        $this->assertSame($tujuan->id_ruangan, $aktif->first()->id_ruangan);
        $this->assertSame(1, $aktif->first()->is_finalized, 'Penempatan baru wajib langsung berstatus 1 (Sudah Difinalisasi).');

        // Penempatan lama ditutup (histori dipertahankan).
        $lama = PenempatanAset::where('id_aset', $idAset)->where('status', 'tidak aktif')
            ->where('id_ruangan', $asal->id_ruangan)
            ->count();
        $this->assertGreaterThanOrEqual(1, $lama, 'Penempatan lama harus ditutup.');

        // Riwayat mutasi tepat satu kali.
        $this->assertSame(1, MutasiAset::count() - $mutasiAwal);
        $this->assertSame(1, DetailMutasiAset::count() - $detailAwal);

        $last = MutasiAset::orderByDesc('id_mutasi')->first();
        $detail = DetailMutasiAset::where('id_mutasi', $last->id_mutasi)->first();
        $this->assertSame('Pindah Ruangan', $last->jenis_mutasi);
        $this->assertSame($asal->id_ruangan, $detail->ruangan_lama);
        $this->assertSame($tujuan->id_ruangan, $detail->ruangan_baru);
    }

    public function test_penempatan_baru_langsung_berstatus_sudah_difinalisasi(): void
    {
        $sampel = $this->asetMelekatSampel(1);

        if (! $sampel) {
            $this->markTestSkipped('Tidak ada aset melekat yang penempatannya sudah difinalisasi.');
        }

        [$idAset, $asal, $tujuan] = $sampel;

        $lama = PenempatanAset::where('id_aset', $idAset)->where('status', 'aktif')->first();
        $this->assertSame(1, (int) $lama->is_finalized, 'Sample harus penempatan aktif yang sudah difinalisasi.');

        $this->mutasi($idAset, $asal, $tujuan);

        $baru = PenempatanAset::where('id_aset', $idAset)->where('status', 'aktif')->first();

        $this->assertSame(1, (int) $baru->is_finalized, 'Penempatan baru wajib langsung berstatus 1 (Sudah Difinalisasi).');
        $this->assertNotSame($tujuan->id_ruangan, $lama->id_ruangan);
        $this->assertNotNull($baru->finalized_at, 'Jejak waktu finalisasi harus terisi.');
        $this->assertNotNull($baru->finalized_by, 'Jejak pelaku finalisasi harus terisi.');
    }

    public function test_pasang_aset_tetap_berstatus_belum_difinalisasi(): void
    {
        // Alur Pasang Aset TIDAK ikut berubah: penempatannya tetap
        // `is_finalized = 0` dan wajib difinalisasi Admin Aset terpisah.
        $aset = Aset::with('barang')
            ->barang()
            ->where('status_aset', 'aktif')
            ->whereDoesntHave('pemegangSaatIni')
            ->whereDoesntHave('penempatanAktif')
            ->first();

        if (! $aset) {
            $this->markTestSkipped('Tidak ada aset yang bisa dipasang untuk diuji.');
        }

        $ruangan = Ruangan::where('status', 'Aktif')->first();

        if (! $ruangan) {
            $this->markTestSkipped('Tidak ada ruangan aktif untuk diuji.');
        }

        $res = $this->controller->pasangAset($ruangan, $aset);

        $this->assertSame(200, $res->getStatusCode());

        $penempatan = PenempatanAset::where('id_aset', $aset->id_aset)->where('status', 'aktif')->first();

        $this->assertNotNull($penempatan, 'Pasang Aset harus membuat penempatan aktif.');
        $this->assertSame(0, (int) $penempatan->is_finalized, 'Pasang Aset tetap berstatus 0 (Belum Difinalisasi).');
    }

    public function test_mutasi_ditolak_bila_penempatan_belum_difinalisasi(): void
    {
        $sampel = $this->asetMelekatSampel(0);

        if (! $sampel) {
            $this->markTestSkipped('Tidak ada aset melekat berstatus Belum Difinalisasi.');
        }

        [$idAset, $asal, $tujuan] = $sampel;

        $sebelum = MutasiAset::count();

        try {
            $this->mutasi($idAset, $asal, $tujuan);
            $this->fail('Mutasi seharusnya ditolak karena penempatan belum difinalisasi.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('belum difinalisasi', collect($e->errors())->flatten()->first());
        }

        $this->assertSame($sebelum, MutasiAset::count(), 'Tidak boleh ada mutasi tercatat.');
    }

    public function test_mutasi_ditolak_bila_penempatan_perlu_verifikasi(): void
    {
        $sampel = $this->asetMelekatSampel(null);

        if (! $sampel) {
            $this->markTestSkipped('Tidak ada aset melekat berstatus Perlu Verifikasi (is_finalized NULL).');
        }

        [$idAset, $asal, $tujuan] = $sampel;

        $sebelum = MutasiAset::count();

        try {
            $this->mutasi($idAset, $asal, $tujuan);
            $this->fail('Mutasi seharusnya ditolak karena penempatan perlu verifikasi.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('belum difinalisasi', collect($e->errors())->flatten()->first());
        }

        $this->assertSame($sebelum, MutasiAset::count(), 'Tidak boleh ada mutasi tercatat.');
    }

    public function test_ruangan_nonaktif_ditolak(): void
    {
        $sampel = $this->asetMelekatSampel(1);

        if (! $sampel) {
            $this->markTestSkipped('Tidak ada aset melekat yang penempatannya sudah difinalisasi.');
        }

        [$idAset, $asal] = $sampel;

        // Simulasikan ruangan tujuan nonaktif (dalam transaksi).
        $nonaktif = Ruangan::where('id_ruangan', '!=', $asal->id_ruangan)->first();
        $nonaktif->update(['status' => 'Nonaktif']);

        $sebelum = MutasiAset::count();

        try {
            $this->mutasi($idAset, $asal, $nonaktif);
            $this->fail('Ruangan tujuan nonaktif harus ditolak.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        } finally {
            $this->assertSame($sebelum, MutasiAset::count(), 'Tidak boleh ada mutasi saat tujuan nonaktif.');
        }
    }

    public function test_aset_yang_tidak_berada_di_ruangan_asal_ditolak(): void
    {
        $sampel = $this->asetMelekatSampel(1);

        if (! $sampel) {
            $this->markTestSkipped('Tidak ada aset melekat yang penempatannya sudah difinalisasi.');
        }

        [$idAset, $asal, $tujuan] = $sampel;

        $ruanganLain = Ruangan::where('status', 'Aktif')
            ->where('id_ruangan', '!=', $asal->id_ruangan)
            ->where('id_ruangan', '!=', $tujuan->id_ruangan)
            ->first();

        if (! $ruanganLain) {
            $this->markTestSkipped('Tidak ada ruangan aktif lain untuk pengujian.');
        }

        $sebelum = MutasiAset::count();

        try {
            $this->mutasi($idAset, $ruanganLain, $tujuan);
            $this->fail('Aset di luar ruangan asal harus ditolak.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertSame($sebelum, MutasiAset::count());
    }

    public function test_aset_berpemegang_ditolak(): void
    {
        $berpemegang = Aset::whereHas('pemegangSaatIni')->whereHas('penempatanAktif')->first();

        if (! $berpemegang) {
            $this->markTestSkipped('Tidak ada aset berpemegang untuk diuji.');
        }

        $asal = Ruangan::find($berpemegang->penempatanAktif->id_ruangan);
        $tujuan = Ruangan::where('status', 'Aktif')->where('id_ruangan', '!=', $asal->id_ruangan)->first();

        $sebelum = MutasiAset::count();

        try {
            $this->mutasi($berpemegang->id_aset, $asal, $tujuan);
            $this->fail('Aset berpemegang seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('dipegang pegawai', collect($e->errors())->flatten()->first());
        }

        $this->assertSame($sebelum, MutasiAset::count());
    }

    // ------------------------------------------------------------------
    // Level HTTP (meniru klik tombol "Pindahkan" di modal)
    // ------------------------------------------------------------------

    /**
     * POST ke route yang sama dengan yang dipanggil modal Mutasi Ruangan.
     */
    private function submitHttp(int $idAset, Ruangan $asal, Ruangan $tujuan): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(
            route('aset-ruangan.aset.mutasi-ruangan', [
                'ruangan' => $asal->id_ruangan,
                'aset' => $idAset,
            ]),
            [
                'id_ruangan_tujuan' => $tujuan->id_ruangan,
                'keterangan' => 'uji http',
            ]
        );
    }

    public function test_submit_http_mutasi_ruangan_berhasil(): void
    {
        $sampel = $this->asetMelekatSampel(1);

        if (! $sampel) {
            $this->markTestSkipped('Tidak ada aset melekat yang penempatannya sudah difinalisasi.');
        }

        [$idAset, $asal, $tujuan] = $sampel;

        $mutasiAwal = MutasiAset::count();
        $detailAwal = DetailMutasiAset::count();

        $res = $this->submitHttp($idAset, $asal, $tujuan);

        $res->assertOk();
        $res->assertJson(['success' => true]);

        // Penempatan baru: satu baris aktif, di ruangan tujuan, is_finalized = 1.
        $aktif = PenempatanAset::where('id_aset', $idAset)->where('status', 'aktif')->get();
        $this->assertCount(1, $aktif);
        $this->assertSame($tujuan->id_ruangan, $aktif->first()->id_ruangan);
        $this->assertSame(1, (int) $aktif->first()->is_finalized, 'Penempatan baru wajib langsung berstatus 1.');

        // Riwayat mutasi tepat satu kali.
        $this->assertSame(1, MutasiAset::count() - $mutasiAwal);
        $this->assertSame(1, DetailMutasiAset::count() - $detailAwal);

        // Penempatan lama ditutup.
        $lama = PenempatanAset::where('id_aset', $idAset)
            ->where('status', 'tidak aktif')
            ->where('id_ruangan', $asal->id_ruangan)
            ->count();
        $this->assertGreaterThanOrEqual(1, $lama);
    }

    /**
     * Setelah mutasi, penempatan baru sudah final => aset tetap boleh
     * dimutasi lagi (ikon Mutasi tetap muncul) dan tiap pemanggilan hanya
     * menambah SATU baris riwayat (tidak ada duplikasi).
     */
    public function test_setelah_mutasi_aset_masih_bisa_dimutasi_lagi_tanpa_duplikasi_riwayat(): void
    {
        $sampel = $this->asetMelekatSampel(1);

        if (! $sampel) {
            $this->markTestSkipped('Tidak ada aset melekat yang penempatannya sudah difinalisasi.');
        }

        [$idAset, $asal, $tujuan] = $sampel;

        $tujuanKedua = Ruangan::where('status', 'Aktif')
            ->whereNotIn('id_ruangan', [$asal->id_ruangan, $tujuan->id_ruangan])
            ->first();

        if (! $tujuanKedua && $asal->status !== 'Aktif') {
            $this->markTestSkipped('Tidak ada ruangan tujuan kedua yang aktif.');
        }

        $mutasiAwal = MutasiAset::count();
        $detailAwal = DetailMutasiAset::count();

        // Mutasi pertama.
        $this->mutasi($idAset, $asal, $tujuan);

        // Mutasi kedua: penempatan baru sudah final, jadi tetap sah.
        $hasil = $this->mutasi($idAset, $tujuan, $tujuanKedua ?: $asal);

        $this->assertTrue($hasil['success']);

        // Tepat satu mutasi per pemanggilan — tidak ada duplikasi riwayat.
        $this->assertSame(2, MutasiAset::count() - $mutasiAwal);
        $this->assertSame(2, DetailMutasiAset::count() - $detailAwal);

        // Tetap satu penempatan aktif, di ruangan terakhir, berstatus 1.
        $aktif = PenempatanAset::where('id_aset', $idAset)->where('status', 'aktif')->get();
        $this->assertCount(1, $aktif);
        $this->assertSame(($tujuanKedua ?: $asal)->id_ruangan, $aktif->first()->id_ruangan);
        $this->assertSame(1, (int) $aktif->first()->is_finalized);
    }

    public function test_submit_http_ditolak_bila_penempatan_belum_difinalisasi(): void
    {
        $sampel = $this->asetMelekatSampel(0);

        if (! $sampel) {
            $this->markTestSkipped('Tidak ada aset melekat berstatus Belum Difinalisasi.');
        }

        [$idAset, $asal, $tujuan] = $sampel;

        $mutasiAwal = MutasiAset::count();

        $res = $this->submitHttp($idAset, $asal, $tujuan);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors('id_aset');
        $this->assertStringContainsString(
            'belum difinalisasi',
            collect($res->json('errors.id_aset'))->first()
        );

        $this->assertSame($mutasiAwal, MutasiAset::count(), 'Tidak boleh ada mutasi tercatat.');
    }

    public function test_submit_http_ditolak_bila_penempatan_perlu_verifikasi(): void
    {
        $sampel = $this->asetMelekatSampel(null);

        if (! $sampel) {
            $this->markTestSkipped('Tidak ada aset melekat berstatus Perlu Verifikasi (is_finalized NULL).');
        }

        [$idAset, $asal, $tujuan] = $sampel;

        $mutasiAwal = MutasiAset::count();

        $res = $this->submitHttp($idAset, $asal, $tujuan);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors('id_aset');
        $this->assertSame($mutasiAwal, MutasiAset::count(), 'Tidak boleh ada mutasi tercatat.');
    }

    public function test_submit_http_ditolak_bila_ruangan_tujuan_nonaktif(): void
    {
        $sampel = $this->asetMelekatSampel(1);

        if (! $sampel) {
            $this->markTestSkipped('Tidak ada aset melekat yang penempatannya sudah difinalisasi.');
        }

        [$idAset, $asal] = $sampel;

        $nonaktif = Ruangan::where('id_ruangan', '!=', $asal->id_ruangan)->first();
        $nonaktif->update(['status' => 'Nonaktif']);

        $mutasiAwal = MutasiAset::count();

        $res = $this->submitHttp($idAset, $asal, $nonaktif);

        $res->assertStatus(422);
        $this->assertSame($mutasiAwal, MutasiAset::count(), 'Tidak boleh ada mutasi tercatat.');
    }

    public function test_submit_http_ditolak_bila_aset_berpemegang(): void
    {
        $berpemegang = Aset::whereHas('pemegangSaatIni')->whereHas('penempatanAktif')->first();

        if (! $berpemegang) {
            $this->markTestSkipped('Tidak ada aset berpemegang untuk diuji.');
        }

        $asal = Ruangan::find($berpemegang->penempatanAktif->id_ruangan);
        $tujuan = Ruangan::where('status', 'Aktif')->where('id_ruangan', '!=', $asal->id_ruangan)->first();

        $mutasiAwal = MutasiAset::count();

        $res = $this->submitHttp($berpemegang->id_aset, $asal, $tujuan);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors('id_aset');
        $this->assertStringContainsString(
            'dipegang pegawai',
            collect($res->json('errors.id_aset'))->first()
        );

        $this->assertSame($mutasiAwal, MutasiAset::count(), 'Tidak boleh ada mutasi tercatat.');
    }

    public function test_submit_http_ditolak_bila_user_bukan_admin_aset(): void
    {
        $sampel = $this->asetMelekatSampel(1);

        if (! $sampel) {
            $this->markTestSkipped('Tidak ada aset melekat yang penempatannya sudah difinalisasi.');
        }

        [$idAset, $asal, $tujuan] = $sampel;

        $userLain = User::whereHas('role', fn ($r) => $r->where('nama_role', '!=', 'Admin Aset'))->first();

        if (! $userLain) {
            $this->markTestSkipped('Tidak ada user non-Admin-Aset untuk diuji.');
        }

        $mutasiAwal = MutasiAset::count();

        $this->actingAs($userLain);

        $res = $this->submitHttp($idAset, $asal, $tujuan);

        $res->assertStatus(403);
        $this->assertSame($mutasiAwal, MutasiAset::count(), 'Tidak boleh ada mutasi tercatat.');
    }
}
