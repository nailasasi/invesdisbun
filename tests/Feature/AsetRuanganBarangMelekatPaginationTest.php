<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\Lokasi;
use App\Models\MasterBarang;
use App\Models\PenempatanAset;
use App\Models\Ruangan;
use App\Models\Skpd;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AsetRuanganBarangMelekatPaginationTest extends TestCase
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
        return User::whereHas('role', fn ($r) => $r->where('nama_role', 'Admin Aset'))->firstOrFail();
    }

    private function buatRuanganTest(string $nama = 'Ruang Test Pagination'): Ruangan
    {
        $skpd = Skpd::first();
        $lokasi = Lokasi::first();

        return Ruangan::create([
            'nama_ruangan' => $nama,
            'lantai' => '1',
            'id_skpd' => $skpd?->id_skpd,
            'id_lokasi' => $lokasi?->id_lokasi,
            'status' => 'Aktif',
        ]);
    }

    private function buatAsetMelekat(Ruangan $ruangan, int $jumlah): array
    {
        $skpd = Skpd::first();
        $lokasi = Lokasi::first();
        $barang = MasterBarang::first();

        $asetList = [];

        for ($i = 1; $i <= $jumlah; $i++) {
            $nomorKartu = 'PAGINATE-' . uniqid() . '-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);

            $aset = Aset::create([
                'id_barang' => $barang?->id_barang,
                'id_skpd' => $skpd?->id_skpd,
                'id_lokasi' => $lokasi?->id_lokasi,
                'nama_barang' => 'Barang Pagination ' . $i,
                'nomor_kartu_barang' => $nomorKartu,
                'merk' => 'Merk Test ' . $i,
                'kondisi' => 'Baik',
                'status_aset' => 'aktif',
                'is_kendaraan' => false,
                'nilai_perolehan' => 1000000 + ($i * 50000),
            ]);

            PenempatanAset::create([
                'id_aset' => $aset->id_aset,
                'id_ruangan' => $ruangan->id_ruangan,
                'tanggal_mulai' => now()->toDateString(),
                'status' => 'aktif',
                'is_finalized' => 1,
            ]);

            $asetList[] = $aset;
        }

        return $asetList;
    }

    /**
     * 1. Uji ruangan dengan lebih dari 10 barang (15 barang):
     *    - Halaman pertama menampilkan 10 barang.
     *    - Card ringkasan menampilkan total 15 aset.
     *    - Nomor urut 1 sampai 10.
     *    - Tombol pagination aktif.
     */
    public function test_ruangan_lebih_dari_10_barang_menampilkan_10_barang_di_halaman_pertama(): void
    {
        $user = $this->adminAset();
        $ruangan = $this->buatRuanganTest('Ruang 15 Barang');
        $this->buatAsetMelekat($ruangan, 15);

        $response = $this->actingAs($user)->get(route('aset-ruangan.show', $ruangan->id_ruangan));

        $response->assertOk();

        // LengthAwarePaginator diteruskan ke view
        $asetMelekat = $response->viewData('asetMelekat');
        $this->assertInstanceOf(LengthAwarePaginator::class, $asetMelekat);
        $this->assertSame(15, $asetMelekat->total());
        $this->assertSame(10, $asetMelekat->perPage());
        $this->assertSame(10, $asetMelekat->count());
        $this->assertSame(1, $asetMelekat->currentPage());
        $this->assertTrue($asetMelekat->hasPages());

        // Card ringkasan tetap menampilkan total keseluruhan (15 aset)
        $response->assertSeeText('15 aset');

        // Memuat elemen navigasi halaman berikutnya (Next)
        $response->assertSee('rel="next"', false);

        // Baris nomor urut 1 sampai 10 muncul di HTML, nomor 11 tidak ada di halaman 1
        $response->assertSee('<td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">1</td>', false);
        $response->assertSee('<td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">10</td>', false);
        $response->assertDontSee('<td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">11</td>', false);
    }

    /**
     * 2. Uji halaman kedua menampilkan sisa barang (5 barang)
     *    dan nomor urut melanjutkan (11 sampai 15).
     */
    public function test_halaman_kedua_menampilkan_sisa_barang_dan_nomor_urut_melanjutkan(): void
    {
        $user = $this->adminAset();
        $ruangan = $this->buatRuanganTest('Ruang 15 Barang P2');
        $this->buatAsetMelekat($ruangan, 15);

        $response = $this->actingAs($user)->get(route('aset-ruangan.show', [
            'ruangan' => $ruangan->id_ruangan,
            'page' => 2,
        ]));

        $response->assertOk();

        $asetMelekat = $response->viewData('asetMelekat');
        $this->assertInstanceOf(LengthAwarePaginator::class, $asetMelekat);
        $this->assertSame(15, $asetMelekat->total());
        $this->assertSame(5, $asetMelekat->count());
        $this->assertSame(2, $asetMelekat->currentPage());
        $this->assertSame(11, $asetMelekat->firstItem());
        $this->assertSame(15, $asetMelekat->lastItem());

        // Memuat tombol Sebelumnya (rel="prev")
        $response->assertSee('rel="prev"', false);

        // Baris nomor urut 11 sampai 15 muncul di HTML, nomor 1 dan 10 tidak ada di tabel halaman 2
        $response->assertSee('<td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">11</td>', false);
        $response->assertSee('<td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">15</td>', false);
        $response->assertDontSee('<td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">1</td>', false);
        $response->assertDontSee('<td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">10</td>', false);
    }

    /**
     * 3. Uji tidak ada duplikasi data dan tidak ada data hilang saat berpindah halaman.
     */
    public function test_tidak_ada_duplikasi_atau_data_hilang_antara_halaman(): void
    {
        $user = $this->adminAset();
        $ruangan = $this->buatRuanganTest('Ruang 15 Cek Duplikasi');
        $asetList = $this->buatAsetMelekat($ruangan, 15);

        $res1 = $this->actingAs($user)->get(route('aset-ruangan.show', ['ruangan' => $ruangan->id_ruangan, 'page' => 1]));
        $res2 = $this->actingAs($user)->get(route('aset-ruangan.show', ['ruangan' => $ruangan->id_ruangan, 'page' => 2]));

        /** @var LengthAwarePaginator $p1 */
        $p1 = $res1->viewData('asetMelekat');
        /** @var LengthAwarePaginator $p2 */
        $p2 = $res2->viewData('asetMelekat');

        $idsP1 = $p1->pluck('id_aset')->all();
        $idsP2 = $p2->pluck('id_aset')->all();

        // Halaman 1 ada 10, Halaman 2 ada 5
        $this->assertCount(10, $idsP1);
        $this->assertCount(5, $idsP2);

        // Tidak ada irisan ID (tidak ada duplikasi)
        $intersection = array_intersect($idsP1, $idsP2);
        $this->assertEmpty($intersection, 'Data antara halaman 1 dan 2 tidak boleh duplikat.');

        // Gabungan keduanya harus sama persis dengan seluruh 15 aset yang dibuat
        $allRetrievedIds = array_merge($idsP1, $idsP2);
        $expectedIds = collect($asetList)->pluck('id_aset')->all();
        $this->assertSameSize($expectedIds, $allRetrievedIds);
        $this->assertEmpty(array_diff($expectedIds, $allRetrievedIds));
    }

    /**
     * 4. Uji ruangan dengan <= 10 barang (5 barang):
     *    - Seluruh barang tampil di 1 halaman.
     *    - Pagination TIDAK ditampilkan.
     */
    public function test_ruangan_kurang_dari_atau_sama_dengan_10_barang_tidak_menampilkan_pagination(): void
    {
        $user = $this->adminAset();
        $ruangan = $this->buatRuanganTest('Ruang 5 Barang');
        $this->buatAsetMelekat($ruangan, 5);

        $response = $this->actingAs($user)->get(route('aset-ruangan.show', $ruangan->id_ruangan));

        $response->assertOk();

        $asetMelekat = $response->viewData('asetMelekat');
        $this->assertSame(5, $asetMelekat->total());
        $this->assertSame(5, $asetMelekat->count());
        $this->assertFalse($asetMelekat->hasPages());

        // Card ringkasan menampilkan 5 aset
        $response->assertSeeText('5 aset');

        // Elemen navigasi rel="next" dan rel="prev" tidak muncul
        $response->assertDontSee('rel="next"', false);
        $response->assertDontSee('rel="prev"', false);
    }

    /**
     * 5. Uji ruangan kosong (0 barang):
     *    - Menampilkan pesan belum ada barang melekat.
     *    - Pagination tidak ditampilkan.
     */
    public function test_ruangan_kosong_menampilkan_pesan_kosong_tanpa_pagination(): void
    {
        $user = $this->adminAset();
        $ruangan = $this->buatRuanganTest('Ruang Kosong');

        $response = $this->actingAs($user)->get(route('aset-ruangan.show', $ruangan->id_ruangan));

        $response->assertOk();

        $asetMelekat = $response->viewData('asetMelekat');
        $this->assertSame(0, $asetMelekat->total());
        $this->assertFalse($asetMelekat->hasPages());

        $response->assertSeeText('Belum ada barang melekat pada ruangan ini.');
        $response->assertDontSee('rel="next"', false);
        $response->assertDontSee('rel="prev"', false);
    }

    /**
     * 6. Perpindahan halaman mempertahankan query parameter yang ada (withQueryString).
     */
    public function test_pagination_mempertahankan_query_string_parameter(): void
    {
        $user = $this->adminAset();
        $ruangan = $this->buatRuanganTest('Ruang Query String Test');
        $this->buatAsetMelekat($ruangan, 15);

        $response = $this->actingAs($user)->get(route('aset-ruangan.show', [
            'ruangan' => $ruangan->id_ruangan,
            'tab' => 'melekat',
            'kata_kunci' => 'meja',
        ]));

        $response->assertOk();

        /** @var LengthAwarePaginator $asetMelekat */
        $asetMelekat = $response->viewData('asetMelekat');
        $nextUrl = $asetMelekat->nextPageUrl();

        $this->assertNotNull($nextUrl);
        $this->assertStringContainsString('tab=melekat', $nextUrl);
        $this->assertStringContainsString('kata_kunci=meja', $nextUrl);
        $this->assertStringContainsString('page=2', $nextUrl);
    }

    /**
     * 7. Seluruh kolom dan tombol aksi (cetak label, mutasi ruangan, detach) tetap berfungsi.
     */
    public function test_fitur_dan_tombol_aksi_tetap_tersedia_pada_item_terpaginasi(): void
    {
        $user = $this->adminAset();
        $ruangan = $this->buatRuanganTest('Ruang Aksi Test');
        $asetList = $this->buatAsetMelekat($ruangan, 12);

        $response = $this->actingAs($user)->get(route('aset-ruangan.show', $ruangan->id_ruangan));

        $response->assertOk();

        $firstAset = $asetList[0];

        // Pastikan kolom-kolom utama ada
        $response->assertSeeText($firstAset->nomor_kartu_barang);
        $response->assertSeeText($firstAset->merk);

        // Pastikan tombol aksi ada
        $response->assertSee('data-mutasi-ruangan="' . $firstAset->id_aset . '"', false);
        $response->assertSee('data-detach-target="' . $firstAset->id_aset . '"', false);
        $response->assertSee(route('aset-barang.cetak.label.single', $firstAset->id_aset));
    }
}
