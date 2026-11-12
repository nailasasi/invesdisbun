<?php

namespace Tests\Feature;

use App\Models\InventarisRuangan;
use App\Models\Ruangan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Modal Mutasi Ruangan untuk Inventaris Lokal Ruangan.
 *
 * Disamacakan dengan modal Mutasi Ruangan pada tabel Barang Melekat:
 * ikon + judul, deskripsi, panel Aset/KIB/Ruangan Asal, dropdown Ruangan
 * Tujuan, catatan ruangan aktif, textarea Keterangan, dan tombol
 * Batal/Pindahkan. Data yang ditampilkan harus berasal dari record nyata.
 *
 * Memakai koneksi MySQL nyata dibungkus transaksi yang di-rollback.
 */
class InventarisLokalMutasiUiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $this->namaDatabase(),
        ]);

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
     * Ruangan yang punya minimal satu inventaris lokal.
     */
    private function ruanganDenganInventaris(): ?Ruangan
    {
        $id = InventarisRuangan::value('id_ruangan');

        return $id ? Ruangan::find($id) : null;
    }

    private function inventaris(Ruangan $ruangan): ?InventarisRuangan
    {
        return InventarisRuangan::where('id_ruangan', $ruangan->id_ruangan)->first();
    }

    /**
     * Ruangan tujuan aktif (bukan ruangan asal).
     */
    private function ruanganTujuan(Ruangan $asal): ?Ruangan
    {
        return Ruangan::where('status', 'Aktif')
            ->where('id_ruangan', '!=', $asal->id_ruangan)
            ->first();
    }

    private function bukaHalaman(Ruangan $ruangan): \Illuminate\Testing\TestResponse
    {
        return $this->get(route('aset-ruangan.show', ['ruangan' => $ruangan->id_ruangan]));
    }

    // ------------------------------------------------------------------
    // Tampilan modal
    // ------------------------------------------------------------------

    public function test_modal_mutasi_inventaris_lokal_memiliki_elemen_paritas(): void
    {
        $ruangan = $this->ruanganDenganInventaris();

        if (! $ruangan) {
            $this->markTestSkipped('Tidak ada inventaris lokal untuk diuji.');
        }

        $res = $this->bukaHalaman($ruangan);

        $res->assertOk();

        // Judul, ikon, dan deskripsi (pola modal Barang Melekat).
        $res->assertSee('id="inv-move-modal"', false);
        $res->assertSee('Mutasi Ruangan', false);
        $res->assertSee('bg-indigo-100', false);
        $res->assertSee('Pindahkan inventaris lokal ini ke ruangan lain', false);

        // Panel informasi Aset / KIB / Ruangan Asal.
        $res->assertSee('id="inv-move-aset"', false);
        $res->assertSee('id="inv-move-kib"', false);
        $res->assertSee('Ruangan Asal:', false);
        $res->assertSee(e($ruangan->nama_ruangan), false);

        // Dropdown tujuan + catatan ruangan aktif.
        $res->assertSee('id="inv-tujuan"', false);
        $res->assertSee('Hanya ruangan aktif yang tercantum.', false);

        // Textarea keterangan.
        $res->assertSee('id="inv-move-keterangan"', false);
        $res->assertSee('name="keterangan"', false);

        // Tombol Batal + Pindahkan.
        $res->assertSee('id="btn-inv-move-submit"', false);
        $res->assertSee('Batal', false);
    }

    public function test_modal_inventaris_lokal_menampilkan_aset_kib_dan_ruangan_asal_dari_data_aktual(): void
    {
        $ruangan = $this->ruanganDenganInventaris();

        if (! $ruangan) {
            $this->markTestSkipped('Tidak ada inventaris lokal untuk diuji.');
        }

        $item = $this->inventaris($ruangan);

        if (! $item) {
            $this->markTestSkipped('Inventaris lokal tidak ditemukan.');
        }

        $res = $this->bukaHalaman($ruangan);

        $res->assertOk();

        // Kode (KIB) & keterangan ikut dibawa ke modal lewat data attribute.
        $res->assertSee('data-inv-kode="' . e($item->kode_inventaris) . '"', false);
        $res->assertSee('data-inv-keterangan="' . e((string) $item->keterangan) . '"', false);
        $res->assertSee('data-inv-nama="' . e($item->nama_barang) . '"', false);
    }

    public function test_dropdown_tujuan_hanya_memuat_ruangan_aktif_dan_bukan_ruangan_asal(): void
    {
        $ruangan = $this->ruanganDenganInventaris();

        if (! $ruangan) {
            $this->markTestSkipped('Tidak ada inventaris lokal untuk diuji.');
        }

        $res = $this->bukaHalaman($ruangan);

        $res->assertOk();

        // Ruangan asal tidak boleh muncul sebagai pilihan tujuan.
        $res->assertDontSee('value="' . $ruangan->id_ruangan . '"', false);

        // Ruangan nonaktif tidak boleh muncul sebagai pilihan tujuan.
        $nonaktif = Ruangan::where('status', '!=', 'Aktif')
            ->where('id_ruangan', '!=', $ruangan->id_ruangan)
            ->first();

        if ($nonaktif) {
            $res->assertDontSee('value="' . $nonaktif->id_ruangan . '"', false);
        }

        // Setidaknya satu ruangan aktif lain harus tercantum.
        $tujuan = $this->ruanganTujuan($ruangan);

        if ($tujuan) {
            $res->assertSee('value="' . $tujuan->id_ruangan . '"', false);
        }
    }

    public function test_modal_barang_melekat_tetap_utuh_setelah_perubahan(): void    {
        $ruangan = $this->ruanganDenganInventaris();

        if (! $ruangan) {
            $this->markTestSkipped('Tidak ada inventaris lokal untuk diuji.');
        }

        $res = $this->bukaHalaman($ruangan);

        $res->assertOk();

        foreach ([
            'id="mutasi-ruangan-modal"',
            'id="mutasi-ruangan-form"',
            'id="mutasi-ruangan-aset"',
            'id="mutasi-ruangan-kib"',
            'id="mutasi-ruangan-tujuan"',
            'id="mutasi-ruangan-keterangan"',
            'id="btn-mutasi-ruangan-submit"',
        ] as $penanda) {
            $res->assertSee($penanda, false);
        }
    }

    /**
     * Guard statis: handler JS harus mengisi panel Aset/KIB dan textarea
     * Keterangan dari data record (atribut data-* pada tombol tabel).
     */
    public function test_script_modal_inventaris_mengisi_panel_dari_data_tabel(): void
    {
        $blade = file_get_contents(resource_path('views/aset-ruangan/show.blade.php'));

        $this->assertIsString($blade);

        foreach ([
            "document.getElementById('inv-move-aset')",
            "document.getElementById('inv-move-kib')",
            "document.getElementById('inv-move-keterangan')",
            'btn.dataset.invKode',
            'btn.dataset.invKeterangan',
            'btn.dataset.invNama',
        ] as $potongan) {
            $this->assertStringContainsString($potongan, $blade, "Bagian script wajib memuat: {$potongan}");
        }
    }

    // ------------------------------------------------------------------
    // Perilaku endpoint mutasi inventaris lokal
    // ------------------------------------------------------------------

    public function test_mutasi_inventaris_lokal_memindahkan_dan_menyimpan_keterangan(): void
    {
        $ruangan = $this->ruanganDenganInventaris();

        if (! $ruangan) {
            $this->markTestSkipped('Tidak ada inventaris lokal untuk diuji.');
        }

        $item = $this->inventaris($ruangan);
        $tujuan = $this->ruanganTujuan($ruangan);

        if (! $item || ! $tujuan) {
            $this->markTestSkipped('Ruangan tujuan aktif tidak tersedia.');
        }

        $res = $this->post(route('aset-ruangan.inventaris.move', [
            'ruangan' => $ruangan->id_ruangan,
            'inventaris' => $item->id_inventaris,
        ]), [
            'id_ruangan_tujuan' => $tujuan->id_ruangan,
            'keterangan' => 'penataan ulang',
        ]);

        $res->assertRedirect();

        $item->refresh();

        $this->assertSame($tujuan->id_ruangan, $item->id_ruangan, 'Barang harus pindah ke ruangan tujuan.');
        $this->assertSame('penataan ulang', $item->keterangan, 'Keterangan modal harus tersimpan.');
        $this->assertNotNull($item->kode_inventaris, 'Kode inventaris harus tetap ada.');
    }

    public function test_mutasi_tanpa_keterangan_tidak_mengubah_catatan_lama(): void
    {
        $ruangan = $this->ruanganDenganInventaris();

        if (! $ruangan) {
            $this->markTestSkipped('Tidak ada inventaris lokal untuk diuji.');
        }

        $item = $this->inventaris($ruangan);
        $tujuan = $this->ruanganTujuan($ruangan);

        if (! $item || ! $tujuan) {
            $this->markTestSkipped('Ruangan tujuan aktif tidak tersedia.');
        }

        $item->update(['keterangan' => 'catatan lama']);

        $res = $this->post(route('aset-ruangan.inventaris.move', [
            'ruangan' => $ruangan->id_ruangan,
            'inventaris' => $item->id_inventaris,
        ]), [
            'id_ruangan_tujuan' => $tujuan->id_ruangan,
        ]);

        $res->assertRedirect();

        $item->refresh();

        $this->assertSame($tujuan->id_ruangan, $item->id_ruangan);
        $this->assertSame('catatan lama', $item->keterangan, 'Catatan lama tidak boleh berubah tanpa isian baru.');
    }

    public function test_mutasi_ke_ruangan_nonaktif_ditolak(): void
    {
        $ruangan = $this->ruanganDenganInventaris();

        if (! $ruangan) {
            $this->markTestSkipped('Tidak ada inventaris lokal untuk diuji.');
        }

        $item = $this->inventaris($ruangan);

        if (! $item) {
            $this->markTestSkipped('Inventaris lokal tidak ditemukan.');
        }

        $nonaktif = Ruangan::where('id_ruangan', '!=', $ruangan->id_ruangan)
            ->where('status', '!=', 'Aktif')
            ->first();

        if (! $nonaktif) {
            // Ruangan nonaktif dibuat di dalam transaksi test (di-rollback).
            $nonaktif = Ruangan::create([
                'nama_ruangan' => 'Ruang Uji Mutasi Nonaktif',
                'status' => 'Nonaktif',
            ]);
        }

        $idRuanganAsal = $item->id_ruangan;

        $res = $this->post(route('aset-ruangan.inventaris.move', [
            'ruangan' => $ruangan->id_ruangan,
            'inventaris' => $item->id_inventaris,
        ]), [
            'id_ruangan_tujuan' => $nonaktif->id_ruangan,
        ]);

        $res->assertStatus(422);

        $item->refresh();

        $this->assertSame($idRuanganAsal, $item->id_ruangan, 'Barang tidak boleh pindah ke ruangan nonaktif.');
    }
}
