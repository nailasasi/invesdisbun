<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tanah extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tanah';
    use HasFactory;

    protected $primaryKey = 'id_tanah';

    protected $fillable = [
        'id_aset', 'luas_tanah', 'alamat', 'status_hak', 'nomor_sertifikat',
        'tanggal_sertifikat', 'penggunaan', 'kondisi', 'nama_petugas',
        'nomor_hp_petugas', 'google_maps', 'foto_tanah', 'video_tanah', 'keterangan',
    ];

    protected $casts = [
        'tanggal_sertifikat' => 'date',
        'luas_tanah' => 'decimal:2',
    ];

    public function aset()
    {
        return $this->belongsTo(Aset::class, 'id_aset');
    }

    public function retribusi()
    {
        return $this->hasMany(RetribusiTanah::class, 'id_tanah');
    }

    public function dokumenPbb()
    {
        return $this->hasMany(DokumenPbbTanah::class, 'id_tanah');
    }
}
