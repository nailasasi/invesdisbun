{{-- Pajak Kendaraan (Tabel riwayat pajak, filter tahun/jenis, kelola pajak, pratinjau bukti) --}}
<x-card :padding="false" class="min-w-0 lg:col-span-2">
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
            <select id="pajak-filter-tahun" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-normal text-slate-700 shadow-sm focus:outline-none focus:border-emerald-500">
                <option value="">Semua Tahun</option>
                @foreach ($kendaraan->pajak->pluck('tahun')->filter()->map(fn ($t) => (int) $t)->unique()->sortDesc() as $tahun)
                    <option value="{{ $tahun }}">{{ $tahun }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex items-center gap-2 text-xs font-semibold text-slate-600">
            <span>Filter Jenis Pajak</span>
            <select id="pajak-filter-jenis" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-normal text-slate-700 shadow-sm focus:outline-none focus:border-emerald-500">
                <option value="">Semua Jenis</option>
                <option value="tahunan">Tahunan</option>
                <option value="5-tahunan">5 Tahunan</option>
            </select>
        </label>
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
                @forelse ($kendaraan->pajak as $pajak)
                    @php
                        $expired = $pajak->tanggal_berakhir && $pajak->tanggal_berakhir->isPast();
                    @endphp
                    <tr data-tahun="{{ $pajak->tahun }}" data-jenis="{{ $pajak->pajak_5_tahunan ? '5-tahunan' : 'tahunan' }}" class="transition hover:bg-slate-50">
                        <td class="px-6 py-4 text-sm text-slate-700">{{ $pajak->jenis_pajak }}
                            @if ($pajak->status === 'Aktif')
                                <span class="ml-1 inline-flex items-center rounded-full bg-disbun-100 px-2 py-0.5 text-[10px] font-medium text-disbun-800">Aktif</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm">
                            @if ($expired)
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-red-100 text-red-700">{{ $pajak->tanggal_berakhir->format('d M Y') }}</span>
                            @else
                                <span class="text-slate-600">{{ $pajak->tanggal_berakhir?->format('d M Y') ?? '-' }}</span>
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
                                <div class="inline-flex items-center gap-1.5">
                                    <button type="button"
                                        data-edit-pajak="{{ $pajak->id_pajak }}"
                                        data-jenis="{{ $pajak->jenis_pajak }}"
                                        data-tahun="{{ $pajak->tahun }}"
                                        data-is5tahunan="{{ $pajak->pajak_5_tahunan ? '1' : '0' }}"
                                        data-bayar="{{ $pajak->tanggal_bayar?->format('Y-m-d') }}"
                                        data-berakhir="{{ $pajak->tanggal_berakhir?->format('Y-m-d') }}"
                                        data-nominal="{{ $pajak->nominal }}"
                                        data-total="{{ $pajak->total_pajak }}"
                                        data-bukti="{{ $pajak->bukti_pajak_path ? '1' : '0' }}"
                                        data-bukti-nama="{{ $pajak->bukti_pajak_nama }}"
                                        class="inline-flex items-center rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-200">Edit</button>
                                    <button type="button" data-delete-pajak="{{ $pajak->id_pajak }}" data-pajak-name="{{ $pajak->jenis_pajak }}" class="inline-flex items-center rounded-lg bg-red-50 px-2.5 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-100">Hapus</button>
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $isAdmin ? 7 : 3 }}" class="px-6 py-10 text-center text-sm text-slate-400">Belum ada data pajak.</td>
                    </tr>
                @endforelse
                <tr id="pajak-filter-empty" class="hidden">
                    <td colspan="{{ $isAdmin ? 7 : 3 }}" class="px-6 py-10 text-center text-sm text-slate-400">Tidak ada data pajak yang cocok dengan filter.</td>
                </tr>
            </tbody>
        </table>
    </div>
</x-card>

