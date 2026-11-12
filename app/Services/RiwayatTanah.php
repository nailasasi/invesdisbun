<?php

namespace App\Services;

use App\Models\Tanah;
use App\Models\TanahHistory;

/**
 * Pencatatan riwayat aktivitas modul Tanah.
 *
 * Semua nilai sensitif (id_user, nama_aktor) diambil dari SERVER
 * (pengguna terautentikasi), bukan dari input pengguna. Timestamp resmi
 * memakai `created_at` Eloquent (waktu server, zona Asia/Jakarta).
 */
class RiwayatTanah
{
    /**
     * Catat satu aktivitas ke `tanah_histories`.
     *
     * @param  array<string, mixed>|null  $lama  data sebelum perubahan
     * @param  array<string, mixed>|null  $baru  data sesudah perubahan
     */
    public function catat(
        Tanah $tanah,
        string $aksi,
        string $objek = 'tanah',
        ?int $idRetribusi = null,
        ?array $lama = null,
        ?array $baru = null,
        ?string $fotoBukti = null,
    ): TanahHistory {
        return TanahHistory::create([
            'id_tanah' => $tanah->id_tanah,
            'id_user' => auth()->id(),
            'id_retribusi' => $idRetribusi,
            'nama_aktor' => $this->namaAktor(),
            'aksi' => strtoupper($aksi),
            'objek' => $objek,
            'foto_bukti' => $fotoBukti,
            'data_lama' => $lama,
            'data_baru' => $baru,
        ]);
    }

    /**
     * Snapshot nama aktor saat aksi dilakukan (server-side).
     */
    public function namaAktor(): string
    {
        $user = auth()->user();

        return trim((string) (
            $user?->pegawai?->nama_pegawai
            ?? $user?->username
            ?? 'Pengguna Sistem'
        )) ?: 'Pengguna Sistem';
    }
}
