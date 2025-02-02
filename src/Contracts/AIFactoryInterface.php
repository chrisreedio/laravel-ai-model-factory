<?php

namespace ChrisReedIO\AIModelFactory\Contracts;

use Illuminate\View\View;

interface AIFactoryInterface
{
    public static function generate(): static;

    public static function getGenerationPrompt(array $input): View;

    public static function getGenerationSeed(): array;

    public static function getFieldDescriptions(): array;

    public static function getGeneratableRelations(): array;
}
