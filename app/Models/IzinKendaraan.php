<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IzinKendaraan extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'izin_kendaraan';

    use HasFactory;

    protected $primaryKey = 'id_izin';

    protected $fillable = [
        'id_kendaraan', 'id_pegawai_pengaju', 'id_pengurus_barang', 'tanggal_berangkat', 'waktu_berangkat',
        'tanggal_kembali', 'waktu_kembali', 'tujuan', 'durasi', 'jenis_pengemudi',
        'id_pegawai_pengemudi', 'nama_pengemudi', 'status_approval', 'file_surat',
        'catatan_pengembalian', 'foto_pengembalian', 'waktu_pengembalian', 'alasan_penolakan',
        'dismissed_at',
    ];

    protected $casts = [
        'tanggal_berangkat' => 'date',
        'tanggal_kembali' => 'date',
        'waktu_berangkat' => 'datetime',
        'waktu_kembali' => 'datetime',
        'waktu_pengembalian' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    public function kendaraan()
    {
        return $this->belongsTo(Kendaraan::class, 'id_kendaraan');
    }

    public function pengaju()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai_pengaju');
    }

    public function pengemudi()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai_pengemudi');
    }

    public function penyerah()
    {
        return $this->belongsTo(Pegawai::class, 'id_pengurus_barang');
    }
}
