<?php

declare(strict_types=1);

namespace Tracezilla\Shopify;

final class RuntimeDirectory
{
    public static function resolve(string $projectDirectory): string
    {
        $configured = trim((string) ($_ENV['TRACEZILLA_RUNTIME_DIR'] ?? ''));
        if ($configured === '') {
            return $projectDirectory.'/var';
        }
        if (str_starts_with($configured, '/')) {
            return rtrim($configured, '/');
        }

        return $projectDirectory.'/'.trim($configured, '/');
    }
}

