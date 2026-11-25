<?php

namespace Tests\Feature;

use App\Http\Controllers\AsetBarangController;
use App\Models\Aset;
use App\Models\PenempatanAset;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Verifikasi & Finalisasi untuk penempatan LAMA (is_finalized NULL /
 * "Perlu Verifikasi").
 *
 * Jalur ini eksplisit per aset: aset legacy diverifikasi manual oleh admin
 * lalu statusnya menjadi "Sudah Difinalisasi", sehingga tidak terkunci
 * permanen oleh gerbang finalisasi pada mutasi.
 *
 * Memakai koneksi MySQL nyata dibungkus transaksi yang di-rollback.
 */
class VerifikasiFinalisasiTest extends TestCase
{
    private AsetBarangController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $this->namaDatabase(),
        ]);

        $this->controller = app(AsetBarangController::class);

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
        return $this->namaDatabasePengujian();
    }

    private function penempatanPerluVerifikasi(): ?PenempatanAset
    {
        return PenempatanAset::where('status', 'aktif')->whereNull('is_finalized')->first();
    }

    /**
     * @return array<string,mixed>
     */
    private function verifikasi(int $idAset): array
    {
        $res = $this->controller->verifikasiFinalisasiPenempatan(
            Request::create('/', 'POST'),
            Aset::find($idAset)
        );

        return $res->getData(true);
    }

    public function test_verifikasi_menandai_penempatan_lama_sebagai_difinalisasi(): void
    {
        $penempatan = $this->penempatanPerluVerifikasi();

        if (! $penempatan) {
            $this->markTestSkipped('Tidak ada penempatan aktif berstatus Perlu Verifikasi (is_finalized NULL).');
        }

        $idAset = $penempatan->id_aset;

        $res = $this->verifikasi($idAset);

        $this->assertTrue($res['success']);
        $this->assertSame(1, $res['is_finalized']);

        $setelah = PenempatanAset::where('id_aset', $idAset)->where('status', 'aktif')->first();

        $this->assertSame(1, (int) $setelah->is_finalized);
        $this->assertNotNull($setelah->finalized_at, 'Wajib mencatat waktu finalisasi.');
        $this->assertNotNull($setelah->finalized_by, 'Wajib mencatat pelaku finalisasi.');
    }

    public function test_verifikasi_ditolak_bila_penempatan_sudah_difinalisasi(): void
    {
        $penempatan = PenempatanAset::where('status', 'aktif')->where('is_finalized', 1)->first();

        if (! $penempatan) {
            $this->markTestSkipped('Tidak ada penempatan aktif yang sudah difinalisasi.');
        }

        $this->expectException(ValidationException::class);

        $this->verifikasi($penempatan->id_aset);
    }

    public function test_verifikasi_ditolak_bila_penempatan_berstatus_belum_difinalisasi(): void
    {
        $penempatan = PenempatanAset::where('status', 'aktif')->where('is_finalized', 0)->first();

        if (! $penempatan) {
            $this->markTestSkipped('Tidak ada penempatan aktif berstatus Belum Difinalisasi.');
        }

        $this->expectException(ValidationException::class);

        $this->verifikasi($penempatan->id_aset);
    }
}
