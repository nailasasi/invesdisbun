<?php

namespace App\Services;

class TanggalAset
{
    public static function utama(array $data): ?string
    {
        if (array_key_exists('tanggal_pengadaan', $data)) {
            return self::bersihkan($data['tanggal_pengadaan']);
        }

        return self::bersihkan($data['tanggal_perolehan'] ?? null);
    }

    public static function samakanUntukSimpan(array $data): array
    {
        $tanggal = self::utama($data);

        $data['tanggal_pengadaan'] = $tanggal;
        unset($data['tanggal_perolehan']);

        return $data;
    }

    public static function samakanUntukUpdate(array $data, ?string $pengadaanLama): array
    {
        $adaTanggal = array_key_exists('tanggal_pengadaan', $data)
            || array_key_exists('tanggal_perolehan', $data);

        if (! $adaTanggal) {
            unset($data['tanggal_perolehan']);

            return $data;
        }

        $tanggal = self::utama($data);

        if ($tanggal === $pengadaanLama) {
            unset($data['tanggal_pengadaan'], $data['tanggal_perolehan']);

            return $data;
        }

        $data['tanggal_pengadaan'] = $tanggal;
        unset($data['tanggal_perolehan']);

        return $data;
    }

    public static function untukImport(?string $pengadaan, ?string $perolehan): array
    {
        $pengadaan = self::bersihkan($pengadaan);
        $perolehan = self::bersihkan($perolehan);

        if ($pengadaan !== null && $perolehan !== null && $pengadaan !== $perolehan) {
            return ['tanggal' => null, 'konflik' => true];
        }

        $tanggal = $pengadaan ?? $perolehan;

        return ['tanggal' => $tanggal, 'konflik' => false];
    }

    private static function bersihkan(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' || $value === '-' ? null : $value;
    }
}
