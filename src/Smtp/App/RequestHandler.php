<?php

declare(strict_types=1);

namespace Emercury\Smtp\App;

class RequestHandler
{
    /**
     * @var array<mixed> $data
     */
    private array $data;

    /**
     * @var array<mixed> $files
     */
    private array $files;

    public function __construct(?array $data = null, ?array $files = null)
    {
        $this->data = $data ?? $_POST;
        $this->files = $files ?? $_FILES;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function getString(string $key, string $default = ''): string
    {
        if (!isset($this->data[$key])) {
            return $default;
        }

        return sanitize_text_field($this->data[$key]);
    }

    public function getEmail(string $key, string $default = ''): string
    {
        if (!isset($this->data[$key])) {
            return $default;
        }

        return sanitize_email($this->data[$key]);
    }

    public function getInt(string $key, int $default = 0): int
    {
        if (!isset($this->data[$key])) {
            return $default;
        }

        return (int) $this->data[$key];
    }

    public function getBool(string $key, bool $default = false): bool
    {
        if (!isset($this->data[$key])) {
            return $default;
        }

        return filter_var($this->data[$key], FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param array<mixed> $default
     */
    public function getArray(string $key, array $default = []): array
    {
        if (!isset($this->data[$key]) || !is_array($this->data[$key])) {
            return $default;
        }

        return $this->data[$key];
    }

    public function getHtml(string $key, string $default = ''): string
    {
        if (!isset($this->data[$key])) {
            return $default;
        }

        return wp_kses_post($this->data[$key]);
    }

    public function getUrl(string $key, string $default = ''): string
    {
        if (!isset($this->data[$key])) {
            return $default;
        }

        return esc_url_raw($this->data[$key]);
    }

    public function getFile(string $key): ?array
    {
        if (!isset($this->files[$key]) || $this->files[$key]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        return $this->files[$key];
    }

    public function getFiles(string $key): array
    {
        if (!isset($this->files[$key]) || empty($this->files[$key]['name'][0])) {
            return [];
        }

        $files = [];
        $fileData = $this->files[$key];

        for ($i = 0; $i < count($fileData['name']); $i++) {
            if ($fileData['error'][$i] === UPLOAD_ERR_OK) {
                $files[] = [
                    'name' => $fileData['name'][$i],
                    'type' => $fileData['type'][$i],
                    'tmp_name' => $fileData['tmp_name'][$i],
                    'error' => $fileData['error'][$i],
                    'size' => $fileData['size'][$i],
                ];
            }
        }

        return $files;
    }

    public function all(): array
    {
        return $this->data;
    }
}
