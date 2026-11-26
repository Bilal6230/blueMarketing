<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

final class MyProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('admin.my-profile', ['user' => $request->user(), 'title' => 'My Profile']);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:male,female,other'],
            'phone_number' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-() ]+$/'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'current_password' => ['nullable', 'required_with:password', 'string', 'current_password'],
            'password' => ['nullable', 'string', 'confirmed', Password::min(8)],
            'password_confirmation' => ['nullable', 'string'],
        ]);

        $newAvatar = null;
        $previousAvatar = $user->avatar;
        if ($request->hasFile('avatar')) {
            $newAvatar = $request->file('avatar')->store('avatar', 'public');
        }

        try {
            $user->name = trim($validated['name']);
            $user->gender = $validated['gender'];
            $user->phone_number = trim($validated['phone_number']);
            if ($newAvatar !== null) {
                $user->avatar = $newAvatar;
            }
            if (!empty($validated['password'])) {
                $user->password = Hash::make($validated['password']);
            }
            $user->save();
        } catch (\Throwable $exception) {
            if ($newAvatar !== null) Storage::disk('public')->delete($newAvatar);
            throw $exception;
        }

        // Only clean up our own managed upload files, never arbitrary disk paths.
        if ($newAvatar !== null && $previousAvatar
            && preg_match('~^avatar/[A-Za-z0-9._-]+$~D', $previousAvatar)
            && $previousAvatar !== $newAvatar) {
            Storage::disk('public')->delete($previousAvatar);
        }

        return response()->json([
            'success' => true,
            'message' => 'Your profile has been updated successfully.',
            'profile' => [
                'name' => $user->name,
                'gender' => $user->gender,
                'phone_number' => $user->phone_number,
                'avatar_url' => $user->avatar ? Storage::disk('public')->url($user->avatar) : null,
            ],
        ]);
    }
}
