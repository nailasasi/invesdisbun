<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RiwayatPlat extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'riwayat_plat';
    use HasFactory;

    protected $primaryKey = 'id_plat';

    protected $fillable = ['id_kendaraan', 'nomor_plat', 'tanggal_berlaku', 'status'];

    protected $casts = [
        'tanggal_berlaku' => 'date',
    ];

    public function kendaraan()
    {
        return $this->belongsTo(Kendaraan::class, 'id_kendaraan');
    }
}
