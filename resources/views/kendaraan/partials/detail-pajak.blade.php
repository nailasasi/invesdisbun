{{-- Pajak Kendaraan (Tabel riwayat pajak, filter tahun/jenis, kelola pajak, pratinjau bukti) --}}
<x-card id="pajak-card" :padding="false" class="min-w-0 lg:col-span-2">
    <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
        <h3 class="text-base font-semibold text-slate-900">Pajak Kendaraan</h3>
        @if ($isAdmin)
            <button type="button" data-pajak-modal class="inline-flex items-center gap-2 rounded-xl bg-disbun-700 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-disbun-800">
                Kelola Pajak Kendaraan
            </button>
        @endif
    </div>
    <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 px-6 py-3">
        <label class="flex items-center gap-2 text-xs font-semibold text-slate-600">
            <span>Filter Tahun</span>
            <select id="pajak-filter-tahun" name="pajak_tahun" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-normal text-slate-700 shadow-sm focus:outline-none focus:border-disbun-500">
                <option value="">Semua Tahun</option>
                @foreach ($daftarTahunPajak as $tahun)
                    <option value="{{ $tahun }}" {{ (string) request('pajak_tahun') === (string) $tahun ? 'selected' : '' }}>{{ $tahun }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex items-center gap-2 text-xs font-semibold text-slate-600">
            <span>Filter Jenis Pajak</span>
            <select id="pajak-filter-jenis" name="pajak_jenis" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-normal text-slate-700 shadow-sm focus:outline-none focus:border-disbun-500">
                <option value="">Semua Jenis</option>
                <option value="tahunan" {{ request('pajak_jenis') === 'tahunan' ? 'selected' : '' }}>Tahunan</option>
                <option value="5-tahunan" {{ request('pajak_jenis') === '5-tahunan' ? 'selected' : '' }}>5 Tahunan</option>
            </select>
        </label>
        @if (request()->filled('pajak_tahun') || request()->filled('pajak_jenis'))
            <a href="{{ route('kendaraan.show', $kendaraan) }}#pajak-card" class="text-xs font-medium text-disbun-700 hover:underline">
                Reset Filter
            </a>
        @endif
    </div>
    <div class="overflow-x-auto">
        <table id="pajak-kendaraan" class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jenis</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jatuh Tempo</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jenis Pajak</th>
                    @if ($isAdmin)
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nominal</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Total</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Bukti</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @forelse ($riwayatPajak as $pajak)
                    <tr data-tahun="{{ $pajak->tahun }}" data-jenis="{{ $pajak->pajak_5_tahunan ? '5-tahunan' : 'tahunan' }}" class="transition hover:bg-slate-50">
                        <td class="px-6 py-4 text-sm text-slate-700">
                            <div class="flex items-center gap-1.5">
                                <span>{{ $pajak->jenis_pajak }}</span>
                                @if ($pajak->status === 'Aktif')
                                    <span class="inline-flex items-center rounded-full bg-disbun-100 px-2 py-0.5 text-[10px] font-medium text-disbun-800">Aktif</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600">Tidak Aktif</span>
                                @endif
                            </div>
                        </td>
                        {{-- Tanggal Jatuh Tempo PKB & Berakhir STNK tanpa pewarnaan status (teks biasa/standar) --}}
                        <td class="px-6 py-4 text-sm">
                            <div>
                                <span class="text-xs text-slate-400 block">PKB:</span>
                                <span class="text-slate-700 font-medium">{{ $pajak->tanggal_berakhir?->format('d M Y') ?? '-' }}</span>
                            </div>
                            @if ($pajak->pajak_5_tahunan && $pajak->tanggal_berakhir_stnk)
                                <div class="mt-1">
                                    <span class="text-xs text-slate-400 block">STNK:</span>
                                    <span class="text-slate-700 font-medium">{{ $pajak->tanggal_berakhir_stnk->format('d M Y') }}</span>
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm">
                            @if ($pajak->pajak_5_tahunan)
                                <span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-700">5 Tahunan</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-disbun-50 px-2.5 py-0.5 text-xs font-medium text-disbun-700">Tahunan</span>
                            @endif
                        </td>
                        @if ($isAdmin)
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $pajak->nominal ? 'Rp ' . number_format((float) $pajak->nominal, 0, ',', '.') : '-' }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $pajak->total_pajak ? 'Rp ' . number_format((float) $pajak->total_pajak, 0, ',', '.') : '-' }}</td>
                            <td class="px-6 py-4 text-sm">
                                @if ($pajak->bukti_pajak_path)
                                    <button type="button" data-lihat-bukti="{{ $pajak->id_pajak }}" data-bukti-nama="{{ $pajak->bukti_pajak_nama }}" class="inline-flex items-center rounded-lg bg-disbun-50 px-2.5 py-1.5 text-xs font-medium text-disbun-700 transition hover:bg-disbun-100">Lihat Bukti</button>
                                @else
                                    <span class="text-xs text-slate-400">Belum ada</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right text-sm">
                                @if ($pajak->status === 'Aktif')
                                    <button type="button"
                                        data-edit-pajak="{{ $pajak->id_pajak }}"
                                        data-jenis="{{ $pajak->jenis_pajak }}"
                                        data-tahun="{{ $pajak->tahun }}"
                                        data-is5tahunan="{{ $pajak->pajak_5_tahunan ? '1' : '0' }}"
                                        data-bayar="{{ $pajak->tanggal_bayar?->format('Y-m-d') }}"
                                        data-berakhir="{{ $pajak->tanggal_berakhir?->format('Y-m-d') }}"
                                        data-berakhir-stnk="{{ $pajak->tanggal_berakhir_stnk?->format('Y-m-d') }}"
                                        data-plat="{{ $kendaraan->platAktif?->nomor_plat }}"
                                        data-nominal="{{ $pajak->nominal }}"
                                        data-total="{{ $pajak->total_pajak }}"
                                        data-bukti="{{ $pajak->bukti_pajak_path ? '1' : '0' }}"
                                        data-bukti-nama="{{ $pajak->bukti_pajak_nama }}"
                                        class="inline-flex items-center rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-200">Edit</button>
                                @else
                                    <span class="text-xs text-slate-400" title="Transaksi Tidak Aktif (Riwayat)">-</span>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $isAdmin ? 7 : 3 }}" class="px-6 py-10 text-center text-sm text-slate-400">Belum ada data pajak.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination Kelola Pajak: Maksimal 5 Transaksi per Halaman --}}
    @if ($riwayatPajak->hasPages())
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 border-t border-slate-100 px-6 py-3.5 bg-slate-50/50">
            <div class="text-xs text-slate-500">
                Menampilkan <span class="font-medium text-slate-700">{{ $riwayatPajak->firstItem() ?? 0 }}</span> - <span class="font-medium text-slate-700">{{ $riwayatPajak->lastItem() ?? 0 }}</span> dari <span class="font-medium text-slate-700">{{ $riwayatPajak->total() }}</span> transaksi
            </div>
            <nav role="navigation" aria-label="Navigasi Halaman Pajak" class="flex items-center gap-1.5">
                {{-- Tombol Previous --}}
                @if ($riwayatPajak->onFirstPage())
                    <span class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-400 cursor-not-allowed select-none" aria-disabled="true">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                        <span>Previous</span>
                    </span>
                @else
                    <a href="{{ $riwayatPajak->previousPageUrl() }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 shadow-2xs transition hover:bg-slate-50 hover:text-slate-900" rel="prev">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                        <span>Previous</span>
                    </a>
                @endif

                {{-- Nomor Halaman (1, 2, 3, ...) --}}
                <div class="flex items-center gap-1">
                    @foreach ($riwayatPajak->getUrlRange(1, $riwayatPajak->lastPage()) as $page => $url)
                        @if ($page == $riwayatPajak->currentPage())
                            <span aria-current="page" class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-disbun-700 text-xs font-semibold text-white shadow-2xs select-none">
                                {{ $page }}
                            </span>
                        @else
                            <a href="{{ $url }}" class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-slate-300 bg-white text-xs font-medium text-slate-700 shadow-2xs transition hover:bg-slate-50 hover:text-slate-900">
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach
                </div>

                {{-- Tombol Next --}}
                @if ($riwayatPajak->hasMorePages())
                    <a href="{{ $riwayatPajak->nextPageUrl() }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 shadow-2xs transition hover:bg-slate-50 hover:text-slate-900" rel="next">
                        <span>Next</span>
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                @else
                    <span class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-400 cursor-not-allowed select-none" aria-disabled="true">
                        <span>Next</span>
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </span>
                @endif
            </nav>
        </div>
    @endif
</x-card>

