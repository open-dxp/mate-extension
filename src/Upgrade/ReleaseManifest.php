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

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;
use function rtrim;
use function sprintf;

final readonly class ReleaseManifest
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ?string $baseUrl,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function load(): ?array
    {
        if (null === $this->baseUrl || '' === $this->baseUrl) {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', $this->getAbsoluteUrl('/releases.json'), ['timeout' => 10]);

            return 200 === $response->getStatusCode() ? $response->toArray() : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function getAbsoluteUrl(string $path): string
    {
        return sprintf('%s%s', rtrim((string) $this->baseUrl, '/'), $path);
    }
}
