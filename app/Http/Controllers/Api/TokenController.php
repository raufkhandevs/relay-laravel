<?php

namespace App\Http\Controllers\Api;

use App\Data\UserData;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class TokenController extends Controller
{
    /**
     * A constant bcrypt hash checked when no user matches, so a lookup miss
     * and a wrong password take comparable time and cannot be distinguished
     * by timing.
     */
    private const DUMMY_HASH = '$2y$12$owgiqhn0IC8PuSotbCik2e0x1lsHghC3MPau/crsRFrbEfNkMaQb6';

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        $passwordMatches = $user
            ? Hash::check($validated['password'], $user->password)
            : Hash::check($validated['password'], self::DUMMY_HASH);

        if (! $user || ! $passwordMatches) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return response()->json([
            'token' => $user->createToken($validated['device_name'])->plainTextToken,
            'user' => UserData::fromModel($user),
        ]);
    }

    public function destroy(Request $request): Response
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->noContent();
    }
}
