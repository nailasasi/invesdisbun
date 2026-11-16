<?php

namespace App\Http\Controllers;

use App\Models\Aset;
use App\Models\Pegawai;
use App\Models\TemplateDokumen;
use App\Services\AsetPenempatan;
use App\Services\AsetScope;
use App\Support\Word\SppbiTemplate;
use App\Support\Word\WordTemplateFiller;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DokumenSppbiController extends Controller
{
    public function __construct(private readonly AsetScope $scope) {}

    /**
     * Unduh SPPBI (.docx) dari template master (khusus Admin Aset).
     *
     * SPPBI hanya boleh dibuat untuk aset yang penempatannya sudah final:
     * penempatan aktif + `is_finalized = 1` (Sudah Difinalisasi) + ruangan
     * aktif. Validasi ini diulang di server (bukan hanya menyembunyikan
     * tombol), sehingga akses langsung ke URL SPPBI tetap ditolak.
     *
     * Aset yang penempatannya belum final TIDAK ikut masuk ke dokumen; bila
     * tidak ada satu pun aset yang memenuhi syarat, permintaan ditolak.
     *
     * Template aktif memakai placeholder SPPBI baru (NAMA ASN, NIP ASN,
     * Jabatan ASN, Tanggal/Bulan/Tahun, Jenis Barang, Merk Barang,
     * Type Barang, Kode Barang, Tahun Pengadaan, Jumlah, Nomor Register,
     * Harga Satuan) plus tanggal surat & Pakta Integritas. Generator mengisi
     * seluruh placeholder tersebut termasuk terbilang harga satuan dan
     * pengulangan blok spesifikasi per barang.
     */
    public function downloadWord(Pegawai $pegawai)
    {
        $this->authorizeAdminAset();

        // Kolom finalisasi belum ada di database aktif sampai migration
        // dijalankan. Tanpa kolom itu, SPPBI belum boleh dibuat.
        abort_unless(
            AsetPenempatan::kolomFinalisasiTersedia(),
            422,
            'SPPBI belum dapat dibuat: kolom finalisasi penempatan belum tersedia di database.'
        );

        $template = TemplateDokumen::where('kode_template', 'sppbi')->first();
        if (! $template || ! $template->fileExists()) {
            return back()->with('error', 'Template dokumen SPPBI belum diunggah di Pengaturan Dokumen.');
        }

        $pegawai->load(['skpd', 'ruangan']);

        $asetList = $this->asetSppbiPemegang($pegawai);

        abort_if(
            $asetList->isEmpty(),
            422,
            'SPPBI hanya tersedia setelah penempatan aset difinalisasi (status Sudah Difinalisasi).'
        );

        $tanggal = Carbon::now()->locale('id');

        // Baris spesifikasi barang: satu blok per aset yang memenuhi syarat.
        $rows = $asetList->map(function (Aset $aset) {
            $tanggalTercatat = $this->tanggalTercatat($aset);

            return [
                'jenis'  => $aset?->barang?->nama_barang ?? '-',
                'merk'   => $aset?->merk ?? '-',
                'type'   => $aset?->barang?->kode_barang ?: '-',
                'tanggal' => $tanggalTercatat ? $tanggalTercatat->format('d') : '-',
                'bulan'   => $tanggalTercatat ? $tanggalTercatat->locale('id')->translatedFormat('F') : '-',
                'tahun'   => $tanggalTercatat ? $tanggalTercatat->format('Y') : '-',
                'kode'   => $aset?->nomor_kartu_barang ?? '-',
                'tahun_pengadaan' => $this->tahunPengadaan($aset),
                'jumlah' => 1,
                'register' => $aset?->nomor_kartu_barang ?? '-',
                'harga'  => $this->hargaSatuan($aset),
                'nominal' => $aset?->nilai_perolehan ?? null,
            ];
        })->all();

        $nomorSurat = $this->nomorSuratOtomatis($pegawai);

        // Nilai identitas ASN & tanggal surat, dipetakan lewat kontrak
        // placeholder template (App\Support\Word\SppbiTemplate) supaya token
        // dan sumber data tidak pernah terpisah.
        $data = [
            'nama_asn' => $pegawai->nama_pegawai,
            'nip_asn' => $pegawai->nip ?? '-',
            'jabatan_asn' => $pegawai->jabatan ?? '-',
            'tanggal_surat' => $tanggal->translatedFormat('d F Y'),
        ];

        $values = [];
        foreach (SppbiTemplate::VALUE_TOKENS as $token => $key) {
            $values[$token] = $data[$key];
        }

        $filePath = WordTemplateFiller::make($template->realPath())->render(
            values: $values,
            rows: [],
            options: [
                'blocks' => [
                    [
                        'start_marker' => SppbiTemplate::BLOCK_START,
                        'end_marker' => SppbiTemplate::BLOCK_END,
                        'currency_token' => SppbiTemplate::CURRENCY_TOKEN,
                        'tokens' => SppbiTemplate::BLOCK_TOKENS,
                        'rows' => $rows,
                    ],
                ],
                'patterns' => [
                    // Nomor surat tertanam langsung di template (tanpa placeholder),
                    // mis. "Nomor : 000.1.4/00000/121.1/2026". Pola mengizinkan
                    // spasi opsional di sekitar titik dua.
                    '~Nomor\s*:\s*[0-9][0-9A-Za-z./\-]*~' => 'Nomor : '.$nomorSurat,
                ],
            ],
        );

        $fileName = 'SPPBI_'.Str::slug($pegawai->nama_pegawai, '_').'.docx';

        return response()->download($filePath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Tanggal "Tgl tercatat" untuk satu aset: pakai tanggal pengadaan, lalu
     * tanggal penempatan aktif.
     */
    private function tanggalTercatat(?Aset $aset): ?Carbon
    {
        foreach ([
            $aset?->tanggal_pengadaan,
            $aset?->penempatanAktif?->tanggal_mulai,
        ] as $tanggal) {
            if ($tanggal) {
                return Carbon::parse($tanggal);
            }
        }

        return null;
    }

    /**
     * Daftar aset yang layak masuk SPPBI seorang pegawai.
     *
     * SUMBER DATA: aset yang PEMEGANG AKTIFNYA saat dokumen dibuat adalah
     * pegawai tersebut. Relasi `pemegangSaatIni` (Aset -> PemegangAset status
     * 'aktif') adalah sumber kebenaran satu-satunya, sehingga:
     *   1. aset yang sudah berpindah pemegang (mutasi Ganti Pemegang)
     *      otomatis keluar dari SPPBI pemegang lama,
     *   2. aset masuk ke SPPBI pemegang baru begitu mutasi selesai,
     *   3. satu aset hanya bisa muncul satu kali (relasi hasOne), sehingga
     *      riwayat pemegang yang menumpuk tidak membuat aset dobel.
     *
     * SYARAT KELAYAKAN (tidak berubah): bukan kendaraan, berada dalam
     * AsetScope user, penempatan aktif SUDAH DIFINALISASI, dan ruangannya
     * berstatus Aktif.
     *
     * @return \Illuminate\Support\Collection<int, Aset>
     */
    public function asetSppbiPemegang(Pegawai $pegawai): Collection
    {
        return Aset::with(['barang', 'penempatanAktif.ruangan'])
            ->where('is_kendaraan', false)
            ->whereHas('pemegangSaatIni', fn ($q) => $q->where('id_pegawai', $pegawai->id_pegawai))
            ->get()
            ->filter(function (Aset $aset) {
                if (! $this->scope->bolehAksesAset($aset)) {
                    return false;
                }

                return AsetPenempatan::penempatanFinalValid($aset->penempatanAktif);
            })
            ->values();
    }

    /**
     * Tahun anggaran/pengadaan: tahun dari tanggal pengadaan.
     */
    private function tahunPengadaan(?Aset $aset): string
    {
        $tanggal = $aset?->tanggal_pengadaan;

        return $tanggal ? Carbon::parse($tanggal)->format('Y') : '-';
    }

    /**
     * Harga satuan sebagai angka tanpa pemisah ribuan (mis. 17300993700).
     *
     * Aset tanpa nilai perolehan memakai '-' agar tidak dibaca sebagai
     * aset bernilai nol.
     */
    private function hargaSatuan(?Aset $aset): string
    {
        $nilai = $aset?->nilai_perolehan;

        if ($nilai === null || $nilai === '') {
            return '-';
        }

        return number_format((float) $nilai, 0, '', '');
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

        // Hanya role final "Admin Aset". Role legacy "admin" TIDAK lagi
        // diterima (tidak ada di tabel role; keputusan Fase 1B).
        return is_string($role)
            && in_array(Str::lower(str_replace('_', ' ', trim($role))), ['admin aset'], true);
    }

    private function authorizeAdminAset(): void
    {
        abort_unless($this->isAdminAset(), 403, 'Hanya Admin Aset yang dapat mengelola dokumen SPPBI.');
    }
}
