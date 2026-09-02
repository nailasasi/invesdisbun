<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MutasiAset extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'mutasi_aset';
    use HasFactory;

    protected $primaryKey = 'id_mutasi';

    protected $fillable = ['tanggal_mutasi', 'jenis_mutasi', 'keterangan', 'id_user_penginput', 'status_mutasi'];

    protected $casts = [
        'tanggal_mutasi' => 'date',
    ];

    public function userPenginput()
    {
        return $this->belongsTo(User::class, 'id_user_penginput');
    }

    public function details()
    {
        return $this->hasMany(DetailMutasiAset::class, 'id_mutasi');
    }
}
