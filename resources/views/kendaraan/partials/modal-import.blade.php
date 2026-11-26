{{-- Modal Import Kendaraan dari Excel --}}
<div id="import-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
    <div class="fixed inset-0" data-import-close></div>
    <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl">
        <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
            <div>
                <h3 class="text-lg font-semibold text-slate-900">Import Kendaraan dari Excel</h3>
                <p class="mt-0.5 text-sm text-slate-500">Unggah file .xlsx / .csv untuk menambah banyak kendaraan sekaligus.</p>
            </div>
            <button type="button" data-import-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="POST" action="{{ route('kendaraan.import') }}" enctype="multipart/form-data" class="px-6 py-6">
            @csrf
            <div class="mb-4 flex items-center justify-between gap-3 rounded-xl border border-emerald-100 bg-emerald-50/50 p-3">
                <div>
                    <span class="block text-sm font-semibold text-slate-700">Belum punya format file?</span>
                    <span class="text-xs text-slate-500">Unduh template standar import kendaraan</span>
                </div>
                <a href="{{ route('kendaraan.template') }}"
                   class="inline-flex shrink-0 items-center gap-1.5 rounded-xl border border-disbun-100 bg-white px-3 py-1.5 text-xs font-semibold text-disbun-800 shadow-sm transition hover:bg-disbun-50">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    <span>Unduh Template</span>
                </a>
            </div>
            <div class="space-y-1.5">
                <label class="block text-sm font-medium text-slate-700">File Excel / CSV <span class="text-red-500">*</span></label>
                <input type="file" name="file" accept=".xlsx,.xls,.csv" required class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                <p class="text-xs text-slate-400">Kolom Wajib: No Kartu Barang, Nama Kendaraan, Nomor Polisi, No. Rangka, No. Mesin, Tanggal Pengadaan, Nilai Perolehan, Kondisi, Status Aset. Kolom Opsional: Jenis Kendaraan, Merk, Tipe, Tanggal Habis Pakai, Keterangan, Jatuh Tempo Pajak 1 &amp; 5 Tahun, Dokumentasi. Maks 5MB.</p>
                @error('file')
                    <p class="text-xs font-medium text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="mt-4 space-y-1.5">
                <label for="import-mode" class="block text-sm font-medium text-slate-700">Mode Duplikasi</label>
                <select id="import-mode" name="mode" class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                    <option value="skip">Lewati jika Nomor Kartu Barang sudah ada</option>
                    <option value="update">Perbarui data jika Nomor Kartu Barang sama</option>
                </select>
                <p class="text-xs text-slate-400">Nomor Kartu Barang adalah identitas utama pembaruan data. Jika plat nomor berubah, riwayat plat baru akan dicatat otomatis dan plat lama dinonaktifkan.</p>
                @error('mode')
                    <p class="text-xs font-medium text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="mt-6 flex items-center justify-end gap-2">
                <button type="button" data-import-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-disbun-700 px-5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-disbun-800">Import</button>
            </div>
        </form>
    </div>
</div>

