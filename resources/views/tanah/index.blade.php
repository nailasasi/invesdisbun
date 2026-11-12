@extends('layouts.app')

@section('title', 'Inventaris Tanah')

@section('page-title', 'Tanah KIB A')

@section('content')
@php
    // Parameter filter tahun yang aktif, untuk diteruskan ke tautan detail.
    $filterParams = array_filter([
        'tahun' => request('tahun'),
    ], fn ($v) => $v !== null && $v !== '');
@endphp
<div class="p-6">

    {{-- =========================================================
         LAPORAN HASIL IMPORT
    ========================================================== --}}
    @if(session('success') || session('error') || session('import_errors'))
        <div class="mb-5 space-y-3">
            @if(session('success'))
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-800">
                    {{ session('error') }}
                </div>
            @endif

            @if(session('import_errors'))
                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900">
                    <p class="mb-1 font-bold">Baris yang tidak dapat diproses:</p>
                    <ul class="list-disc space-y-0.5 pl-5">
                        @foreach(session('import_errors') as $err)
                            <li>Baris {{ $err['row'] }}: {{ implode(' ', $err['messages']) }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif

    {{-- =========================================================
         CARD BENTO + ACTION BAR
    ========================================================== --}}
    <x-card :padding="false">

        <x-action-bar>
            <form method="GET" action="{{ route('tanah.index') }}"
                  class="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:items-center">

                <div class="relative flex-1 sm:max-w-md">
                    <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"/>
                    </svg>

                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Cari KIB, deskripsi objek, alamat, nomor sertifikat..."
                        class="w-full rounded-2xl border border-slate-200 py-2.5 pl-11 pr-4
                               text-sm text-slate-700 shadow-sm transition
                               placeholder:text-slate-400
                               focus:border-disbun-600 focus:outline-none
                               focus:ring-2 focus:ring-disbun-400/40"
                    >
                </div>

                <div class="flex items-center gap-2">
                    <select name="tahun" onchange="this.form.submit()"
                            title="Filter tahun"
                            class="rounded-2xl border border-slate-200 bg-white py-2.5 pl-4 pr-9
                                   text-sm font-medium text-slate-700 shadow-sm transition
                                   focus:border-disbun-600 focus:outline-none focus:ring-2
                                   focus:ring-disbun-400/40">
                        @foreach ($tahunOptions as $optionTahun)
                            <option value="{{ $optionTahun }}" @selected((int) $tahun === (int) $optionTahun)>{{ $optionTahun }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-2xl
                               bg-disbun-700 px-5 py-2.5
                               text-sm font-bold text-white shadow-sm
                               transition hover:bg-disbun-800">
                    Cari
                </button>

                @if(request('search') || request('tahun') !== null)
                    <a href="{{ route('tanah.index') }}"
                       class="inline-flex items-center rounded-2xl border border-disbun-card-border
                              bg-white px-4 py-2.5
                              text-sm font-semibold text-slate-600 shadow-sm
                              transition hover:bg-slate-50">
                        Reset ke Tahun Berjalan
                    </a>
                @endif

            </form>

            <x-slot name="actions">
                @if(in_array(auth()->user()?->role?->nama_role, ['Admin Aset', 'Admin UPT']))
                    <a href="{{ route('tanah.export', request()->query()) }}"
                       class="inline-flex items-center gap-1.5 rounded-2xl border border-disbun-card-border
                              bg-white px-4 py-2.5 text-xs font-bold
                              text-slate-700 shadow-sm transition hover:bg-slate-50">

                        <svg class="h-4 w-4 text-disbun-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>

                        Export Excel
                    </a>

                    <button type="button" onclick="openModalImportTanah()"
                            class="inline-flex items-center gap-1.5 rounded-2xl border border-disbun-card-border
                                   bg-white px-4 py-2.5 text-xs font-bold
                                   text-slate-700 shadow-sm transition hover:bg-slate-50">

                        <svg xmlns="http://www.w3.org/2000/svg"
                             class="h-4 w-4 text-disbun-700"
                             fill="none"
                             viewBox="0 0 24 24"
                             stroke="currentColor">
                            <path stroke-linecap="round"
                                  stroke-linejoin="round"
                                  stroke-width="2"
                                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8-4-4m0 0L8 8m4-4v12"/>
                        </svg>

                        Import Excel
                    </button>

                    <a href="{{ route('tanah.create') }}"
                       class="inline-flex items-center gap-2 rounded-2xl px-4 py-2.5
                              bg-disbun-700 hover:bg-disbun-800
                              text-white text-sm font-bold
                              shadow-sm transition">

                        <svg xmlns="http://www.w3.org/2000/svg"
                             class="h-4 w-4"
                             fill="none"
                             viewBox="0 0 24 24"
                             stroke="currentColor">
                            <path stroke-linecap="round"
                                  stroke-linejoin="round"
                                  stroke-width="2"
                                  d="M12 4v16m8-8H4"/>
                        </svg>

                        Tambah Tanah
                    </a>
                @endif
            </x-slot>
        </x-action-bar>


        {{-- CARD HEADER: total data + kelompok kolom --}}
        <div class="flex flex-wrap items-center gap-2 border-b border-disbun-card-border px-6 py-4">

            <span class="text-sm font-semibold text-slate-800">
                {{ $tanahList->total() }} data
            </span>

            <span class="hidden h-4 w-px bg-slate-200 sm:block"></span>

                <span class="inline-flex items-center gap-2
                             px-3 py-1.5 rounded-lg
                             bg-disbun-50 text-disbun-700
                             border border-disbun-100
                             text-xs font-semibold">

                    <span class="w-2 h-2 rounded-full bg-disbun-500"></span>
                    Data KIB A
                </span>


                <span class="inline-flex items-center gap-2
                             px-3 py-1.5 rounded-lg
                             bg-blue-50 text-blue-700
                             text-xs font-semibold">

                    <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                    Petugas & Lokasi
                </span>


                <span class="inline-flex items-center gap-2
                             px-3 py-1.5 rounded-lg
                             bg-amber-50 text-amber-700
                             text-xs font-semibold">

                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                    Pemanfaatan & Retribusi
                </span>


                <span class="ml-auto text-xs text-slate-400 lg:hidden">
                    ← Geser ke kanan untuk melihat seluruh data →
                </span>

        </div>


        {{-- =====================================================
             TABLE
        ====================================================== --}}
        <div class="overflow-x-auto">

            <table class="w-full min-w-full text-xs">

                {{-- HEADER --}}
                <thead>

                <tr class="bg-slate-50 text-slate-600">


                <th class="px-4 py-3 text-center font-bold">
                    No
                </th>


                <th class="px-4 py-3 text-left font-bold">
                    KIB / No Barang
                </th>


                <th class="px-4 py-3 text-left font-bold">
                    Penggunaan & Alamat
                </th>


                <th class="px-4 py-3 text-left font-bold">
                    Luas / Hak
                </th>


                <th class="px-4 py-3 text-left font-bold">
                    Petugas / PIC
                </th>


                <th class="px-4 py-3 text-right font-bold">
                    Jumlah PAD
                </th>


                <th class="px-4 py-3 text-right font-bold">
                    Tarif Retribusi
                </th>


                <th class="px-4 py-3 text-center font-bold">
                    Media Aset
                </th>


                <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                    Dokumen PBB
                </th>


                <th class="px-4 py-3 text-center font-bold">
                    Gmaps
                </th>


                <th class="px-4 py-3 text-right whitespace-nowrap font-bold">
                    Aksi
                </th>


                </tr>

                </thead>


                {{-- BODY --}}
                <tbody>

                    @forelse($tanahList as $index => $tanah)


                        <tr class="group hover:bg-slate-50">

                        @php
                            // Ringkasan per objek tanah (bukan akumulasi lintas tahun).
                            // Bila tahun dipilih: ambil baris pada tahun itu.
                            // Bila "Semua Tahun": ambil baris dari tahun TERBARU
                            // (tie-break id terbesar) pada masing-masing kolom.
                            $retribusiRingkas = $tanah->retribusi->where('tahun', $tahun)
                                ->sortBy([['tahun', 'desc'], ['id_retribusi', 'desc']])
                                ->first();

                            $pbbRingkas = $tanah->dokumenPbb->where('tahun_pbb', $tahun)
                                ->sortBy([['tahun_pbb', 'desc'], ['id_pbb', 'desc']])
                                ->first();
                        @endphp


                            <td class="px-4 py-4">
                            {{ $tanahList->firstItem()+$index }}
                            </td>


                            <td class="px-4 py-4">

                            <div class="font-bold">
                            {{ $tanah->kib ?? '-' }}
                            </div>

                            </td>



                            <td class="px-4 py-4">

                            <div class="font-semibold">
                            {{ $tanah->penggunaan ?? '-' }}
                            </div>

                            <div class="text-xs text-slate-500">
                            {{ $tanah->alamat ?? '-' }}
                            </div>

                            </td>



                            <td class="px-4 py-4">

                            {{ number_format(
                            $tanah->luas_tanah ?? 0,
                            2,
                            ',',
                            '.'
                            )}} m²


                            <div class="text-xs text-slate-500">
                            {{ $tanah->status_hak ?? '-' }}
                            </div>


                            </td>




                            <td class="px-4 py-4">

                            {{ $tanah->nama_petugas ?? '-' }}

                            <div class="text-xs text-slate-500">

                            {{ $tanah->nomor_hp_petugas ?? '-' }}

                            </div>

                            </td>




                            <td class="px-4 py-4 text-right">

                            @if ($retribusiRingkas && $retribusiRingkas->PAD !== null)
                                Rp {{ number_format((float) $retribusiRingkas->PAD, 0, ',', '.') }}
                            @else
                                -
                            @endif

                            </td>



                            <td class="px-4 py-4 text-right">

                            @if ($retribusiRingkas && $retribusiRingkas->tarif_retribusi !== null)
                                Rp {{ number_format((float) $retribusiRingkas->tarif_retribusi, 0, ',', '.') }}
                            @else
                                -
                            @endif

                            </td>




                            <td class="px-4 py-4 text-center">


                            @if($tanah->foto_tanah || $tanah->video_tanah)

                            <span class="text-green-600">
                            Ada
                            </span>

                            @else

                            -

                            @endif


                            </td>




                            <td class="px-3 py-2.5 text-xs">
                            @if ($pbbRingkas)
                                <a href="{{ asset('storage/' . $pbbRingkas->file_pbb) }}" target="_blank"
                                   class="inline-flex items-center gap-1 rounded-lg bg-disbun-50 px-2 py-1 text-[11px] font-semibold text-disbun-800 transition hover:bg-disbun-100">
                                    <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <span>PBB {{ $pbbRingkas->tahun_pbb ?? '' }}</span>
                                </a>
                            @else
                                -
                            @endif
                        </td>




                            <td class="px-4 py-4 text-center">


                            @if($tanah->google_maps)

                            <a href="{{ $tanah->google_maps }}"
                            target="_blank"
                            class="text-blue-600">

                            Maps

                            </a>

                            @else

                            -

                            @endif


                            </td>



                            <td class="px-4 py-4 text-right whitespace-nowrap">

                            <div class="inline-flex items-center gap-1 rounded-2xl border border-slate-200/70 bg-slate-50/60 p-1 shadow-2xs">

                            <a href="{{ route('tanah.show', ['tanah' => $tanah] + $filterParams) }}"
                               title="Detail Tanah"
                               class="flex h-7 w-7 items-center justify-center rounded-xl
                                      bg-white text-slate-600 transition
                                      hover:bg-slate-100 hover:text-slate-900">

                            <svg xmlns="http://www.w3.org/2000/svg"
                                 class="w-3.5 h-3.5"
                                 fill="none"
                                 viewBox="0 0 24 24"
                                 stroke="currentColor">
                            <path stroke-linecap="round"
                                  stroke-linejoin="round"
                                  stroke-width="2"
                                  d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                            <path stroke-linecap="round"
                                  stroke-linejoin="round"
                                  stroke-width="2"
                                  d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                            </svg>

                            </a>

                            @if(in_array(auth()->user()?->role?->nama_role, ['Admin Aset', 'Admin UPT']))

                            <a href="{{ route('tanah.edit',$tanah) }}"
                               title="Edit Tanah"
                               class="flex h-7 w-7 items-center justify-center rounded-xl
                                      bg-blue-50 text-blue-600 transition
                                      hover:bg-blue-100">

                            <svg xmlns="http://www.w3.org/2000/svg"
                                 class="w-3.5 h-3.5"
                                 fill="none"
                                 viewBox="0 0 24 24"
                                 stroke="currentColor">
                            <path stroke-linecap="round"
                                  stroke-linejoin="round"
                                  stroke-width="2"
                                  d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/>
                            </svg>

                            </a>

                            @endif

                            </div>

                            </td>

                            </tr>

                    @empty

                        <tr>

                            <td colspan="11"
                                class="px-6 py-16 text-center">

                                <div class="flex flex-col items-center">

                                    <div class="w-14 h-14 rounded-2xl
                                                bg-slate-100
                                                flex items-center justify-center
                                                mb-4">

                                        <svg xmlns="http://www.w3.org/2000/svg"
                                             class="w-7 h-7 text-slate-400"
                                             fill="none"
                                             viewBox="0 0 24 24"
                                             stroke="currentColor">
                                            <path stroke-linecap="round"
                                                  stroke-linejoin="round"
                                                  stroke-width="2"
                                                  d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/>
                                        </svg>

                                    </div>

                                    <h3 class="font-semibold text-slate-700">
                                        Belum ada data tanah
                                    </h3>

                                    <p class="text-sm text-slate-400 mt-1">
                                        Data inventaris tanah akan muncul di sini.
                                    </p>

                                </div>

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>


        {{-- =====================================================
             PAGINATION
        ====================================================== --}}
        @if($tanahList->hasPages())

            <div class="px-5 py-4 border-t border-slate-200">
                {{ $tanahList->links() }}
            </div>

        @endif

    </x-card>

</div>

    {{-- Modal Import Tanah Terpisah --}}
    @include('tanah.partials.modal-import')
@endsection
