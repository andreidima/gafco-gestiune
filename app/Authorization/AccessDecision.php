<?php

namespace App\Authorization;

final readonly class AccessDecision
{
    /**
     * @param  array<int, array{type: string, label: string, scope: string}>  $sources
     * @param  array<int, string>  $locations
     */
    public function __construct(
        public string $ability,
        public string $module,
        public string $label,
        public string $description,
        public string $risk,
        public bool $allowed,
        public bool $conditional,
        public string $scope,
        public string $scopeLabel,
        public string $reason,
        public array $sources = [],
        public array $locations = [],
        public ?string $condition = null,
    ) {}
}
