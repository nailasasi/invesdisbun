<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsulanRkbmd extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'usulan_rkbmd';
    use HasFactory;

    protected $primaryKey = 'id_usulan';

    protected $fillable = [
        'id_pegawai', 'tahun_anggaran', 'jenis_usulan', 'program_kegiatan',
        'status_usulan', 'tanggal_usulan',
    ];

    protected $casts = [
        'tahun_anggaran' => 'integer',
        'tanggal_usulan' => 'date',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }

    public function details()
    {
        return $this->hasMany(DetailUsulanRkbmd::class, 'id_usulan');
    }
}
