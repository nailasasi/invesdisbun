@php
    $mode = $mode ?? (isset($retribusi) ? 'edit' : 'create');
    $statusTerpilih = old('status_pemanfaatan', $retribusi->status_pemanfaatan ?? null);
    $statusOpsi = ['Pakai', 'Idle', 'Rencana Disewakan', 'Disewakan'];
    $satuanTerpilih = old('satuan', $retribusi->satuan ?? null);
    $satuanOpsi = ['per titik/tahun', 'per hektar/tahun', 'per meter/tahun', 'lainnya'];
@endphp

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
                   value="{{ old('tahun', $retribusi->tahun ?? date('Y')) }}"
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

                @foreach ($statusOpsi as $opsi)
                    <option value="{{ $opsi }}"
                        @selected($statusTerpilih === $opsi)>
                        {{ $opsi }}
                    </option>
                @endforeach

                {{-- Pertahankan nilai lama yang belum ada di daftar opsi standar. --}}
                @if ($statusTerpilih && ! in_array($statusTerpilih, $statusOpsi, true))
                    <option value="{{ $statusTerpilih }}" selected>
                        {{ $statusTerpilih }}
                    </option>
                @endif

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
                   value="{{ old('biaya_pengurusan', $retribusi->biaya_pengurusan ?? '') }}"
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
                   value="{{ old('PAD', $retribusi->PAD ?? '') }}"
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
                   value="{{ old('tarif_retribusi', $retribusi->tarif_retribusi ?? '') }}"
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
                   value="{{ old('total_tarif_sewa', $retribusi->total_tarif_sewa ?? '') }}"
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

                @foreach ($satuanOpsi as $opsi)
                    <option value="{{ $opsi }}"
                        @selected($satuanTerpilih === $opsi)>
                        {{ $opsi === 'lainnya' ? 'Lainnya' : $opsi }}
                    </option>
                @endforeach

                {{-- Pertahankan nilai lama yang belum ada di daftar opsi standar. --}}
                @if ($satuanTerpilih && ! in_array($satuanTerpilih, $satuanOpsi, true))
                    <option value="{{ $satuanTerpilih }}" selected>
                        {{ $satuanTerpilih }}
                    </option>
                @endif
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
                             focus:ring-slate-500">{{ old('keterangan', $retribusi->keterangan ?? '') }}</textarea>

            @error('keterangan')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

    </div>
</div>

@if (($mode ?? 'create') === 'edit')
    {{-- Konfirmasi Otorisasi (Hanya saat Edit) --}}
    <div class="overflow-hidden rounded-xl border border-amber-200 bg-amber-50/40 shadow-sm">
        <div class="border-b border-amber-200 px-6 py-4">
            <h2 class="font-semibold text-slate-900">
                Konfirmasi Otorisasi
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Demi keamanan, masukkan ulang password akun Anda untuk menyimpan perubahan ini.
            </p>
        </div>

        <div class="p-6">
            <label for="password_otorisasi"
                   class="mb-2 block text-sm font-medium text-slate-700">
                Password Otorisasi (Wajib) <span class="text-red-500">*</span>
            </label>

            {{-- Tidak memakai old(): password tidak pernah diisi ulang. --}}
            <input type="password"
                   id="password_otorisasi"
                   name="password_otorisasi"
                   required
                   autocomplete="current-password"
                   placeholder="Masukkan ulang password akun Anda"
                   class="w-full max-w-md rounded-lg border border-slate-300 px-3 py-2.5
                          text-sm outline-none focus:border-slate-500 focus:ring-1
                          focus:ring-slate-500">

            @error('password_otorisasi')
                <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>
            @enderror

            {{-- Jam real-time + kamera bukti otorisasi (wajib sebelum simpan) --}}
            <x-otorisasi-bukti />
        </div>
    </div>
@endif

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
        {{ ($mode ?? 'create') === 'edit' ? 'Simpan Perubahan' : 'Simpan Retribusi' }}
    </button>

</div>

