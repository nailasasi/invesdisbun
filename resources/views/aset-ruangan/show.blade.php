@extends('layouts.app')

@section('title', 'Detail Aset Ruangan')

@section('content')
    {{-- Navigasi Kembali Minimalis di Atas Judul --}}
    <div class="mb-3">
        <a href="{{ route('aset-ruangan.index') }}"
           class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 transition hover:text-slate-900">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <span>Kembali ke Aset Ruangan</span>
        </a>
    </div>

    {{-- Header Judul & Aksi Utama --}}
    <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">{{ $ruangan->nama_ruangan }}</h1>
            <p class="mt-1 text-sm text-slate-500">Aset yang ditempatkan di ruangan ini</p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <a href="{{ route('aset-ruangan.label.download', $ruangan->id_ruangan) }}"
               class="inline-flex items-center gap-1.5 rounded-xl border border-amber-200 bg-amber-50/80 px-3 py-1.5 text-xs font-bold text-amber-700 shadow-2xs transition hover:bg-amber-100"
               title="Unduh Semua Label Aset di Ruangan Ini">
                <svg class="h-3.5 w-3.5 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                <span>Cetak Semua Label</span>
            </a>

            {{-- Cetak KIR (Biru / Sky Pastel) --}}
            <a href="{{ route('aset-ruangan.kir.download', $ruangan->id_ruangan) }}"
               target="_blank"
               class="inline-flex items-center gap-1.5 rounded-xl border border-sky-200 bg-sky-50/80 px-3 py-1.5 text-xs font-bold text-sky-700 shadow-2xs transition hover:bg-sky-100"
               title="Unduh Kartu Inventaris Ruangan (KIR)">
                <svg class="h-3.5 w-3.5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span>Cetak KIR</span>
            </a>
        </div>
    </div>

    <x-alert type="success" />
    <x-alert type="error" />

    <x-card class="mb-5">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Lantai</p>
                <p class="mt-1 text-sm font-semibold text-slate-800">{{ $ruangan->lantai ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wider text-slate-400">SKPD</p>
                <p class="mt-1 text-sm font-semibold text-slate-800">{{ $ruangan->skpd?->nama_skpd ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Jumlah Aset</p>
                <p class="mt-1 text-sm font-semibold text-slate-800">{{ $asetList->count() }} aset</p>
            </div>
        </div>
    </x-card>

    <x-card :padding="false">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-6 py-4">
            <p class="text-sm font-semibold text-slate-700">Daftar Aset</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">#</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nama Barang</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Kartu Barang</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Merk</th>
                        @if ($isAdminAset)
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nilai</th>
                        @endif
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Kondisi</th>
                        @if ($isAdminAset)
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Pemegang</th>
                        @endif
                        @if ($isAdminAset)
                        <th scope="col" class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($asetList as $i => $aset)
                        @php
                            $pemegang = $aset->pemegangSaatIni?->pegawai;
                            $kondisiColor = match ($aset->kondisi) {
                                'Baik' => 'bg-emerald-100 text-emerald-700',
                                'Rusak Ringan' => 'bg-amber-100 text-amber-700',
                                'Rusak Berat' => 'bg-red-100 text-red-700',
                                default => 'bg-slate-100 text-slate-600',
                            };
                        @endphp
                        <tr class="transition hover:bg-slate-50">
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $i + 1 }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{{ $aset->barang?->nama_barang ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $aset->nomor_kartu_barang ?? '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $aset->merk ?? '-' }}</td>
                            @if ($isAdminAset)
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $aset->nilai_perolehan ? 'Rp ' . number_format((float) $aset->nilai_perolehan, 0, ',', '.') : '-' }}</td>
                            @endif
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $kondisiColor }}">{{ $aset->kondisi ?? '-' }}</span>
                            </td>
                            @if ($isAdminAset)
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                @if ($pemegang)
                                    <span class="font-medium text-slate-700">{{ $pemegang->nama_pegawai }}</span>
                                @else   
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            @endif
                            @if ($isAdminAset)
                            <td class="whitespace-nowrap px-3 py-4 text-sm">
                                <div class="inline-flex items-center gap-1 rounded-2xl border border-slate-200/70 bg-slate-50/60 p-1 shadow-2xs">
                                <a href="{{ route('aset-barang.cetak.label.single', $aset->id_aset) }}" target="_blank" title="Cetak Label" class="flex h-7 w-7 items-center justify-center rounded-xl bg-amber-50 text-amber-600 transition hover:bg-amber-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.01"/></svg>
                                </a>
                                <button type="button" data-detach-target="{{ $aset->id_aset }}" data-detach-name="{{ $aset->barang?->nama_barang ?? $aset->nomor_kartu_barang }}" title="Keluarkan" class="flex h-7 w-7 items-center justify-center rounded-xl bg-red-50 text-red-600 transition hover:bg-red-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                                </div>
                            </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isAdminAset ? 8 : 5 }}" class="px-6 py-12 text-center text-sm text-slate-400">Belum ada aset di ruangan ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    @if ($isAdminAset)
    {{-- Modal Keluarkan Aset --}}
    <div id="detach-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
        <div class="fixed inset-0" data-detach-close></div>
        <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
            <div class="flex items-start gap-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-red-100">
                    <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div class="min-w-0">
                    <h3 class="text-lg font-semibold text-slate-900">Keluarkan Aset</h3>
                    <p class="mt-1 text-sm text-slate-500">Keluarkan <span id="detach-name" class="font-medium text-slate-700"></span> dari ruangan ini?</p>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" data-detach-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                <form id="detach-form" method="POST" action="">
                    @csrf
                    @method('DELETE')
<button type="submit" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700">Keluarkan</button>
                </form>
            </div>
    </div>
    </div>

    @push('scripts')
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        const detachModal = document.getElementById('detach-modal');

        const detachForm = document.getElementById('detach-form');
        const detachUrl = @json(route('aset-ruangan.aset.detach', ['ruangan' => $ruangan->id_ruangan, 'aset' => '__ID__']));
        document.querySelectorAll('[data-detach-target]').forEach(btn => {
            btn.addEventListener('click', () => {
                detachForm.action = detachUrl.replace('__ID__', btn.dataset.detachTarget);
                document.getElementById('detach-name').textContent = btn.dataset.detachName;
                detachModal.classList.remove('hidden');
                detachModal.classList.add('flex');
            });
        });

        function bindClose(modalEl, closeSelector) {
            modalEl.querySelectorAll(closeSelector).forEach(el => el.addEventListener('click', () => {
                modalEl.classList.add('hidden');
                modalEl.classList.remove('flex');
                document.body.classList.remove('overflow-hidden');
            }));
        }
        bindClose(detachModal, '[data-detach-close]');
    </script>
    @endpush
    @endif
@endsection
