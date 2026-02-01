<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with(['languages', 'skills'])->paginate();

        return UserResource::collection($users);
    }

    public function show(User $user)
    {
        return new UserResource($user->load(['languages', 'skills']));
    }

    public function store(StoreUserRequest $request)
    {
        $input = $request->validated();
        $name = data_get($input, 'name');
        $email = data_get($input, 'email');
        $password = data_get($input, 'password');
        $role = data_get($input, 'role', 'agent');
        $department = data_get($input, 'department');
        $status = data_get($input, 'status', 'offline');
        $languageIds = data_get($input, 'language_ids') ?? Language::pluck('id')->toArray();
        $skillIds = data_get($input, 'skill_ids') ?? Skill::pluck('id')->toArray();

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'department' => $department,
            'status' => $status,
        ]);

        $user->languages()->sync($languageIds);
        $user->skills()->sync($skillIds);

        return new UserResource($user->load(['languages', 'skills']));
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $input = $request->validated();
        $languageIds = data_get($input, 'language_ids');
        $skillIds = data_get($input, 'skill_ids');

        $user->update($input);

        if ($languageIds) {
            $user->languages()->sync($languageIds);
        }

        if ($skillIds) {
            $user->skills()->sync($skillIds);
        }

        return new UserResource($user->fresh(['languages', 'skills']));
    }

    public function destroy(User $user)
    {
        $user->delete();

        return response()->noContent();
    }
}
