{{-- Tab 2: Pemanfaatan & Retribusi --}}
<div data-tab-panel id="tab-retribusi" class="mt-4 hidden">
    <x-card :padding="false">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-base font-bold text-slate-900">Pemanfaatan &amp; Retribusi</h3>
                <p class="text-xs text-slate-400">Perhitungan pemanfaatan aset tanah (kebun) oleh pihak ketiga</p>
            </div>
            @if ($isAdmin)
                <a href="{{ route('tanah.retribusi.create', $tanah) }}"
                   class="inline-flex items-center gap-1.5 rounded-xl bg-disbun-700 px-3.5 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-disbun-800">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    <span>Tambah Retribusi</span>
                </a>
            @endif
        </div>

        <div class="p-6">
            {{-- Metrik nominal --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border border-slate-200/80 bg-slate-50/60 p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Biaya Kebun</p>
                    <p class="mt-1 text-lg font-extrabold text-slate-900">{{ $rupiah($totalBiayaKebun) }}</p>
                    <p class="mt-0.5 text-[11px] text-slate-400">Akumulasi {{ $retribusiRows->count() }} tahun</p>
                </div>
                <div class="rounded-2xl border border-emerald-200/60 bg-emerald-50/60 p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-emerald-600">Realisasi PAD</p>
                    <p class="mt-1 text-lg font-extrabold text-emerald-700">{{ $rupiah($totalPad) }}</p>
                    <p class="mt-0.5 text-[11px] text-emerald-500/70">Akumulasi {{ $retribusiRows->count() }} tahun</p>
                </div>
                <div class="rounded-2xl border border-slate-200/80 bg-slate-50/60 p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Tarif Retribusi</p>
                    <p class="mt-1 text-lg font-extrabold text-slate-900">{{ $rupiah($totalTarif) }}</p>
                    <p class="mt-0.5 text-[11px] text-slate-400">Akumulasi {{ $retribusiRows->count() }} tahun</p>
                </div>
                <div class="rounded-2xl border border-slate-200/80 bg-slate-50/60 p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Total Potensi Sewa</p>
                    <p class="mt-1 text-lg font-extrabold text-slate-900">{{ $rupiah($totalSewa) }}</p>
                    <p class="mt-0.5 text-[11px] text-slate-400">Akumulasi {{ $retribusiRows->count() }} tahun</p>
                </div>
            </div>

            {{-- Detail pelengkap --}}
            <div class="mt-6 grid grid-cols-1 gap-x-6 gap-y-4 border-t border-slate-100 pt-5 sm:grid-cols-2">
                <div class="space-y-1">
                    <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Satuan Tarif</dt>
                    <dd class="text-sm font-medium text-slate-900">{{ $satuanTarif }}</dd>
                </div>
                <div class="space-y-1">
                    <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Sumber Air</dt>
                    <dd class="text-sm text-slate-900">{{ $tanah->penggunaan_air ?: '-' }}</dd>
                </div>
                <div class="space-y-1 sm:col-span-2">
                    <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Status Retribusi</dt>
                    <dd class="text-sm">
                        @if ($latestRetribusi)
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700">{{ $latestRetribusi->status_pemanfaatan ?: 'Belum ditetapkan' }}</span>
                            <span class="ml-2 text-xs text-slate-400">Tahun {{ $latestRetribusi->tahun }}</span>
                        @else
                            <span class="text-slate-400">Belum ada data pemanfaatan.</span>
                        @endif
                    </dd>
                </div>
                <div class="space-y-1 sm:col-span-2">
                    <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Keterangan</dt>
                    <dd class="text-sm text-slate-900">{{ $latestRetribusi?->keterangan ?: '-' }}</dd>
                </div>
            </div>
        </div>

        {{-- Rincian per tahun (semua tahun) --}}
        @if ($retribusiRows->count())
            <div class="overflow-x-auto border-t border-slate-100">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Tahun</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Biaya Kebun</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Realisasi PAD</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Tarif</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Total Sewa</th>
                            @if ($isAdmin)
                                <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($retribusiRows as $r)
                            <tr class="transition hover:bg-slate-50">
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{{ $r->tahun }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $r->status_pemanfaatan ?: '-' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-700">{{ $rupiah((float) $r->biaya_pengurusan) }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-700">{{ $rupiah((float) $r->PAD) }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-700">{{ $rupiah((float) $r->tarif_retribusi) }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-700">{{ $rupiah((float) $r->total_tarif_sewa) }}</td>
                                @if ($isAdmin)
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                        <a href="{{ route('tanah.retribusi.edit', $r) }}" class="inline-flex items-center rounded-lg bg-amber-50 px-2.5 py-1.5 text-xs font-medium text-amber-700 transition hover:bg-amber-100">Edit</a>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-6">
                <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 p-6 text-center">
                    <p class="text-sm font-medium text-slate-700">Belum ada data pemanfaatan &amp; retribusi.</p>
                    <p class="mt-1 text-sm text-slate-500">Data retribusi tahunan aset akan tampil di sini.</p>
                </div>
            </div>
        @endif
    </x-card>
</div>

