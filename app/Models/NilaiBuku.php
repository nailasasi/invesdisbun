<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NilaiBuku extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'nilai_buku';
    use HasFactory;

    protected $primaryKey = 'id_nilai';

    protected $fillable = ['id_aset', 'tahun', 'nilai_awal', 'penyusutan', 'nilai_akhir'];

    protected $casts = [
        'tahun' => 'integer',
        'nilai_awal' => 'decimal:2',
        'penyusutan' => 'decimal:2',
        'nilai_akhir' => 'decimal:2',
    ];

    public function aset()
    {
        return $this->belongsTo(Aset::class, 'id_aset');
    }
}
