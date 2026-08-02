<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait NormalizesInternalCodes
{
    protected static function bootNormalizesInternalCodes(): void
    {
        static::saving(function ($model): void {
            foreach ($model->internalCodeAttributes() as $attribute) {
                $value = $model->getAttribute($attribute);
                $normalized = $value === null ? null : Str::upper(trim((string) $value));
                $model->setAttribute(
                    $attribute,
                    $normalized === '' && in_array($attribute, $model->nullableInternalCodeAttributes(), true)
                        ? null
                        : $normalized,
                );
            }
        });
    }

    /** @return list<string> */
    protected function nullableInternalCodeAttributes(): array
    {
        return [];
    }

    /** @return list<string> */
    abstract protected function internalCodeAttributes(): array;
}
