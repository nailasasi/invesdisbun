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
        'id_pegawai', 'id_skpd', 'tahun_anggaran', 'jenis_usulan', 'program_kegiatan',
        'nama_barang', 'kode_barang', 'spesifikasi', 'jumlah', 'satuan',
        'kebutuhan_maksimum', 'kebutuhan_riil', 'barang_optimalisasi',
        'alasan_kebutuhan', 'status_usulan', 'tanggal_usulan', 'catatan_pengurus',
        'approved_by', 'approved_at',
    ];

    protected $casts = [
        'tahun_anggaran' => 'integer',
        'tanggal_usulan' => 'date',
        'jumlah' => 'integer',
        'kebutuhan_maksimum' => 'integer',
        'kebutuhan_riil' => 'integer',
        'barang_optimalisasi' => 'array',
        'approved_at' => 'datetime',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }

    public function skpd()
    {
        return $this->belongsTo(Skpd::class, 'id_skpd');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by', 'id_user');
    }

    public function details()
    {
        return $this->hasMany(DetailUsulanRkbmd::class, 'id_usulan');
    }
}
