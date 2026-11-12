<div>
    <div class="mb-3.5 flex items-center justify-between">
        <div>
            <h2 class="text-base font-bold text-slate-800 sm:text-lg">Informasi Pribadi Admin Bidang</h2>
            <p class="text-xs text-slate-500">Identitas aparatur pemegang akun dan aset dinas yang dipertanggungjawabkan secara personal.</p>
        </div>
        <span class="rounded-full bg-violet-50 px-2.5 py-1 text-[11px] font-semibold text-violet-700">
            Personal
        </span>
    </div>

    <div class="grid grid-cols-1 items-stretch gap-4 lg:grid-cols-3">
        {{-- Identitas Admin Bidang (Bebas dari SPPBI) --}}
        <div class="flex flex-col justify-between rounded-3xl border border-disbun-card-border bg-white p-5 shadow-bento transition hover:shadow-bento-hover sm:p-6">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Profil Aparatur</p>
                <div class="mt-4 flex items-center gap-3.5">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-disbun-700 to-disbun-900 text-base font-bold text-white shadow-sm">
                        {{ strtoupper(substr($diri->nama_pegawai ?? 'A', 0, 2)) }}
                    </div>
                    <div class="min-w-0">
                        <h4 class="truncate text-base font-bold text-slate-800" title="{{ $diri->nama_pegawai }}">{{ $diri->nama_pegawai }}</h4>
                        <p class="truncate text-xs font-medium text-slate-500">NIP. {{ $diri->nip ?? '-' }}</p>
                    </div>
                </div>

                <div class="mt-5 space-y-2.5 border-t border-slate-100 pt-4 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Jabatan</span>
                        <span class="font-semibold text-slate-700 text-right">{{ $diri->jabatan ?? '-' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Unit Organisasi</span>
                        <span class="font-semibold text-slate-700 text-right">{{ $skpd->nama_skpd }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Hak Akses</span>
                        <span class="rounded-md bg-emerald-50 px-2 py-0.5 font-bold text-emerald-700">Admin Bidang</span>
                    </div>
                </div>
            </div>

            <div class="mt-4 rounded-xl bg-slate-50 p-3 text-[11px] text-slate-500">
                Akun ini bertindak sebagai administrator pengelolaan aset dan perencanaan kebutuhan barang unit Bidang.
            </div>
        </div>

        {{-- Aset yang Dipegang Pribadi --}}
        <div class="flex flex-col rounded-3xl border border-disbun-card-border bg-white p-5 shadow-bento transition hover:shadow-bento-hover sm:p-6 lg:col-span-2">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-800 sm:text-base">Aset yang Sedang Dipegang Pribadi</h3>
                    <p class="text-xs text-slate-400">Inventaris kedinasan yang dipertanggungjawabkan secara langsung kepada Anda</p>
                </div>
                <span class="rounded-lg bg-disbun-50 px-2.5 py-1 text-xs font-bold text-disbun-700">
                    {{ $asetPribadi->count() }} Aset
                </span>
            </div>

            <div class="flex-1 space-y-2">
                @forelse ($asetPribadi as $pegang)
                    <div class="flex items-center justify-between gap-3 rounded-2xl bg-slate-50/70 px-4 py-3 transition hover:bg-slate-100/70">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-800">
                                {{ $pegang->aset?->kendaraan?->namaTampil() ?? $pegang->aset?->barang?->nama_barang ?? $pegang->aset?->merk ?? 'Aset Inventaris' }}
                            </p>
                            <p class="truncate text-[11px] text-slate-400">
                                KIB: {{ $pegang->aset?->nomor_kartu_barang ?? '-' }}
                                @if($pegang->aset?->kendaraan?->platAktif)
                                    · <span class="font-bold text-slate-600">{{ $pegang->aset->kendaraan->platAktif->nomor_polisi }}</span>
                                @endif
                            </p>
                        </div>
                        <span @class([
                            'shrink-0 rounded-lg px-2.5 py-1 text-[11px] font-bold',
                            'bg-disbun-50 text-disbun-700' => $pegang->aset?->kondisi === 'Baik',
                            'bg-amber-50 text-amber-700' => $pegang->aset?->kondisi === 'Rusak Ringan',
                            'bg-rose-50 text-rose-700' => $pegang->aset?->kondisi === 'Rusak Berat',
                            'bg-slate-100 text-slate-600' => ! in_array($pegang->aset?->kondisi, ['Baik', 'Rusak Ringan', 'Rusak Berat']),
                        ])>
                            {{ $pegang->aset?->kondisi ?? '-' }}
                        </span>
                    </div>
                @empty
                    <div class="flex h-36 flex-col items-center justify-center rounded-2xl border border-dashed border-disbun-card-border text-center">
                        <svg class="h-8 w-8 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <p class="mt-2 text-xs font-medium text-slate-400">Anda tidak memegang aset dinas secara perorangan</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
