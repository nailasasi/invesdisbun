<?php

namespace Tests\Feature;

use App\Models\MasterKegiatanRkbmd;
use App\Models\Pegawai;
use App\Models\Role;
use App\Models\Skpd;
use App\Models\TemplateDokumen;
use App\Models\User;
use App\Models\UsulanRkbmd;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

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
            'nama_program' => $kegiatan?->nama_program ?: 'Program Perkebunan',
            'nama_sub_kegiatan' => $kegiatan?->nama_sub_kegiatan ?: 'Sub Kegiatan Perkebunan',
            'program_kegiatan' => 'Program Uji Perkebunan',
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

    private function loadExportSheet($response)
    {
        $tmp = tempnam(sys_get_temp_dir(), 'rkbmd_test_');
        file_put_contents($tmp, $response->streamedContent());
        $sheet = IOFactory::load($tmp)->getActiveSheet();
        @unlink($tmp);

        return $sheet;
    }

    /**
     * 1. Tombol Ekspor Data dan modal pilihan 5 jenis usulan tampil untuk Admin Aset, Admin Bidang, dan Admin UPT.
     */
    public function test_visibilitas_tombol_ekspor_data_dan_modal_pilihan_5_jenis(): void
    {
        $adminAset = $this->buatUser('Admin Aset', null, 'aset_exp_vis');
        $skpdBidang = $this->buatSkpd('Bidang Produksi Tanaman Perkebunan', 'Bidang');
        $adminBidang = $this->buatUser('Admin Bidang', $skpdBidang, 'bidang_exp_vis');
        $skpdUpt = $this->buatSkpd('UPT Proteksi Tanaman', 'UPT');
        $adminUpt = $this->buatUser('Admin UPT', $skpdUpt, 'upt_exp_vis');

        foreach ([$adminAset, $adminBidang, $adminUpt] as $user) {
            $res = $this->actingAs($user)->get(route('rkbmd.index'));
            $res->assertOk();
            $res->assertSee('Ekspor Data');
            $res->assertSee('modalExportRkbmd');
            $res->assertSee('PILIH JENIS USULAN RKBMD');
            $res->assertSee('Usulan RKBMD Pengadaan');
            $res->assertSee('Usulan RKBMD Pemeliharaan');
            $res->assertSee('Usulan RKBMD Penghapusan');
            $res->assertSee('Usulan RKBMD Pemindahtanganan');
            $res->assertSee('Usulan RKBMD Pemanfaatan');
        }
    }

    /**
     * 2. Pegawai biasa atau tamu ditolak saat mengakses endpoint ekspor (403 / redirect).
     */
    public function test_pegawai_biasa_dan_tamu_ditolak_akses_ekspor(): void
    {
        $skpd = $this->buatSkpd('Sekretariat Pegawai', 'Bidang');
        $pegawai = $this->buatUser('Pegawai', $skpd, 'pegawai_exp_block');

        // Pegawai biasa ditolak 403
        $this->actingAs($pegawai)->get(route('rkbmd.export'))->assertForbidden();

        // Tamu unauthenticated diarahkan ke login
        auth()->logout();
        $this->get(route('rkbmd.export'))->assertRedirect('/login');
    }

    /**
     * 3. Admin Aset dapat mengekspor seluruh data usulan lintas SKPD (Bidang & UPT).
     */
    public function test_admin_aset_bisa_ekspor_seluruh_bidang_dan_upt(): void
    {
        $adminAset = $this->buatUser('Admin Aset', null, 'aset_exp_all');
        $skpd1 = $this->buatSkpd('Bidang Satu Exp', 'Bidang');
        $skpd2 = $this->buatSkpd('UPT Dua Exp', 'UPT');

        $u1 = $this->buatUsulan($skpd1, null, ['nama_barang' => 'Barang Bidang Satu ' . uniqid(), 'status_usulan' => 'Diajukan']);
        $u2 = $this->buatUsulan($skpd2, null, ['nama_barang' => 'Barang UPT Dua ' . uniqid(), 'status_usulan' => 'Disetujui Pengurus Barang']);

        $response = $this->actingAs($adminAset)->get(route('rkbmd.export', ['jenis' => 'pengadaan']));
        $response->assertSuccessful();

        $sheet = $this->loadExportSheet($response);
        $data = $sheet->toArray(null, true, true, true);
        $namaBarangList = array_map(fn ($r) => (string) ($r['D'] ?? ''), $data);

        $this->assertContains($u1->nama_barang, $namaBarangList);
        $this->assertContains($u2->nama_barang, $namaBarangList);
    }

    /**
     * 4. Admin Bidang hanya dapat mengekspor data usulan milik SKPD-nya sendiri.
     */
    public function test_admin_bidang_hanya_mengekspor_data_skpd_sendiri(): void
    {
        $skpdBidang = $this->buatSkpd('Bidang Perlindungan Khusus', 'Bidang');
        $skpdLain = $this->buatSkpd('Bidang Lain Terpisah', 'Bidang');
        $adminBidang = $this->buatUser('Admin Bidang', $skpdBidang, 'bidang_exp_own');

        $uOwn = $this->buatUsulan($skpdBidang, $adminBidang, ['nama_barang' => 'Barang Milik Sendiri ' . uniqid()]);
        $uOther = $this->buatUsulan($skpdLain, null, ['nama_barang' => 'Barang Unit Lain ' . uniqid()]);

        $response = $this->actingAs($adminBidang)->get(route('rkbmd.export', ['jenis' => 'pengadaan']));
        $response->assertSuccessful();

        $sheet = $this->loadExportSheet($response);
        $data = $sheet->toArray(null, true, true, true);
        $namaBarangList = array_map(fn ($r) => (string) ($r['D'] ?? ''), $data);

        $this->assertContains($uOwn->nama_barang, $namaBarangList);
        $this->assertNotContains($uOther->nama_barang, $namaBarangList);
    }

    /**
     * 5. Manipulasi parameter query ?bidang=... oleh Admin Bidang tetap terkunci pada SKPD miliknya.
     */
    public function test_admin_bidang_manipulasi_parameter_bidang_tetap_terkunci_ke_skpd_sendiri(): void
    {
        $skpdBidang = $this->buatSkpd('Bidang Kunci Scope', 'Bidang');
        $skpdTargetHacker = $this->buatSkpd('Bidang Target Peretasan', 'Bidang');
        $adminBidang = $this->buatUser('Admin Bidang', $skpdBidang, 'bidang_hacker_exp');

        $uOwn = $this->buatUsulan($skpdBidang, $adminBidang, ['nama_barang' => 'Barang Resmi Saya ' . uniqid()]);
        $uTarget = $this->buatUsulan($skpdTargetHacker, null, ['nama_barang' => 'Barang Target Rahasia ' . uniqid()]);

        // Manipulasi query bidang ke SKPD unit lain
        $response = $this->actingAs($adminBidang)->get(route('rkbmd.export', [
            'jenis' => 'pengadaan',
            'bidang' => [$skpdTargetHacker->id_skpd],
        ]));
        $response->assertSuccessful();

        $sheet = $this->loadExportSheet($response);
        $data = $sheet->toArray(null, true, true, true);
        $namaBarangList = array_map(fn ($r) => (string) ($r['D'] ?? ''), $data);

        $this->assertContains($uOwn->nama_barang, $namaBarangList);
        $this->assertNotContains($uTarget->nama_barang, $namaBarangList);
    }

    /**
     * 6. Admin UPT hanya mengekspor data usulannya dan manipulasi parameter bidang tetap terkunci.
     */
    public function test_admin_upt_hanya_mengekspor_data_uptnya_dan_manipulasi_bidang_terkunci(): void
    {
        $skpdUpt = $this->buatSkpd('UPT Benih Tanaman', 'UPT');
        $skpdLain = $this->buatSkpd('UPT Lainnya', 'UPT');
        $adminUpt = $this->buatUser('Admin UPT', $skpdUpt, 'upt_scope_lock');

        $uOwn = $this->buatUsulan($skpdUpt, $adminUpt, ['nama_barang' => 'Barang UPT Resmi ' . uniqid()]);
        $uOther = $this->buatUsulan($skpdLain, null, ['nama_barang' => 'Barang UPT Lain ' . uniqid()]);

        $response = $this->actingAs($adminUpt)->get(route('rkbmd.export', [
            'jenis' => 'pengadaan',
            'bidang' => [$skpdLain->id_skpd],
        ]));
        $response->assertSuccessful();

        $sheet = $this->loadExportSheet($response);
        $data = $sheet->toArray(null, true, true, true);
        $namaBarangList = array_map(fn ($r) => (string) ($r['D'] ?? ''), $data);

        $this->assertContains($uOwn->nama_barang, $namaBarangList);
        $this->assertNotContains($uOther->nama_barang, $namaBarangList);
    }

    /**
     * 7. Usulan berstatus Draft tidak pernah ikut diekspor.
     */
    public function test_usulan_berstatus_draft_tidak_pernah_ikut_diekspor(): void
    {
        $adminAset = $this->buatUser('Admin Aset', null, 'aset_no_draft');
        $skpd = $this->buatSkpd('Bidang Draft Guard', 'Bidang');

        $uDraft = $this->buatUsulan($skpd, null, ['nama_barang' => 'Barang Draft Disembunyikan ' . uniqid(), 'status_usulan' => 'Draft']);
        $uDiajukan = $this->buatUsulan($skpd, null, ['nama_barang' => 'Barang Diajukan Diekspor ' . uniqid(), 'status_usulan' => 'Diajukan']);
        $uDiterima = $this->buatUsulan($skpd, null, ['nama_barang' => 'Barang Diterima Diekspor ' . uniqid(), 'status_usulan' => 'Disetujui Pengurus Barang']);
        $uDitolak = $this->buatUsulan($skpd, null, ['nama_barang' => 'Barang Ditolak Diekspor ' . uniqid(), 'status_usulan' => 'Ditolak']);

        $response = $this->actingAs($adminAset)->get(route('rkbmd.export', ['jenis' => 'pengadaan']));
        $response->assertSuccessful();

        $sheet = $this->loadExportSheet($response);
        $data = $sheet->toArray(null, true, true, true);
        $namaBarangList = array_map(fn ($r) => (string) ($r['D'] ?? ''), $data);

        $this->assertNotContains($uDraft->nama_barang, $namaBarangList);
        $this->assertContains($uDiajukan->nama_barang, $namaBarangList);
        $this->assertContains($uDiterima->nama_barang, $namaBarangList);
        $this->assertContains($uDitolak->nama_barang, $namaBarangList);
    }

    /**
     * 8. Filter Tahun Anggaran dan Status Usulan diterapkan dengan tepat.
     */
    public function test_filter_tahun_dan_status_usulan_diterapkan_dengan_tepat(): void
    {
        $adminAset = $this->buatUser('Admin Aset', null, 'aset_filter_ts');
        $skpd = $this->buatSkpd('Bidang Tahun Status', 'Bidang');

        $u2027Disetujui = $this->buatUsulan($skpd, null, [
            'nama_barang' => 'Barang 2027 Disetujui ' . uniqid(),
            'tahun_anggaran' => 2027,
            'status_usulan' => 'Disetujui Pengurus Barang',
        ]);
        $u2027Diajukan = $this->buatUsulan($skpd, null, [
            'nama_barang' => 'Barang 2027 Diajukan ' . uniqid(),
            'tahun_anggaran' => 2027,
            'status_usulan' => 'Diajukan',
        ]);
        $u2028Disetujui = $this->buatUsulan($skpd, null, [
            'nama_barang' => 'Barang 2028 Disetujui ' . uniqid(),
            'tahun_anggaran' => 2028,
            'status_usulan' => 'Disetujui Pengurus Barang',
        ]);

        $response = $this->actingAs($adminAset)->get(route('rkbmd.export', [
            'jenis' => 'pengadaan',
            'tahun' => 2027,
            'status' => 'disetujui',
        ]));
        $response->assertSuccessful();

        $sheet = $this->loadExportSheet($response);
        $data = $sheet->toArray(null, true, true, true);
        $namaBarangList = array_map(fn ($r) => (string) ($r['D'] ?? ''), $data);

        $this->assertContains($u2027Disetujui->nama_barang, $namaBarangList);
        $this->assertNotContains($u2027Diajukan->nama_barang, $namaBarangList);
        $this->assertNotContains($u2028Disetujui->nama_barang, $namaBarangList);
    }

    /**
     * 9. Seluruh 5 template resmi dapat diekspor dan menghasilkan nama file standar.
     */
    public function test_seluruh_5_template_resmi_dapat_diekspor_dan_nama_file_standar(): void
    {
        $adminAset = $this->buatUser('Admin Aset', null, 'aset_5_tpl');
        $skpd = $this->buatSkpd('Bidang 5 Template', 'Bidang');

        $jenisMap = [
            'pengadaan' => 'Pengadaan',
            'pemeliharaan' => 'Pemeliharaan',
            'penghapusan' => 'Penghapusan',
            'pemindahtanganan' => 'Pemindahtanganan',
            'pemanfaatan' => 'Pemanfaatan',
        ];

        foreach ($jenisMap as $jenisKey => $label) {
            $u = $this->buatUsulan($skpd, null, [
                'nama_barang' => "Barang {$label} " . uniqid(),
                'jenis_usulan' => $label,
                'tahun_anggaran' => 2027,
            ]);

            $response = $this->actingAs($adminAset)->get(route('rkbmd.export', [
                'jenis' => $jenisKey,
                'tahun' => 2027,
            ]));

            $response->assertSuccessful();
            $contentDisposition = $response->headers->get('content-disposition');
            $this->assertStringContainsString("Ekspor_RKBMD_{$label}_2027.xlsx", $contentDisposition);
        }
    }

    /**
     * 10. Header Pengguna Barang diformat dinamis sesuai peran pengguna.
     */
    public function test_header_pengguna_barang_sesuai_peran(): void
    {
        $adminAset = $this->buatUser('Admin Aset', null, 'aset_header_chk');
        $skpdBidang = $this->buatSkpd('Bidang Perlindungan Perkebunan', 'Bidang');
        $adminBidang = $this->buatUser('Admin Bidang', $skpdBidang, 'bidang_header_chk');
        $skpdUpt = $this->buatSkpd('UPT Pengawasan Benih', 'UPT');
        $adminUpt = $this->buatUser('Admin UPT', $skpdUpt, 'upt_header_chk');

        // Admin Aset tanpa filter -> PENGGUNA BARANG SEMUA BIDANG DAN UPT
        $resAset = $this->actingAs($adminAset)->get(route('rkbmd.export', ['jenis' => 'pengadaan', 'tahun' => 2027]));
        $sheetAset = $this->loadExportSheet($resAset);
        $this->assertSame('PENGGUNA BARANG SEMUA BIDANG DAN UPT', $sheetAset->getCell('A3')->getValue());

        // Admin Bidang -> PENGGUNA BARANG BIDANG PERLINDUNGAN PERKEBUNAN
        $this->buatUsulan($skpdBidang, $adminBidang, ['jenis_usulan' => 'Pengadaan', 'tahun_anggaran' => 2027]);
        $resBidang = $this->actingAs($adminBidang)->get(route('rkbmd.export', ['jenis' => 'pengadaan', 'tahun' => 2027]));
        $sheetBidang = $this->loadExportSheet($resBidang);
        $this->assertSame('PENGGUNA BARANG BIDANG PERLINDUNGAN PERKEBUNAN', $sheetBidang->getCell('A3')->getValue());

        // Admin UPT -> PENGGUNA BARANG UPT PENGAWASAN BENIH
        $this->buatUsulan($skpdUpt, $adminUpt, ['jenis_usulan' => 'Pengadaan', 'tahun_anggaran' => 2027]);
        $resUpt = $this->actingAs($adminUpt)->get(route('rkbmd.export', ['jenis' => 'pengadaan', 'tahun' => 2027]));
        $sheetUpt = $this->loadExportSheet($resUpt);
        $this->assertSame('PENGGUNA BARANG UPT PENGAWASAN BENIH', $sheetUpt->getCell('A3')->getValue());
    }

    /**
     * 11. Format Kuasa Pengguna Barang/Program/Kegiatan/Output: "Nama Program | Nama Sub Kegiatan".
     */
    public function test_format_kuasa_pengguna_barang_program_sub_kegiatan(): void
    {
        $adminAset = $this->buatUser('Admin Aset', null, 'aset_prog_sub');
        $skpd = $this->buatSkpd('Bidang Program Check', 'Bidang');

        $this->buatUsulan($skpd, null, [
            'nama_barang' => 'Mesin Olah Kopi ' . uniqid(),
            'nama_program' => 'PROGRAM PENGEMBANGAN TEKNOLOGI',
            'nama_sub_kegiatan' => 'Pemberdayaan Petani Kopi',
            'jenis_usulan' => 'Pengadaan',
        ]);

        $response = $this->actingAs($adminAset)->get(route('rkbmd.export', [
            'jenis' => 'pengadaan',
            'bidang' => [$skpd->id_skpd],
        ]));
        $sheet = $this->loadExportSheet($response);

        $this->assertSame('PROGRAM PENGEMBANGAN TEKNOLOGI | Pemberdayaan Petani Kopi', $sheet->getCell('B11')->getValue());
    }

    /**
     * 12. Tanda tangan Kepala Dinas Perkebunan Provinsi Jawa Timur tetap utuh pada hasil ekspor.
     */
    public function test_penandatangan_kepala_dinas_perkebunan_tetap_utuh(): void
    {
        $adminAset = $this->buatUser('Admin Aset', null, 'aset_ttd_chk');
        $skpd = $this->buatSkpd('Bidang TTD Check', 'Bidang');

        $this->buatUsulan($skpd, null, ['jenis_usulan' => 'Pengadaan']);

        $response = $this->actingAs($adminAset)->get(route('rkbmd.export', [
            'jenis' => 'pengadaan',
            'bidang' => [$skpd->id_skpd],
        ]));
        $sheet = $this->loadExportSheet($response);

        $foundKadis = false;
        $foundSuseno = false;
        $foundNip = false;

        $maxR = $sheet->getHighestRow();
        for ($r = 20; $r <= $maxR; $r++) {
            $val = (string) $sheet->getCell("M{$r}")->getValue();
            if (stripos($val, 'Kepala Dinas Perkebunan') !== false) {
                $foundKadis = true;
            }
            if (stripos($val, 'Dr. Ir. Heru Suseno, M.T.') !== false) {
                $foundSuseno = true;
            }
            if (stripos($val, '196805301995031003') !== false) {
                $foundNip = true;
            }
        }

        $this->assertTrue($foundKadis, 'Jabatan Kepala Dinas Perkebunan harus ditemukan.');
        $this->assertTrue($foundSuseno, 'Nama Dr. Ir. Heru Suseno, M.T. harus ditemukan.');
        $this->assertTrue($foundNip, 'NIP 196805301995031003 harus ditemukan.');
    }

    /**
     * 13. Data yang bernilai null menggunakan string 'NIHIL', bukan '-' atau '0'.
     */
    public function test_data_null_menggunakan_string_nihil(): void
    {
        $adminAset = $this->buatUser('Admin Aset', null, 'aset_nihil_chk');
        $skpd = $this->buatSkpd('Bidang Nihil Check', 'Bidang');

        $this->buatUsulan($skpd, null, [
            'nama_barang' => 'Barang Serba Null ' . uniqid(),
            'kode_barang' => null,
            'kebutuhan_maksimum' => null,
            'kebutuhan_riil' => null,
            'barang_optimalisasi' => null,
            'alasan_kebutuhan' => null,
            'jenis_usulan' => 'Pengadaan',
        ]);

        $response = $this->actingAs($adminAset)->get(route('rkbmd.export', [
            'jenis' => 'pengadaan',
            'bidang' => [$skpd->id_skpd],
        ]));
        $sheet = $this->loadExportSheet($response);

        // Baris data 11
        $this->assertSame('NIHIL', $sheet->getCell('C11')->getValue(), 'Kode barang null harus NIHIL');
        $this->assertSame('NIHIL', $sheet->getCell('G11')->getValue(), 'Kebutuhan maksimum null harus NIHIL');
        $this->assertSame('NIHIL', $sheet->getCell('H11')->getValue(), 'Satuan kebutuhan maksimum null harus NIHIL');
        $this->assertSame('NIHIL', $sheet->getCell('I11')->getValue(), 'Optimalisasi kode null harus NIHIL');
        $this->assertSame('NIHIL', $sheet->getCell('M11')->getValue(), 'Kebutuhan riil null harus NIHIL');
        $this->assertSame('NIHIL', $sheet->getCell('O11')->getValue(), 'Keterangan null harus NIHIL');
    }

    /**
     * 14. Template Pemeliharaan: kolom G sampai N dibiarkan KOSONG (""), bukan NIHIL.
     */
    public function test_pemeliharaan_kolom_g_sampai_n_dibiarkan_kosong(): void
    {
        $adminAset = $this->buatUser('Admin Aset', null, 'aset_pemel_empty');
        $skpd = $this->buatSkpd('Bidang Pemeliharaan Empty', 'Bidang');

        $u = $this->buatUsulan($skpd, null, [
            'nama_barang' => 'Traktor Roda Empat ' . uniqid(),
            'jenis_usulan' => 'Pemeliharaan',
            'jumlah' => 1,
            'satuan' => 'Unit',
        ]);

        $response = $this->actingAs($adminAset)->get(route('rkbmd.export', [
            'jenis' => 'pemeliharaan',
            'bidang' => [$skpd->id_skpd],
        ]));
        $sheet = $this->loadExportSheet($response);

        // Baris data 12: Kolom A-F terisi data, kolom G-N kosong
        $this->assertSame(1, (int) $sheet->getCell('A12')->getValue());
        $this->assertSame($u->nama_barang, $sheet->getCell('D12')->getValue());

        foreach (range('G', 'N') as $col) {
            $val = $sheet->getCell("{$col}12")->getValue();
            $this->assertTrue($val === '' || $val === null, "Kolom {$col}12 pada template pemeliharaan harus kosong string.");
        }
    }

    /**
     * 15. Template Penghapusan: NIBAR dan Nilai Perolehan adalah NIHIL, baris TOTAL tetap ada.
     */
    public function test_penghapusan_nibar_nilai_perolehan_nihil_dan_total_ada(): void
    {
        $adminAset = $this->buatUser('Admin Aset', null, 'aset_hapus_chk');
        $skpd = $this->buatSkpd('Bidang Penghapusan Check', 'Bidang');

        $this->buatUsulan($skpd, null, [
            'nama_barang' => 'Mobil Rusak Berat ' . uniqid(),
            'jenis_usulan' => 'Penghapusan',
            'jumlah' => 1,
        ]);

        $response = $this->actingAs($adminAset)->get(route('rkbmd.export', [
            'jenis' => 'penghapusan',
            'bidang' => [$skpd->id_skpd],
        ]));
        $sheet = $this->loadExportSheet($response);

        // Baris data 16
        $this->assertSame('NIHIL', $sheet->getCell('E16')->getValue(), 'NIBAR harus NIHIL');
        $this->assertSame('NIHIL', $sheet->getCell('H16')->getValue(), 'Nilai perolehan harus NIHIL');

        // Pastikan baris TOTAL ditemukan di bawah data
        $foundTotal = false;
        $maxR = $sheet->getHighestRow();
        for ($r = 17; $r <= $maxR; $r++) {
            if ($sheet->getCell("H{$r}")->getValue() === 'TOTAL') {
                $foundTotal = true;
                break;
            }
        }
        $this->assertTrue($foundTotal, 'Baris TOTAL harus tetap ada pada template Penghapusan.');
    }

    /**
     * 16. Template Pemindahtanganan: NIBAR, Lokasi, Nilai, Bentuk adalah NIHIL, baris TOTAL tetap ada.
     */
    public function test_pemindahtanganan_nibar_lokasi_nilai_bentuk_nihil_dan_total_ada(): void
    {
        $adminAset = $this->buatUser('Admin Aset', null, 'aset_pindah_chk');
        $skpd = $this->buatSkpd('Bidang Pemindahtanganan Check', 'Bidang');

        $this->buatUsulan($skpd, null, [
            'nama_barang' => 'Genset Hibah ' . uniqid(),
            'jenis_usulan' => 'Pemindahtanganan',
            'jumlah' => 1,
        ]);

        $response = $this->actingAs($adminAset)->get(route('rkbmd.export', [
            'jenis' => 'pemindahtanganan',
            'bidang' => [$skpd->id_skpd],
        ]));
        $sheet = $this->loadExportSheet($response);

        // Baris data 15
        $this->assertSame('NIHIL', $sheet->getCell('E15')->getValue(), 'NIBAR harus NIHIL');
        $this->assertSame('NIHIL', $sheet->getCell('H15')->getValue(), 'Lokasi harus NIHIL');
        $this->assertSame('NIHIL', $sheet->getCell('I15')->getValue(), 'Nilai perolehan harus NIHIL');
        $this->assertSame('NIHIL', $sheet->getCell('J15')->getValue(), 'Bentuk pemindahtanganan harus NIHIL');

        $foundTotal = false;
        $maxR = $sheet->getHighestRow();
        for ($r = 16; $r <= $maxR; $r++) {
            if ($sheet->getCell("H{$r}")->getValue() === 'TOTAL') {
                $foundTotal = true;
                break;
            }
        }
        $this->assertTrue($foundTotal, 'Baris TOTAL harus tetap ada pada template Pemindahtanganan.');
    }

    /**
     * 17. Template Pemanfaatan: Kolom B adalah SKPD pemilik usulan, kolom data teknis (J-S) adalah NIHIL.
     */
    public function test_pemanfaatan_kolom_b_skpd_dan_data_teknis_nihil(): void
    {
        $adminAset = $this->buatUser('Admin Aset', null, 'aset_manfaat_chk');
        $skpd = $this->buatSkpd('UPT Produksi Benih Unggul', 'UPT');

        $u = $this->buatUsulan($skpd, null, [
            'nama_barang' => 'Aula Serbaguna ' . uniqid(),
            'jenis_usulan' => 'Pemanfaatan',
            'jumlah' => 1,
            'alasan_kebutuhan' => 'Sewa aula pertemuan',
        ]);

        $response = $this->actingAs($adminAset)->get(route('rkbmd.export', [
            'jenis' => 'pemanfaatan',
            'bidang' => [$skpd->id_skpd],
        ]));
        $sheet = $this->loadExportSheet($response);

        // Baris data 12
        $this->assertSame('UPT Produksi Benih Unggul', $sheet->getCell('B12')->getValue());
        $this->assertSame($u->nama_barang, $sheet->getCell('E12')->getValue());
        $this->assertSame('NIHIL', $sheet->getCell('G12')->getValue(), 'Reg harus NIHIL');
        // Unmerge Kolom H dan I: H berisi Jumlah numerik, I berdiri sendiri berisi NIHIL (Deskripsi Barang)
        $this->assertSame(1, (int) $sheet->getCell('H12')->getValue(), 'Jumlah pemanfaatan pada kolom H harus angka');
        $this->assertSame('NIHIL', $sheet->getCell('I12')->getValue(), 'Deskripsi barang pada kolom I harus NIHIL');
        $this->assertFalse($sheet->getCell('H12')->isInMergeRange(), 'Kolom H12 tidak boleh dalam status merge');
        $this->assertFalse($sheet->getCell('I12')->isInMergeRange(), 'Kolom I12 tidak boleh dalam status merge');

        $this->assertSame('NIHIL', $sheet->getCell('J12')->getValue(), 'Luas M2 harus NIHIL');
        $this->assertSame('NIHIL', $sheet->getCell('K12')->getValue(), 'Letak alamat harus NIHIL');
        $this->assertSame('NIHIL', $sheet->getCell('L12')->getValue(), 'Peruntukan harus NIHIL');
        $this->assertSame('NIHIL', $sheet->getCell('M12')->getValue(), 'Status harus NIHIL');
        $this->assertSame('NIHIL', $sheet->getCell('O12')->getValue(), 'Validasi harus NIHIL');
        $this->assertSame('Sewa aula pertemuan', $sheet->getCell('T12')->getValue(), 'Peruntukan pemanfaatan harus sesuai alasan');
    }

    /**
     * 18. Grouping: Jika 5 field sama persis, kolom numerik dijumlahkan (SUM) dan teks digabung ( / ).
     */
    public function test_grouping_lima_field_identik_menjumlahkan_jumlah_usulan(): void
    {
        $adminAset = $this->buatUser('Admin Aset', null, 'aset_grp_sum');
        $skpd = $this->buatSkpd('Bidang Grouping Sum', 'Bidang');

        $kodeBarang = '1.3.2.05.01.001';
        $namaBarang = 'Komputer Server Identik';
        $program = 'PROGRAM TEKNOLOGI INFORMASI';
        $subKegiatan = 'Pengadaan Perangkat Server';
        $satuan = 'Unit';

        // Usulan 1: jumlah 3, maks 10, riil 4, opt 1, alasan 'Server database lama rusak'
        $this->buatUsulan($skpd, null, [
            'nama_program' => $program,
            'nama_sub_kegiatan' => $subKegiatan,
            'kode_barang' => $kodeBarang,
            'nama_barang' => $namaBarang,
            'satuan' => $satuan,
            'jumlah' => 3,
            'kebutuhan_maksimum' => 10,
            'kebutuhan_riil' => 4,
            'barang_optimalisasi' => [
                'kode' => '1.3.2.05.01.999',
                'nama' => 'Server Bekas UPT',
                'jumlah' => 1,
                'satuan' => 'Unit',
            ],
            'alasan_kebutuhan' => 'Server database lama rusak',
            'jenis_usulan' => 'Pengadaan',
        ]);

        // Usulan 2: jumlah 5, maks 15, riil 6, opt 2, alasan 'Kebutuhan server backup data'
        $this->buatUsulan($skpd, null, [
            'nama_program' => $program,
            'nama_sub_kegiatan' => $subKegiatan,
            'kode_barang' => $kodeBarang,
            'nama_barang' => $namaBarang,
            'satuan' => $satuan,
            'jumlah' => 5,
            'kebutuhan_maksimum' => 15,
            'kebutuhan_riil' => 6,
            'barang_optimalisasi' => [
                'kode' => '1.3.2.05.01.999',
                'nama' => 'Server Cadangan Lab',
                'jumlah' => 2,
                'satuan' => 'Unit',
            ],
            'alasan_kebutuhan' => 'Kebutuhan server backup data',
            'jenis_usulan' => 'Pengadaan',
        ]);

        $response = $this->actingAs($adminAset)->get(route('rkbmd.export', [
            'jenis' => 'pengadaan',
            'bidang' => [$skpd->id_skpd],
        ]));
        $sheet = $this->loadExportSheet($response);

        // Hanya menghasilkan 1 baris data di row 11
        $this->assertSame(1, (int) $sheet->getCell('A11')->getValue());
        $this->assertSame('Komputer Server Identik', $sheet->getCell('D11')->getValue());

        // 1. Numerik dijumlahkan (SUM)
        $this->assertSame(8, (int) $sheet->getCell('E11')->getValue(), 'Jumlah usulan (3 + 5) harus 8.');
        $this->assertSame(25, (int) $sheet->getCell('G11')->getValue(), 'Kebutuhan maksimum (10 + 15) harus 25.');
        $this->assertSame(3, (int) $sheet->getCell('K11')->getValue(), 'Jumlah optimalisasi (1 + 2) harus 3.');
        $this->assertSame(10, (int) $sheet->getCell('M11')->getValue(), 'Kebutuhan riil (4 + 6) harus 10.');

        // 2. Deskriptif digabung dengan pemisah ' / '
        $this->assertSame('Server database lama rusak / Kebutuhan server backup data', $sheet->getCell('O11')->getValue());
        $this->assertSame('Server Bekas UPT / Server Cadangan Lab', $sheet->getCell('J11')->getValue());

        // Baris 12 bukan baris kedua komputer server
        $this->assertNotSame('Komputer Server Identik', $sheet->getCell('D12')->getValue());
    }

    /**
     * 19. Grouping: Jika satuan berbeda, usulan TIDAK digabungkan (menjadi 2 baris terpisah).
     */
    public function test_grouping_satuan_berbeda_tidak_digabungkan(): void
    {
        $adminAset = $this->buatUser('Admin Aset', null, 'aset_grp_diff');
        $skpd = $this->buatSkpd('Bidang Grouping Diff', 'Bidang');

        $kodeBarang = '1.3.2.05.01.002';
        $namaBarang = 'Paket Jaringan Internet';
        $program = 'PROGRAM TEKNOLOGI INFORMASI';
        $subKegiatan = 'Pengadaan Jaringan';

        // Usulan 1: satuan Unit
        $this->buatUsulan($skpd, null, [
            'nama_program' => $program,
            'nama_sub_kegiatan' => $subKegiatan,
            'kode_barang' => $kodeBarang,
            'nama_barang' => $namaBarang,
            'satuan' => 'Unit',
            'jumlah' => 2,
            'jenis_usulan' => 'Pengadaan',
        ]);

        // Usulan 2: satuan Paket (satuan berbeda)
        $this->buatUsulan($skpd, null, [
            'nama_program' => $program,
            'nama_sub_kegiatan' => $subKegiatan,
            'kode_barang' => $kodeBarang,
            'nama_barang' => $namaBarang,
            'satuan' => 'Paket',
            'jumlah' => 1,
            'jenis_usulan' => 'Pengadaan',
        ]);

        $response = $this->actingAs($adminAset)->get(route('rkbmd.export', [
            'jenis' => 'pengadaan',
            'bidang' => [$skpd->id_skpd],
        ]));
        $sheet = $this->loadExportSheet($response);

        // Menghasilkan 2 baris terpisah di row 11 dan row 12
        $this->assertSame(1, (int) $sheet->getCell('A11')->getValue());
        $this->assertSame(2, (int) $sheet->getCell('A12')->getValue());
        $this->assertSame('Paket Jaringan Internet', $sheet->getCell('D11')->getValue());
        $this->assertSame('Paket Jaringan Internet', $sheet->getCell('D12')->getValue());

        $satuans = [$sheet->getCell('F11')->getValue(), $sheet->getCell('F12')->getValue()];
        $this->assertContains('Unit', $satuans);
        $this->assertContains('Paket', $satuans);
    }

    /**
     * 20. Penanganan error ramah jika berkas fisik template hilang (tidak crash exception).
     */
    public function test_error_handling_jika_berkas_fisik_template_hilang(): void
    {
        $adminAset = $this->buatUser('Admin Aset', null, 'aset_err_handling');

        // Simulasikan file_path template salah/hilang sementara
        $tpl = TemplateDokumen::where('kode_template', 'rkbmd_pengadaan')->first();
        $originalPath = $tpl->file_path;
        $tpl->update(['file_path' => 'templates/non_existent_template.xlsx']);

        $response = $this->actingAs($adminAset)->get(route('rkbmd.export', ['jenis' => 'pengadaan']));

        // Kembalikan path asli
        $tpl->update(['file_path' => $originalPath]);

        // Tidak crash unhandled 500, melainkan redirect back dengan pesan error
        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    /**
     * 21. Logika mergeDescriptiveText menggabungkan string berbeda, memangkas spasi, dan mencegah duplikasi.
     */
    public function test_merge_descriptive_text_mencegah_duplikasi_dan_menggabungkan_dengan_pemisah(): void
    {
        $service = app(\App\Services\RkbmdExportService::class);

        // Jika salah satu null
        $this->assertSame('Catatan A', $service->mergeDescriptiveText('Catatan A', null));
        $this->assertSame('Catatan B', $service->mergeDescriptiveText(null, 'Catatan B'));

        // Jika bernilai identik (deduplikasi)
        $this->assertSame('Catatan Sama', $service->mergeDescriptiveText('Catatan Sama', 'Catatan Sama'));

        // Jika bernilai berbeda -> dipisah ' / '
        $this->assertSame('Alasan 1 / Alasan 2', $service->mergeDescriptiveText('Alasan 1', 'Alasan 2'));

        // Menambahkan teks baru ke gabungan yang sudah ada
        $this->assertSame('A / B / C', $service->mergeDescriptiveText('A / B', 'C'));

        // Menambahkan teks yang sudah ada dalam gabungan (tetap deduplikasi)
        $this->assertSame('A / B', $service->mergeDescriptiveText('A / B', 'A'));
    }
}
