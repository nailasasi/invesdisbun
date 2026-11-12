<?php

namespace App\Models;

use App\Services\BuktiOtorisasi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TanahHistory extends Model
{
    protected $table = 'tanah_histories';

    protected $primaryKey = 'id_history';

    protected $fillable = [
        'id_tanah',
        'id_user',
        'id_retribusi',
        'nama_aktor',
        'aksi',
        'objek',
        'foto_bukti',
        'data_lama',
        'data_baru',
    ];

    protected $casts = [
        'data_lama' => 'array',
        'data_baru' => 'array',
    ];

    /**
     * Saat baris riwayat dihapus, ikut hapus file bukti yang direferensikan.
     * Catatan: cascade delete di level database (mis. saat Tanah dihapus)
     * TIDAK memicu event model ini, sehingga file tidak ikut terhapus pada
     * jalur tersebut (perilaku lama dipertahankan; belum ada hapus Tanah).
     */
    protected static function booted(): void
    {
        static::deleting(function (TanahHistory $history) {
            if ($history->foto_bukti) {
                Storage::disk(BuktiOtorisasi::DISK)->delete($history->foto_bukti);
            }
        });
    }

    public function tanah()
    {
        return $this->belongsTo(
            Tanah::class,
            'id_tanah'
        );
    }

    public function user()
    {
        return $this->belongsTo(
            User::class,
            'id_user'
        );
    }

    public function retribusi()
    {
        return $this->belongsTo(
            RetribusiTanah::class,
            'id_retribusi'
        );
    }

    /**
     * Identitas aktor untuk tampilan: utamakan snapshot `nama_aktor` agar
     * tetap terbaca walau relasi user sudah berubah/terhapus.
     */
    public function getNamaAktorTampilAttribute(): string
    {
        $snapshot = trim((string) ($this->nama_aktor ?? ''));
        if ($snapshot !== '') {
            return $snapshot;
        }

        return $this->user?->pegawai?->nama_pegawai
            ?? $this->user?->username
            ?? 'Pengguna Sistem';
    }

    /**
     * Judul aktivitas yang mudah dibaca, berdasarkan objek + aksi.
     */
    public function getJudulAktivitasAttribute(): string
    {
        $aksi = strtoupper((string) $this->aksi);
        $objek = strtolower((string) ($this->objek ?? 'tanah'));

        return [
            'tanah.CREATE' => 'Tambah Tanah',
            'tanah.UPDATE' => 'Edit Tanah',
            'retribusi.CREATE' => 'Tambah Retribusi',
            'retribusi.UPDATE' => 'Edit Retribusi',
            'retribusi.DELETE' => 'Hapus Retribusi',
            'dokumen_pbb.DELETE' => 'Hapus Dokumen PBB',
        ][$objek.'.'.$aksi] ?? ($objek === 'retribusi' ? 'Perubahan Retribusi' : ($objek === 'dokumen_pbb' ? 'Perubahan Dokumen PBB' : 'Perubahan Tanah'));
    }

    /**
     * Daftar field yang berubah (untuk tampilan "sebelum -> sesudah").
     * Hanya field yang nilainya berbeda yang dikembalikan.
     *
     * @return array<int, array{field: string, lama: mixed, baru: mixed}>
     */
    public function getPerubahanTampilAttribute(): array
    {
        $lama = is_array($this->data_lama) ? $this->data_lama : [];
        $baru = is_array($this->data_baru) ? $this->data_baru : [];

        $fields = array_unique(array_merge(array_keys($lama), array_keys($baru)));

        $hasil = [];
        foreach ($fields as $field) {
            $nilaiLama = $lama[$field] ?? null;
            $nilaiBaru = $baru[$field] ?? null;

            if ($this->normalisasiBanding($nilaiLama) === $this->normalisasiBanding($nilaiBaru)) {
                continue;
            }

            $hasil[] = [
                'field' => $field,
                'lama' => $nilaiLama,
                'baru' => $nilaiBaru,
            ];
        }

        return $hasil;
    }

    private function normalisasiBanding(mixed $nilai): string
    {
        if (is_bool($nilai)) {
            return $nilai ? '1' : '0';
        }

        if ($nilai === null) {
            return '';
        }

        if (is_array($nilai)) {
            return json_encode($nilai);
        }

        return (string) $nilai;
    }
}
