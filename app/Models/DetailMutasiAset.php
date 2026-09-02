<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetailMutasiAset extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'detail_mutasi_aset';
    use HasFactory;

    protected $primaryKey = 'id_detail';

    protected $fillable = [
        'id_mutasi', 'id_aset', 'pegawai_lama', 'pegawai_baru',
        'ruangan_lama', 'ruangan_baru',
    ];

    public function mutasi()
    {
        return $this->belongsTo(MutasiAset::class, 'id_mutasi');
    }

    public function aset()
    {
        return $this->belongsTo(Aset::class, 'id_aset');
    }

    public function pegawaiLama()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_lama');
    }

    public function pegawaiBaru()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_baru');
    }

    public function ruanganLama()
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_lama');
    }

    public function ruanganBaru()
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_baru');
    }
}
