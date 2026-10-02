<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ruangan extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ruangan';
    use HasFactory;

    protected $primaryKey = 'id_ruangan';

    protected $fillable = ['nama_ruangan', 'lantai', 'id_skpd', 'id_lokasi', 'status'];

    public function skpd()
    {
        return $this->belongsTo(Skpd::class, 'id_skpd');
    }

    public function lokasi()
    {
        return $this->belongsTo(Lokasi::class, 'id_lokasi');
    }

    public function penempatanAset()
    {
        return $this->hasMany(PenempatanAset::class, 'id_ruangan');
    }
}
