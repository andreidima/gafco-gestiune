<?php

namespace Tests\Unit;

use App\Support\LocalizedNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LocalizedNumberTest extends TestCase
{
    #[DataProvider('quantities')]
    public function test_it_only_displays_significant_decimals(mixed $value, string $expected): void
    {
        $this->assertSame($expected, LocalizedNumber::quantity($value));
    }

    public static function quantities(): array
    {
        return [
            'integer' => [5, '5'],
            'stored integer' => ['5.000', '5'],
            'one decimal' => ['5.100', '5,1'],
            'two decimals' => ['5.120', '5,12'],
            'three decimals' => ['5.125', '5,125'],
            'thousands' => ['12345.125', '12.345,125'],
        ];
    }
}
