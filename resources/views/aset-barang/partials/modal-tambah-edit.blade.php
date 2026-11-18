{{-- Modal Tambah / Edit Aset Master (field, urutan, dan penanda wajib Tambah & Edit identik). Penempatan TIDAK dikelola di form ini. --}}
<div id="aset-modal" class="invisible fixed inset-0 z-[1000] flex items-center justify-center overflow-y-auto bg-slate-900/60 p-4 backdrop-blur-sm">
    <div class="fixed inset-0" data-modal-close></div>
    <div class="relative flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-100 px-6 py-4">
            <div class="min-w-0">
                <h3 id="modal-title" class="text-base font-semibold text-slate-900">Tambah Aset</h3>
                <p id="modal-subtitle" class="mt-0.5 text-xs text-slate-500 sm:text-sm">Lengkapi data master aset.</p>
            </div>
            <button type="button" data-modal-close class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form id="aset-form" method="POST" action="{{ route('aset-barang.store.flat') }}" autocomplete="off" class="flex min-h-0 flex-1 flex-col overflow-hidden">
            @csrf
            <input type="hidden" id="field-id" name="id" value="">
            <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5">
                <div class="grid grid-cols-1 gap-x-5 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                    {{-- Edit & Tambah HANYA data master aset: Pemegang, Ruangan, SKPD, dan
                         Lokasi Fisik sengaja TIDAK ada di form. Penempatan dikelola
                         tombol + / Edit Penempatan / Mutasi; SKPD & Lokasi turunan server. --}}

                    <div class="flex flex-col gap-1.5">
                        <label for="field-barang" class="block text-sm font-medium text-slate-700">Nama Barang <span class="text-red-500">*</span></label>
                        <input type="text" id="field-barang" name="nama_barang" required maxlength="100" class="block h-10 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: Laptop">
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="nama_barang"></p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="field-kartu" class="block text-sm font-medium text-slate-700">Nomor Kartu Barang <span class="text-red-500">*</span></label>
                        <input type="text" id="field-kartu" name="nomor_kartu_barang" maxlength="255" required class="block h-10 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: KIB-001">
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="nomor_kartu_barang"></p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="field-merk" class="block text-sm font-medium text-slate-700">Merk</label>
                        <input type="text" id="field-merk" name="merk" maxlength="100" class="block h-10 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: Canon">
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="merk"></p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="field-pengadaan" class="block text-sm font-medium text-slate-700">Tanggal Pengadaan <span class="text-red-500">*</span></label>
                        <input type="date" id="field-pengadaan" name="tanggal_pengadaan" required class="block h-10 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="tanggal_pengadaan"></p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="field-tanggal-habis-pakai" class="block text-sm font-medium text-slate-700">Tanggal Habis Pakai <span class="text-red-500">*</span></label>
                        <input id="field-tanggal-habis-pakai" name="tanggal_habis_pakai" type="date" required class="block h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="tanggal_habis_pakai"></p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="field-nilai-perolehan" class="block text-sm font-medium text-slate-700">Nilai Perolehan <span class="text-red-500">*</span></label>
                        <input id="field-nilai-perolehan" name="nilai_perolehan" type="number" step="any" inputmode="decimal" required min="0" class="block h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: 1462000">
                        <small class="text-xs text-slate-500 text-muted">Catatan: Masukkan angka murni <strong>tanpa titik</strong> ataupun <strong>koma</strong> (contoh: 1462000).</small>
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="nilai_perolehan"></p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="field-kondisi" class="block text-sm font-medium text-slate-700">Kondisi <span class="text-red-500">*</span></label>
                        <select id="field-kondisi" name="kondisi" required class="block h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                            <option value="" selected>-- Pilih Kondisi --</option>
                            @foreach ($kondisiList as $kondisi)
                                <option value="{{ $kondisi }}">{{ $kondisi }}</option>
                            @endforeach
                        </select>
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="kondisi"></p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="field-status" class="block text-sm font-medium text-slate-700">Status Aset <span class="text-red-500">*</span></label>
                        <select id="field-status" name="status_aset" required class="block h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="status_aset"></p>
                    </div>
                </div>

                <p id="modal-note" class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-[11px] leading-snug text-slate-500">
                    Form ini hanya mengubah data master aset. Pemegang, Ruangan, SKPD, dan Lokasi Fisik dikelola lewat Penempatan / Mutasi, dan nilainya dihitung otomatis oleh server.
                </p>
            </div>

            <div class="flex shrink-0 items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/60 px-6 py-4">
                <button type="button" data-modal-close class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">Batal</button>
                <button type="submit" id="btn-submit" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-disbun-700 px-5 text-sm font-medium text-white shadow-sm transition hover:bg-disbun-800">
                    Simpan
                </button>
            </div>
        </form>
    </div>
</div>

