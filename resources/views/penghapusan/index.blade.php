@extends('layouts.app')

@section('title', 'Usulan Penghapusan Aset')

@section('content')
    <x-page-header title="Usulan Penghapusan Aset" subtitle="Daftar barang dalam proses penghapusan administrasi buku inventaris">
    </x-page-header>

    <x-alert type="success" />
    <x-alert type="error" />

    <div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-2xs">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h3 class="text-base font-bold text-slate-900">Daftar Usulan Penghapusan Aset</h3>
                <p class="text-xs text-slate-400">Aset di sini hanya mengubah status — tidak dihapus dari database, hanya arsip status</p>
            </div>
            <div class="flex items-center gap-2">
                <form method="GET" action="{{ route('penghapusan.index') }}" class="flex items-center gap-2">
                    <div class="relative w-64">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama / kode aset..."
                               class="w-full rounded-xl border border-slate-200 bg-white py-1.5 pl-8 pr-3 text-xs text-slate-800 placeholder-slate-400 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                        <svg class="pointer-events-none absolute left-2.5 top-2 h-3.5 w-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    @if (request()->has('search'))
                        <a href="{{ route('penghapusan.index') }}" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50">Reset</a>
                    @endif
                </form>
                <button type="button" onclick="openModalUsulanManual()" class="inline-flex items-center gap-1.5 rounded-xl bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700 transition hover:bg-rose-100">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Usulkan Aset</span>
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-100 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                        <th class="py-3 px-3">Aset & Kode Barang</th>
                        <th class="py-3 px-3">Lokasi / Pemegang Terakhir</th>
                        <th class="py-3 px-3">Nilai Buku</th>
                        <th class="py-3 px-3">Alasan Usulan</th>
                        <th class="py-3 px-3 text-center">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse ($usulanList as $item)
                        @php
                            $alasan = $item->alasan_penghapusan ?? '';
                            $badge = 'bg-slate-100 text-slate-600';
                            if (str_contains($alasan, 'Masa Pakai')) {
                                $badge = 'bg-amber-50 text-amber-700';
                            } elseif (str_contains($alasan, 'Rusak')) {
                                $badge = 'bg-rose-50 text-rose-700';
                            }
                        @endphp
                        <tr class="transition hover:bg-slate-50/50">
                            <td class="py-3 px-3">
                                <span class="font-bold text-slate-800">{{ $item->aset?->barang?->nama_barang ?? '-' }}</span>
                                <span class="block font-mono text-[11px] text-slate-400">{{ $item->aset?->nomor_kartu_barang ?? '-' }}</span>
                                <span class="block text-[10px] text-slate-300">Diusulkan {{ $item->tanggal_usulan?->format('d M Y') ?? '-' }}</span>
                            </td>
                            <td class="py-3 px-3 text-slate-600">
                                {{ $item->aset?->penempatanAktif?->ruangan?->nama_ruangan ?? '-' }}
                                <span class="block text-[11px] text-slate-400">{{ $item->aset?->pemegangSaatIni?->pegawai?->nama_pegawai ?? 'Tanpa pemegang' }}</span>
                            </td>
                            <td class="py-3 px-3 font-mono text-slate-600">
                                Rp {{ number_format($item->aset?->nilai_perolehan ?? 0, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-3">
                                <span class="rounded-full px-2.5 py-0.5 text-[10px] font-bold {{ $badge }}">{{ $item->alasan_penghapusan ?? '-' }}</span>
                            </td>
                            <td class="py-3 px-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <form action="{{ route('penghapusan.reaktifkan', $item->id_usulan_hapus) }}" method="POST" onsubmit="return confirm('Batalkan usulan dan kembalikan aset ini ke status aktif?')">
                                        @csrf
                                        <button type="submit" class="rounded-xl border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700 transition hover:bg-emerald-100">Re-Aktifkan</button>
                                    </form>
                                    <form action="{{ route('penghapusan.eksekusi', $item->id_usulan_hapus) }}" method="POST" onsubmit="return confirm('Apakah SK penghapusan sudah terbit untuk aset ini? Aset akan diarsipkan sebagai dihapuskan.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-xl border border-rose-200 bg-rose-50 px-2.5 py-1 text-[11px] font-bold text-rose-700 transition hover:bg-rose-100">Arsipkan Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-slate-400">Tidak ada usulan penghapusan aset yang aktif.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $usulanList->links() }}
        </div>
    </div>

    {{-- Modal: Usulkan Penghapusan Aset --}}
    <div id="usulan-modal" class="fixed inset-0 z-50 hidden items-center justify-center overflow-y-auto bg-slate-900/50 p-4">
        <div class="fixed inset-0" data-usulan-close></div>
        <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Usulkan Penghapusan Aset</h3>
                    <p class="mt-0.5 text-sm text-slate-500">Aset masuk antrean hingga SK penghapusan terbit.</p>
                </div>
                <button type="button" data-usulan-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('penghapusan.store') }}" autocomplete="off">
                @csrf
                <div class="space-y-5 px-6 py-6">
                    <div class="space-y-1.5">
                        <label for="usulan-aset" class="block text-sm font-medium text-slate-700">Pilih Aset <span class="text-red-500">*</span></label>
                        <select id="usulan-aset" name="id_aset" required class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                            <option value="" selected>-- Pilih Aset --</option>
                            @foreach ($asetAktifList as $a)
                                <option value="{{ $a->id_aset }}">{{ $a->barang?->nama_barang ?? 'Aset' }} #{{ $a->nomor_kartu_barang }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label for="usulan-alasan" class="block text-sm font-medium text-slate-700">Alasan <span class="text-red-500">*</span></label>
                        <select id="usulan-alasan" name="alasan_penghapusan" required class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                            <option value="" selected>-- Pilih Alasan --</option>
                            <option>Masa Pakai Habis</option>
                            <option>Rusak Berat</option>
                            <option>Hilang</option>
                            <option>Lainnya</option>
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label for="usulan-keterangan" class="block text-sm font-medium text-slate-700">Keterangan</label>
                        <textarea id="usulan-keterangan" name="keterangan" rows="2" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="opsional"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-6 py-4">
                    <button type="button" data-usulan-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-rose-600 px-5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-rose-700">Ajukan Usulan</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        const usulanModal = document.getElementById('usulan-modal');

        function openModalUsulanManual() {
            if (!usulanModal) return;
            usulanModal.classList.remove('hidden');
            usulanModal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }

        function closeModalUsulan() {
            if (!usulanModal) return;
            usulanModal.classList.add('hidden');
            usulanModal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }

        if (usulanModal) {
            usulanModal.querySelectorAll('[data-usulan-close]').forEach(el => el.addEventListener('click', closeModalUsulan));
        }
    </script>
    @endpush
@endsection