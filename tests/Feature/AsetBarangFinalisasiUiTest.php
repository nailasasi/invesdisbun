<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\PenempatanAset;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Gerbang finalisasi pada tampilan tabel Aset Barang.
 *
 * Ikon Mutasi harus hilang saat penempatan aktif belum difinalisasi
 * (0 = Belum Difinalisasi, NULL = Perlu Verifikasi), dan tombol
 * "Verifikasi & Finalisasi" hanya muncul untuk penempatan NULL.
 *
 * Memakai koneksi MySQL nyata dibungkus transaksi yang di-rollback.
 */
class AsetBarangFinalisasiUiTest extends TestCase
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

    private function adminAset(): User
    {
        return User::whereHas('role', fn ($r) => $r->where('nama_role', 'Admin Aset'))->first();
    }

    /**
     * Aset dengan penempatan aktif berstatus finalisasi tertentu.
     */
    private function asetDenganFinalisasi(?int $nilai): ?Aset
    {
        $query = PenempatanAset::where('status', 'aktif');

        if (is_null($nilai)) {
            $query->whereNull('is_finalized');
        } else {
            $query->where('is_finalized', $nilai);
        }

        $id = $query->value('id_aset');

        return $id ? Aset::find($id) : null;
    }

    private function bukaHalaman(?Aset $aset): \Illuminate\Testing\TestResponse
    {
        $kunci = $aset?->nomor_kartu_barang ?: $aset?->merk ?: '___tidak_ada___';

        return $this->actingAs($this->adminAset())
            ->get(route('aset-barang.index', ['search' => $kunci]));
    }

    public function test_ikon_mutasi_disembunyikan_saat_penempatan_belum_difinalisasi(): void
    {
        $aset = $this->asetDenganFinalisasi(0);

        if (! $aset) {
            $this->markTestSkipped('Tidak ada aset dengan penempatan berstatus Belum Difinalisasi.');
        }

        $res = $this->bukaHalaman($aset);

        $res->assertOk();
        $res->assertDontSee('data-mutasi-modal="' . $aset->id_aset . '"', false);
    }

    public function test_ikon_mutasi_disembunyikan_saat_penempatan_perlu_verifikasi(): void
    {
        $aset = $this->asetDenganFinalisasi(null);

        if (! $aset) {
            $this->markTestSkipped('Tidak ada aset dengan penempatan berstatus Perlu Verifikasi.');
        }

        $res = $this->bukaHalaman($aset);

        $res->assertOk();
        $res->assertDontSee('data-mutasi-modal="' . $aset->id_aset . '"', false);
    }

    public function test_ikon_mutasi_muncul_saat_penempatan_sudah_difinalisasi(): void
    {
        $aset = $this->asetDenganFinalisasi(1);

        if (! $aset) {
            $this->markTestSkipped('Tidak ada aset dengan penempatan yang sudah difinalisasi.');
        }

        $res = $this->bukaHalaman($aset);

        $res->assertOk();
        $res->assertSee('data-mutasi-modal="' . $aset->id_aset . '"', false);
    }

    public function test_tombol_verifikasi_finalisasi_hanya_muncul_untuk_penempatan_null(): void
    {
        $asetNull = $this->asetDenganFinalisasi(null);

        if (! $asetNull) {
            $this->markTestSkipped('Tidak ada aset dengan penempatan berstatus Perlu Verifikasi.');
        }

        $res = $this->bukaHalaman($asetNull);

        $res->assertOk();
        // Tombol verifikasi untuk aset berstatus NULL.
        $res->assertSee('data-finalisasi-url="', false);
        $res->assertSee('verifikasi-finalisasi', false);
        $res->assertSee('data-finalisasi-penempatan="' . $asetNull->id_aset . '"', false);

        // Aset yang sudah difinalisasi TIDAK boleh punya tombol verifikasi.
        $asetFinal = $this->asetDenganFinalisasi(1);

        if ($asetFinal) {
            $res2 = $this->bukaHalaman($asetFinal);

            $res2->assertOk();
            $res2->assertDontSee('data-finalisasi-url="', false);
        }
    }

    public function test_modal_finalisasi_massal_memiliki_elemen_dan_script_yang_benar(): void
    {
        $res = $this->actingAs($this->adminAset())
            ->get(route('aset-barang.index'));

        $res->assertOk();
        $res->assertSee('id="finalisasi-massal-modal"', false);
        $res->assertSee('id="btn-finalisasi-massal-batal"', false);
        $res->assertSee('id="btn-finalisasi-massal-confirm"', false);
        $res->assertSee('id="btn-finalisasi-massal-label"', false);

        // Pastikan script menyembunyikan tombol batal saat selesai (tidak ada duplikasi tombol Tutup & Muat Ulang Tabel)
        $res->assertSee("btnFinalisasiMassalBatal.classList.add('hidden')", false);
        $res->assertDontSee("btnFinalisasiMassalBatal.textContent = 'Tutup & Muat Ulang Tabel'", false);
    }
}
