{{-- Form Tanah (KIB A) — dipakai bersama Create & Edit via @include --}}
@php if (!isset($tanah)) { $tanah = new \App\Models\Tanah(); } @endphp
<div class="space-y-6">

    {{-- ============ 1. DATA KIB A & LEGALITAS ============ --}}
    <div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-2xs">
        <div class="mb-4 flex items-center gap-2.5 border-b border-slate-100 pb-3">
            <span class="flex h-7 w-7 items-center justify-center rounded-xl bg-emerald-50 text-xs font-bold text-emerald-600">1</span>
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">Data KIB A & Legalitas Aset</h3>
        </div>

        <div class="grid grid-cols-1 gap-4 text-xs md:grid-cols-2">
            <div>
                <label for="kib" class="mb-1 block font-semibold text-slate-700">KIB <span class="text-rose-500">*</span></label>
                <input type="text" id="kib" name="kib" required
                       value="{{ old('kib', $tanah->kib ?? '') }}" placeholder="Contoh: 01.01.01.01.001"
                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
            </div>

            <div>
                <label for="tanggal_buku" class="mb-1 block font-semibold text-slate-700">Tanggal Buku</label>
                <input type="date" id="tanggal_buku" name="tanggal_buku"
                       value="{{ old('tanggal_buku', $tanah->tanggal_buku?->format('Y-m-d') ?? '') }}"
                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
            </div>

            <div>
                <label for="tanggal_perolehan" class="mb-1 block font-semibold text-slate-700">Tanggal Perolehan</label>
                <input type="date" id="tanggal_perolehan" name="tanggal_perolehan"
                       value="{{ old('tanggal_perolehan', $tanah->tanggal_perolehan?->format('Y-m-d') ?? '') }}"
                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
            </div>

            <div>
                <label for="nilai_perolehan" class="mb-1 block font-semibold text-slate-700">Nilai Perolehan (Rp)</label>
                <input type="number" id="nilai_perolehan" name="nilai_perolehan" min="0" step="0.01"
                       value="{{ old('nilai_perolehan', $tanah->nilai_perolehan ?? '') }}" placeholder="0"
                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
            </div>

            <div>
                <label for="luas_tanah" class="mb-1 block font-semibold text-slate-700">Luas Tanah (m²)</label>
                <input type="number" id="luas_tanah" name="luas_tanah" min="0" step="0.01"
                       value="{{ old('luas_tanah', $tanah->luas_tanah ?? '') }}" placeholder="0"
                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
            </div>

            <div>
                <label for="status_hak" class="mb-1 block font-semibold text-slate-700">Status Hak</label>
                <input type="text" id="status_hak" name="status_hak"
                       value="{{ old('status_hak', $tanah->status_hak ?? '') }}" placeholder="Contoh: Hak Pakai / Hak Guna Bangunan"
                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
            </div>

            <div>
                <label for="nomor_sertifikat" class="mb-1 block font-semibold text-slate-700">Nomor Sertifikat</label>
                <input type="text" id="nomor_sertifikat" name="nomor_sertifikat"
                       value="{{ old('nomor_sertifikat', $tanah->nomor_sertifikat ?? '') }}" placeholder="Nomor sertifikat"
                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
            </div>

            <div>
                <label for="tanggal_sertifikat" class="mb-1 block font-semibold text-slate-700">Tanggal Sertifikat</label>
                <input type="date" id="tanggal_sertifikat" name="tanggal_sertifikat"
                       value="{{ old('tanggal_sertifikat', $tanah->tanggal_sertifikat?->format('Y-m-d') ?? '') }}"
                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
            </div>

            <div>
                <label for="ketkel" class="mb-1 block font-semibold text-slate-700">Kecamatan / Kabupaten (Ketkel)</label>
                <input type="text" id="ketkel" name="ketkel"
                       value="{{ old('ketkel', $tanah->ketkel ?? '') }}" placeholder="Kelurahan / Desa"
                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
            </div>

            <div class="md:col-span-2">
                <label for="deskripsi_objek" class="mb-1 block font-semibold text-slate-700">Deskripsi Objek</label>
                <textarea id="deskripsi_objek" name="deskripsi_objek" rows="2" placeholder="Masukkan deskripsi objek tanah"
                          class="w-full rounded-xl border border-slate-200 px-3 py-2 text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">{{ old('deskripsi_objek', $tanah->deskripsi_objek ?? '') }}</textarea>
            </div>

            <div class="md:col-span-2">
                <label for="alamat" class="mb-1 block font-semibold text-slate-700">Alamat Lengkap</label>
                <textarea id="alamat" name="alamat" rows="2" placeholder="Nama jalan, dukuh, desa..."
                          class="w-full rounded-xl border border-slate-200 px-3 py-2 text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">{{ old('alamat', $tanah->alamat ?? '') }}</textarea>
            </div>
        </div>
    </div>

    {{-- ============ 2. PEMANFAATAN & MEDIA ============ --}}
    <div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-2xs">
        <div class="mb-4 flex items-center gap-2.5 border-b border-slate-100 pb-3">
            <span class="flex h-7 w-7 items-center justify-center rounded-xl bg-amber-50 text-xs font-bold text-amber-600">2</span>
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">Pemanfaatan, Kondisi & Media</h3>
        </div>

        <div class="grid grid-cols-1 gap-4 text-xs md:grid-cols-2">
            <div>
                <label for="penggunaan" class="mb-1 block font-semibold text-slate-700">Penggunaan</label>
                <input type="text" id="penggunaan" name="penggunaan"
                       value="{{ old('penggunaan', $tanah->penggunaan ?? '') }}" placeholder="Contoh: Kebun / Kantor / Lahan Pertanian"
                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
            </div>

            <div>
                <label for="penggunaan_air" class="mb-1 block font-semibold text-slate-700">Penggunaan Air</label>
                <select id="penggunaan_air" name="penggunaan_air"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                    <option value="">-- Pilih Penggunaan Air --</option>
                    @foreach (['Mata Air', 'Air Tanah', 'Air Sungai', 'Air Lainnya'] as $air)
                        <option value="{{ $air }}" @selected(old('penggunaan_air', $tanah->penggunaan_air ?? '') == $air)>{{ $air }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="kondisi" class="mb-1 block font-semibold text-slate-700">Kondisi</label>
                <select id="kondisi" name="kondisi"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                    <option value="">-- Pilih Kondisi --</option>
                    @foreach (['Baik', 'Rusak Ringan', 'Rusak Berat'] as $kondisi)
                        <option value="{{ $kondisi }}" @selected(old('kondisi', $tanah->kondisi ?? '') == $kondisi)>{{ $kondisi }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="foto" class="mb-1 block font-semibold text-slate-700">Foto Tanah</label>
                <input type="file" id="foto" name="foto" accept="image/*"
                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-slate-800 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-emerald-600 hover:file:bg-emerald-100 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                <p class="mt-1 text-[11px] text-slate-400">Format gambar (JPG/PNG/WebP) maks. 5 MB</p>

                @php
                    $fotoThumb = null;
                    if (!empty($tanah->foto_tanah)) {
                        if (\Illuminate\Support\Str::startsWith($tanah->foto_tanah, ['http://', 'https://', 'data:'])) {
                            $fotoThumb = $tanah->foto_tanah;
                        } elseif (\Illuminate\Support\Facades\Storage::disk('public')->exists($tanah->foto_tanah)) {
                            $fotoThumb = \Illuminate\Support\Facades\Storage::disk('public')->url($tanah->foto_tanah);
                        }
                    }
                @endphp
                @if ($fotoThumb)
                    <div class="mt-2">
                        <img src="{{ $fotoThumb }}" alt="Foto tanah saat ini"
                             class="h-20 w-28 rounded-xl border border-slate-200 object-cover">
                        <p class="mt-1 text-[11px] text-slate-400">Foto saat ini — unggah baru untuk mengganti</p>
                    </div>
                @endif
            </div>

            <div>
                <label for="video_url" class="mb-1 block font-semibold text-slate-700">Tautan Video Dokumentasi (Google Drive / YouTube)</label>
                <input type="url" id="video_url" name="video_url"
                       value="{{ old('video_url', $tanah->video_tanah ?? '') }}" placeholder="https://drive.google.com/..."
                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                <p class="mt-1 text-[11px] text-slate-400">Tempel tautan berbagi video (Google Drive / YouTube)</p>
            </div>

            <div class="md:col-span-2">
                <label for="keterangan" class="mb-1 block font-semibold text-slate-700">Keterangan</label>
                <textarea id="keterangan" name="keterangan" rows="2" placeholder="Keterangan tambahan mengenai tanah"
                          class="w-full rounded-xl border border-slate-200 px-3 py-2 text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">{{ old('keterangan', $tanah->keterangan ?? '') }}</textarea>
            </div>
        </div>
    </div>

    {{-- ============ 3. PETUGAS & TITIK LOKASI ============ --}}
    <div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-2xs">
        <div class="mb-4 flex items-center gap-2.5 border-b border-slate-100 pb-3">
            <span class="flex h-7 w-7 items-center justify-center rounded-xl bg-blue-50 text-xs font-bold text-blue-600">3</span>
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">Petugas Lapangan & Titik Lokasi</h3>
        </div>

        <div class="grid grid-cols-1 gap-4 text-xs md:grid-cols-2">
            <div>
                <label for="nama_petugas" class="mb-1 block font-semibold text-slate-700">Nama Petugas</label>
                <input type="text" id="nama_petugas" name="nama_petugas"
                       value="{{ old('nama_petugas', $tanah->nama_petugas ?? '') }}" placeholder="Masukkan nama petugas"
                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
            </div>

            <div>
                <label for="nomor_hp_petugas" class="mb-1 block font-semibold text-slate-700">Nomor Tlp Petugas</label>
                <input type="text" id="nomor_hp_petugas" name="nomor_hp_petugas" maxlength="20"
                       value="{{ old('nomor_hp_petugas', $tanah->nomor_hp_petugas ?? '') }}" placeholder="Contoh: 081234567890"
                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
            </div>

            <div class="md:col-span-2">
                <label for="google_maps" class="mb-1 block font-semibold text-slate-700">Tautan Google Maps</label>
                <input type="url" id="google_maps" name="google_maps"
                       value="{{ old('google_maps', $tanah->google_maps ?? '') }}" placeholder="https://maps.google.com/..."
                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                <p class="mt-1 text-[11px] text-slate-400">Masukkan link lokasi Google Maps aset.</p>
            </div>
        </div>
    </div>

    {{-- ============ AKSI SIMPAN ============ --}}
    <div class="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50/50 px-6 py-4 rounded-3xl">
        <a href="{{ route('tanah.index') }}"
           class="rounded-2xl border border-slate-200 bg-white px-5 py-2.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50">
            Batal
        </a>

        <button type="submit"
                class="inline-flex items-center gap-1.5 rounded-2xl bg-emerald-600 px-6 py-2.5 text-xs font-bold text-white shadow-2xs transition hover:bg-emerald-700">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <span>{{ $tanah->id_tanah ? 'Simpan Perubahan' : 'Simpan Data Tanah' }}</span>
        </button>
    </div>

</div>