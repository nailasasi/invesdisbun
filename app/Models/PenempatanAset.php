<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PenempatanAset extends Model
{
    use HasFactory;

    protected $table = 'penempatan_aset';

    protected $primaryKey = 'id_penempatan';

    protected $fillable = ['id_aset', 'id_ruangan', 'tanggal_mulai', 'tanggal_selesai', 'status', 'is_finalized', 'finalized_at', 'finalized_by'];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'is_finalized' => 'integer',
        'finalized_at' => 'datetime',
        'finalized_by' => 'integer',
    ];

    public function aset()
    {
        return $this->belongsTo(Aset::class, 'id_aset');
    }

    public function ruangan()
    {
        return $this->belongsTo(Ruangan::class, 'id_ruangan');
    }

    public function finalizedBy()
    {
        return $this->belongsTo(User::class, 'finalized_by', 'id_user');
    }
}
