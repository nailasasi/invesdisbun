<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PajakKendaraan extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pajak_kendaraan';
    use HasFactory;

    protected $primaryKey = 'id_pajak';

    protected $fillable = ['id_kendaraan', 'jenis_pajak', 'tahun', 'tanggal_bayar', 'tanggal_berakhir', 'nominal', 'total_pajak', 'pajak_5_tahunan', 'status'];

    protected $casts = [
        'tahun' => 'integer',
        'tanggal_bayar' => 'date',
        'tanggal_berakhir' => 'date',
        'nominal' => 'decimal:2',
        'total_pajak' => 'decimal:2',
        'pajak_5_tahunan' => 'boolean',
    ];

    public function kendaraan()
    {
        return $this->belongsTo(Kendaraan::class, 'id_kendaraan');
    }
}
