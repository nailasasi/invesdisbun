@extends('layouts.app')

@section('title', 'Usulan Penghapusan Aset')

@section('page-title', 'Penghapusan Aset')

@section('content')
    <x-alert type="success" />
    <x-alert type="warning" />
    <x-alert type="error" />

    {{-- Navigasi Tab: Antrean vs Riwayat --}}
    <div class="mb-4 flex items-center justify-between gap-4 border-b border-slate-200">
        <div class="flex items-center gap-2">
            <a href="{{ route('penghapusan.index') }}"
               class="inline-flex items-center gap-2 border-b-2 border-disbun-700 px-4 py-2.5 text-sm font-bold text-disbun-700">
                <span>Antrean Usulan</span>
                <span class="rounded-full bg-disbun-100 px-2 py-0.5 text-xs font-bold text-disbun-800">{{ $usulanList->total() }}</span>
            </a>
            <a href="{{ route('penghapusan.riwayat') }}"
               class="inline-flex items-center gap-2 border-b-2 border-transparent px-4 py-2.5 text-sm font-semibold text-slate-500 transition hover:border-slate-300 hover:text-slate-800">
                <span>Riwayat Penghapusan</span>
                @if (isset($totalRiwayat) && $totalRiwayat > 0)
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ $totalRiwayat }}</span>
                @endif
            </a>
        </div>
    </div>

    <x-card :padding="false">
        <x-action-bar>
            <form method="GET" action="{{ route('penghapusan.index') }}" class="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative flex-1 sm:max-w-sm">
                    <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama / kode aset..."
                           class="w-full rounded-2xl border border-slate-200 bg-white py-2.5 pl-11 pr-4 text-sm text-slate-800 shadow-sm transition placeholder:text-slate-400 focus:border-disbun-600 focus:outline-none focus:ring-2 focus:ring-disbun-400/40">
                </div>
                <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-disbun-700 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-disbun-800">
                    Cari
                </button>
                @if (request()->has('search'))
                    <a href="{{ route('penghapusan.index') }}" class="inline-flex items-center rounded-2xl border border-disbun-card-border bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">Reset</a>
                @endif
            </form>

            <x-slot name="actions">
                <button type="button" onclick="openModalUsulanManual()" class="inline-flex items-center gap-1.5 rounded-2xl bg-disbun-700 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-disbun-800">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Usulkan Aset</span>
                </button>
            </x-slot>
        </x-action-bar>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-100 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                        <th class="py-3 px-3">Aset & Kode Barang</th>
                        <th class="py-3 px-3">Lokasi / Pemegang Terakhir</th>
                        <th class="py-3 px-3">Kondisi</th>
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
                            $isInventaris = (bool) $item->inventaris;
                            if ($isInventaris) {
                                $inv = $item->inventaris;
                                $namaAset = $inv->nama_barang;
                                $nomorKartu = $inv->kode_inventaris;
                                $ruangNama = $inv->ruangan?->nama_ruangan ?? '-';
                                $lokasiGedung = $inv->ruangan?->lokasi?->nama_lokasi ?? '-';
                                $lokasi = $ruangNama . ' (' . $lokasiGedung . ')';
                                $pemegang = 'Ruangan (Non-Personal)';
                                $nilaiFormatted = '—';
                                $kondisiAset = $inv->kondisi ?? 'Rusak Berat';
                            } else {
                                $namaAset = $item->aset?->kendaraan?->namaTampil() ?? $item->aset?->barang?->nama_barang ?? 'Aset #'.$item->id_aset;
                                $nomorKartu = $item->aset?->nomor_kartu_barang ?? '-';
                                $lokasi = $item->aset?->penempatanAktif?->ruangan?->nama_ruangan ?? $item->aset?->lokasi?->nama_lokasi ?? '-';
                                $pemegang = $item->aset?->pemegangSaatIni?->pegawai?->nama_pegawai ?? 'Tanpa Pemegang';
                                $nilaiFormatted = 'Rp ' . number_format($item->aset?->nilai_perolehan ?? 0, 0, ',', '.');
                                $kondisiAset = $item->aset?->kondisi;
                            }
                        @endphp
                        <tr class="transition hover:bg-slate-50/50">
                            <td class="py-3 px-3">
                                <span class="font-bold text-slate-800">{{ $namaAset }}</span>
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <span class="font-mono text-[11px] text-slate-400">{{ $nomorKartu }}</span>
                                    @if ($isInventaris)
                                        <span class="inline-flex items-center rounded-full bg-purple-50 px-2 py-0.2 text-[10px] font-bold text-purple-700">Inventaris Lokal</span>
                                    @endif
                                </div>
                                @if ($isInventaris && $item->inventaris?->merk)
                                    <span class="block text-[11px] text-slate-500">Merk: {{ $item->inventaris->merk }}</span>
                                @endif
                                <span class="block text-[10px] text-slate-300">Diusulkan {{ $item->tanggal_usulan?->format('d M Y') ?? '-' }}</span>
                            </td>
                            <td class="py-3 px-3 text-slate-600">
                                {{ $lokasi }}
                                <span class="block text-[11px] text-slate-400">{{ $pemegang }}</span>
                            </td>
                            <td class="py-3 px-3">
                                @php
                                    $kondisiAset = $item->aset?->kondisi;
                                    $kondisiBadge = match ($kondisiAset) {
                                        'Rusak Berat' => 'bg-rose-50 text-rose-700',
                                        'Rusak Ringan' => 'bg-amber-50 text-amber-700',
                                        'Baik' => 'bg-emerald-50 text-emerald-700',
                                        default => 'bg-slate-100 text-slate-600',
                                    };
                                @endphp
                                <span class="rounded-full px-2.5 py-0.5 text-[10px] font-bold {{ $kondisiBadge }}">{{ $kondisiAset ?? '-' }}</span>
                            </td>
                            <td class="py-3 px-3 font-mono text-slate-600">
                                {{ $nilaiFormatted }}
                            </td>
                            <td class="py-3 px-3">
                                <span class="rounded-full px-2.5 py-0.5 text-[10px] font-bold {{ $badge }}">{{ $item->alasan_penghapusan ?? '-' }}</span>
                            </td>
                            <td class="py-3 px-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    @if (auth()->user()?->role?->nama_role === 'Admin Aset')
                                        <form action="{{ route('penghapusan.reaktifkan', $item->id_usulan_hapus) }}" method="POST" onsubmit="return confirm('Batalkan usulan dan kembalikan barang ini ke status aktif?')">
                                            @csrf
                                            <button type="submit" class="rounded-xl border border-disbun-100 bg-disbun-50 px-2.5 py-1 text-[11px] font-bold text-disbun-800 transition hover:bg-disbun-100">Re-Aktifkan</button>
                                        </form>

                                        {{-- Tombol Hapus Permanen: Membuka Modal Otorisasi Kamera --}}
                                        <button type="button"
                                                onclick="bukaModalHapusPermanen({
                                                    id: {{ $item->id_usulan_hapus }},
                                                    action: '{{ route('penghapusan.eksekusi', $item->id_usulan_hapus) }}',
                                                    nama: '{{ addslashes($namaAset) }}',
                                                    kode: '{{ addslashes($nomorKartu) }}',
                                                    alasan: '{{ addslashes($item->alasan_penghapusan ?? '-') }}',
                                                    lokasi: '{{ addslashes($lokasi) }}',
                                                    pemegang: '{{ addslashes($pemegang) }}',
                                                    nilai: '{{ $nilaiFormatted }}'
                                                })"
                                                class="rounded-xl border border-rose-200 bg-rose-50 px-2.5 py-1 text-[11px] font-bold text-rose-700 transition hover:bg-rose-100">
                                            Hapus Permanen
                                        </button>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-medium text-slate-600">
                                            Menunggu Verifikasi
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400">Tidak ada usulan penghapusan aset yang aktif.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-disbun-card-border px-6 py-4">
            {{ $usulanList->links() }}
        </div>
    </x-card>

    {{-- Modal: Usulkan Penghapusan Aset --}}
    <div id="usulan-modal" class="fixed inset-0 z-50 hidden items-center justify-center overflow-y-auto bg-slate-900/50 p-4">
        <div class="fixed inset-0" data-usulan-close></div>
        <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Usulkan Penghapusan Aset</h3>
                    <p class="mt-0.5 text-sm text-slate-500">Aset masuk antrean hingga dieksekusi hapus permanen.</p>
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
                                <option value="{{ $a->id_aset }}">{{ $a->kendaraan?->namaTampil() ?? $a->barang?->nama_barang ?? 'Aset' }} #{{ $a->nomor_kartu_barang }}</option>
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

@endsection

@push('modals')
    @if (auth()->user()?->role?->nama_role === 'Admin Aset')
    {{-- Modal Otorisasi: Hapus Permanen --}}
    <div id="modal-hapus-permanen"
         class="fixed inset-0 hidden items-center justify-center p-3 sm:p-4 lg:pl-72 lg:pr-6 overflow-y-auto"
         style="z-index: 9999;"
         role="dialog"
         aria-modal="true">
        {{-- Fullscreen dark backdrop covering entire viewport, header & sidebar --}}
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" onclick="tutupModalHapusPermanen()"></div>

        {{-- Modal Dialog Container: centered in main content area after sidebar --}}
        <div class="relative flex flex-col w-[94%] sm:w-[90%] lg:w-[88%] max-w-[980px] max-h-[85vh] rounded-2xl bg-white shadow-2xl overflow-hidden my-auto z-10 border border-slate-100">
            {{-- Modal Header (Fixed at top) --}}
            <div class="flex items-start justify-between border-b border-rose-100 bg-rose-50/70 px-5 sm:px-6 py-3.5 shrink-0">
                <div>
                    <h3 class="text-base sm:text-lg font-bold text-rose-900">Otorisasi Hapus Permanen Aset</h3>
                    <p class="mt-0.5 text-xs text-rose-600">Aset akan dikeluarkan dari inventaris aktif secara permanen dan dicatat ke riwayat.</p>
                </div>
                <button type="button" onclick="tutupModalHapusPermanen()" class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Form Wraps Body and Footer --}}
            <form id="form-hapus-permanen" method="POST" action="" class="flex flex-col flex-1 min-h-0 overflow-hidden">
                @csrf
                @method('DELETE')

                {{-- Scrollable Modal Body --}}
                <div class="flex-1 overflow-y-auto px-5 sm:px-6 py-4 sm:py-5">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 lg:gap-6 items-start">
                        {{-- Kolom Kiri — Informasi SK dan Otorisasi --}}
                        <div class="space-y-4">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                                <span class="text-xs font-bold uppercase tracking-wider text-slate-700">Informasi SK & Otorisasi</span>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">Dokumen & Akun</span>
                            </div>

                            {{-- Ringkasan Objek Tindakan --}}
                            <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-3.5 text-xs space-y-2">
                                <div class="flex justify-between items-start gap-2">
                                    <div class="min-w-0 flex-1">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Nama Aset</span>
                                        <p id="hapus-nama-aset" class="font-bold text-slate-900 text-sm truncate">—</p>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Nomor Kartu Barang</span>
                                        <p id="hapus-kode-aset" class="font-mono font-semibold text-slate-700">—</p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-200/60 text-slate-600">
                                    <div>
                                        <span class="text-[10px] text-slate-400">Lokasi / Ruangan:</span>
                                        <p id="hapus-lokasi-aset" class="font-medium truncate">—</p>
                                    </div>
                                    <div>
                                        <span class="text-[10px] text-slate-400">Pemegang Terakhir:</span>
                                        <p id="hapus-pemegang-aset" class="font-medium truncate">—</p>
                                    </div>
                                    <div>
                                        <span class="text-[10px] text-slate-400">Nilai Perolehan:</span>
                                        <p id="hapus-nilai-aset" class="font-mono font-medium">—</p>
                                    </div>
                                    <div>
                                        <span class="text-[10px] text-slate-400">Alasan Usulan:</span>
                                        <p id="hapus-alasan-aset" class="font-medium text-rose-700 truncate">—</p>
                                    </div>
                                </div>
                            </div>

                            {{-- Dokumen Resmi SK Penghapusan --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div class="space-y-1.5">
                                    <label for="no_sk" class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                        Nomor SK Resmi <span class="text-rose-500">*</span>
                                    </label>
                                    <input type="text" id="no_sk" name="no_sk" required maxlength="100"
                                           placeholder="Contoh: 030/SK-HAPUS/2026"
                                           value="{{ old('no_sk') }}"
                                           class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm transition focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-400">
                                    @error('no_sk')
                                        <p class="text-xs font-semibold text-rose-600 mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="space-y-1.5">
                                    <label for="tanggal_sk" class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                        Tanggal SK Resmi <span class="text-rose-500">*</span>
                                    </label>
                                    <input type="date" id="tanggal_sk" name="tanggal_sk" required max="{{ date('Y-m-d') }}"
                                           value="{{ old('tanggal_sk') }}"
                                           class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm transition focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-400">
                                    @error('tanggal_sk')
                                        <p class="text-xs font-semibold text-rose-600 mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                            <p class="text-[11px] text-slate-400 -mt-2">
                                * Nomor dan tanggal SK sesuai fisik dokumen. Tanggal SK tidak boleh melebihi hari ini.
                            </p>

                            {{-- Konfirmasi Ulang Password Akun (Step-up Verification) --}}
                            <div class="space-y-1.5">
                                <label for="password_otorisasi" class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                    Password Akun Login <span class="text-rose-500">*</span>
                                </label>
                                <input type="password" id="password_otorisasi" name="password_otorisasi" required autocomplete="current-password"
                                       placeholder="Masukkan password akun Anda untuk konfirmasi"
                                       class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm transition focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-400">
                                @error('password_otorisasi')
                                    <p class="text-xs font-semibold text-rose-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Kolom Kanan — Bukti Otorisasi --}}
                        <div class="space-y-3">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                                <span class="text-xs font-bold uppercase tracking-wider text-slate-700">
                                    Bukti Otorisasi Kamera <span class="text-rose-500">*</span>
                                </span>
                                <span class="rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-semibold text-rose-700">Live Kamera</span>
                            </div>

                            <div class="-mt-4">
                                <x-otorisasi-bukti />
                            </div>
                            @error('foto_bukti_otentikasi')
                                <p class="text-xs font-semibold text-rose-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Modal Footer (Fixed at bottom) --}}
                <div class="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50 px-5 sm:px-6 py-3.5 shrink-0">
                    <button type="button" onclick="tutupModalHapusPermanen()" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-100 transition">
                        Batal
                    </button>
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-rose-600 px-5 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-rose-700">
                        Konfirmasi Hapus Permanen
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
@endpush

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

    function aturPosisiModalHapusPermanen() {
        const modal = document.getElementById('modal-hapus-permanen');
        const sidebar = document.getElementById('sidebar');
        if (!modal) return;

        const isDesktop = window.innerWidth >= 1024;
        const isSidebarCollapsed = sidebar && sidebar.classList.contains('lg:-translate-x-full');

        if (isDesktop) {
            if (isSidebarCollapsed) {
                modal.classList.remove('lg:pl-72');
                modal.classList.add('lg:pl-6');
            } else {
                modal.classList.remove('lg:pl-6');
                modal.classList.add('lg:pl-72');
            }
        } else {
            modal.classList.remove('lg:pl-72', 'lg:pl-6');
        }
    }

    window.addEventListener('resize', aturPosisiModalHapusPermanen);

    function bukaModalHapusPermanen(data) {
        const modal = document.getElementById('modal-hapus-permanen');
        const form = document.getElementById('form-hapus-permanen');
        if (!modal || !form) return;

        form.action = data.action;
        document.getElementById('hapus-nama-aset').textContent = data.nama;
        document.getElementById('hapus-kode-aset').textContent = data.kode;
        document.getElementById('hapus-lokasi-aset').textContent = data.lokasi;
        document.getElementById('hapus-pemegang-aset').textContent = data.pemegang;
        document.getElementById('hapus-nilai-aset').textContent = data.nilai;
        document.getElementById('hapus-alasan-aset').textContent = data.alasan;

        // Reset input form
        const noSkInput = document.getElementById('no_sk');
        if (noSkInput) noSkInput.value = '';
        const tglSkInput = document.getElementById('tanggal_sk');
        if (tglSkInput) tglSkInput.value = '';
        const pwdInput = document.getElementById('password_otorisasi');
        if (pwdInput) pwdInput.value = '';

        aturPosisiModalHapusPermanen();

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.classList.add('overflow-hidden');

        // Auto-trigger buka kamera bila ada tombol buka
        const btnBuka = modal.querySelector('[data-buka]');
        if (btnBuka && !btnBuka.classList.contains('hidden')) {
            btnBuka.click();
        }
    }

    function tutupModalHapusPermanen() {
        const modal = document.getElementById('modal-hapus-permanen');
        if (!modal) return;

        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');

        // Hentikan stream kamera saat modal ditutup
        const btnTutup = modal.querySelector('[data-tutup]');
        if (btnTutup) {
            btnTutup.click();
        }
    }
</script>
@endpush
