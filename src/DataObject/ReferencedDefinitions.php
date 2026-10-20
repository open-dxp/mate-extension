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

namespace OpenDxp\MateExtension\DataObject;

use function in_array;

final class ReferencedDefinitions
{
    /** @var list<string> */
    private array $fieldCollections = [];

    /** @var list<string> */
    private array $objectBricks = [];

    /** @var list<int> */
    private array $classificationStores = [];

    public function addFieldCollection(string $type): void
    {
        if (!in_array($type, $this->fieldCollections, true)) {
            $this->fieldCollections[] = $type;
        }
    }

    public function addObjectBrick(string $type): void
    {
        if (!in_array($type, $this->objectBricks, true)) {
            $this->objectBricks[] = $type;
        }
    }

    public function addClassificationStore(int $storeId): void
    {
        if (!in_array($storeId, $this->classificationStores, true)) {
            $this->classificationStores[] = $storeId;
        }
    }

    /**
     * @return list<string>
     */
    public function getFieldCollections(): array
    {
        return $this->fieldCollections;
    }

    /**
     * @return list<string>
     */
    public function getObjectBricks(): array
    {
        return $this->objectBricks;
    }

    /**
     * @return list<int>
     */
    public function getClassificationStores(): array
    {
        return $this->classificationStores;
    }
}
