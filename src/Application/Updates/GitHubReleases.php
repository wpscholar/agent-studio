<?php

declare(strict_types=1);

namespace AgentStudio\Application\Updates;

use AgentStudio\Contracts\Http\HttpClient;
use AgentStudio\Contracts\Http\HttpException;
use AgentStudio\Contracts\Http\HttpRequest;
use AgentStudio\Domain\Update\Release;

/**
 * Finds the newest published release of the plugin on GitHub.
 *
 * Only the zip that the release workflow attaches counts: "<slug>-<version>.zip".
 * GitHub's own source archives lack vendor/ and the built assets, so a release
 * without that zip offers no update at all. Drafts and pre-releases are skipped
 * because GitHub's "latest release" never returns them.
 *
 * A token is needed only while the repository is private. With one, the
 * package is the asset's API address, which must be fetched with the token.
 */
final class GitHubReleases
{
    public const API = 'https://api.github.com';

    public function __construct(
        private readonly HttpClient $http,
        private readonly string $repository,
        private readonly string $slug,
        private readonly string $token = '',
    ) {
    }

    /**
     * @return Release|null Null when nothing installable has been published.
     *
     * @throws ReleaseLookupFailed
     */
    public function latest(): ?Release
    {
        try {
            $response = $this->http->send(new HttpRequest(
                'GET',
                self::API . '/repos/' . $this->repository . '/releases/latest',
                $this->headers('application/vnd.github+json'),
                '',
                10
            ));
        } catch (HttpException $e) {
            throw new ReleaseLookupFailed('GitHub could not be reached: ' . $e->getMessage(), 0, $e);
        }

        if ($response->status === 404) {
            return null;
        }

        if (! $response->ok()) {
            throw new ReleaseLookupFailed(sprintf('GitHub answered with status %d.', $response->status));
        }

        $data = $response->json();
        if ($data === null) {
            throw new ReleaseLookupFailed('GitHub sent a release we could not read.');
        }

        if (! empty($data['draft']) || ! empty($data['prerelease'])) {
            return null;
        }

        $version = self::version($data['tag_name'] ?? null);
        if ($version === null) {
            return null;
        }

        $package = $this->package($data['assets'] ?? null, $version);
        if ($package === null) {
            return null;
        }

        return new Release(
            $version,
            $package,
            is_string($data['html_url'] ?? null) ? $data['html_url'] : 'https://github.com/' . $this->repository . '/releases',
            is_string($data['body'] ?? null) ? $data['body'] : '',
            is_string($data['published_at'] ?? null) ? $data['published_at'] : '',
        );
    }

    /**
     * Whether a package address is one of this repository's release assets on the API,
     * which can only be downloaded with the token.
     */
    public function isPrivateAsset(string $url): bool
    {
        return $this->token !== ''
            && str_starts_with($url, self::API . '/repos/' . $this->repository . '/releases/assets/');
    }

    /**
     * Headers for downloading a private asset. GitHub answers with a redirect to
     * a signed address, which must be followed without these headers.
     *
     * @return array<string, string>
     */
    public function assetDownloadHeaders(): array
    {
        return $this->headers('application/octet-stream');
    }

    public function hasToken(): bool
    {
        return $this->token !== '';
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $accept): array
    {
        $headers = [
            'Accept' => $accept,
            'X-GitHub-Api-Version' => '2022-11-28',
        ];
        if ($this->token !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }

        return $headers;
    }

    private static function version(mixed $tag): ?string
    {
        if (! is_string($tag)) {
            return null;
        }

        $version = ltrim(trim($tag), 'vV');

        return preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version) === 1 ? $version : null;
    }

    private function package(mixed $assets, string $version): ?string
    {
        if (! is_array($assets)) {
            return null;
        }

        $name = $this->slug . '-' . $version . '.zip';
        foreach ($assets as $asset) {
            if (! is_array($asset) || ($asset['name'] ?? null) !== $name) {
                continue;
            }
            if (($asset['state'] ?? 'uploaded') !== 'uploaded') {
                return null;
            }

            $url = $this->token !== '' ? ($asset['url'] ?? null) : ($asset['browser_download_url'] ?? null);

            return is_string($url) && str_starts_with($url, 'https://') ? $url : null;
        }

        return null;
    }
}
