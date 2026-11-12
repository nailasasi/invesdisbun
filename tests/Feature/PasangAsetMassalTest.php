<?php

namespace Tests\Feature;

use App\Http\Controllers\AsetRuanganController;
use App\Models\Aset;
use App\Models\MasterBarang;
use App\Models\MutasiAset;
use App\Models\DetailMutasiAset;
use App\Models\PenempatanAset;
use App\Models\Ruangan;
use App\Models\User;
use App\Services\AsetPenempatan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Fitur Pasang Aset massal (password_asli: server memilih aset nyata per
 * jenis + jumlah, urut KIB, satu transaksi, `is_finalized = 0`, tanpa mutasi).
 *
 * Menggunakan koneksi MySQL nyata (bukan sqlite in-memory) dengan transaksi
 * yang selalu di-rollback di `tearDown` agar tidak ada perubahan permanen.
 */
class PasangAsetMassalTest extends TestCase
{
    private AsetRuanganController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml menimpa DB_DATABASE menjadi :memory: (untuk sqlite).
        // Paksa koneksi default kembali ke MySQL dengan nama database nyata,
        // lalu bungkus tiap uji dalam transaksi yang di-rollback di tearDown.
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $this->namaDatabase(),
        ]);

        $this->controller = app(AsetRuanganController::class);

        DB::beginTransaction();

        $this->actingAs(User::whereHas('role', fn ($r) => $r->where('nama_role', 'Admin Aset'))->first());
    }

    private function namaDatabase(): string
    {
        $baris = file(base_path('.env'));

        foreach ($baris as $line) {
            if (str_starts_with(trim($line), 'DB_DATABASE=')) {
                $nilai = trim(explode('=', $line, 2)[1]);

                return $nilai === '' ? 'invesdisbun' : $nilai;
            }
        }

        return 'invesdisbun';
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function ruanganAktif(): Ruangan
    {
        return Ruangan::where('status', 'Aktif')->first();
    }

    private function jenisTerbanyak(): int
    {
        return Aset::with('barang')
            ->barang()
            ->where('status_aset', 'aktif')
            ->whereDoesntHave('pemegangSaatIni')
            ->whereDoesntHave('penempatanAktif')
            ->get()
            ->groupBy('id_barang')
            ->sortByDesc(fn ($items) => $items->count())
            ->keys()
            ->first();
    }

    private function pasang(int $idBarang, int $jumlah): array
    {
        $res = $this->controller->pasangAsetMassal(
            Request::create('/', 'POST', ['id_barang' => $idBarang, 'jumlah' => $jumlah]),
            $this->ruanganAktif()
        );

        return $res->getData(true);
    }

    /** Penempatan yang dibuat di dalam transaksi uji ini (id > watermark). */
    private function penempatanBaru(int $watermark)
    {
        return PenempatanAset::where('id_penempatan', '>', $watermark)
            ->where('status', 'aktif')
            ->with('aset')
            ->get();
    }

    public function test_memasang_satu_aset(): void
    {
        $idBarang = $this->jenisTerbanyak();
        $hasil = $this->pasang($idBarang, 1);

        $this->assertTrue($hasil['success']);
        $this->assertStringContainsString('1 aset berhasil dipasang', $hasil['message']);
    }

    public function test_penempatan_baru_berstatus_belum_difinalisasi(): void
    {
        $idBarang = $this->jenisTerbanyak();
        $watermark = PenempatanAset::max('id_penempatan');

        $this->pasang($idBarang, 3);

        $baru = $this->penempatanBaru($watermark);
        $this->assertGreaterThanOrEqual(3, $baru->count());

        foreach ($baru as $p) {
            $this->assertSame(0, $p->is_finalized, 'Penempatan baru wajib is_finalized = 0.');
        }
    }

    public function test_memilih_kib_terendah_terlebih_dahulu(): void
    {
        $idBarang = $this->jenisTerbanyak();
        $watermark = PenempatanAset::max('id_penempatan');

        $this->pasang($idBarang, 5);

        $baru = $this->penempatanBaru($watermark)
            ->filter(fn ($p) => $p->aset && (int) $p->aset->id_barang === (int) $idBarang);

        $keys = $baru->map(fn ($p) => AsetPenempatan::kunciKib($p->aset->nomor_kartu_barang))->values()->all();
        $sorted = $keys;
        usort($sorted, fn ($a, $b) => $a <=> $b);

        $this->assertSame($sorted, $keys, 'KIB terpilih harus terurut terendah → tertinggi.');
    }

    public function test_jumlah_melebihi_ketersediaan_ditolak_tanpa_menyimpan(): void
    {
        $idBarang = $this->jenisTerbanyak();
        $tersedia = Aset::with('barang')->barang()->where('status_aset', 'aktif')
            ->where('id_barang', $idBarang)
            ->whereDoesntHave('pemegangSaatIni')->whereDoesntHave('penempatanAktif')->count();

        $sebelum = PenempatanAset::count();

        try {
            $this->pasang($idBarang, $tersedia + 1);
            $this->fail('Harusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('tidak menyimpan sebagian', collect($e->errors())->flatten()->first());
        }

        $this->assertSame($sebelum, PenempatanAset::count(), 'Tidak boleh menyimpan sebagian.');
    }

    public function test_tidak_memilih_aset_berpemegang_atau_sudah_berpenempatan(): void
    {
        $idBarang = $this->jenisTerbanyak();
        $watermark = PenempatanAset::max('id_penempatan');

        $this->pasang($idBarang, 4);

        $baru = $this->penempatanBaru($watermark);
        $ids = $baru->pluck('id_aset')->unique();

        // Tanpa duplikat penempatan aktif untuk aset yang sama.
        $this->assertSame($ids->count(), $baru->count(), 'Tidak boleh ada duplikasi.');

        // Tanpa pemegang aktif.
        foreach ($baru as $p) {
            $this->assertFalse($p->aset->pemegangSaatIni()->exists(), 'Aset berpemegang tidak boleh ikut dipasang.');
        }
    }

    public function test_tidak_menciptakan_riwayat_mutasi(): void
    {
        $mutasi = MutasiAset::count();
        $detail = DetailMutasiAset::count();

        $this->pasang($this->jenisTerbanyak(), 1);

        $this->assertSame($mutasi, MutasiAset::count());
        $this->assertSame($detail, DetailMutasiAset::count());
    }
}