<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DokumenPbbHistory extends Model
{

    protected $table = 'dokumen_pbb_histories';

    protected $primaryKey = 'id_history';


    protected $fillable = [
        'id_tanah',
        'id_pbb',
        'id_user',
        'aksi',
        'nama_file'
    ];


    public function dokumen()
    {
        return $this->belongsTo(
            DokumenPbbTanah::class,
            'id_pbb'
        );
    }


    public function user()
    {
        return $this->belongsTo(
            User::class,
            'id_user'
        );
    }

}