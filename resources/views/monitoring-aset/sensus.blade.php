@extends('layouts.app')

@section('title', 'Sensus Aset - ' . ($aset->kendaraan?->namaTampil() ?? $aset->barang?->nama_barang ?? 'Aset'))

@section('page-title', 'Sensus Aset')

@section('content')
<div class="space-y-5 max-w-[850px] w-full mx-auto">

    {{-- Breadcrumb & Aksi Atas --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2 text-xs text-slate-500">
            <a href="{{ route('monitoring-aset.index', ['periode' => $periodeDipilih]) }}" class="hover:text-disbun-700 transition">Monitoring Aset</a>
            <span>/</span>
            <span class="font-semibold text-slate-700">Sensus Aset</span>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('monitoring-aset.sensus.riwayat', $aset->id_aset) }}"
               class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Lihat Riwayat Sensus
            </a>
            <a href="{{ route('monitoring-aset.index', ['periode' => $periodeDipilih]) }}"
               class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Kembali
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-xs text-emerald-800 font-medium">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs text-rose-800">
            <p class="font-bold mb-1">Periksa kembali data yang dimasukkan:</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- KONTENER MODAL / KARTU LANDSCAPE (MELEBAR KE SAMPING, 2 KOLOM 40% : 60%) --}}
    <div class="rounded-3xl border border-slate-200 bg-white shadow-xl overflow-hidden">
        {{-- Header Modal --}}
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 bg-white">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-900">
                        {{ $sensus ? 'Edit Hasil Sensus' : 'Tambah Hasil Sensus' }}
                    </h2>
                    <p class="text-xs text-slate-400">Sensus berkala fisik 3 tahunan aset dinas perkebunan.</p>
                </div>
            </div>
            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold {{ $sensus ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                {{ $sensus ? 'Sudah Disensus' : 'Belum Disensus' }}
            </span>
        </div>

        <form method="POST" action="{{ route('monitoring-aset.sensus.update', $aset->id_aset) }}" autocomplete="off" class="m-0">
            @csrf

            <div class="p-6">
                <div class="sensus-two-col-grid grid grid-cols-1 md:grid-cols-[0.35fr_0.65fr] gap-4 sm:gap-5 items-stretch">

                    {{-- Kolom Kiri: Informasi Aset (35%) --}}
                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50/90 p-4 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between border-b border-slate-200/70 pb-2.5 mb-3">
                                <div class="flex items-center gap-1.5">
                                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Informasi Aset</span>
                                </div>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold {{ $aset->is_kendaraan ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-200 text-slate-700' }}">
                                    {{ $aset->is_kendaraan ? 'Kendaraan' : 'Barang' }}
                                </span>
                            </div>

                            <div class="space-y-3.5">
                                <div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Nama Barang</span>
                                    <p class="text-xs sm:text-sm font-bold text-slate-900 leading-snug break-words mt-0.5">
                                        {{ $aset->kendaraan?->namaTampil() ?? $aset->barang?->nama_barang ?? '-' }}
                                    </p>
                                </div>

                                <div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Kode / Nomor KIB</span>
                                    <p class="font-mono text-[11px] sm:text-xs font-semibold text-slate-700 break-all mt-0.5">
                                        {{ $aset->nomor_kartu_barang ?? '-' }}
                                    </p>
                                </div>

                                <div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Pemegang Saat Ini</span>
                                    <p class="text-xs font-medium text-slate-700 break-words mt-0.5">
                                        {{ $aset->pemegangSaatIni?->pegawai?->nama_pegawai ?? '-' }}
                                    </p>
                                </div>

                                <div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Lokasi / Ruangan</span>
                                    <p class="text-xs font-medium text-slate-700 break-words mt-0.5">
                                        {{ $aset->penempatanAktif?->ruangan?->nama_ruangan ?? '-' }}
                                    </p>
                                </div>

                                <div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Tanggal Pengadaan</span>
                                    <p class="text-xs font-medium text-slate-700 mt-0.5">
                                        {{ $aset->tanggal_pengadaan ? $aset->tanggal_pengadaan->format('d M Y') : '-' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="pt-2.5 border-t border-slate-200/70 mt-3">
                            <p class="text-[10px] text-slate-400 leading-tight">Data aset diambil otomatis dari sistem inventaris.</p>
                        </div>
                    </div>

                    {{-- Kolom Kanan: Formulir Hasil Sensus (65%) --}}
                    <div class="flex flex-col justify-between space-y-3.5">
                        {{-- 1. Judul Formulir --}}
                        <div class="border-b border-slate-100 pb-2">
                            <h4 class="text-sm font-bold text-slate-900">Formulir Hasil Sensus</h4>
                            <p class="text-[11px] text-slate-400 mt-0.5">Catat kondisi fisik aset hasil sensus berkala 3 tahunan.</p>
                        </div>

                        {{-- 2 & 3. Periode Sensus & Tanggal Pelaksanaan Sensus --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="periode" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">
                                    Periode Sensus <span class="text-rose-500">*</span>
                                </label>
                                <select id="periode" name="periode" required
                                        onchange="window.location.href='{{ route('monitoring-aset.sensus.edit', $aset->id_aset) }}?periode=' + this.value"
                                        class="block w-full h-9 rounded-xl border border-slate-300 bg-white px-3 text-xs text-slate-900 shadow-sm focus:border-disbun-600 focus:outline-none focus:ring-1 focus:ring-disbun-400">
                                    @foreach ($daftarPeriode as $p)
                                        <option value="{{ $p }}" {{ $p === $periodeDipilih ? 'selected' : '' }}>
                                            Periode {{ $p }} {{ $p === $periodeAktif ? '(Aktif Saat Ini)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="tanggal_sensus" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">
                                    Tanggal Pelaksanaan Sensus <span class="text-rose-500">*</span>
                                </label>
                                <input type="date" id="tanggal_sensus" name="tanggal_sensus" required
                                       value="{{ old('tanggal_sensus', $sensus?->tanggal_sensus?->format('Y-m-d') ?? date('Y-m-d')) }}"
                                       class="block w-full h-9 rounded-xl border border-slate-300 bg-white px-3 text-xs text-slate-900 shadow-sm focus:border-disbun-600 focus:outline-none focus:ring-1 focus:ring-disbun-400">
                            </div>
                        </div>

                        {{-- 4. Pilihan Hasil Sensus (3 Kartu Kondisi Horizontal) --}}
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                                Pilihan Hasil Sensus <span class="text-rose-500">*</span>
                            </label>
                            @php $currentHasil = old('hasil_sensus', $sensus?->hasil_sensus); @endphp
                            <div class="grid grid-cols-3 gap-2 sm:gap-2.5">
                                {{-- Baik --}}
                                <label class="sensus-radio-card relative flex flex-col items-center justify-center cursor-pointer rounded-xl border p-2 text-center transition hover:bg-slate-50 select-none min-h-[66px] {{ $currentHasil === 'Baik' ? 'border-emerald-500 bg-emerald-50/70 ring-2 ring-emerald-500/20 text-emerald-900' : 'border-slate-200 bg-white' }}">
                                    <input type="radio" name="hasil_sensus" value="Baik" class="sr-only" required {{ $currentHasil === 'Baik' ? 'checked' : '' }}
                                           onchange="this.form.querySelectorAll('.sensus-radio-card').forEach(l => { l.classList.remove('border-emerald-500', 'bg-emerald-50/70', 'ring-2', 'ring-emerald-500/20', 'text-emerald-900', 'border-amber-500', 'bg-amber-50/70', 'ring-2', 'ring-amber-500/20', 'text-amber-900', 'border-rose-500', 'bg-rose-50/70', 'ring-2', 'ring-rose-500/20', 'text-rose-900'); l.classList.add('border-slate-200', 'bg-white'); }); this.closest('label').classList.remove('border-slate-200', 'bg-white'); this.closest('label').classList.add('border-emerald-500', 'bg-emerald-50/70', 'ring-2', 'ring-emerald-500/20', 'text-emerald-900');">
                                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 mb-1 shrink-0"></span>
                                    <span class="text-xs font-bold text-slate-800">Baik</span>
                                    <span class="text-[10px] text-slate-400 leading-tight mt-0.5">Berfungsi normal</span>
                                </label>

                                {{-- Rusak Ringan --}}
                                <label class="sensus-radio-card relative flex flex-col items-center justify-center cursor-pointer rounded-xl border p-2 text-center transition hover:bg-slate-50 select-none min-h-[66px] {{ $currentHasil === 'Rusak Ringan' ? 'border-amber-500 bg-amber-50/70 ring-2 ring-amber-500/20 text-amber-900' : 'border-slate-200 bg-white' }}">
                                    <input type="radio" name="hasil_sensus" value="Rusak Ringan" class="sr-only" required {{ $currentHasil === 'Rusak Ringan' ? 'checked' : '' }}
                                           onchange="this.form.querySelectorAll('.sensus-radio-card').forEach(l => { l.classList.remove('border-emerald-500', 'bg-emerald-50/70', 'ring-2', 'ring-emerald-500/20', 'text-emerald-900', 'border-amber-500', 'bg-amber-50/70', 'ring-2', 'ring-amber-500/20', 'text-amber-900', 'border-rose-500', 'bg-rose-50/70', 'ring-2', 'ring-rose-500/20', 'text-rose-900'); l.classList.add('border-slate-200', 'bg-white'); }); this.closest('label').classList.remove('border-slate-200', 'bg-white'); this.closest('label').classList.add('border-amber-500', 'bg-amber-50/70', 'ring-2', 'ring-amber-500/20', 'text-amber-900');">
                                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500 mb-1 shrink-0"></span>
                                    <span class="text-xs font-bold text-slate-800">Rusak Ringan</span>
                                    <span class="text-[10px] text-slate-400 leading-tight mt-0.5">Perbaikan minor</span>
                                </label>

                                {{-- Rusak Berat --}}
                                <label class="sensus-radio-card relative flex flex-col items-center justify-center cursor-pointer rounded-xl border p-2 text-center transition hover:bg-slate-50 select-none min-h-[66px] {{ $currentHasil === 'Rusak Berat' ? 'border-rose-500 bg-rose-50/70 ring-2 ring-rose-500/20 text-rose-900' : 'border-slate-200 bg-white' }}">
                                    <input type="radio" name="hasil_sensus" value="Rusak Berat" class="sr-only" required {{ $currentHasil === 'Rusak Berat' ? 'checked' : '' }}
                                           onchange="this.form.querySelectorAll('.sensus-radio-card').forEach(l => { l.classList.remove('border-emerald-500', 'bg-emerald-50/70', 'ring-2', 'ring-emerald-500/20', 'text-emerald-900', 'border-amber-500', 'bg-amber-50/70', 'ring-2', 'ring-amber-500/20', 'text-amber-900', 'border-rose-500', 'bg-rose-50/70', 'ring-2', 'ring-rose-500/20', 'text-rose-900'); l.classList.add('border-slate-200', 'bg-white'); }); this.closest('label').classList.remove('border-slate-200', 'bg-white'); this.closest('label').classList.add('border-rose-500', 'bg-rose-50/70', 'ring-2', 'ring-rose-500/20', 'text-rose-900');">
                                    <span class="w-2.5 h-2.5 rounded-full bg-rose-500 mb-1 shrink-0"></span>
                                    <span class="text-xs font-bold text-slate-800">Rusak Berat</span>
                                    <span class="text-[10px] text-slate-400 leading-tight mt-0.5">Tidak layak pakai</span>
                                </label>
                            </div>
                        </div>

                        {{-- 5. Catatan Temuan Fisik (opsional) --}}
                        <div>
                            <label for="catatan" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">
                                Catatan Temuan Fisik (Opsional)
                            </label>
                            <textarea id="catatan" name="catatan" rows="2"
                                      class="block w-full rounded-xl border border-slate-300 px-3 py-2 text-xs text-slate-900 shadow-sm focus:border-disbun-600 focus:outline-none focus:ring-1 focus:ring-disbun-400 placeholder:text-slate-400"
                                      placeholder="Tuliskan nomor seri, kelengkapan komponen, atau temuan fisik lainnya...">{{ old('catatan', $sensus?->catatan) }}</textarea>
                        </div>
                    </div>

                </div>
            </div>

            {{-- Footer: Tombol Batal dan Simpan sejajar di sebelah kanan --}}
            <div class="flex items-center justify-end gap-3 border-t border-slate-200/80 bg-slate-50/80 px-6 py-3.5">
                <a href="{{ route('monitoring-aset.index', ['periode' => $periodeDipilih]) }}"
                   class="inline-flex items-center justify-center h-9 px-4 rounded-xl border border-slate-300 bg-white text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-100">
                    Batal
                </a>
                <button type="submit"
                        class="inline-flex items-center justify-center gap-1.5 h-9 px-5 rounded-xl bg-disbun-700 text-xs font-bold text-white shadow-sm transition hover:bg-disbun-800 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>{{ $sensus ? 'Perbarui Hasil Sensus' : 'Simpan Hasil Sensus' }}</span>
                </button>
            </div>
        </form>
    </div>

</div>

@push('styles')
<style>
    @media (min-width: 768px) {
        .sensus-two-col-grid {
            display: grid !important;
            grid-template-columns: 0.35fr 0.65fr !important;
        }
    }
</style>
@endpush
@endsection
