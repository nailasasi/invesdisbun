<?php

namespace Tests\Feature;

use App\Models\MasterKegiatanRkbmd;
use App\Models\Pegawai;
use App\Models\Role;
use App\Models\Skpd;
use App\Models\User;
use App\Models\UsulanRkbmd;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Pengujian Fitur Export Excel RKBMD (31 Kolom, 1 Sheet Berjenjang Tanpa ID Usulan):
 *
 * 1. Tombol Export Excel hanya terlihat oleh Admin Aset dan Super Admin.
 * 2. Tombol Import Excel tidak tampil pada halaman (telah dihapus dari aksi RKBMD).
 * 3. Admin Bidang dan Admin UPT tidak melihat tombol dan ditolak HTTP 403 saat akses URL langsung.
 * 4. File Excel memiliki tepat 31 kolom (A sampai AE) dengan 2 baris header berjenjang dan tanpa kolom ID Usulan.
 * 5. Data usulan berstatus Draf tidak ikut diekspor.
 * 6. Kode Barang dan NIP diekspor utuh sebagai tipe teks/string.
 * 7. Catatan Pengurus Barang dan Alasan Penolakan dipisahkan sesuai status usulan.
 * 8. Filter Tahun Anggaran, Jenis Usulan, dan Unit Kerja berfungsi pada hasil ekspor.
 */
class RkbmdExportTest extends TestCase
{
    private const PASSWORD = 'password-testing-123';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => 'invesdisbun',
        ]);
        DB::purge('mysql');
        DB::reconnect('mysql');

        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function buatRole(string $nama): Role
    {
        return Role::firstOrCreate(['nama_role' => $nama]);
    }

    private function buatSkpd(string $nama, string $jenis): Skpd
    {
        return Skpd::firstOrCreate(['nama_skpd' => $nama], ['jenis_skpd' => $jenis]);
    }

    private function buatUser(string $roleNama, ?Skpd $skpd, string $username): User
    {
        $role = $this->buatRole($roleNama);

        $pegawai = Pegawai::create([
            'nip' => '08' . random_int(10000000, 99999999),
            'nama_pegawai' => 'Pegawai ' . $username,
            'jabatan' => 'Staf ' . $roleNama,
            'id_skpd' => $skpd?->id_skpd,
        ]);

        return User::create([
            'id_pegawai' => $pegawai->id_pegawai,
            'id_role' => $role->id_role,
            'username' => $username,
            'password' => self::PASSWORD,
            'status_user' => 'aktif',
        ]);
    }

    private function buatUsulan(Skpd $skpd, ?User $creator = null, array $override = []): UsulanRkbmd
    {
        $kegiatan = MasterKegiatanRkbmd::first();

        return UsulanRkbmd::create(array_merge([
            'id_skpd' => $skpd->id_skpd,
            'id_pegawai' => $creator?->pegawai?->id_pegawai,
            'tahun_anggaran' => 2027,
            'jenis_usulan' => 'Pengadaan',
            'id_kegiatan' => $kegiatan?->id_kegiatan,
            'bidang_urusan' => $kegiatan?->bidang_urusan,
            'nama_program' => $kegiatan?->nama_program,
            'nama_sub_kegiatan' => $kegiatan?->nama_sub_kegiatan,
            'program_kegiatan' => $kegiatan ? "{$kegiatan->bidang_urusan} - {$kegiatan->nama_program} - {$kegiatan->nama_sub_kegiatan}" : 'Program Uji',
            'nama_barang' => 'Laptop Testing ' . uniqid(),
            'kode_barang' => '1.3.2.10.02.001',
            'spesifikasi' => 'Core i7, RAM 16GB, SSD 512GB',
            'jumlah' => 2,
            'satuan' => 'Unit',
            'kebutuhan_maksimum' => 5,
            'kebutuhan_riil' => 2,
            'barang_optimalisasi' => [
                'kode' => '1.3.2.10.02.999',
                'nama' => 'Laptop Lama',
                'jumlah' => 1,
                'satuan' => 'Unit',
            ],
            'alasan_kebutuhan' => 'Kebutuhan uji coba ekspor Excel.',
            'status_usulan' => 'Diajukan',
            'tanggal_usulan' => now()->toDateString(),
        ], $override));
    }

    /**
     * 1. Tombol Export Excel hanya terlihat oleh Admin Aset dan Super Admin.
     */
    public function test_visibilitas_tombol_export_excel_hanya_untuk_admin(): void
    {
        $superAdmin = $this->buatUser('Super Admin', null, 'super_export_view');
        $adminAset = $this->buatUser('Admin Aset', null, 'admin_aset_export_view');
        $skpdBidang = $this->buatSkpd('Bidang Perkebunan View Test', 'Bidang');
        $adminBidang = $this->buatUser('Admin Bidang', $skpdBidang, 'bidang_export_view');
        $skpdUpt = $this->buatSkpd('UPT Perkebunan View Test', 'UPT');
        $adminUpt = $this->buatUser('Admin UPT', $skpdUpt, 'upt_export_view');

        // Super Admin melihat tombol Export Excel dan TIDAK melihat Import Excel
        $resSuper = $this->actingAs($superAdmin)->get(route('rkbmd.index'));
        $resSuper->assertOk();
        $resSuper->assertSee('Export Excel');
        $resSuper->assertSee(route('rkbmd.export'), false);
        $resSuper->assertDontSee('Import Excel');

        // Admin Aset melihat tombol Export Excel dan TIDAK melihat Import Excel
        $resAset = $this->actingAs($adminAset)->get(route('rkbmd.index'));
        $resAset->assertOk();
        $resAset->assertSee('Export Excel');
        $resAset->assertDontSee('Import Excel');

        // Admin Bidang TIDAK melihat tombol Export Excel maupun Import Excel
        $resBidang = $this->actingAs($adminBidang)->get(route('rkbmd.index'));
        $resBidang->assertOk();
        $resBidang->assertDontSee('Export Excel');
        $resBidang->assertDontSee('Import Excel');

        // Admin UPT TIDAK melihat tombol Export Excel maupun Import Excel
        $resUpt = $this->actingAs($adminUpt)->get(route('rkbmd.index'));
        $resUpt->assertOk();
        $resUpt->assertDontSee('Export Excel');
        $resUpt->assertDontSee('Import Excel');
    }

    /**
     * 2. Admin Bidang dan Admin UPT ditolak HTTP 403 saat akses URL ekspor secara langsung.
     */
    public function test_admin_bidang_dan_upt_ditolak_403_saat_akses_url_export(): void
    {
        $skpdBidang = $this->buatSkpd('Bidang Direct Test', 'Bidang');
        $adminBidang = $this->buatUser('Admin Bidang', $skpdBidang, 'bidang_direct_export');
        $skpdUpt = $this->buatSkpd('UPT Direct Test', 'UPT');
        $adminUpt = $this->buatUser('Admin UPT', $skpdUpt, 'upt_direct_export');

        // Direct GET oleh Admin Bidang -> 403
        $resBidang = $this->actingAs($adminBidang)->get(route('rkbmd.export'));
        $resBidang->assertForbidden();

        // Direct GET oleh Admin UPT -> 403
        $resUpt = $this->actingAs($adminUpt)->get(route('rkbmd.export'));
        $resUpt->assertForbidden();
    }

    /**
     * 3. Struktur workbook memiliki tepat 31 kolom tanpa kolom ID Usulan.
     */
    public function test_struktur_workbook_memiliki_tepat_31_kolom_tanpa_id_usulan(): void
    {
        $superAdmin = $this->buatUser('Super Admin', null, 'super_structure_test');
        $skpd = $this->buatSkpd('Bidang Struktur 31 Kolom', 'Bidang');
        $pengusul = $this->buatUser('Admin Bidang', $skpd, 'pengusul_struktur_31');

        $usulan = $this->buatUsulan($skpd, $pengusul, [
            'nama_barang' => 'Drone Pertanian Precision 31',
            'kode_barang' => '1.3.2.10.02.001',
            'spesifikasi' => 'Kamera Multispektral 4K, Baterai 5000mAh',
            'jumlah' => 3,
            'kebutuhan_maksimum' => 5,
            'kebutuhan_riil' => 3,
            'status_usulan' => 'Diajukan',
        ]);

        $response = $this->actingAs($superAdmin)->get(route('rkbmd.export', ['bidang' => [$skpd->id_skpd]]));
        $response->assertSuccessful();

        $tmp = tempnam(sys_get_temp_dir(), 'rkbmd31_');
        file_put_contents($tmp, $response->streamedContent());
        $sheet = IOFactory::load($tmp)->getActiveSheet();
        @unlink($tmp);

        $data = $sheet->toArray(null, true, true, true);

        // Baris 1: Header Kategori
        $this->assertSame('A. IDENTITAS USULAN', $data[1]['A']);
        $this->assertSame('B. PROGRAM & KEGIATAN RKBMD', $data[1]['I']);
        $this->assertSame('C. RINCIAN BARANG YANG DIUSULKAN', $data[1]['M']);
        $this->assertSame('D. ANALISIS KEBUTUHAN', $data[1]['R']);
        $this->assertSame('E. BARANG YANG DAPAT DIOPTIMALKAN', $data[1]['U']);
        $this->assertSame('F. KETERANGAN & VERIFIKASI', $data[1]['AA']);

        // Baris 2: Sub-Header Kolom Spesifik (Harus tepat 31 kolom dan tanpa kolom ID Usulan)
        $this->assertCount(31, $data[2]);
        $this->assertNotContains('ID Usulan', $data[2]);
        $this->assertSame('No', $data[2]['A']);
        $this->assertSame('Bidang / Unit Kerja', $data[2]['B']);
        $this->assertSame('Nama Pengusul', $data[2]['C']);
        $this->assertSame('NIP Pengusul', $data[2]['D']);
        $this->assertSame('Tahun Anggaran', $data[2]['E']);
        $this->assertSame('Jenis Usulan', $data[2]['F']);
        $this->assertSame('Tanggal Pengajuan', $data[2]['G']);
        $this->assertSame('Status Usulan', $data[2]['H']);
        $this->assertSame('Bidang Urusan', $data[2]['I']);
        $this->assertSame('Nama Program', $data[2]['J']);
        $this->assertSame('Nama Sub Kegiatan', $data[2]['K']);
        $this->assertSame('Program / Kegiatan Gabungan', $data[2]['L']);
        $this->assertSame('Kode Barang', $data[2]['M']);
        $this->assertSame('Nama Barang', $data[2]['N']);
        $this->assertSame('Spesifikasi Teknis', $data[2]['O']);
        $this->assertSame('Jumlah Usulan', $data[2]['P']);
        $this->assertSame('Satuan Usulan', $data[2]['Q']);
        $this->assertSame('Kebutuhan Maksimum', $data[2]['R']);
        $this->assertSame('Kebutuhan Riil', $data[2]['S']);
        $this->assertSame('Satuan Kebutuhan', $data[2]['T']);
        $this->assertSame('Status Optimalisasi', $data[2]['U']);
        $this->assertSame('Kode Barang Lama', $data[2]['V']);
        $this->assertSame('Nama Barang Lama', $data[2]['W']);
        $this->assertSame('Jumlah Optimalisasi', $data[2]['X']);
        $this->assertSame('Satuan Optimalisasi', $data[2]['Y']);
        $this->assertSame('Keterangan Optimalisasi', $data[2]['Z']);
        $this->assertSame('Alasan Kebutuhan', $data[2]['AA']);
        $this->assertSame('Catatan Pengurus Barang', $data[2]['AB']);
        $this->assertSame('Alasan Penolakan', $data[2]['AC']);
        $this->assertSame('Petugas Verifikasi', $data[2]['AD']);
        $this->assertSame('Waktu Verifikasi', $data[2]['AE']);

        // Baris 3: Data Usulan
        $row = $data[3];
        $this->assertEquals(1, $row['A']);
        $this->assertSame($skpd->nama_skpd, $row['B']);
        $this->assertSame($pengusul->pegawai->nama_pegawai, $row['C']);
        $this->assertSame($pengusul->pegawai->nip, $row['D']);
        $this->assertSame('Diajukan', $row['H']);
        $this->assertSame('1.3.2.10.02.001', $row['M']);
        $this->assertSame('Drone Pertanian Precision 31', $row['N']);
        $this->assertSame('Kamera Multispektral 4K, Baterai 5000mAh', $row['O']);
        $this->assertSame('Ada Optimalisasi', $row['U']);
        $this->assertSame('1.3.2.10.02.999', $row['V']);
        $this->assertSame('Laptop Lama', $row['W']);
    }

    /**
     * 4. Usulan berstatus Draf tidak ikut diekspor oleh Super Admin / Admin Aset.
     */
    public function test_usulan_berstatus_draf_tidak_ikut_diekspor(): void
    {
        $skpd = $this->buatSkpd('Bidang Draft Filter Export', 'Bidang');
        $adminAset = $this->buatUser('Admin Aset', $skpd, 'admin_aset_draft_export');
        $pengusul = $this->buatUser('Admin Bidang', $skpd, 'pengusul_draft_export');

        $usulanDraftBidang = $this->buatUsulan($skpd, $pengusul, [
            'nama_barang' => 'Barang Rahasia Draft Exclude ' . uniqid(),
            'status_usulan' => 'Draft',
        ]);

        $usulanDraftAdminSendiri = $this->buatUsulan($skpd, $adminAset, [
            'nama_barang' => 'Draf Admin Aset Sendiri Exclude ' . uniqid(),
            'status_usulan' => 'Draft',
        ]);

        $usulanDiajukan = $this->buatUsulan($skpd, $pengusul, [
            'nama_barang' => 'Barang Resmi Diajukan Include ' . uniqid(),
            'status_usulan' => 'Diajukan',
        ]);

        // Ekspor oleh Admin Aset yang memiliki draf sendiri
        $response = $this->actingAs($adminAset)->get(route('rkbmd.export', ['bidang' => [$skpd->id_skpd]]));
        $response->assertSuccessful();

        $tmp = tempnam(sys_get_temp_dir(), 'rkbmd_draft_');
        file_put_contents($tmp, $response->streamedContent());
        $sheet = IOFactory::load($tmp)->getActiveSheet();
        @unlink($tmp);

        $data = $sheet->toArray(null, true, true, true);
        $namaBarangList = array_map(fn ($r) => (string) ($r['N'] ?? ''), $data);

        // Memastikan barang resmi ada dan barang draf (baik milik orang lain maupun milik admin sendiri) TIDAK ada
        $this->assertContains($usulanDiajukan->nama_barang, $namaBarangList);
        $this->assertNotContains($usulanDraftBidang->nama_barang, $namaBarangList);
        $this->assertNotContains($usulanDraftAdminSendiri->nama_barang, $namaBarangList);
    }

    /**
     * 5. Catatan Pengurus Barang dan Alasan Penolakan dipisahkan sesuai status.
     */
    public function test_catatan_pengurus_dan_alasan_penolakan_dipisahkan_sesuai_status(): void
    {
        $superAdmin = $this->buatUser('Super Admin', null, 'super_notes_test');
        $skpd = $this->buatSkpd('Bidang Notes Separation', 'Bidang');

        // Usulan Ditolak: kolom Catatan Pengurus Barang = '-', kolom Alasan Penolakan = alasan dari pengurus
        $usulanDitolak = $this->buatUsulan($skpd, null, [
            'nama_barang' => 'Barang Ditolak Alasan ' . uniqid(),
            'status_usulan' => 'Ditolak',
            'catatan_pengurus' => 'Spesifikasi teknis belum memenuhi standar SNI.',
        ]);

        // Usulan Diterima: kolom Catatan Pengurus Barang = catatan, kolom Alasan Penolakan = '-'
        $usulanDiterima = $this->buatUsulan($skpd, null, [
            'nama_barang' => 'Barang Diterima Catatan ' . uniqid(),
            'status_usulan' => 'Disetujui Pengurus Barang',
            'catatan_pengurus' => 'Disetujui untuk prioritas pengadaan APBD 2027.',
        ]);

        $response = $this->actingAs($superAdmin)->get(route('rkbmd.export', ['bidang' => [$skpd->id_skpd]]));
        $response->assertSuccessful();

        $tmp = tempnam(sys_get_temp_dir(), 'rkbmd_notes_');
        file_put_contents($tmp, $response->streamedContent());
        $sheet = IOFactory::load($tmp)->getActiveSheet();
        @unlink($tmp);

        $data = $sheet->toArray(null, true, true, true);

        // Cari baris usulan ditolak
        $rowDitolak = null;
        $rowDiterima = null;
        foreach ($data as $r) {
            if (($r['N'] ?? '') === $usulanDitolak->nama_barang) {
                $rowDitolak = $r;
            }
            if (($r['N'] ?? '') === $usulanDiterima->nama_barang) {
                $rowDiterima = $r;
            }
        }

        $this->assertNotNull($rowDitolak);
        $this->assertSame('-', $rowDitolak['AB'], 'Catatan Pengurus untuk usulan ditolak harus tanda strip.');
        $this->assertSame('Spesifikasi teknis belum memenuhi standar SNI.', $rowDitolak['AC']);

        $this->assertNotNull($rowDiterima);
        $this->assertSame('Disetujui untuk prioritas pengadaan APBD 2027.', $rowDiterima['AB']);
        $this->assertSame('-', $rowDiterima['AC'], 'Alasan penolakan untuk usulan diterima harus tanda strip.');
    }

    /**
     * 6. Filter Tahun Anggaran, Jenis Usulan, dan Unit Kerja berfungsi pada ekspor.
     */
    public function test_filter_tahun_jenis_dan_unit_kerja_berfungsi_pada_ekspor(): void
    {
        $superAdmin = $this->buatUser('Super Admin', null, 'super_filter_test');
        $skpd1 = $this->buatSkpd('Bidang Filter 1 ' . uniqid(), 'Bidang');
        $skpd2 = $this->buatSkpd('Bidang Filter 2 ' . uniqid(), 'Bidang');

        $u1 = $this->buatUsulan($skpd1, null, [
            'nama_barang' => 'Barang Target Filter ' . uniqid(),
            'tahun_anggaran' => 2027,
            'jenis_usulan' => 'Pengadaan',
        ]);

        $u2 = $this->buatUsulan($skpd2, null, [
            'nama_barang' => 'Barang Luar Filter ' . uniqid(),
            'tahun_anggaran' => 2028,
            'jenis_usulan' => 'Pemeliharaan',
        ]);

        // Ekspor dengan filter tahun=2027, jenis=Pengadaan, bidang=[$skpd1->id_skpd]
        $response = $this->actingAs($superAdmin)->get(route('rkbmd.export', [
            'tahun' => 2027,
            'jenis' => 'Pengadaan',
            'bidang' => [$skpd1->id_skpd],
        ]));
        $response->assertSuccessful();

        $tmp = tempnam(sys_get_temp_dir(), 'rkbmd_filter_');
        file_put_contents($tmp, $response->streamedContent());
        $sheet = IOFactory::load($tmp)->getActiveSheet();
        @unlink($tmp);

        $data = $sheet->toArray(null, true, true, true);
        $namaBarangList = array_map(fn ($r) => (string) ($r['N'] ?? ''), $data);

        $this->assertContains($u1->nama_barang, $namaBarangList);
        $this->assertNotContains($u2->nama_barang, $namaBarangList);
    }

    /**
     * 7. Endpoint Import Excel dan Download Template usulan telah dihapus total (HTTP 404).
     */
    public function test_route_dan_endpoint_import_serta_template_telah_dihapus(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('rkbmd.import'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('rkbmd.template'));

        $adminAset = $this->buatUser('Admin Aset', $this->buatSkpd('Aset Test No Import', 'Bidang'), 'admin_aset_no_import');

        $this->actingAs($adminAset)->post('/rkbmd/import')->assertNotFound();
        $this->actingAs($adminAset)->get('/rkbmd/template-usulan')->assertNotFound();
    }
}

