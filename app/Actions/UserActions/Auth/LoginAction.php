<?php

namespace App\Actions\UserActions\Auth;

use App\Actions\Base\BaseAction;
use App\Exceptions\Errors;
use App\Http\Resources\User\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Lorisleiva\Actions\ActionRequest;

class LoginAction extends BaseAction
{
    public function handle(array $data): array
    {
        $user = User::with('cart')->where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            Errors::InvalidCredentials('Incorrect email or password', 'invalid credentials');
        }

        $user->tokens()->delete();
        $token = $user->createToken('api')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'password' => 'required|min:6',
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
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }
}
