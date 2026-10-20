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

namespace OpenDxp\MateExtension\Configuration;

use OpenDxp\MateExtension\Kernel\HostKernel;
use OpenDxp\Model\Asset\Image\Thumbnail\Config\Listing as ThumbnailListing;
use OpenDxp\Model\Element\ElementInterface;
use OpenDxp\Model\Site\Listing as SiteListing;
use OpenDxp\Model\WebsiteSetting\Listing as WebsiteSettingListing;
use OpenDxp\Tool;
use Symfony\AI\Mate\Attribute\MateTool;
use Symfony\AI\Mate\Encoding\ResponseEncoder;
use Throwable;
use function is_scalar;
use function sprintf;

final class SiteConfigTool
{
    private const array SECTIONS = ['website_settings', 'thumbnails', 'sites', 'languages'];

    public function __construct(private readonly HostKernel $host)
    {
    }

    /**
     * @param string|null $section One of website_settings, thumbnails, sites, languages. All of them when omitted.
     */
    #[MateTool(
        name: 'opendxp_site_config',
        description: 'The configuration an editor maintains in this installation: website settings with their values resolved, image thumbnail names, sites and languages. A handler that reads a website setting or renders a thumbnail needs the exact names from here. They appear nowhere in the code.',
    )]
    public function __invoke(?string $section = null): string
    {
        if (null !== $section && !in_array($section, self::SECTIONS, true)) {
            return ResponseEncoder::encode(['error' => sprintf('Unknown section "%s". Available: %s.', $section, implode(', ', self::SECTIONS))]);
        }

        $this->host->boot();

        $sections = null !== $section ? [$section] : self::SECTIONS;
        $config = [];

        foreach ($sections as $name) {
            $config[$name] = match ($name) {
                'website_settings' => $this->getWebsiteSettings(),
                'thumbnails' => $this->getThumbnails(),
                'sites' => $this->getSites(),
                'languages' => Tool::getValidLanguages(),
            };
        }

        return ResponseEncoder::encodeUntrusted($config);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getWebsiteSettings(): array
    {
        $settings = [];

        foreach ((new WebsiteSettingListing())->getSettings() as $setting) {
            $settings[] = [
                'name'     => $setting->getName(),
                'type'     => $setting->getType(),
                'value'    => $this->describeValue($setting->getData()),
                'language' => '' !== $setting->getLanguage() ? $setting->getLanguage() : null,
                'site'     => $setting->getSiteId(),
            ];
        }

        return $settings;
    }

    /**
     * @return array<string, string>
     */
    private function getThumbnails(): array
    {
        $thumbnails = [];

        foreach ((new ThumbnailListing())->getThumbnails() as $thumbnail) {
            $thumbnails[$thumbnail->getName()] = $this->describeThumbnailItems($thumbnail->getItems());
        }

        ksort($thumbnails);

        return $thumbnails;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getSites(): array
    {
        $sites = [];

        foreach ((new SiteListing())->getSites() as $site) {
            $sites[] = [
                'id'         => $site->getId(),
                'mainDomain' => $site->getMainDomain(),
                'rootPath'   => $site->getRootPath(),
            ];
        }

        return $sites;
    }

    private function describeValue(mixed $data): ?string
    {
        if ($data instanceof ElementInterface) {
            try {
                return $data->getFullPath();
            } catch (Throwable) {
                return sprintf('#%s (unresolvable)', $data->getId() ?? '?');
            }
        }

        if (is_bool($data)) {
            return $data ? 'true' : 'false';
        }

        if (null === $data || is_scalar($data)) {
            return null === $data ? null : (string) $data;
        }

        return sprintf('<%s>', get_debug_type($data));
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function describeThumbnailItems(array $items): string
    {
        $described = [];

        foreach ($items as $item) {
            $method = $item['method'] ?? null;

            if (null === $method) {
                continue;
            }

            $arguments = $item['arguments'] ?? [];
            $width = $arguments['width'] ?? null;
            $height = $arguments['height'] ?? null;

            $described[] = match (true) {
                null !== $width && null !== $height => sprintf('%s %sx%s', $method, $width, $height),
                null !== $width => sprintf('%s w%s', $method, $width),
                null !== $height => sprintf('%s h%s', $method, $height),
                default => $method,
            };
        }

        return [] !== $described ? implode(', ', $described) : 'no transformation';
    }
}
