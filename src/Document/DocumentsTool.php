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

namespace OpenDxp\MateExtension\Document;

use OpenDxp\Config;
use OpenDxp\Extension\Document\Areabrick\AreabrickManagerInterface;
use OpenDxp\MateExtension\Kernel\HostKernel;
use Symfony\AI\Mate\Attribute\MateTool;
use Symfony\AI\Mate\Encoding\ResponseEncoder;
use Throwable;
use function ksort;
use function ltrim;

final class DocumentsTool
{
    public function __construct(private readonly HostKernel $host)
    {
    }

    #[MateTool(
        name: 'opendxp_documents',
        description: 'The document types and areabricks of this installation. A project or a bundle can add both. Each document type has its class and its capabilities. Each areabrick has its id, name, description, class and template. Check the existing areabricks before you build a new one.',
    )]
    public function __invoke(): string
    {
        $container = $this->host->boot()->getContainer();

        return ResponseEncoder::encode([
            'document_types' => $this->getDocumentTypes(),
            'areabricks'     => $this->getAreabricks($container->get(AreabrickManagerInterface::class)),
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getDocumentTypes(): array
    {
        $types = [];

        foreach (Config::getSystemConfiguration('documents')['type_definitions']['map'] ?? [] as $type => $definition) {
            $types[$type] = [
                'class'                     => isset($definition['class']) ? ltrim((string) $definition['class'], '\\') : null,
                'routed_by_path'            => $definition['direct_route'] ?? false,
                'children'                  => $definition['children_supported'] ?? true,
                'only_printable_children'   => $definition['only_printable_childrens'] ?? false,
                'predefined_document_types' => $definition['predefined_document_types'] ?? false,
                'translatable'              => $definition['translatable'] ?? true,
            ];
        }

        return $types;
    }

    /**
     * @return array<string, array<string, string|null>>
     */
    private function getAreabricks(AreabrickManagerInterface $manager): array
    {
        $bricks = [];

        foreach ($manager->getBricks() as $id => $brick) {
            try {
                $template = $brick->hasTemplate() ? $brick->getTemplate() : null;
            } catch (Throwable) {
                $template = null;
            }

            $bricks[$id] = [
                'name'        => $brick->getName(),
                'description' => '' !== $brick->getDescription() ? $brick->getDescription() : null,
                'class'       => $brick::class,
                'template'    => $template,
            ];
        }

        ksort($bricks);

        return $bricks;
    }
}
