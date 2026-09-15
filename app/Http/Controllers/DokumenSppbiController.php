<?php

namespace App\Http\Controllers;

use App\Models\DokumenSppbi;
use App\Models\Pegawai;
use App\Models\PemegangAset;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;

class DokumenSppbiController extends Controller
{
    /**
     * Tampilan cetak format resmi browser / PDF (semua barang aktif).
     */
    public function print(Pegawai $pegawai)
    {
        Carbon::setLocale('id');
        $pegawai->load(['skpd', 'ruangan']);

        $asetList = PemegangAset::with(['aset.barang'])
            ->where('id_pegawai', $pegawai->id_pegawai)
            ->where('status', 'aktif')
            ->whereHas('aset', fn ($q) => $q->where('is_kendaraan', false))
            ->get();

        $sppbi = $pegawai->sppbiAktif;

        return view('aset-barang.sppbi-print', compact('pegawai', 'asetList', 'sppbi'));
    }

    /**
     * Perbarui nomor SPPBI & upload dokumen bertanda tangan (khusus Admin Aset).
     */
    public function updateOrCreate(Request $request, Pegawai $pegawai)
    {
        $request->validate([
            'nomor_surat' => ['required', 'string', 'max:100'],
            'tanggal_surat' => ['required', 'date'],
            'file_dokumen' => ['nullable', 'file', 'mimes:pdf,jpg,png', 'max:5120'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        // Arsipkan SPPBI aktif sebelumnya jika ada perubahan dokumen
        DokumenSppbi::where('id_pegawai', $pegawai->id_pegawai)
            ->where('status', 'aktif')
            ->update(['status' => 'arsip']);

        $filePath = null;
        if ($request->hasFile('file_dokumen')) {
            $filePath = $request->file('file_dokumen')->store('dokumen-sppbi', 'public');
        }

        DokumenSppbi::create([
            'id_pegawai' => $pegawai->id_pegawai,
            'nomor_surat' => $request->input('nomor_surat'),
            'tanggal_surat' => $request->input('tanggal_surat'),
            'file_path' => $filePath,
            'status' => 'aktif',
            'catatan' => $request->input('catatan'),
            'id_user_penginput' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Dokumen SPPBI berhasil diperbarui.',
        ]);
    }

    /**
     * Unduh berkas Word (.docx) SPPBI yang digenerate langsung dari data
     * (tanpa dependensi template yang diunggah admin).
     */
    public function downloadWord(Pegawai $pegawai)
    {
        $pegawai->load(['ruangan']);

        $asetList = PemegangAset::with(['aset.barang'])
            ->where('id_pegawai', $pegawai->id_pegawai)
            ->where('status', 'aktif')
            ->whereHas('aset', fn ($q) => $q->where('is_kendaraan', false))
            ->get();

        $now = Carbon::now()->locale('id');
        $sppbi = $pegawai->sppbiAktif;

        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(12);

        $section = $phpWord->addSection([
            'marginTop' => 720,
            'marginBottom' => 720,
            'marginLeft' => 1000,
            'marginRight' => 1000,
        ]);

        $center = ['alignment' => Jc::CENTER];

        // Kop surat
        $section->addText('PEMERINTAH PROVINSI JAWA TIMUR', ['bold' => true], $center);
        $section->addText('DINAS PERKEBUNAN', ['bold' => true], $center);
        $section->addText('SURAT PENUNJUKAN PEMEGANG BARANG INVENTARIS (SPPBI)', ['bold' => true, 'size' => 11], $center);
        $section->addText('Nomor: ' . ($sppbi?->nomor_surat ?? '......./SPPBI/' . $now->year), ['size' => 11], $center);

        $section->addTextBreak(1);
        $section->addText('Yang bertanda tangan di bawah ini menerangkan bahwa barang inventaris dinas berikut:');

        // Tabel barang
        $tableStyle = [
            'borderSize' => 4,
            'borderColor' => '000000',
            'cellMargin' => 60,
        ];
        $header = ['bold' => true, 'size' => 11];
        $table = $section->addTable($tableStyle);
        $table->addRow();
        $table->addCell(600)->addText('No', $header, ['alignment' => Jc::CENTER]);
        $table->addCell(3000)->addText('Nama Barang', $header);
        $table->addCell(2400)->addText('No. Register / Kartu', $header);
        $table->addCell(2400)->addText('Merk / Tipe', $header);
        $table->addCell(2000)->addText('Kondisi', $header);

        if ($asetList->isEmpty()) {
            $table->addRow();
            $table->addCell(600)->addText('1', null, ['alignment' => Jc::CENTER]);
            $table->addCell(9800, null, ['gridSpan' => 4])->addText('-');
        } else {
            foreach ($asetList as $i => $item) {
                $aset = $item->aset;
                $table->addRow();
                $table->addCell(600)->addText($i + 1, null, ['alignment' => Jc::CENTER]);
                $table->addCell(3000)->addText($aset->barang?->nama_barang ?? '-');
                $table->addCell(2400)->addText($aset->nomor_kartu_barang ?? '-');
                $table->addCell(2400)->addText($aset->merk ?? '-');
                $table->addCell(2000)->addText($aset->kondisi ?? '-');
            }
        }

        $section->addTextBreak(1);
        $section->addText('Diserahkan sebagai penanggung jawab pemegang barang dinas kepada:');
        $section->addText('Nama Pegawai        : ' . $pegawai->nama_pegawai);
        $section->addText('NIP                        : ' . ($pegawai->nip ?? '-'));
        $section->addText('Jabatan                 : ' . ($pegawai->jabatan ?? '-'));
        $section->addText('Unit Kerja / Ruangan : ' . ($pegawai->ruangan?->nama_ruangan ?? '-'));

        $section->addTextBreak(2);

        // Blok tanda tangan (dua kolom)
        $left = [
            'Penerima / Pemegang,',
            '',
            '',
            $pegawai->nama_pegawai,
            'NIP. ' . ($pegawai->nip ?? '........................'),
        ];
        $right = [
            'Surabaya, ' . ($sppbi?->tanggal_surat?->translatedFormat('d F Y') ?? $now->translatedFormat('d F Y')),
            'Pengurus Barang / Admin Aset,',
            '',
            '',
            'Achmar Adrian Ramadhan, A.Md.',
            'NIP. 19991223 202504 1 006',
        ];

        $sigTable = $section->addTable(['cellMargin' => 60]);
        $sigTable->addRow();
        $col1 = $sigTable->addCell(5200);
        $col2 = $sigTable->addCell(5200);
        foreach ($left as $line) {
            $col1->addText($line, $line === $pegawai->nama_pegawai ? ['bold' => true, 'size' => 11] : ['size' => 11], ['alignment' => Jc::CENTER]);
        }
        foreach ($right as $line) {
            $col2->addText($line, $line === 'Achmar Adrian Ramadhan, A.Md.' ? ['bold' => true, 'size' => 11] : ['size' => 11], ['alignment' => Jc::CENTER]);
        }

        // Simpan ke file temporer (.docx) lalu unduh
        $filePath = sys_get_temp_dir() . '/SPPBI_' . uniqid() . '.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($filePath);

        $fileName = 'SPPBI_' . str_replace(' ', '_', $pegawai->nama_pegawai) . '.docx';

        return response()->download($filePath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }
}