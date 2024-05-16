<?php

namespace App\Http\Controllers\ApiAuth;

use App\Http\Controllers\Controller;

class ApiAuthController extends Controller
{
    public function login()
    {
        $credentials = request(['email', 'password']);

        if (!auth()->attempt($credentials)) {
            return response()->json(['error' => 'Invalid Credentials'], 401);
        }

        return response()->json(['token' => user()->createToken('authToken')->plainTextToken]);
    }

    public function logout()
    {
        user()->tokens()->delete();

        return response()->json(['message' => 'Logged out']);
    }
}
