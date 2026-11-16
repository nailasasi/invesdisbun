@php
    $mode = $mode ?? 'create';
    $prefix = $prefix ?? ($mode === 'create' ? 'tambah_' : 'edit_');
    $pegawai = $pegawai ?? null;
    $skpdList = $skpdList ?? collect();
    $ruanganList = $ruanganList ?? collect();
    $lokasiDinas = $lokasiDinas ?? null;

    $oldSkpd = old('id_skpd', $pegawai->id_skpd ?? '');
    $oldRuangan = old('id_ruangan', $pegawai->id_ruangan ?? '');
    $lokasiAwal = \App\Models\Lokasi::untukSkpd($oldSkpd !== '' && $oldSkpd !== null ? (int) $oldSkpd : null)?->nama_lokasi ?? '';
@endphp

<div class="space-y-3.5">
    {{-- Kotak Error Umum (AJAX) --}}
    <div id="{{ $prefix }}general-error" class="hidden rounded-xl border border-red-200 bg-red-50 p-3 text-xs font-medium text-red-700"></div>

    {{-- Field 1: NIP (Vertikal, Wajib) --}}
    <div class="space-y-1">
        <label for="{{ $prefix }}nip" class="block text-xs font-semibold uppercase tracking-wider text-slate-600">
            NIP <span class="text-red-500">*</span>
        </label>
        <input
            type="text"
            id="{{ $prefix }}nip"
            name="nip"
            required
            maxlength="30"
            value="{{ old('nip', $pegawai->nip ?? '') }}"
            placeholder="contoh: 198001012010011001"
            autocomplete="off"
            class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-xs focus:border-disbun-600 focus:outline-none focus:ring-2 focus:ring-disbun-400 transition"
        >
        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="nip"></p>
        @error('nip')<p class="text-xs font-medium text-red-600">{{ $message }}</p>@enderror
        <p class="text-[11px] text-slate-400">NIP dipakai sebagai identitas unik pegawai serta akun login.</p>
    </div>

    {{-- Field 2: Nama Pegawai (Vertikal, Wajib) --}}
    <div class="space-y-1">
        <label for="{{ $prefix }}nama_pegawai" class="block text-xs font-semibold uppercase tracking-wider text-slate-600">
            Nama Pegawai <span class="text-red-500">*</span>
        </label>
        <input
            type="text"
            id="{{ $prefix }}nama_pegawai"
            name="nama_pegawai"
            required
            maxlength="100"
            value="{{ old('nama_pegawai', $pegawai->nama_pegawai ?? '') }}"
            placeholder="contoh: Drs. Budi Santoso"
            autocomplete="off"
            class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-xs focus:border-disbun-600 focus:outline-none focus:ring-2 focus:ring-disbun-400 transition"
        >
        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="nama_pegawai"></p>
        @error('nama_pegawai')<p class="text-xs font-medium text-red-600">{{ $message }}</p>@enderror
    </div>

    {{-- Field 3: Jabatan (Vertikal, Opsional) --}}
    <div class="space-y-1">
        <label for="{{ $prefix }}jabatan" class="block text-xs font-semibold uppercase tracking-wider text-slate-600">
            Jabatan
        </label>
        <input
            type="text"
            id="{{ $prefix }}jabatan"
            name="jabatan"
            maxlength="100"
            value="{{ old('jabatan', $pegawai->jabatan ?? '') }}"
            placeholder="contoh: Kepala Seksi / Staf"
            autocomplete="off"
            class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-xs focus:border-disbun-600 focus:outline-none focus:ring-2 focus:ring-disbun-400 transition"
        >
        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="jabatan"></p>
        @error('jabatan')<p class="text-xs font-medium text-red-600">{{ $message }}</p>@enderror
    </div>

    {{-- Field 4: SKPD (Vertikal, Wajib) --}}
    <div class="space-y-1">
        <label for="{{ $prefix }}id_skpd" class="block text-xs font-semibold uppercase tracking-wider text-slate-600">
            SKPD <span class="text-red-500">*</span>
        </label>
        <select
            id="{{ $prefix }}id_skpd"
            name="id_skpd"
            required
            class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-xs focus:border-disbun-600 focus:outline-none focus:ring-2 focus:ring-disbun-400 transition"
        >
            <option value="" disabled @selected((string) $oldSkpd === '')>-- Pilih SKPD --</option>
            @foreach ($skpdList as $skpd)
                @php
                    $lokasiSkpd = $skpd->isUpt() ? $skpd->lokasi : $lokasiDinas;
                @endphp
                <option
                    value="{{ $skpd->id_skpd }}"
                    data-lokasi-id="{{ $lokasiSkpd?->id_lokasi ?? '' }}"
                    data-lokasi-nama="{{ $lokasiSkpd?->nama_lokasi ?? '' }}"
                    @selected((string) $oldSkpd === (string) $skpd->id_skpd)
                >
                    {{ $skpd->nama_skpd }} ({{ $skpd->jenis_skpd }})
                </option>
            @endforeach
        </select>
        <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="id_skpd"></p>
        @error('id_skpd')<p class="text-xs font-medium text-red-600">{{ $message }}</p>@enderror
    </div>

    {{-- Field 5 & 6: Lokasi Kerja (Otomatis) & Ruangan Kerja (Wajib) berdampingan di sm+, vertikal di mobile --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div class="space-y-1">
            <label for="{{ $prefix }}lokasi" class="block text-xs font-semibold uppercase tracking-wider text-slate-600">
                Lokasi Kerja
            </label>
            <input
                type="text"
                id="{{ $prefix }}lokasi"
                readonly
                value="{{ $lokasiAwal }}"
                placeholder="-- Otomatis dari SKPD --"
                class="block w-full cursor-not-allowed rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2 text-sm text-slate-600 shadow-xs focus:outline-none transition"
            >
            <p class="text-[11px] text-slate-400">Turunan otomatis dari SKPD.</p>
        </div>

        <div class="space-y-1">
            <label for="{{ $prefix }}id_ruangan" class="block text-xs font-semibold uppercase tracking-wider text-slate-600">
                Ruangan Kerja <span class="text-red-500">*</span>
            </label>
            <select
                id="{{ $prefix }}id_ruangan"
                name="id_ruangan"
                required
                class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 shadow-xs focus:border-disbun-600 focus:outline-none focus:ring-2 focus:ring-disbun-400 transition"
            >
                <option value="" disabled @selected((string) $oldRuangan === '')>-- Pilih Ruangan --</option>
                @foreach ($ruanganList as $ruangan)
                    <option
                        value="{{ $ruangan->id_ruangan }}"
                        data-lokasi="{{ $ruangan->id_lokasi ?? '' }}"
                        @if ($ruangan->id_skpd === null) data-shared="1" @endif
                        @selected((string) $oldRuangan === (string) $ruangan->id_ruangan)
                    >
                        {{ $ruangan->nama_ruangan }}
                    </option>
                @endforeach
            </select>
            <p class="field-error hidden text-xs font-medium text-red-600" data-error-for="id_ruangan"></p>
            @error('id_ruangan')<p class="text-xs font-medium text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>
</div>

