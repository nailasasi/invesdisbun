@if ($bolehKelolaRuangan)
{{-- Modal Tambah/Ubah Inventaris Lokal --}}
<div id="inv-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
    <div class="fixed inset-0" data-inv-close></div>
    <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h3 id="inv-modal-title" class="text-lg font-semibold text-slate-900">Tambah Inventaris</h3>
            <button type="button" data-inv-close class="flex h-8 w-8 items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-100">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form id="inv-form" method="POST" autocomplete="off">
            @csrf
            <input type="hidden" name="_method" value="POST" id="inv-method">

            <div class="max-h-[70vh] space-y-4 overflow-y-auto px-6 py-6">
                <div class="space-y-1.5" id="inv-kode-row">
                    <label for="inv-kode" class="block text-sm font-medium text-slate-700">Kode Inventaris</label>
                    <input type="text" id="inv-kode" name="kode_inventaris" maxlength="50" autocomplete="off"
                           placeholder="Kosongkan untuk kode otomatis"
                           class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm font-mono text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                    <p id="inv-kode-note" class="text-xs text-slate-400">Kosongkan agar kode dibuat otomatis (contoh: RUA-INV-001).</p>
                    <p class="field-error hidden text-xs font-medium text-red-600" data-inv-error="kode_inventaris"></p>
                </div>

                <div class="space-y-1.5">
                    <label for="inv-nama" class="block text-sm font-medium text-slate-700">Nama Barang <span class="text-red-500">*</span></label>
                    <input type="text" id="inv-nama" name="nama_barang" required maxlength="150"
                           class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                    <p class="field-error hidden text-xs font-medium text-red-600" data-inv-error="nama_barang"></p>
                </div>

                <div class="space-y-1.5">
                    <label for="inv-merk" class="block text-sm font-medium text-slate-700">Merk</label>
                    <input type="text" id="inv-merk" name="merk" maxlength="100" placeholder="contoh: Canon"
                           class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                    <p class="field-error hidden text-xs font-medium text-red-600" data-inv-error="merk"></p>
                </div>

                <div class="space-y-1.5">
                    <label for="inv-kondisi" class="block text-sm font-medium text-slate-700">Kondisi</label>
                    <select id="inv-kondisi" name="kondisi"
                            class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                        <option value="Baik">Baik</option>
                        <option value="Rusak Ringan">Rusak Ringan</option>
                        <option value="Rusak Berat">Rusak Berat</option>
                    </select>
                    <p class="field-error hidden text-xs font-medium text-red-600" data-inv-error="kondisi"></p>
                </div>

                <div class="space-y-1.5">
                    <label for="inv-keterangan" class="block text-sm font-medium text-slate-700">Keterangan</label>
                    <textarea id="inv-keterangan" name="keterangan" rows="3"
                              class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition"></textarea>
                    <p class="field-error hidden text-xs font-medium text-red-600" data-inv-error="keterangan"></p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-6 py-4">
                <button type="button" data-inv-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                <button type="submit" id="btn-inv-submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-disbun-700 px-5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-disbun-800">Simpan</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Mutasi Ruangan (Inventaris Lokal) --}}
<div id="inv-move-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
    <div class="fixed inset-0" data-inv-move-close></div>
    <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
        <div class="flex items-start gap-4">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-indigo-100">
                <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>
            </div>
            <div class="min-w-0 flex-1">
                <h3 class="text-lg font-semibold text-slate-900">Mutasi Ruangan</h3>
                <p class="mt-1 text-sm text-slate-500">Pindahkan inventaris lokal ini ke ruangan lain. Kode inventaris tidak berubah.</p>
            </div>
            <button type="button" data-inv-move-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form id="inv-move-form" method="POST" autocomplete="off" class="mt-5 space-y-4">
            @csrf
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
                <p><span class="font-semibold text-slate-700">Aset:</span> <span id="inv-move-aset"></span></p>
                <p class="mt-1"><span class="font-semibold text-slate-700">KIB:</span> <span id="inv-move-kib" class="font-mono"></span></p>
                <p class="mt-1"><span class="font-semibold text-slate-700">Ruangan Asal:</span> {{ $ruangan->nama_ruangan }}</p>
                <p class="mt-2 border-t border-slate-200 pt-2 text-[11px] text-slate-400">Inventaris lokal bukan aset resmi — nilai pada baris KIB adalah kode inventaris ruangan.</p>
            </div>

            <div class="space-y-1.5">
                <label for="inv-tujuan" class="block text-sm font-medium text-slate-700">Ruangan Tujuan <span class="text-red-500">*</span></label>
                @if ($ruanganTujuan->isEmpty())
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-700">Tidak ada ruangan tujuan aktif lain yang dapat Anda akses.</div>
                @else
                    <select id="inv-tujuan" name="id_ruangan_tujuan" required
                            class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-400 transition">
                        <option value="" selected disabled>-- Pilih Ruangan Tujuan --</option>
                        @foreach ($ruanganTujuan as $tujuan)
                            <option value="{{ $tujuan->id_ruangan }}">{{ $tujuan->nama_ruangan }}</option>
                        @endforeach
                    </select>
                @endif
                <p class="text-xs text-slate-500">Hanya ruangan aktif yang tercantum.</p>
                <p class="field-error hidden text-xs font-medium text-red-600" data-inv-error="id_ruangan_tujuan"></p>
            </div>

            <div class="space-y-1.5">
                <label for="inv-move-keterangan" class="block text-sm font-medium text-slate-700">Keterangan</label>
                <textarea id="inv-move-keterangan" name="keterangan" rows="2"
                          class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-400 transition"
                          placeholder="contoh: penataan ulang"></textarea>
            </div>

            <p class="text-xs text-amber-600">Inventaris lokal bukan aset resmi: tidak ada penempatan baru, sehingga tidak ada status <span class="font-medium">Belum Difinalisasi</span> yang perlu diselesaikan.</p>

            <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                <button type="button" data-inv-move-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">Batal</button>
                <button type="submit" id="btn-inv-move-submit" @if ($ruanganTujuan->isEmpty()) disabled @endif class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-50">Pindahkan</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Usulkan Penghapusan Inventaris Lokal --}}
<div id="inv-del-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
    <div class="fixed inset-0" data-inv-del-close></div>
    <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
        <div class="flex items-start gap-4">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-red-100">
                <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </div>
            <div class="min-w-0">
                <h3 class="text-lg font-semibold text-slate-900">Usulkan Penghapusan Inventaris</h3>
                <p class="mt-1 text-sm text-slate-500">Usulkan penghapusan <span id="inv-del-name" class="font-medium text-slate-700"></span>? Kondisi barang akan otomatis ditandai sebagai <strong>Rusak Berat</strong> dan masuk antrean modul Usulan Penghapusan.</p>
            </div>
        </div>
        <div class="mt-6 flex justify-end gap-2">
            <button type="button" data-inv-del-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
            <form id="inv-del-form" method="POST" action="">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700">Usulkan Hapus</button>
            </form>
        </div>
    </div>
</div>
@endif

