<?php

declare(strict_types=1);

/**
 * OpenDXP
 *
 * This source file is licensed under the GNU General Public License version 3 (GPLv3).
 *
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) OpenDXP (https://www.opendxp.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0.html  GNU General Public License version 3 (GPLv3)
 */

namespace OpenDxp\MateExtension\Upgrade;

use Composer\InstalledVersions;
use Composer\Semver\Comparator;
use Composer\Semver\VersionParser;
use Symfony\AI\Mate\Attribute\MateTool;
use Symfony\AI\Mate\Encoding\ResponseEncoder;

use function array_filter;
use function array_map;
use function array_reverse;
use function array_values;
use function count;
use function end;
use function in_array;
use function is_array;
use function is_string;
use function sort;
use function sprintf;
use function usort;

final readonly class UpgradePlanTool
{
    private const array CHANNELS = ['latest', 'next'];

    public function __construct(private ReleaseManifest $manifest)
    {
    }

    /**
     * @param string|null $package A composer package such as "open-dxp/toolbox-bundle". Without it, the plan covers every installed OpenDXP package.
     * @param string      $channel "latest" plans the update to the released versions. "next" adds the changes that are merged and not released yet, for a project that runs the development branches.
     */
    #[MateTool(
        name: 'opendxp_upgrade_plan',
        description: 'Plans the update of the installed OpenDXP packages from their changelogs. For each package it names the installed and the target version, the tasks every project does after the update, the opt-in features with their steps, the deprecations and the commands for the migrations. Every item is a changelog entry.',
    )]
    public function __invoke(?string $package = null, string $channel = 'latest'): string
    {
        return ResponseEncoder::encodeUntrusted($this->plan($package, $channel));
    }

    /**
     * @return array<string, mixed>
     */
    private function plan(?string $package, string $channel): array
    {
        if (!in_array($channel, self::CHANNELS, true)) {
            return ['error' => sprintf('There is no channel "%s". Use "latest" or "next".', $channel)];
        }

        if (null !== $package && !InstalledVersions::isInstalled($package)) {
            return ['error' => sprintf('The package "%s" is not installed.', $package)];
        }

        $manifest = $this->manifest->load();

        if (null === $manifest) {
            return ['error' => 'The documentation site did not answer with its release manifest.'];
        }

        $plans = [];
        $upToDate = [];
        $fromBranch = [];

        foreach ($this->getList($manifest['packages'] ?? null) as $release) {
            $name = $release['package'] ?? null;

            if (!is_string($name) || !InstalledVersions::isInstalled($name) || (null !== $package && $package !== $name)) {
                continue;
            }

            $version = InstalledVersions::getPrettyVersion($name);

            // A branch has no version to start from, so the changelog cannot tell what it already contains.
            if (null === $version || 'dev' === VersionParser::parseStability($version)) {
                $fromBranch[] = $name;

                continue;
            }

            $versions = $this->getVersionsAfter($this->getList($release['versions'] ?? null), (string) InstalledVersions::getVersion($name), $channel);

            if ([] === $versions) {
                $upToDate[] = $name;

                continue;
            }

            $plans[] = $this->describe($release, $name, $version, $versions, $channel);
        }

        usort($plans, static fn (array $a, array $b): int => $a['package'] <=> $b['package']);
        sort($upToDate);
        sort($fromBranch);

        return [
            'channel'                 => $channel,
            'packages'                => $plans,
            'up_to_date'              => $upToDate,
            'installed_from_a_branch' => $fromBranch,
        ];
    }

    /**
     * The manifest lists the newest version first. The plan takes them in the order they were released.
     *
     * @param list<array<string, mixed>> $versions
     * @param string                     $installed The normalized version, as Composer stores it.
     *
     * @return list<array<string, mixed>>
     */
    private function getVersionsAfter(array $versions, string $installed, string $channel): array
    {
        $parser = new VersionParser();
        $after = array_filter($versions, static function (array $version) use ($parser, $installed, $channel): bool {
            if (null === ($version['released'] ?? null)) {
                return 'next' === $channel;
            }

            return is_string($version['version'] ?? null) && Comparator::greaterThan($parser->normalize($version['version']), $installed);
        });

        return array_reverse(array_values($after));
    }

    /**
     * @param array<string, mixed>       $release
     * @param list<array<string, mixed>> $versions
     *
     * @return array<string, mixed>
     */
    private function describe(array $release, string $name, string $installed, array $versions, string $channel): array
    {
        $tasks = [];
        $offers = [];
        $deprecations = [];
        $others = 0;
        $migrates = false;

        foreach ($versions as $version) {
            foreach ($this->getList($version['entries'] ?? null) as $entry) {

                $type = $entry['type'] ?? null;
                $markers = is_array($entry['markers'] ?? null) ? $entry['markers'] : [];

                $item = [
                    'version' => $version['version'] ?? null,
                    'type'    => $type,
                    'text'    => $entry['text'] ?? null,
                    'steps'   => is_array($entry['steps'] ?? null) ? $entry['steps'] : [],
                ];

                $migrates = $migrates || in_array('MIGRATION', $markers, true);

                if ('BC BREAK' === $type || in_array('FOLLOW-UP', $markers, true)) {
                    $tasks[] = $item;
                } elseif (in_array('OPT-IN', $markers, true)) {
                    $offers[] = $item;
                } elseif ('DEPRECATION' === $type) {
                    $deprecations[] = $item;
                } else {
                    ++$others;
                }
            }
        }

        $target = end($versions);
        $changelog = $release['changelog'][$channel] ?? null;

        return [
            'package'          => $name,
            'installed'        => $installed,
            'target'           => $target['version'] ?? null,
            'requires_opendxp' => $target['requires'] ?? null,
            'tasks'            => $tasks,
            'offers'           => $offers,
            'deprecations'     => $deprecations,
            'migrate'          => $migrates ? $this->getMigrateCommands($release['migrations'] ?? null) : [],
            'other_changes'    => $others,
            'changelog'        => is_string($changelog) ? $this->manifest->getAbsoluteUrl($changelog) : null,
        ];
    }

    /**
     * @return list<string>|null
     */
    private function getMigrateCommands(mixed $namespaces): ?array
    {
        $namespaces = array_values(array_filter(is_array($namespaces) ? $namespaces : [], is_string(...)));

        if (0 === count($namespaces)) {
            return null;
        }

        return array_map(
            static fn (string $namespace): string => sprintf("bin/console doctrine:migrations:migrate --prefix '%s'", $namespace),
            $namespaces,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getList(mixed $value): array
    {
        return array_values(array_filter(is_array($value) ? $value : [], is_array(...)));
    }
}
