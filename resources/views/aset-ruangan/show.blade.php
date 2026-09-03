@extends('layouts.app')

@section('title', 'Detail Aset Ruangan')

@section('content')
    <x-page-header title="{{ $ruangan->nama_ruangan }}" subtitle="Aset yang ditempatkan di ruangan ini">
        <x-slot name="actions">
            <x-button href="{{ route('aset-ruangan.index') }}" variant="secondary" icon="M11 17l-5-5m0 0l5-5m-5 5h12">
                Kembali
            </x-button>
        </x-slot>
    </x-page-header>

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
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                <button type="button" data-detach-target="{{ $aset->id_aset }}" data-detach-name="{{ $aset->barang?->nama_barang ?? $aset->nomor_kartu_barang }}" class="inline-flex items-center gap-2 rounded-xl bg-red-50 px-2.5 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-100">
                                    Keluarkan
                                </button>
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
    <div id="detach-modal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" data-detach-close></div>
        <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
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
