<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Unit organisasi tunggal dinas: mencakup Sekretariat, Bidang-bidang,
 * dan UPT. Jenisnya ditandai oleh kolom `jenis_skpd`.
 */
class Skpd extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'skpd';
    use HasFactory;

    protected $primaryKey = 'id_skpd';

    protected $fillable = ['nama_skpd', 'jenis_skpd'];

    public $timestamps = false;

    public function pegawai()
    {
        return $this->hasMany(Pegawai::class, 'id_skpd');
    }

    public function ruangan()
    {
        return $this->hasMany(Ruangan::class, 'id_skpd');
    }

    /**
     * Lokasi fisik kantor skpd ini. Hanya terisi untuk skpd UPT; skpd
     * Sekretariat & Bidang berkantor di lokasi dinas (lihat Lokasi::dinas()).
     */
    public function lokasi(): HasOne
    {
        return $this->hasOne(Lokasi::class, 'id_skpd');
    }

    public function isUpt(): bool
    {
        return $this->jenis_skpd === 'UPT';
    }

    public function isBidang(): bool
    {
        return $this->jenis_skpd === 'Bidang';
    }

    public function isSekretariat(): bool
    {
        return $this->jenis_skpd === 'Sekretariat';
    }

    /**
     * Lokasi fisik kantor skpd ini (untuk UPT), atau lokasi kantor dinas
     * untuk Sekretariat & Bidang.
     */
    public function lokasiKerja(): ?Lokasi
    {
        return $this->lokasi ?? Lokasi::dinas();
    }

    public function scopeUpt($query)
    {
        return $query->where('jenis_skpd', 'UPT');
    }

    public function scopeUnitOrganisasi($query)
    {
        return $query->whereNotNull('jenis_skpd');
    }
}
