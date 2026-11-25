<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\Lokasi;
use App\Models\MasterBarang;
use App\Models\Pegawai;
use App\Models\PemegangAset;
use App\Models\PenempatanAset;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\SensusAset;
use App\Models\Skpd;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class MonitoringAsetExportTest extends TestCase
{
    private User $adminAset;
    private User $adminUptP2btp;
    private User $adminUptPsbp;
    private User $pegawaiBiasa;
    private Skpd $skpdDinas;
    private Skpd $skpdP2btp;
    private Skpd $skpdPsbp;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $this->namaDatabase(),
        ]);

        DB::beginTransaction();
        Carbon::setTestNow(Carbon::parse('2026-11-01 10:00:00'));

        $this->siapkanDataPengguna();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function namaDatabase(): string
    {
        return $this->namaDatabasePengujian();
    }

    private function siapkanDataPengguna(): void
    {
        $this->adminAset = User::whereHas('role', fn ($q) => $q->where('nama_role', 'Admin Aset'))->firstOrFail();

        $this->skpdP2btp = Skpd::where('nama_skpd', 'UPT P2BTP')->firstOrFail();
        $this->skpdPsbp = Skpd::where('nama_skpd', 'UPT PSBP')->firstOrFail();
        $this->skpdDinas = Skpd::where('nama_skpd', 'Sekretariat')->first() ?? Skpd::first();

        $this->adminUptP2btp = User::whereHas('role', fn ($q) => $q->where('nama_role', 'Admin UPT'))
            ->whereHas('pegawai.skpd', fn ($q) => $q->where('nama_skpd', 'UPT P2BTP'))
            ->firstOrFail();

        $this->adminUptPsbp = User::whereHas('role', fn ($q) => $q->where('nama_role', 'Admin UPT'))
            ->whereHas('pegawai.skpd', fn ($q) => $q->where('nama_skpd', 'UPT PSBP'))
            ->first() ?? $this->buatUserAdminUpt($this->skpdPsbp);

        $this->pegawaiBiasa = User::whereHas('role', fn ($q) => $q->where('nama_role', 'Pegawai'))->first()
            ?? $this->buatUserPegawai();
    }

    private function buatUserAdminUpt(Skpd $skpd): User
    {
        $pegawai = Pegawai::create([
            'nip' => 'UPT' . uniqid(),
            'nama_pegawai' => 'Admin ' . $skpd->nama_skpd,
            'id_skpd' => $skpd->id_skpd,
        ]);

        return User::create([
            'id_pegawai' => $pegawai->id_pegawai,
            'username' => 'upt_' . strtolower(str_replace(' ', '_', $skpd->nama_skpd)) . '_' . uniqid(),
            'password' => bcrypt('password'),
            'id_role' => Role::where('nama_role', 'Admin UPT')->firstOrFail()->id_role,
            'status_user' => 'aktif',
        ]);
    }

    private function buatUserPegawai(): User
    {
        $pegawai = Pegawai::create([
            'nip' => 'PGW' . uniqid(),
            'nama_pegawai' => 'Pegawai Biasa Test',
            'id_skpd' => $this->skpdDinas->id_skpd,
        ]);

        return User::create([
            'id_pegawai' => $pegawai->id_pegawai,
            'username' => 'pgw_' . uniqid(),
            'password' => bcrypt('password'),
            'id_role' => Role::where('nama_role', 'Pegawai')->firstOrFail()->id_role,
            'status_user' => 'aktif',
        ]);
    }

    private function loadSpreadsheetFromContent(string $content): Spreadsheet
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'test_exp_') . '.xlsx';
        file_put_contents($tempPath, $content);
        $spreadsheet = IOFactory::load($tempPath);
        @unlink($tempPath);
        return $spreadsheet;
    }

    private function teksSheet(Spreadsheet $spreadsheet, int $sheetIndex = 0): string
    {
        $sheet = $spreadsheet->getSheet($sheetIndex);
        $lines = [];
        for ($r = 1; $r <= $sheet->getHighestRow(); $r++) {
            $rowVals = [];
            for ($col = 'A'; $col <= $sheet->getHighestColumn(); $col++) {
                $rowVals[] = (string) $sheet->getCell($col . $r)->getValue();
            }
            $lines[] = implode(' | ', $rowVals);
        }
        return implode("\n", $lines);
    }

    private function teksDataRows(Spreadsheet $spreadsheet, int $sheetIndex = 0, int $startRow = 6): string
    {
        $sheet = $spreadsheet->getSheet($sheetIndex);
        $lines = [];
        for ($r = $startRow; $r <= $sheet->getHighestRow(); $r++) {
            $rowVals = [];
            for ($col = 'A'; $col <= $sheet->getHighestColumn(); $col++) {
                $rowVals[] = (string) $sheet->getCell($col . $r)->getValue();
            }
            $lines[] = implode(' | ', $rowVals);
        }
        return implode("\n", $lines);
    }

    /**
     * 1. Test UI Monitoring Aset memuat satu tombol "Export Data" dan modal 4 pilihan.
     */
    public function test_ui_monitoring_aset_memiliki_satu_tombol_export_data_dan_modal_empat_pilihan(): void
    {
        $response = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.index'));

        $response->assertOk();
        $html = $response->getContent();

        // Tombol tunggal Export Data ada
        $this->assertStringContainsString('Export Data', $html);
        $this->assertStringContainsString('openExportModal()', $html);

        // Tombol lama Rekap Excel & Export per Pengguna tidak ada di action bar
        $this->assertStringNotContainsString('<span>Rekap Excel</span>', $html);
        $this->assertStringNotContainsString('<span>Export per Pengguna</span>', $html);

        // Modal Export Data ada beserta 4 opsi jenis laporan
        $this->assertStringContainsString('id="export-modal"', $html);
        $this->assertStringContainsString('value="seluruh"', $html);
        $this->assertStringContainsString('value="per_skpd"', $html);
        $this->assertStringContainsString('value="per_pemegang"', $html);
        $this->assertStringContainsString('value="per_ruangan"', $html);
        $this->assertStringContainsString('Unduh Excel', $html);
    }

    /**
     * 2. Test Admin Aset dapat menghasilkan keempat jenis laporan.
     */
    public function test_admin_aset_dapat_menghasilkan_keempat_jenis_laporan_excel(): void
    {
        $jenisList = [
            'seluruh' => 'REKAPITULASI SELURUH ASET',
            'per_skpd' => 'REKAPITULASI ASET PER SKPD PEMEGANG',
            'per_pemegang' => 'REKAPITULASI ASET PER PEMEGANG',
            'per_ruangan' => 'REKAPITULASI ASET PER RUANGAN',
        ];

        foreach ($jenisList as $jenis => $judulExpected) {
            $response = $this->actingAs($this->adminAset)
                ->get(route('monitoring-aset.export', [
                    'jenis_laporan' => $jenis,
                    'periode' => '2025–2028',
                ]));

            $response->assertOk();
            $spreadsheet = $this->loadSpreadsheetFromContent($response->streamedContent());
            $sheet = $spreadsheet->getActiveSheet();

            $this->assertSame($judulExpected, $sheet->getCell('A1')->getValue());
            $this->assertStringContainsString('TOTAL KESELURUHAN', $this->teksSheet($spreadsheet, 0));
        }
    }

    /**
     * 3a. Test hak akses: Role tidak berhak (Pegawai biasa) mendapatkan 403 Forbidden.
     */
    public function test_hak_akses_role_tidak_berhak_mendapatkan_403(): void
    {
        $this->actingAs($this->pegawaiBiasa)
            ->get(route('monitoring-aset.export'))
            ->assertForbidden();

        $this->actingAs($this->pegawaiBiasa)
            ->get(route('monitoring-aset.export-per-pengguna'))
            ->assertForbidden();
    }

    /**
     * 3b. Test hak akses: Admin UPT P2BTP hanya mendapatkan aset di lingkup P2BTP.
     */
    public function test_hak_akses_admin_upt_p2btp_hanya_melihat_aset_p2btp(): void
    {
        $barang = MasterBarang::first() ?? MasterBarang::create(['kode_barang' => 'BRG' . uniqid(), 'nama_barang' => 'Barang Uji']);
        $lokasiP2btp = Lokasi::where('id_skpd', $this->skpdP2btp->id_skpd)->firstOrFail();
        $lokasiPsbp = Lokasi::where('id_skpd', $this->skpdPsbp->id_skpd)->firstOrFail();

        $kartuP2btp = 'KARTU-P2-' . uniqid();
        $kartuPsbp = 'KARTU-PS-' . uniqid();

        Aset::create([
            'id_barang' => $barang->id_barang,
            'id_skpd' => $this->skpdP2btp->id_skpd,
            'id_lokasi' => $lokasiP2btp->id_lokasi,
            'nomor_kartu_barang' => $kartuP2btp,
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
        ]);

        Aset::create([
            'id_barang' => $barang->id_barang,
            'id_skpd' => $this->skpdPsbp->id_skpd,
            'id_lokasi' => $lokasiPsbp->id_lokasi,
            'nomor_kartu_barang' => $kartuPsbp,
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
        ]);

        $resP2 = $this->actingAs($this->adminUptP2btp)
            ->get(route('monitoring-aset.export', ['jenis_laporan' => 'seluruh']));
        $resP2->assertOk();
        $teksP2 = $this->teksSheet($this->loadSpreadsheetFromContent($resP2->streamedContent()));
        $this->assertStringContainsString($kartuP2btp, $teksP2);
        $this->assertStringNotContainsString($kartuPsbp, $teksP2);
    }

    /**
     * 3c. Test hak akses: Admin UPT PSBP hanya mendapatkan aset di lingkup PSBP.
     */
    public function test_hak_akses_admin_upt_psbp_hanya_melihat_aset_psbp(): void
    {
        $barang = MasterBarang::first() ?? MasterBarang::create(['kode_barang' => 'BRG' . uniqid(), 'nama_barang' => 'Barang Uji']);
        $lokasiP2btp = Lokasi::where('id_skpd', $this->skpdP2btp->id_skpd)->firstOrFail();
        $lokasiPsbp = Lokasi::where('id_skpd', $this->skpdPsbp->id_skpd)->firstOrFail();

        $kartuP2btp = 'KARTU-P2-' . uniqid();
        $kartuPsbp = 'KARTU-PS-' . uniqid();

        Aset::create([
            'id_barang' => $barang->id_barang,
            'id_skpd' => $this->skpdP2btp->id_skpd,
            'id_lokasi' => $lokasiP2btp->id_lokasi,
            'nomor_kartu_barang' => $kartuP2btp,
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
        ]);

        Aset::create([
            'id_barang' => $barang->id_barang,
            'id_skpd' => $this->skpdPsbp->id_skpd,
            'id_lokasi' => $lokasiPsbp->id_lokasi,
            'nomor_kartu_barang' => $kartuPsbp,
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
        ]);

        $resPs = $this->actingAs($this->adminUptPsbp)
            ->get(route('monitoring-aset.export', ['jenis_laporan' => 'seluruh']));
        $resPs->assertOk();
        $teksPs = $this->teksSheet($this->loadSpreadsheetFromContent($resPs->streamedContent()));
        $this->assertStringContainsString($kartuPsbp, $teksPs);
        $this->assertStringNotContainsString($kartuP2btp, $teksPs);
    }

    /**
     * 4. Test filter periode sensus historis diterapkan dan hasil sensus kosong tampil "Belum Disensus".
     */
    public function test_filter_periode_sensus_historis_diterapkan_secara_konsisten(): void
    {
        $barang = MasterBarang::first() ?? MasterBarang::create(['kode_barang' => 'BRG' . uniqid(), 'nama_barang' => 'Barang Uji']);
        $kartu = 'SENSUS-HIST-' . uniqid();

        $aset = Aset::create([
            'id_barang' => $barang->id_barang,
            'id_skpd' => $this->skpdDinas->id_skpd,
            'nomor_kartu_barang' => $kartu,
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
        ]);

        // Buat hasil sensus pada periode lampau 2022–2025 saja
        SensusAset::create([
            'id_aset' => $aset->id_aset,
            'periode' => '2022–2025',
            'hasil_sensus' => 'Rusak Ringan',
            'tanggal_sensus' => '2024-05-15',
            'id_user' => $this->adminAset->id_user,
        ]);

        // Ekspor dengan filter periode 2022–2025 harus menampilkan "Rusak Ringan"
        $resLama = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.export', [
                'jenis_laporan' => 'seluruh',
                'periode' => '2022–2025',
                'search' => $kartu,
            ]));
        $resLama->assertOk();
        $teksLama = $this->teksSheet($this->loadSpreadsheetFromContent($resLama->streamedContent()));
        $this->assertStringContainsString('Rusak Ringan', $teksLama);

        // Ekspor dengan filter periode aktif 2025–2028 harus menampilkan "Belum Disensus"
        $resAktif = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.export', [
                'jenis_laporan' => 'seluruh',
                'periode' => '2025–2028',
                'search' => $kartu,
            ]));
        $resAktif->assertOk();
        $teksAktif = $this->teksSheet($this->loadSpreadsheetFromContent($resAktif->streamedContent()));
        $this->assertStringContainsString('Belum Disensus', $teksAktif);
    }

    /**
     * 5. Test aturan pemegang ganda:
     * - Dikecualikan dari laporan per SKPD dan per Pemegang
     * - Menghasilkan sheet kedua "Anomali Pemegang"
     * - Tetap masuk Rekap Seluruh Aset dan Rekap per Ruangan (jika penempatan aktif).
     */
    public function test_penanganan_aset_pemegang_ganda(): void
    {
        $barang = MasterBarang::first() ?? MasterBarang::create(['kode_barang' => 'BRG' . uniqid(), 'nama_barang' => 'Barang Uji']);
        $ruangan = Ruangan::first() ?? Ruangan::create(['nama_ruangan' => 'Ruang Uji', 'id_skpd' => $this->skpdDinas->id_skpd]);

        $kartuGanda = 'GANDA-' . uniqid();
        $merkGanda = 'MERKGANDA-' . uniqid();

        $aset = Aset::create([
            'id_barang' => $barang->id_barang,
            'id_skpd' => $this->skpdDinas->id_skpd,
            'nomor_kartu_barang' => $kartuGanda,
            'merk' => $merkGanda,
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
        ]);

        // Tempatkan di ruangan aktif
        PenempatanAset::create([
            'id_aset' => $aset->id_aset,
            'id_ruangan' => $ruangan->id_ruangan,
            'status' => 'aktif',
            'tanggal_mulai' => now()->toDateString(),
        ]);

        // Buat 2 pemegang aktif
        $pegawais = Pegawai::take(2)->get();
        PemegangAset::create([
            'id_aset' => $aset->id_aset,
            'id_pegawai' => $pegawais[0]->id_pegawai,
            'status' => 'aktif',
            'tanggal_mulai' => now()->toDateString(),
        ]);
        PemegangAset::create([
            'id_aset' => $aset->id_aset,
            'id_pegawai' => $pegawais[1]->id_pegawai,
            'status' => 'aktif',
            'tanggal_mulai' => now()->toDateString(),
        ]);

        // Laporan per SKPD: Aset TIDAK masuk baris data sheet utama, tapi masuk sheet Anomali Pemegang
        $resSkpd = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.export', [
                'jenis_laporan' => 'per_skpd',
                'search' => $kartuGanda,
            ]));
        $resSkpd->assertOk();
        $spreadsheetSkpd = $this->loadSpreadsheetFromContent($resSkpd->streamedContent());
        $this->assertSame(2, $spreadsheetSkpd->getSheetCount());
        $this->assertNotNull($spreadsheetSkpd->getSheetByName('Anomali Pemegang'));
        $this->assertStringNotContainsString($merkGanda, $this->teksDataRows($spreadsheetSkpd, 0));
        $this->assertStringContainsString($kartuGanda, $this->teksSheet($spreadsheetSkpd, 1));

        // Laporan per Pemegang: Aset TIDAK masuk baris data sheet utama, tapi masuk sheet Anomali Pemegang
        $resPemegang = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.export', [
                'jenis_laporan' => 'per_pemegang',
                'search' => $kartuGanda,
            ]));
        $resPemegang->assertOk();
        $spreadsheetPemegang = $this->loadSpreadsheetFromContent($resPemegang->streamedContent());
        $this->assertSame(2, $spreadsheetPemegang->getSheetCount());
        $this->assertStringNotContainsString($merkGanda, $this->teksDataRows($spreadsheetPemegang, 0));
        $this->assertStringContainsString($kartuGanda, $this->teksSheet($spreadsheetPemegang, 1));

        // Rekap Seluruh Aset: Aset TETAP masuk
        $resSeluruh = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.export', [
                'jenis_laporan' => 'seluruh',
                'search' => $kartuGanda,
            ]));
        $resSeluruh->assertOk();
        $teksSeluruh = $this->teksSheet($this->loadSpreadsheetFromContent($resSeluruh->streamedContent()));
        $this->assertStringContainsString($kartuGanda, $teksSeluruh);
        $this->assertStringContainsString('Pemegang Ganda (2)', $teksSeluruh);

        // Rekap per Ruangan: Aset TETAP masuk karena penempatan ruangannya aktif
        $resRuangan = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.export', [
                'jenis_laporan' => 'per_ruangan',
                'search' => $kartuGanda,
            ]));
        $resRuangan->assertOk();
        $teksRuangan = $this->teksDataRows($this->loadSpreadsheetFromContent($resRuangan->streamedContent()));
        $this->assertStringContainsString($merkGanda, $teksRuangan);
        $this->assertStringContainsString($pegawais[0]->nama_pegawai, $teksRuangan);
    }

    /**
     * 6. Test aset tanpa pemegang dan tanpa ruangan hanya muncul pada Rekap Seluruh Aset.
     */
    public function test_aset_tanpa_pemegang_dan_tanpa_ruangan_hanya_muncul_pada_rekap_seluruh(): void
    {
        $barang = MasterBarang::first() ?? MasterBarang::create(['kode_barang' => 'BRG' . uniqid(), 'nama_barang' => 'Barang Uji']);
        $kartuPolos = 'POLOS-' . uniqid();
        $merkPolos = 'MERKPOLOS-' . uniqid();

        Aset::create([
            'id_barang' => $barang->id_barang,
            'id_skpd' => $this->skpdDinas->id_skpd,
            'nomor_kartu_barang' => $kartuPolos,
            'merk' => $merkPolos,
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
        ]);

        // Rekap Seluruh Aset: Muncul di baris data
        $resSeluruh = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.export', ['jenis_laporan' => 'seluruh', 'search' => $kartuPolos]));
        $resSeluruh->assertOk();
        $this->assertStringContainsString($kartuPolos, $this->teksDataRows($this->loadSpreadsheetFromContent($resSeluruh->streamedContent())));

        // Rekap per SKPD: Tidak muncul di baris data
        $resSkpd = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.export', ['jenis_laporan' => 'per_skpd', 'search' => $kartuPolos]));
        $resSkpd->assertOk();
        $this->assertStringNotContainsString($merkPolos, $this->teksDataRows($this->loadSpreadsheetFromContent($resSkpd->streamedContent())));

        // Rekap per Pemegang: Tidak muncul di baris data
        $resPemegang = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.export', ['jenis_laporan' => 'per_pemegang', 'search' => $kartuPolos]));
        $resPemegang->assertOk();
        $this->assertStringNotContainsString($merkPolos, $this->teksDataRows($this->loadSpreadsheetFromContent($resPemegang->streamedContent())));

        // Rekap per Ruangan: Tidak muncul di baris data
        $resRuangan = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.export', ['jenis_laporan' => 'per_ruangan', 'search' => $kartuPolos]));
        $resRuangan->assertOk();
        $this->assertStringNotContainsString($merkPolos, $this->teksDataRows($this->loadSpreadsheetFromContent($resRuangan->streamedContent())));
    }

    /**
     * 7. Test efisiensi query ekspor (tidak ada N+1 query).
     */
    public function test_kueri_ekspor_efisien_dan_bebas_dari_n_plus_1(): void
    {
        DB::enableQueryLog();
        DB::flushQueryLog();

        $response = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.export', [
                'jenis_laporan' => 'seluruh',
                'periode' => '2025–2028',
            ]));

        $response->assertOk();
        $queryCount = count(DB::getQueryLog());

        // Untuk mengekspor seluruh aset (~280 baris), jumlah kueri harus di bawah 25
        $this->assertLessThanOrEqual(25, $queryCount, "Ekspor memicu {$queryCount} kueri database, terindikasi N+1 query.");
    }

    /**
     * 8. Test verifikasi elemen filter pada halaman Monitoring Aset:
     * - Dropdown "Rekap Aset Umum" dan "Semua Penempatan" sudah tidak ada pada form filter.
     * - Kolom pencarian, periode sensus, tahun, jenis aset, status, dan tombol Filter tetap ada.
     * - Tombol dan modal Export Data tetap ada.
     */
    public function test_filter_monitoring_aset_hanya_menampilkan_elemen_yang_ditentukan(): void
    {
        $response = $this->actingAs($this->adminAset)->get(route('monitoring-aset.index'));
        $response->assertOk();

        // Dropdown yang dihapus TIDAK boleh ada lagi di form filter
        $response->assertDontSee('<select name="bentuk_rekap"', false);
        $response->assertDontSee('Rekap Aset Umum');
        $response->assertDontSee('<select name="penempatan"', false);
        $response->assertDontSee('Semua Penempatan');

        // Elemen yang harus tetap dipertahankan
        $response->assertSee('<input type="text" name="search"', false);
        $response->assertSee('<select name="periode"', false);
        $response->assertSee('<select name="tahun"', false);
        $response->assertSee('<select name="jenis_aset"', false);
        $response->assertSee('<select name="status"', false);
        $response->assertSee('type="submit"', false);
        $response->assertSee('Filter');

        // Tombol Export Data & Modal tetap utuh
        $response->assertSee('Export Data');
        $response->assertSee('openExportModal()', false);
        $response->assertSee('1. Rekap Seluruh Aset');
        $response->assertSee('2. Rekap Aset per SKPD Pemegang');
        $response->assertSee('3. Rekap Aset per Pemegang');
        $response->assertSee('4. Rekap Aset per Ruangan');
    }

    /**
     * 9. Test filter yang tersisa tetap berfungsi normal saat dieksekusi.
     */
    public function test_seluruh_filter_yang_tersisa_tetap_berfungsi(): void
    {
        // Filter Search
        $resSearch = $this->actingAs($this->adminAset)->get(route('monitoring-aset.index', ['search' => 'Laptop']));
        $resSearch->assertOk();
        $resSearch->assertSee('Reset');

        // Filter Tahun
        $resTahun = $this->actingAs($this->adminAset)->get(route('monitoring-aset.index', ['tahun' => 2024]));
        $resTahun->assertOk();

        // Filter Jenis Aset
        $resJenis = $this->actingAs($this->adminAset)->get(route('monitoring-aset.index', ['jenis_aset' => 'barang']));
        $resJenis->assertOk();

        // Filter Status
        $resStatus = $this->actingAs($this->adminAset)->get(route('monitoring-aset.index', ['status' => 'habis']));
        $resStatus->assertOk();

        // Filter Periode Sensus
        $resPeriode = $this->actingAs($this->adminAset)->get(route('monitoring-aset.index', ['periode' => '2025–2028']));
        $resPeriode->assertOk();
    }
}
