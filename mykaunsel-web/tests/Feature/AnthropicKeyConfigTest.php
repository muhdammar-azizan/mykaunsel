<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The place for the Anthropic API key: the server's private .env file, read through config, and nowhere else. These tests make sure the
 * key can never end up in git, in the mobile app or in a page.
 */
class AnthropicKeyConfigTest extends TestCase
{
    /** Built in two pieces so this very file does not contain what it searches for. */
    private function keyPattern(): string
    {
        return '/'.'sk-'.'ant-[A-Za-z0-9_\-]{10,}/';
    }

    public function test_the_key_is_read_from_the_environment_through_config(): void
    {
        $services = file_get_contents(config_path('services.php'));

        $this->assertStringContainsString("'anthropic' => [", $services);
        $this->assertStringContainsString("'key' => env('ANTHROPIC_API_KEY')", $services);
        $this->assertArrayHasKey('key', config('services.anthropic'));
    }

    public function test_the_example_env_has_an_empty_slot_never_a_real_key(): void
    {
        $example = file_get_contents(base_path('.env.example'));

        $this->assertMatchesRegularExpression('/^ANTHROPIC_API_KEY=$/m', $example, 'the line exists and is empty');
        $this->assertDoesNotMatchRegularExpression($this->keyPattern(), $example);
    }

    public function test_the_private_env_file_is_ignored_by_git(): void
    {
        $ignored = array_map('trim', file(base_path('.gitignore')));

        $this->assertContains('.env', $ignored);
    }

    public function test_no_anthropic_key_is_written_anywhere_in_the_code_the_docs_or_the_mobile_app(): void
    {
        $roots = [base_path('app'), base_path('config'), base_path('routes'), base_path('resources'), base_path('database'), base_path('tests'), base_path('public'), dirname(base_path()).'/docs', dirname(base_path()).'/mykaunsel-app'];
        $skip = ['/vendor/', '/node_modules/', '/.dart_tool/', '/build/', '/.git/', '/storage/', '/.gradle/', '/Pods/'];
        $found = [];

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
                $path = str_replace('\\', '/', $file->getPathname());

                if (! $file->isFile() || $file->getSize() > 2_000_000 || array_filter($skip, fn ($part) => str_contains($path, $part))) {
                    continue;
                }

                if (preg_match($this->keyPattern(), (string) file_get_contents($path))) {
                    $found[] = $path;
                }
            }
        }

        $this->assertSame([], $found, 'a real-looking Anthropic key is written in a file that goes to git');
    }

    public function test_an_unset_key_means_the_feature_is_off_not_broken(): void
    {
        config(['services.anthropic.key' => null]);

        $this->assertFalse(filled(config('services.anthropic.key')));
        $this->get('/login')->assertOk();   // the app runs fine without it
    }

    public function test_a_configured_key_is_never_shown_on_a_page_or_an_error(): void
    {
        $secret = 'sk-'.'ant-api03-TESTONLY-'.str_repeat('x', 30);
        config(['services.anthropic.key' => $secret]);

        $this->assertStringNotContainsString($secret, $this->get('/login')->getContent());
        $this->assertStringNotContainsString($secret, $this->get('/this-page-does-not-exist')->getContent());
        $this->assertStringNotContainsString($secret, json_encode(config('app')), 'it is not in the app config either');
    }
}
