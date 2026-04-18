<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AdminInitController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $user = Auth::user();

        $user->load('role');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->full_name,
                'username' => $user->username,
                'role' => $user->role,
            ],
            'app_info' => [
                'name' => 'Inventory Pro',
                'version' => '1.0.0',
            ],
        ]);
    }
}
