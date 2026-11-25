<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\Lokasi;
use App\Models\Pegawai;
use App\Models\Role;
use App\Models\SensusAset;
use App\Models\Skpd;
use App\Models\User;
use App\Models\UsulanPenghapusan;
use App\Services\PeriodeSensus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pengujian fitur Sensus Aset:
 * - Kolom Hasil Sensus di sebelah Masa Pakai
 * - Label "Belum Disensus" & tombol "Tambah Sensus" pada periode aktif
 * - Penghapusan tombol "Detail" dan "Usulkan Hapus" dari Monitoring Aset
 * - Otorisasi AsetScope: Admin Aset penuh vs Admin UPT terbatas lokasi UPT
 * - Validasi input hasil sensus (Baik, Rusak Ringan, Rusak Berat) & siklus periode
 */
class SensusAsetTest extends TestCase
{
    private User $adminAset;

    private User $adminUptP2btp;

    private User $adminUptPsbp;

    private User $pegawaiBiasa;

    private Aset $asetP2btp;

    private Aset $asetPsbp;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $this->namaDatabase(),
        ]);

        DB::beginTransaction();

        Carbon::setTestNow(Carbon::parse('2026-10-28 10:00:00'));

        $this->siapkanDataUji();
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

    private function userDenganRole(string $role): User
    {
        return $this->ambilAtauBuatUserRole($role);
    }

    private function adminUptP2btp(): User
    {
        return User::whereHas('role', fn ($q) => $q->where('nama_role', 'Admin UPT'))
            ->whereHas('pegawai.skpd', fn ($q) => $q->where('nama_skpd', 'UPT P2BTP'))
            ->firstOrFail();
    }

    private function adminUptPsbp(): User
    {
        $skpd = Skpd::where('nama_skpd', 'UPT PSBP')->firstOrFail();
        $pegawai = Pegawai::create([
            'nip' => 'TST' . uniqid(),
            'nama_pegawai' => 'Admin UPT PSBP Test',
            'id_skpd' => $skpd->id_skpd,
        ]);

        return User::create([
            'id_pegawai' => $pegawai->id_pegawai,
            'id_role' => Role::where('nama_role', 'Admin UPT')->firstOrFail()->id_role,
            'username' => 'tst_upt_psbp_' . uniqid(),
            'password' => 'rahasia',
            'status_user' => 'aktif',
        ]);
    }

    private function lokasiUpt(string $namaSkpd): Lokasi
    {
        $skpd = Skpd::where('nama_skpd', $namaSkpd)->firstOrFail();

        return Lokasi::where('id_skpd', $skpd->id_skpd)->firstOrFail();
    }

    private function siapkanDataUji(): void
    {
        $this->adminAset = $this->userDenganRole('Admin Aset');
        $this->adminUptP2btp = $this->adminUptP2btp();
        $this->adminUptPsbp = $this->adminUptPsbp();
        $this->pegawaiBiasa = $this->userDenganRole('Pegawai');

        $lokasiP2 = $this->lokasiUpt('UPT P2BTP');
        $lokasiPs = $this->lokasiUpt('UPT PSBP');

        $this->asetP2btp = Aset::create([
            'id_lokasi' => $lokasiP2->id_lokasi,
            'nomor_kartu_barang' => 'TEST-SENSUS-P2BTP-' . uniqid(),
            'merk' => 'Laptop P2BTP',
            'tanggal_pengadaan' => '2025-03-01',
            'nilai_perolehan' => 15000000,
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ]);

        $this->asetPsbp = Aset::create([
            'id_lokasi' => $lokasiPs->id_lokasi,
            'nomor_kartu_barang' => 'TEST-SENSUS-PSBP-' . uniqid(),
            'merk' => 'Mesin PSBP',
            'tanggal_pengadaan' => '2025-05-01',
            'nilai_perolehan' => 25000000,
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ]);
    }

    public function test_monitoring_aset_menampilkan_kolom_hasil_sensus_dan_tombol_tambah(): void
    {
        $response = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.index'));

        $response->assertOk()
            ->assertSee('Hasil Sensus')
            ->assertSee('Belum Disensus')
            ->assertSee('Tambah Sensus')
            ->assertSee('Riwayat')
            // Tombol Detail dan Usulan Hapus telah dihapus dari tabel Monitoring Aset
            ->assertDontSee('Usulkan Hapus');
    }

    public function test_tombol_detail_dan_usulan_hapus_tidak_tampil_di_monitoring_aset(): void
    {
        $response = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.index'));

        $response->assertOk();
        $content = $response->getContent();

        // Tidak ada tombol aksi Usulkan Hapus di dalam tabel
        $this->assertStringNotContainsString('onclick="openModalUsulanMonitoring', $content);
        $this->assertStringNotContainsString('route(\'penghapusan.store\')', $content);
    }

    public function test_admin_aset_dapat_mengakses_halaman_sensus_semua_aset(): void
    {
        // Akses aset di UPT P2BTP
        $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.sensus.edit', $this->asetP2btp))
            ->assertOk()
            ->assertSee($this->asetP2btp->nomor_kartu_barang)
            ->assertSee('Periode ' . PeriodeSensus::periodeAktif(null, $this->asetP2btp));

        // Akses aset di UPT PSBP
        $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.sensus.edit', $this->asetPsbp))
            ->assertOk()
            ->assertSee($this->asetPsbp->nomor_kartu_barang);
    }

    public function test_admin_upt_hanya_dapat_mengakses_sensus_aset_dalam_lingkupnya(): void
    {
        // Admin UPT P2BTP boleh mengakses aset P2BTP
        $this->actingAs($this->adminUptP2btp)
            ->get(route('monitoring-aset.sensus.edit', $this->asetP2btp))
            ->assertOk()
            ->assertSee($this->asetP2btp->nomor_kartu_barang);

        // Admin UPT P2BTP DITOLAK saat mengakses aset UPT PSBP (di luar lingkup)
        $this->actingAs($this->adminUptP2btp)
            ->get(route('monitoring-aset.sensus.edit', $this->asetPsbp))
            ->assertForbidden();
    }

    public function test_pegawai_biasa_ditolak_mengakses_sensus(): void
    {
        $this->actingAs($this->pegawaiBiasa)
            ->get(route('monitoring-aset.sensus.edit', $this->asetP2btp))
            ->assertForbidden();
    }

    public function test_admin_upt_hanya_dapat_melihat_riwayat_aset_dalam_lingkupnya(): void
    {
        // Boleh lihat riwayat aset sendiri
        $this->actingAs($this->adminUptP2btp)
            ->get(route('monitoring-aset.sensus.riwayat', $this->asetP2btp))
            ->assertOk()
            ->assertSee('Daftar Riwayat Sensus per Periode');

        // Ditolak lihat riwayat aset UPT lain
        $this->actingAs($this->adminUptP2btp)
            ->get(route('monitoring-aset.sensus.riwayat', $this->asetPsbp))
            ->assertForbidden();
    }

    public function test_validasi_input_hasil_sensus_menolak_nilai_tidak_valid(): void
    {
        $res = $this->actingAs($this->adminAset)
            ->post(route('monitoring-aset.sensus.update', $this->asetP2btp), [
                'periode' => '2025–2028',
                'hasil_sensus' => 'Kondisi Tidak Dikenal',
                'tanggal_sensus' => '2026-10-28',
            ]);

        $res->assertSessionHasErrors('hasil_sensus');
    }

    public function test_validasi_input_periode_menolak_siklus_tidak_sesuai(): void
    {
        // Bukan siklus 3 tahunan dari 2025 (mis. 2026–2029)
        $res = $this->actingAs($this->adminAset)
            ->post(route('monitoring-aset.sensus.update', $this->asetP2btp), [
                'periode' => '2026–2029',
                'hasil_sensus' => 'Baik',
                'tanggal_sensus' => '2026-10-28',
            ]);

        $res->assertSessionHasErrors('periode');
    }

    public function test_validasi_input_periode_masa_depan_ditolak_sebelum_waktunya(): void
    {
        // Periode masa depan (2028–2031) belum boleh diisi pada tahun 2026
        $res = $this->actingAs($this->adminAset)
            ->post(route('monitoring-aset.sensus.update', $this->asetP2btp), [
                'periode' => '2028–2031',
                'hasil_sensus' => 'Baik',
                'tanggal_sensus' => '2026-10-28',
            ]);

        $res->assertSessionHasErrors('periode');
    }

    public function test_ekspor_monitoring_dan_filter_periode_berjalan_tanpa_tabel_sensus(): void
    {
        $res = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.export', ['periode' => '2025–2028']));

        $res->assertOk();
        $this->assertStringContainsString('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', (string) $res->headers->get('content-type'));
    }

    public function test_modal_sensus_ditempatkan_pada_layer_modals_dengan_struktur_dua_kolom(): void
    {
        $response = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.index'));

        $response->assertOk();
        $html = $response->getContent();

        // 1. Modal ada di layer @stack('modals') di luar </main>
        $this->assertStringContainsString('id="sensus-modal"', $html);
        $mainClosePos = strpos($html, '</main>');
        $modalPos = strpos($html, 'id="sensus-modal"');
        $this->assertNotFalse($mainClosePos);
        $this->assertNotFalse($modalPos);
        $this->assertGreaterThan($mainClosePos, $modalPos, 'Modal sensus harus berada di luar </main> agar tidak tertutup stacking context sidebar.');

        // 2. Dimensi lebar desktop max-w-[850px], responsif w-[calc(100vw-32px)], dan proporsi 2 kolom 35% : 65%
        $this->assertStringContainsString('max-w-[850px]', $html);
        $this->assertStringContainsString('w-[calc(100vw-32px)]', $html);
        $this->assertStringContainsString('md:grid-cols-[0.35fr_0.65fr]', $html);
        $this->assertStringContainsString('sensus-two-col-grid', $html);

        // 3. Kolom kiri memuat 5 data aset ringkas
        $this->assertStringContainsString('id="sensus-m-nama"', $html);
        $this->assertStringContainsString('id="sensus-m-kode"', $html);
        $this->assertStringContainsString('id="sensus-m-pemegang"', $html);
        $this->assertStringContainsString('id="sensus-m-lokasi"', $html);
        $this->assertStringContainsString('id="sensus-m-tgl-pengadaan"', $html);

        // 4. Kolom kanan memuat judul, periode, tanggal, 3 radio pill, dan catatan
        $this->assertStringContainsString('Formulir Hasil Sensus', $html);
        $this->assertStringContainsString('Periode Sensus', $html);
        $this->assertStringContainsString('Tanggal Pelaksanaan Sensus', $html);
        $this->assertStringContainsString('value="Baik"', $html);
        $this->assertStringContainsString('value="Rusak Ringan"', $html);
        $this->assertStringContainsString('value="Rusak Berat"', $html);
        $this->assertStringContainsString('Catatan Temuan Fisik (Opsional)', $html);

        // 5. Footer memuat tombol Batal dan Simpan Hasil Sensus
        $this->assertStringContainsString('data-sensus-close', $html);
        $this->assertStringContainsString('Simpan Hasil Sensus', $html);
    }

    public function test_tambah_sensus_berhasil_menyimpan_data_dan_mencatat_activity_log(): void
    {
        $response = $this->actingAs($this->adminAset)
            ->post(route('monitoring-aset.sensus.update', $this->asetP2btp), [
                'periode' => '2025–2028',
                'hasil_sensus' => 'Baik',
                'tanggal_sensus' => '2026-10-28',
                'catatan' => 'Pemeriksaan fisik lengkap dan berfungsi baik',
                'redirect_to' => 'monitoring',
            ]);

        $response->assertRedirect(route('monitoring-aset.index', ['periode' => '2025–2028']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('sensus_aset', [
            'id_aset' => $this->asetP2btp->id_aset,
            'periode' => '2025–2028',
            'hasil_sensus' => 'Baik',
            'tanggal_sensus' => '2026-10-28',
            'catatan' => 'Pemeriksaan fisik lengkap dan berfungsi baik',
            'id_user' => $this->adminAset->id_user,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'log_type' => 'sensus_aset.created',
            'id_user' => $this->adminAset->id_user,
        ]);
    }

    public function test_edit_sensus_pada_aset_dan_periode_sama_memperbarui_catatan_tanpa_duplikasi(): void
    {
        // 1. Simpan pertama kali (Tambah)
        $this->actingAs($this->adminAset)
            ->post(route('monitoring-aset.sensus.update', $this->asetP2btp), [
                'periode' => '2025–2028',
                'hasil_sensus' => 'Baik',
                'tanggal_sensus' => '2026-10-28',
                'catatan' => 'Kondisi awal',
            ]);

        $this->assertSame(1, SensusAset::where('id_aset', $this->asetP2btp->id_aset)->where('periode', '2025–2028')->count());

        // 2. Simpan kedua kali pada periode yang sama (Edit)
        $response = $this->actingAs($this->adminAset)
            ->post(route('monitoring-aset.sensus.update', $this->asetP2btp), [
                'periode' => '2025–2028',
                'hasil_sensus' => 'Rusak Ringan',
                'tanggal_sensus' => '2026-10-29',
                'catatan' => 'Keyboard sebagian tombol macet',
            ]);

        $response->assertRedirect(route('monitoring-aset.sensus.edit', ['aset' => $this->asetP2btp->id_aset, 'periode' => '2025–2028']))
            ->assertSessionHas('success');

        // Pastikan tidak ada duplikat: tetap hanya 1 catatan untuk aset dan periode tersebut
        $this->assertSame(1, SensusAset::where('id_aset', $this->asetP2btp->id_aset)->where('periode', '2025–2028')->count());

        $this->assertDatabaseHas('sensus_aset', [
            'id_aset' => $this->asetP2btp->id_aset,
            'periode' => '2025–2028',
            'hasil_sensus' => 'Rusak Ringan',
            'tanggal_sensus' => '2026-10-29',
            'catatan' => 'Keyboard sebagian tombol macet',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'log_type' => 'sensus_aset.updated',
            'id_user' => $this->adminAset->id_user,
        ]);
    }

    public function test_admin_upt_dapat_menyimpan_sensus_aset_dalam_lingkupnya_dan_ditolak_untuk_upt_lain(): void
    {
        // Admin UPT P2BTP berhasil menyimpan sensus untuk aset P2BTP miliknya
        $resSukses = $this->actingAs($this->adminUptP2btp)
            ->post(route('monitoring-aset.sensus.update', $this->asetP2btp), [
                'periode' => '2025–2028',
                'hasil_sensus' => 'Baik',
                'tanggal_sensus' => '2026-10-28',
                'redirect_to' => 'monitoring',
            ]);

        $resSukses->assertRedirect(route('monitoring-aset.index', ['periode' => '2025–2028']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('sensus_aset', [
            'id_aset' => $this->asetP2btp->id_aset,
            'periode' => '2025–2028',
            'hasil_sensus' => 'Baik',
        ]);

        // Admin UPT P2BTP DITOLAK saat mencoba menyimpan sensus untuk aset UPT PSBP
        $resDitolak = $this->actingAs($this->adminUptP2btp)
            ->post(route('monitoring-aset.sensus.update', $this->asetPsbp), [
                'periode' => '2025–2028',
                'hasil_sensus' => 'Baik',
                'tanggal_sensus' => '2026-10-28',
            ]);

        $resDitolak->assertForbidden();

        $this->assertDatabaseMissing('sensus_aset', [
            'id_aset' => $this->asetPsbp->id_aset,
            'periode' => '2025–2028',
        ]);
    }

    public function test_riwayat_sensus_menampilkan_nama_lengkap_petugas_bukan_username(): void
    {
        $namaLengkap = $this->adminAset->pegawai?->nama_pegawai ?? 'Achmar Aset';
        $usernameAkun = $this->adminAset->username;

        SensusAset::create([
            'id_aset' => $this->asetP2btp->id_aset,
            'periode' => '2025–2028',
            'hasil_sensus' => 'Baik',
            'tanggal_sensus' => '2026-10-28',
            'catatan' => 'Uji nama petugas',
            'id_user' => $this->adminAset->id_user,
        ]);

        $response = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.sensus.riwayat', $this->asetP2btp));

        $response->assertOk();
        $html = $response->getContent();

        // 1. Nama lengkap petugas harus muncul di halaman riwayat
        $this->assertStringContainsString($namaLengkap, $html);

        // 2. Kolom Petugas Pencatat tidak boleh menampilkan username/identitas akun
        $this->assertStringNotContainsString(
            "<td class=\"py-3 px-3 text-slate-700 font-medium\">\n                                {$usernameAkun}\n                            </td>",
            $html
        );

        // 3. Method model namaPetugas() mengembalikan nama lengkap pegawai
        $sensus = SensusAset::where('id_aset', $this->asetP2btp->id_aset)->where('periode', '2025–2028')->firstOrFail();
        $this->assertSame($namaLengkap, $sensus->namaPetugas());
    }

    public function test_riwayat_sensus_menampilkan_strip_jika_petugas_tidak_memiliki_data_pegawai_atau_null(): void
    {
        $sensusNullUser = SensusAset::create([
            'id_aset' => $this->asetP2btp->id_aset,
            'periode' => '2022–2025',
            'hasil_sensus' => 'Baik',
            'tanggal_sensus' => '2024-05-10',
            'id_user' => null,
        ]);

        $this->assertSame('-', $sensusNullUser->namaPetugas());

        $response = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.sensus.riwayat', $this->asetP2btp));

        $response->assertOk();
    }

    public function test_tombol_tambah_dan_edit_sensus_berubah_dinamis_sesuai_periode_yang_dipilih(): void
    {
        // 1. Rekam sensus pada periode historis 2022–2025 saja
        SensusAset::create([
            'id_aset' => $this->asetP2btp->id_aset,
            'periode' => '2022–2025',
            'hasil_sensus' => 'Baik',
            'tanggal_sensus' => '2023-06-15',
            'id_user' => $this->adminAset->id_user,
        ]);

        // Saat filter periode aktif (2025–2028), tombol harus "Tambah Sensus" dan status "Belum Disensus"
        $resAktif = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.index', [
                'periode' => '2025–2028',
                'search' => $this->asetP2btp->nomor_kartu_barang,
            ]));

        $resAktif->assertOk();
        $htmlAktif = $resAktif->getContent();
        $this->assertStringContainsString('Tambah Sensus', $htmlAktif);
        $this->assertStringContainsString('Belum Disensus', $htmlAktif);
        $this->assertStringContainsString('data-sudah-disensus="0"', $htmlAktif);

        // Saat filter beralih ke periode lama (2022–2025), kolom Hasil Sensus menampilkan data historis "Baik",
        // sementara tombol aksi tetap mengarah ke periode aktif aset (tidak terkunci sembarangan karena dropdown)
        $resLama = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.index', [
                'periode' => '2022–2025',
                'search' => $this->asetP2btp->nomor_kartu_barang,
            ]));

        $resLama->assertOk();
        $htmlLama = $resLama->getContent();
        $this->assertStringContainsString('Baik', $htmlLama);
        $this->assertStringContainsString('Tambah Sensus', $htmlLama);

        // 2. Tambah sensus pada periode baru 2025–2027 (periode aktif)
        $periodeAktifAset = PeriodeSensus::periodeAktif(null, $this->asetP2btp);
        $this->actingAs($this->adminAset)
            ->post(route('monitoring-aset.sensus.update', $this->asetP2btp), [
                'periode' => $periodeAktifAset,
                'hasil_sensus' => 'Rusak Ringan',
                'tanggal_sensus' => '2026-10-28',
            ]);

        // Pastikan catatan periode lama dan baru tersimpan terpisah (tidak saling menimpa)
        $this->assertSame(2, SensusAset::where('id_aset', $this->asetP2btp->id_aset)->count());
        $this->assertDatabaseHas('sensus_aset', ['id_aset' => $this->asetP2btp->id_aset, 'periode' => '2022–2025', 'hasil_sensus' => 'Baik']);
        $this->assertDatabaseHas('sensus_aset', ['id_aset' => $this->asetP2btp->id_aset, 'periode' => $periodeAktifAset, 'hasil_sensus' => 'Rusak Ringan']);

        // Sekarang pada filter periode aktif, tombol dinamis berubah menjadi "Edit Sensus"
        $resAktif2 = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.index', [
                'periode' => $periodeAktifAset,
                'search' => $this->asetP2btp->nomor_kartu_barang,
            ]));

        $resAktif2->assertOk();
        $htmlAktif2 = $resAktif2->getContent();
        $this->assertStringContainsString('Edit Sensus', $htmlAktif2);
        $this->assertStringContainsString('Rusak Ringan', $htmlAktif2);
        $this->assertStringContainsString('data-sudah-disensus="1"', $htmlAktif2);
    }

    public function test_halaman_riwayat_sensus_tidak_menampilkan_tombol_input_edit_di_atas_dan_mempertahankan_tombol_kembali(): void
    {
        $response = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.sensus.riwayat', $this->asetP2btp));

        $response->assertOk();
        $html = $response->getContent();

        // 1. Tombol "Input / Edit Sensus" di bagian atas halaman telah dihapus
        $this->assertStringNotContainsString('Input / Edit Sensus', $html);

        // 2. Tombol "Kembali" tetap ada dan mengarah ke Monitoring Aset
        $this->assertStringContainsString('Kembali', $html);
        $this->assertStringContainsString(route('monitoring-aset.index'), $html);
    }

    public function test_halaman_riwayat_sensus_tidak_lagi_menampilkan_kolom_aksi_dan_tombol_edit(): void
    {
        // Buat dua riwayat sensus pada periode berbeda
        SensusAset::create([
            'id_aset' => $this->asetP2btp->id_aset,
            'periode' => '2022–2025',
            'hasil_sensus' => 'Baik',
            'tanggal_sensus' => '2023-05-10',
            'catatan' => 'Sensus periode lalu',
            'id_user' => $this->adminAset->id_user,
        ]);

        SensusAset::create([
            'id_aset' => $this->asetP2btp->id_aset,
            'periode' => '2025–2028',
            'hasil_sensus' => 'Rusak Ringan',
            'tanggal_sensus' => '2026-10-28',
            'catatan' => 'Sensus periode aktif',
            'id_user' => $this->adminAset->id_user,
        ]);

        $response = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.sensus.riwayat', $this->asetP2btp));

        $response->assertOk();
        $html = $response->getContent();

        // Verifikasi bahwa kolom aksi dan tombol edit TIDAK ada di halaman riwayat sensus
        $this->assertStringNotContainsString('<th class="py-3 px-3 text-center">Aksi</th>', $html);
        $this->assertStringNotContainsString(
            route('monitoring-aset.sensus.edit', ['aset' => $this->asetP2btp->id_aset, 'periode' => '2022–2025']),
            $html
        );
        $this->assertStringNotContainsString(
            route('monitoring-aset.sensus.edit', ['aset' => $this->asetP2btp->id_aset, 'periode' => '2025–2028']),
            $html
        );
    }

    public function test_hasil_sensus_rusak_berat_otomatis_mengubah_status_menjadi_diusulkan_hapus_dan_membuat_usulan(): void
    {
        $this->asetP2btp->update([
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
        ]);

        $jumlahUsulanAwal = UsulanPenghapusan::where('id_aset', $this->asetP2btp->id_aset)->count();

        $response = $this->actingAs($this->adminAset)
            ->post(route('monitoring-aset.sensus.update', $this->asetP2btp), [
                'periode' => '2025–2028',
                'hasil_sensus' => 'Rusak Berat',
                'tanggal_sensus' => '2026-10-28',
                'catatan' => 'Rusak total pada motherboard',
            ]);

        $response->assertSessionHas('success');
        $this->assertStringContainsStringIgnoringCase('otomatis masuk', (string) session('success'));
        $this->assertStringContainsStringIgnoringCase('usulan penghapusan', (string) session('success'));

        // Pastikan hasil sensus tersimpan di sensus_aset
        $this->assertDatabaseHas('sensus_aset', [
            'id_aset' => $this->asetP2btp->id_aset,
            'periode' => '2025–2028',
            'hasil_sensus' => 'Rusak Berat',
        ]);

        // Kondisi master aset diperbarui menjadi Rusak Berat dan status menjadi diusulkan_hapus
        $this->asetP2btp->refresh();
        $this->assertSame('Rusak Berat', $this->asetP2btp->kondisi, 'Kondisi master aset harus sinkron dan diperbarui sesuai hasil sensus.');
        $this->assertSame('diusulkan_hapus', $this->asetP2btp->status_aset, 'Status aset harus otomatis berubah menjadi diusulkan_hapus.');

        // Usulan penghapusan baru dibuat secara otomatis
        $this->assertDatabaseHas('usulan_penghapusan', [
            'id_aset' => $this->asetP2btp->id_aset,
            'status_usulan' => 'diajukan',
        ]);
        $this->assertSame($jumlahUsulanAwal + 1, UsulanPenghapusan::where('id_aset', $this->asetP2btp->id_aset)->count());
    }

    public function test_penyimpanan_ulang_sensus_rusak_berat_tidak_membuat_usulan_ganda(): void
    {
        $this->asetP2btp->update([
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
        ]);

        // Simpan sensus Rusak Berat pertama kali
        $this->actingAs($this->adminAset)
            ->post(route('monitoring-aset.sensus.update', $this->asetP2btp), [
                'periode' => '2025–2028',
                'hasil_sensus' => 'Rusak Berat',
                'tanggal_sensus' => '2026-10-28',
                'catatan' => 'Rusak total motherboard',
            ]);

        $this->assertSame(1, UsulanPenghapusan::where('id_aset', $this->asetP2btp->id_aset)->count());

        // Simpan sensus ulang dengan hasil yang sama
        $response = $this->actingAs($this->adminAset)
            ->post(route('monitoring-aset.sensus.update', $this->asetP2btp), [
                'periode' => '2025–2028',
                'hasil_sensus' => 'Rusak Berat',
                'tanggal_sensus' => '2026-10-29',
                'catatan' => 'Pembaruan catatan: Rusak parah tak tertolong',
            ]);

        $response->assertSessionHas('success');
        $this->assertSame(1, UsulanPenghapusan::where('id_aset', $this->asetP2btp->id_aset)->count(), 'Menyimpan ulang sensus Rusak Berat tidak boleh membuat usulan duplikat.');
    }

    public function test_hasil_sensus_selain_rusak_berat_tidak_membuat_usulan_otomatis(): void
    {
        $this->asetP2btp->update([
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
        ]);

        $response = $this->actingAs($this->adminAset)
            ->post(route('monitoring-aset.sensus.update', $this->asetP2btp), [
                'periode' => '2025–2028',
                'hasil_sensus' => 'Rusak Ringan',
                'tanggal_sensus' => '2026-10-28',
                'catatan' => 'Baret halus pada bodi',
            ]);

        $response->assertSessionHas('success');
        $this->asetP2btp->refresh();
        $this->assertSame('Rusak Ringan', $this->asetP2btp->kondisi);
        $this->assertSame('aktif', $this->asetP2btp->status_aset);
        $this->assertSame(0, UsulanPenghapusan::where('id_aset', $this->asetP2btp->id_aset)->count());
    }

    public function test_monitoring_aset_hanya_menampilkan_aset_aktif_dan_mengecualikan_diusulkan_hapus_dihapuskan_nonaktif(): void
    {
        $lokasiP2 = $this->lokasiUpt('UPT P2BTP');

        $asetAktif = Aset::create([
            'id_lokasi' => $lokasiP2->id_lokasi,
            'nomor_kartu_barang' => 'TEST-MONITORING-AKTIF-' . uniqid(),
            'merk' => 'Aset Monitor Aktif',
            'tanggal_pengadaan' => '2025-01-01',
            'status_aset' => 'aktif',
            'kondisi' => 'Baik',
        ]);

        $asetDiusulkan = Aset::create([
            'id_lokasi' => $lokasiP2->id_lokasi,
            'nomor_kartu_barang' => 'TEST-MONITORING-USUL-' . uniqid(),
            'merk' => 'Aset Monitor Usul Hapus',
            'tanggal_pengadaan' => '2025-01-01',
            'status_aset' => 'diusulkan_hapus',
            'kondisi' => 'Rusak Berat',
        ]);

        $asetDihapuskan = Aset::create([
            'id_lokasi' => $lokasiP2->id_lokasi,
            'nomor_kartu_barang' => 'TEST-MONITORING-HAPUS-' . uniqid(),
            'merk' => 'Aset Monitor Dihapuskan',
            'tanggal_pengadaan' => '2025-01-01',
            'status_aset' => 'dihapuskan',
            'kondisi' => 'Rusak Berat',
        ]);

        $asetNonaktif = Aset::create([
            'id_lokasi' => $lokasiP2->id_lokasi,
            'nomor_kartu_barang' => 'TEST-MONITORING-NONAKTIF-' . uniqid(),
            'merk' => 'Aset Monitor Nonaktif',
            'tanggal_pengadaan' => '2025-01-01',
            'status_aset' => 'nonaktif',
            'kondisi' => 'Baik',
        ]);

        $response = $this->actingAs($this->adminAset)->get(route('monitoring-aset.index'));
        $response->assertOk();

        $response->assertSee($asetAktif->nomor_kartu_barang);
        $response->assertDontSee($asetDiusulkan->nomor_kartu_barang);
        $response->assertDontSee($asetDihapuskan->nomor_kartu_barang);
        $response->assertDontSee($asetNonaktif->nomor_kartu_barang);
    }

    public function test_aset_yang_direaktifkan_muncul_kembali_di_monitoring_meskipun_tanpa_pemegang_dan_lokasi(): void
    {
        $skpdP2 = Skpd::where('nama_skpd', 'UPT P2BTP')->firstOrFail();

        $asetReaktif = Aset::create([
            'id_skpd' => $skpdP2->id_skpd,
            'id_lokasi' => null,
            'nomor_kartu_barang' => 'TEST-REAKTIF-NOLOKASI-' . uniqid(),
            'merk' => 'Laptop Reaktif Mandiri',
            'tanggal_pengadaan' => '2025-01-01',
            'status_aset' => 'aktif',
            'kondisi' => 'Baik',
        ]);

        $response = $this->actingAs($this->adminAset)->get(route('monitoring-aset.index'));
        $response->assertOk();
        $response->assertSee($asetReaktif->nomor_kartu_barang);
    }

    public function test_kpi_dan_filter_tahun_monitoring_tidak_menghitung_aset_yang_sedang_diusulkan(): void
    {
        $lokasiP2 = $this->lokasiUpt('UPT P2BTP');

        $responseAwal = $this->actingAs($this->adminAset)->get(route('monitoring-aset.index'));
        $totalNilaiBukuAwal = $responseAwal->viewData('totalNilaiBuku');

        $tahunUnik = 1993;
        $asetDiusulkan = Aset::create([
            'id_lokasi' => $lokasiP2->id_lokasi,
            'nomor_kartu_barang' => 'TEST-KPI-USUL-' . uniqid(),
            'merk' => 'Aset Usulan KPI',
            'tanggal_pengadaan' => "{$tahunUnik}-05-10",
            'nilai_perolehan' => 88888888,
            'status_aset' => 'diusulkan_hapus',
            'kondisi' => 'Rusak Berat',
        ]);

        $response = $this->actingAs($this->adminAset)->get(route('monitoring-aset.index'));
        $response->assertOk();

        // 1. Filter tahun tidak boleh menyertakan tahun aset yang sedang diusulkan
        $tahunList = $response->viewData('tahunList');
        $this->assertFalse($tahunList->contains($tahunUnik), 'Filter tahun tidak boleh menyertakan tahun dari aset diusulkan_hapus.');

        // 2. Total nilai buku tidak terpengaruh oleh aset yang berstatus diusulkan_hapus
        $this->assertSame($totalNilaiBukuAwal, $response->viewData('totalNilaiBuku'));

        // 3. Daftar aset yang ditampilkan tidak mengandung aset tersebut
        $asetList = $response->viewData('asetList');
        $this->assertFalse($asetList->getCollection()->pluck('nomor_kartu_barang')->contains($asetDiusulkan->nomor_kartu_barang));
    }

    public function test_admin_upt_dapat_menyimpan_sensus_pada_aset_tanpa_lokasi_jika_skpd_sesuai(): void
    {
        $skpdP2 = Skpd::where('nama_skpd', 'UPT P2BTP')->firstOrFail();

        // Buat aset milik UPT P2BTP tanpa lokasi fisik (id_lokasi = null)
        $asetTanpaLokasi = Aset::create([
            'id_skpd' => $skpdP2->id_skpd,
            'id_lokasi' => null,
            'nomor_kartu_barang' => 'TEST-UNPLACED-' . uniqid(),
            'tanggal_pengadaan' => '2025-01-01',
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ]);

        $response = $this->actingAs($this->adminUptP2btp)
            ->post(route('monitoring-aset.sensus.update', $asetTanpaLokasi), [
                'periode' => '2025–2027',
                'hasil_sensus' => 'Rusak Ringan',
                'tanggal_sensus' => '2026-10-28',
                'catatan' => 'Sensus aset unplaced sukses',
                'redirect_to' => 'monitoring',
            ]);

        $response->assertRedirect(route('monitoring-aset.index', ['periode' => '2025–2027']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('sensus_aset', [
            'id_aset' => $asetTanpaLokasi->id_aset,
            'periode' => '2025–2027',
            'hasil_sensus' => 'Rusak Ringan',
        ]);

        // Kondisi master aset juga terupdate
        $asetTanpaLokasi->refresh();
        $this->assertSame('Rusak Ringan', $asetTanpaLokasi->kondisi);

        // Admin UPT lain (PSBP) DITOLAK mengakses aset unplaced milik P2BTP
        $resDitolak = $this->actingAs($this->adminUptPsbp)
            ->post(route('monitoring-aset.sensus.update', $asetTanpaLokasi), [
                'periode' => '2025–2027',
                'hasil_sensus' => 'Baik',
                'tanggal_sensus' => '2026-10-28',
            ]);

        $resDitolak->assertForbidden();
    }

    public function test_skenario_1_tambah_sensus_pada_periode_berjalan_berhasil(): void
    {
        $response = $this->actingAs($this->adminAset)
            ->post(route('monitoring-aset.sensus.update', $this->asetP2btp), [
                'periode' => '2025–2027',
                'hasil_sensus' => 'Baik',
                'tanggal_sensus' => '2026-10-28',
                'catatan' => 'Sensus periode aktif berjalan',
                'redirect_to' => 'monitoring',
            ]);

        $response->assertRedirect(route('monitoring-aset.index', ['periode' => '2025–2027']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('sensus_aset', [
            'id_aset' => $this->asetP2btp->id_aset,
            'periode' => '2025–2027',
            'hasil_sensus' => 'Baik',
            'catatan' => 'Sensus periode aktif berjalan',
        ]);

        $this->asetP2btp->refresh();
        $this->assertSame('Baik', $this->asetP2btp->kondisi);
    }

    public function test_skenario_2_edit_sensus_pada_periode_berjalan_berhasil(): void
    {
        // Catat sensus awal pada periode berjalan
        SensusAset::create([
            'id_aset' => $this->asetP2btp->id_aset,
            'periode' => '2025–2027',
            'hasil_sensus' => 'Baik',
            'tanggal_sensus' => '2026-05-10',
            'id_user' => $this->adminAset->id_user,
        ]);

        // Edit sensus pada periode berjalan yang sama
        $response = $this->actingAs($this->adminAset)
            ->post(route('monitoring-aset.sensus.update', $this->asetP2btp), [
                'periode' => '2025–2027',
                'hasil_sensus' => 'Rusak Ringan',
                'tanggal_sensus' => '2026-10-28',
                'catatan' => 'Pembaruan kondisi fisik pada periode berjalan',
            ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('sensus_aset', [
            'id_aset' => $this->asetP2btp->id_aset,
            'periode' => '2025–2027',
            'hasil_sensus' => 'Rusak Ringan',
            'catatan' => 'Pembaruan kondisi fisik pada periode berjalan',
        ]);

        $this->asetP2btp->refresh();
        $this->assertSame('Rusak Ringan', $this->asetP2btp->kondisi);
    }

    public function test_skenario_3_edit_sensus_pada_periode_sudah_berakhir_ditolak(): void
    {
        // Rekam data sensus masa lalu
        $sensusLama = SensusAset::create([
            'id_aset' => $this->asetP2btp->id_aset,
            'periode' => '2022–2024',
            'hasil_sensus' => 'Baik',
            'tanggal_sensus' => '2023-05-15',
            'catatan' => 'Catatan lama asli',
            'id_user' => $this->adminAset->id_user,
        ]);

        $this->asetP2btp->update(['kondisi' => 'Baik']);

        // Coba edit sensus periode lampau yang sudah berakhir
        $response = $this->actingAs($this->adminAset)
            ->post(route('monitoring-aset.sensus.update', $this->asetP2btp), [
                'periode' => '2022–2024',
                'hasil_sensus' => 'Rusak Berat',
                'tanggal_sensus' => '2026-10-28',
                'catatan' => 'Mencoba mengubah data masa lalu',
            ]);

        $response->assertSessionHasErrors('periode');

        // Pastikan database dan kondisi aset tidak berubah sama sekali
        $sensusLama->refresh();
        $this->assertSame('Baik', $sensusLama->hasil_sensus);
        $this->assertSame('Catatan lama asli', $sensusLama->catatan);

        $this->asetP2btp->refresh();
        $this->assertSame('Baik', $this->asetP2btp->kondisi);
    }

    public function test_skenario_4_tambah_sensus_pada_periode_sudah_berakhir_ditolak(): void
    {
        $response = $this->actingAs($this->adminAset)
            ->post(route('monitoring-aset.sensus.update', $this->asetP2btp), [
                'periode' => '2022–2024',
                'hasil_sensus' => 'Baik',
                'tanggal_sensus' => '2026-10-28',
            ]);

        $response->assertSessionHasErrors('periode');

        $this->assertDatabaseMissing('sensus_aset', [
            'id_aset' => $this->asetP2btp->id_aset,
            'periode' => '2022–2024',
        ]);
    }

    public function test_skenario_5_tambah_sensus_pada_periode_belum_dimulai_ditolak(): void
    {
        // Periode masa depan (2028–2030) saat ini tahun 2026
        $response = $this->actingAs($this->adminAset)
            ->post(route('monitoring-aset.sensus.update', $this->asetP2btp), [
                'periode' => '2028–2030',
                'hasil_sensus' => 'Baik',
                'tanggal_sensus' => '2026-10-28',
            ]);

        $response->assertSessionHasErrors('periode');

        $this->assertDatabaseMissing('sensus_aset', [
            'id_aset' => $this->asetP2btp->id_aset,
            'periode' => '2028–2030',
        ]);
    }

    public function test_skenario_6_akses_langsung_url_edit_periode_sudah_berakhir_ditolak_403(): void
    {
        // Akses langsung melalui URL query periode masa lampau yang sudah berakhir
        $response = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.sensus.edit', [
                'aset' => $this->asetP2btp->id_aset,
                'periode' => '2022–2024',
            ]));

        $response->assertForbidden();

        // Akses langsung URL query periode legacy lampau juga ditolak 403
        $responseLegacy = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.sensus.edit', [
                'aset' => $this->asetP2btp->id_aset,
                'periode' => '2022–2025',
            ]));

        $responseLegacy->assertForbidden();
    }

    public function test_skenario_7_tampilan_riwayat_sensus_periode_lama_terlihat_tanpa_tombol_edit(): void
    {
        SensusAset::create([
            'id_aset' => $this->asetP2btp->id_aset,
            'periode' => '2022–2024',
            'hasil_sensus' => 'Baik',
            'tanggal_sensus' => '2023-04-10',
            'catatan' => 'Sensus historis masa lampau',
            'id_user' => $this->adminAset->id_user,
        ]);

        $response = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.sensus.riwayat', $this->asetP2btp));

        $response->assertOk();
        $html = $response->getContent();

        // Data terlihat jelas
        $this->assertStringContainsString('2022–2024', $html);
        $this->assertStringContainsString('Baik', $html);
        $this->assertStringContainsString('Sensus historis masa lampau', $html);

        // Tidak ada tombol edit atau link edit form
        $this->assertStringNotContainsString(route('monitoring-aset.sensus.edit', $this->asetP2btp), $html);
        $this->assertStringNotContainsString('<th class="py-3 px-3 text-center">Aksi</th>', $html);
    }

    public function test_skenario_8_admin_upt_mencoba_mengedit_aset_upt_lain_ditolak_403(): void
    {
        // Admin UPT P2BTP mencoba akses URL edit aset milik UPT PSBP -> 403
        $resEdit = $this->actingAs($this->adminUptP2btp)
            ->get(route('monitoring-aset.sensus.edit', $this->asetPsbp));

        $resEdit->assertForbidden();

        // Admin UPT P2BTP mencoba kirim POST update untuk aset UPT PSBP -> 403
        $resUpdate = $this->actingAs($this->adminUptP2btp)
            ->post(route('monitoring-aset.sensus.update', $this->asetPsbp), [
                'periode' => '2025–2027',
                'hasil_sensus' => 'Baik',
                'tanggal_sensus' => '2026-10-28',
            ]);

        $resUpdate->assertForbidden();

        // Pastikan tidak ada data yang tersimpan
        $this->assertDatabaseMissing('sensus_aset', [
            'id_aset' => $this->asetPsbp->id_aset,
            'periode' => '2025–2027',
        ]);
    }
}
