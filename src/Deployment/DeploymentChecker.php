<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Deployment;

use RuntimeException;
use Throwable;
use Tracezilla\Shopify\Lock\FileIntegrationLock;
use Tracezilla\Shopify\Retry\FileRetryRepository;

final class DeploymentChecker
{
    public function check(string $runtimeDirectory): array
    {
        $checks = [
            'runtime_directory' => false,
            'atomic_file_replace' => false,
            'global_lock' => false,
        ];

        if (! is_dir($runtimeDirectory) && ! mkdir($runtimeDirectory, 0700, true) && ! is_dir($runtimeDirectory)) {
            throw new RuntimeException("Could not create runtime directory [{$runtimeDirectory}].");
        }
        chmod($runtimeDirectory, 0700);
        $checks['runtime_directory'] = is_writable($runtimeDirectory);
        new FileRetryRepository($runtimeDirectory);
        $logDirectory = $runtimeDirectory.'/log';
        if (! is_dir($logDirectory) && ! mkdir($logDirectory, 0700, true) && ! is_dir($logDirectory)) {
            throw new RuntimeException("Could not create log directory [{$logDirectory}].");
        }
        chmod($logDirectory, 0700);

        $suffix = bin2hex(random_bytes(6));
        $temporary = $runtimeDirectory.'/.deployment-check-'.$suffix.'.tmp';
        $published = $runtimeDirectory.'/.deployment-check-'.$suffix.'.ready';
        try {
            $value = 'tracezilla-deployment-check';
            if (file_put_contents($temporary, $value, LOCK_EX) !== strlen($value)) {
                throw new RuntimeException('Could not write deployment-check file.');
            }
            chmod($temporary, 0600);
            if (! rename($temporary, $published)) {
                throw new RuntimeException('Could not atomically rename deployment-check file.');
            }
            $checks['atomic_file_replace'] = file_get_contents($published) === $value;

            $locks = new FileIntegrationLock($runtimeDirectory);
            $first = $locks->acquire('deployment-check', ['command' => 'deployment:check']);
            $second = $locks->acquire('deployment-check', ['command' => 'deployment:check']);
            $checks['global_lock'] = $first !== null && $second === null;
            $first?->release();
            $second?->release();
        } catch (Throwable $exception) {
            throw new RuntimeException('Deployment storage check failed: '.$exception->getMessage(), 0, $exception);
        } finally {
            @unlink($temporary);
            @unlink($published);
        }

        return [
            'ready' => ! in_array(false, $checks, true),
            'runtime_directory' => $runtimeDirectory,
            'checks' => $checks,
        ];
    }
}
