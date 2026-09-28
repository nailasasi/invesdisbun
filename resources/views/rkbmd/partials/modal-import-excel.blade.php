{{-- Partial: Modal Import Usulan RKBMD dari Excel --}}
<div id="modalImportExcel"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
     data-modal>

    <div class="relative flex flex-col w-full max-w-lg max-h-[90vh] rounded-3xl bg-white shadow-2xl border border-slate-100 overflow-hidden">

        {{-- Header (sticky) --}}
        <div class="flex items-center justify-between border-b border-slate-100 bg-white px-6 py-4 shrink-0">
            <div class="flex items-center gap-2.5">
                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-800">Import Template Excel</h3>
                    <p class="text-[11px] text-slate-400">Unggah kembali file sesuai template (.xlsx / .xls)</p>
                </div>
            </div>
            <button type="button" data-close class="rounded-xl p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" title="Tutup">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Body --}}
        <form method="POST" action="{{ route('rkbmd.import') }}" enctype="multipart/form-data" class="flex flex-col flex-1 overflow-hidden">
            @csrf
            <div class="flex-1 space-y-4 overflow-y-auto px-6 py-4 text-xs">
                <div>
                    <label for="excel-file" class="mb-1 block font-semibold text-slate-700">
                        File Excel Usulan RKBMD <span class="text-rose-500">*</span>
                    </label>
                    <input type="file" id="excel-file" name="file_excel" accept=".xlsx,.xls" required
                           class="w-full cursor-pointer rounded-xl border border-dashed border-slate-200 bg-slate-50 text-xs text-slate-600 transition file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-emerald-700 hover:bg-slate-100">
                    <p class="mt-1 text-[11px] text-slate-400">Maksimal 5 MB, format .xlsx / .xls.</p>
                </div>

                <div>
                    <label for="excel-skpd" class="mb-1 block font-semibold text-slate-700">
                        Unit Kerja / Bidang Pengusul <span class="text-rose-500">*</span>
                    </label>
                    @if ($isAdmin)
                        <select id="excel-skpd" name="id_skpd" required
                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                            <option value="" disabled selected>-- Pilih Unit Kerja --</option>
                            @foreach ($bidangOptions as $opt)
                                <option value="{{ $opt->id_skpd }}">{{ $opt->nama_skpd }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="text" value="{{ $mySkpd?->nama_skpd ?? 'Unit tidak terdeteksi' }}"
                               class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-500" disabled>
                    @endif
                </div>

                <div>
                    <label for="excel-tahun" class="mb-1 block font-semibold text-slate-700">
                        Tahun Anggaran <span class="text-rose-500">*</span>
                    </label>
                    <input type="number" id="excel-tahun" name="tahun_anggaran" min="2000" max="2100"
                           value="{{ old('tahun_anggaran', now()->year + 1) }}" required
                           class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                </div>

                <p class="rounded-xl bg-slate-50 px-3 py-2 leading-relaxed text-[11px] text-slate-500">
                    Gunakan file hasil <span class="font-semibold">Unduh Template</span>. Baris contoh otomatis dilewati;
                    hanya baris data yang valid yang diimpor sebagai usulan <span class="font-semibold">Draft</span>.
                </p>
            </div>

            {{-- Footer (sticky) --}}
            <div class="flex items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/50 px-6 py-3.5 shrink-0">
                <button type="button" data-close
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-100">
                    Batal
                </button>
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-5 py-2 text-xs font-bold text-white shadow-2xs transition hover:bg-emerald-700">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Import Excel
                </button>
            </div>
        </form>
    </div>
</div>