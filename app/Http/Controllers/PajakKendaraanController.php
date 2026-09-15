<?php

namespace App\Http\Controllers;

use App\Models\Kendaraan;
use App\Models\PajakKendaraan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PajakKendaraanController extends Controller
{
    public function store(Request $request, Kendaraan $kendaraan)
    {
        $data = $request->validate([
            'jenis_pajak' => ['required', 'string', 'max:50'],
            'tahun' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'tanggal_bayar' => ['nullable', 'date'],
            'tanggal_berakhir' => ['nullable', 'date'],
            'nominal' => ['nullable', 'numeric', 'min:0'],
            'total_pajak' => ['nullable', 'numeric', 'min:0'],
            'pajak_5_tahunan' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(['Aktif', 'Tidak Aktif'])],
        ]);

        if (($data['status'] ?? 'Aktif') === 'Aktif') {
            PajakKendaraan::where('id_kendaraan', $kendaraan->id_kendaraan)
                ->where('status', 'Aktif')
                ->update(['status' => 'Tidak Aktif']);
        }

        PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => $data['jenis_pajak'],
            'tahun' => $data['tahun'] ?? null,
            'tanggal_bayar' => $data['tanggal_bayar'] ?? null,
            'tanggal_berakhir' => $data['tanggal_berakhir'] ?? null,
            'nominal' => $data['nominal'] ?? null,
            'total_pajak' => $data['total_pajak'] ?? null,
            'pajak_5_tahunan' => $data['pajak_5_tahunan'] ?? false,
            'status' => $data['status'] ?? 'Aktif',
        ]);

        return response()->json(['success' => true, 'message' => 'Data pajak berhasil ditambahkan.']);
    }

    public function destroy(Kendaraan $kendaraan, PajakKendaraan $pajak)
    {
        if ($pajak->id_kendaraan !== $kendaraan->id_kendaraan) {
            return response()->json(['success' => false, 'message' => 'Data tidak valid.'], 422);
        }
        $pajak->delete();
        return response()->json(['success' => true, 'message' => 'Data pajak berhasil dihapus.']);
    }
}
