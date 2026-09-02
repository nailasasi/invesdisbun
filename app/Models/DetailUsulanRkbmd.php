<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetailUsulanRkbmd extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'detail_usulan_rkbmd';
    use HasFactory;

    protected $primaryKey = 'id_detail';

    protected $fillable = [
        'id_usulan', 'id_barang', 'jumlah_usulan', 'kebutuhan_riil',
        'kebutuhan_maksimum', 'keterangan',
    ];

    public function usulan()
    {
        return $this->belongsTo(UsulanRkbmd::class, 'id_usulan');
    }

    public function barang()
    {
        return $this->belongsTo(MasterBarang::class, 'id_barang');
    }
}
