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
use Symfony\Component\Process\Process;

use function is_array;
use function is_string;
use function json_decode;
use function ksort;
use function sort;
use function sprintf;

final readonly class UpgradePlanTool
{
    public function __construct(
        private HostKernel $host,
        private ReleaseManifest $manifest,
    ) {
    }

    /**
     * @param string $from The commit whose composer.lock holds the versions from before the update.
     */
    #[MateTool(
        name: 'opendxp_upgrade_plan',
        description: 'Call it after composer update. It compares the composer.lock of a commit with the installed packages and lists, for every OpenDXP package that changed, the tasks after the update, the commands for the migrations, the deprecations and the opt-in features, all from the changelogs.',
    )]
    public function __invoke(string $from = 'HEAD'): string
    {
        return ResponseEncoder::encodeUntrusted($this->plan($from));
    }

    /**
     * @return array<string, mixed>
     */
    private function plan(string $from): array
    {
        $before = $this->getLockedVersions($from);

        if (null === $before) {
            return ['error' => sprintf('The commit "%s" has no composer.lock. Pass the commit from before the update.', $from)];
        }

        $packages = $this->manifest->getPackages();

        if (null === $packages) {
            return ['error' => 'The documentation site did not answer with its release manifest.'];
        }

        $plans = [];
        $fromBranch = [];

        foreach ($packages as $name => $releases) {
            $old = $before[$name] ?? null;

            if (null === $old || !InstalledVersions::isInstalled($name)) {
                continue;
            }

            $new = (string) InstalledVersions::getVersion($name);

            // A branch has no version, so the changelog cannot tell what the update brought.
            if ('dev' === VersionParser::parseStability($old) || 'dev' === VersionParser::parseStability($new)) {
                if ($old !== $new) {
                    $fromBranch[] = $name;
                }

                continue;
            }

            if (!Comparator::greaterThan($new, $old)) {
                continue;
            }

            $changes = Changes::fromVersions($releases->getVersionsBetween($old, $new));

            $plans[$name] = [
                'from'          => $old,
                'to'            => InstalledVersions::getPrettyVersion($name),
                'tasks'         => $changes->tasks,
                'migrate'       => $changes->migrates ? $releases->getMigrateCommands() : [],
                'deprecations'  => $changes->deprecations,
                'offers'        => $changes->offers,
                'other_changes' => $changes->others,
                'changelog'     => $releases->changelog,
            ];
        }

        ksort($plans);
        sort($fromBranch);

        return [
            'compared_with'         => $from,
            'updated'               => $plans,
            'updated_from_a_branch' => $fromBranch,
        ];
    }

    /**
     * @return array<string, string>|null The normalized version of every locked package.
     */
    private function getLockedVersions(string $commit): ?array
    {
        $process = new Process(['git', 'show', sprintf('%s:./composer.lock', $commit)], $this->host->getProjectDir());
        $process->run();

        $lock = $process->isSuccessful() ? json_decode($process->getOutput(), true) : null;

        if (!is_array($lock)) {
            return null;
        }

        $parser = new VersionParser();
        $versions = [];

        foreach ([...$lock['packages'] ?? [], ...$lock['packages-dev'] ?? []] as $package) {
            if (is_array($package) && is_string($package['name'] ?? null) && is_string($package['version'] ?? null)) {
                $versions[$package['name']] = $parser->normalize($package['version']);
            }
        }

        return $versions;
    }
}
