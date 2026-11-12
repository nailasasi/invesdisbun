<?php

namespace Tests\Unit;

use App\Http\Controllers\MonitoringAsetController;
use App\Models\Aset;
use App\Models\Kendaraan;
use App\Models\MasterBarang;
use App\Models\Pegawai;
use App\Models\PemegangAset;
use App\Models\PenempatanAset;
use App\Models\RiwayatPlat;
use App\Models\Ruangan;
use App\Models\Skpd;
use App\Services\AsetScope;
use App\Services\MasaPakaiAset;
use App\Services\MonitoringAsetExporter;
use Illuminate\Http\Request;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class MonitoringAsetScopeTest extends TestCase
{
    /**
     * Helper untuk memanggil method private applyFilters pada controller
     */
    private function panggilApplyFilters(MonitoringAsetController $controller, Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $ref = new ReflectionClass($controller);
        $method = $ref->getMethod('applyFilters');
        $method->setAccessible(true);

        return $method->invoke($controller, $request);
    }

    /**
     * Helper untuk memanggil method private toRow pada controller
     */
    private function panggilToRow(MonitoringAsetController $controller, Aset $aset): object
    {
        $ref = new ReflectionClass($controller);
        $method = $ref->getMethod('toRow');
        $method->setAccessible(true);

        return $method->invoke($controller, $aset, collect(), '2025–2028');
    }

    /**
     * Skenario 1: Admin Aset memiliki scope penuh tanpa filter lokasi fisik.
     */
    public function test_scope_admin_aset_cakupan_penuh(): void
    {
        $scopeMock = $this->createMock(AsetScope::class);
        $scopeMock->method('isAdminUpt')->willReturn(false);
        $scopeMock->method('terapkan')->willReturnCallback(fn ($q) => $q);

        $controller = new MonitoringAsetController(
            $scopeMock,
            app(MasaPakaiAset::class),
            app(MonitoringAsetExporter::class)
        );

        $request = Request::create('/monitoring-aset', 'GET');
        $query = $this->panggilApplyFilters($controller, $request);
        $sql = $query->toSql();

        // Tidak boleh ada filter pembatasan lokasi UPT
        $this->assertDoesNotMatchRegularExpression('/["`]aset["`]\.["`]id_lokasi["`]\s+in/i', $sql);
        $this->assertDoesNotMatchRegularExpression('/["`]aset["`]\.["`]id_skpd["`]\s+=/i', $sql);
    }

    /**
     * Skenario 2: Admin UPT P2BTP hanya melihat lokasi P2BTP dan aset SKPD P2BTP yang id_lokasi-nya NULL.
     */
    public function test_scope_admin_upt_p2btp_menggabungkan_lokasi_dan_skpd(): void
    {
        $scopeMock = $this->createMock(AsetScope::class);
        $scopeMock->method('isAdminUpt')->willReturn(true);
        $scopeMock->method('idLokasi')->willReturn([2]); // ID lokasi P2BTP
        $scopeMock->method('skpdId')->willReturn(10);    // ID SKPD P2BTP

        $controller = new MonitoringAsetController(
            $scopeMock,
            app(MasaPakaiAset::class),
            app(MonitoringAsetExporter::class)
        );

        $request = Request::create('/monitoring-aset', 'GET');
        $query = $this->panggilApplyFilters($controller, $request);
        $sql = $query->toSql();
        $bindings = $query->getBindings();

        // Harus ada whereIn id_lokasi dan orWhere (id_lokasi is null and id_skpd = ?)
        $this->assertMatchesRegularExpression('/["`]aset["`]\.["`]id_lokasi["`]\s+in\s*\(\?\)/i', $sql);
        $this->assertMatchesRegularExpression('/["`]aset["`]\.["`]id_lokasi["`]\s+is null and ["`]aset["`]\.["`]id_skpd["`]\s*=\s*\?/i', $sql);
        $this->assertContains(2, $bindings, 'ID lokasi P2BTP harus masuk ke bindings.');
        $this->assertContains(10, $bindings, 'ID SKPD P2BTP harus masuk ke bindings.');
    }

    /**
     * Skenario 3: Admin UPT PSBP terisolasi pada lokasi PSBP dan SKPD PSBP.
     */
    public function test_scope_admin_upt_psbp_terisolasi_dari_p2btp(): void
    {
        $scopeMock = $this->createMock(AsetScope::class);
        $scopeMock->method('isAdminUpt')->willReturn(true);
        $scopeMock->method('idLokasi')->willReturn([3]); // ID lokasi PSBP
        $scopeMock->method('skpdId')->willReturn(20);    // ID SKPD PSBP

        $controller = new MonitoringAsetController(
            $scopeMock,
            app(MasaPakaiAset::class),
            app(MonitoringAsetExporter::class)
        );

        $request = Request::create('/monitoring-aset', 'GET');
        $query = $this->panggilApplyFilters($controller, $request);
        $bindings = $query->getBindings();

        $this->assertContains(3, $bindings, 'ID lokasi PSBP harus masuk ke bindings.');
        $this->assertContains(20, $bindings, 'ID SKPD PSBP harus masuk ke bindings.');
        $this->assertNotContains(2, $bindings, 'Lokasi P2BTP tidak boleh ada pada bindings PSBP.');
        $this->assertNotContains(10, $bindings, 'SKPD P2BTP tidak boleh ada pada bindings PSBP.');
    }

    /**
     * Skenario 4: Fitur pencarian mencakup plat nomor, pemegang teks, dan pemegang relasi.
     */
    public function test_query_pencarian_mencakup_plat_dan_pemegang_kendaraan(): void
    {
        $scopeMock = $this->createMock(AsetScope::class);
        $scopeMock->method('isAdminUpt')->willReturn(false);
        $scopeMock->method('terapkan')->willReturnCallback(fn ($q) => $q);

        $controller = new MonitoringAsetController(
            $scopeMock,
            app(MasaPakaiAset::class),
            app(MonitoringAsetExporter::class)
        );

        $request = Request::create('/monitoring-aset', 'GET', ['search' => 'B1234XYZ']);
        $query = $this->panggilApplyFilters($controller, $request);
        $sql = $query->toSql();

        // Memeriksa pencarian plat aktif kendaraan (tabel riwayat_plat)
        $this->assertMatchesRegularExpression('/riwayat_plat/i', $sql);
        // Memeriksa pencarian pemegang kendaraan
        $this->assertMatchesRegularExpression('/["`]pemegang["`]\s+like\s+\?/i', $sql);
    }

    /**
     * Skenario 5: toRow() menampilkan fallback pemegang kendaraan dan status penempatan.
     */
    public function test_to_row_fallback_pemegang_dan_status_penempatan(): void
    {
        $scopeMock = $this->createMock(AsetScope::class);
        $controller = new MonitoringAsetController(
            $scopeMock,
            app(MasaPakaiAset::class),
            app(MonitoringAsetExporter::class)
        );

        // Aset kendaraan tanpa relasi pemegang_aset tapi punya kendaraan.pemegang
        $aset = new Aset();
        $aset->id_aset = 999;
        $aset->is_kendaraan = true;
        $aset->nomor_kartu_barang = 'KND-999';

        $kendaraan = new Kendaraan();
        $kendaraan->nama_kendaraan = 'Toyota Innova';
        $kendaraan->pemegang = 'Budi Santoso';
        $aset->setRelation('kendaraan', $kendaraan);
        $aset->setRelation('penempatanAktif', null);
        $aset->setRelation('pemegangSaatIni', null);
        $aset->setRelation('sensus', collect());
        $aset->setRelation('lokasi', null);

        $row = $this->panggilToRow($controller, $aset);

        $this->assertSame('Budi Santoso', $row->pemegang, 'Nama pemegang harus fallback ke kendaraan.pemegang.');
        $this->assertSame('Belum Ditempatkan', $row->status_penempatan, 'Status aset tanpa penempatan aktif harus Belum Ditempatkan.');
        $this->assertNull($row->lokasi, 'Lokasi aset tanpa ruangan harus null.');
    }

    /**
     * Skenario 6: toRow() mendeteksi penempatan yang sudah difinalisasi vs belum difinalisasi.
     */
    public function test_to_row_status_finalisasi_penempatan(): void
    {
        $scopeMock = $this->createMock(AsetScope::class);
        $controller = new MonitoringAsetController(
            $scopeMock,
            app(MasaPakaiAset::class),
            app(MonitoringAsetExporter::class)
        );

        $aset = new Aset();
        $aset->id_aset = 1000;
        $aset->is_kendaraan = false;

        $barang = new MasterBarang();
        $barang->nama_barang = 'Laptop Dell';
        $aset->setRelation('barang', $barang);
        $aset->setRelation('kendaraan', null);
        $aset->setRelation('lokasi', null);

        $penempatan = new PenempatanAset();
        $penempatan->is_finalized = 1;
        $ruangan = new Ruangan();
        $ruangan->nama_ruangan = 'Ruang Kepala Balai';
        $penempatan->setRelation('ruangan', $ruangan);
        $aset->setRelation('penempatanAktif', $penempatan);
        $aset->setRelation('pemegangSaatIni', null);
        $aset->setRelation('sensus', collect());

        $row = $this->panggilToRow($controller, $aset);
        $this->assertSame('Sudah Difinalisasi', $row->status_penempatan);
        $this->assertSame('Ruang Kepala Balai', $row->lokasi);

        // Uji belum difinalisasi
        $penempatan->is_finalized = 0;
        $row2 = $this->panggilToRow($controller, $aset);
        $this->assertSame('Belum Difinalisasi', $row2->status_penempatan);
    }

    /**
     * Skenario 7: Ekspor Excel menyertakan plat nomor pada typeModelAset.
     */
    public function test_exporter_type_model_menyertakan_nomor_polisi(): void
    {
        $exporter = app(MonitoringAsetExporter::class);
        $ref = new ReflectionClass($exporter);
        $method = $ref->getMethod('typeModelAset');
        $method->setAccessible(true);

        $aset = new Aset();
        $aset->is_kendaraan = true;

        $kendaraan = new Kendaraan();
        $kendaraan->tipe = 'Innova Venturer';

        $plat = new RiwayatPlat();
        $plat->nomor_plat = 'B 1234 ABC';
        $kendaraan->setRelation('platAktif', $plat);
        $aset->setRelation('kendaraan', $kendaraan);

        $result = $method->invoke($exporter, $aset);
        $this->assertSame('Innova Venturer (B 1234 ABC)', $result, 'Format Type/Model harus menyertakan nomor plat dalam kurung.');
    }

    /**
     * Skenario 8: Exporter pemegangTunggalEfektif mendukung relasi pegawai dan fallback kendaraan.pemegang.
     */
    public function test_exporter_pemegang_tunggal_efektif(): void
    {
        $exporter = app(MonitoringAsetExporter::class);
        $ref = new ReflectionClass($exporter);
        $method = $ref->getMethod('pemegangTunggalEfektif');
        $method->setAccessible(true);

        // 1. Relasi pemegang aktif
        $aset1 = new Aset();
        $aset1->is_kendaraan = false;
        $pemegang = new PemegangAset();
        $pemegang->status = 'aktif';
        $pegawai = new Pegawai();
        $pegawai->nama_pegawai = 'Ahmad Fauzi';
        $pemegang->setRelation('pegawai', $pegawai);
        $aset1->setRelation('pemegang', collect([$pemegang]));
        $res1 = $method->invoke($exporter, $aset1);
        $this->assertIsArray($res1);
        $this->assertSame('Ahmad Fauzi', $res1['nama']);

        // 2. Kendaraan dengan fallback kolom text
        $aset2 = new Aset();
        $aset2->is_kendaraan = true;
        $aset2->setRelation('pemegang', collect());
        $kendaraan = new Kendaraan();
        $kendaraan->pemegang = 'Supir Operasional';
        $aset2->setRelation('kendaraan', $kendaraan);
        $res2 = $method->invoke($exporter, $aset2);
        $this->assertIsArray($res2);
        $this->assertSame('Supir Operasional', $res2['nama']);

        // 3. Tanpa pemegang sama sekali
        $aset3 = new Aset();
        $aset3->is_kendaraan = false;
        $aset3->setRelation('pemegang', collect());
        $res3 = $method->invoke($exporter, $aset3);
        $this->assertNull($res3);
    }
}
