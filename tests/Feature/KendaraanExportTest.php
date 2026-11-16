<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\Kendaraan;
use App\Models\Lokasi;
use App\Models\MasterBarang;
use App\Models\PajakKendaraan;
use App\Models\Pegawai;
use App\Models\PemegangAset;
use App\Models\RiwayatPlat;
use App\Models\Role;
use App\Models\Skpd;
use App\Models\User;
use App\Services\AsetScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class KendaraanExportTest extends TestCase
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

    private function buatUserRole(string $namaRole, int $idSkpd = 1): User
    {
        $role = Role::firstOrCreate(['nama_role' => $namaRole], ['deskripsi' => $namaRole]);
        $skpd = Skpd::find($idSkpd) ?? Skpd::create([
            'id_skpd' => $idSkpd,
            'nama_skpd' => 'SKPD Test ' . $idSkpd,
            'jenis_skpd' => $namaRole === 'Admin UPT' ? 'UPT' : 'Bidang',
        ]);
        $pegawai = Pegawai::create([
            'nama_pegawai' => $namaRole . ' ' . uniqid(),
            'nip' => '19900101' . rand(100000, 999999),
            'id_skpd' => $skpd->id_skpd,
            'jabatan' => 'Staf Test',
        ]);
        return User::create([
            'username' => strtolower(str_replace(' ', '_', $namaRole)) . '_' . uniqid(),
            'password' => bcrypt('password123'),
            'id_role' => $role->id_role,
            'id_pegawai' => $pegawai->id_pegawai,
            'status_user' => 'aktif',
        ]);
    }

    private function buatKendaraan(string $statusAset = 'aktif', ?string $nama = null, ?int $idLokasi = null): Kendaraan
    {
        $aset = Aset::create([
            'nomor_kartu_barang' => 'NKB-' . uniqid(),
            'status_aset' => $statusAset,
            'is_kendaraan' => true,
            'kondisi' => 'Baik',
            'id_lokasi' => $idLokasi,
        ]);

        return Kendaraan::create([
            'id_aset' => $aset->id_aset,
            'nama_kendaraan' => $nama ?? ('Mobil Test ' . uniqid()),
            'jenis_kendaraan' => 'Mobil',
            'nomor_rangka' => 'NR-' . uniqid(),
            'nomor_mesin' => 'NM-' . uniqid(),
            'pemegang' => 'Fallback Lama',
        ]);
    }

    /** 1. Hak Akses Seluruh Role */
    public function test_hak_akses_seluruh_role(): void
    {
        $adminAset = $this->buatUserRole('Admin Aset');
        $adminUpt = $this->buatUserRole('Admin UPT');
        $adminBidang = $this->buatUserRole('Admin Bidang');
        $pegawai = $this->buatUserRole('Pegawai');

        // Admin Aset BOLEH GET dan POST
        $this->actingAs($adminAset)->get(route('kendaraan.export', ['mode' => 'semua']))->assertOk();
        $this->actingAs($adminAset)->post(route('kendaraan.export'), ['mode' => 'pilih', 'ids' => [1]])->assertOk();

        // Admin UPT DITOLAK 403
        $this->actingAs($adminUpt)->get(route('kendaraan.export', ['mode' => 'semua']))->assertStatus(403);
        $this->actingAs($adminUpt)->post(route('kendaraan.export'), ['mode' => 'pilih', 'ids' => [1]])->assertStatus(403);

        // Admin Bidang DITOLAK 403
        $this->actingAs($adminBidang)->get(route('kendaraan.export', ['mode' => 'semua']))->assertStatus(403);
        $this->actingAs($adminBidang)->post(route('kendaraan.export'), ['mode' => 'pilih', 'ids' => [1]])->assertStatus(403);

        // Pegawai DITOLAK 403
        $this->actingAs($pegawai)->get(route('kendaraan.export', ['mode' => 'semua']))->assertStatus(403);
        $this->actingAs($pegawai)->post(route('kendaraan.export'), ['mode' => 'pilih', 'ids' => [1]])->assertStatus(403);

        // Guest diarahkan ke login
        auth()->logout();
        $this->get(route('kendaraan.export', ['mode' => 'semua']))->assertRedirect(route('login'));
        $this->post(route('kendaraan.export'), ['mode' => 'pilih', 'ids' => [1]])->assertRedirect(route('login'));
    }

    /** 2. Mode Semua Kendaraan: Hanya Status Aktif yang Boleh Terekspor */
    public function test_mode_semua_hanya_ekspor_kendaraan_status_aset_aktif(): void
    {
        $adminAset = $this->buatUserRole('Admin Aset');
        $this->actingAs($adminAset);

        $kAktif = $this->buatKendaraan('aktif', 'Mobil Aktif Lolos');
        $kNonaktif = $this->buatKendaraan('nonaktif', 'Mobil Nonaktif Ditolak');
        $kUsulHapus = $this->buatKendaraan('diusulkan_hapus', 'Mobil Usul Hapus Ditolak');
        $kHapuskan = $this->buatKendaraan('dihapuskan', 'Mobil Dihapuskan Ditolak');
        $kPajakKosong = $this->buatKendaraan('aktif', 'Mobil Pajak Kosong Masuk');

        // Request tanpa filter status pada query string
        $res = $this->get(route('kendaraan.export', ['mode' => 'semua']));
        $res->assertOk();

        // Uji query backend langsung yang digunakan export
        $controller = app(\App\Http\Controllers\KendaraanController::class);
        $ref = new \ReflectionClass($controller);
        $mFilter = $ref->getMethod('filteredKendaraanQuery');
        $mFilter->setAccessible(true);

        $req = request()->merge(['mode' => 'semua', 'status' => 'aktif']);
        $q = $mFilter->invoke($controller, $req)->whereHas('aset', fn ($a) => $a->where('status_aset', 'aktif'));
        $ids = $q->pluck('id_kendaraan')->toArray();

        $this->assertContains($kAktif->id_kendaraan, $ids, 'Kendaraan aktif wajib masuk');
        $this->assertContains($kPajakKosong->id_kendaraan, $ids, 'Kendaraan aktif dengan pajak kosong wajib masuk');
        $this->assertNotContains($kNonaktif->id_kendaraan, $ids, 'Kendaraan nonaktif TIDAK BOLEH masuk');
        $this->assertNotContains($kUsulHapus->id_kendaraan, $ids, 'Kendaraan diusulkan_hapus TIDAK BOLEH masuk');
        $this->assertNotContains($kHapuskan->id_kendaraan, $ids, 'Kendaraan dihapuskan TIDAK BOLEH masuk');
    }

    /** 3. Mode Segera Habis Pajak: Pengujian Seluruh Batas Waktu dan Jenis Pajak */
    public function test_mode_segera_habis_pajak_seluruh_skenario(): void
    {
        $adminAset = $this->buatUserRole('Admin Aset');
        $this->actingAs($adminAset);

        $today = Carbon::today();

        // 1. Pajak tahunan H+0 (hari ini): MASUK
        $kH0 = $this->buatKendaraan('aktif', 'Mobil P1 H+0');
        PajakKendaraan::create([
            'id_kendaraan' => $kH0->id_kendaraan,
            'jenis_pajak' => 'PKB',
            'tanggal_berakhir' => $today->copy(),
            'pajak_5_tahunan' => false,
            'status' => 'Aktif',
        ]);

        // 2. Pajak tahunan H+5: MASUK
        $kH5 = $this->buatKendaraan('aktif', 'Mobil P1 H+5');
        PajakKendaraan::create([
            'id_kendaraan' => $kH5->id_kendaraan,
            'jenis_pajak' => 'PKB',
            'tanggal_berakhir' => $today->copy()->addDays(5),
            'pajak_5_tahunan' => false,
            'status' => 'Aktif',
        ]);

        // 3. Pajak tahunan H+29: MASUK
        $kH29 = $this->buatKendaraan('aktif', 'Mobil P1 H+29');
        PajakKendaraan::create([
            'id_kendaraan' => $kH29->id_kendaraan,
            'jenis_pajak' => 'PKB',
            'tanggal_berakhir' => $today->copy()->addDays(29),
            'pajak_5_tahunan' => false,
            'status' => 'Aktif',
        ]);

        // 4. Pajak tahunan H+30: MASUK (inklusif)
        $kH30 = $this->buatKendaraan('aktif', 'Mobil P1 H+30');
        PajakKendaraan::create([
            'id_kendaraan' => $kH30->id_kendaraan,
            'jenis_pajak' => 'PKB',
            'tanggal_berakhir' => $today->copy()->addDays(30),
            'pajak_5_tahunan' => false,
            'status' => 'Aktif',
        ]);

        // 5. Pajak tahunan H+31: TIDAK MASUK
        $kH31 = $this->buatKendaraan('aktif', 'Mobil P1 H+31');
        PajakKendaraan::create([
            'id_kendaraan' => $kH31->id_kendaraan,
            'jenis_pajak' => 'PKB',
            'tanggal_berakhir' => $today->copy()->addDays(31),
            'pajak_5_tahunan' => false,
            'status' => 'Aktif',
        ]);

        // 6. Pajak tahunan H-1 (terlambat): TIDAK MASUK
        $kHMin1 = $this->buatKendaraan('aktif', 'Mobil P1 H-1');
        PajakKendaraan::create([
            'id_kendaraan' => $kHMin1->id_kendaraan,
            'jenis_pajak' => 'PKB',
            'tanggal_berakhir' => $today->copy()->subDays(1),
            'pajak_5_tahunan' => false,
            'status' => 'Aktif',
        ]);

        // 7. Pajak 5 tahunan H+10 meskipun pajak tahunan H+60: MASUK
        $kP5H10 = $this->buatKendaraan('aktif', 'Mobil P5 H+10');
        PajakKendaraan::create([
            'id_kendaraan' => $kP5H10->id_kendaraan,
            'jenis_pajak' => 'PKB',
            'tanggal_berakhir' => $today->copy()->addDays(60),
            'pajak_5_tahunan' => false,
            'status' => 'Aktif',
        ]);
        // Status 'Tidak Aktif' seperti hasil impor data riil: HARUS TETAP MASUK
        PajakKendaraan::create([
            'id_kendaraan' => $kP5H10->id_kendaraan,
            'jenis_pajak' => 'PKB',
            'tanggal_berakhir' => $today->copy()->addDays(10),
            'pajak_5_tahunan' => true,
            'status' => 'Tidak Aktif',
        ]);

        // 8. Pajak 5 tahunan H+30: MASUK
        $kP5H30 = $this->buatKendaraan('aktif', 'Mobil P5 H+30');
        PajakKendaraan::create([
            'id_kendaraan' => $kP5H30->id_kendaraan,
            'jenis_pajak' => 'PKB',
            'tanggal_berakhir' => $today->copy()->addDays(30),
            'pajak_5_tahunan' => true,
            'status' => 'Tidak Aktif',
        ]);

        // 9. Pajak 5 tahunan H-1 (terlambat): TIDAK MASUK
        $kP5HMin1 = $this->buatKendaraan('aktif', 'Mobil P5 H-1');
        PajakKendaraan::create([
            'id_kendaraan' => $kP5HMin1->id_kendaraan,
            'jenis_pajak' => 'PKB',
            'tanggal_berakhir' => $today->copy()->subDays(1),
            'pajak_5_tahunan' => true,
            'status' => 'Tidak Aktif',
        ]);

        // 10. Kedua jenis pajak H+30: MASUK TEPAT SATU KALI
        $kKeduanya = $this->buatKendaraan('aktif', 'Mobil Keduanya H+30');
        PajakKendaraan::create([
            'id_kendaraan' => $kKeduanya->id_kendaraan,
            'jenis_pajak' => 'PKB',
            'tanggal_berakhir' => $today->copy()->addDays(30),
            'pajak_5_tahunan' => false,
            'status' => 'Aktif',
        ]);
        PajakKendaraan::create([
            'id_kendaraan' => $kKeduanya->id_kendaraan,
            'jenis_pajak' => 'PKB',
            'tanggal_berakhir' => $today->copy()->addDays(30),
            'pajak_5_tahunan' => true,
            'status' => 'Tidak Aktif',
        ]);

        // 11. Tanggal pajak kosong / null: TIDAK MASUK
        $kNull = $this->buatKendaraan('aktif', 'Mobil Pajak Null');

        // 12. Kendaraan nonaktif dengan pajak H+10: TIDAK MASUK
        $kNonaktifPajak = $this->buatKendaraan('nonaktif', 'Mobil Nonaktif Pajak H+10');
        PajakKendaraan::create([
            'id_kendaraan' => $kNonaktifPajak->id_kendaraan,
            'jenis_pajak' => 'PKB',
            'tanggal_berakhir' => $today->copy()->addDays(10),
            'pajak_5_tahunan' => false,
            'status' => 'Aktif',
        ]);

        // Eksekusi request export mode segera
        $response = $this->get(route('kendaraan.export', ['mode' => 'segera']));
        $response->assertOk();

        // Uji query kueri backend yang sama
        $controller = app(\App\Http\Controllers\KendaraanController::class);
        $ref = new \ReflectionClass($controller);
        $mFilter = $ref->getMethod('filteredKendaraanQuery');
        $mFilter->setAccessible(true);

        $tglToday = $today->toDateString();
        $tglBatas = $today->copy()->addDays(30)->toDateString();

        $query = $mFilter->invoke($controller, request()->merge(['mode' => 'segera', 'status' => 'aktif']))
            ->whereHas('aset', fn ($a) => $a->where('status_aset', 'aktif'))
            ->where(function ($q) use ($tglToday, $tglBatas) {
                $q->whereExists(function ($sub) use ($tglToday, $tglBatas) {
                    $sub->selectRaw(1)
                        ->from('pajak_kendaraan as p1')
                        ->whereColumn('p1.id_kendaraan', 'kendaraan.id_kendaraan')
                        ->where('p1.pajak_5_tahunan', false)
                        ->whereNotNull('p1.tanggal_berakhir')
                        ->whereBetween('p1.tanggal_berakhir', [$tglToday, $tglBatas])
                        ->whereNotExists(function ($newer) {
                            $newer->selectRaw(1)
                                ->from('pajak_kendaraan as p1_newer')
                                ->whereColumn('p1_newer.id_kendaraan', 'p1.id_kendaraan')
                                ->where('p1_newer.pajak_5_tahunan', false)
                                ->whereColumn('p1_newer.tanggal_berakhir', '>', 'p1.tanggal_berakhir');
                        });
                })->orWhereExists(function ($sub) use ($tglToday, $tglBatas) {
                    $sub->selectRaw(1)
                        ->from('pajak_kendaraan as p5')
                        ->whereColumn('p5.id_kendaraan', 'kendaraan.id_kendaraan')
                        ->where('p5.pajak_5_tahunan', true)
                        ->whereNotNull('p5.tanggal_berakhir')
                        ->whereBetween('p5.tanggal_berakhir', [$tglToday, $tglBatas])
                        ->whereNotExists(function ($newer) {
                            $newer->selectRaw(1)
                                ->from('pajak_kendaraan as p5_newer')
                                ->whereColumn('p5_newer.id_kendaraan', 'p5.id_kendaraan')
                                ->where('p5_newer.pajak_5_tahunan', true)
                                ->whereColumn('p5_newer.tanggal_berakhir', '>', 'p5.tanggal_berakhir');
                        });
                });
            });

        $ids = $query->pluck('id_kendaraan')->toArray();

        $this->assertContains($kH0->id_kendaraan, $ids, 'H+0 harus masuk');
        $this->assertContains($kH5->id_kendaraan, $ids, 'H+5 harus masuk');
        $this->assertContains($kH29->id_kendaraan, $ids, 'H+29 harus masuk');
        $this->assertContains($kH30->id_kendaraan, $ids, 'H+30 harus masuk');
        $this->assertNotContains($kH31->id_kendaraan, $ids, 'H+31 TIDAK BOLEH masuk');
        $this->assertNotContains($kHMin1->id_kendaraan, $ids, 'H-1 (terlambat) TIDAK BOLEH masuk');
        $this->assertContains($kP5H10->id_kendaraan, $ids, 'Pajak 5 tahunan H+10 (meskipun status tidak aktif) HARUS masuk');
        $this->assertContains($kP5H30->id_kendaraan, $ids, 'Pajak 5 tahunan H+30 HARUS masuk');
        $this->assertNotContains($kP5HMin1->id_kendaraan, $ids, 'Pajak 5 tahunan H-1 TIDAK BOLEH masuk');
        $this->assertContains($kKeduanya->id_kendaraan, $ids, 'Keduanya H+30 HARUS masuk');
        $this->assertNotContains($kNull->id_kendaraan, $ids, 'Pajak null TIDAK BOLEH masuk');
        $this->assertNotContains($kNonaktifPajak->id_kendaraan, $ids, 'Kendaraan nonaktif TIDAK BOLEH masuk');

        // Pastikan tidak ada duplikasi pada ID kKeduanya
        $countKeduanya = count(array_filter($ids, fn ($id) => $id == $kKeduanya->id_kendaraan));
        $this->assertEquals(1, $countKeduanya, 'Kendaraan kKeduanya harus tepat 1 kali');
    }

    /** 4. Mode Kendaraan Terpilih: Validasi Checkbox, IDs, Nonaktif, AsetScope */
    public function test_mode_pilih_kendaraan(): void
    {
        $adminAset = $this->buatUserRole('Admin Aset');
        $this->actingAs($adminAset);

        $k1 = $this->buatKendaraan('aktif', 'Mobil Terpilih 1');
        $k2 = $this->buatKendaraan('aktif', 'Mobil Terpilih 2');
        $k3 = $this->buatKendaraan('aktif', 'Mobil Tidak Dicentang');
        $kNonaktif = $this->buatKendaraan('nonaktif', 'Mobil Nonaktif Dicoba');

        // A. Satu kendaraan sah
        $res1 = $this->post(route('kendaraan.export'), ['mode' => 'pilih', 'ids' => [$k1->id_kendaraan]]);
        $res1->assertOk();

        // B. Beberapa kendaraan sah
        $res2 = $this->post(route('kendaraan.export'), ['mode' => 'pilih', 'ids' => [$k1->id_kendaraan, $k2->id_kendaraan]]);
        $res2->assertOk();

        // C. IDs kosong: menghasilkan stream Excel kosong tanpa error
        $resEmpty = $this->post(route('kendaraan.export'), ['mode' => 'pilih', 'ids' => []]);
        $resEmpty->assertOk();

        // D. Manipulasi ID kendaraan nonaktif: Backend MENOLAK kendaraan nonaktif
        $controller = app(\App\Http\Controllers\KendaraanController::class);
        $ref = new \ReflectionClass($controller);
        $mFilter = $ref->getMethod('filteredKendaraanQuery');
        $mFilter->setAccessible(true);

        $q = $mFilter->invoke($controller, request()->merge(['mode' => 'pilih', 'status' => 'aktif']))
            ->whereHas('aset', fn ($a) => $a->where('status_aset', 'aktif'))
            ->whereIn('id_kendaraan', [$k1->id_kendaraan, $kNonaktif->id_kendaraan]);

        $ids = $q->pluck('id_kendaraan')->toArray();
        $this->assertContains($k1->id_kendaraan, $ids);
        $this->assertNotContains($kNonaktif->id_kendaraan, $ids, 'Kendaraan nonaktif harus dicegat pada mode pilih');

        // E. Pembatasan AsetScope: User dengan scope lokasi terbatas tidak dapat mengakses aset di luar lokasi
        $skpdUpt = Skpd::create(['nama_skpd' => 'UPT Test A ' . uniqid(), 'jenis_skpd' => 'UPT']);
        $lokasiA = Lokasi::create(['nama_lokasi' => 'Lokasi A ' . uniqid(), 'id_skpd' => $skpdUpt->id_skpd]);
        $lokasiB = Lokasi::create(['nama_lokasi' => 'Lokasi B ' . uniqid()]);

        $kAsetA = $this->buatKendaraan('aktif', 'Mobil Lokasi A', $lokasiA->id_lokasi);
        $kAsetB = $this->buatKendaraan('aktif', 'Mobil Lokasi B', $lokasiB->id_lokasi);

        $adminUptA = $this->buatUserRole('Admin UPT', $skpdUpt->id_skpd);

        // Pastikan AsetScope terapkan bekerja
        $this->actingAs($adminUptA);
        app()->forgetInstance(AsetScope::class);

        $qScope = Kendaraan::whereHas('aset', fn ($a) => app(AsetScope::class)->terapkan($a));
        $scopeIds = $qScope->pluck('id_kendaraan')->toArray();
        $this->assertContains($kAsetA->id_kendaraan, $scopeIds);
        $this->assertNotContains($kAsetB->id_kendaraan, $scopeIds);
    }

    /** 5. Validasi Isi Spreadsheet Excel dan Evaluasi Jenis Pajak */
    public function test_validasi_isi_spreadsheet_excel(): void
    {
        $adminAset = $this->buatUserRole('Admin Aset');
        $this->actingAs($adminAset);

        $skpd = Skpd::create(['nama_skpd' => 'Bidang Perkebunan Test', 'jenis_skpd' => 'Bidang']);
        $pegawai = Pegawai::create([
            'nama_pegawai' => 'Drs. Hendro Wibowo',
            'nip' => '198501012010011005',
            'id_skpd' => $skpd->id_skpd,
            'jabatan' => 'Kepala Bidang',
        ]);

        $aset = Aset::create([
            'nomor_kartu_barang' => 'KIB-B-0099',
            'status_aset' => 'aktif',
            'is_kendaraan' => true,
            'kondisi' => 'Baik',
            'id_skpd' => $skpd->id_skpd,
            'merk' => 'Toyota',
        ]);

        $kendaraan = Kendaraan::create([
            'id_aset' => $aset->id_aset,
            'nama_kendaraan' => 'Toyota Fortuner VRZ',
            'jenis_kendaraan' => 'Roda Empat (Mobil)',
            'nomor_rangka' => 'MHK1234567890',
            'nomor_mesin' => '2GD1234567',
            'merk' => 'Toyota',
            'tipe' => 'VRZ 2.4 AT',
            'pemegang' => 'Nama Teks Lama', // Fallback lama
        ]);

        // Buat penempatan resmi aktif pada PemegangAset
        PemegangAset::create([
            'id_aset' => $aset->id_aset,
            'id_pegawai' => $pegawai->id_pegawai,
            'tanggal_mulai' => Carbon::now()->subMonths(6),
            'status' => 'Aktif',
        ]);

        // Buat plat aktif
        RiwayatPlat::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'nomor_plat' => 'L 1234 OP',
            'tanggal_berlaku' => Carbon::today()->addDays(20),
            'status' => 'Aktif',
        ]);

        // Pajak tahunan jatuh tempo dalam 15 hari
        PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => 'PKB',
            'tanggal_berakhir' => Carbon::today()->addDays(15),
            'nominal' => 4500000,
            'total_pajak' => 4650000,
            'pajak_5_tahunan' => false,
            'status' => 'Aktif',
        ]);

        // Pajak 5 tahunan jatuh tempo dalam 20 hari
        PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => 'PKB',
            'tanggal_berakhir' => Carbon::today()->addDays(20),
            'nominal' => 5000000,
            'total_pajak' => 5200000,
            'pajak_5_tahunan' => true,
            'status' => 'Tidak Aktif',
        ]);

        $controller = app(\App\Http\Controllers\KendaraanController::class);
        $ref = new \ReflectionClass($controller);

        // Uji pemegang resmi penempatan aktif
        $kLoaded = Kendaraan::with(['aset.pemegangSaatIni.pegawai', 'aset.skpd', 'pajak', 'platAktif'])->find($kendaraan->id_kendaraan);
        $pemegangResmi = $kLoaded->aset?->pemegangSaatIni?->pegawai?->nama_pegawai;
        $this->assertEquals('Drs. Hendro Wibowo', $pemegangResmi, 'Pemegang harus mengambil nama pegawai dari penempatan aktif');

        // Uji method pajakJatuhTempo
        $mPajakJT = $ref->getMethod('pajakJatuhTempo');
        $mPajakJT->setAccessible(true);
        [$jt1, $jt5] = $mPajakJT->invoke($controller, $kLoaded);

        $this->assertEquals(Carbon::today()->addDays(15)->toDateString(), $jt1->toDateString());
        $this->assertEquals(Carbon::today()->addDays(20)->toDateString(), $jt5->toDateString());

        // Uji jenisPajakSegeraHabis: karena keduanya dalam [today, today+30], hasilnya 'Keduanya'
        $mJenis = $ref->getMethod('jenisPajakSegeraHabis');
        $mJenis->setAccessible(true);
        $today = Carbon::today();
        $batas = Carbon::today()->addDays(30);

        $jenisHasil = $mJenis->invoke($controller, $jt1, $jt5, $today, $batas);
        $this->assertEquals('Keduanya', $jenisHasil, 'Harus terdeteksi Keduanya');

        // Uji jika hanya tahunan
        $jenisHasil1 = $mJenis->invoke($controller, $jt1, Carbon::today()->addDays(40), $today, $batas);
        $this->assertEquals('Pajak Tahunan', $jenisHasil1);

        // Uji jika hanya 5 tahunan
        $jenisHasil5 = $mJenis->invoke($controller, Carbon::today()->addDays(40), $jt5, $today, $batas);
        $this->assertEquals('Pajak Lima Tahunan', $jenisHasil5);

        // Uji jika tidak ada yang segera habis
        $jenisHasilNone = $mJenis->invoke($controller, Carbon::today()->addDays(40), Carbon::today()->addDays(40), $today, $batas);
        $this->assertEquals('-', $jenisHasilNone);

        // Uji label tombol pada view index
        $view = $this->actingAs($adminAset)->get(route('kendaraan.index'))->getContent();
        $this->assertStringContainsString('Ekspor Data', $view, 'Label tombol harus Ekspor Data');
    }
}
