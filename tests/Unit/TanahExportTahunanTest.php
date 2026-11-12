<?php

namespace Tests\Unit;

use App\Http\Controllers\TanahController;
use App\Models\RetribusiTanah;
use App\Models\Tanah;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Aturan pembentukan Export Tanah + PAD/Tarif Retribusi tahunan.
 *
 * Semua pengujian memakai objek model yang BELUM disimpan (tanpa database),
 * namun tetap menjalankan logika produksi yang sebenarnya (bukan sekadar
 * pemeriksaan kode).
 */
class TanahExportTahunanTest extends TestCase
{
    private static function callStatic(string $method, array $args = [])
    {
        $m = new ReflectionMethod(TanahController::class, $method);
        $m->setAccessible(true);

        return $m->invokeArgs(null, $args);
    }

    private static function kolomTemplateMaster(): array
    {
        $ref = new \ReflectionClassConstant(TanahController::class, 'KOLOM_TEMPLATE');

        return $ref->getValue();
    }

    private static function retribusi(int $id, int $tahun, ?float $pad, ?float $tarif): RetribusiTanah
    {
        $r = new RetribusiTanah([
            'tahun' => $tahun,
            'PAD' => $pad,
            'tarif_retribusi' => $tarif,
        ]);
        $r->id_retribusi = $id;

        return $r;
    }

    /** 0. Template import tetap 19 kolom master. */
    public function test_template_master_tetap_19_kolom(): void
    {
        $this->assertCount(19, self::kolomTemplateMaster());
    }

    /** 1. Header export = 19 master + 4 kolom retribusi tahunan berlabel tahun. */
    public function test_header_export_memuat_empat_kolom_tahunan(): void
    {
        $kolom = self::callStatic('kolomEkspor', [2026]);

        $this->assertCount(23, $kolom);
        $this->assertSame('PAD 2026', $kolom[19]);
        $this->assertSame('Tarif Retribusi 2026', $kolom[20]);
        $this->assertSame('Biaya Pengurusan Kebun /1 tahun 2026', $kolom[21]);
        $this->assertSame('Total Tarif Jika Disewakan 2026', $kolom[22]);

        // 19 kolom pertama tidak berubah (identik template master).
        $this->assertSame(self::kolomTemplateMaster(), array_slice($kolom, 0, 19));
    }

    /** 3. Tanpa parameter tahun => tahun berjalan. */
    public function test_tanpa_parameter_tahun_memakai_tahun_berjalan(): void
    {
        $tahun = self::callStatic('tahunEkspor', [Request::create('/tanah/export')]);

        $this->assertSame((int) date('Y'), $tahun);
    }

    public function test_parameter_tahun_valid_dipakai(): void
    {
        $tahun = self::callStatic('tahunEkspor', [Request::create('/tanah/export', 'GET', ['tahun' => '2026'])]);

        $this->assertSame(2026, $tahun);
    }

    public function test_parameter_tahun_tidak_valid_diabaikan(): void
    {
        $tahun = self::callStatic('tahunEkspor', [Request::create('/tanah/export', 'GET', ['tahun' => 'abc'])]);

        $this->assertSame((int) date('Y'), $tahun);
    }

    /** 2. Pemilihan data retribusi mengikuti tahun terpilih. */
    public function test_pilih_retribusi_hanya_pada_tahun_terpilih(): void
    {
        $koleksi = collect([
            self::retribusi(1, 2025, 111, 11),
            self::retribusi(2, 2026, 222, 22),
        ]);

        $this->assertSame(222.0, (float) self::callStatic('pilihRetribusiTahun', [$koleksi, 2026])->PAD);
        $this->assertSame(111.0, (float) self::callStatic('pilihRetribusiTahun', [$koleksi, 2025])->PAD);
    }

    /** 5. Duplikat (Tanah,tahun) => pilih id_retribusi terbesar. */
    public function test_duplikat_dipilih_id_retribusi_terbesar(): void
    {
        $koleksi = collect([
            self::retribusi(5, 2026, 100, 10),
            self::retribusi(12, 2026, 999, 88),
            self::retribusi(9, 2026, 500, 50),
        ]);

        $pilih = self::callStatic('pilihRetribusiTahun', [$koleksi, 2026]);

        $this->assertSame(12, (int) $pilih->id_retribusi);
        $this->assertSame(999.0, (float) $pilih->PAD);
    }

    public function test_tanpa_baris_pada_tahun_terpilih_mengembalikan_null(): void
    {
        $koleksi = collect([self::retribusi(1, 2025, 111, 11)]);

        $this->assertNull(self::callStatic('pilihRetribusiTahun', [$koleksi, 2026]));
    }

    /** 4. Tanah tanpa retribusi => empat kolom tahunan kosong; baris tetap ada. */
    public function test_baris_ekspor_tanpa_retribusi_kolom_tahunan_kosong(): void
    {
        $tanah = new Tanah(['kib' => 'KIB-2', 'deskripsi_objek' => 'Tanah B']);
        $tanah->setRelation('retribusi', collect());

        $row = self::callStatic('barisEkspor', [$tanah, 2026, 1]);

        $this->assertCount(23, $row);
        $this->assertSame('', $row[19]);
        $this->assertSame('', $row[20]);
        $this->assertSame('', $row[21]);
        $this->assertSame('', $row[22]);
    }

    /** 4b. Ada retribusi tapi tahun beda => tetap kosong. */
    public function test_baris_ekspor_tahun_beda_kolom_tahunan_kosong(): void
    {
        $tanah = new Tanah(['kib' => 'KIB-3']);
        $tanah->setRelation('retribusi', collect([self::retribusi(1, 2025, 111, 11)]));

        $row = self::callStatic('barisEkspor', [$tanah, 2026, 1]);

        $this->assertSame('', $row[19]);
        $this->assertSame('', $row[20]);
        $this->assertSame('', $row[21]);
        $this->assertSame('', $row[22]);
    }

    public function test_baris_ekspor_mengisi_empat_kolom_dari_tahun_terpilih(): void
    {
        $tanah = new Tanah(['kib' => 'KIB-1']);
        $r = self::retribusi(2, 2026, 1500000, 250000);
        $r->biaya_pengurusan = 125000;
        $r->total_tarif_sewa = 1875000;
        $tanah->setRelation('retribusi', collect([$r]));

        $row = self::callStatic('barisEkspor', [$tanah, 2026, 1]);

        $this->assertSame(1500000.0, $row[19]);   // PAD
        $this->assertSame(250000.0, $row[20]);    // Tarif Retribusi
        $this->assertSame(125000.0, $row[21]);    // Biaya Pengurusan Kebun
        $this->assertSame(1875000.0, $row[22]);   // Total Tarif Jika Disewakan
        $this->assertSame('KIB-1', $row[1]);
    }

    public function test_baris_ekspor_duplikat_memakai_id_terbesar(): void
    {
        $tanah = new Tanah(['kib' => 'KIB-1']);
        $r1 = self::retribusi(3, 2026, 100, 10);
        $r1->biaya_pengurusan = 1;
        $r1->total_tarif_sewa = 2;
        $r2 = self::retribusi(11, 2026, 400, 40);
        $r2->biaya_pengurusan = 8;
        $r2->total_tarif_sewa = 9;
        $tanah->setRelation('retribusi', collect([$r1, $r2]));

        $row = self::callStatic('barisEkspor', [$tanah, 2026, 1]);

        $this->assertSame(400.0, $row[19]);
        $this->assertSame(40.0, $row[20]);
        $this->assertSame(8.0, $row[21]);
        $this->assertSame(9.0, $row[22]);
    }
}
