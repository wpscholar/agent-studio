<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Unit;

use PHPUnit\Framework\TestCase;
use AgentStudio\Application\Updates\GitHubReleases;
use AgentStudio\Application\Updates\ReleaseLookupFailed;
use AgentStudio\Contracts\Http\HttpResponse;
use AgentStudio\Domain\Update\Release;
use AgentStudio\Tests\Support\FakeHttpClient;

final class GitHubReleasesTest extends TestCase
{
    private const LATEST = 'https://api.github.com/repos/wpscholar/agent-studio/releases/latest';

    public function testFindsTheReleaseZip(): void
    {
        $http = new FakeHttpClient(static fn () => FakeHttpClient::json(self::release()));

        $release = (new GitHubReleases($http, 'wpscholar/agent-studio', 'agent-studio'))->latest();

        $this->assertNotNull($release);
        $this->assertSame('1.2.0', $release->version);
        $this->assertSame('https://github.com/wpscholar/agent-studio/releases/download/1.2.0/agent-studio-1.2.0.zip', $release->packageUrl);
        $this->assertSame('https://github.com/wpscholar/agent-studio/releases/tag/1.2.0', $release->pageUrl);
        $this->assertSame('Fixes things.', $release->notes);
        $this->assertSame('2026-11-01T12:00:00Z', $release->publishedAt);

        $request = $http->requests[0];
        $this->assertSame('GET', $request->method);
        $this->assertSame(self::LATEST, $request->url);
        $this->assertSame('application/vnd.github+json', $request->headers['Accept']);
        $this->assertArrayNotHasKey('Authorization', $request->headers);
    }

    public function testWithATokenUsesTheApiAssetAddress(): void
    {
        $http = new FakeHttpClient(static fn () => FakeHttpClient::json(self::release()));
        $releases = new GitHubReleases($http, 'wpscholar/agent-studio', 'agent-studio', 'secret');

        $release = $releases->latest();

        $this->assertSame('Bearer secret', $http->requests[0]->headers['Authorization']);
        $this->assertNotNull($release);
        $this->assertSame('https://api.github.com/repos/wpscholar/agent-studio/releases/assets/42', $release->packageUrl);
        $this->assertTrue($releases->isPrivateAsset($release->packageUrl));
        $this->assertFalse($releases->isPrivateAsset('https://api.github.com/repos/someone/else/releases/assets/42'));
        $this->assertSame('application/octet-stream', $releases->assetDownloadHeaders()['Accept']);
    }

    public function testWithoutATokenNothingIsAPrivateAsset(): void
    {
        $releases = new GitHubReleases(new FakeHttpClient(static fn () => null), 'wpscholar/agent-studio', 'agent-studio');

        $this->assertFalse($releases->isPrivateAsset('https://api.github.com/repos/wpscholar/agent-studio/releases/assets/42'));
    }

    public function testAcceptsAVPrefixOnTheTag(): void
    {
        $data = self::release();
        $data['tag_name'] = 'v1.2.0';

        $release = self::lookup($data);

        $this->assertNotNull($release);
        $this->assertSame('1.2.0', $release->version);
    }

    /**
     * @return array<string, array{0: callable(array<string, mixed>): array<string, mixed>}>
     */
    public static function uninstallable(): array
    {
        return [
            'only source archives' => [static fn (array $d): array => ['assets' => []] + $d],
            'zip for another version' => [static function (array $d): array {
                $d['assets'][0]['name'] = 'agent-studio-1.1.0.zip';
                return $d;
            }],
            'zip still uploading' => [static function (array $d): array {
                $d['assets'][0]['state'] = 'starter';
                return $d;
            }],
            'tag is not a version' => [static fn (array $d): array => ['tag_name' => 'nightly'] + $d],
            'pre-release' => [static fn (array $d): array => ['prerelease' => true] + $d],
            'draft' => [static fn (array $d): array => ['draft' => true] + $d],
            'download is not https' => [static function (array $d): array {
                $d['assets'][0]['browser_download_url'] = 'http://example.com/agent-studio-1.2.0.zip';
                return $d;
            }],
        ];
    }

    /**
     * @dataProvider uninstallable
     *
     * @param callable(array<string, mixed>): array<string, mixed> $change
     */
    public function testOffersNothingWithoutAnInstallableZip(callable $change): void
    {
        $this->assertNull(self::lookup($change(self::release())));
    }

    public function testNoReleasesYetIsNotAFailure(): void
    {
        $http = new FakeHttpClient(static fn () => FakeHttpClient::json(['message' => 'Not Found'], 404));

        $this->assertNull((new GitHubReleases($http, 'wpscholar/agent-studio', 'agent-studio'))->latest());
    }

    /**
     * @return array<string, array{0: callable(): (HttpResponse|null)}>
     */
    public static function failures(): array
    {
        return [
            'unreachable' => [static fn () => null],
            'rate limited' => [static fn () => FakeHttpClient::json(['message' => 'API rate limit exceeded'], 403)],
            'server error' => [static fn () => new HttpResponse(502, [], 'Bad gateway')],
            'not json' => [static fn () => new HttpResponse(200, [], '<html>')],
        ];
    }

    /**
     * @dataProvider failures
     *
     * @param callable(): (HttpResponse|null) $answer
     */
    public function testReportsLookupFailures(callable $answer): void
    {
        $this->expectException(ReleaseLookupFailed::class);

        (new GitHubReleases(new FakeHttpClient($answer), 'wpscholar/agent-studio', 'agent-studio'))->latest();
    }

    public function testReleaseComparesVersionsAndRoundTrips(): void
    {
        $release = new Release('1.10.0', 'https://x/p.zip', 'https://x/r', 'Notes', '2026-11-01');

        $this->assertTrue($release->isNewerThan('1.9.3'));
        $this->assertFalse($release->isNewerThan('1.10.0'));
        $this->assertTrue((new Release('2.0.0', 'p', 'r'))->isNewerThan('2.0.0-rc.1'));
        $this->assertEquals($release, Release::fromArray($release->toArray()));
        $this->assertNull(Release::fromArray(['version' => '1.0.0']));
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function lookup(array $data): ?Release
    {
        return (new GitHubReleases(new FakeHttpClient(static fn () => FakeHttpClient::json($data)), 'wpscholar/agent-studio', 'agent-studio'))->latest();
    }

    /**
     * @return array<string, mixed>
     */
    private static function release(): array
    {
        return [
            'tag_name' => '1.2.0',
            'draft' => false,
            'prerelease' => false,
            'html_url' => 'https://github.com/wpscholar/agent-studio/releases/tag/1.2.0',
            'body' => 'Fixes things.',
            'published_at' => '2026-11-01T12:00:00Z',
            'assets' => [
                [
                    'name' => 'agent-studio-1.2.0.zip',
                    'state' => 'uploaded',
                    'url' => 'https://api.github.com/repos/wpscholar/agent-studio/releases/assets/42',
                    'browser_download_url' => 'https://github.com/wpscholar/agent-studio/releases/download/1.2.0/agent-studio-1.2.0.zip',
                ],
            ],
        ];
    }
}
