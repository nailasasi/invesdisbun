<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PemegangAset extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pemegang_aset';
    use HasFactory;

    protected $primaryKey = 'id_pemegang';

    protected $fillable = ['id_pegawai', 'id_aset', 'tanggal_mulai', 'tanggal_selesai', 'status'];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }

    public function aset()
    {
        return $this->belongsTo(Aset::class, 'id_aset');
    }
}
