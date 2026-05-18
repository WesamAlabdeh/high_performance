<?php

namespace App\Actions\UserActions\Wallet;

use App\Actions\Base\BaseAction;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;

class ShowWalletAction extends BaseAction
{
    public function asController(ActionRequest $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            'balance' => $user->balance,
            'version' => $user->version,
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }
}
