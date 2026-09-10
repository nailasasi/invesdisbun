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

    public $timestamps = false;

    protected $fillable = [
    'no_excel',
    'kib',
    'tanggal_buku',
    'tanggal_perolehan',
    'nilai_perolehan',
    'deskripsi_objek',
    'luas_tanah',
    'alamat',
    'ketkel',
    'status_hak',
    'nomor_sertifikat',
    'tanggal_sertifikat',
    'penggunaan',
    'penggunaan_air',
    'kondisi',
    'keterangan',
    'nama_petugas',
    'nomor_hp_petugas',
    'google_maps',
    'satuan',
    'foto_tanah',
    'video_tanah',
];

    protected $casts = [
    'tanggal_buku' => 'date',
    'tanggal_perolehan' => 'date',
    'tanggal_sertifikat' => 'date',
    'luas_tanah' => 'decimal:2',
    'nilai_perolehan' => 'decimal:2',
];

    

    public function retribusi()
    {
        return $this->hasMany(RetribusiTanah::class, 'id_tanah');
    }

    public function dokumenPbb()
    {
        return $this->hasMany(DokumenPbbTanah::class, 'id_tanah');
    }
    
    public function dokumenPbbHistories()
    {
        return $this->hasMany(
            DokumenPbbHistory::class,
            'id_tanah'
        );
    }

    public function histories()
    {
        return $this->hasMany(
            TanahHistory::class,
            'id_tanah'
        );
    }
}
