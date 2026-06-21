<?php

namespace App\Actions\AdminActions\Batch;

use App\Actions\Base\BaseAction;
use App\Exceptions\Errors;
use App\Services\Concurrency\DistributedLockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Lorisleiva\Actions\ActionRequest;

class TriggerDailySalesBatchAction extends BaseAction
{
    public function __construct(private readonly DistributedLockService $locks) {}

    public function handle(?string $date): array
    {
        $date = $date ?? now()->subDay()->toDateString();
        $lockKey = "batch:daily-sales:{$date}";

        $result = $this->locks->tryRun($lockKey, function () use ($date) {
            Artisan::call('sales:process-daily', ['date' => $date]);

            return [
                'output' => Artisan::output(),
                'date' => $date,
                'dispatched' => true,
            ];
        }, ttlSeconds: 120);

        if ($result === null) {
            Errors::Conflict(
                'Daily sales batch already running for this date',
                'distributed lock held'
            );
        }

        return $result;
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
