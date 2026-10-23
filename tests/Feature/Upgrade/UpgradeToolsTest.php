<?php

declare(strict_types=1);

namespace OpenDxp\MateExtension\Tests\Feature\Upgrade;

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use OpenDxp\MateExtension\Kernel\HostKernel;
use OpenDxp\MateExtension\Upgrade\ReleaseManifest;
use OpenDxp\MateExtension\Upgrade\UpdateOptionsTool;
use OpenDxp\MateExtension\Upgrade\UpgradePlanTool;
use Symfony\AI\Mate\Encoding\ResponseEncoder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Process\Process;

function entry(string $type, array $markers = [], array $steps = []): array
{
    return ['type' => $type, 'markers' => $markers, 'text' => $type . ' ' . implode(' ', $markers), 'announcement' => null, 'steps' => $steps];
}

function manifest(array $migrations = ['OpenDxp\Bundle\ToolboxBundle\Migrations']): ReleaseManifest
{
    $highlight = ['type' => 'NEW FEATURE', 'markers' => ['HIGHLIGHT'], 'text' => 'Something big', 'announcement' => 'Try it.', 'steps' => []];

    return new ReleaseManifest(new MockHttpClient(new JsonMockResponse(['packages' => [[
        'package'    => 'open-dxp/toolbox-bundle',
        'changelog'  => ['latest' => '/docs/bundles/toolbox/CHANGELOG/'],
        'migrations' => $migrations,
        'versions'   => [
            ['version' => '2.0.0', 'released' => '2026-10-01', 'requires' => '^1.5', 'entries' => [entry('BC BREAK', [], ['Rename it.']), $highlight]],
            ['version' => '1.1.0', 'released' => '2026-09-22', 'requires' => '^1.4', 'entries' => [
                entry('NEW FEATURE', ['OPT-IN'], ['Enable it.']),
                entry('CHORE', ['FOLLOW-UP', 'MIGRATION'], ['Clear the cache.']),
                entry('DEPRECATION'),
                entry('BUGFIX'),
            ]],
            ['version' => '1.0.1', 'released' => '2026-07-01', 'requires' => '^1.1', 'entries' => [entry('BUGFIX', ['FOLLOW-UP'], ['Rebuild.'])]],
            ['version' => '1.0.0', 'released' => '2026-06-01', 'requires' => '^1.1', 'entries' => [entry('NEW FEATURE', ['FOLLOW-UP'], ['Old.'])]],
        ],
    ]]])), 'https://docs.example.com');
}

/**
 * Runs a tool in a project whose last commit locks the versions of $locked and whose vendor holds $installed.
 * $applicationPath places the project in a subdirectory of the repository.
 */
function inProject(array $installed, array $locked, array $required, callable $run, string $applicationPath = ''): array
{
    $repositoryDir = sys_get_temp_dir() . '/mate-upgrade-' . bin2hex(random_bytes(4));
    $projectDir = rtrim($repositoryDir . '/' . $applicationPath, '/');
    $filesystem = new Filesystem();
    $filesystem->dumpFile($projectDir . '/composer.json', json_encode(['require' => $required]));
    $filesystem->dumpFile($projectDir . '/composer.lock', json_encode([
        'packages' => array_map(static fn (string $name, string $version): array => ['name' => $name, 'version' => $version], array_keys($locked), $locked),
    ]));

    foreach ([['init', '-q'], ['add', '.'], ['-c', 'user.name=Test', '-c', 'user.email=test@example.com', 'commit', '-q', '-m', 'lock']] as $arguments) {
        (new Process(['git', ...$arguments], $repositoryDir))->mustRun();
    }

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

    try {
        return ResponseEncoder::decode($run(new HostKernel($projectDir)))['untrusted_data'];
    } finally {
        InstalledVersions::reload($original);
        $filesystem->remove($repositoryDir);
    }
}

it('plans the versions between the locked and the installed one', function () {
    $plan = inProject(['open-dxp/toolbox-bundle' => '1.1.0'], ['open-dxp/toolbox-bundle' => 'v1.0.0'], [], static fn (HostKernel $host): string => (new UpgradePlanTool($host, manifest()))());

    expect($plan['updated']['open-dxp/toolbox-bundle'])->toMatchArray([
        'to'            => '1.1.0',
        'migrate'       => ["bin/console doctrine:migrations:migrate --prefix 'OpenDxp\Bundle\ToolboxBundle\Migrations'"],
        'other_changes' => 1,
        'changelog'     => 'https://docs.example.com/docs/bundles/toolbox/CHANGELOG/',
    ])
        ->and(array_column($plan['updated']['open-dxp/toolbox-bundle']['tasks'], 'steps'))->toBe([['Rebuild.'], ['Clear the cache.']])
        ->and($plan['updated']['open-dxp/toolbox-bundle']['offers'][0]['steps'])->toBe(['Enable it.'])
        ->and($plan['updated']['open-dxp/toolbox-bundle']['deprecations'])->toHaveCount(1);
});

it('reads the lock of a project in a subdirectory of the repository', function () {
    $plan = inProject(['open-dxp/toolbox-bundle' => '1.1.0'], ['open-dxp/toolbox-bundle' => 'v1.0.0'], [], static fn (HostKernel $host): string => (new UpgradePlanTool($host, manifest()))(), 'backend/app');

    expect($plan['updated']['open-dxp/toolbox-bundle']['to'])->toBe('1.1.0');
});

it('leaves out a package the update did not change', function () {
    $plan = inProject(['open-dxp/toolbox-bundle' => '1.1.0'], ['open-dxp/toolbox-bundle' => '1.1.0'], [], static fn (HostKernel $host): string => (new UpgradePlanTool($host, manifest()))());

    expect($plan['updated'])->toBe([]);
});

it('gives no migrate command when the namespace of the migrations is unknown', function () {
    $plan = inProject(['open-dxp/toolbox-bundle' => '1.1.0'], ['open-dxp/toolbox-bundle' => '1.0.1'], [], static fn (HostKernel $host): string => (new UpgradePlanTool($host, manifest([])))());

    expect($plan['updated']['open-dxp/toolbox-bundle']['migrate'])->toBeNull();
});

it('names a package updated from a branch instead of planning it', function () {
    $plan = inProject(['open-dxp/toolbox-bundle' => 'dev-1.x'], ['open-dxp/toolbox-bundle' => '1.0.0'], [], static fn (HostKernel $host): string => (new UpgradePlanTool($host, manifest()))());

    expect($plan['updated'])->toBe([])
        ->and($plan['updated_from_a_branch'])->toBe(['open-dxp/toolbox-bundle']);
});

it('refuses a commit without a composer.lock', function () {
    $plan = inProject([], [], [], static fn (HostKernel $host): string => (new UpgradePlanTool($host, manifest()))('nothing'));

    expect($plan['error'])->toBe('The commit "nothing" has no composer.lock. Pass the commit from before the update.');
});

it('offers the update within the constraint and the newest release beyond it', function () {
    $options = inProject(['open-dxp/toolbox-bundle' => '1.0.0'], [], ['open-dxp/toolbox-bundle' => '^1.0'], static fn (HostKernel $host): string => (new UpdateOptionsTool($host, manifest()))());

    expect($options['newer_releases']['open-dxp/toolbox-bundle']['update'])->toMatchArray([
        'version'          => '1.1.0',
        'tasks'            => 2,
        'breaking_changes' => 0,
        'offers'           => 1,
        'migrations'       => true,
    ])
        ->and($options['newer_releases']['open-dxp/toolbox-bundle']['newest'])->toMatchArray([
            'version'          => '2.0.0',
            'requires_opendxp' => '^1.5',
            'breaking_changes' => 1,
            'highlights'       => [['version' => '2.0.0', 'text' => 'Something big', 'announcement' => 'Try it.']],
        ]);
});

it('names a package installed from a branch', function () {
    $options = inProject(['open-dxp/toolbox-bundle' => 'dev-1.x'], [], [], static fn (HostKernel $host): string => (new UpdateOptionsTool($host, manifest()))());

    expect($options['installed_from_a_branch'])->toBe(['open-dxp/toolbox-bundle']);
});

it('names a package that is up to date', function () {
    $options = inProject(['open-dxp/toolbox-bundle' => '2.0.0'], [], ['open-dxp/toolbox-bundle' => '^2.0'], static fn (HostKernel $host): string => (new UpdateOptionsTool($host, manifest()))());

    expect($options['newer_releases'])->toBe([])
        ->and($options['up_to_date'])->toBe(['open-dxp/toolbox-bundle']);
});
