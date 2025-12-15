<?php

declare(strict_types=1);

namespace Emercury\Smtp\Core;

use Emercury\Smtp\Contracts\LocalizationInterface;

class Localization implements LocalizationInterface
{
    private string $domain;

    public function __construct(string $domain)
    {
        $this->domain = $domain;
    }

    public function load(string $path): void
    {
        load_plugin_textdomain($this->domain, false, $path);
    }

    public function t(string $text): string
    {
        return __($text, $this->domain);
    }

    public function esc(string $text): string
    {
        return esc_html__($text, $this->domain);
    }
}