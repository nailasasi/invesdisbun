<?php

namespace App\Services;

use App\Models\Aset;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

    /**
     * Satu sumber aturan masa pakai & nilai buku Monitoring Aset.
     *
     * - Kritis : sisa 1-30 hari.
     * - Normal : sisa lebih dari 30 hari.
     * - Habis  : sisa 0 hari atau kurang, ATAU nilai buku sudah Rp0.
     *
     * Nilai buku memakai garis lurus tanpa residu. Penyusutan hanya diakui
     * setelah satu periode penuh (ulang tahun tanggal pengadaan), bukan setiap
     * pergantian tahun kalender. Nilai buku selalu dihitung terhadap tanggal
     * acuan (default hari ini), bukan terhadap tahun filter pengadaan.
     */
class MasaPakaiAset
{
    public const KRITIS_MIN_HARI = 1;

    public const KRITIS_MAX_HARI = 30;

    public function __construct(
        private readonly ?AsetScope $scope = null
    ) {
    }

    /**
     * @return array{
     *     nilai_buku: float|null,
     *     sisa_hari: int|null,
     *     masa_status: string,
     *     tahun_penuh: int,
     *     masa_manfaat_tahun: float|null
     * }
     */
    public function evaluasi(Aset $aset, ?CarbonInterface $acuan = null): array
    {
        $acuan = ($acuan ?? Carbon::today())->copy()->startOfDay();

        $nilaiPerolehan = $aset->nilai_perolehan !== null
            ? (float) $aset->nilai_perolehan
            : null;

        $perolehan = $aset->tanggal_pengadaan?->copy()->startOfDay();
        $habis = $aset->tanggal_habis_pakai?->copy()->startOfDay();

        $sisaHari = $habis !== null
            ? (int) $acuan->diffInDays($habis, false)
            : null;

        // Nilai perolehan kosong berarti nilai buku TIDAK DAPAT DITENTUKAN.
        // Jangan diganti Rp0, karena Rp0 adalah nilai buku valid untuk aset
        // yang memang habis/disusutkan penuh.
        $nilaiBuku = $nilaiPerolehan;
        $tahunPenuh = 0;
        $masaManfaatTahun = null;

        if ($perolehan !== null && $habis !== null && $habis->greaterThan($perolehan)) {
            $masaManfaatTahun = $perolehan->floatDiffInYears($habis);

            if ($masaManfaatTahun > 0 && $nilaiPerolehan !== null) {
                $tahunPenuh = $this->tahunPenggunaanPenuh($perolehan, $acuan);
                $nilaiBuku = $nilaiPerolehan
                    - (($nilaiPerolehan / $masaManfaatTahun) * $tahunPenuh);
            }
        }

        // Tanggal habis pakai adalah batas mutlak untuk nilai buku yang
        // diketahui. Nilai perolehan kosong tetap tidak dapat ditentukan.
        if ($nilaiBuku !== null && $habis !== null && $acuan->greaterThanOrEqualTo($habis)) {
            $nilaiBuku = 0.0;
        }

        if ($nilaiBuku !== null && $nilaiBuku < 0) {
            $nilaiBuku = 0.0;
        }

        if ($nilaiBuku !== null) {
            $nilaiBuku = round($nilaiBuku, 2);
        }

        return [
            'nilai_buku' => $nilaiBuku,
            'sisa_hari' => $sisaHari,
            'masa_status' => $this->klasifikasi($nilaiPerolehan, $nilaiBuku, $sisaHari),
            'tahun_penuh' => $tahunPenuh,
            'masa_manfaat_tahun' => $masaManfaatTahun,
        ];
    }

    /**
     * Jumlah ulang tahun tanggal pengadaan yang sudah terlewati.
     *
     * Carbon menghitung 29 Februari -> 1 Maret pada tahun non-kabisat karena
     * tanggal yang sama tidak tersedia. Kasus akhir bulan (31 Januari) tetap
     * memakai tanggal 31 Januari.
     */
    public function tahunPenggunaanPenuh(CarbonInterface $perolehan, CarbonInterface $acuan): int
    {
        if ($acuan->lessThan($perolehan)) {
            return 0;
        }

        return max(0, (int) floor($perolehan->floatDiffInYears($acuan)));
    }

    /**
     * Urutan penting: nilai buku Rp0 yang diketahui selalu Habis, termasuk
     * sebelum tanggal habis pakai. Sisa 0 hari juga Habis, bukan Kritis.
     * Nilai buku yang tidak dapat ditentukan bukan Rp0 dan tidak memicu Habis
     * hanya karena nilainya kosong.
     */
    public function klasifikasi(?float $nilaiPerolehan, ?float $nilaiBuku, ?int $sisaHari): string
    {
        if ($nilaiPerolehan !== null && $nilaiBuku !== null && $nilaiBuku <= 0) {
            return 'habis';
        }

        if ($sisaHari === null) {
            return 'nan';
        }

        if ($sisaHari <= 0) {
            return 'habis';
        }

        if ($sisaHari >= self::KRITIS_MIN_HARI && $sisaHari <= self::KRITIS_MAX_HARI) {
            return 'kritis';
        }

        return 'normal';
    }

    /**
     * ID aset bernilai perolehan positif yang nilai bukunya sudah Rp0
     * meskipun tanggal habis pakai belum tercapai.
     *
     * Kasus ini bisa muncul dari pembulatan 2 desimal pada masa manfaat
     * pecahan. SQL sederhana tidak dapat menghitung rumus tahun penuh Carbon
     * dengan aman, jadi kandidat dihitung dengan sumber aturan yang sama
     * (`evaluasi()`) lalu dipakai sebagai himpunan ID pada filter.
     *
     * Query kandidat selalu mematuhi scope query aktif (bila disediakan)
     * atau AsetScope untuk mencegah kebocoran ID lintas kewenangan/lokasi.
     *
     * @return array<int, int>
     */
    private function idNilaiBukuNolSebelumHabis(CarbonInterface $acuan, ?Builder $baseQuery = null): array
    {
        $kandidatQuery = $baseQuery
            ? (clone $baseQuery)->setEagerLoads([])
            : Aset::query()->tap(fn ($q) => $this->scope?->terapkan($q));

        return $kandidatQuery
            ->whereNotNull('nilai_perolehan')
            ->where('nilai_perolehan', '>', 0)
            ->whereNotNull('tanggal_pengadaan')
            ->whereNotNull('tanggal_habis_pakai')
            ->whereDate('tanggal_habis_pakai', '>', $acuan->toDateString())
            ->select(['id_aset', 'tanggal_pengadaan', 'tanggal_habis_pakai', 'nilai_perolehan'])
            ->get()
            ->filter(fn (Aset $aset) => $this->evaluasi($aset, $acuan)['nilai_buku'] === 0.0)
            ->pluck('id_aset')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Cermin SQL dari klasifikasi() agar filter status menghasilkan kumpulan
     * yang sama dengan badge/KPI.
     */
    public function terapkanFilterStatus(
        Builder $query,
        ?string $status,
        ?CarbonInterface $acuan = null
    ): Builder {
        $status = $status !== null ? trim($status) : '';

        if (! in_array($status, ['habis', 'kritis', 'normal'], true)) {
            return $query;
        }

        $acuan = ($acuan ?? Carbon::today())->copy()->startOfDay();
        $hariIni = $acuan->toDateString();
        $besok = $acuan->copy()->addDay()->toDateString();
        $batasKritis = $acuan->copy()->addDays(self::KRITIS_MAX_HARI)->toDateString();
        $idNilaiNol = $this->idNilaiBukuNolSebelumHabis($acuan, $query);

        return match ($status) {
            'habis' => $query->where(function ($q) use ($hariIni, $idNilaiNol) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('nilai_perolehan')
                        ->where('nilai_perolehan', '<=', 0);
                })->orWhere(function ($q2) use ($hariIni) {
                    $q2->whereNotNull('tanggal_habis_pakai')
                        ->whereDate('tanggal_habis_pakai', '<=', $hariIni);
                });

                if ($idNilaiNol !== []) {
                    $q->orWhereIn('id_aset', $idNilaiNol);
                }
            }),
            'kritis' => $query
                ->where(function ($q) {
                    $q->whereNull('nilai_perolehan')
                        ->orWhere('nilai_perolehan', '>', 0);
                })
                ->whereNotNull('tanggal_habis_pakai')
                ->whereDate('tanggal_habis_pakai', '>=', $besok)
                ->whereDate('tanggal_habis_pakai', '<=', $batasKritis)
                ->when($idNilaiNol !== [], fn ($q) => $q->whereNotIn('id_aset', $idNilaiNol)),
            'normal' => $query
                ->where(function ($q) {
                    $q->whereNull('nilai_perolehan')
                        ->orWhere('nilai_perolehan', '>', 0);
                })
                ->whereNotNull('tanggal_habis_pakai')
                ->whereDate('tanggal_habis_pakai', '>', $batasKritis)
                ->when($idNilaiNol !== [], fn ($q) => $q->whereNotIn('id_aset', $idNilaiNol)),
        };
    }
}
