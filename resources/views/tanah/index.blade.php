@extends('layouts.app')

@section('title', 'Inventaris Tanah')

@section('content')
<div class="p-6">

    {{-- =========================================================
         HEADER
    ========================================================== --}}
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
                        Inventaris Tanah
                    </h1>

                    <p class="text-sm text-slate-500 mt-0.5">
                        Data inventaris tanah / KIB A milik instansi
                    </p>
                </div>
            </div>
        </div>


        {{-- TOMBOL TAMBAH --}}
        @if(in_array(auth()->user()?->role?->nama_role, ['Admin Aset', 'Admin UPT P2DP']))
            <a href="{{ route('tanah.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2.5
                      bg-emerald-500 hover:bg-emerald-600
                      text-white text-sm font-semibold
                      rounded-xl shadow-sm transition">

                <svg xmlns="http://www.w3.org/2000/svg"
                     class="w-4 h-4"
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

    </div>


    {{-- =========================================================
         SEARCH CARD
    ========================================================== --}}
    <div class="bg-white border border-slate-200 rounded-2xl
                shadow-sm p-4 mb-5">

        <form method="GET" action="{{ route('tanah.index') }}"
              class="flex items-center gap-3">

            <div class="relative flex-1">

                <svg xmlns="http://www.w3.org/2000/svg"
                     class="absolute left-4 top-1/2 -translate-y-1/2
                            w-5 h-5 text-slate-400"
                     fill="none"
                     viewBox="0 0 24 24"
                     stroke="currentColor">
                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"/>
                </svg>

                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Cari KIB, deskripsi objek, alamat, nomor sertifikat..."
                    class="w-full pl-11 pr-4 py-2.5
                           border border-slate-200 rounded-xl
                           text-sm text-slate-700
                           placeholder:text-slate-400
                           focus:outline-none
                           focus:ring-2 focus:ring-emerald-100
                           focus:border-emerald-400"
                >
            </div>


            <button type="submit"
                    class="px-5 py-2.5 rounded-xl
                           bg-emerald-500 hover:bg-emerald-600
                           text-white text-sm font-semibold
                           transition">
                Cari
            </button>


            @if(request('search'))
                <a href="{{ route('tanah.index') }}"
                   class="px-4 py-2.5 rounded-xl
                          border border-slate-200
                          text-slate-600 text-sm font-medium
                          hover:bg-slate-50 transition">
                    Reset
                </a>
            @endif

        </form>

    </div>


    {{-- =========================================================
         DATA CARD
    ========================================================== --}}
    <div class="bg-white border border-slate-200 rounded-2xl
                shadow-sm overflow-hidden">


        {{-- CARD HEADER --}}
        <div class="px-5 py-4 border-b border-slate-200">

            <div class="flex items-center justify-between">

                <div>
                    <h2 class="text-base font-bold text-slate-800">
                        Data KIB A — Tanah
                    </h2>

                </div>


                <div class="text-sm text-slate-500">
                    <span class="font-semibold text-slate-800">
                        {{ $tanahList->total() }}
                    </span>
                    data
                </div>

            </div>


            {{-- GROUP INFORMATION --}}
            <div class="flex flex-wrap items-center gap-2 mt-4">

                <span class="inline-flex items-center gap-2
                             px-3 py-1.5 rounded-lg
                             bg-emerald-50 text-emerald-700
                             text-xs font-semibold">

                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
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


                <span class="ml-auto text-xs text-slate-400">
                    ← Geser ke kanan untuk melihat seluruh data →
                </span>

            </div>

        </div>


        {{-- =====================================================
             TABLE
        ====================================================== --}}
        <div class="overflow-x-auto">

            <table class="min-w-[1100px] w-full text-xs">

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
                    Target Retribusi
                </th>


                <th class="px-4 py-3 text-center font-bold">
                    Media Aset
                </th>


                <th class="px-4 py-3 text-center font-bold">
                    Arsip PBB 2026
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


                            <td class="px-4 py-4">
                            {{ $tanahList->firstItem()+$index }}
                            </td>


                            <td class="px-4 py-4">

                            <div class="font-bold">
                            {{ $tanah->kib ?? '-' }}
                            </div>

                            <div class="text-xs text-slate-500">
                            No Excel :
                            {{ $tanah->no_excel ?? '-' }}
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

                            Rp {{ number_format(
                            $tanah->retribusi_sum_PAD ?? 0,
                            0,
                            ',',
                            '.'
                            )}}

                            </td>



                            <td class="px-4 py-4 text-right">

                            Rp {{ number_format(
                            $tanah->retribusi_sum_total_tarif_sewa ?? 0,
                            0,
                            ',',
                            '.'
                            )}}

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




                            <td class="px-4 py-4 text-center">


                            @if($tanah->dokumenPbb->count())

                            <a href="{{asset(
                            'storage/'.$tanah->dokumenPbb->first()->file_pbb
                            )}}"
                            target="_blank"
                            class="text-blue-600">

                            Lihat

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

                            <a href="{{ route('tanah.show',$tanah) }}"
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

                            @if(in_array(auth()->user()?->role?->nama_role, ['Admin Aset', 'Admin UPT P2DP']))

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

                            <td colspan="26"
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

    </div>

</div>
@endsection