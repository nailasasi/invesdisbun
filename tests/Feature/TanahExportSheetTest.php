<?php

namespace Tests\Feature;

use App\Http\Controllers\TanahController;
use App\Models\RetribusiTanah;
use App\Models\Tanah;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Integrasi penulisan worksheet Export Tanah (tanpa database).
 *
 * Memverifikasi layout kolom, satu baris per Tanah walau ada duplikat
 * retribusi, filter tahun, dan filter pencarian. Semua model dibuat
 * in-memory (belum disimpan) sehingga tidak menulis ke database development.
 */
class TanahExportSheetTest extends TestCase
{
    private static function callStatic(string $method, array $args = [])
    {
        $m = new ReflectionMethod(TanahController::class, $method);
        $m->setAccessible(true);

        return $m->invokeArgs(null, $args);
    }

    private function sheet(array $tanahList, int $tahun)
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        self::callStatic('tulisSheetExport', [$sheet, $tanahList, $tahun]);

        return $sheet;
    }

    private function retribusi(int $id, int $tahun, ?float $pad, ?float $tarif, ?float $biaya = null, ?float $sewa = null): RetribusiTanah
    {
        $r = new RetribusiTanah([
            'tahun' => $tahun,
            'PAD' => $pad,
            'tarif_retribusi' => $tarif,
        ]);
        $r->id_retribusi = $id;
        $r->biaya_pengurusan = $biaya;
        $r->total_tarif_sewa = $sewa;

        return $r;
    }

    private function tanah(string $kib, array $retribusi = []): Tanah
    {
        $tanah = new Tanah(['kib' => $kib, 'deskripsi_objek' => "Objek {$kib}"]);
        $tanah->setRelation('retribusi', collect($retribusi));

        return $tanah;
    }

    /** 1 & 7. Layout: 23 kolom (A..W), 19 master di depan + 4 tahunan di belakang. */
    public function test_layout_header_23_kolom_dengan_empat_kolom_tahunan(): void
    {
        $sheet = $this->sheet([$this->tanah('KIB-1')], 2026);

        $this->assertSame('W', $sheet->getHighestColumn());

        $header = $sheet->rangeToArray('A1:W1', null, true, false)[0];
        $this->assertCount(23, $header);
        $this->assertSame('PAD 2026', $header[19]);
        $this->assertSame('Tarif Retribusi 2026', $header[20]);
        $this->assertSame('Biaya Pengurusan Kebun /1 tahun 2026', $header[21]);
        $this->assertSame('Total Tarif Jika Disewakan 2026', $header[22]);
        $this->assertSame('No', $header[0]);
        $this->assertSame('Penggunaan Air (Silahkan diisi pilihan sbg berikut)', $header[18]);
    }

    /** 2. Keempat nilai diambil dari tahun terpilih. */
    public function test_keempat_nilai_diambil_dari_tahun_terpilih(): void
    {
        $tanah = $this->tanah('KIB-1', [
            $this->retribusi(1, 2025, 111, 11, 1, 2),
            $this->retribusi(2, 2026, 222, 22, 33, 44),
        ]);

        $sheet = $this->sheet([$tanah], 2026);

        $this->assertSame(222.0, $sheet->getCell('T2')->getValue());
        $this->assertSame(22.0, $sheet->getCell('U2')->getValue());
        $this->assertSame(33.0, $sheet->getCell('V2')->getValue());
        $this->assertSame(44.0, $sheet->getCell('W2')->getValue());
    }

    /** 4. Tanah tanpa retribusi pada tahun terpilih tetap diekspor & kolom kosong. */
    public function test_tanah_tanpa_retribusi_tetap_satu_baris_dan_kolom_kosong(): void
    {
        $tanah = $this->tanah('KIB-1', [
            $this->retribusi(1, 2025, 111, 11), // tahun beda
        ]);

        $sheet = $this->sheet([$tanah], 2026);

        $this->assertSame(2, $sheet->getHighestRow()); // header + 1 baris
        $this->assertSame('KIB-1', $sheet->getCell('B2')->getValue());
        $this->assertNull($sheet->getCell('T2')->getValue());
        $this->assertNull($sheet->getCell('U2')->getValue());
        $this->assertNull($sheet->getCell('V2')->getValue());
        $this->assertNull($sheet->getCell('W2')->getValue());
    }

    /** 5. Duplikat (Tanah,tahun) tidak menggandakan baris; dipilih id terbesar. */
    public function test_duplikat_tidak_menggandakan_baris_dan_pilih_id_terbesar(): void
    {
        $tanah = $this->tanah('KIB-1', [
            $this->retribusi(3, 2026, 100, 10, 1, 1),
            $this->retribusi(11, 2026, 400, 40, 88, 99),
            $this->retribusi(7, 2026, 200, 20, 2, 2),
        ]);

        $sheet = $this->sheet([$tanah], 2026);

        $this->assertSame(2, $sheet->getHighestRow()); // hanya 1 baris data
        $this->assertSame(400.0, $sheet->getCell('T2')->getValue());
        $this->assertSame(40.0, $sheet->getCell('U2')->getValue());
        $this->assertSame(88.0, $sheet->getCell('V2')->getValue());
        $this->assertSame(99.0, $sheet->getCell('W2')->getValue());
    }

    public function test_banyak_tanah_menghasilkan_satu_baris_masing_masing(): void
    {
        $daftar = [
            $this->tanah('KIB-1', [$this->retribusi(1, 2026, 10, 1)]),
            $this->tanah('KIB-2'), // tanpa retribusi
            $this->tanah('KIB-3', [$this->retribusi(2, 2026, 30, 3)]),
        ];

        $sheet = $this->sheet($daftar, 2026);

        $this->assertSame(4, $sheet->getHighestRow()); // header + 3
        $this->assertSame('KIB-2', $sheet->getCell('B3')->getValue());
        $this->assertNull($sheet->getCell('T3')->getValue());
    }

    /** 6. Filter pencarian tetap menghasilkan klausa LIKE untuk semua kolom. */
    public function test_filter_pencarian_membentuk_klausa_like_semua_kolom(): void
    {
        $query = Tanah::query();

        self::callStatic('terapkanPencarian', [$query, 'kantor']);

        $this->assertSame(9, substr_count(strtolower($query->toSql()), 'like'));
        $this->assertCount(9, $query->getBindings());
        foreach ($query->getBindings() as $binding) {
            $this->assertSame('%kantor%', $binding);
        }
    }
}
