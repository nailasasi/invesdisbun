<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsulanPenghapusan extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'usulan_penghapusan';
    use HasFactory;

    protected $primaryKey = 'id_usulan_hapus';

    protected $fillable = [
        'id_pegawai_penghapus', 'tanggal_usulan', 'alasan_penghapusan',
        'status_usulan', 'keterangan', 'id_aset',
    ];

    protected $casts = [
        'tanggal_usulan' => 'date',
    ];

    public function pegawaiPenghapus()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai_penghapus');
    }

    public function aset()
    {
        return $this->belongsTo(Aset::class, 'id_aset');
    }
}
