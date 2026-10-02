<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Audit log universal.
 *
 * Ditulis lewat {@see \App\Services\ActivityLogger} setelah perubahan
 * berhasil disimpan. Baris log tidak pernah diubah atau dihapus oleh
 * alur aplikasi — tidak ada controller yang menyediakan aksi tersebut.
 */
class ActivityLog extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'activity_logs';

    use HasFactory;

    protected $primaryKey = 'id_activity_log';

    protected $fillable = [
        'log_type',
        'description',
        'subject_type',
        'subject_id',
        'id_user',
        'nama_aktor',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /**
     * Admin Aset yang melakukan tindakan.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    /**
     * Objek yang diubah. Saat ini hanya diisi oleh UserController, namun
     * strukturnya polimorfik agar modul lain bisa memakainya nanti.
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Daftar nama field yang Changed, untuk ditampilkan pada timeline.
     */
    public function changedFields(): array
    {
        return array_values(array_unique(array_merge(
            array_keys($this->old_values ?? []),
            array_keys($this->new_values ?? [])
        )));
    }
}