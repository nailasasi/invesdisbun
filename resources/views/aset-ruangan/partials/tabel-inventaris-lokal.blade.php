{{-- Inventaris Lokal Ruangan: barang non-aset (dispenser, kulkas, dll).
     Terpisah total dari aset resmi; tidak memengaruhi KIR/label/neraca. --}}
<x-card :padding="false" class="mt-5">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-6 py-4">
        <div>
            <p class="text-sm font-semibold text-slate-700">Inventaris Lokal Ruangan</p>
            <p class="mt-0.5 text-xs text-slate-400">Barang non-aset yang berada di ruangan ini.</p>
        </div>
        @if ($bolehKelolaRuangan)
        <button type="button" id="btn-inv-add"
                class="inline-flex items-center gap-1.5 rounded-xl bg-disbun-700 px-3.5 py-2 text-xs font-bold text-white shadow-2xs transition hover:bg-disbun-800">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            <span>Tambah Inventaris</span>
        </button>
        @endif
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">#</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Kode</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nama Barang</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Merk</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Kondisi</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Keterangan</th>
                    @if ($bolehKelolaRuangan)
                    <th scope="col" class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @forelse ($inventarisLokal as $i => $item)
                    @php
                        $invKondisiColor = match ($item->kondisi) {
                            'Baik' => 'bg-disbun-100 text-disbun-800',
                            'Rusak Ringan' => 'bg-amber-100 text-amber-700',
                            'Rusak Berat' => 'bg-red-100 text-red-700',
                            default => 'bg-slate-100 text-slate-600',
                        };
                    @endphp
                    <tr class="transition hover:bg-slate-50">
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $i + 1 }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm font-mono text-slate-600">{{ $item->kode_inventaris }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{{ $item->nama_barang }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $item->merk ?: '—' }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $invKondisiColor }}">{{ $item->kondisi ?? '-' }}</span>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-600">{{ $item->keterangan ?? '-' }}</td>
                        @if ($bolehKelolaRuangan)
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-right">
                            <div class="inline-flex items-center gap-1 rounded-2xl border border-slate-200/70 bg-slate-50/60 p-1 shadow-2xs">
                                <a href="{{ route('aset-ruangan.inventaris.label', ['ruangan' => $ruangan->id_ruangan, 'inventaris' => $item->id_inventaris]) }}" target="_blank" rel="noopener" title="Cetak Label Inventaris"
                                   class="flex h-7 w-7 items-center justify-center rounded-xl bg-disbun-50 text-disbun-700 transition hover:bg-disbun-100">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.01"/></svg>
                                </a>
                                <button type="button" title="Ubah"
                                        data-inv-edit="{{ $item->id_inventaris }}"
                                        data-inv-kode="{{ $item->kode_inventaris }}"
                                        data-inv-nama="{{ $item->nama_barang }}"
                                        data-inv-merk="{{ $item->merk }}"
                                        data-inv-kondisi="{{ $item->kondisi }}"
                                        data-inv-keterangan="{{ $item->keterangan }}"
                                        class="flex h-7 w-7 items-center justify-center rounded-xl bg-sky-50 text-sky-600 transition hover:bg-sky-100">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                                </button>
                                <button type="button" title="Mutasi Ruangan"
                                        data-inv-move="{{ $item->id_inventaris }}"
                                        data-inv-nama="{{ $item->nama_barang }}"
                                        data-inv-kode="{{ $item->kode_inventaris }}"
                                        data-inv-keterangan="{{ $item->keterangan }}"
                                        class="flex h-7 w-7 items-center justify-center rounded-xl bg-amber-50 text-amber-600 transition hover:bg-amber-100">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>
                                </button>
                                <button type="button" title="Usulkan Penghapusan"
                                        data-inv-del="{{ $item->id_inventaris }}"
                                        data-inv-nama="{{ $item->nama_barang }}"
                                        class="flex h-7 w-7 items-center justify-center rounded-xl bg-red-50 text-red-600 transition hover:bg-red-100">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $bolehKelolaRuangan ? 7 : 6 }}" class="px-6 py-12 text-center text-sm text-slate-400">Belum ada inventaris lokal pada ruangan ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>

