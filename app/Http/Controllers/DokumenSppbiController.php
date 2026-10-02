<?php

namespace App\Http\Controllers;

use App\Models\DokumenSppbi;
use App\Models\Pegawai;
use App\Models\PemegangAset;
use App\Models\TemplateDokumen;
use App\Support\Word\WordTemplateFiller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DokumenSppbiController extends Controller
{
    /**
     * Upload berkas hasil scan tanda tangan basah SPPBI (khusus Admin Aset).
     * Satu dokumen aktif per pegawai; berkas lama dihapus lalu record diperbarui.
     */
    public function uploadTtd(Request $request, Pegawai $pegawai)
    {
        $this->authorizeAdminAset();

        $request->validate([
            'file_ttd' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $dokumen = $pegawai->dokumenSppbi;

        if ($dokumen?->file_path) {
            Storage::disk('public')->delete($dokumen->file_path);
        }

        $pathBaru = $request->file('file_ttd')->store('dokumen_sppbi', 'public');

        if ($pathBaru === false) {
            return back()->with('error', 'Berkas gagal disimpan di server. Silakan coba lagi.');
        }

        DokumenSppbi::updateOrCreate(
            ['id_pegawai' => $pegawai->id_pegawai],
            [
                'nomor_surat' => $this->nomorSuratOtomatis($pegawai),
                'tanggal_surat' => Carbon::now()->toDateString(),
                'file_path' => $pathBaru,
                'status' => 'aktif',
                'id_user_penginput' => auth()->id(),
            ]
        );

        return back()->with('success', 'Berkas tanda tangan SPPBI berhasil diunggah.');
    }

    /**
     * Unduh SPPBI (.docx) dari template master (khusus Admin Aset).
     */
    public function downloadWord(Pegawai $pegawai)
    {
        $this->authorizeAdminAset();

        $template = TemplateDokumen::where('kode_template', 'sppbi')->first();
        if (! $template || ! $template->file_path || ! Storage::disk('public')->exists($template->file_path)) {
            return back()->with('error', 'Template dokumen SPPBI belum diunggah di Pengaturan Dokumen.');
        }

        $pegawai->load(['skpd', 'ruangan']);

        $asetList = PemegangAset::with(['aset.barang'])
            ->where('id_pegawai', $pegawai->id_pegawai)
            ->where('status', 'aktif')
            ->whereHas('aset', fn ($q) => $q->where('is_kendaraan', false))
            ->get();

        $tanggal = Carbon::now()->locale('id');
        $nomorSurat = $this->nomorSuratOtomatis($pegawai);
        $pengurus = auth()->user()?->pegawai;

        $rows = $asetList->map(fn (PemegangAset $item) => [
            'nama' => $item->aset?->barang?->nama_barang ?? '-',
            'merk' => $item->aset?->merk ?? '-',
            'tahun' => $item->aset?->tanggal_perolehan
                ? Carbon::parse($item->aset->tanggal_perolehan)->format('Y')
                : '-',
            'kode' => $item->aset?->nomor_kartu_barang ?? '-',
        ])->all();

        if ($rows === []) {
            $rows = [['nama' => '-', 'merk' => '-', 'tahun' => '-', 'kode' => '-']];
        }

        $filePath = WordTemplateFiller::make(storage_path('app/public/'.$template->file_path))->render(
            values: [
                'hari' => $tanggal->translatedFormat('l'),
                'tanggal_terbilang' => WordTemplateFiller::terbilang((int) $tanggal->format('d')),
                'bulan' => $tanggal->translatedFormat('F'),
                'tahun_terbilang' => WordTemplateFiller::terbilang((int) $tanggal->format('Y')),
                'tanggal_surat' => $tanggal->translatedFormat('d F Y'),
                'nomor_surat' => $nomorSurat,
                'nama_pihak_pertama' => $pengurus?->nama_pegawai ?? '-',
                'nip_pihak_pertama' => $pengurus?->nip ?? '-',
                'jabatan_pihak_pertama' => $pengurus?->jabatan ?? 'Pengurus Barang',
                'alamat_pihak_pertama' => $pengurus?->ruangan?->nama_ruangan ?? '-',
                'nama_pihak_kedua' => $pegawai->nama_pegawai,
                'nip_pihak_kedua' => $pegawai->nip ?? '-',
                'jabatan_pihak_kedua' => $pegawai->jabatan ?? '-',
                'alamat_pihak_kedua' => $pegawai->ruangan?->nama_ruangan ?? '-',
                'unit' => $pegawai->ruangan?->nama_ruangan ?? '-',
                'skpd' => $pegawai->skpd?->nama_skpd ?? '-',
            ],
            rows: $rows,
            options: [
                'row_macro' => 'barang',
                'row_tokens' => [
                    'nama_barang' => 'nama',
                    'merk_barang' => 'merk',
                    'tahun_pengadaan' => 'tahun',
                    'kode_barang' => 'kode',
                ],
                'patterns' => [
                    '~Nomor:\s*[0-9][0-9A-Za-z./\-]*~' => 'Nomor: '.$nomorSurat,
                ],
            ],
        );

        $fileName = 'SPPBI_'.Str::slug($pegawai->nama_pegawai, '_').'.docx';

        return response()->download($filePath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Nomor surat SPPBI otomatis, mis. 001/SPPBI/DISBUN/2026.
     */
    private function nomorSuratOtomatis(Pegawai $pegawai): string
    {
        return sprintf('%03d/SPPBI/DISBUN/%s', $pegawai->id_pegawai, Carbon::now()->format('Y'));
    }

    private function isAdminAset(): bool
    {
        $role = auth()->user()?->role?->nama_role;

        return is_string($role)
            && in_array(Str::lower(str_replace('_', ' ', trim($role))), ['admin aset', 'admin'], true);
    }

    private function authorizeAdminAset(): void
    {
        abort_unless($this->isAdminAset(), 403, 'Hanya Admin Aset yang dapat mengelola dokumen SPPBI.');
    }
}
