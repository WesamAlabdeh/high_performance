<?php

namespace App\Actions\UserActions\Product;

use App\Actions\Base\BaseAction;
use App\Http\Resources\User\Product\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;

class GetProductAction extends BaseAction
{
    public function handle(array $filters): mixed
    {
        return Product::filters($filters)
            ->where('is_active', true)
            ->with('category')
            ->paginate();
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
