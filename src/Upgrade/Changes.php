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

use function in_array;
use function is_array;

final readonly class Changes
{
    /**
     * @param list<array<string, mixed>> $tasks
     * @param list<array<string, mixed>> $offers
     * @param list<array<string, mixed>> $deprecations
     * @param list<array<string, mixed>> $highlights
     */
    private function __construct(
        public array $tasks,
        public array $offers,
        public array $deprecations,
        public array $highlights,
        public int $breaks,
        public int $others,
        public bool $migrates,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $versions
     */
    public static function fromVersions(array $versions): self
    {
        $tasks = [];
        $offers = [];
        $deprecations = [];
        $highlights = [];
        $breaks = 0;
        $others = 0;
        $migrates = false;

        foreach ($versions as $version) {
            foreach (is_array($version['entries'] ?? null) ? $version['entries'] : [] as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $type = $entry['type'] ?? null;
                $markers = is_array($entry['markers'] ?? null) ? $entry['markers'] : [];
                $item = [
                    'version' => $version['version'],
                    'type'    => $type,
                    'text'    => $entry['text'] ?? null,
                    'steps'   => is_array($entry['steps'] ?? null) ? $entry['steps'] : [],
                ];

                $migrates = $migrates || in_array('MIGRATION', $markers, true);

                if (in_array('HIGHLIGHT', $markers, true)) {
                    $highlights[] = ['version' => $version['version'], 'text' => $item['text'], 'announcement' => $entry['announcement'] ?? null];
                }

                if ('BC BREAK' === $type) {
                    ++$breaks;
                }

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

        return new self($tasks, $offers, $deprecations, $highlights, $breaks, $others, $migrates);
    }
}
