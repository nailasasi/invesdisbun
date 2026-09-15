<?php

namespace App\Http\Controllers;

use App\Models\IzinKendaraan;
use App\Models\Kendaraan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IzinKendaraanController extends Controller
{
    public function store(Request $request, Kendaraan $kendaraan)
    {
        $data = $request->validate([
            'id_pegawai_pengaju' => ['nullable', 'exists:pegawai,id_pegawai'],
            'tanggal_berangkat' => ['nullable', 'date'],
            'waktu_berangkat' => ['nullable', 'string', 'max:5'],
            'tanggal_kembali' => ['nullable', 'date'],
            'waktu_kembali' => ['nullable', 'string', 'max:5'],
            'tujuan' => ['nullable', 'string', 'max:500'],
            'jenis_pengemudi' => ['nullable', 'string', 'max:50'],
            'id_pegawai_pengemudi' => ['nullable', 'exists:pegawai,id_pegawai'],
            'nama_pengemudi' => ['nullable', 'string', 'max:100'],
            'file_surat' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ]);

        $filePath = null;
        if ($request->hasFile('file_surat')) {
            $filePath = $request->file('file_surat')->store('izin-kendaraan', 'public');
        }

        IzinKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'id_pegawai_pengaju' => $data['id_pegawai_pengaju'] ?? auth()->user()->pegawai?->id_pegawai,
            'tanggal_berangkat' => $data['tanggal_berangkat'] ?? null,
            'waktu_berangkat' => $data['waktu_berangkat'] ?? null,
            'tanggal_kembali' => $data['tanggal_kembali'] ?? null,
            'waktu_kembali' => $data['waktu_kembali'] ?? null,
            'tujuan' => $data['tujuan'] ?? null,
            'jenis_pengemudi' => $data['jenis_pengemudi'] ?? null,
            'id_pegawai_pengemudi' => $data['id_pegawai_pengemudi'] ?? null,
            'nama_pengemudi' => $data['nama_pengemudi'] ?? null,
            'status_approval' => 'Menunggu',
            'file_surat' => $filePath,
        ]);

        return response()->json(['success' => true, 'message' => 'Permohonan izin berhasil diajukan.']);
    }

    public function approve(Request $request, Kendaraan $kendaraan, IzinKendaraan $izin)
    {
        if ($izin->id_kendaraan !== $kendaraan->id_kendaraan) {
            return response()->json(['success' => false, 'message' => 'Data tidak valid.'], 422);
        }

        $data = $request->validate([
            'status_approval' => ['required', Rule::in(['Disetujui', 'Ditolak'])],
        ]);

        $izin->update(['status_approval' => $data['status_approval']]);

        return response()->json([
            'success' => true,
            'message' => $data['status_approval'] === 'Disetujui'
                ? 'Permohonan izin disetujui.'
                : 'Permohonan izin ditolak.',
        ]);
    }

    public function destroy(Kendaraan $kendaraan, IzinKendaraan $izin)
    {
        if ($izin->id_kendaraan !== $kendaraan->id_kendaraan) {
            return response()->json(['success' => false, 'message' => 'Data tidak valid.'], 422);
        }
        $izin->delete();
        return response()->json(['success' => true, 'message' => 'Permohonan izin dihapus.']);
    }
}
