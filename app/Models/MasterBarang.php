<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterBarang extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'master_barang';
    use HasFactory;

    protected $primaryKey = 'id_barang';

    protected $fillable = ['kode_barang', 'nama_barang', 'satuan', 'id_kategori'];

    public function kategori()
    {
        return $this->belongsTo(KategoriAset::class, 'id_kategori');
    }

    public function aset()
    {
        return $this->hasMany(Aset::class, 'id_barang');
    }
}
