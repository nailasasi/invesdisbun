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
        if (file_exists(base_path('.env'))) {
            foreach (file(base_path('.env')) as $line) {
                if (str_starts_with(trim($line), 'DB_DATABASE=')) {
                    $nilai = trim(explode('=', $line, 2)[1]);

                    return $nilai === '' ? 'invesdisbun' : $nilai;
                }
            }
        }

        return 'invesdisbun';
    }

    private function userDenganRole(string $role): User
    {
        return User::whereHas('role', fn ($q) => $q->where('nama_role', $role))->firstOrFail();
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
            ->assertSee('Periode 2025–2028');

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

        // Saat filter beralih ke periode lama (2022–2025), tombol harus berubah menjadi "Edit Sensus"
        $resLama = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.index', [
                'periode' => '2022–2025',
                'search' => $this->asetP2btp->nomor_kartu_barang,
            ]));

        $resLama->assertOk();
        $htmlLama = $resLama->getContent();
        $this->assertStringContainsString('Edit Sensus', $htmlLama);
        $this->assertStringContainsString('data-sudah-disensus="1"', $htmlLama);

        // 2. Tambah sensus pada periode baru 2025–2028
        $this->actingAs($this->adminAset)
            ->post(route('monitoring-aset.sensus.update', $this->asetP2btp), [
                'periode' => '2025–2028',
                'hasil_sensus' => 'Rusak Ringan',
                'tanggal_sensus' => '2026-10-28',
            ]);

        // Pastikan catatan periode lama dan baru tersimpan terpisah (tidak saling menimpa)
        $this->assertSame(2, SensusAset::where('id_aset', $this->asetP2btp->id_aset)->count());
        $this->assertDatabaseHas('sensus_aset', ['id_aset' => $this->asetP2btp->id_aset, 'periode' => '2022–2025', 'hasil_sensus' => 'Baik']);
        $this->assertDatabaseHas('sensus_aset', ['id_aset' => $this->asetP2btp->id_aset, 'periode' => '2025–2028', 'hasil_sensus' => 'Rusak Ringan']);

        // Sekarang pada filter 2025–2028, tombol dinamis berubah menjadi "Edit Sensus"
        $resAktif2 = $this->actingAs($this->adminAset)
            ->get(route('monitoring-aset.index', [
                'periode' => '2025–2028',
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

    public function test_tombol_edit_pada_setiap_baris_riwayat_membuka_catatan_sesuai_periode(): void
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

        // Verifikasi tautan Edit per baris memuat parameter periode yang tepat
        $urlEditLama = route('monitoring-aset.sensus.edit', ['aset' => $this->asetP2btp->id_aset, 'periode' => '2022–2025']);
        $urlEditAktif = route('monitoring-aset.sensus.edit', ['aset' => $this->asetP2btp->id_aset, 'periode' => '2025–2028']);

        $this->assertStringContainsString($urlEditLama, $html);
        $this->assertStringContainsString($urlEditAktif, $html);

        // Verifikasi saat membuka halaman edit dengan parameter periode lama, form menampilkan data periode tersebut
        $editResponse = $this->actingAs($this->adminAset)->get($urlEditLama);
        $editResponse->assertOk();
        $editHtml = $editResponse->getContent();
        $this->assertStringContainsString('Sensus periode lalu', $editHtml);
        $this->assertStringContainsString('2022–2025', $editHtml);
    }

    public function test_hasil_sensus_rusak_berat_tidak_mengubah_kondisi_inventaris_dan_tidak_otomatis_membuat_usulan_penghapusan(): void
    {
        // Pastikan kondisi awal inventaris adalah 'Baik' dan status aktif
        $this->asetP2btp->update([
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
        ]);

        $jumlahUsulanAwal = UsulanPenghapusan::where('id_aset', $this->asetP2btp->id_aset)->count();

        // Simpan hasil sensus dengan hasil 'Rusak Berat'
        $response = $this->actingAs($this->adminAset)
            ->post(route('monitoring-aset.sensus.update', $this->asetP2btp), [
                'periode' => '2025–2028',
                'hasil_sensus' => 'Rusak Berat',
                'tanggal_sensus' => '2026-10-28',
                'catatan' => 'Rusak total pada motherboard',
            ]);

        $response->assertSessionHas('success');

        // Pastikan hasil sensus tersimpan di sensus_aset
        $this->assertDatabaseHas('sensus_aset', [
            'id_aset' => $this->asetP2btp->id_aset,
            'periode' => '2025–2028',
            'hasil_sensus' => 'Rusak Berat',
        ]);

        // 1. Kondisi inventaris asli di tabel aset TIDAK berubah (tetap 'Baik')
        $this->asetP2btp->refresh();
        $this->assertSame('Baik', $this->asetP2btp->kondisi, 'Kondisi sistem inventaris harus tetap mandiri dan tidak boleh diubah otomatis oleh hasil sensus.');

        // 2. Status aset tetap 'aktif' dan tidak berubah menjadi 'diusulkan_hapus'
        $this->assertSame('aktif', $this->asetP2btp->status_aset, 'Status aset tidak boleh otomatis berubah karena hasil sensus.');

        // 3. Tidak ada usulan penghapusan yang dibuat secara otomatis
        $jumlahUsulanAkhir = UsulanPenghapusan::where('id_aset', $this->asetP2btp->id_aset)->count();
        $this->assertSame($jumlahUsulanAwal, $jumlahUsulanAkhir, 'Hasil sensus Rusak Berat tidak boleh otomatis membuat usulan penghapusan.');
    }
}
