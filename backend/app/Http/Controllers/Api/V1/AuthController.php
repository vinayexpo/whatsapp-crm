<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::lower($request->input('email')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        if (! Auth::attempt($request->only('email', 'password'))) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        if (Auth::user()->company?->status === 'suspended') {
            Auth::logout();
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => 'This company account has been suspended.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return response()->json([
            'data' => new UserResource($request->user()),
        ]);
    }

    public function bootstrapStatus(): JsonResponse
    {
        return response()->json([
            'superadminExists' => User::query()->role('superadmin')->exists(),
        ]);
    }

    public function setupSuperadmin(Request $request): JsonResponse
    {
        if (User::query()->role('superadmin')->exists()) {
            return response()->json([
                'message' => 'A superadmin already exists.',
            ], 409);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'company_id' => null,
            'status' => 'active',
        ]);
        $user->assignRole('superadmin');

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'data' => new UserResource($user),
        ], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new UserResource($request->user()),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'avatar' => ['sometimes', 'image', 'max:2048'],
        ]);

        $user = $request->user();

        if (isset($data['name'])) {
            $user->name = $data['name'];
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar_url) {
                Storage::disk('public')->delete(Str::after($user->avatar_url, '/storage/'));
            }

            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar_url = Storage::disk('public')->url($path);
        }

        $user->save();

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }
}
