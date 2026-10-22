<?php

declare(strict_types=1);

namespace OpenDxp\MateExtension\Tests\Feature\Upgrade;

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use OpenDxp\MateExtension\Upgrade\ReleaseManifest;
use OpenDxp\MateExtension\Upgrade\UpgradePlanTool;
use Symfony\AI\Mate\Encoding\ResponseEncoder;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

function entry(string $type, array $markers = [], array $steps = []): array
{
    return ['type' => $type, 'markers' => $markers, 'text' => $type . ' ' . implode(' ', $markers), 'announcement' => null, 'steps' => $steps];
}

/**
 * @param array<string, string> $installed The pretty version of each installed package.
 */
function plan(array $installed, string $channel = 'latest', ?string $package = null, array $migrations = ['OpenDxp\Bundle\ToolboxBundle\Migrations']): array
{
    $describe = static fn (string $version): array => [
        'pretty_version'  => $version,
        'version'         => (new VersionParser())->normalize($version),
        'reference'       => null,
        'type'            => 'library',
        'install_path'    => __DIR__,
        'aliases'         => [],
        'dev_requirement' => false,
    ];
    $original = InstalledVersions::getAllRawData()[0];

    InstalledVersions::reload([
        'root'     => ['name' => 'project', 'dev' => false, ...$describe('1.0.0')],
        'versions' => array_map($describe, $installed),
    ]);

    $manifest = ['packages' => [
        [
            'package'    => 'open-dxp/toolbox-bundle',
            'changelog'  => ['latest' => '/docs/bundles/toolbox/CHANGELOG/', 'next' => '/docs/next/bundles/toolbox/CHANGELOG/'],
            'migrations' => $migrations,
            'versions'   => [
                ['version' => '1.2.0', 'released' => null, 'requires' => null, 'entries' => [entry('NEW FEATURE', ['FOLLOW-UP'], ['Run it.'])]],
                ['version' => '1.1.0', 'released' => '2026-09-22', 'requires' => '^1.4', 'entries' => [
                    entry('NEW FEATURE', ['OPT-IN'], ['Enable it.']),
                    entry('CHORE', ['FOLLOW-UP', 'MIGRATION'], ['Clear the cache.']),
                    entry('DEPRECATION'),
                    entry('BUGFIX'),
                ]],
                ['version' => '1.0.1', 'released' => '2026-07-01', 'requires' => '^1.1', 'entries' => [entry('BUGFIX', ['FOLLOW-UP'], ['Rebuild.'])]],
                ['version' => '1.0.0', 'released' => '2026-06-01', 'requires' => '^1.1', 'entries' => [entry('NEW FEATURE', ['FOLLOW-UP'], ['Old.'])]],
            ],
        ],
        [
            'package'    => 'open-dxp/news-bundle',
            'changelog'  => ['latest' => '/docs/bundles/news/CHANGELOG/', 'next' => '/docs/next/bundles/news/CHANGELOG/'],
            'migrations' => [],
            'versions'   => [['version' => '1.0.0', 'released' => '2026-06-01', 'requires' => '^1.1', 'entries' => [entry('BUGFIX')]]],
        ],
    ]];

    $tool = new UpgradePlanTool(new ReleaseManifest(new MockHttpClient(new JsonMockResponse($manifest)), 'https://docs.example.com'));

    try {
        return ResponseEncoder::decode($tool($package, $channel))['untrusted_data'];
    } finally {
        InstalledVersions::reload($original);
    }
}

it('plans the released versions after the installed one', function () {
    $plan = plan(['open-dxp/toolbox-bundle' => 'v1.0.0', 'open-dxp/news-bundle' => '1.0.0']);

    expect($plan['up_to_date'])->toBe(['open-dxp/news-bundle'])
        ->and($plan['packages'])->toHaveCount(1)
        ->and($plan['packages'][0])->toMatchArray([
            'package'          => 'open-dxp/toolbox-bundle',
            'installed'        => 'v1.0.0',
            'target'           => '1.1.0',
            'requires_opendxp' => '^1.4',
            'migrate'          => ["bin/console doctrine:migrations:migrate --prefix 'OpenDxp\Bundle\ToolboxBundle\Migrations'"],
            'other_changes'    => 1,
            'changelog'        => 'https://docs.example.com/docs/bundles/toolbox/CHANGELOG/',
        ])
        ->and(array_column($plan['packages'][0]['tasks'], 'version'))->toBe(['1.0.1', '1.1.0'])
        ->and($plan['packages'][0]['tasks'][1]['steps'])->toBe(['Clear the cache.'])
        ->and($plan['packages'][0]['offers'][0]['steps'])->toBe(['Enable it.'])
        ->and($plan['packages'][0]['deprecations'])->toHaveCount(1);
});

it('adds the changes that are not released yet on next', function () {
    $plan = plan(['open-dxp/toolbox-bundle' => '1.1.0'], 'next');

    expect($plan['packages'][0]['target'])->toBe('1.2.0')
        ->and($plan['packages'][0]['tasks'][0]['steps'])->toBe(['Run it.'])
        ->and($plan['packages'][0]['changelog'])->toBe('https://docs.example.com/docs/next/bundles/toolbox/CHANGELOG/');
});

it('gives no migrate command when the namespace of the migrations is unknown', function () {
    $plan = plan(['open-dxp/toolbox-bundle' => '1.0.1'], migrations: []);

    expect($plan['packages'][0]['migrate'])->toBeNull();
});

it('names a package installed from a branch instead of planning it', function () {
    $plan = plan(['open-dxp/toolbox-bundle' => 'dev-1.x']);

    expect($plan['packages'])->toBe([])
        ->and($plan['installed_from_a_branch'])->toBe(['open-dxp/toolbox-bundle']);
});

it('refuses a package that is not installed', function () {
    expect(plan([], 'latest', 'open-dxp/toolbox-bundle'))
        ->toBe(['error' => 'The package "open-dxp/toolbox-bundle" is not installed.']);
});
