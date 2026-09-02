<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DokumenPbbTanah extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'dokumen_pbb_tanah';
    use HasFactory;

    protected $primaryKey = 'id_pbb';

    protected $fillable = ['id_tanah', 'tahun_pbb', 'file_pbb', 'tanggal_upload', 'uploaded_by'];

    protected $casts = [
        'tahun_pbb' => 'integer',
        'tanggal_upload' => 'date',
    ];

    public function tanah()
    {
        return $this->belongsTo(Tanah::class, 'id_tanah');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
