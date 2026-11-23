<?php

namespace App\Http\Controllers;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated=$request->validated();

        $user=User::create($validated);

        return response()->json([
            'user'=>$user,
            'token'=>$user->createToken('api')->plainTextToken,
            'message'=>'Registered successfully',
        ],201);
    }

    public function login(LoginRequest $request): JsonResponse
    {

        $user=User::where('email',$request->email)->first();

        if(!$user || ! Hash::check($request->password,$user->password))
        {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return response()->json([
            'user'=>$user,
            'token'=>$user->createToken('api')->plainTextToken,
            'message'=>'Logged in successfully',
        ]);
    }


    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function logout(Request $request)
    {
        $user=$request->user();

        $user->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }
}
