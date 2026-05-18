<?php

namespace App\Actions\UserActions\Product;

use App\Actions\Base\BaseAction;
use App\Http\Resources\User\Product\ProductResource;
use App\Models\Product;
use App\Services\Cache\ProductCacheService;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;

class ShowProductAction extends BaseAction
{
    public function __construct(private readonly ProductCacheService $productCache) {}

    public function handle(int $id): Product
    {
        return $this->productCache->findActive($id);
    }

    public function asController(int $id): Product
    {
        return $this->handle($id);
    }

    public function jsonResponse(Product $product): JsonResponse
    {
        return $this->success(new ProductResource($product));
    }

    public function authorize(): bool
    {
        return true;
    }
}
