@extends('layouts.app')

@section('title', 'Riwayat Penghapusan Aset')

@section('page-title', 'Riwayat Penghapusan Aset')

@section('content')
    <x-alert type="success" />
    <x-alert type="warning" />
    <x-alert type="error" />

    {{-- Navigasi Tab: Antrean vs Riwayat --}}
    <div class="mb-4 flex items-center justify-between gap-4 border-b border-slate-200">
        <div class="flex items-center gap-2">
            <a href="{{ route('penghapusan.index') }}"
               class="inline-flex items-center gap-2 border-b-2 border-transparent px-4 py-2.5 text-sm font-semibold text-slate-500 transition hover:border-slate-300 hover:text-slate-800">
                <span>Antrean Usulan</span>
                @if (isset($totalAntrean) && $totalAntrean > 0)
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ $totalAntrean }}</span>
                @endif
            </a>
            <a href="{{ route('penghapusan.riwayat') }}"
               class="inline-flex items-center gap-2 border-b-2 border-disbun-700 px-4 py-2.5 text-sm font-bold text-disbun-700">
                <span>Riwayat Penghapusan</span>
                <span class="rounded-full bg-disbun-100 px-2 py-0.5 text-xs font-bold text-disbun-800">{{ $riwayatList->total() }}</span>
            </a>
        </div>
    </div>

    <x-card :padding="false">
        <x-action-bar>
            <form method="GET" action="{{ route('penghapusan.riwayat') }}" class="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative flex-1 sm:max-w-sm">
                    <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama / kode aset / nomor SK / pelaksana..."
                           class="w-full rounded-2xl border border-slate-200 bg-white py-2.5 pl-11 pr-4 text-sm text-slate-800 shadow-sm transition placeholder:text-slate-400 focus:border-disbun-600 focus:outline-none focus:ring-2 focus:ring-disbun-400/40">
                </div>
                <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-disbun-700 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-disbun-800">
                    Cari
                </button>
                @if (request()->has('search'))
                    <a href="{{ route('penghapusan.riwayat') }}" class="inline-flex items-center rounded-2xl border border-disbun-card-border bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">Reset</a>
                @endif
            </form>
        </x-action-bar>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-100 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                        <th class="py-3 px-3">Aset Dihapus & Kode</th>
                        <th class="py-3 px-3">Jenis & Nilai</th>
                        <th class="py-3 px-3">Dokumen SK Resmi</th>
                        <th class="py-3 px-3">Alasan Usulan</th>
                        <th class="py-3 px-3">Waktu Eksekusi (Server)</th>
                        <th class="py-3 px-3">Pelaksana</th>
                        <th class="py-3 px-3 text-center">Foto Otorisasi</th>
                        <th class="py-3 px-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse ($riwayatList as $item)
                        @php
                            $snap = $item->snapshot_aset ?? [];
                            $namaAset = $item->nama_aset_tampil;
                            $nomorKartu = $item->nomor_kartu_barang_tampil;
                            $jenisAset = $item->jenis_aset_tampil;
                            $nilai = $item->nilai_perolehan_tampil;
                            $noSk = $item->no_sk_tampil;
                            $tanggalSk = $item->tanggal_sk_tampil;
                            $waktuEksekusiFmt = $item->waktu_penghapusan ? $item->waktu_penghapusan->timezone('Asia/Jakarta')->format('d M Y, H:i') . ' WIB' : '-';
                            $pelaksana = $item->nama_pelaksana ?? $item->user?->pegawai?->nama_pegawai ?? $item->user?->username ?? '-';
                        @endphp
                        <tr class="transition hover:bg-slate-50/50">
                            <td class="py-3 px-3">
                                <span class="font-bold text-slate-800">{{ $namaAset }}</span>
                                <span class="block font-mono text-[11px] text-slate-400">{{ $nomorKartu }}</span>
                            </td>
                            <td class="py-3 px-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold {{ $jenisAset === 'Kendaraan' ? 'bg-blue-50 text-blue-700' : 'bg-slate-100 text-slate-700' }}">
                                    {{ $jenisAset }}
                                </span>
                                <span class="block font-mono text-[11px] text-slate-500 mt-0.5">{{ $nilai }}</span>
                            </td>
                            <td class="py-3 px-3">
                                <span class="font-semibold text-slate-800">{{ $noSk }}</span>
                                <span class="block text-[11px] text-emerald-700 font-medium">Tgl SK: {{ $tanggalSk }}</span>
                            </td>
                            <td class="py-3 px-3">
                                <span class="rounded-full bg-rose-50 px-2.5 py-0.5 text-[10px] font-bold text-rose-700">{{ $item->alasan_penghapusan }}</span>
                                @if ($item->keterangan)
                                    <p class="text-[11px] text-slate-400 mt-1 line-clamp-1" title="{{ $item->keterangan }}">{{ $item->keterangan }}</p>
                                @endif
                            </td>
                            <td class="py-3 px-3 font-mono text-slate-600">
                                <span>{{ $waktuEksekusiFmt }}</span>
                            </td>
                            <td class="py-3 px-3">
                                <span class="font-semibold text-slate-800">{{ $pelaksana }}</span>
                                <span class="block text-[10px] text-slate-400">{{ $item->user?->role?->nama_role ?? 'Admin' }}</span>
                            </td>
                            <td class="py-3 px-3 text-center">
                                @if ($item->foto_bukti && auth()->user()?->role?->nama_role === 'Admin Aset')
                                    <button type="button"
                                            onclick="bukaModalFoto('{{ route('penghapusan.riwayat.bukti', $item->id_riwayat) }}', '{{ addslashes($namaAset) }}', '{{ addslashes($pelaksana) }}', '{{ $waktuEksekusiFmt }}', '{{ addslashes($noSk) }}')"
                                            class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-bold text-slate-700 shadow-2xs transition hover:bg-slate-50 hover:text-disbun-700">
                                        <svg class="h-3.5 w-3.5 text-disbun-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        <span>Lihat Foto</span>
                                    </button>
                                @else
                                    <span class="text-[11px] text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="py-3 px-3 text-center">
                                <button type="button"
                                        onclick="bukaModalDetail({{ json_encode([
                                            'nama' => $namaAset,
                                            'nomor_kartu' => $nomorKartu,
                                            'jenis' => $jenisAset,
                                            'kategori' => $snap['kategori'] ?? '-',
                                            'merk' => $snap['merk'] ?? '-',
                                            'kondisi' => $snap['kondisi'] ?? '-',
                                            'nilai' => $nilai,
                                            'no_sk' => $noSk,
                                            'tanggal_sk' => $tanggalSk,
                                            'tanggal_pengadaan' => $snap['tanggal_pengadaan'] ?? '-',
                                            'skpd' => $snap['skpd'] ?? '-',
                                            'lokasi' => $snap['lokasi'] ?? '-',
                                            'ruangan' => $snap['ruangan'] ?? '-',
                                            'pemegang' => $snap['pemegang'] ?? 'Tanpa Pemegang',
                                            'alasan' => $item->alasan_penghapusan,
                                            'keterangan' => $item->keterangan ?? '-',
                                            'pelaksana' => $pelaksana,
                                            'waktu_eksekusi' => $waktuEksekusiFmt,
                                            'foto_url' => $item->foto_bukti ? route('penghapusan.riwayat.bukti', $item->id_riwayat) : null,
                                        ]) }})"
                                        class="rounded-xl border border-disbun-card-border bg-white px-2.5 py-1 text-[11px] font-bold text-slate-700 transition hover:bg-slate-50">
                                    Detail
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-slate-400">Belum ada riwayat aset yang dihapus permanen.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-disbun-card-border px-6 py-4">
            {{ $riwayatList->links() }}
        </div>
    </x-card>

    {{-- Modal Lihat Foto Bukti Otorisasi --}}
    <div id="modal-foto-bukti" class="fixed inset-0 z-50 hidden items-center justify-center overflow-y-auto bg-slate-900/60 p-4 backdrop-blur-xs">
        <div class="fixed inset-0" onclick="tutupModalFoto()"></div>
        <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl overflow-hidden my-8">
            <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Foto Bukti Otorisasi</h3>
                    <p id="foto-modal-sub" class="text-xs text-slate-500">—</p>
                </div>
                <button type="button" onclick="tutupModalFoto()" class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-slate-900 flex items-center justify-center">
                    <img id="foto-modal-img" src="" alt="Bukti Otorisasi" class="mx-auto max-h-96 w-full object-contain">
                </div>
                <div class="rounded-xl bg-slate-50 p-3 text-xs space-y-1.5 text-slate-600">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Nomor SK:</span>
                        <span id="foto-modal-sk" class="font-bold text-slate-800">—</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Pelaksana:</span>
                        <span id="foto-modal-pelaksana" class="font-bold text-slate-800">—</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Waktu Eksekusi:</span>
                        <span id="foto-modal-waktu" class="font-mono text-slate-800">—</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Detail Riwayat Aset Lengkap --}}
    <div id="modal-detail-riwayat" class="fixed inset-0 z-50 hidden items-center justify-center overflow-y-auto bg-slate-900/60 p-4 backdrop-blur-xs">
        <div class="fixed inset-0" onclick="tutupModalDetail()"></div>
        <div class="relative w-full max-w-2xl rounded-2xl bg-white shadow-2xl overflow-hidden my-8">
            <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4 bg-slate-50">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Detail Riwayat Penghapusan Aset</h3>
                    <p class="text-xs text-slate-500">Rekaman snapshot data inventaris dan dokumen resmi SK penghapusan.</p>
                </div>
                <button type="button" onclick="tutupModalDetail()" class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 text-xs">
                    <div class="space-y-3">
                        <div class="rounded-xl border border-slate-200 bg-white p-3 space-y-1">
                            <span class="text-[10px] uppercase font-bold text-slate-400">Nama Aset</span>
                            <p id="dt-nama" class="font-bold text-slate-900 text-sm">—</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-3 space-y-1">
                            <span class="text-[10px] uppercase font-bold text-slate-400">Nomor Kartu Barang</span>
                            <p id="dt-nomor-kartu" class="font-mono font-semibold text-slate-800">—</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-3 space-y-1">
                            <span class="text-[10px] uppercase font-bold text-slate-400">Dokumen SK Resmi</span>
                            <p id="dt-sk" class="font-bold text-emerald-800">—</p>
                            <p id="dt-tgl-sk" class="text-slate-600 text-[11px]">—</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-3 space-y-1">
                            <span class="text-[10px] uppercase font-bold text-slate-400">Jenis & Kategori</span>
                            <p id="dt-jenis" class="font-medium text-slate-700">—</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-3 space-y-1">
                            <span class="text-[10px] uppercase font-bold text-slate-400">Merk / Nilai Perolehan</span>
                            <p id="dt-merk" class="font-medium text-slate-700">—</p>
                            <p id="dt-nilai" class="font-mono font-semibold text-slate-800">—</p>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div class="rounded-xl border border-slate-200 bg-white p-3 space-y-1">
                            <span class="text-[10px] uppercase font-bold text-slate-400">SKPD / Unit</span>
                            <p id="dt-skpd" class="font-medium text-slate-700">—</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-3 space-y-1">
                            <span class="text-[10px] uppercase font-bold text-slate-400">Lokasi / Ruangan Terakhir</span>
                            <p id="dt-lokasi" class="font-medium text-slate-700">—</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-3 space-y-1">
                            <span class="text-[10px] uppercase font-bold text-slate-400">Pemegang Terakhir</span>
                            <p id="dt-pemegang" class="font-medium text-slate-700">—</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-3 space-y-1">
                            <span class="text-[10px] uppercase font-bold text-slate-400">Alasan Penghapusan</span>
                            <p id="dt-alasan" class="font-bold text-rose-700">—</p>
                            <p id="dt-keterangan" class="text-slate-500 mt-1">—</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-rose-50/50 p-3 space-y-1">
                            <span class="text-[10px] uppercase font-bold text-rose-700">Pelaksana & Waktu Eksekusi</span>
                            <p id="dt-eksekutor" class="font-bold text-slate-900">—</p>
                            <p id="dt-waktu" class="font-mono text-slate-600">—</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function bukaModalFoto(url, nama, pelaksana, waktu, noSk) {
            const modal = document.getElementById('modal-foto-bukti');
            if (!modal) return;
            document.getElementById('foto-modal-img').src = url;
            document.getElementById('foto-modal-sub').textContent = nama;
            document.getElementById('foto-modal-sk').textContent = noSk || '-';
            document.getElementById('foto-modal-pelaksana').textContent = pelaksana;
            document.getElementById('foto-modal-waktu').textContent = waktu;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }

        function tutupModalFoto() {
            const modal = document.getElementById('modal-foto-bukti');
            if (!modal) return;
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.getElementById('foto-modal-img').src = '';
            document.body.classList.remove('overflow-hidden');
        }

        function bukaModalDetail(data) {
            const modal = document.getElementById('modal-detail-riwayat');
            if (!modal) return;
            document.getElementById('dt-nama').textContent = data.nama;
            document.getElementById('dt-nomor-kartu').textContent = data.nomor_kartu;
            document.getElementById('dt-sk').textContent = 'No. SK: ' + data.no_sk;
            document.getElementById('dt-tgl-sk').textContent = 'Tanggal SK: ' + data.tanggal_sk;
            document.getElementById('dt-jenis').textContent = data.jenis + ' (' + data.kategori + ')';
            document.getElementById('dt-merk').textContent = data.merk + ' (' + data.kondisi + ')';
            document.getElementById('dt-nilai').textContent = data.nilai;
            document.getElementById('dt-skpd').textContent = data.skpd;
            document.getElementById('dt-lokasi').textContent = data.lokasi + ' / ' + data.ruangan;
            document.getElementById('dt-pemegang').textContent = data.pemegang;
            document.getElementById('dt-alasan').textContent = data.alasan;
            document.getElementById('dt-keterangan').textContent = data.keterangan;
            document.getElementById('dt-eksekutor').textContent = data.pelaksana;
            document.getElementById('dt-waktu').textContent = data.waktu_eksekusi;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }

        function tutupModalDetail() {
            const modal = document.getElementById('modal-detail-riwayat');
            if (!modal) return;
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }
    </script>
    @endpush
@endsection
