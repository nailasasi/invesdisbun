@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-50 py-8">
    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">

        {{-- Header --}}
        <div class="mb-6">
            <div class="mb-2 flex items-center gap-2 text-sm text-slate-500">
                <a href="{{ route('tanah.show', $tanah) }}"
                   class="hover:text-slate-900">
                    Detail Tanah
                </a>
                <span>/</span>
                <span>Tambah Retribusi</span>
            </div>

            <h1 class="text-2xl font-bold text-slate-900">
                Tambah Retribusi Tanah
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                Tambahkan data retribusi untuk aset
                <strong>{{ $tanah->kib ?? '-' }}</strong>.
            </p>
        </div>

        {{-- Informasi Tanah --}}
        <div class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                Aset Tanah
            </p>

            <div class="mt-2">
                <p class="font-semibold text-slate-900">
                    {{ $tanah->kib ?? '-' }}
                </p>

                <p class="mt-1 text-sm text-slate-500">
                    {{ $tanah->deskripsi_objek ?? '-' }}
                </p>
            </div>
        </div>

        {{-- Form --}}
        <form action="{{ route('tanah.retribusi.store', $tanah) }}"
              method="POST"
              class="space-y-6">

            @csrf

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

                <div class="border-b border-slate-200 px-6 py-4">
                    <h2 class="font-semibold text-slate-900">
                        Data Retribusi
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Sesuaikan data dengan pencatatan KIB A.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-5 p-6 md:grid-cols-2">

                    {{-- Tahun --}}
                    <div>
                        <label for="tahun"
                               class="mb-2 block text-sm font-medium text-slate-700">
                            Tahun
                        </label>

                        <input type="number"
                               id="tahun"
                               name="tahun"
                               value="{{ old('tahun', date('Y')) }}"
                               min="2000"
                               max="2100"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5
                                      text-sm outline-none focus:border-slate-500 focus:ring-1
                                      focus:ring-slate-500">

                        @error('tahun')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Status Pemanfaatan --}}
                    <div>
                        <label for="status_pemanfaatan"
                            class="mb-2 block text-sm font-medium text-slate-700">
                            Status Pemanfaatan
                        </label>

                        <select id="status_pemanfaatan"
                                name="status_pemanfaatan"
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5
                                    text-sm outline-none focus:border-slate-500 focus:ring-1
                                    focus:ring-slate-500">

                            <option value="">
                                Pilih Status
                            </option>

                            <option value="Pakai"
                                {{ old('status_pemanfaatan') == 'Pakai' ? 'selected' : '' }}>
                                Pakai
                            </option>

                            <option value="Idle"
                                {{ old('status_pemanfaatan') == 'Idle' ? 'selected' : '' }}>
                                Idle
                            </option>

                            <option value="Rencana Disewakan"
                                {{ old('status_pemanfaatan') == 'Rencana Disewakan' ? 'selected' : '' }}>
                                Rencana Disewakan
                            </option>

                            <option value="Disewakan"
                                {{ old('status_pemanfaatan') == 'Disewakan' ? 'selected' : '' }}>
                                Disewakan
                            </option>

                        </select>


                        @error('status_pemanfaatan')
                            <p class="mt-1 text-xs text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Biaya Pengurusan --}}
                    <div>
                        <label for="biaya_pengurusan"
                               class="mb-2 block text-sm font-medium text-slate-700">
                            Biaya Pengurusan Kebun / 1 Tahun
                        </label>

                        <input type="number"
                               id="biaya_pengurusan"
                               name="biaya_pengurusan"
                               value="{{ old('biaya_pengurusan') }}"
                               min="0"
                               step="0.01"
                               placeholder="Contoh: 500000"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5
                                      text-sm outline-none focus:border-slate-500 focus:ring-1
                                      focus:ring-slate-500">

                        @error('biaya_pengurusan')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- PAD --}}
                    <div>
                        <label for="PAD"
                               class="mb-2 block text-sm font-medium text-slate-700">
                            Penerimaan PAD / 1 Tahun
                        </label>

                        <input type="number"
                               id="PAD"
                               name="PAD"
                               value="{{ old('PAD') }}"
                               min="0"
                               step="0.01"
                               placeholder="Contoh: 1000000"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5
                                      text-sm outline-none focus:border-slate-500 focus:ring-1
                                      focus:ring-slate-500">

                        @error('PAD')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Tarif Retribusi --}}
                    <div>
                        <label for="tarif_retribusi"
                               class="mb-2 block text-sm font-medium text-slate-700">
                            Tarif Retribusi
                        </label>

                        <input type="number"
                               id="tarif_retribusi"
                               name="tarif_retribusi"
                               value="{{ old('tarif_retribusi') }}"
                               min="0"
                               step="0.01"
                               placeholder="Contoh: 250000"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5
                                      text-sm outline-none focus:border-slate-500 focus:ring-1
                                      focus:ring-slate-500">

                        @error('tarif_retribusi')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Total Tarif --}}
                    <div>
                        <label for="total_tarif_sewa"
                               class="mb-2 block text-sm font-medium text-slate-700">
                            Total Tarif Jika Disewakan
                        </label>

                        <input type="number"
                               id="total_tarif_sewa"
                               name="total_tarif_sewa"
                               value="{{ old('total_tarif_sewa') }}"
                               min="0"
                               step="0.01"
                               placeholder="Contoh: 5000000"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5
                                      text-sm outline-none focus:border-slate-500 focus:ring-1
                                      focus:ring-slate-500">

                        @error('total_tarif_sewa')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Satuan --}}
                    <div>
                        <label for="satuan"
                               class="mb-2 block text-sm font-medium text-slate-700">
                            Satuan
                        </label>

                        <select id="satuan"
                                name="satuan"
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5
                                       text-sm outline-none focus:border-slate-500 focus:ring-1
                                       focus:ring-slate-500">

                            <option value="">Pilih satuan</option>

                            <option value="per titik/tahun"
                                {{ old('satuan') === 'per titik/tahun' ? 'selected' : '' }}>
                                per titik/tahun
                            </option>

                            <option value="per hektar/tahun"
                                {{ old('satuan') === 'per hektar/tahun' ? 'selected' : '' }}>
                                per hektar/tahun
                            </option>

                            <option value="per meter/tahun"
                                {{ old('satuan') === 'per meter/tahun' ? 'selected' : '' }}>
                                per meter/tahun
                            </option>

                            <option value="lainnya"
                                {{ old('satuan') === 'lainnya' ? 'selected' : '' }}>
                                Lainnya
                            </option>
                        </select>

                        @error('satuan')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Keterangan --}}
                    <div class="md:col-span-2">
                        <label for="keterangan"
                               class="mb-2 block text-sm font-medium text-slate-700">
                            Keterangan
                        </label>

                        <textarea id="keterangan"
                                  name="keterangan"
                                  rows="4"
                                  placeholder="Tambahkan keterangan retribusi jika diperlukan..."
                                  class="w-full rounded-lg border border-slate-300 px-3 py-2.5
                                         text-sm outline-none focus:border-slate-500 focus:ring-1
                                         focus:ring-slate-500">{{ old('keterangan') }}</textarea>

                        @error('keterangan')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                </div>
            </div>

            {{-- Tombol --}}
            <div class="flex items-center justify-end gap-3">

                <a href="{{ route('tanah.show', $tanah) }}"
                   class="rounded-lg border border-slate-300 bg-white px-5 py-2.5
                          text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">
                    Batal
                </a>

                <button type="submit"
                        class="rounded-lg bg-slate-900 px-5 py-2.5 text-sm
                               font-medium text-white shadow-sm hover:bg-slate-800">
                    Simpan Retribusi
                </button>

            </div>

        </form>

    </div>
</div>
@endsection