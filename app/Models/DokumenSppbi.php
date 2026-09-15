<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DokumenSppbi extends Model
{
    use HasFactory;

    protected $table = 'dokumen_sppbi';
    protected $primaryKey = 'id_sppbi';

    protected $fillable = [
        'id_pegawai',
        'nomor_surat',
        'tanggal_surat',
        'file_path',
        'status',
        'catatan',
        'id_user_penginput',
    ];

    protected $casts = [
        'tanggal_surat' => 'date',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }

    public function penginput()
    {
        return $this->belongsTo(User::class, 'id_user_penginput', 'id_user');
    }
}