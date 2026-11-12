<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Integration;

use WP_Error;
use WP_UnitTestCase;
use AgentStudio\Application\Updates\GitHubReleases;
use AgentStudio\Infrastructure\WordPressHttpClient;
use AgentStudio\WordPress\Updates\PluginUpdates;

/**
 * New versions published as GitHub releases reach sites through WordPress's own plugin updates.
 */
final class PluginUpdatesTest extends WP_UnitTestCase
{
    private const LATEST = 'https://api.github.com/repos/wpscholar/agent-studio/releases/latest';
    private const ASSET = 'https://api.github.com/repos/wpscholar/agent-studio/releases/assets/42';
    private const SIGNED = 'https://objects.githubusercontent.com/signed/agent-studio-9.9.9.zip?sig=abc';

    /** @var list<array{url: string, args: array<string, mixed>}> */
    private array $requests = [];

    /** @var callable(string): (array<string, mixed>|WP_Error) */
    private $answer;

    public function set_up(): void
    {
        parent::set_up();

        delete_site_transient(PluginUpdates::CACHE);
        $this->requests = [];
        $this->answer = fn (string $url): array => $this->json(self::release());
        add_filter('pre_http_request', [$this, 'fakeHttp'], 10, 3);
    }

    public function tear_down(): void
    {
        remove_filter('pre_http_request', [$this, 'fakeHttp']);
        delete_site_transient(PluginUpdates::CACHE);
        unset($_GET['force-check']);
        parent::tear_down();
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return array<string, mixed>|WP_Error
     */
    public function fakeHttp(mixed $preempt, array $args, string $url): array|WP_Error
    {
        $this->requests[] = ['url' => $url, 'args' => $args];

        return ($this->answer)($url);
    }

    public function testThePluginListensOnTheUpdateUriHost(): void
    {
        $this->assertNotFalse(has_filter('update_plugins_github.com'));
        $this->assertNotFalse(has_filter('plugins_api'));
    }

    public function testOffersTheReleaseZipAsTheUpdate(): void
    {
        $update = $this->updates()->offerUpdate(false, ['RequiresWP' => '7.0', 'RequiresPHP' => '8.2'], $this->basename());

        $this->assertIsArray($update);
        $this->assertSame('agent-studio', $update['slug']);
        $this->assertSame('9.9.9', $update['version']);
        $this->assertSame('https://github.com/wpscholar/agent-studio/releases/download/9.9.9/agent-studio-9.9.9.zip', $update['package']);
        $this->assertSame('https://github.com/wpscholar/agent-studio/releases/tag/9.9.9', $update['url']);
        $this->assertSame('8.2', $update['requires_php']);
        $this->assertSame(self::LATEST, $this->requests[0]['url']);
    }

    public function testLeavesOtherPluginsAlone(): void
    {
        $this->assertFalse($this->updates()->offerUpdate(false, [], 'other/other.php'));
        $this->assertSame([], $this->requests);
    }

    public function testAsksGitHubAtMostOnceEveryFewHours(): void
    {
        $updates = $this->updates();
        $updates->latest();
        $updates->latest();

        $this->assertCount(1, $this->requests);
    }

    public function testCheckAgainOnTheUpdatesScreenAsksRightAway(): void
    {
        $updates = $this->updates();
        $updates->latest();

        set_current_screen('update-core');
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        if (is_multisite()) {
            grant_super_admin(get_current_user_id());
        }
        $_GET['force-check'] = '1';
        $updates->latest();

        $this->assertCount(2, $this->requests);
    }

    public function testAFailedCheckOffersNothingAndWaitsBeforeAskingAgain(): void
    {
        $this->answer = static fn (string $url): array => ['headers' => [], 'body' => '{"message":"rate limited"}', 'response' => ['code' => 403, 'message' => 'Forbidden'], 'cookies' => [], 'filename' => null];
        $updates = $this->updates();

        $this->assertFalse($updates->offerUpdate(false, [], $this->basename()));
        $this->assertFalse($updates->offerUpdate(false, [], $this->basename()));
        $this->assertCount(1, $this->requests);
    }

    public function testFillsTheViewDetailsWindow(): void
    {
        $info = $this->updates()->details(false, 'plugin_information', (object) ['slug' => 'agent-studio']);

        $this->assertIsObject($info);
        $this->assertSame('Agent Studio', $info->name);
        $this->assertSame('9.9.9', $info->version);
        $this->assertSame('8.2', $info->requires_php);
        $this->assertStringContainsString('&lt;b&gt;Fixes&lt;/b&gt;', $info->sections['changelog'], 'Release notes are escaped.');
        $this->assertStringContainsString('releases/tag/9.9.9', $info->sections['changelog']);

        $this->assertFalse($this->updates()->details(false, 'plugin_information', (object) ['slug' => 'akismet']));
        $this->assertFalse($this->updates()->details(false, 'query_plugins', (object) ['slug' => 'agent-studio']));
    }

    public function testDownloadsPrivateZipsWithTheTokenButNotPastTheRedirect(): void
    {
        $this->answer = function (string $url): array {
            if ($url === self::ASSET) {
                return ['headers' => ['location' => self::SIGNED], 'body' => '', 'response' => ['code' => 302, 'message' => 'Found'], 'cookies' => [], 'filename' => null];
            }

            return $url === self::LATEST ? $this->json(self::release()) : ['headers' => [], 'body' => 'PK', 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
        };
        $updates = $this->updates('secret');
        $package = $updates->latest()?->packageUrl;
        $this->assertSame(self::ASSET, $package);

        $file = $updates->downloadPrivatePackage(false, $package);

        $asset = $this->requests[1];
        $this->assertSame(self::ASSET, $asset['url']);
        $this->assertSame('Bearer secret', $asset['args']['headers']['Authorization']);
        $this->assertSame('application/octet-stream', $asset['args']['headers']['Accept']);
        $this->assertSame(0, $asset['args']['redirection']);

        $signed = $this->requests[2];
        $this->assertSame(self::SIGNED, $signed['url']);
        $this->assertArrayNotHasKey('Authorization', (array) ($signed['args']['headers'] ?? []));

        $this->assertIsString($file);
        @unlink($file); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
    }

    public function testLeavesPublicDownloadsToWordPress(): void
    {
        $this->assertFalse($this->updates('secret')->downloadPrivatePackage(false, 'https://downloads.wordpress.org/plugin/akismet.zip'));
        $this->assertFalse($this->updates()->downloadPrivatePackage(false, self::ASSET));
        $this->assertSame([], $this->requests);
    }

    public function testRejectsAZipThatIsNotThePlugin(): void
    {
        $remote = $this->unpacked('agent-studio', 'readme.txt');

        $result = $this->updates()->checkPackage($remote . '/agent-studio/', $remote, null, ['plugin' => $this->basename()]);

        $this->assertWPError($result);
    }

    public function testKeepsThePluginInItsCurrentFolder(): void
    {
        $remote = $this->unpacked('some-other-name', 'agent-studio.php');
        $basename = $this->basename();
        $expected = dirname($basename) === '.' ? $remote . '/some-other-name/' : $remote . '/' . dirname($basename) . '/';

        $result = $this->updates()->checkPackage($remote . '/some-other-name/', $remote, null, ['plugin' => $basename]);

        $this->assertSame($expected, $result);
        $this->assertFileExists($expected . 'agent-studio.php');
    }

    public function testForgetsTheCachedReleaseAfterUpdating(): void
    {
        $updates = $this->updates();
        $updates->latest();

        $updates->forgetAfterUpgrade(null, ['type' => 'plugin', 'action' => 'update', 'plugins' => [$this->basename()]]);

        $this->assertFalse(get_site_transient(PluginUpdates::CACHE));
    }

    private function updates(string $token = ''): PluginUpdates
    {
        return new PluginUpdates(
            new GitHubReleases(new WordPressHttpClient(), PluginUpdates::REPOSITORY, PluginUpdates::SLUG, $token),
            AGENT_STUDIO_FILE
        );
    }

    private function basename(): string
    {
        return plugin_basename(AGENT_STUDIO_FILE);
    }

    private function unpacked(string $folder, string $file): string
    {
        global $wp_filesystem;
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();

        $remote = get_temp_dir() . 'agent-studio-upgrade-' . wp_generate_password(8, false);
        $wp_filesystem->mkdir($remote);
        $wp_filesystem->mkdir($remote . '/' . $folder);
        $wp_filesystem->put_contents($remote . '/' . $folder . '/' . $file, '<?php');

        return $remote;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function json(array $data): array
    {
        return ['headers' => ['content-type' => 'application/json'], 'body' => (string) wp_json_encode($data), 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
    }

    /**
     * @return array<string, mixed>
     */
    private static function release(): array
    {
        return [
            'tag_name' => '9.9.9',
            'draft' => false,
            'prerelease' => false,
            'html_url' => 'https://github.com/wpscholar/agent-studio/releases/tag/9.9.9',
            'body' => '<b>Fixes</b> things.',
            'published_at' => '2026-11-01T12:00:00Z',
            'assets' => [
                [
                    'name' => 'agent-studio-9.9.9.zip',
                    'state' => 'uploaded',
                    'url' => self::ASSET,
                    'browser_download_url' => 'https://github.com/wpscholar/agent-studio/releases/download/9.9.9/agent-studio-9.9.9.zip',
                ],
            ],
        ];
    }
}
