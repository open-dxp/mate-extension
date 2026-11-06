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

namespace OpenDxp\MateExtension\Security;

use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;
use UnexpectedValueException;

use function array_filter;
use function array_values;
use function is_array;
use function is_string;
use function rtrim;
use function sprintf;

final readonly class SecurityAdvisories
{
    private const int VERSION = 1;

    public function __construct(
        private HttpClientInterface $httpClient,
        private ?string $baseUrl,
    ) {
    }

    /**
     * @param array<string, string> $installed The version of every installed package, by its name.
     *
     * @return list<array<string, mixed>>|null The advisories that affect an installed version, or null when the
     *                                         documentation site does not answer with its feed.
     */
    public function findAffecting(array $installed): ?array
    {
        $advisories = $this->load();

        if (null === $advisories) {
            return null;
        }

        $affecting = [];

        foreach ($advisories as $advisory) {
            $packages = [];

            foreach (is_array($advisory['packages'] ?? null) ? $advisory['packages'] : [] as $package) {
                $name = $package['name'] ?? null;
                $version = is_string($name) ? $installed[$name] ?? null : null;

                if (null === $version || !$this->isAffected($version, $package['affected'] ?? null)) {
                    continue;
                }

                $packages[] = [
                    'name'      => $name,
                    'installed' => $version,
                    'fixed'     => $this->getFixedAbove($version, $package['fixed'] ?? []),
                ];
            }

            if ([] !== $packages) {
                $affecting[] = [
                    'id'       => $advisory['id'] ?? null,
                    'title'    => $advisory['title'] ?? null,
                    'severity' => $advisory['severity'] ?? null,
                    'cvss'     => $advisory['cvss'] ?? null,
                    'url'      => $advisory['url'] ?? null,
                    'packages' => $packages,
                ];
            }
        }

        return $affecting;
    }

    private function isAffected(string $version, mixed $affected): bool
    {
        // A package installed from a branch has no version to compare.
        if (!is_string($affected) || 'dev' === VersionParser::parseStability($version)) {
            return false;
        }

        try {
            return Semver::satisfies($version, $affected);
        } catch (UnexpectedValueException) {
            return false;
        }
    }

    /**
     * @return list<string> The fixed versions above the installed one. A backport offers more than one.
     */
    private function getFixedAbove(string $version, mixed $fixed): array
    {
        return array_values(array_filter(
            is_array($fixed) ? $fixed : [],
            static fn (mixed $candidate): bool => is_string($candidate) && Comparator::greaterThan(
                (new VersionParser())->normalize($candidate),
                (new VersionParser())->normalize($version),
            ),
        ));
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function load(): ?array
    {
        if (null === $this->baseUrl || '' === $this->baseUrl) {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', sprintf('%s/security/advisories.json', rtrim($this->baseUrl, '/')), ['timeout' => 10]);
            $feed = 200 === $response->getStatusCode() ? $response->toArray() : null;
        } catch (Throwable) {
            return null;
        }

        if (self::VERSION !== ($feed['version'] ?? null) || !is_array($feed['advisories'] ?? null)) {
            return null;
        }

        return array_values(array_filter($feed['advisories'], is_array(...)));
    }
}
