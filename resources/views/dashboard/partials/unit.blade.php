@if (! $unit)
    <div class="flex min-h-[280px] flex-col items-center justify-center rounded-3xl border border-dashed border-disbun-card-border bg-white p-8 text-center shadow-bento">
        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-100 text-disbun-amber">
            <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        </span>
        <h3 class="mt-4 text-base font-semibold text-slate-800">Unit kerja belum dipetakan</h3>
        <p class="mt-1 max-w-sm text-sm text-slate-500">
            Akun Anda belum terhubung ke data pegawai/SKPD. Hubungi Admin Aset untuk melengkapi profil unit kerja.
        </p>
    </div>
@else
    {{-- HERO UNIT --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#0c2e1b] via-[#133e25] to-[#1c5531] p-6 text-white shadow-bento sm:p-8">
        <div class="absolute -right-14 -top-14 h-56 w-56 rounded-full bg-disbun-amber-glow/20 blur-3xl"></div>
        <div class="absolute -bottom-16 right-24 h-48 w-48 rounded-full bg-emerald-400/15 blur-3xl"></div>
        <div class="relative flex flex-col justify-between gap-6 sm:flex-row sm:items-center">
            <div>
                <p class="inline-flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-widest text-disbun-100/80">
                    Dashboard Unit Kerja
                </p>
                <h1 class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $unit->nama_skpd }}</h1>
                <p class="mt-2 max-w-xl text-sm text-white/70">
                    Ringkasan aset yang dipegang pegawai di lingkungan unit kerja ini.
                </p>
            </div>
            <a href="{{ route('aset-barang.index') }}"
               class="inline-flex w-fit items-center gap-1.5 rounded-xl bg-gradient-to-r from-disbun-amber to-disbun-amber-glow px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-black/10 transition hover:brightness-105">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                Lihat Data Aset
            </a>
        </div>
    </div>

    {{-- METRIC BENTO TILES --}}
    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="animate-fade-in-up rounded-3xl border border-disbun-card-border bg-white p-5 shadow-bento transition hover:shadow-bento-hover" style="animation-delay: 40ms">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Aset Unit</p>
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-disbun-600 to-disbun-800 text-white shadow-sm">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
            </div>
            <p class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">{{ number_format($totalAsetUnit, 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-400">aset dipegang pegawai unit</p>
        </div>

        <div class="animate-fade-in-up rounded-3xl border border-disbun-card-border bg-white p-5 shadow-bento transition hover:shadow-bento-hover" style="animation-delay: 80ms">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Nilai Perolehan</p>
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 text-white shadow-sm">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <p class="mt-3 text-2xl font-extrabold tracking-tight text-slate-900">Rp {{ number_format($nilaiAsetUnit, 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-400">total nilai perolehan unit</p>
        </div>

        <div class="animate-fade-in-up rounded-3xl border border-disbun-card-border bg-white p-5 shadow-bento transition hover:shadow-bento-hover" style="animation-delay: 120ms">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Kendaraan</p>
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-sky-500 to-blue-600 text-white shadow-sm">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 18H3V5h11v13H9m-4 0a2 2 0 104 0m-4 0a2 2 0 114 0m4-8h5l3 3v5h-8m0 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
                </div>
            </div>
            <p class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">
                <span class="text-disbun-600">{{ number_format($kendaraanUnitTersedia, 0, ',', '.') }}</span>
                <span class="text-slate-300">/</span>
                <span class="text-sky-600">{{ number_format($kendaraanUnitDipakai, 0, ',', '.') }}</span>
            </p>
            <p class="mt-1 text-xs text-slate-400">tersedia / dipakai</p>
        </div>

        <div class="animate-fade-in-up rounded-3xl border border-disbun-card-border bg-white p-5 shadow-bento transition hover:shadow-bento-hover" style="animation-delay: 160ms">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Pegawai Pemegang</p>
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 to-purple-600 text-white shadow-sm">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
            </div>
            <p class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">{{ number_format($pemegangUnit->pluck('id_pegawai')->unique()->count(), 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-400">pegawai memegang aset</p>
        </div>
    </div>

    {{-- Grid utama unit --}}
    <div class="mt-6 grid grid-cols-1 items-stretch gap-4 lg:grid-cols-3">
        <div class="flex flex-col rounded-3xl border border-disbun-card-border bg-white p-6 shadow-bento transition hover:shadow-bento-hover lg:col-span-2">
            <h3 class="mb-4 text-base font-bold text-slate-800">Kondisi Aset Unit</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border border-disbun-card-border bg-disbun-50/60 p-4">
                    <p class="text-xs font-medium text-disbun-700">Kondisi Baik</p>
                    <p class="mt-2 text-2xl font-extrabold text-disbun-600">{{ number_format($asetBaikUnit, 0, ',', '.') }}</p>
                </div>
                <div class="rounded-2xl border border-amber-100 bg-amber-50/60 p-4">
                    <p class="text-xs font-medium text-amber-700">Rusak Ringan</p>
                    <p class="mt-2 text-2xl font-extrabold text-amber-600">{{ number_format($asetRusakRinganUnit, 0, ',', '.') }}</p>
                </div>
                <div class="rounded-2xl border border-rose-100 bg-rose-50/60 p-4">
                    <p class="text-xs font-medium text-rose-700">Rusak Berat</p>
                    <p class="mt-2 text-2xl font-extrabold text-rose-600">{{ number_format($asetRusakBeratUnit, 0, ',', '.') }}</p>
                </div>
            </div>

            <h3 class="mt-6 mb-3 text-base font-bold text-slate-800">Aset Dipegang Pegawai Unit</h3>
            <div class="flex-1 space-y-2">
                @forelse ($pemegangUnit->take(8) as $pemegang)
                    <div class="flex items-center justify-between gap-3 rounded-2xl bg-slate-50/70 px-4 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-800">{{ $pemegang->aset?->barang?->nama_barang ?? $pemegang->aset?->merk ?? 'Aset' }}</p>
                            <p class="truncate text-[11px] text-slate-400">{{ $pemegang->aset?->nomor_kartu_barang ?? '-' }} · {{ $pemegang->pegawai?->nama_pegawai ?? '-' }}</p>
                        </div>
                        <span @class([
                            'shrink-0 rounded-lg px-2.5 py-1 text-[11px] font-bold',
                            'bg-disbun-50 text-disbun-700' => $pemegang->aset?->kondisi === 'Baik',
                            'bg-amber-50 text-amber-700' => $pemegang->aset?->kondisi === 'Rusak Ringan',
                            'bg-rose-50 text-rose-700' => $pemegang->aset?->kondisi === 'Rusak Berat',
                            'bg-slate-50 text-slate-600' => ! in_array($pemegang->aset?->kondisi, ['Baik', 'Rusak Ringan', 'Rusak Berat']),
                        ])>
                            {{ $pemegang->aset?->kondisi ?? '-' }}
                        </span>
                    </div>
                @empty
                    <div class="flex h-24 flex-col items-center justify-center rounded-2xl border border-dashed border-disbun-card-border text-center">
                        <p class="text-sm font-medium text-slate-400">Belum ada aset tercatat untuk unit ini</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Status RKBMD unit --}}
        <div class="flex flex-col rounded-3xl border border-disbun-card-border bg-white p-6 shadow-bento transition hover:shadow-bento-hover">
            <h3 class="mb-4 text-base font-bold text-slate-800">Usulan RKBMD Unit</h3>
            <div class="grid grid-cols-2 gap-3">
                @php
                    $statusInfo = [
                        'Draft' => ['label' => 'Draft', 'badge' => 'bg-slate-100 text-slate-700'],
                        'Diajukan' => ['label' => 'Diajukan', 'badge' => 'bg-sky-100 text-sky-700'],
                        'Disetujui Pengurus Barang' => ['label' => 'Disetujui', 'badge' => 'bg-disbun-50 text-disbun-700'],
                        'Ditolak' => ['label' => 'Ditolak', 'badge' => 'bg-rose-100 text-rose-700'],
                    ];
                    $known = array_keys($statusInfo);
                @endphp
                @foreach ($known as $st)
                    <div class="rounded-2xl border border-disbun-card-border bg-slate-50/50 p-3">
                        <p class="text-[11px] font-medium {{ $statusInfo[$st]['badge'] }} w-fit rounded-full px-2 py-0.5">{{ $statusInfo[$st]['label'] }}</p>
                        <p class="mt-1.5 text-2xl font-extrabold text-slate-900">{{ number_format($rkbmdUnitStatuses[$st] ?? 0, 0, ',', '.') }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 flex-1 space-y-2">
                @forelse ($rkbmdUnitPending->take(4) as $usulan)
                    <a href="{{ route('rkbmd.index') }}" class="flex items-center justify-between gap-2 rounded-2xl bg-slate-50/70 px-3 py-2.5 transition hover:bg-sky-50">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-800">{{ $usulan->nama_barang }}</p>
                            <p class="truncate text-[11px] text-slate-400">{{ $usulan->jenis_usulan }} · {{ $usulan->tahun_anggaran }}</p>
                        </div>
                        <span class="shrink-0 rounded-lg bg-sky-100 px-2.5 py-1 text-[11px] font-bold text-sky-700">Pending</span>
                    </a>
                @empty
                    <div class="flex h-20 flex-col items-center justify-center rounded-2xl border border-dashed border-disbun-card-border text-center">
                        <svg class="h-6 w-6 text-disbun-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="mt-1.5 text-xs font-medium text-slate-400">Tidak ada usulan pending</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endif