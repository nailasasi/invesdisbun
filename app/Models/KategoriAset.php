<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KategoriAset extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'kategori_aset';
    use HasFactory;

    protected $primaryKey = 'id_kategori';

    protected $fillable = ['nama_kategori'];

    public $timestamps = false;


    public function masterBarang()
    {
        return $this->hasMany(MasterBarang::class, 'id_kategori');
    }
}
