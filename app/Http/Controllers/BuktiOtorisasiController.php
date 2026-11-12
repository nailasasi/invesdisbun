<?php

namespace App\Http\Controllers;

use App\Models\TanahHistory;
use App\Services\BuktiOtorisasi;
use App\Services\TanahScope;
use Illuminate\Support\Facades\Storage;

class BuktiOtorisasiController extends Controller
{
    /**
     * Gerbang modul Tanah (Admin Aset / Admin UPT P2BTP), senada controller
     * Tanah lain. Deny by default.
     */
    public function __construct(private readonly TanahScope $tanahScope)
    {
        if (auth()->check()) {
            abort_unless(
                $this->tanahScope->bolehAkses(),
                403,
                'Anda tidak memiliki hak akses untuk modul data tanah.'
            );
        }
    }

    /**
     * Menyajikan file bukti otorisasi dari disk PRIVAT lewat route
     * terautentikasi. Foto tidak pernah dapat diakses lewat URL publik;
     * pengguna harus lolos pemeriksaan hak akses (TanahScope) terlebih dahulu.
     */
    public function show(TanahHistory $history)
    {
        abort_unless(
            $this->tanahScope->bolehAkses(),
            403,
            'Anda tidak memiliki hak akses untuk melihat bukti ini.'
        );

        $path = $history->foto_bukti;

        abort_if(blank($path), 404, 'Bukti tidak tersedia.');

        $disk = Storage::disk(BuktiOtorisasi::DISK);

        abort_unless($disk->exists($path), 404, 'File bukti tidak ditemukan.');

        return $disk->response($path);
    }
}
