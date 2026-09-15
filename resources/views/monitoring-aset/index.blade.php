@extends('layouts.app')

@section('title', 'Monitoring Aset')

@section('content')
<div class="space-y-6">
    {{-- Header Title & Action --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-slate-900">Monitoring Aset</h2>
            <p class="text-xs text-slate-400">Pusat Pengawasan Nilai Buku & Masa Manfaat Aset</p>
        </div>
        <a href="{{ route('monitoring-aset.export', request()->query()) }}"
           class="inline-flex items-center gap-2 rounded-2xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-2xs transition hover:bg-emerald-700">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <span>Rekap Excel</span>
        </a>
    </div>

    <x-alert type="success" />
    <x-alert type="error" />

    {{-- 3 Metrics Card (Grid 3 Kolom) --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        {{-- Card 1 --}}
        <div class="flex items-center justify-between rounded-3xl border border-slate-100 bg-white p-5 shadow-2xs">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Nilai Buku Aset</p>
                <h3 class="mt-1 text-xl font-black text-emerald-600">
                    Rp {{ number_format($totalNilaiBuku ?? 0, 0, ',', '.') }}
                </h3>
                <p class="mt-1 text-[11px] text-slate-400">Akumulasi aset belum dihapus</p>
            </div>
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>

        {{-- Card 2 --}}
        <div class="flex items-center justify-between rounded-3xl border border-slate-100 bg-white p-5 shadow-2xs">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Aset Nilai Buku Habis</p>
                <h3 class="mt-1 text-xl font-black text-rose-600">
                    {{ $asetHabisCount ?? 0 }} <span class="text-xs font-semibold text-slate-500">Aset</span>
                </h3>
                <p class="mt-1 text-[11px] text-slate-400">Nilai ekonomis habis (Rp 0)</p>
            </div>
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-rose-50 text-rose-600">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>

        {{-- Card 3 --}}
        <div class="flex items-center justify-between rounded-3xl border border-slate-100 bg-white p-5 shadow-2xs">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Masa Pakai Kritis</p>
                <h3 class="mt-1 text-xl font-black text-amber-500">
                    {{ $asetKritisCount ?? 0 }} <span class="text-xs font-semibold text-slate-500">Aset</span>
                </h3>
                <p class="mt-1 text-[11px] text-slate-400">Sisa masa manfaat &lt; 30 hari</p>
            </div>
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-50 text-amber-500">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
        </div>
    </div>

    {{-- Main Table Container --}}
    <div class="rounded-3xl border border-slate-100 bg-white p-5 shadow-2xs">
        {{-- Filter Bar --}}
        <form action="{{ route('monitoring-aset.index') }}" method="GET" class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                {{-- Search Input --}}
                <div class="relative w-64">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama / kode aset..."
                           class="w-full rounded-2xl border border-slate-200 bg-white py-2 pl-9 pr-3 text-xs text-slate-800 placeholder-slate-400 focus:border-emerald-500 focus:outline-hidden focus:ring-1 focus:ring-emerald-500">
                    <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>

                {{-- Filter Tahun --}}
                <select name="tahun" class="rounded-2xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 focus:border-emerald-500 focus:outline-hidden focus:ring-1 focus:ring-emerald-500">
                    <option value="">Semua Tahun</option>
                    @foreach ($tahunList ?? [] as $th)
                        <option value="{{ $th }}" {{ request('tahun') == $th ? 'selected' : '' }}>{{ $th }}</option>
                    @endforeach
                </select>

                {{-- Filter Status --}}
                <select name="status" class="rounded-2xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 focus:border-emerald-500 focus:outline-hidden focus:ring-1 focus:ring-emerald-500">
                    <option value="">Semua Status</option>
                    <option value="habis" {{ request('status') == 'habis' ? 'selected' : '' }}>Masa Pakai Habis</option>
                    <option value="kritis" {{ request('status') == 'kritis' ? 'selected' : '' }}>Kritis (&lt; 30 Hari)</option>
                    <option value="normal" {{ request('status') == 'normal' ? 'selected' : '' }}>Normal</option>
                </select>

                <button type="submit" class="rounded-2xl bg-slate-900 px-4 py-2 text-xs font-bold text-white transition hover:bg-slate-800">
                    Filter
                </button>
                @if (request()->anyFilled(['search', 'tahun', 'status']))
                    <a href="{{ route('monitoring-aset.index') }}" class="rounded-2xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50">
                        Reset
                    </a>
                @endif
            </div>
        </form>

        {{-- Tabel Monitoring --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-100 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                        <th class="py-3 px-3">#</th>
                        <th class="py-3 px-3">Aset & Kode</th>
                        <th class="py-3 px-3">Lokasi / Pemegang</th>
                        <th class="py-3 px-3">Nilai Perolehan</th>
                        <th class="py-3 px-3">Nilai Buku</th>
                        <th class="py-3 px-3">Tgl Pengadaan</th>
                        <th class="py-3 px-3">Masa Pakai</th>
                        <th class="py-3 px-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse ($asetList as $index => $item)
                        @php
                            $masaBadge = match ($item->masa_status) {
                                'habis' => 'bg-rose-50 text-rose-700',
                                'kritis' => 'bg-amber-50 text-amber-700',
                                'normal' => 'bg-emerald-50 text-emerald-700',
                                default => 'bg-slate-100 text-slate-400',
                            };
                            $masaText = match ($item->masa_status) {
                                'habis' => 'Habis',
                                'kritis', 'normal' => $item->sisa_hari . ' Hari',
                                default => '—',
                            };
                        @endphp
                        <tr class="transition hover:bg-slate-50/50">
                            <td class="py-3 px-3 font-mono text-slate-400">{{ ($asetList->firstItem() ?? 0) + $index }}</td>
                            <td class="py-3 px-3">
                                <span class="font-bold text-slate-800">{{ $item->nama_barang ?? '-' }}</span>
                                <span class="block font-mono text-[11px] text-slate-400">{{ $item->nomor_kartu ?? '-' }}</span>
                            </td>
                            <td class="py-3 px-3 text-slate-600">
                                {{ $item->lokasi ?? ($item->pemegang ?? '-') }}
                                <span class="block text-[11px] text-slate-400">{{ $item->pemegang ? (($item->lokasi ? 'Pemegang: ' : '') . $item->pemegang) : 'Tanpa pemegang' }}</span>
                            </td>
                            <td class="py-3 px-3 font-mono text-slate-600">
                                @if ($item->punya_nilai)
                                    Rp {{ number_format($item->nilai_perolehan ?? 0, 0, ',', '.') }}
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="py-3 px-3 font-mono font-semibold {{ $item->punya_nilai && $item->nilai_buku <= 0 ? 'text-rose-600' : 'text-slate-700' }}">
                                @if ($item->punya_nilai)
                                    Rp {{ number_format($item->nilai_buku ?? 0, 0, ',', '.') }}
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="py-3 px-3 text-slate-500">
                                {{ $item->tanggal_pengadaan ? \Carbon\Carbon::parse($item->tanggal_pengadaan)->format('d M Y') : '-' }}
                            </td>
                            <td class="py-3 px-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-bold {{ $masaBadge }}">{{ $masaText }}</span>
                            </td>
                            <td class="py-3 px-3 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    @if ($item->bisa_usul)
                                        <button type="button"
                                                data-usulan-id="{{ $item->id_aset }}"
                                                data-usulan-name="{{ $item->nama_barang ?? 'Aset' }}"
                                                data-usulan-kode="{{ $item->nomor_kartu ?? '-' }}"
                                                data-usulan-alasan="{{ $item->usulan_alasan }}"
                                                onclick="openModalUsulanMonitoring(this)"
                                                class="inline-flex rounded-xl border border-rose-200 bg-rose-50 px-2.5 py-1 text-[11px] font-bold text-rose-700 transition hover:bg-rose-100">
                                            Usulkan Hapus
                                        </button>
                                    @elseif ($item->dalam_antrean)
                                        <span class="rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-bold text-amber-700">Dalam Antrean</span>
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                    <a href="{{ route('aset-barang.aset.detail', $item->id_aset) }}"
                                       class="inline-flex rounded-xl border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-bold text-slate-600 transition hover:bg-slate-50">
                                        Detail
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-slate-400">Data aset tidak ditemukan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $asetList->links() }}
        </div>
    </div>
</div>

    {{-- MODAL: USULKAN HAPUS --}}
    <div id="usulan-monitoring-modal" class="fixed inset-0 z-50 hidden items-center justify-center overflow-y-auto bg-slate-900/50 p-4">
        <div class="fixed inset-0" data-usulan-m-close></div>
        <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Usulkan Penghapusan Aset</h3>
                    <p class="mt-0.5 text-sm text-slate-500">Aset ini akan masuk antrean Usulan Penghapusan.</p>
                </div>
                <button type="button" data-usulan-m-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('penghapusan.store') }}" autocomplete="off">
                @csrf
                <input type="hidden" name="id_aset" id="usulan-m-id" value="">

                <div class="space-y-5 px-6 py-6">
                    <div class="rounded-2xl border border-slate-100 bg-slate-50/60 px-4 py-3">
                        <p id="usulan-m-name" class="text-sm font-bold text-slate-800">-</p>
                        <p id="usulan-m-kode" class="mt-0.5 font-mono text-[11px] text-slate-400">-</p>
                    </div>

                    <div class="space-y-1.5">
                        <label for="usulan-m-alasan" class="block text-sm font-medium text-slate-700">Alasan <span class="text-red-500">*</span></label>
                        <select id="usulan-m-alasan" name="alasan_penghapusan" required class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                            <option>Masa Pakai Habis</option>
                            <option>Rusak Berat</option>
                            <option>Hilang</option>
                            <option>Lainnya</option>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label for="usulan-m-keterangan" class="block text-sm font-medium text-slate-700">Keterangan</label>
                        <textarea id="usulan-m-keterangan" name="keterangan" rows="2" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="opsional"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-6 py-4">
                    <button type="button" data-usulan-m-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-rose-600 px-5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-rose-700">Masukkan Ke Antrean</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        const usulanMonitoringModal = document.getElementById('usulan-monitoring-modal');

        function openModalUsulanMonitoring(btn) {
            if (!usulanMonitoringModal) return;
            document.getElementById('usulan-m-id').value = btn.dataset.usulanId ?? '';
            document.getElementById('usulan-m-name').textContent = btn.dataset.usulanName ?? '-';
            document.getElementById('usulan-m-kode').textContent = 'No. Kartu: ' + (btn.dataset.usulanKode ?? '-');
            document.getElementById('usulan-m-alasan').value = btn.dataset.usulanAlasan ?? 'Lainnya';
            document.getElementById('usulan-m-keterangan').value = '';
            usulanMonitoringModal.classList.remove('hidden');
            usulanMonitoringModal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }

        function closeModalUsulanMonitoring() {
            if (!usulanMonitoringModal) return;
            usulanMonitoringModal.classList.add('hidden');
            usulanMonitoringModal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }

        if (usulanMonitoringModal) {
            usulanMonitoringModal.querySelectorAll('[data-usulan-m-close]').forEach(el => el.addEventListener('click', closeModalUsulanMonitoring));
        }
    </script>
    @endpush
@endsection