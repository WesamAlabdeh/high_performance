<?php

namespace App\Actions\Base;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class BaseAction
{
    use AsAction;

    protected function transaction(callable $callback, ?callable $onError = null): mixed
    {
        try {
            DB::beginTransaction();
            $result = $callback();
            DB::commit();

            return $result;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            if ($onError) {
                $onError($e);
            }

            throw $e;
        }
    }

    protected function success(
        mixed $data = null,
        string $message = 'Success',
        int $statusCode = 200,
        string $dataKey = 'data',
        array $meta = []
    ): JsonResponse {
        if ($data instanceof ResourceCollection && $data->resource instanceof LengthAwarePaginator) {
            $paginator = $data->resource->toArray();
            $paginator[$dataKey] = $data->collection
                ->map(fn (JsonResource $item) => $item->resolve(request()))
                ->values()
                ->all();

            return response()->json(array_merge(['status' => 'success', 'message' => __($message)], $paginator), $statusCode);
        }

        if ($data instanceof LengthAwarePaginator) {
            return response()->json(array_merge(['status' => 'success', 'message' => __($message)], $data->toArray()), $statusCode);
        }

        $response = ['status' => 'success', 'message' => __($message)];

        if (! is_null($data)) {
            $response[$dataKey] = $data;
        }

        if ($meta !== []) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $statusCode);
    }
}
