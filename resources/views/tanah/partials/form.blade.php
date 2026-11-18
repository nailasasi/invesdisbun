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
                       value="{{ old('nilai_perolehan', $tanah->nilai_perolehan ?? '') }}" placeholder="contoh: 2213000"
                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                <small class="text-xs text-slate-500 text-muted">Catatan: Masukkan angka murni <strong>tanpa titik</strong> ataupun <strong>koma</strong> (contoh: 2213000).</small>
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
                @php
                    // Pertahankan nilai lama yang belum dikenal (mis. hasil import
                    // berkapitalisasi berbeda) agar tidak hilang saat form disimpan.
                    $airOptions = ['Mata Air', 'Air Tanah', 'Air Sungai', 'Air Lainnya'];
                    $airTerpilih = old('penggunaan_air', $tanah->penggunaan_air ?? '');
                    if ($airTerpilih !== null && $airTerpilih !== '' && ! in_array($airTerpilih, $airOptions, true)) {
                        $airOptions[] = $airTerpilih;
                    }
                @endphp
                <select id="penggunaan_air" name="penggunaan_air"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                    <option value="">-- Pilih Penggunaan Air --</option>
                    @foreach ($airOptions as $air)
                        <option value="{{ $air }}" @selected($airTerpilih == $air)>{{ $air }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="kondisi" class="mb-1 block font-semibold text-slate-700">Kondisi</label>
                @php
                    // Sama seperti Penggunaan Air: nilai lama (mis. "BAIK") tetap
                    // ditampilkan & terpilih agar tidak terhapus saat disimpan.
                    $kondisiOptions = ['Baik', 'Rusak Ringan', 'Rusak Berat'];
                    $kondisiTerpilih = old('kondisi', $tanah->kondisi ?? '');
                    if ($kondisiTerpilih !== null && $kondisiTerpilih !== '' && ! in_array($kondisiTerpilih, $kondisiOptions, true)) {
                        $kondisiOptions[] = $kondisiTerpilih;
                    }
                @endphp
                <select id="kondisi" name="kondisi"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                    <option value="">-- Pilih Kondisi --</option>
                    @foreach ($kondisiOptions as $kondisi)
                        <option value="{{ $kondisi }}" @selected($kondisiTerpilih == $kondisi)>{{ $kondisi }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="foto" class="mb-1 block font-semibold text-slate-700">Foto Tanah</label>
                <input type="file" id="foto" name="foto" accept="image/*"
                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-slate-800 file:mr-3 file:rounded-lg file:border-0 file:bg-disbun-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-disbun-700 hover:file:bg-disbun-100 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
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

    {{-- ============ 4. KONFIRMASI OTORISASI (HANYA UNTUK EDIT TANAH) ============ --}}
    @if ($tanah->exists || !empty($tanah->id_tanah))
    <div class="rounded-3xl border border-amber-100 bg-amber-50/40 p-6 shadow-2xs">
        <div class="mb-4 flex items-center gap-2.5 border-b border-amber-100 pb-3">
            <span class="flex h-7 w-7 items-center justify-center rounded-xl bg-amber-100 text-xs font-bold text-amber-700">4</span>
            <h3 class="text-xs font-bold uppercase tracking-wider text-amber-800">Konfirmasi Otorisasi</h3>
        </div>

        <div class="text-xs">
            <label for="password_otorisasi" class="mb-1 block font-semibold text-slate-700">
                Password Otorisasi (Wajib) <span class="text-rose-500">*</span>
            </label>
            {{-- Field password sengaja TIDAK memakai old(): nilai tidak pernah
                 diisi ulang setelah validasi gagal. --}}
            <input type="password" id="password_otorisasi" name="password_otorisasi" required
                   autocomplete="current-password" placeholder="Masukkan ulang password akun Anda"
                   class="w-full max-w-md rounded-xl border border-slate-200 px-3 py-2 text-slate-800 focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
            <p class="mt-1 text-[11px] text-slate-400">
                Demi keamanan, masukkan ulang password akun Anda untuk menyimpan perubahan ini.
            </p>
            @error('password_otorisasi')
                <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Jam real-time + kamera bukti otorisasi (wajib sebelum simpan) --}}
        <x-otorisasi-bukti />
    </div>
    @endif

    {{-- ============ AKSI SIMPAN ============ --}}
    <div class="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50/50 px-6 py-4 rounded-3xl">
        <a href="{{ route('tanah.index') }}"
           class="rounded-2xl border border-slate-200 bg-white px-5 py-2.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50">
            Batal
        </a>

        <button type="submit"
                class="inline-flex items-center gap-1.5 rounded-2xl bg-disbun-700 px-6 py-2.5 text-xs font-bold text-white shadow-2xs transition hover:bg-disbun-800">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <span>{{ $tanah->id_tanah ? 'Simpan Perubahan' : 'Simpan Data Tanah' }}</span>
        </button>
    </div>

</div>
