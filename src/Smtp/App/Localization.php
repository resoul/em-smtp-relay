<?php

declare(strict_types=1);

namespace Emercury\Smtp\App;

class Localization
{
    private string $domain;

    public function __construct(string $domain)
    {
        $this->domain = $domain;
    }

    public function load(string $path)
    {
        load_plugin_textdomain($this->domain, false, $path);
    }

    public function t(string $text): string
    {
        return __($text, $this->domain);
    }

    public function escHtml(string $text): string
    {
        return esc_html__($text, $this->domain);
    }
}
