<?php

namespace App\Http\Controllers;

use App\Http\Requests\GeneralAuthLoginRequest;
use App\Models\User;
use App\Utils\Fail;
use App\Utils\Permission\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class GeneralAuthController extends Controller
{
    public function login(GeneralAuthLoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Cari berdasarkan Username
        $user = User::where('username', $request->username)->first();

        // Pakai Helper Fail punya senior kamu agar errornya seragam
        Fail::when(empty($user) || ! Hash::check($validated['password'], $user->password), [
            'username' => 'Akun tidak ditemukan atau password salah.',
        ]);

        Fail::when(! $user->is_active, [
            'username' => 'Akun Anda telah dinonaktifkan. Silakan hubungi Administrator.',
        ]);

        // Berikan token dengan nama sesuai Role
        $tokenName = str($user->role->value)->slug('_')->append('_token');
        $token = $user->createToken($tokenName);

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => $user->load('role'),
        ]);
    }

    public function logout(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();

        /** @var PersonalAccessToken */
        $token = $user->currentAccessToken();

        $token->delete();
    }
}
