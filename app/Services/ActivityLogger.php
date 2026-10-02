<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Audit log universal.
 *
 * Dipanggil SETELAH perubahan berhasil disimpan. Nilai yang dicatat sudah
 * berupa label human-readable (mis. nama_skpd, bukan id_skpd) supaya
 * timeline dapat dibaca tanpa join tambahan.
 *
 * Password dan key sensitif lain tidak pernah masuk ke log — hanya
 * Recording boolean bahwa password diubah.
 */
class ActivityLogger
{
    /**
     * Key yang dibuang sebelum nilai apa pun ditulis ke log.
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_baru',
        'password_lama',
        'password_confirmation',
        'current_password',
        'remember_token',
        'api_token',
        'token',
        'access_token',
    ];

    /**
     * Tulis satu entri log.
     */
    public function log(
        string $logType,
        ?Model $subject = null,
        array $oldValues = [],
        array $newValues = [],
        ?string $description = null
    ): ActivityLog {
        $old = $this->sanitize($oldValues);
        $new = $this->sanitize($newValues);

        try {
            return ActivityLog::create([
                'log_type' => $logType,
                'description' => $description ? mb_substr($description, 0, 255) : null,
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'id_user' => Auth::id(),
                'nama_aktor' => $this->namaAktor(),
                'old_values' => $old ?: null,
                'new_values' => $new ?: null,
                'ip_address' => $this->ipAddress(),
                'user_agent' => $this->userAgent(),
            ]);
        } catch (\Throwable $e) {
            // Audit tidak boleh menjatuhkan aksi bisnis yang sudah berhasil.
            Log::error('Gagal menulis activity log: '.$e->getMessage(), [
                'log_type' => $logType,
                'subject_id' => $subject?->getKey(),
            ]);

            throw $e;
        }
    }

    /**
     * Ambil hanya field yang benar-benar berubah.
     *
     * @return array{changed: array<int, string>, old: array<string, mixed>, new: array<string, mixed>}
     */
    public function diff(array $oldValues, array $newValues): array
    {
        $old = $this->sanitize($oldValues);
        $new = $this->sanitize($newValues);

        $changedOld = [];
        $changedNew = [];

        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $key) {
            $before = $old[$key] ?? null;
            $after = $new[$key] ?? null;

            if ($this->sama($before, $after)) {
                continue;
            }

            $changedOld[$key] = $before;
            $changedNew[$key] = $after;
        }

        return [
            'changed' => array_keys($changedNew),
            'old' => $changedOld,
            'new' => $changedNew,
        ];
    }

    /**
     * Buang key sensitif dari sebuah array.
     */
    public function sanitize(array $values): array
    {
        foreach (self::SENSITIVE_KEYS as $key) {
            unset($values[$key]);
        }

        return $values;
    }

    /**
     * Perbandingan longgar: "1", 1, true, dan "true" dianggap sama supaya
     * perubahan id integer tidak tercatat sebagai perubahan palsu, dan
     * null dianggap sama dengan string kosong karena form kosong tidak
     * ikut terkirim saat submit.
     */
    private function sama(mixed $before, mixed $after): bool
    {
        if ($before === $after) {
            return true;
        }

        if ($before === null) {
            return $after === null || $after === '';
        }

        if ($after === null) {
            return $before === '';
        }

        if (is_bool($before) || is_bool($after)) {
            return (bool) $before === (bool) $after;
        }

        return (string) $before === (string) $after;
    }

    private function namaAktor(): ?string
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return null;
        }

        $nama = $user->pegawai?->nama_pegawai ?: $user->username;

        return $nama ? mb_substr((string) $nama, 0, 100) : null;
    }

    private function userAgent(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        $agent = request()->userAgent();

        return $agent ? mb_substr($agent, 0, 255) : null;
    }

    private function ipAddress(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        return request()->ip();
    }
}