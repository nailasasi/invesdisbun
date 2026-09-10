<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Skpd extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'skpd';
    use HasFactory;

    protected $primaryKey = 'id_skpd';

    protected $fillable = ['nama_skpd'];

    public $timestamps = false;

    public function pegawai()
    {
        return $this->hasMany(Pegawai::class, 'id_skpd');
    }

    public function ruangan()
    {
        return $this->hasMany(Ruangan::class, 'id_skpd');
    }
}
