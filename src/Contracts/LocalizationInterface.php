<?php

declare(strict_types=1);

namespace Emercury\Smtp\Contracts;

interface LocalizationInterface
{
    public function t(string $text): string;
    public function esc(string $text): string;
}