<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pegawai extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pegawai';

    use HasFactory;

    protected $primaryKey = 'id_pegawai';

    protected $fillable = ['nip', 'nama_pegawai', 'jabatan', 'id_skpd', 'id_ruangan'];

    public function skpd()
    {
        return $this->belongsTo(Skpd::class, 'id_skpd');
    }

    public function ruangan()
    {
        return $this->belongsTo(Ruangan::class, 'id_ruangan');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'id_pegawai');
    }

    public function pemegangAset()
    {
        return $this->hasMany(PemegangAset::class, 'id_pegawai');
    }
}
