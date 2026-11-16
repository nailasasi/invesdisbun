<?php

namespace App\Services;

use App\Models\Aset;
use App\Models\SensusAset;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Pengelola aturan periode Sensus Aset.
 *
 * Aturan bisnis:
 * - Sensus berlangsung setiap 3 tahun sekali secara kalender inklusif.
 * - Dasar perhitungan menggunakan tahun pengadaan masing-masing aset (`tanggal_pengadaan`).
 * - Contoh siklus 3 tahunan: 2025–2027, 2028–2030, 2031–2033, dst.
 * - Periode berikutnya dimulai 1 tahun setelah periode sebelumnya berakhir.
 * - Jika aset belum/tidak memiliki tanggal pengadaan, sistem secara aman menggunakan
 *   tahun dasar standar dinas (2025) sebagai jangkar perhitungan.
 */
class PeriodeSensus
{
    public const TAHUN_DASAR = 2025;

    public const INTERVAL_TAHUN = 3;

    /**
     * Dapatkan tahun acuan awal siklus sensus aset.
     * Menggunakan tahun dari tanggal_pengadaan aset, atau fallback ke TAHUN_DASAR (2025).
     */
    public static function tahunAcuanAset($aset = null): int
    {
        if ($aset instanceof Aset) {
            $tgl = $aset->getRawOriginal('tanggal_pengadaan') ?? $aset->tanggal_pengadaan;
            if ($tgl) {
                return (int) Carbon::parse($tgl)->format('Y');
            }
        } elseif ($aset instanceof \DateTimeInterface) {
            return (int) $aset->format('Y');
        } elseif (is_string($aset) && ! empty($aset)) {
            if (preg_match('/^\d{4}$/', trim($aset))) {
                return (int) trim($aset);
            }
            try {
                return (int) Carbon::parse($aset)->format('Y');
            } catch (\Throwable $e) {
                // fallback
            }
        } elseif (is_int($aset) && $aset >= 1900 && $aset <= 2100) {
            return $aset;
        }

        return self::TAHUN_DASAR;
    }

    /**
     * Tentukan periode aktif berdasarkan tanggal acuan (default: saat ini) dan aset terkait.
     * Setiap periode mencakup tepat 3 tahun kalender inklusif (mulai hingga mulai + 2).
     */
    public static function periodeAktif(?CarbonInterface $date = null, $aset = null): string
    {
        $date = $date ?? Carbon::now();
        $tahunSekarang = (int) $date->format('Y');
        $tahunAcuan = self::tahunAcuanAset($aset);

        if ($tahunSekarang < $tahunAcuan) {
            $mulai = $tahunAcuan;
        } else {
            $offset = $tahunSekarang - $tahunAcuan;
            $k = (int) floor($offset / self::INTERVAL_TAHUN);
            $mulai = $tahunAcuan + ($k * self::INTERVAL_TAHUN);
        }

        // Tepat 3 tahun kalender inklusif (contoh: 2025 sampai 2027)
        $selesai = $mulai + self::INTERVAL_TAHUN - 1;

        return self::format($mulai, $selesai);
    }

    /**
     * Format string standar periode menggunakan en-dash.
     */
    public static function format(int $mulai, int $selesai): string
    {
        return "{$mulai}–{$selesai}";
    }

    /**
     * Normalisasi string periode dari format variatif (mis. '2025-2027' atau '2025 – 2027')
     * menjadi format standar '2025–2027'.
     */
    public static function normalisasi(?string $periode): string
    {
        if ($periode === null) {
            return self::periodeAktif();
        }

        $clean = trim($periode);
        if (preg_match('/^(\d{4})\s*[\-–—]\s*(\d{4})$/u', $clean, $matches)) {
            return self::format((int) $matches[1], (int) $matches[2]);
        }

        return $clean;
    }

    /**
     * Validasi apakah sebuah periode sesuai dengan siklus 3 tahunan.
     * Menerima rentang 3 tahun kalender inklusif (selisih 2, e.g. 2025–2027)
     * dan mempertahankan backwards-compatibility untuk data legacy (selisih 3, e.g. 2025–2028).
     */
    public static function valid(string $periode, $aset = null): bool
    {
        $clean = trim($periode);
        if (! preg_match('/^(\d{4})\s*[\-–—]\s*(\d{4})$/u', $clean, $matches)) {
            return false;
        }

        $mulai = (int) $matches[1];
        $selesai = (int) $matches[2];
        $selisih = $selesai - $mulai;

        // Standar baru: selisih 2 (3 tahun inklusif, contoh 2025–2027)
        // Legacy: selisih 3 (contoh 2025–2028)
        if ($selisih !== 2 && $selisih !== self::INTERVAL_TAHUN) {
            return false;
        }

        if ($aset !== null) {
            $tahunAcuan = self::tahunAcuanAset($aset);
            $cocokAcuan = abs($mulai - $tahunAcuan) % self::INTERVAL_TAHUN === 0;
            $cocokDasar = abs($mulai - self::TAHUN_DASAR) % self::INTERVAL_TAHUN === 0;

            if (! $cocokAcuan && ! $cocokDasar) {
                return false;
            }
        }

        return true;
    }

    /**
     * Periksa apakah periode sensus sedang berlangsung saat ini.
     * Sensus hanya dapat dicatat atau diperbarui pada periode yang sedang berlangsung.
     */
    public static function periodeSedangBerlangsung(string $periode, ?CarbonInterface $date = null, $aset = null): bool
    {
        if (! self::valid($periode, $aset)) {
            return false;
        }

        [$mulai] = self::urai($periode);
        [$aktifMulai] = self::urai(self::periodeAktif($date, $aset));

        return $mulai === $aktifMulai;
    }

    /**
     * Periksa apakah sebuah periode sensus telah berakhir (masa lalu) sehingga telah terkunci.
     */
    public static function periodeSudahBerakhir(string $periode, ?CarbonInterface $date = null, $aset = null): bool
    {
        [$mulai] = self::urai($periode);
        [$aktifMulai] = self::urai(self::periodeAktif($date, $aset));

        return $mulai < $aktifMulai;
    }

    /**
     * Periksa apakah sebuah periode sensus berada di masa depan dan belum dimulai.
     */
    public static function periodeBelumDimulai(string $periode, ?CarbonInterface $date = null, $aset = null): bool
    {
        [$mulai] = self::urai($periode);
        [$aktifMulai] = self::urai(self::periodeAktif($date, $aset));

        return $mulai > $aktifMulai;
    }

    /**
     * Pastikan periode dapat diisi (hanya periode yang sedang berlangsung).
     * Periode yang sudah berakhir terkunci, dan periode masa depan belum dapat diisi sebelum waktunya.
     */
    public static function bolehDiisi(string $periode, ?CarbonInterface $date = null, $aset = null): bool
    {
        return self::valid($periode, $aset) && self::periodeSedangBerlangsung($periode, $date, $aset);
    }

    /**
     * Daftar pilihan periode khusus untuk satu aset tertentu.
     * Hanya menyertakan periode yang sah dan dapat disimpan (dari tahun pengadaan s.d. periode aktif saat ini).
     * Tidak menyertakan periode masa depan.
     *
     * @return string[]
     */
    public static function daftarPeriodeUntukAset($aset, ?CarbonInterface $date = null): array
    {
        $date = $date ?? Carbon::now();
        $tahunAcuan = self::tahunAcuanAset($aset);
        $periodeAktif = self::periodeAktif($date, $aset);
        [$aktifMulai] = self::urai($periodeAktif);

        $daftar = [];

        // Hasilkan seluruh siklus 3 tahunan dari tahun pengadaan hingga siklus aktif saat ini
        for ($m = $tahunAcuan; $m <= $aktifMulai; $m += self::INTERVAL_TAHUN) {
            $daftar[] = self::format($m, $m + self::INTERVAL_TAHUN - 1);
        }

        // Jika aset telah memiliki riwayat sensus sebelumnya, sertakan periode tersebut agar dapat dilihat/diedit
        if ($aset instanceof Aset) {
            $sensusRecords = $aset->relationLoaded('sensus')
                ? $aset->sensus
                : ($aset->exists ? $aset->sensus()->get(['periode']) : []);

            foreach ($sensusRecords as $s) {
                $p = self::normalisasi($s->periode);
                if (! in_array($p, $daftar, true)) {
                    $daftar[] = $p;
                }
            }
        }

        if (! in_array($periodeAktif, $daftar, true)) {
            $daftar[] = $periodeAktif;
        }

        return array_values(array_unique($daftar));
    }

    /**
     * Daftar pilihan periode umum untuk dropdown filter global monitoring aset.
     *
     * @param int $jumlahSebelumnya Jumlah siklus sebelum periode aktif
     * @param bool $sertakanMasaDepan Apakah menyertakan siklus masa depan (default: false)
     * @return string[]
     */
    public static function daftarPeriode(int $jumlahSebelumnya = 1, bool $sertakanMasaDepan = false): array
    {
        $aktif = self::periodeAktif();
        [$aktifMulai] = self::urai($aktif);

        $daftar = [];

        for ($i = -$jumlahSebelumnya; $i <= 0; $i++) {
            $mulai = $aktifMulai + ($i * self::INTERVAL_TAHUN);
            $selesai = $mulai + self::INTERVAL_TAHUN - 1;
            $daftar[] = self::format($mulai, $selesai);
        }

        if ($sertakanMasaDepan) {
            $mulaiMasaDepan = $aktifMulai + self::INTERVAL_TAHUN;
            $daftar[] = self::format($mulaiMasaDepan, $mulaiMasaDepan + self::INTERVAL_TAHUN - 1);
        }

        return $daftar;
    }

    /**
     * Himpun seluruh pilihan periode 3 tahunan yang valid untuk dropdown Monitoring Aset.
     * Menggabungkan siklus dari tahun pengadaan seluruh aset dan riwayat sensus yang valid 3 tahun inklusif.
     * Format lama 4 tahun (selisih != 2) otomatis disaring dan tidak dimasukkan.
     *
     * @return string[]
     */
    public static function daftarPeriodeMonitoring(?CarbonInterface $date = null): array
    {
        $date = $date ?? Carbon::now();

        try {
            $tahunPengadaan = Aset::whereNotNull('tanggal_pengadaan')
                ->selectRaw('DISTINCT YEAR(tanggal_pengadaan) as thn')
                ->pluck('thn')
                ->map(fn ($y) => (int) $y)
                ->all();
        } catch (\Throwable $e) {
            $tahunPengadaan = [];
        }

        $allTahun = array_values(array_unique(array_merge([self::TAHUN_DASAR], $tahunPengadaan)));
        sort($allTahun);

        $daftarPeriode = [];

        foreach ($allTahun as $thn) {
            $aktif = self::periodeAktif($date, $thn);
            [$aktifMulai] = self::urai($aktif);

            // Hasilkan siklus mundur (minimal 2 siklus ke belakang atau dari tahun pengadaan)
            $mulaiBawah = min($thn, $aktifMulai - (2 * self::INTERVAL_TAHUN));
            for ($m = $mulaiBawah; $m <= $aktifMulai; $m += self::INTERVAL_TAHUN) {
                $daftarPeriode[] = self::format($m, $m + self::INTERVAL_TAHUN - 1);
            }
        }

        // Tambahkan periode dari database sensus_aset HANYA jika valid 3 tahun inklusif (selisih 2)
        try {
            $sensusDb = SensusAset::distinct()->pluck('periode')->all();
            foreach ($sensusDb as $pdb) {
                $norm = self::normalisasi($pdb);
                [$m, $s] = self::urai($norm);
                if ($s - $m === 2) {
                    $daftarPeriode[] = $norm;
                }
            }
        } catch (\Throwable $e) {
            // fallback
        }

        $daftarPeriode = array_values(array_unique($daftarPeriode));

        // Urutkan secara kronologis descending (siklus terbaru di atas)
        usort($daftarPeriode, function ($a, $b) {
            [$ma] = self::urai($a);
            [$mb] = self::urai($b);

            return $mb <=> $ma ?: strcmp($b, $a);
        });

        return $daftarPeriode;
    }

    /**
     * Daftar seluruh periode yang saat ini sedang aktif di tahun acuan.
     *
     * @return string[]
     */
    public static function daftarPeriodeAktifList(?CarbonInterface $date = null): array
    {
        $date = $date ?? Carbon::now();

        try {
            $tahunPengadaan = Aset::whereNotNull('tanggal_pengadaan')
                ->selectRaw('DISTINCT YEAR(tanggal_pengadaan) as thn')
                ->pluck('thn')
                ->map(fn ($y) => (int) $y)
                ->all();
        } catch (\Throwable $e) {
            $tahunPengadaan = [];
        }

        $allTahun = array_values(array_unique(array_merge([self::TAHUN_DASAR], $tahunPengadaan)));

        $aktifList = [];
        foreach ($allTahun as $thn) {
            $aktifList[] = self::periodeAktif($date, $thn);
        }

        return array_values(array_unique($aktifList));
    }

    /**
     * Hitung jumlah aset yang belum memiliki catatan sensus pada periode aktif masing-masing aset.
     * Menggunakan query database efisien berbasis NOT EXISTS dan mengelompokkan tahun pengadaan
     * ke periode aktifnya masing-masing.
     */
    public static function hitungAsetBelumDisensusAktif($query, ?CarbonInterface $date = null): int
    {
        $date = $date ?? Carbon::now();

        // Ambil tahun pengadaan unik yang ada dalam query yang sedang difilter
        $years = (clone $query)
            ->whereNotNull('tanggal_pengadaan')
            ->selectRaw('DISTINCT YEAR(tanggal_pengadaan) as thn')
            ->pluck('thn')
            ->map(fn ($y) => (int) $y)
            ->all();

        $hasNull = (clone $query)->whereNull('tanggal_pengadaan')->exists();
        if ($hasNull) {
            $years[] = null;
        }

        if (empty($years)) {
            return 0;
        }

        // Petakan setiap tahun pengadaan ke periode aktif masing-masing
        $mapPeriode = [];
        foreach ($years as $y) {
            $p = self::periodeAktif($date, $y);
            $mapPeriode[$p][] = $y;
        }

        return (clone $query)->setEagerLoads([])->where(function ($sub) use ($mapPeriode) {
            foreach ($mapPeriode as $p => $thnList) {
                $sub->orWhere(function ($q) use ($p, $thnList) {
                    $q->where(function ($qThn) use ($thnList) {
                        $withNull = in_array(null, $thnList, true);
                        $clean = array_values(array_filter($thnList, fn ($y) => $y !== null));
                        if (! empty($clean)) {
                            $qThn->where(function ($qYears) use ($clean) {
                                foreach ($clean as $idx => $y) {
                                    if ($idx === 0) {
                                        $qYears->whereYear('tanggal_pengadaan', $y);
                                    } else {
                                        $qYears->orWhereYear('tanggal_pengadaan', $y);
                                    }
                                }
                            });
                        }
                        if ($withNull) {
                            if (! empty($clean)) {
                                $qThn->orWhereNull('tanggal_pengadaan');
                            } else {
                                $qThn->whereNull('tanggal_pengadaan');
                            }
                        }
                    })
                    ->whereDoesntHave('sensus', fn ($s) => $s->where('periode', $p));
                });
            }
        })->count();
    }

    /**
     * Urai periode menjadi pasangan tahun [mulai, selesai].
     *
     * @return array{int, int}
     */
    public static function urai(string $periode): array
    {
        $clean = self::normalisasi($periode);
        if (preg_match('/^(\d{4})–(\d{4})$/u', $clean, $matches)) {
            return [(int) $matches[1], (int) $matches[2]];
        }

        $tahun = (int) date('Y');

        return [$tahun, $tahun + self::INTERVAL_TAHUN - 1];
    }
}
