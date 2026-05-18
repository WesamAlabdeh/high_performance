<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait Filters
{
    public static function filters(array $data): Builder
    {
        $query = self::query();
        $filters = property_exists(static::class, 'filtersArray') ? static::$filtersArray : [];

        foreach ($data as $key => $value) {
            if (method_exists(static::class, 'scope'.ucfirst($key))) {
                $query->$key($value);
            } elseif (array_key_exists($key, $filters)) {
                self::{$filters[$key]}($query, $key, $value);
            }
        }

        return $query;
    }

    private static function equal(Builder $query, string $key, mixed $value): Builder
    {
        return $query->where($key, $value);
    }

    private static function like(Builder $query, string $key, mixed $value): Builder
    {
        return $query->where($key, 'like', '%'.$value.'%');
    }
}
