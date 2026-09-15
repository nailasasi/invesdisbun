{{-- Modal Tambah / Edit Kendaraan (partial; dipakai di index & show) --}}
<div id="kendaraan-modal" class="overflow-y-auto"
     style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 99999; background-color: rgba(15, 23, 42, 0.6); display: flex; align-items: center; justify-content: center; padding: 1rem; visibility: hidden;">
    <div class="fixed inset-0" data-modal-close></div>
    <div class="relative w-full max-w-2xl rounded-3xl bg-white p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-start justify-between">
            <div>
                <h3 class="text-lg font-semibold text-slate-900" id="modal-title">Tambah Kendaraan</h3>
                <p class="mt-0.5 text-sm text-slate-500">Masukkan data kendaraan dinas.</p>
            </div>
            <button type="button" data-modal-close class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form id="kendaraan-form" method="POST" enctype="multipart/form-data" autocomplete="off" class="mt-5">
            @csrf
            <input type="hidden" id="field-id" name="id" value="">
            <input type="hidden" id="field-id-aset" name="id_aset" value="">

            {{-- Identitas Dasar --}}
            <div class="rounded-2xl border border-slate-100 bg-slate-50/50 p-4">
                <h4 class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Identitas Dasar</h4>
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="space-y-1">
                        <label for="field-nama" class="block text-xs font-medium text-slate-700">Nama Kendaraan <span class="text-red-500">*</span></label>
                        <input type="text" id="field-nama" name="nama_kendaraan" maxlength="100" class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: Toyota Fortuner">
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="nama_kendaraan"></p>
                    </div>
                    <div class="space-y-1">
                        <label for="field-jenis" class="block text-xs font-medium text-slate-700">Jenis Kendaraan</label>
                        <select id="field-jenis" name="jenis_kendaraan" class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                            <option value="" {{ ($kendaraan->jenis_kendaraan ?? '') == '' ? 'selected' : '' }}>-- Pilih Jenis --</option>
                            <option value="Roda Dua (Sepeda Motor)" {{ ($kendaraan->jenis_kendaraan ?? '') == 'Roda Dua (Sepeda Motor)' ? 'selected' : '' }}>Roda Dua (Sepeda Motor)</option>
                            <option value="Roda Empat (Mobil)" {{ ($kendaraan->jenis_kendaraan ?? '') == 'Roda Empat (Mobil)' ? 'selected' : '' }}>Roda Empat (Mobil)</option>
                            <option value="Truk" {{ ($kendaraan->jenis_kendaraan ?? '') == 'Truk' ? 'selected' : '' }}>Truk</option>
                            <option value="Pick Up" {{ ($kendaraan->jenis_kendaraan ?? '') == 'Pick Up' ? 'selected' : '' }}>Pick Up</option>
                            <option value="Bus" {{ ($kendaraan->jenis_kendaraan ?? '') == 'Bus' ? 'selected' : '' }}>Bus</option>
                            <option value="Lainnya" {{ ($kendaraan->jenis_kendaraan ?? '') == 'Lainnya' ? 'selected' : '' }}>Lainnya</option>
                        </select>
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="jenis_kendaraan"></p>
                    </div>
                    <div class="space-y-1">
                        <label for="field-pemegang" class="block text-xs font-medium text-slate-700">Pemegang Kendaraan</label>
                        <input type="text" id="field-pemegang" name="pemegang" list="listPegawai" maxlength="150" autocomplete="off" class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: Kepala Dinas">
                        <datalist id="listPegawai">
                            @foreach ($pegawais as $pegawai)
                                <option value="{{ $pegawai->nama_pegawai }}"></option>
                            @endforeach
                        </datalist>
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="pemegang"></p>
                    </div>
                    <div class="space-y-1">
                        <label for="field-kartu" class="block text-xs font-medium text-slate-700">Nomor Kartu Barang</label>
                        <input type="text" id="field-kartu" name="nomor_kartu_barang" maxlength="255" class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: KIB-004">
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="nomor_kartu_barang"></p>
                    </div>
                </div>
            </div>

            {{-- Surat & Dokumen --}}
            <div class="mt-3 rounded-2xl border border-slate-100 bg-slate-50/50 p-4">
                <h4 class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Surat & Dokumen</h4>
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="space-y-1">
                        <label for="field-plat" class="block text-xs font-medium text-slate-700">Nomor Plat Aktif</label>
                        <input type="text" id="field-plat" name="plat_nomor" maxlength="20" class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: B 1234 ABC">
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="plat_nomor"></p>
                    </div>
                    <div class="space-y-1">
                        <label for="field-tgl-plat" class="block text-xs font-medium text-slate-700">Masa Aktif Nomor Polisi</label>
                        <input type="date" id="field-tgl-plat" name="plat_tanggal" class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="plat_tanggal"></p>
                    </div>
                    <div class="space-y-1">
                        <label for="field-rangka" class="block text-xs font-medium text-slate-700">Nomor Rangka</label>
                        <input type="text" id="field-rangka" name="nomor_rangka" maxlength="100" class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: MH1PB...">
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="nomor_rangka"></p>
                    </div>
                    <div class="space-y-1">
                        <label for="field-mesin" class="block text-xs font-medium text-slate-700">Nomor Mesin</label>
                        <input type="text" id="field-mesin" name="nomor_mesin" maxlength="100" class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: JZ1P...">
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="nomor_mesin"></p>
                    </div>
                </div>
            </div>

            {{-- Berkas & Catatan --}}
            <div class="mt-3 rounded-2xl border border-slate-100 bg-slate-50/50 p-4">
                <h4 class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Berkas & Catatan</h4>
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="space-y-1">
                        <label for="field-foto" class="block text-xs font-medium text-slate-700">Foto Kendaraan (utama)</label>
                        <input type="file" id="field-foto" name="foto" accept="image/*" class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                        <p class="text-[11px] text-slate-400">JPG/PNG/WebP, maks 2MB. Kosongkan jika foto tidak diganti.</p>
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="foto"></p>
                    </div>
                    <div class="space-y-1">
                        <label for="field-keterangan" class="block text-xs font-medium text-slate-700">Keterangan</label>
                        <input type="text" id="field-keterangan" name="keterangan" maxlength="255" class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: Kendaraan operasional dinas">
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="keterangan"></p>
                    </div>
                </div>
            </div>

            {{-- Data Aset & Pajak --}}
            <div class="mt-3 rounded-2xl border border-slate-100 bg-slate-50/50 p-4">
                <h4 class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Data Aset & Pajak</h4>
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="space-y-1">
                        <label for="field-merk" class="block text-xs font-medium text-slate-700">Merk</label>
                        <input type="text" id="field-merk" name="merk" maxlength="100" class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: Toyota">
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="merk"></p>
                    </div>
                    <div class="space-y-1">
                        <label for="field-tipe" class="block text-xs font-medium text-slate-700">Tipe</label>
                        <input type="text" id="field-tipe" name="tipe" maxlength="50" class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: Avanza">
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="tipe"></p>
                    </div>
                    <div class="space-y-1">
                        <label for="field-nilai" class="block text-xs font-medium text-slate-700">Nilai Perolehan</label>
                        <input type="number" id="field-nilai" name="nilai_perolehan" step="0.01" min="0" class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: 250000000">
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="nilai_perolehan"></p>
                    </div>
                    <div class="space-y-1">
                        <label for="field-kondisi" class="block text-xs font-medium text-slate-700">Kondisi</label>
                        <select id="field-kondisi" name="kondisi" class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                            <option value="">-- Pilih Kondisi --</option>
                        </select>
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="kondisi"></p>
                    </div>
                    <div class="space-y-1">
                        <label for="field-status" class="block text-xs font-medium text-slate-700">Status Aset</label>
                        <input type="text" id="field-status" name="status_aset" maxlength="50" class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: aktif">
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="status_aset"></p>
                    </div>
                    <div class="space-y-1">
                        <label for="field-tgl-pengadaan" class="block text-xs font-medium text-slate-700">Tanggal Pengadaan</label>
                        <input type="date" id="field-tgl-pengadaan" name="tanggal_pengadaan" class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                    </div>
                    <div class="space-y-1">
                        <label for="field-tgl-perolehan" class="block text-xs font-medium text-slate-700">Tanggal Perolehan</label>
                        <input type="date" id="field-tgl-perolehan" name="tanggal_perolehan" class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                    </div>
                    <div class="space-y-1">
                        <label for="field-tgl-habis" class="block text-xs font-medium text-slate-700">Tanggal Habis Pakai</label>
                        <input type="date" id="field-tgl-habis" name="tanggal_habis_pakai" class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                    </div>
                    <div class="space-y-1">
                        <label for="field-pajak-tgl" class="block text-xs font-medium text-slate-700">Pajak Jatuh Tempo (PAJAK BULAN)</label>
                        <input type="date" id="field-pajak-tgl" name="pajak_tanggal_berakhir" class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 shadow-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="pajak_tanggal_berakhir"></p>
                    </div>
                    <div class="space-y-1">
                        <label for="field-pajak-5thn" class="flex items-center gap-2 pt-2 text-xs font-medium text-slate-700">
                            <input type="checkbox" id="field-pajak-5thn" name="pajak_max_tahunan" value="1" class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            Pajak 5 Tahunan
                        </label>
                    </div>
                    <div class="space-y-1">
                        <label for="field-pajak-nominal" class="block text-xs font-medium text-slate-700">Nominal Pajak</label>
                        <input type="number" id="field-pajak-nominal" name="pajak_nominal" step="0.01" min="0" class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition" placeholder="contoh: 450000">
                    </div>
                    <div class="space-y-1">
                        <label for="field-pajak-total" class="block text-xs font-medium text-slate-700">Total Pajak</label>
                        <input type="number" id="field-pajak-total" name="pajak_total" step="0.01" min="0" class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-400 transition">
                    </div>
                </div>
            </div>

            {{-- Tombol Aksi: selalu terlihat di pojok kanan bawah --}}
            <div class="sticky bottom-0 -mx-6 -mb-6 mt-4 flex items-center justify-end gap-2 border-t border-slate-100 bg-white px-6 py-4">
                <button type="button" data-modal-close class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Batal</button>
                <button type="submit" id="btn-submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700">
                    Simpan
                </button>
            </div>
        </form>
    </div>
</div>