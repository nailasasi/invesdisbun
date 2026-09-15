<?php

namespace App\Http\Controllers;

use App\Models\Aset;
use App\Models\UsulanPenghapusan;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class MonitoringAsetController extends Controller
{
    /**
     * Pusat pengawasan nilai buku & masa manfaat aset.
     */
    public function index(Request $request)
    {
        $query = $this->applyFilters($request);

        [$totalNilaiBuku, $asetHabisCount, $asetKritisCount] = $this->ringkasan((clone $query)->get());

        $tahunList = Aset::query()
            ->selectRaw('YEAR(tanggal_perolehan) as tahun')
            ->whereNotNull('tanggal_perolehan')
            ->distinct()
            ->orderByDesc('tahun')
            ->pluck('tahun');

        $pendingAsetIds = UsulanPenghapusan::where('status_usulan', 'diajukan')->pluck('id_aset');

        $asetList = $query->orderByDesc('id_aset')->paginate(10)->withQueryString();
        $asetList->setCollection($asetList->getCollection()->map(
            fn (Aset $a) => $this->toRow($a, $pendingAsetIds)
        ));

        return view('monitoring-aset.index', compact(
            'asetList', 'totalNilaiBuku', 'asetHabisCount', 'asetKritisCount', 'tahunList'
        ));
    }

    /**
     * Ekspor rekap neraca aset (.xlsx) dengan filter yang sedang aktif.
     */
    public function export(Request $request)
    {
        $rows = $this->applyFilters($request)
            ->orderByDesc('id_aset')
            ->get()
            ->map(fn (Aset $a) => $this->toRow($a, collect()));

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Monitoring Aset');

        $headers = [
            'No', 'Nama Barang', 'No. Kartu Barang', 'Lokasi / Pemegang',
            'Nilai Perolehan', 'Nilai Buku Terkini', 'Tanggal Pengadaan',
            'Masa Pakai', 'Kondisi',
        ];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '047857']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $rowNum = 2;
        foreach ($rows as $i => $r) {
            $lokasiPemegang = $r->lokasi ?: ($r->pemegang ?: '-');
            $sheet->fromArray([
                $i + 1,
                (string) ($r->nama_barang ?? '-'),
                (string) ($r->nomor_kartu ?? '-'),
                $lokasiPemegang,
                $r->punya_nilai ? $r->nilai_perolehan : '-',
                $r->punya_nilai ? $r->nilai_buku : '-',
                $r->tanggal_pengadaan ? Carbon::parse($r->tanggal_pengadaan)->format('d M Y') : '-',
                $this->masaText($r),
                (string) ($r->kondisi ?? '-'),
            ], null, "A{$rowNum}");

            if ($r->punya_nilai) {
                $sheet->getStyle("E{$rowNum}:F{$rowNum}")->getNumberFormat()
                    ->setFormatCode('"Rp "#,##0');
            }
            $rowNum++;
        }

        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(
            static fn () => $writer->save('php://output'),
            'rekap-monitoring-aset-' . now()->format('Ymd') . '.xlsx'
        );
    }

    /**
     * Query dasar = seluruh aset yang belum "dihapuskan", dihiasi filter.
     */
    private function applyFilters(Request $request)
    {
        $search = trim((string) $request->query('search'));
        $tahun = (int) $request->query('tahun');
        $status = $request->query('status');

        return Aset::query()
            ->with(['barang', 'penempatanAktif.ruangan', 'pemegangSaatIni.pegawai'])
            ->where(fn ($q) => $q->whereNull('status_aset')->orWhere('status_aset', '!=', 'dihapuskan'))
            ->when($search !== '', fn ($q) => $q->where(function ($q2) use ($search) {
                $q2->whereHas('barang', fn ($b) => $b->where('nama_barang', 'like', "%{$search}%"))
                    ->orWhere('nomor_kartu_barang', 'like', "%{$search}%");
            }))
            ->when($tahun, fn ($q) => $q->whereYear('tanggal_perolehan', $tahun))
            ->when($status === 'habis', fn ($q) => $q
                ->whereNotNull('tanggal_habis_pakai')
                ->whereDate('tanggal_habis_pakai', '<=', Carbon::today()->toDateString()))
            ->when($status === 'kritis', fn ($q) => $q
                ->whereNotNull('tanggal_habis_pakai')
                ->whereDate('tanggal_habis_pakai', '>=', Carbon::today()->addDays(1)->toDateString())
                ->whereDate('tanggal_habis_pakai', '<', Carbon::today()->addDays(30)->toDateString()))
            ->when($status === 'normal', fn ($q) => $q->where(fn ($q2) => $q2
                ->whereNull('tanggal_habis_pakai')
                ->orWhereDate('tanggal_habis_pakai', '>=', Carbon::today()->addDays(30)->toDateString())));
    }

    /**
     * Agregasi 3 KPI sebagai ringkasan global (tidak terpengaruh filter).
     *
     * @return array{float, int, int}
     */
    private function ringkasan(Collection $asetList): array
    {
        $total = 0.0;
        $habis = 0;
        $kritis = 0;

        foreach ($asetList as $aset) {
            $calc = $this->hitungDepresiasi($aset);
            $total += $calc['nilai_buku'];

            if ($aset->nilai_perolehan !== null && $calc['nilai_buku'] <= 0) {
                $habis++;
            }

            if ($calc['sisa_hari'] !== null && $calc['sisa_hari'] < 30) {
                $kritis++;
            }
        }

        return [round($total, 2), $habis, $kritis];
    }

    /**
     * Penyusutan garis lurus (straight-line) berbasis tanggal perolehan &
     * tanggal habis pakai. Nilai buku = nilai_perolehan x sisa proporsi umur.
     */
    private function hitungDepresiasi(Aset $aset): array
    {
        $buku = (float) ($aset->nilai_perolehan ?? 0);
        $perolehan = $aset->tanggal_perolehan ? Carbon::parse($aset->tanggal_perolehan) : null;
        $habis = $aset->tanggal_habis_pakai ? Carbon::parse($aset->tanggal_habis_pakai)->startOfDay() : null;

        $sisaHari = null;
        if ($habis) {
            $sisaHari = (int) Carbon::today()->startOfDay()->diffInDays($habis, false);
        }

        if ($perolehan && $habis && $habis->greaterThan($perolehan)) {
            $totalHari = max(1, (int) $perolehan->startOfDay()->diffInDays($habis));
            $hariTerpakai = max(0, (int) $perolehan->startOfDay()->diffInDays(Carbon::today()->startOfDay()));
            $rasio = min(1, $hariTerpakai / $totalHari);
            $buku = round($buku * (1 - $rasio), 2);
            if ($buku < 0) {
                $buku = 0.0;
            }
        }

        if ($sisaHari === null) {
            $masa = 'nan';
        } elseif ($sisaHari < 0) {
            $masa = 'habis';
        } elseif ($sisaHari < 30) {
            $masa = 'kritis';
        } else {
            $masa = 'normal';
        }

        return ['nilai_buku' => $buku, 'sisa_hari' => $sisaHari, 'masa_status' => $masa];
    }

    /**
     * Dekorasi aset menjadi baris tampilan + metadata untuk aksi "Usulkan Hapus".
     */
    private function toRow(Aset $aset, Collection $pendingAsetIds): object
    {
        $calc = $this->hitungDepresiasi($aset);
        $punyaNilai = $aset->nilai_perolehan !== null;

        $dalamAntrean = $aset->status_aset === 'diusulkan_hapus' || $pendingAsetIds->contains($aset->id_aset);

        $usulanAlasan = match (true) {
            $punyaNilai && $calc['nilai_buku'] <= 0, $calc['masa_status'] === 'habis' => 'Masa Pakai Habis',
            in_array($aset->kondisi, ['Rusak Berat', 'Rusak', 'Rusak Berat / Tidak Layak'], true) => 'Rusak Berat',
            default => 'Lainnya',
        };

        return (object) [
            'id_aset' => $aset->id_aset,
            'nama_barang' => $aset->barang?->nama_barang,
            'nomor_kartu' => $aset->nomor_kartu_barang,
            'lokasi' => $aset->penempatanAktif?->ruangan?->nama_ruangan,
            'pemegang' => $aset->pemegangSaatIni?->pegawai?->nama_pegawai,
            'kondisi' => $aset->kondisi,
            'nilai_perolehan' => (float) ($aset->nilai_perolehan ?? 0),
            'punya_nilai' => $punyaNilai,
            'nilai_buku' => $calc['nilai_buku'],
            'tanggal_pengadaan' => $aset->tanggal_pengadaan ?? $aset->tanggal_perolehan,
            'sisa_hari' => $calc['sisa_hari'],
            'masa_status' => $calc['masa_status'],
            'bisa_usul' => $aset->status_aset === 'aktif' && ! $dalamAntrean,
            'dalam_antrean' => $dalamAntrean,
            'usulan_alasan' => $usulanAlasan,
        ];
    }

    private function masaText(object $row): string
    {
        if ($row->sisa_hari === null) {
            return 'Tidak ditentukan';
        }

        return $row->sisa_hari < 0 ? 'Masa pakai habis' : sprintf('%d hari lagi', $row->sisa_hari);
    }
}