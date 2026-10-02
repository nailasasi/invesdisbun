<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lokasi fisik tempat barang berada.
 *
 * Berbeda dengan Skpd: skpd adalah unit organisasi yang bertanggung jawab,
 * lokasi adalah tempat fisiknya. Aset milik Bidang dapat berada di UPT
 * dan sebaliknya.
 */
class Lokasi extends Model
{
    protected $table = 'lokasi';

    protected $primaryKey = 'id_lokasi';

    protected $fillable = [
        'id_skpd',
        'nama_lokasi',
        'jenis_lokasi',
        'keterangan',
        'status',
    ];

    protected function casts(): array
    {
        return [];
    }

    public function ruangan(): HasMany
    {
        return $this->hasMany(Ruangan::class, 'id_lokasi');
    }

    public function aset(): HasMany
    {
        return $this->hasMany(Aset::class, 'id_lokasi');
    }

    /**
     * Skpd pemilik lokasi ini (hanya diisi untuk lokasi UPT).
     * Lokasi Dinas tidak dimiliki satu skpd — dipakai seluruh Sekretariat
     * dan Bidang.
     */
    public function skpd(): BelongsTo
    {
        return $this->belongsTo(Skpd::class, 'id_skpd');
    }

    /**
     * Lokasi kerja kantor dinas (Sekretariat & seluruh Bidang).
     */
    public static function dinas(): ?self
    {
        return static::where('jenis_lokasi', 'Dinas')->where('status', 'Aktif')->first()
            ?? static::where('jenis_lokasi', 'Dinas')->first();
    }

    /**
     * Lokasi kerja untuk skpd tertentu.
     *
     * - Skpd UPT -> lokasi kantor UPT tsb (lewat relasi id_skpd)
     * - Skpd lain (Sekretariat & Bidang) -> lokasi kantor dinas
     *
     * Pemetaan memakai relasi, bukan pencocokan nama, sehingga rename
     * lokasi tidak merusak hak akses.
     */
    public static function untukSkpd(?int $idSkpd): ?self
    {
        if (! $idSkpd) {
            return static::dinas();
        }

        $skpd = Skpd::find($idSkpd);

        if ($skpd?->isUpt()) {
            return static::where('id_skpd', $idSkpd)->first()
                // Fallback untuk data lama yang belum dipetakan relasinya.
                ?? static::where('nama_lokasi', 'Kantor '.$skpd->nama_skpd)->first();
        }

        return static::dinas();
    }

    /**
     * Lokasi fisik yang sah untuk seorang pegawai — diturunkan dari
     * skpd pegawai tersebut.
     */
    public static function untukPegawai(?Pegawai $pegawai): ?self
    {
        return static::untukSkpd($pegawai?->id_skpd);
    }
}
