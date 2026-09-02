<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RetribusiTanah extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'retribusi_tanah';
    use HasFactory;

    protected $primaryKey = 'id_retribusi';

    protected $fillable = [
        'id_tanah', 'tahun', 'status_pemanfaatan', 'tarif_retribusi',
        'target_penerimaan', 'realisasi_penerimaan', 'PAD', 'biaya_pengurusan',
        'total_tarif_sewa', 'keterangan',
    ];

    protected $casts = [
        'tahun' => 'integer',
        'tarif_retribusi' => 'decimal:2',
        'target_penerimaan' => 'decimal:2',
        'realisasi_penerimaan' => 'decimal:2',
        'PAD' => 'decimal:2',
        'biaya_pengurusan' => 'decimal:2',
        'total_tarif_sewa' => 'decimal:2',
    ];

    public function tanah()
    {
        return $this->belongsTo(Tanah::class, 'id_tanah');
    }
}
