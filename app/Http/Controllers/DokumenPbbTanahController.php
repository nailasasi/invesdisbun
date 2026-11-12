<?php

namespace App\Http\Controllers;

use App\Models\Tanah;
use App\Models\DokumenPbbTanah;
use App\Models\DokumenPbbHistory;
use App\Rules\FotoKameraValid;
use App\Services\BuktiOtorisasi;
use App\Services\RiwayatTanah;
use App\Services\TanahScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class DokumenPbbTanahController extends Controller
{
    /**
     * Gerbang modul Tanah: Admin Aset atau Admin UPT P2BTP saja
     * (deny by default untuk seluruh method).
     */
    public function __construct(
        private readonly TanahScope $tanahScope,
        private readonly BuktiOtorisasi $bukti,
        private readonly RiwayatTanah $riwayat,
    ) {
        // Guard auth()->check() — lihat TanahController (CLI/route:list).
        if (auth()->check()) {
            abort_unless(
                $this->tanahScope->bolehAkses(),
                403,
                'Anda tidak memiliki hak akses untuk modul data tanah.'
            );
        }
    }

    public function store(Request $request, Tanah $tanah)
    {

        $validated = $request->validate([
            'tahun_pbb' => [
                'nullable',
                'integer'
            ],

            'file_pbb' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120'
            ],
        ]);


        $file = $request->file('file_pbb');

        $path = $file->store(
            'dokumen-pbb',
            'public'
        );


        $pbb = DokumenPbbTanah::create([
            'id_tanah' => $tanah->id_tanah,
            'tahun_pbb' => $request->tahun_pbb,
            'file_pbb' => $path,
            'tanggal_upload' => now(),
            'uploaded_by' => auth()->id(),
        ]);


        DokumenPbbHistory::create([
            'id_tanah' => $tanah->id_tanah,
            'id_pbb' => $pbb->id_pbb,
            'id_user' => auth()->id(),
            'aksi' => 'UPLOAD',
            'nama_file' => basename($path),
        ]);


        return redirect()
            ->route('tanah.show',$tanah)
            ->with(
                'success',
                'Dokumen PBB berhasil ditambahkan'
            );
    }



    public function destroy(Request $request, DokumenPbbTanah $dokumen)
    {
        // Penghapusan dokumen PBB adalah aksi sensitif: WAJIB konfirmasi ulang password
        // akun yang sedang login + foto bukti kamera langsung.
        $validator = Validator::make($request->only(['password_otorisasi', 'foto_bukti_otentikasi']), [
            'password_otorisasi' => ['required', 'current_password:web'],
            'foto_bukti_otentikasi' => ['required', 'string', new FotoKameraValid],
        ], [
            'password_otorisasi.required' => 'Password otorisasi wajib diisi.',
            'password_otorisasi.current_password' => 'Password otorisasi tidak cocok dengan akun Anda.',
            'foto_bukti_otentikasi.required' => 'Silakan ambil foto konfirmasi terlebih dahulu sebelum menyimpan.',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('tanah.show', $dokumen->id_tanah)
                ->withErrors($validator)
                ->with('hapus_pbb_gagal_id', $dokumen->id_pbb);
        }

        $tanah = $dokumen->tanah;
        $dataLama = $dokumen->toArray();
        $filePbbLama = $dokumen->file_pbb;

        try {
            $fotoBukti = $this->bukti->simpan($request->input('foto_bukti_otentikasi'));
        } catch (\Throwable $e) {
            return redirect()
                ->route('tanah.show', $dokumen->id_tanah)
                ->withErrors(['foto_bukti_otentikasi' => 'Foto bukti gagal disimpan. Silakan ambil foto ulang.'])
                ->with('hapus_pbb_gagal_id', $dokumen->id_pbb);
        }

        try {
            DB::transaction(function () use ($dokumen, $tanah, $dataLama, $fotoBukti) {
                $idPbb = $dokumen->id_pbb;

                DokumenPbbHistory::create([
                    'id_tanah' => $tanah->id_tanah,
                    'id_pbb' => $idPbb,
                    'id_user' => auth()->id(),
                    'aksi' => 'DELETE',
                    'nama_file' => basename($dokumen->file_pbb),
                ]);

                $this->riwayat->catat(
                    $tanah,
                    'DELETE',
                    'dokumen_pbb',
                    null,
                    $dataLama,
                    null,
                    $fotoBukti
                );

                $dokumen->delete();
            });
        } catch (\Throwable $e) {
            $this->bukti->hapus($fotoBukti);

            return redirect()
                ->route('tanah.show', $dokumen->id_tanah)
                ->withErrors(['foto_bukti_otentikasi' => 'Data gagal dihapus sehingga foto bukti dibatalkan. Silakan coba lagi.'])
                ->with('hapus_pbb_gagal_id', $dokumen->id_pbb);
        }

        if ($filePbbLama && Storage::disk('public')->exists($filePbbLama)) {
            // Pastikan tidak digunakan oleh dokumen PBB lain
            $dipakaiLain = DokumenPbbTanah::where('id_pbb', '!=', $dokumen->id_pbb)
                ->where('file_pbb', $filePbbLama)
                ->exists();
            if (! $dipakaiLain) {
                Storage::disk('public')->delete($filePbbLama);
            }
        }

        return redirect()
            ->route('tanah.show', $tanah)
            ->with(
                'success',
                'Dokumen PBB berhasil dihapus'
            );
    }

}