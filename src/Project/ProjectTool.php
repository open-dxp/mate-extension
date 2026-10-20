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

namespace OpenDxp\MateExtension\Project;

use OpenDxp\MateExtension\Kernel\HostKernel;
use Symfony\AI\Mate\Attribute\MateTool;
use Symfony\AI\Mate\Encoding\ResponseEncoder;
use function array_intersect;
use function array_map;
use function file_get_contents;
use function is_array;
use function is_file;
use function json_decode;
use function ksort;
use function ltrim;

final class ProjectTool
{
    private const string CORE_PACKAGE = 'open-dxp/opendxp';

    private const string BUNDLE_TYPE = 'opendxp-bundle';

    public function __construct(private readonly HostKernel $host)
    {
    }

    #[MateTool(
        name: 'opendxp_project',
        description: 'What this OpenDXP installation is: the core version, the PHP version, the environment, and every installed OpenDXP bundle with its version and whether the kernel has it enabled. Call this once before you answer anything about the stack, instead of assuming a version from documentation.',
    )]
    public function __invoke(): string
    {
        $kernel = $this->host->boot();
        $packages = $this->getInstalledPackages();
        $enabled = array_map(static fn (object $bundle): string => $bundle::class, $kernel->getBundles());

        $bundles = [];

        foreach ($packages as $package) {
            if (self::BUNDLE_TYPE !== ($package['type'] ?? null)) {
                continue;
            }

            $classes = array_map(
                static fn (string $class): string => ltrim($class, '\\'),
                $package['extra']['opendxp']['bundles'] ?? [],
            );

            $bundles[$package['name']] = [
                'version' => $package['version'] ?? null,
                'enabled' => [] !== array_intersect($classes, $enabled),
            ];
        }

        ksort($bundles);

        return ResponseEncoder::encode([
            'opendxp' => [
                'version'     => $packages[self::CORE_PACKAGE]['version'] ?? null,
                'php'         => PHP_VERSION,
                'environment' => $kernel->getEnvironment(),
            ],
            'bundles' => $bundles,
        ]);
    }

    /**
     * Composer keeps the extra section of a package only in installed.json. It names the bundle classes.
     *
     * @return array<string, array<string, mixed>>
     */
    private function getInstalledPackages(): array
    {
        $file = $this->host->getProjectDir() . '/vendor/composer/installed.json';
        $installed = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $packages = [];

        foreach (is_array($installed) ? $installed['packages'] ?? $installed : [] as $package) {
            if (is_array($package) && isset($package['name'])) {
                $packages[$package['name']] = $package;
            }
        }

        return $packages;
    }
}
