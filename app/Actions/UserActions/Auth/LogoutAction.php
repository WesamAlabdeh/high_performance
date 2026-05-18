<?php

namespace App\Actions\UserActions\Auth;

use App\Actions\Base\BaseAction;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;

class LogoutAction extends BaseAction
{
    public function handle(ActionRequest $request): void
    {
        $request->user()->currentAccessToken()?->delete();
    }

    public function asController(ActionRequest $request): void
    {
        $this->handle($request);
    }

    public function jsonResponse(): JsonResponse
    {
        return $this->success(message: 'Logged out');
    }

    public function authorize(): bool
    {
        return true;
    }
}
