{{-- Partial: Modal Export Rekap RKBMD (parameter pilihan) --}}
<div id="modalExportRKBMD"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
     data-modal>

    <div class="relative flex flex-col w-full max-w-lg max-h-[90vh] rounded-3xl bg-white shadow-2xl border border-slate-100 overflow-hidden">

        {{-- Header (sticky) --}}
        <div class="flex items-center justify-between border-b border-slate-100 bg-white px-6 py-4 shrink-0">
            <div class="flex items-center gap-2.5">
                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-800">Export Rekap RKBMD</h3>
                    <p class="text-[11px] text-slate-400">Sesuaikan filter data berkas Excel yang diunduh</p>
                </div>
            </div>
            <button type="button" data-close class="rounded-xl p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" title="Tutup">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Body: Form Parameter Export --}}
        <form action="{{ route('rkbmd.export') }}" method="GET" target="_blank" class="flex flex-col flex-1 overflow-hidden">
            <div class="flex-1 space-y-4 overflow-y-auto px-6 py-4 text-xs">

                {{-- 1. Jenis Usulan / Lampiran --}}
                <div>
                    <label for="export-jenis" class="mb-1 block font-semibold text-slate-700">Jenis Usulan / Lampiran</label>
                    <select id="export-jenis" name="jenis"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                        <option value="all">Semua Data Usulan (Rekapitulasi Lengkap)</option>
                        <option value="pengadaan">Usulan Pengadaan</option>
                        <option value="pemeliharaan">Usulan Pemeliharaan</option>
                        <option value="pemanfaatan">Usulan Pemanfaatan / Penghapusan</option>
                    </select>
                </div>

                {{-- 2. Unit Kerja / Bidang --}}
                <div>
                    <label for="export-bidang" class="mb-1 block font-semibold text-slate-700">Unit Kerja / Bidang</label>
                    @if ($isAdmin)
                        <select id="export-bidang" name="bidang"
                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                            <option value="all">Semua Bidang (SKPD)</option>
                            @foreach ($bidangOptions as $opt)
                                <option value="{{ $opt->id_skpd }}">{{ $opt->nama_skpd }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="text" value="{{ $mySkpd?->nama_skpd ?? 'Unit tidak terdeteksi' }}"
                               class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-500" disabled>
                        <p class="mt-1 text-[11px] text-slate-400">Ekspor otomatis terbatas pada unit kerja Anda.</p>
                    @endif
                </div>

                {{-- 3. Tahun + Status --}}
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label for="export-tahun" class="mb-1 block font-semibold text-slate-700">Tahun Anggaran</label>
                        <select id="export-tahun" name="tahun"
                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                            <option value="">Semua Tahun</option>
                            @foreach ($tahunList as $tahun)
                                <option value="{{ $tahun }}">TA {{ $tahun }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="export-status" class="mb-1 block font-semibold text-slate-700">Status Usulan</label>
                        <select id="export-status" name="status"
                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                            <option value="all">Semua Status</option>
                            <option value="disetujui">Hanya Disetujui</option>
                            <option value="draft">Termasuk Draft</option>
                        </select>
                    </div>
                </div>

                <p class="rounded-xl bg-slate-50 px-3 py-2 leading-relaxed text-[11px] text-slate-500">
                    Hasil unduhan berupa file <span class="font-semibold">.xlsx</span> dengan nama dinamis
                    <span class="font-semibold">RKBMD_{jenis}_{bidang}_{tahun}.xlsx</span>.
                </p>
            </div>

            {{-- Footer (sticky) --}}
            <div class="flex items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/50 px-6 py-3.5 shrink-0">
                <button type="button" data-close
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-100">
                    Batal
                </button>
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-disbun-700 px-5 py-2 text-xs font-bold text-white shadow-2xs transition hover:bg-disbun-800">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Download Excel
                </button>
            </div>
        </form>
    </div>
</div>
