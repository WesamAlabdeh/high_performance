<?php

namespace App\Actions\UserActions\Auth;

use App\Actions\Base\BaseAction;
use App\Http\Resources\User\UserResource;
use App\Models\Cart;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Lorisleiva\Actions\ActionRequest;

class RegisterAction extends BaseAction
{
    public function handle(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        Cart::create(['user_id' => $user->id]);

        $token = $user->createToken('api')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
        ];
    }

    public function asController(ActionRequest $request): array
    {
        return $this->handle($request->validated());
    }

    public function jsonResponse(array $data): JsonResponse
    {
        return $this->success([
            'user' => new UserResource($data['user']),
            'token' => $data['token'],
        ], 'Registered successfully', 201);
    }

    public function authorize(): bool
    {
        return true;
    }
}
