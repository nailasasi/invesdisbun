<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    /**
     * Show the change-password page.
     */
    public function showChangePassword()
    {
        return view('profile.change-password');
    }

    /**
     * Update the authenticated user's password.
     */
    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'password_lama' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['password_lama'], $user->password)) {
            throw ValidationException::withMessages([
                'password_lama' => 'Password lama tidak sesuai.',
            ]);
        }

        $user->password = $data['password'];
        $user->save();

        return redirect()->back()->with('success', 'Password berhasil diubah.');
    }
}
