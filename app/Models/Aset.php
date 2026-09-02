<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Aset extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'aset';
    use HasFactory;

    protected $primaryKey = 'id_aset';

    protected $fillable = [
        'id_barang', 'nomor_kartu_barang', 'merk', 'tanggal_pengadaan',
        'tanggal_perolehan', 'tanggal_habis_pakai', 'nilai_perolehan',
        'kondisi', 'status_aset',
    ];

    protected $casts = [
        'tanggal_pengadaan' => 'date',
        'tanggal_perolehan' => 'date',
        'tanggal_habis_pakai' => 'date',
        'nilai_perolehan' => 'decimal:2',
    ];

    public function barang()
    {
        return $this->belongsTo(MasterBarang::class, 'id_barang');
    }

    public function pemegang()
    {
        return $this->hasMany(PemegangAset::class, 'id_aset');
    }

    public function pemegangSaatIni()
    {
        return $this->hasOne(PemegangAset::class, 'id_aset')->where('status', 'aktif');
    }

    public function penempatan()
    {
        return $this->hasMany(PenempatanAset::class, 'id_aset');
    }

    public function penempatanAktif()
    {
        return $this->hasOne(PenempatanAset::class, 'id_aset')->where('status', 'aktif');
    }

    public function kendaraan()
    {
        return $this->hasOne(Kendaraan::class, 'id_aset');
    }

    public function tanah()
    {
        return $this->hasOne(Tanah::class, 'id_aset');
    }
}
