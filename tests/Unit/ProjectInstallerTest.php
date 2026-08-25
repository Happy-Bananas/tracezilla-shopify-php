<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ProjectInstallerTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir().'/tracezilla-project-installer-'.bin2hex(random_bytes(6));
        mkdir($this->temporaryDirectory, 0700, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->temporaryDirectory)) {
            exec('find '.escapeshellarg($this->temporaryDirectory).' -depth -delete');
        }
    }

    public function test_it_creates_a_project_with_local_configuration_and_safe_template_remote(): void
    {
        $project = dirname(__DIR__, 2);
        $template = $this->createTemplate($project);
        $target = $this->temporaryDirectory.'/my-shopify-integration';
        $command = sprintf(
            'TRACEZILLA_TEMPLATE_REPOSITORY=%s sh %s %s 2>&1',
            escapeshellarg($template),
            escapeshellarg($project.'/create-shopify-project'),
            escapeshellarg($target),
        );

        exec($command, $output, $exitCode);

        self::assertSame(0, $exitCode, implode("\n", $output));
        self::assertFileExists($target.'/.env');
        self::assertSame(file_get_contents($target.'/.env.example'), file_get_contents($target.'/.env'));
        self::assertTrue(is_executable($target.'/check-connection'));
        self::assertDirectoryExists($target.'/.git');
        exec('git -C '.escapeshellarg($target).' remote', $remotes);
        self::assertSame(['template'], $remotes);
    }

    public function test_it_refuses_to_overwrite_an_existing_path(): void
    {
        $project = dirname(__DIR__, 2);
        $template = $this->createTemplate($project);
        $target = $this->temporaryDirectory.'/existing';
        mkdir($target);

        exec(sprintf(
            'TRACEZILLA_TEMPLATE_REPOSITORY=%s sh %s %s 2>&1',
            escapeshellarg($template),
            escapeshellarg($project.'/create-shopify-project'),
            escapeshellarg($target),
        ), $output, $exitCode);

        self::assertNotSame(0, $exitCode);
        self::assertStringContainsString('already exists', implode("\n", $output));
    }

    private function createTemplate(string $project): string
    {
        $template = $this->temporaryDirectory.'/template';
        mkdir($template);
        copy($project.'/.env.example', $template.'/.env.example');
        file_put_contents($template.'/check-connection', "#!/bin/sh\n");
        chmod($template.'/check-connection', 0755);
        exec(
            'git -C '.escapeshellarg($template).' init -q -b main'
            .' && git -C '.escapeshellarg($template).' config user.email test@example.com'
            .' && git -C '.escapeshellarg($template).' config user.name Test'
            .' && git -C '.escapeshellarg($template).' add .'
            .' && git -C '.escapeshellarg($template).' commit -qm template'
        );

        return $template;
    }
}
