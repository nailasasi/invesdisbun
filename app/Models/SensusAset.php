<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SensusAset extends Model
{
    use HasFactory;

    protected $table = 'sensus_aset';

    protected $primaryKey = 'id_sensus';

    protected $fillable = [
        'id_aset',
        'periode',
        'hasil_sensus',
        'tanggal_sensus',
        'catatan',
        'id_user',
    ];

    protected $casts = [
        'tanggal_sensus' => 'date',
    ];

    public function aset()
    {
        return $this->belongsTo(Aset::class, 'id_aset');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    /**
     * Nama lengkap petugas pencatat dari relasi user -> pegawai.
     * Mengembalikan '-' jika akun tidak memiliki profil pegawai / belum terisi.
     * Tidak mengembalikan username atau nomor identitas akun.
     */
    public function namaPetugas(): string
    {
        return $this->user?->pegawai?->nama_pegawai ?: '-';
    }
}

