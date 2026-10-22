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

use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use UnexpectedValueException;

use function array_filter;
use function array_map;
use function array_reverse;
use function array_values;
use function is_array;
use function is_string;
use function sprintf;

final readonly class PackageReleases
{
    /**
     * @param list<string>               $migrationNamespaces
     * @param list<array<string, mixed>> $versions
     */
    private function __construct(
        public ?string $changelog,
        private array $migrationNamespaces,
        private array $versions,
    ) {
    }

    /**
     * @param array<string, mixed> $package
     */
    public static function fromManifest(array $package, ?string $changelog): self
    {
        $parser = new VersionParser();
        $versions = [];

        foreach (is_array($package['versions'] ?? null) ? $package['versions'] : [] as $version) {
            // A version without a release date has no tag yet, so no project can have it installed.
            if (!is_array($version) || !is_string($version['version'] ?? null) || null === ($version['released'] ?? null)) {
                continue;
            }

            try {
                $versions[] = [...$version, 'normalized' => $parser->normalize($version['version'])];
            } catch (UnexpectedValueException) {
                continue;
            }
        }

        $namespaces = is_array($package['migrations'] ?? null) ? $package['migrations'] : [];

        return new self($changelog, array_values(array_filter($namespaces, is_string(...))), $versions);
    }

    /**
     * @param string $after  A normalized version, as Composer stores it.
     * @param string $upTo   A normalized version, as Composer stores it.
     *
     * @return list<array<string, mixed>> The versions in the order they were released.
     */
    public function getVersionsBetween(string $after, string $upTo): array
    {
        return array_reverse(array_values(array_filter(
            $this->versions,
            static fn (array $version): bool => Comparator::greaterThan($version['normalized'], $after)
                && Comparator::lessThanOrEqualTo($version['normalized'], $upTo),
        )));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getNewest(?string $constraint = null): ?array
    {
        foreach ($this->versions as $version) {
            if (null === $constraint || Semver::satisfies($version['version'], $constraint)) {
                return $version;
            }
        }

        return null;
    }

    /**
     * A command without a prefix would run every pending migration of the project, so an unknown namespace gives none.
     *
     * @return list<string>|null
     */
    public function getMigrateCommands(): ?array
    {
        if ([] === $this->migrationNamespaces) {
            return null;
        }

        return array_map(
            static fn (string $namespace): string => sprintf("bin/console doctrine:migrations:migrate --prefix '%s'", $namespace),
            $this->migrationNamespaces,
        );
    }
}
