@extends('layouts.app')

@section('title', 'Ganti Password')

@section('content')
    <x-page-header title="Ganti Password" subtitle="Perbarui password akun login Anda" />

    <x-alert type="success" />
    <x-alert type="error" />

    <div class="mx-auto max-w-lg">
        <x-form-card title="Ganti Password" description="Masukkan password lama lalu password baru Anda.">
            <form method="POST" action="{{ route('profile.password.update') }}" class="space-y-5">
                @csrf

                <x-input
                    name="password_lama"
                    label="Password Lama"
                    type="password"
                    required
                    autocomplete="current-password"
                />

                <x-input
                    name="password"
                    label="Password Baru"
                    type="password"
                    required
                    autocomplete="new-password"
                    help="Minimal 8 karakter."
                />

                <x-input
                    name="password_confirmation"
                    label="Konfirmasi Password Baru"
                    type="password"
                    required
                    autocomplete="new-password"
                />

                <div class="flex justify-end">
                    <x-button type="submit" icon="M12 4v16m8-8H4">
                        Simpan Password
                    </x-button>
                </div>
            </form>
        </x-form-card>
    </div>
@endsection
