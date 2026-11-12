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

            @include('tanah.retribusi.partials.form', ['mode' => 'create'])

        </form>

    </div>
</div>
@endsection

