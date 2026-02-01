<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $input = $request->validated();
        $name = data_get($input, 'name');
        $email = data_get($input, 'email');
        $password = data_get($input, 'password');

        $languageIds = Language::pluck('id')->toArray();
        $skillIds = Skill::pluck('id')->toArray();

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => UserRole::Agent->value,
            'status' => 'offline',
        ]);

        $user->languages()->sync($languageIds);
        $user->skills()->sync($skillIds);

        $token = $user->createToken('auth-token')->plainTextToken;

        return (new UserResource($user->load(['languages', 'skills'])))
            ->additional(['meta' => ['token' => $token]]);
    }

    public function login(LoginRequest $request)
    {
        $input = $request->validated();
        $email = data_get($input, 'email');
        $password = data_get($input, 'password');

        if (!Auth::attempt(['email' => $email, 'password' => $password])) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        $user = Auth::user();
        $token = $user->createToken('auth-token')->plainTextToken;

        return (new UserResource($user->load(['languages', 'skills'])))
            ->additional(['meta' => ['token' => $token]]);
    }

    public function user(Request $request)
    {
        return new UserResource($request->user()->load(['languages', 'skills']));
    }
}
