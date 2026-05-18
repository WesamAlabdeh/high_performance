<?php

namespace App\Actions\UserActions\Product;

use App\Actions\Base\BaseAction;
use App\Http\Resources\User\Product\ProductResource;
use App\Services\Cache\ProductCacheService;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;

class GetProductAction extends BaseAction
{
    public function __construct(private readonly ProductCacheService $productCache) {}

    public function handle(array $filters): mixed
    {
        return $this->productCache->paginateActive($filters);
    }

    public function asController(ActionRequest $request): mixed
    {
        return $this->handle($request->all());
    }

    public function jsonResponse(mixed $products): JsonResponse
    {
        return $this->success(ProductResource::collection($products));
    }

    public function authorize(): bool
    {
        return true;
    }
}
