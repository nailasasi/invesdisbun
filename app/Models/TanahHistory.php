<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TanahHistory extends Model
{
    protected $table = 'tanah_histories';

    protected $primaryKey = 'id_history';


    protected $fillable = [
        'id_tanah',
        'id_user',
        'aksi',
        'data_lama',
        'data_baru',
    ];


    protected $casts = [
        'data_lama' => 'array',
        'data_baru' => 'array',
    ];



    public function tanah()
    {
        return $this->belongsTo(
            Tanah::class,
            'id_tanah'
        );
    }



    public function user()
    {
        return $this->belongsTo(
            User::class,
            'id_user'
        );
    }

    public function histories()
{
    return $this->hasMany(
        TanahHistory::class,
        'id_tanah'
    );
}

}
