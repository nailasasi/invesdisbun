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

    protected $fillable = ['nama_ruangan', 'lantai', 'id_skpd'];

    public function skpd()
    {
        return $this->belongsTo(Skpd::class, 'id_skpd');
    }

    public function penempatan()
    {
        return $this->hasMany(PenempatanAset::class, 'id_ruangan');
    }
}
