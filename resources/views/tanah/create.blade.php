@extends('layouts.app')

@section('title', 'Tambah Inventaris Tanah')

@section('content')
<div class="p-6">

    {{-- HEADER --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg"
                         class="w-5 h-5 text-emerald-600"
                         fill="none"
                         viewBox="0 0 24 24"
                         stroke="currentColor">
                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/>
                    </svg>
                </div>

                <div>
                    <h1 class="text-2xl font-bold text-slate-800">
                        Tambah Inventaris Tanah
                    </h1>

                    <p class="text-sm text-slate-500 mt-0.5">
                        Tambahkan data aset tanah sesuai KIB A
                    </p>
                </div>
            </div>
        </div>

        <a href="{{ route('tanah.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2.5
                  border border-slate-200 rounded-xl
                  text-sm font-semibold text-slate-600
                  hover:bg-slate-50 transition">

            <svg xmlns="http://www.w3.org/2000/svg"
                 class="w-4 h-4"
                 fill="none"
                 viewBox="0 0 24 24"
                 stroke="currentColor">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M15 19l-7-7 7-7"/>
            </svg>

            Kembali
        </a>
    </div>


    {{-- ERROR VALIDATION --}}
    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4">
            <div class="flex items-start gap-3">
                <svg xmlns="http://www.w3.org/2000/svg"
                     class="w-5 h-5 text-red-500 mt-0.5"
                     fill="none"
                     viewBox="0 0 24 24"
                     stroke="currentColor">
                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M12 9v4m0 4h.01M10.29 3.86l-8.82 15A2 2 0 003.2 22h17.6a2 2 0 001.73-3.14l-8.82-15a2 2 0 00-3.42 0Z"/>
                </svg>

                <div>
                    <p class="text-sm font-semibold text-red-700">
                        Data belum dapat disimpan
                    </p>

                    <ul class="mt-1 text-sm text-red-600 list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif


    <form method="POST"
          action="{{ route('tanah.store') }}"
          class="space-y-5">

        @csrf


        {{-- =====================================================
             1. IDENTITAS KIB A
        ====================================================== --}}
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm">

            <div class="px-5 py-4 border-b border-slate-200">
                <h2 class="text-base font-bold text-slate-800">
                    Identitas KIB A
                </h2>

                <p class="text-xs text-slate-500 mt-1">
                    Informasi dasar pencatatan aset tanah.
                </p>
            </div>

            <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-5">

                {{-- NO EXCEL --}}
                <div>
                    <label for="no_excel"
                           class="block text-sm font-semibold text-slate-700 mb-1.5">
                        No
                    </label>

                    <input type="number"
                           id="no_excel"
                           name="no_excel"
                           value="{{ old('no_excel') }}"
                           min="1"
                           placeholder="Nomor sesuai Excel"
                           class="w-full px-4 py-2.5 rounded-xl
                                  border border-slate-200
                                  text-sm text-slate-700
                                  focus:outline-none
                                  focus:ring-2 focus:ring-emerald-100
                                  focus:border-emerald-400">

                    <p class="text-xs text-slate-400 mt-1">
                        Nomor referensi dari data Excel.
                    </p>
                </div>


                {{-- KIB --}}
                <div>
                    <label for="kib"
                           class="block text-sm font-semibold text-slate-700 mb-1.5">
                        KIB
                    </label>

                    <input type="text"
                           id="kib"
                           name="kib"
                           value="{{ old('kib') }}"
                           placeholder="Contoh: 01.01.01.01.001"
                           class="w-full px-4 py-2.5 rounded-xl
                                  border border-slate-200
                                  text-sm text-slate-700
                                  focus:outline-none
                                  focus:ring-2 focus:ring-emerald-100
                                  focus:border-emerald-400">
                </div>


                {{-- TANGGAL BUKU --}}
                <div>
                    <label for="tanggal_buku"
                           class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Tanggal Buku
                    </label>

                    <input type="date"
                           id="tanggal_buku"
                           name="tanggal_buku"
                           value="{{ old('tanggal_buku') }}"
                           class="w-full px-4 py-2.5 rounded-xl
                                  border border-slate-200
                                  text-sm text-slate-700
                                  focus:outline-none
                                  focus:ring-2 focus:ring-emerald-100
                                  focus:border-emerald-400">
                </div>


                {{-- TANGGAL PEROLEHAN --}}
                <div>
                    <label for="tanggal_perolehan"
                           class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Tanggal Perolehan
                    </label>

                    <input type="date"
                           id="tanggal_perolehan"
                           name="tanggal_perolehan"
                           value="{{ old('tanggal_perolehan') }}"
                           class="w-full px-4 py-2.5 rounded-xl
                                  border border-slate-200
                                  text-sm text-slate-700
                                  focus:outline-none
                                  focus:ring-2 focus:ring-emerald-100
                                  focus:border-emerald-400">
                </div>


                {{-- PEROLEHAN --}}
                <div>
                    <label for="nilai_perolehan"
                           class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Perolehan
                    </label>

                    <input type="number"
                           id="nilai_perolehan"
                           name="nilai_perolehan"
                           value="{{ old('nilai_perolehan') }}"
                           min="0"
                           step="0.01"
                           placeholder="Masukkan nilai perolehan"
                           class="w-full px-4 py-2.5 rounded-xl
                                  border border-slate-200
                                  text-sm text-slate-700
                                  focus:outline-none
                                  focus:ring-2 focus:ring-emerald-100
                                  focus:border-emerald-400">
                </div>


                {{-- DESKRIPSI OBJEK --}}
                <div class="md:col-span-2">
                    <label for="deskripsi_objek"
                           class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Deskripsi Objek
                    </label>

                    <textarea id="deskripsi_objek"
                              name="deskripsi_objek"
                              rows="3"
                              placeholder="Masukkan deskripsi objek tanah"
                              class="w-full px-4 py-2.5 rounded-xl
                                     border border-slate-200
                                     text-sm text-slate-700
                                     focus:outline-none
                                     focus:ring-2 focus:ring-emerald-100
                                     focus:border-emerald-400">{{ old('deskripsi_objek') }}</textarea>
                </div>

            </div>
        </div>


        {{-- =====================================================
             2. DATA TANAH
        ====================================================== --}}
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm">

            <div class="px-5 py-4 border-b border-slate-200">
                <h2 class="text-base font-bold text-slate-800">
                    Data Tanah & Sertifikat
                </h2>

                <p class="text-xs text-slate-500 mt-1">
                    Informasi fisik dan legalitas tanah.
                </p>
            </div>

            <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-5">

                {{-- LUAS --}}
                <div>
                    <label for="luas_tanah"
                           class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Luas
                    </label>

                    <div class="relative">
                        <input type="number"
                               id="luas_tanah"
                               name="luas_tanah"
                               value="{{ old('luas_tanah') }}"
                               min="0"
                               step="0.01"
                               placeholder="Masukkan luas tanah"
                               class="w-full px-4 pr-16 py-2.5 rounded-xl
                                      border border-slate-200
                                      text-sm text-slate-700
                                      focus:outline-none
                                      focus:ring-2 focus:ring-emerald-100
                                      focus:border-emerald-400">

                        <span class="absolute right-4 top-1/2 -translate-y-1/2
                                     text-xs text-slate-400">
                            m²
                        </span>
                    </div>
                </div>


                {{-- STATUS HAK --}}
                <div>
                    <label for="status_hak"
                           class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Status Hak
                    </label>

                    <input type="text"
                           id="status_hak"
                           name="status_hak"
                           value="{{ old('status_hak') }}"
                           placeholder="Contoh: Hak Pakai / Hak Guna Bangunan"
                           class="w-full px-4 py-2.5 rounded-xl
                                  border border-slate-200
                                  text-sm text-slate-700
                                  focus:outline-none
                                  focus:ring-2 focus:ring-emerald-100
                                  focus:border-emerald-400">
                </div>


                {{-- ALAMAT --}}
                <div class="md:col-span-2">
                    <label for="alamat"
                           class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Alamat
                    </label>

                    <textarea id="alamat"
                              name="alamat"
                              rows="3"
                              placeholder="Masukkan alamat lengkap tanah"
                              class="w-full px-4 py-2.5 rounded-xl
                                     border border-slate-200
                                     text-sm text-slate-700
                                     focus:outline-none
                                     focus:ring-2 focus:ring-emerald-100
                                     focus:border-emerald-400">{{ old('alamat') }}</textarea>
                </div>


                {{-- KETKEL --}}
                <div>
                    <label for="ketkel"
                           class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Ketkel
                    </label>

                    <input type="text"
                           id="ketkel"
                           name="ketkel"
                           value="{{ old('ketkel') }}"
                           placeholder="Kelurahan / Desa"
                           class="w-full px-4 py-2.5 rounded-xl
                                  border border-slate-200
                                  text-sm text-slate-700
                                  focus:outline-none
                                  focus:ring-2 focus:ring-emerald-100
                                  focus:border-emerald-400">
                </div>


                {{-- NOMOR SERTIFIKAT --}}
                <div>
                    <label for="nomor_sertifikat"
                           class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Nomor Sertifikat
                    </label>

                    <input type="text"
                           id="nomor_sertifikat"
                           name="nomor_sertifikat"
                           value="{{ old('nomor_sertifikat') }}"
                           placeholder="Masukkan nomor sertifikat"
                           class="w-full px-4 py-2.5 rounded-xl
                                  border border-slate-200
                                  text-sm text-slate-700
                                  focus:outline-none
                                  focus:ring-2 focus:ring-emerald-100
                                  focus:border-emerald-400">
                </div>


                {{-- TANGGAL SERTIFIKAT --}}
                <div>
                    <label for="tanggal_sertifikat"
                           class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Tanggal Sertifikat
                    </label>

                    <input type="date"
                           id="tanggal_sertifikat"
                           name="tanggal_sertifikat"
                           value="{{ old('tanggal_sertifikat') }}"
                           class="w-full px-4 py-2.5 rounded-xl
                                  border border-slate-200
                                  text-sm text-slate-700
                                  focus:outline-none
                                  focus:ring-2 focus:ring-emerald-100
                                  focus:border-emerald-400">
                </div>

            </div>
        </div>


        {{-- =====================================================
             3. PENGGUNAAN & KONDISI
        ====================================================== --}}
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm">

            <div class="px-5 py-4 border-b border-slate-200">
                <h2 class="text-base font-bold text-slate-800">
                    Penggunaan & Kondisi
                </h2>

                <p class="text-xs text-slate-500 mt-1">
                    Informasi penggunaan dan kondisi aset tanah.
                </p>
            </div>

            <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-5">

                {{-- PENGGUNAAN --}}
                <div>
                    <label for="penggunaan"
                           class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Penggunaan
                    </label>

                    <input type="text"
                           id="penggunaan"
                           name="penggunaan"
                           value="{{ old('penggunaan') }}"
                           placeholder="Contoh: Kebun / Kantor / Lahan Pertanian"
                           class="w-full px-4 py-2.5 rounded-xl
                                  border border-slate-200
                                  text-sm text-slate-700
                                  focus:outline-none
                                  focus:ring-2 focus:ring-emerald-100
                                  focus:border-emerald-400">
                </div>


                {{-- PENGGUNAAN AIR --}}
                <div>
                    <label for="penggunaan_air"
                           class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Penggunaan Air
                    </label>

                    <select id="penggunaan_air"
                            name="penggunaan_air"
                            class="w-full px-4 py-2.5 rounded-xl
                                   border border-slate-200
                                   bg-white
                                   text-sm text-slate-700
                                   focus:outline-none
                                   focus:ring-2 focus:ring-emerald-100
                                   focus:border-emerald-400">

                        <option value="">-- Pilih Penggunaan Air --</option>

                        <option value="Mata Air"
                            @selected(old('penggunaan_air') === 'Mata Air')>
                            Mata Air
                        </option>

                        <option value="Air Tanah"
                            @selected(old('penggunaan_air') === 'Air Tanah')>
                            Air Tanah
                        </option>

                        <option value="Air Sungai"
                            @selected(old('penggunaan_air') === 'Air Sungai')>
                            Air Sungai
                        </option>

                        <option value="Air Lainnya"
                            @selected(old('penggunaan_air') === 'Air Lainnya')>
                            Air Lainnya
                        </option>

                    </select>
                </div>


                {{-- KONDISI --}}
                <div>
                    <label for="kondisi"
                           class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Kondisi
                    </label>

                    <input type="text"
                           id="kondisi"
                           name="kondisi"
                           value="{{ old('kondisi') }}"
                           placeholder="Masukkan kondisi aset"
                           class="w-full px-4 py-2.5 rounded-xl
                                  border border-slate-200
                                  text-sm text-slate-700
                                  focus:outline-none
                                  focus:ring-2 focus:ring-emerald-100
                                  focus:border-emerald-400">
                </div>


                {{-- KETERANGAN --}}
                <div>
                    <label for="keterangan"
                           class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Keterangan
                    </label>

                    <textarea id="keterangan"
                              name="keterangan"
                              rows="3"
                              placeholder="Keterangan tambahan mengenai tanah"
                              class="w-full px-4 py-2.5 rounded-xl
                                     border border-slate-200
                                     text-sm text-slate-700
                                     focus:outline-none
                                     focus:ring-2 focus:ring-emerald-100
                                     focus:border-emerald-400">{{ old('keterangan') }}</textarea>
                </div>

            </div>
        </div>


        {{-- =====================================================
             4. PETUGAS & LOKASI
        ====================================================== --}}
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm">

            <div class="px-5 py-4 border-b border-slate-200">
                <h2 class="text-base font-bold text-slate-800">
                    Petugas & Lokasi
                </h2>

                <p class="text-xs text-slate-500 mt-1">
                    Informasi petugas dan lokasi aset.
                </p>
            </div>

            <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-5">

                {{-- NAMA PETUGAS --}}
                <div>
                    <label for="nama_petugas"
                           class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Nama Petugas
                    </label>

                    <input type="text"
                           id="nama_petugas"
                           name="nama_petugas"
                           value="{{ old('nama_petugas') }}"
                           placeholder="Masukkan nama petugas"
                           class="w-full px-4 py-2.5 rounded-xl
                                  border border-slate-200
                                  text-sm text-slate-700
                                  focus:outline-none
                                  focus:ring-2 focus:ring-emerald-100
                                  focus:border-emerald-400">
                </div>


                {{-- NOMOR TELEPON --}}
                <div>
                    <label for="nomor_hp_petugas"
                           class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Nomor Tlp Petugas
                    </label>

                    <input type="text"
                           id="nomor_hp_petugas"
                           name="nomor_hp_petugas"
                           value="{{ old('nomor_hp_petugas') }}"
                           placeholder="Contoh: 081234567890"
                           class="w-full px-4 py-2.5 rounded-xl
                                  border border-slate-200
                                  text-sm text-slate-700
                                  focus:outline-none
                                  focus:ring-2 focus:ring-emerald-100
                                  focus:border-emerald-400">
                </div>


                {{-- GOOGLE MAPS --}}
                <div class="md:col-span-2">
                    <label for="google_maps"
                           class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Google Maps
                    </label>

                    <input type="url"
                           id="google_maps"
                           name="google_maps"
                           value="{{ old('google_maps') }}"
                           placeholder="https://maps.google.com/..."
                           class="w-full px-4 py-2.5 rounded-xl
                                  border border-slate-200
                                  text-sm text-slate-700
                                  focus:outline-none
                                  focus:ring-2 focus:ring-emerald-100
                                  focus:border-emerald-400">

                    <p class="text-xs text-slate-400 mt-1">
                        Masukkan link lokasi Google Maps aset.
                    </p>
                </div>

            </div>
        </div>


        {{-- =====================================================
             5. MEDIA
        ====================================================== --}}
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm">

            <div class="px-5 py-4 border-b border-slate-200">
                <h2 class="text-base font-bold text-slate-800">
                    Media Aset
                </h2>

                <p class="text-xs text-slate-500 mt-1">
                    Referensi foto dan video aset tanah.
                </p>
            </div>

            <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-5">

                {{-- FOTO --}}
                <div>
                    <label for="foto_tanah"
                           class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Foto Tanah
                    </label>

                    <input type="text"
                           id="foto_tanah"
                           name="foto_tanah"
                           value="{{ old('foto_tanah') }}"
                           placeholder="Nama/path file foto"
                           class="w-full px-4 py-2.5 rounded-xl
                                  border border-slate-200
                                  text-sm text-slate-700
                                  focus:outline-none
                                  focus:ring-2 focus:ring-emerald-100
                                  focus:border-emerald-400">

                    <p class="text-xs text-slate-400 mt-1">
                        Upload file akan kita integrasikan pada tahap berikutnya.
                    </p>
                </div>


                {{-- VIDEO --}}
                <div>
                    <label for="video_tanah"
                           class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Video Tanah
                    </label>

                    <input type="text"
                           id="video_tanah"
                           name="video_tanah"
                           value="{{ old('video_tanah') }}"
                           placeholder="Nama/path file video"
                           class="w-full px-4 py-2.5 rounded-xl
                                  border border-slate-200
                                  text-sm text-slate-700
                                  focus:outline-none
                                  focus:ring-2 focus:ring-emerald-100
                                  focus:border-emerald-400">

                    <p class="text-xs text-slate-400 mt-1">
                        Upload file akan kita integrasikan pada tahap berikutnya.
                    </p>
                </div>

            </div>
        </div>


        {{-- =====================================================
             ACTION
        ====================================================== --}}
        <div class="flex items-center justify-end gap-3">

            <a href="{{ route('tanah.index') }}"
               class="px-5 py-2.5 rounded-xl
                      border border-slate-200
                      text-sm font-semibold text-slate-600
                      hover:bg-slate-50 transition">
                Batal
            </a>

            <button type="submit"
                    class="inline-flex items-center gap-2
                           px-5 py-2.5 rounded-xl
                           bg-emerald-500 hover:bg-emerald-600
                           text-white text-sm font-semibold
                           shadow-sm transition">

                <svg xmlns="http://www.w3.org/2000/svg"
                     class="w-4 h-4"
                     fill="none"
                     viewBox="0 0 24 24"
                     stroke="currentColor">
                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M5 13l4 4L19 7"/>
                </svg>

                Simpan Data
            </button>

        </div>

    </form>

</div>
@endsection