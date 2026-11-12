{{-- Modal Import Excel --}}
<div id="import-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
    <div class="fixed inset-0" data-import-close></div>
    <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl overflow-hidden">
        <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
            <div>
                <h3 class="text-lg font-semibold text-slate-900">Import Aset Barang dari Excel</h3>
                <p class="mt-0.5 text-sm text-slate-500">Unggah file .xlsx / .csv untuk menambah banyak aset sekaligus.</p>
            </div>
            <button type="button" data-import-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Form Unggah Excel --}}
        <form id="import-form" method="POST" action="{{ route('aset-barang.import') }}" enctype="multipart/form-data" class="px-6 py-6">
            @csrf
            <div id="import-form-error" class="hidden mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-700"></div>

            <div class="mb-4 flex items-center justify-between gap-3 rounded-xl border border-emerald-100 bg-emerald-50/50 p-3">
                <div>
                    <span class="block text-sm font-semibold text-slate-700">Belum punya format file?</span>
                    <span class="text-xs text-slate-500">Unduh template standar import aset barang</span>
                </div>
                <a href="{{ route('aset-barang.template') }}"
                   class="inline-flex shrink-0 items-center gap-1.5 rounded-xl border border-disbun-100 bg-white px-3 py-1.5 text-xs font-semibold text-disbun-800 shadow-sm transition hover:bg-disbun-50">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    <span>Unduh Template</span>
                </a>
            </div>

            <div class="space-y-1.5">
                <label class="block text-sm font-medium text-slate-700">File Excel / CSV <span class="text-red-500">*</span></label>
                <input type="file" id="import-file-input" name="file" accept=".xlsx,.xls,.csv" required class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                <p class="text-xs text-slate-400">Kolom Wajib: Nomor Kartu Barang / Kode Barang, Nama Barang, Tanggal Pengadaan. Kolom Opsional: Merk, Tanggal Habis Pakai, Nilai Perolehan (Rp), Kondisi, Status Aset. Menggunakan mekanisme Partial Import (duplikat &amp; baris tidak valid ditolak, baris valid tetap disimpan). Maks 5MB.</p>
                @error('file')
                    <p class="text-xs font-medium text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-6 flex items-center justify-end gap-2">
                <button type="button" data-import-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                <button type="submit" id="btn-import-submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-disbun-700 px-5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-disbun-800 disabled:opacity-50">
                    <span id="btn-import-text">Import</span>
                </button>
            </div>
        </form>

        {{-- Seksi Hasil Import (Muncul setelah pemrosesan AJAX selesai) --}}
        <div id="import-result-section" class="hidden px-6 py-6 space-y-4">
            <div id="import-result-banner" class="rounded-2xl border p-4">
                <div class="flex items-start gap-3">
                    <div id="import-result-icon" class="shrink-0 mt-0.5"></div>
                    <div class="min-w-0 flex-1">
                        <h4 id="import-result-title" class="text-base font-bold text-slate-900"></h4>
                        <div class="mt-1 flex items-center gap-2 text-xs">
                            <span id="import-count-success" class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 font-bold text-emerald-800"></span>
                            <span id="import-count-failed" class="inline-flex items-center rounded-full bg-rose-100 px-2.5 py-0.5 font-bold text-rose-800"></span>
                        </div>
                    </div>
                </div>

                <div id="import-error-list-wrapper" class="mt-4 border-t border-slate-200/60 pt-3">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Detail Kegagalan:</p>
                    <ul id="import-error-list" class="max-h-48 overflow-y-auto space-y-1 font-mono text-xs text-slate-700 list-disc list-inside"></ul>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                <button type="button" id="btn-import-retry" class="hidden rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">
                    Pilih File Lain
                </button>
                <button type="button" id="btn-import-done" class="inline-flex items-center justify-center rounded-xl bg-disbun-700 px-5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-disbun-800">
                    Selesai
                </button>
            </div>
        </div>
    </div>
</div>
