<?php

namespace App\Actions\AdminActions\Batch;

use App\Actions\Base\BaseAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Lorisleiva\Actions\ActionRequest;

class TriggerDailySalesBatchAction extends BaseAction
{
    public function handle(?string $date): array
    {
        Artisan::call('sales:process-daily', $date ? ['date' => $date] : []);

        return [
            'output' => Artisan::output(),
            'date' => $date ?? now()->subDay()->toDateString(),
        ];
    }

    public function rules(): array
    {
        return [
            'date' => 'nullable|date_format:Y-m-d',
        ];
    }

    public function asController(ActionRequest $request): array
    {
        return $this->handle($request->input('date'));
    }

    public function jsonResponse(array $data): JsonResponse
    {
        return $this->success($data, 'Daily sales batch dispatched');
    }

    public function authorize(): bool
    {
        return true;
    }
}
