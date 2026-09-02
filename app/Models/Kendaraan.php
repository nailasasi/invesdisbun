<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kendaraan extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'kendaraan';
    use HasFactory;

    protected $primaryKey = 'id_kendaraan';

    protected $fillable = ['id_aset', 'jenis_kendaraan', 'nomor_rangka', 'nomor_mesin', 'merk', 'tipe'];

    public function aset()
    {
        return $this->belongsTo(Aset::class, 'id_aset');
    }

    public function riwayatPlat()
    {
        return $this->hasMany(RiwayatPlat::class, 'id_kendaraan');
    }

    public function pajak()
    {
        return $this->hasMany(PajakKendaraan::class, 'id_kendaraan');
    }

    public function izin()
    {
        return $this->hasMany(IzinKendaraan::class, 'id_kendaraan');
    }
}
