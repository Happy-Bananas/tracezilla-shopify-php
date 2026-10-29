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

    public function test_it_creates_a_shopify_project_with_the_shared_script(): void
    {
        self::assertSame("shopify\nhappy-hanna-shopify", $this->runInstaller('happy-hanna-shopify'));
    }

    public function test_it_keeps_the_default_project_name(): void
    {
        self::assertSame("shopify\nmy-bifrost-connect-project", $this->runInstaller());
    }

    /**
     * Runs create-shopify-project against a stand-in for the shared script that
     * prints the arguments it receives.
     */
    private function runInstaller(string ...$arguments): string
    {
        $sharedScript = $this->temporaryDirectory.'/create-project';
        file_put_contents($sharedScript, "printf '%s\\n' \"\$@\"\n");

        exec(sprintf(
            'TRACEZILLA_CREATE_PROJECT_URL=%s sh %s %s 2>&1',
            escapeshellarg('file://'.$sharedScript),
            escapeshellarg(dirname(__DIR__, 2).'/create-shopify-project'),
            implode(' ', array_map('escapeshellarg', $arguments)),
        ), $output, $exitCode);

        self::assertSame(0, $exitCode, implode("\n", $output));

        return implode("\n", $output);
    }
}
