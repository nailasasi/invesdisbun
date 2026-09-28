<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArsipRkbmd extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'arsip_rkbmd';

    use HasFactory;

    protected $primaryKey = 'id_arsip';

    protected $fillable = [
        'id_skpd', 'tahun_anggaran', 'nama_dokumen', 'file_path',
        'tanggal_pengesahan', 'uploaded_by',
    ];

    protected $casts = [
        'tahun_anggaran' => 'integer',
        'tanggal_pengesahan' => 'date',
    ];

    public function skpd()
    {
        return $this->belongsTo(Skpd::class, 'id_skpd');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'id_user');
    }
}
