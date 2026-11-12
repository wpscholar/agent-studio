<?php

declare(strict_types=1);

namespace AgentStudio\WordPress\Updates;

use WP_Error;
use WP_Filesystem_Base;
use AgentStudio\Application\Updates\GitHubReleases;
use AgentStudio\Application\Updates\ReleaseLookupFailed;
use AgentStudio\Domain\Update\Release;

/**
 * Offers new versions published as GitHub releases through WordPress's normal
 * plugin updates: the "update available" notice, "View details", one-click
 * and automatic updates.
 *
 * The plugin header's "Update URI" points at GitHub, so WordPress asks us
 * (through the update_plugins_github.com filter) instead of WordPress.org.
 */
final class PluginUpdates
{
    public const REPOSITORY = 'wpscholar/agent-studio';
    public const SLUG = 'agent-studio';
    public const CACHE = 'agent_studio_latest_release';

    private const FRESH_FOR = 6 * HOUR_IN_SECONDS;
    private const RETRY_AFTER = HOUR_IN_SECONDS;

    public function __construct(
        private readonly GitHubReleases $releases,
        private readonly string $pluginFile,
    ) {
    }

    public function register(): void
    {
        add_filter('update_plugins_github.com', [$this, 'offerUpdate'], 10, 3);
        add_filter('plugins_api', [$this, 'details'], 10, 3);
        add_filter('upgrader_pre_download', [$this, 'downloadPrivatePackage'], 10, 2);
        add_filter('upgrader_source_selection', [$this, 'checkPackage'], 10, 4);
        add_action('upgrader_process_complete', [$this, 'forgetAfterUpgrade'], 10, 2);
    }

    /**
     * @param array<string, mixed>|false $update
     * @param array<string, mixed>       $pluginData
     *
     * @return array<string, mixed>|false
     */
    public function offerUpdate(array|false $update, array $pluginData, string $pluginFile): array|false
    {
        if ($pluginFile !== $this->basename()) {
            return $update;
        }

        $release = $this->latest();
        if ($release === null) {
            return $update;
        }

        return [
            'slug' => self::SLUG,
            'version' => $release->version,
            'url' => $release->pageUrl,
            'package' => $release->packageUrl,
            'requires' => (string) ($pluginData['RequiresWP'] ?? ''),
            'requires_php' => (string) ($pluginData['RequiresPHP'] ?? ''),
            'icons' => [],
            'banners' => [],
            'translations' => [],
        ];
    }

    /**
     * Fills the "View details" window on the Plugins and Updates screens.
     *
     * @param false|object|array<mixed> $result
     *
     * @return false|object|array<mixed>
     */
    public function details(mixed $result, string $action, mixed $args): mixed
    {
        if ($action !== 'plugin_information' || ! is_object($args) || ($args->slug ?? '') !== self::SLUG) {
            return $result;
        }

        $release = $this->latest();
        if ($release === null) {
            return $result;
        }

        $header = get_file_data($this->pluginFile, [
            'Name' => 'Plugin Name',
            'RequiresWP' => 'Requires at least',
            'RequiresPHP' => 'Requires PHP',
            'Author' => 'Author',
            'AuthorURI' => 'Author URI',
        ]);

        return (object) [
            'name' => $header['Name'],
            'slug' => self::SLUG,
            'version' => $release->version,
            'author' => sprintf('<a href="%s">%s</a>', esc_url($header['AuthorURI']), esc_html($header['Author'])),
            'homepage' => 'https://github.com/' . self::REPOSITORY,
            'requires' => $header['RequiresWP'],
            'requires_php' => $header['RequiresPHP'],
            'last_updated' => $release->publishedAt,
            'download_link' => $release->packageUrl,
            'sections' => ['changelog' => $this->changelog($release)],
        ];
    }

    /**
     * Downloads a private repository's release zip. GitHub's API needs the
     * token, then redirects to a signed address that must not get it.
     *
     * @return bool|string|WP_Error
     */
    public function downloadPrivatePackage(mixed $reply, mixed $package): mixed
    {
        if ($reply !== false || ! is_string($package) || ! $this->releases->isPrivateAsset($package)) {
            return $reply;
        }

        $response = wp_safe_remote_get($package, [
            'headers' => $this->releases->assetDownloadHeaders(),
            'redirection' => 0,
            'timeout' => 30,
        ]);
        if (is_wp_error($response)) {
            return $response;
        }

        $location = wp_remote_retrieve_header($response, 'location');
        $location = is_array($location) ? (string) end($location) : $location;
        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status < 300 || $status >= 400 || ! str_starts_with($location, 'https://')) {
            return new WP_Error(
                'agent_studio_download_failed',
                sprintf(
                    /* translators: %d: HTTP status code. */
                    __('GitHub would not hand over the Agent Studio update (status %d). Check that the access token can read the repository.', 'agent-studio'),
                    $status
                )
            );
        }

        if (! function_exists('download_url')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        return download_url($location, 300);
    }

    /**
     * Makes sure an unpacked update really is this plugin, and that it lands in
     * the folder the plugin is installed in now, so it stays active.
     *
     * @param string|WP_Error      $source
     * @param array<string, mixed> $hookExtra
     *
     * @return string|WP_Error
     */
    public function checkPackage(mixed $source, string $remoteSource, mixed $upgrader, array $hookExtra = []): mixed
    {
        if (! is_string($source) || ($hookExtra['plugin'] ?? null) !== $this->basename()) {
            return $source;
        }

        global $wp_filesystem;
        if (! $wp_filesystem instanceof WP_Filesystem_Base) {
            return $source;
        }

        $source = trailingslashit($source);
        if (! $wp_filesystem->exists($source . basename($this->pluginFile))) {
            return new WP_Error(
                'agent_studio_bad_package',
                __('The downloaded update does not contain Agent Studio, so it was not installed.', 'agent-studio')
            );
        }

        $folder = dirname($this->basename());
        if ($folder === '.' || basename(untrailingslashit($source)) === $folder) {
            return $source;
        }

        $target = trailingslashit($remoteSource) . $folder;
        if (! $wp_filesystem->move(untrailingslashit($source), $target, true)) {
            return new WP_Error(
                'agent_studio_bad_package',
                __('The Agent Studio update could not be unpacked into its folder.', 'agent-studio')
            );
        }

        return trailingslashit($target);
    }

    /**
     * @param array<string, mixed> $hookExtra
     */
    public function forgetAfterUpgrade(mixed $upgrader, array $hookExtra): void
    {
        $plugins = (array) ($hookExtra['plugins'] ?? []);
        if (isset($hookExtra['plugin'])) {
            $plugins[] = $hookExtra['plugin'];
        }

        if (($hookExtra['type'] ?? '') === 'plugin' && in_array($this->basename(), $plugins, true)) {
            delete_site_transient(self::CACHE);
        }
    }

    /**
     * The newest release, asked of GitHub at most every few hours. "Check
     * again" on the Updates screen asks right away.
     */
    public function latest(): ?Release
    {
        $cached = get_site_transient(self::CACHE);
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- mirrors core's own "Check again" link, which has no nonce; it only refreshes a cache.
        $forced = is_admin() && isset($_GET['force-check']) && current_user_can('update_plugins');
        if (! $forced && is_array($cached) && array_key_exists('release', $cached)) {
            return is_array($cached['release']) ? Release::fromArray($cached['release']) : null;
        }

        try {
            $release = $this->releases->latest();
        } catch (ReleaseLookupFailed) {
            set_site_transient(self::CACHE, ['release' => null], self::RETRY_AFTER);

            return null;
        }

        set_site_transient(self::CACHE, ['release' => $release?->toArray()], self::FRESH_FOR);

        return $release;
    }

    private function basename(): string
    {
        return plugin_basename($this->pluginFile);
    }

    private function changelog(Release $release): string
    {
        $notes = trim($release->notes) === ''
            ? ''
            : wpautop(make_clickable(esc_html($release->notes)));

        return $notes . sprintf(
            '<p><a href="%s">%s</a></p>',
            esc_url($release->pageUrl),
            esc_html__('See this release on GitHub', 'agent-studio')
        );
    }
}
