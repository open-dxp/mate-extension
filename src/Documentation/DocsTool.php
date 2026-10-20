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

namespace OpenDxp\MateExtension\Documentation;

use Symfony\AI\Mate\Attribute\MateTool;
use Symfony\AI\Mate\Encoding\ResponseEncoder;

use function count;
use function is_array;
use function is_int;
use function is_string;
use function trim;

final readonly class DocsTool
{
    private const int HITS = 8;

    public function __construct(private DocumentationSearch $documentation)
    {
    }

    /**
     * @param string      $query   What to search for, e.g. "thumbnail definition" or "conditional logic".
     * @param string|null $section Narrow to one section. "core-framework" is the core itself. A bundle has the name its documentation shows, not the name of its composer package.
     */
    #[MateTool(
        name: 'opendxp_docs',
        description: 'Searches the OpenDXP documentation and answers with the matching pages and an excerpt from each. Every result carries the url of the page and, where one exists, the url of its raw markdown.',
    )]
    public function __invoke(string $query, ?string $section = null): string
    {
        return ResponseEncoder::encodeUntrusted($this->search($query, $section));
    }

    /**
     * @return array<string, mixed>
     */
    private function search(string $query, ?string $section): array
    {
        if (!$this->documentation->isAvailable()) {
            return ['error' => 'No documentation site is configured.'];
        }

        $result = $this->documentation->search(trim($query), $section, self::HITS);

        if (null === $result) {
            return ['error' => 'The documentation site did not answer.'];
        }

        $pages = [];

        foreach ($this->getHits($result) as $hit) {
            $page = $this->describePage($hit);

            if (null !== $page) {
                $pages[] = $page;
            }
        }

        $total = $result['total'] ?? null;

        return [
            'query'         => is_string($result['query'] ?? null) ? $result['query'] : $query,
            'pages_matched' => is_int($total) ? $total : count($pages),
            'pages'         => $pages,
        ];
    }

    /**
     * @param array<string, mixed> $result
     *
     * @return list<mixed>
     */
    private function getHits(array $result): array
    {
        $hits = $result['hits'] ?? null;

        return is_array($hits) ? array_values($hits) : [];
    }

    /**
     * @return array<string, string|null>|null
     */
    private function describePage(mixed $hit): ?array
    {
        if (!is_array($hit)) {
            return null;
        }

        $url = $hit['url'] ?? null;
        $markdownUrl = $hit['markdown_url'] ?? null;

        // A page without a url is nothing the caller can follow.
        if (!is_string($url) || '' === $url) {
            return null;
        }

        return [
            'title'        => $this->getText($hit['title'] ?? null),
            'section'      => $this->getText($hit['section'] ?? null),
            'url'          => $this->documentation->getAbsoluteUrl($url),
            'markdown_url' => is_string($markdownUrl) && '' !== $markdownUrl
                ? $this->documentation->getAbsoluteUrl($markdownUrl)
                : null,
            'excerpt'      => $this->getText($hit['excerpt'] ?? null),
        ];
    }

    private function getText(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
