<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PenempatanAset extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'penempatan_aset';
    use HasFactory;

    protected $primaryKey = 'id_penempatan';

    protected $fillable = ['id_aset', 'id_ruangan', 'tanggal_mulai', 'tanggal_selesai', 'status'];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
    ];

    public function aset()
    {
        return $this->belongsTo(Aset::class, 'id_aset');
    }

    public function ruangan()
    {
        return $this->belongsTo(Ruangan::class, 'id_ruangan');
    }
}
