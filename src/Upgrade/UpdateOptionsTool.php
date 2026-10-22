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
use OpenDxp\MateExtension\Kernel\HostKernel;
use Symfony\AI\Mate\Attribute\MateTool;
use Symfony\AI\Mate\Encoding\ResponseEncoder;

use function count;
use function file_get_contents;
use function is_array;
use function is_file;
use function json_decode;
use function ksort;

final readonly class UpdateOptionsTool
{
    public function __construct(
        private HostKernel $host,
        private ReleaseManifest $manifest,
    ) {
    }

    #[MateTool(
        name: 'opendxp_update_options',
        description: 'Lists the newer releases of every installed OpenDXP package: the newest one composer update brings within the constraint of composer.json, and the newest one overall. Each comes with its highlights and how many tasks, breaking changes and opt-in features lie on the way, all from the changelogs.',
    )]
    public function __invoke(): string
    {
        return ResponseEncoder::encodeUntrusted($this->describe());
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(): array
    {
        $packages = $this->manifest->getPackages();

        if (null === $packages) {
            return ['error' => 'The documentation site did not answer with its release manifest.'];
        }

        $constraints = $this->getRequiredConstraints();
        $options = [];
        $upToDate = [];

        foreach ($packages as $name => $releases) {
            $installed = InstalledVersions::isInstalled($name) ? (string) InstalledVersions::getVersion($name) : null;

            if (null === $installed || 'dev' === VersionParser::parseStability($installed)) {
                continue;
            }

            $constraint = $constraints[$name] ?? null;
            $allowed = null !== $constraint ? $releases->getNewest($constraint) : null;
            $newest = $releases->getNewest();

            if (null === $newest || !Comparator::greaterThan($newest['normalized'], $installed)) {
                $upToDate[] = $name;

                continue;
            }

            $options[$name] = [
                'installed'  => InstalledVersions::getPrettyVersion($name),
                'constraint' => $constraint,
                'update'     => null !== $allowed && Comparator::greaterThan($allowed['normalized'], $installed)
                    ? $this->summarize($releases, $installed, $allowed)
                    : null,
                'newest'     => $newest !== $allowed ? $this->summarize($releases, $installed, $newest) : null,
            ];
        }

        ksort($options);

        return [
            'newer_releases' => $options,
            'up_to_date'     => $upToDate,
        ];
    }

    /**
     * @param array<string, mixed> $target
     *
     * @return array<string, mixed>
     */
    private function summarize(PackageReleases $releases, string $installed, array $target): array
    {
        $changes = Changes::fromVersions($releases->getVersionsBetween($installed, $target['normalized']));

        return [
            'version'          => $target['version'],
            'released'         => $target['released'] ?? null,
            'requires_opendxp' => $target['requires'] ?? null,
            'highlights'       => $changes->highlights,
            'tasks'            => count($changes->tasks),
            'breaking_changes' => $changes->breaks,
            'offers'           => count($changes->offers),
            'migrations'       => $changes->migrates,
        ];
    }

    /**
     * @return array<string, string> The constraint the composer.json of the project sets for each package.
     */
    private function getRequiredConstraints(): array
    {
        $file = $this->host->getProjectDir() . '/composer.json';
        $composer = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        if (!is_array($composer)) {
            return [];
        }

        return [...$composer['require-dev'] ?? [], ...$composer['require'] ?? []];
    }
}
