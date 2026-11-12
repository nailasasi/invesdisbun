<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Inventaris lokal ruangan — barang yang tidak tercatat sebagai aset resmi
 * (dispenser, kulkas, dan sejenisnya). Terpisah dari `aset`/`master_barang`.
 */
class InventarisRuangan extends Model
{
    protected $table = 'inventaris_ruangan';

    use HasFactory;

    public const STATUS_AKTIF = 'aktif';

    public const STATUS_DIUSULKAN_HAPUS = 'diusulkan_hapus';

    public const STATUS_DIHAPUS = 'dihapuskan';

    protected $primaryKey = 'id_inventaris';

    protected $fillable = [
        'id_ruangan',
        'kode_inventaris',
        'nama_barang',
        'merk',
        'kondisi',
        'keterangan',
        'status',
    ];

    public function ruangan()
    {
        return $this->belongsTo(Ruangan::class, 'id_ruangan');
    }

    public function detailMutasi()
    {
        return $this->hasMany(DetailMutasiAset::class, 'id_inventaris');
    }

    public function usulanPenghapusan()
    {
        return $this->hasMany(UsulanPenghapusan::class, 'id_inventaris');
    }

    public function usulanAktif()
    {
        return $this->hasOne(UsulanPenghapusan::class, 'id_inventaris')
            ->where('status_usulan', 'diajukan');
    }

    public function riwayatPenghapusan()
    {
        return $this->hasMany(RiwayatPenghapusan::class, 'id_inventaris');
    }
}
