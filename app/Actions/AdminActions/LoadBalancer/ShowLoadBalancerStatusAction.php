<?php

namespace App\Actions\AdminActions\LoadBalancer;

use App\Actions\Base\BaseAction;
use App\Services\LoadBalancing\LoadBalancerService;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;

class ShowLoadBalancerStatusAction extends BaseAction
{
    public function __construct(private readonly LoadBalancerService $loadBalancer) {}

    public function handle(): array
    {
        return $this->loadBalancer->status('api');
    }

    public function asController(ActionRequest $request): array
    {
        return $this->handle();
    }

    public function jsonResponse(array $data): JsonResponse
    {
        return $this->success($data, 'Load balancer status');
    }

    public function authorize(): bool
    {
        return true;
    }
}
