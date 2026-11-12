<x-card :padding="false">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-6 py-4">
        <p class="text-sm font-semibold text-slate-700">Barang Melekat pada Ruangan</p>
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
                    @if ($isAdminAset || $bolehKelolaRuangan)
                    <th scope="col" class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @forelse ($asetMelekat as $i => $aset)
                    @php
                        $kondisiColor = match ($aset->kondisi) {
                            'Baik' => 'bg-disbun-100 text-disbun-800',
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
                        @if ($isAdminAset || $bolehKelolaRuangan)
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-right">
                            @if (! ($aset->boleh_mutasi_ruangan ?? false))
                                {{-- Penempatan belum difinalisasi (status 0 atau NULL): mutasi dilarang --}}
                                <span class="mr-1 inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700" title="Penempatan belum difinalisasi. Finalisasi penempatan di menu Aset Barang sebelum mutasi.">Belum Difinalisasi</span>
                            @endif
                            <div class="inline-flex items-center gap-1 rounded-2xl border border-slate-200/70 bg-slate-50/60 p-1 shadow-2xs">
                                {{-- Tombol Cetak Label: Dapat diakses Admin Aset & Admin UPT --}}
                                <a href="{{ route('aset-barang.cetak.label.single', $aset->id_aset) }}" target="_blank" title="Cetak Label" class="flex h-7 w-7 items-center justify-center rounded-xl bg-amber-50 text-amber-600 transition hover:bg-amber-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.01"/></svg>
                                </a>

                                {{-- Tombol Mutasi Ruangan: Muncul jika penempatan sudah difinalisasi --}}
                                @if ($aset->boleh_mutasi_ruangan ?? false)
                                <button type="button" data-mutasi-ruangan="{{ $aset->id_aset }}" data-aset-nama="{{ $aset->barang?->nama_barang ?? '-' }}" data-aset-kib="{{ $aset->nomor_kartu_barang ?? '-' }}" title="Mutasi Ruangan" class="flex h-7 w-7 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 transition hover:bg-indigo-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>
                                </button>
                                @endif

                                {{-- Tombol Keluarkan Aset: Dapat diakses Admin Aset & Admin UPT --}}
                                <button type="button" data-detach-target="{{ $aset->id_aset }}" data-detach-name="{{ $aset->barang?->nama_barang ?? $aset->nomor_kartu_barang }}" title="Keluarkan" class="flex h-7 w-7 items-center justify-center rounded-xl bg-red-50 text-red-600 transition hover:bg-red-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ ($isAdminAset ? 7 : ($bolehKelolaRuangan ? 6 : 5)) }}" class="px-6 py-12 text-center text-sm text-slate-400">Belum ada barang melekat pada ruangan ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>

