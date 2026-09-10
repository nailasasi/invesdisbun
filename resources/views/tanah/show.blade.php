@php
    use Illuminate\Support\Facades\Storage;
@endphp

@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-50 py-8">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

        {{-- Header --}}
        <div class="mb-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

                <div>
                    <div class="mb-2 flex items-center gap-2 text-sm text-slate-500">
                        <a href="{{ route('tanah.index') }}"
                           class="transition hover:text-slate-900">
                            Inventaris Tanah
                        </a>
                        <span>/</span>
                        <span>Detail</span>
                    </div>

                    <h1 class="text-2xl font-bold tracking-tight text-slate-900">
                        Detail Inventaris Tanah
                    </h1>

                    <p class="mt-1 text-sm text-slate-500">
                        Informasi lengkap aset tanah berdasarkan KIB A.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('tanah.index') }}"
                       class="inline-flex items-center gap-2 rounded-lg border border-slate-300
                              bg-white px-4 py-2.5 text-sm font-medium text-slate-700
                              shadow-sm transition hover:bg-slate-50">

                        <svg xmlns="http://www.w3.org/2000/svg"
                             class="h-4 w-4"
                             fill="none"
                             viewBox="0 0 24 24"
                             stroke="currentColor"
                             stroke-width="2">
                            <path stroke-linecap="round"
                                  stroke-linejoin="round"
                                  d="M15 19l-7-7 7-7"/>
                        </svg>

                        Kembali
                    </a>

                    @if(in_array(auth()->user()->role?->nama_role ?? '', ['Admin Aset', 'Admin UPT P2DP']))
                        <a href="{{ route('tanah.edit', $tanah) }}"
                           class="inline-flex items-center gap-2 rounded-lg bg-slate-900
                                  px-4 py-2.5 text-sm font-medium text-white shadow-sm
                                  transition hover:bg-slate-800">

                            <svg xmlns="http://www.w3.org/2000/svg"
                                 class="h-4 w-4"
                                 fill="none"
                                 viewBox="0 0 24 24"
                                 stroke="currentColor"
                                 stroke-width="2">
                                <path stroke-linecap="round"
                                      stroke-linejoin="round"
                                      d="M16.862 4.487l1.65-1.65a2.121 2.121 0 013 3l-1.65 1.65M15 6l-9 9v3h3l9-9"/>
                            </svg>

                            Edit Data
                        </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- Identitas singkat --}}
        <div class="mb-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                            KIB A
                        </p>

                        <h2 class="mt-1 text-xl font-bold text-slate-900">
                            {{ $tanah->kib ?? '-' }}
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            {{ $tanah->deskripsi_objek ?? 'Tidak ada deskripsi objek.' }}
                        </p>
                    </div>

                    <div class="rounded-lg bg-slate-100 px-4 py-3">
                        <p class="text-xs text-slate-500">
                            No Excel
                        </p>
                        <p class="mt-1 text-lg font-semibold text-slate-900">
                            {{ $tanah->no_excel ?? '-' }}
                        </p>
                    </div>

                </div>
            </div>
        </div>

        {{-- DATA KIB A --}}
        <div class="mb-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-base font-semibold text-slate-900">
                    Data KIB A
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Informasi dasar pencatatan aset tanah.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-x-8 gap-y-5 p-6 md:grid-cols-2 lg:grid-cols-3">

                <div>
                    <p class="text-xs font-medium text-slate-500">No</p>
                    <p class="mt-1 text-sm font-semibold text-slate-900">
                        {{ $tanah->no_excel ?? '-' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium text-slate-500">KIB</p>
                    <p class="mt-1 text-sm font-semibold text-slate-900">
                        {{ $tanah->kib ?? '-' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium text-slate-500">Tanggal Buku</p>
                    <p class="mt-1 text-sm text-slate-900">
                        {{ $tanah->tanggal_buku?->format('d/m/Y') ?? '-' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium text-slate-500">Tanggal Perolehan</p>
                    <p class="mt-1 text-sm text-slate-900">
                        {{ $tanah->tanggal_perolehan?->format('d/m/Y') ?? '-' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium text-slate-500">Perolehan</p>
                    <p class="mt-1 text-sm font-semibold text-slate-900">
                        Rp {{ number_format($tanah->nilai_perolehan ?? 0, 0, ',', '.') }}
                    </p>
                </div>

                <div class="md:col-span-2 lg:col-span-1">
                    <p class="text-xs font-medium text-slate-500">Deskripsi Objek</p>
                    <p class="mt-1 text-sm text-slate-900">
                        {{ $tanah->deskripsi_objek ?? '-' }}
                    </p>
                </div>

            </div>
        </div>

        {{-- DATA TANAH & SERTIFIKAT --}}
        <div class="mb-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-base font-semibold text-slate-900">
                    Data Tanah & Sertifikat
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Informasi luas, lokasi, dan legalitas tanah.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-x-8 gap-y-5 p-6 md:grid-cols-2 lg:grid-cols-3">

                <div>
                    <p class="text-xs font-medium text-slate-500">Luas</p>
                    <p class="mt-1 text-sm font-semibold text-slate-900">
                        {{ $tanah->luas_tanah !== null
                            ? number_format($tanah->luas_tanah, 2, ',', '.') . ' m²'
                            : '-' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium text-slate-500">Alamat</p>
                    <p class="mt-1 text-sm text-slate-900">
                        {{ $tanah->alamat ?? '-' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium text-slate-500">Ketkel</p>
                    <p class="mt-1 text-sm text-slate-900">
                        {{ $tanah->ketkel ?? '-' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium text-slate-500">Status Hak</p>
                    <p class="mt-1 text-sm text-slate-900">
                        {{ $tanah->status_hak ?? '-' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium text-slate-500">Nomor Sertifikat</p>
                    <p class="mt-1 text-sm font-semibold text-slate-900">
                        {{ $tanah->nomor_sertifikat ?? '-' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium text-slate-500">Tanggal Sertifikat</p>
                    <p class="mt-1 text-sm text-slate-900">
                        {{ $tanah->tanggal_sertifikat?->format('d/m/Y') ?? '-' }}
                    </p>
                </div>

            </div>
        </div>

        {{-- PENGGUNAAN & KONDISI --}}
        <div class="mb-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-base font-semibold text-slate-900">
                    Penggunaan & Kondisi
                </h2>
            </div>

            <div class="grid grid-cols-1 gap-x-8 gap-y-5 p-6 md:grid-cols-2 lg:grid-cols-4">

                <div>
                    <p class="text-xs font-medium text-slate-500">Penggunaan</p>
                    <p class="mt-1 text-sm text-slate-900">
                        {{ $tanah->penggunaan ?? '-' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium text-slate-500">Penggunaan Air</p>
                    <p class="mt-1 text-sm text-slate-900">
                        {{ $tanah->penggunaan_air ?? '-' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium text-slate-500">Kondisi</p>
                    <p class="mt-1 text-sm font-medium text-slate-900">
                        {{ $tanah->kondisi ?? '-' }}
                    </p>
                </div>

                <div class="md:col-span-2 lg:col-span-1">
                    <p class="text-xs font-medium text-slate-500">Keterangan</p>
                    <p class="mt-1 text-sm text-slate-900">
                        {{ $tanah->keterangan ?? '-' }}
                    </p>
                </div>

            </div>
        </div>

        {{-- PETUGAS & LOKASI --}}
        <div class="mb-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-base font-semibold text-slate-900">
                    Petugas & Lokasi
                </h2>
            </div>

            <div class="grid grid-cols-1 gap-x-8 gap-y-5 p-6 md:grid-cols-2 lg:grid-cols-3">

                <div>
                    <p class="text-xs font-medium text-slate-500">Nama Petugas</p>
                    <p class="mt-1 text-sm text-slate-900">
                        {{ $tanah->nama_petugas ?? '-' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium text-slate-500">Nomor Tlp Petugas</p>
                    <p class="mt-1 text-sm text-slate-900">
                        {{ $tanah->nomor_hp_petugas ?? '-' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium text-slate-500">Google Maps</p>

                    @if($tanah->google_maps)
                        <a href="{{ $tanah->google_maps }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="mt-1 inline-flex items-center gap-1 text-sm font-medium
                                  text-slate-700 underline hover:text-slate-900">
                            Buka Lokasi
                        </a>
                    @else
                        <p class="mt-1 text-sm text-slate-900">-</p>
                    @endif
                </div>

            </div>
        </div>

        {{-- MEDIA ASET --}}
        <div class="mb-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-base font-semibold text-slate-900">
                    Media Aset
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Foto dan video yang berkaitan dengan aset tanah.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-6 p-6 md:grid-cols-2">

                <div class="rounded-lg border border-dashed border-slate-300 p-5">
                    <p class="text-sm font-semibold text-slate-900">
                        Foto Tanah
                    </p>

                    @if($tanah->foto_tanah)
                        <p class="mt-2 text-sm text-slate-600">
                            {{ $tanah->foto_tanah }}
                        </p>
                    @else
                        <p class="mt-2 text-sm text-slate-500">
                            Belum ada foto.
                        </p>
                    @endif
                </div>

                <div class="rounded-lg border border-dashed border-slate-300 p-5">
                    <p class="text-sm font-semibold text-slate-900">
                        Video Tanah
                    </p>

                    @if($tanah->video_tanah)
                        <p class="mt-2 text-sm text-slate-600">
                            {{ $tanah->video_tanah }}
                        </p>
                    @else
                        <p class="mt-2 text-sm text-slate-500">
                            Belum ada video.
                        </p>
                    @endif
                </div>

            </div>
        </div>

        {{-- RIWAYAT PERUBAHAN --}}
        <div class="mb-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-base font-semibold text-slate-900">
                    Riwayat Perubahan
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Informasi pengguna yang melakukan perubahan data inventaris tanah.
                </p>
            </div>


            <div class="p-6">

                @if($tanah->histories->count())

                    <div class="space-y-4">

                        @foreach($tanah->histories as $history)

                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">

                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">

                                    <div>

                                        <p class="text-sm font-semibold text-slate-800">

                                            {{ $history->aksi }}

                                        </p>


                                        <p class="mt-1 text-sm text-slate-600">

                                            Diedit oleh:

                                            <span class="font-semibold">
                                                {{ $history->user->pegawai->nama_pegawai ?? $history->user->username }}
                                            </span>

                                        </p>

                                    </div>


                                    <div class="text-xs text-slate-500">

                                        {{ $history->created_at->format('d/m/Y H:i') }}

                                    </div>

                                </div>


                            </div>

                        @endforeach

                    </div>


                @else

                    <p class="text-sm text-slate-500">
                        Belum ada riwayat perubahan.
                    </p>

                @endif

            </div>

        </div>

        {{-- RETRIBUSI TANAH --}}
        <div class="mb-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-semibold text-slate-900">
                    Retribusi Tanah
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Data biaya pengurusan, PAD, tarif, dan pemanfaatan aset.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
                    {{ $tanah->retribusi->count() }} data
                </span>

                <a href="{{ route('tanah.retribusi.create', $tanah) }}"
                class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2
                        text-sm font-medium text-white shadow-sm hover:bg-slate-800">
                    + Tambah Retribusi
                </a>
            </div>

        </div>
                <span class="inline-flex w-fit items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
                    {{ $tanah->retribusi->count() }} data
                </span>

            </div>

            @if($tanah->retribusi->count())
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead>
                            <tr>
                                <th>Tahun</th>
                                <th>Status Pemanfaatan</th>
                                <th>Biaya Pengurusan</th>
                                <th>PAD</th>
                                <th>Tarif Retribusi</th>
                                <th>Total Tarif</th>
                                <th>Satuan</th>
                                <th>Keterangan</th>
                                <th class="text-center">
                                    Aksi
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-200 bg-white">
                            @foreach($tanah->retribusi as $retribusi)

                            <td class="text-center">

                                    <div class="flex justify-center gap-2">

                                        <a href="{{ route('tanah.retribusi.edit', $retribusi) }}"
                                        class="rounded-lg bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-100">

                                            Edit

                                        </a>


                                        <form action="{{ route('tanah.retribusi.destroy', $retribusi) }}"
                                            method="POST"
                                            onsubmit="return confirm('Hapus data retribusi ini?')">

                                            @csrf
                                            @method('DELETE')

                                            <button type="submit"
                                                    class="rounded-lg bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100">

                                                Hapus

                                            </button>

                                        </form>

                                    </div>

                                </td>

                                <tr>
                                    <td class="whitespace-nowrap px-5 py-3 text-slate-900">
                                        {{ $retribusi->tahun ?? '-' }}
                                    </td>

                                    <td>
                                        {{ $retribusi->status_pemanfaatan ?? '-' }}
                                    </td>

                                    <td class="whitespace-nowrap px-5 py-3 text-slate-900">
                                        Rp {{ number_format($retribusi->biaya_pengurusan ?? 0, 0, ',', '.') }}
                                    </td>

                                    <td class="whitespace-nowrap px-5 py-3 text-slate-900">
                                        Rp {{ number_format($retribusi->PAD ?? 0, 0, ',', '.') }}
                                    </td>

                                    <td class="whitespace-nowrap px-5 py-3 text-slate-900">
                                        Rp {{ number_format($retribusi->tarif_retribusi ?? 0, 0, ',', '.') }}
                                    </td>

                                    <td class="whitespace-nowrap px-5 py-3 text-slate-900">
                                        Rp {{ number_format($retribusi->total_tarif_sewa ?? 0, 0, ',', '.') }}
                                    </td>

                                    <td class="whitespace-nowrap px-5 py-3 text-slate-900">
                                        {{ $retribusi->satuan ?? '-' }}
                                    </td>

                                    <td class="px-5 py-3 text-slate-900">
                                        {{ $retribusi->keterangan ?? '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-6">
                    <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
                        <p class="text-sm font-medium text-slate-700">
                            Belum ada data retribusi.
                        </p>

                        <p class="mt-1 text-sm text-slate-500">
                            Data retribusi akan ditambahkan pada tahap berikutnya.
                        </p>
                    </div>
                </div>
            @endif
        </div>

        {{-- DOKUMEN PBB --}}
        <div class="mb-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">

        <div class="flex items-start justify-between">

            <div>
                <h2 class="text-base font-semibold text-slate-900">
                    Dokumen PBB
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Arsip dokumen PBB yang terkait dengan aset tanah.
                </p>
            </div>


            @if(in_array(auth()->user()?->role?->nama_role, ['Admin Aset', 'Admin UPT P2DP']))
                <a href="{{ route('tanah.dokumen.create', $tanah) }}"
                class="inline-flex items-center gap-2 rounded-lg
                        bg-emerald-600 px-4 py-2
                        text-sm font-semibold text-white
                        hover:bg-emerald-700 transition">

                    <svg class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        viewBox="0 0 24 24">

                        <path stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 4v16m8-8H4"/>

                    </svg>

                    Tambah Dokumen PBB

                </a>
            @endif

        </div>

        {{-- RIWAYAT DOKUMEN PBB --}}

        <div class="mb-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

            <div class="border-b border-slate-200 px-6 py-4">

                <h2 class="text-base font-semibold text-slate-900">
                    Riwayat Dokumen PBB
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Riwayat upload dan penghapusan dokumen PBB.
                </p>

            </div>


            @if($tanah->dokumenPbbHistories->count())


            <div class="overflow-x-auto">

                <table class="min-w-full text-sm">


                    <thead class="bg-slate-50">

                        <tr>

                            <th class="px-5 py-3 text-left">
                                Aksi
                            </th>

                            <th class="px-5 py-3 text-left">
                                Nama File
                            </th>

                            <th class="px-5 py-3 text-left">
                                Dilakukan Oleh
                            </th>

                            <th class="px-5 py-3 text-left">
                                Waktu
                            </th>

                        </tr>

                    </thead>


                    <tbody class="divide-y divide-slate-200">


                        @foreach($tanah->dokumenPbbHistories as $history)


                        <tr>


                            <td class="px-5 py-3">

                                @if($history->aksi == 'UPLOAD')

                                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                                        Upload
                                    </span>

                                @else

                                    <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700">
                                        Hapus
                                    </span>

                                @endif

                            </td>



                            <td class="px-5 py-3 text-slate-700">

                                {{ $history->nama_file ?? '-' }}

                            </td>



                            <td class="px-5 py-3 text-slate-700">

                                {{ $history->user->username ?? '-' }}

                            </td>



                            <td class="px-5 py-3 text-slate-700">

                                {{ $history->created_at->format('d/m/Y H:i') }}

                            </td>


                        </tr>


                        @endforeach


                    </tbody>


                </table>


            </div>


            @else


                <div class="p-6 text-center text-sm text-slate-500">

                    Belum ada riwayat dokumen PBB.

                </div>


            @endif


        </div>

    </div>

            @if($tanah->dokumenPbb->count())
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-5 py-3 text-left font-semibold text-slate-600">
                                    Tahun PBB
                                </th>
                                <th class="px-5 py-3 text-left font-semibold text-slate-600">
                                    Tanggal Upload
                                </th>
                                <th class="px-5 py-3 text-left font-semibold text-slate-600">
                                    File
                                </th>
                                <th class="px-5 py-3 text-center font-semibold text-slate-600">
                                    Aksi
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-200 bg-white">
                            @foreach($tanah->dokumenPbb as $pbb)
                                <tr>
                                    <td class="whitespace-nowrap px-5 py-3 text-slate-900">
                                        {{ $pbb->tahun_pbb ?? '-' }}
                                    </td>

                                    <td class="whitespace-nowrap px-5 py-3 text-slate-900">
                                        {{ $pbb->tanggal_upload?->format('d/m/Y') ?? '-' }}
                                    </td>

                                    <td class="px-5 py-3">

                                        @if($pbb->file_pbb)

                                        <a href="{{ Storage::url($pbb->file_pbb) }}"
                                        target="_blank"
                                        class="inline-flex items-center rounded-lg bg-blue-50 px-3 py-1.5 text-sm font-semibold text-blue-600 hover:bg-blue-100">

                                            Lihat File

                                        </a>

                                        @else

                                        -

                                        @endif

                                    </td>

                                    <td class="px-5 py-3 text-center">

                                        @if(in_array(auth()->user()?->role?->nama_role, ['Admin Aset', 'Admin UPT P2DP']))

                                            <form action="{{ route('tanah.dokumen.destroy', $pbb) }}"
                                                method="POST"
                                                onsubmit="return confirm('Hapus dokumen PBB ini?')">

                                                @csrf
                                                @method('DELETE')

                                                <button type="submit"
                                                        class="rounded-lg bg-red-50 px-3 py-1.5
                                                            text-xs font-semibold text-red-600
                                                            hover:bg-red-100">

                                                    Hapus

                                                </button>

                                            </form>

                                        @else

                                            <span class="text-slate-400">
                                                -
                                            </span>

                                        @endif

                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-6">
                    <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
                        <p class="text-sm font-medium text-slate-700">
                            Belum ada dokumen PBB.
                        </p>

                        <p class="mt-1 text-sm text-slate-500">
                            Arsip PBB akan ditambahkan pada tahap berikutnya.
                        </p>
                    </div>
                </div>
            @endif
        </div>

    </div>
</div>
@endsection