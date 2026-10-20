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

use OpenDxp\Config;
use OpenDxp\MateExtension\Kernel\HostKernel;
use OpenDxp\Model\DataObject\ClassDefinition;
use OpenDxp\Model\DataObject\ClassDefinition\Data;
use OpenDxp\Model\DataObject\ClassDefinition\Data\Classificationstore;
use OpenDxp\Model\DataObject\ClassDefinition\Data\Fieldcollections;
use OpenDxp\Model\DataObject\ClassDefinition\Data\Localizedfields;
use OpenDxp\Model\DataObject\ClassDefinition\Data\Objectbricks;
use OpenDxp\Model\DataObject\ClassDefinition\Data\Relations\AbstractRelations;
use OpenDxp\Model\DataObject\ClassDefinition\Listing as ClassDefinitionListing;
use OpenDxp\Model\DataObject\Classificationstore\GroupConfig\Listing as GroupConfigListing;
use OpenDxp\Model\DataObject\Classificationstore\KeyGroupRelation\Listing as KeyGroupRelationListing;
use OpenDxp\Model\DataObject\Classificationstore\StoreConfig;
use OpenDxp\Model\DataObject\Classificationstore\StoreConfig\Listing as StoreConfigListing;
use OpenDxp\Model\DataObject\Fieldcollection\Definition as FieldcollectionDefinition;
use OpenDxp\Model\DataObject\Fieldcollection\Definition\Listing as FieldcollectionDefinitionListing;
use OpenDxp\Model\DataObject\Objectbrick\Definition as ObjectbrickDefinition;
use OpenDxp\Model\DataObject\Objectbrick\Definition\Listing as ObjectbrickDefinitionListing;
use Symfony\AI\Mate\Attribute\MateTool;
use Symfony\AI\Mate\Encoding\ResponseEncoder;
use Throwable;
use function array_keys;
use function implode;
use function is_subclass_of;
use function ltrim;
use function sprintf;

final class ModelSchemaTool
{
    public function __construct(private readonly HostKernel $host)
    {
    }

    /**
     * @param string|null $class Class name as it appears in the class list. Omit it to see that list first.
     */
    #[MateTool(
        name: 'opendxp_model_schema',
        description: 'The DataObject model of this installation. Without a class name it lists every class, every relation field type, and every field collection, object brick and classification store with the class fields they belong to. A definition with none belongs nowhere and cannot hold data. With a class name it describes every field of that class, including localized fields, the classes a relation may point at, and the fields of the collections and bricks it can hold. Read this before writing a handler that loads or filters DataObjects.',
    )]
    public function __invoke(?string $class = null): string
    {
        $this->host->boot();

        return ResponseEncoder::encodeUntrusted($this->describe($class));
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(?string $class): array
    {
        if (null === $class) {
            return $this->getModelOverview();
        }

        $definition = ClassDefinition::getByName($class);

        if (!$definition instanceof ClassDefinition) {
            return [
                'error'   => sprintf('No class "%s" in this installation.', $class),
                'classes' => $this->getClassNames(),
            ];
        }

        $referenced = new ReferencedDefinitions();
        $name = (string) $definition->getName();

        return [
                'class'  => $name,
                'fields' => $this->describeFields($definition->getFieldDefinitions(), $referenced, $this->getBricksByField($name)),
            ] + $this->describeReferencedDefinitions($referenced);
    }

    /**
     * @return array<string, mixed>
     */
    private function getModelOverview(): array
    {
        $overview = ['classes' => $this->getClassNames(), 'relation_types' => $this->getRelationTypes()];

        foreach ([
                     'fieldcollections'     => $this->getFieldCollectionUsage(),
                     'objectbricks'         => $this->getBrickUsage(),
                     'classificationstores' => $this->getClassificationStoreUsage(),
                 ] as $name => $usage) {
            if ([] !== $usage) {
                $overview[$name] = $usage;
            }
        }

        return $overview;
    }

    /**
     * The relation field types the installation knows. A bundle can add its own.
     *
     * @return list<string>
     */
    private function getRelationTypes(): array
    {
        $types = [];

        foreach (Config::getSystemConfiguration('objects')['class_definitions']['data']['map'] ?? [] as $type => $class) {
            if (is_subclass_of(ltrim((string) $class, '\\'), AbstractRelations::class)) {
                $types[] = $type;
            }
        }

        return $types;
    }

    /**
     * @return list<string>
     */
    private function getClassNames(): array
    {
        $names = [];

        foreach ((new ClassDefinitionListing())->getClasses() as $definition) {
            $name = $definition->getName();

            if (null !== $name) {
                $names[] = $name;
            }
        }

        sort($names);

        return $names;
    }

    /**
     * @param array<string, Data>         $definitions
     * @param array<string, list<string>> $bricksByField
     *
     * @return array<string, string>
     */
    private function describeFields(array $definitions, ReferencedDefinitions $referenced, array $bricksByField, bool $localized = false): array
    {
        $fields = [];

        foreach ($definitions as $definition) {
            if ($definition instanceof Localizedfields) {
                $fields += $this->describeFields($definition->getFieldDefinitions(), $referenced, $bricksByField, true);

                continue;
            }

            $name = $definition->getName();

            if (null === $name) {
                continue;
            }

            $fields[$name] = $this->describeField($definition, $referenced, $bricksByField, $localized);
        }

        return $fields;
    }

    /**
     * @param array<string, list<string>> $bricksByField
     */
    private function describeField(Data $definition, ReferencedDefinitions $referenced, array $bricksByField, bool $localized): string
    {
        $description = $definition->getFieldType();

        if ($localized) {
            $description .= ', localized';
        }

        if ($definition->getMandatory()) {
            $description .= ', mandatory';
        }

        if ($definition instanceof Fieldcollections && [] === $definition->getAllowedTypes()) {
            $description .= ', no type restriction';
        }

        if ($definition instanceof Objectbricks && [] === ($bricksByField[$definition->getName()] ?? [])) {
            $description .= ', no brick assigned';
        }

        $targets = [
            ...$this->getRelationTargets($definition),
            ...$this->getAllowedElementTypes($definition),
            ...$this->getAllowedDefinitions($definition, $referenced, $bricksByField),
        ];

        if ([] !== $targets) {
            $description .= sprintf(' -> %s', implode('|', $targets));
        }

        return $description;
    }

    /**
     * @param array<string, list<string>> $bricksByField
     *
     * @return list<string>
     */
    private function getAllowedDefinitions(Data $definition, ReferencedDefinitions $referenced, array $bricksByField): array
    {
        if ($definition instanceof Fieldcollections) {
            // An empty restriction is not an empty field. The editor then offers every collection.
            $types = [] !== $definition->getAllowedTypes() ? $definition->getAllowedTypes() : $this->getFieldCollectionNames();

            foreach ($types as $type) {
                $referenced->addFieldCollection($type);
            }

            return $types;
        }

        if ($definition instanceof Objectbricks) {
            $types = $bricksByField[$definition->getName()] ?? [];

            foreach ($types as $type) {
                $referenced->addObjectBrick($type);
            }

            return $types;
        }

        if ($definition instanceof Classificationstore) {
            $storeId = $definition->getStoreId();
            $referenced->addClassificationStore($storeId);

            return [$this->getClassificationStoreName($storeId)];
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function describeReferencedDefinitions(ReferencedDefinitions $referenced): array
    {
        $described = [];

        foreach ($referenced->getFieldCollections() as $type) {
            $definition = FieldcollectionDefinition::getByKey($type);

            if (null !== $definition) {
                $described['fieldcollections'][$type] = $this->describeFields($definition->getFieldDefinitions(), $referenced, []);
            }
        }

        foreach ($referenced->getObjectBricks() as $type) {
            $definition = ObjectbrickDefinition::getByKey($type);

            if (null !== $definition) {
                $described['objectbricks'][$type] = $this->describeFields($definition->getFieldDefinitions(), $referenced, []);
            }
        }

        foreach ($referenced->getClassificationStores() as $storeId) {
            $described['classificationstores'][$this->getClassificationStoreName($storeId)] = $this->getClassificationStoreGroups($storeId);
        }

        return $described;
    }

    /**
     * @return array<string, list<string>>
     */
    private function getBricksByField(string $className): array
    {
        $bricks = [];

        foreach ($this->getBrickDefinitions() as $key => $definition) {
            foreach ($definition->getClassDefinitions() as $attachment) {
                if (($attachment['classname'] ?? null) === $className) {
                    $bricks[$attachment['fieldname']][] = $key;
                }
            }
        }

        return $bricks;
    }

    /**
     * @return array<string, ObjectbrickDefinition>
     */
    private function getBrickDefinitions(): array
    {
        $definitions = [];

        foreach ((new ObjectbrickDefinitionListing())->loadNames() as $key) {
            $definition = ObjectbrickDefinition::getByKey($key);

            if (null !== $definition) {
                $definitions[$key] = $definition;
            }
        }

        return $definitions;
    }

    /**
     * @return list<string>
     */
    private function getFieldCollectionNames(): array
    {
        return (new FieldcollectionDefinitionListing())->loadNames();
    }

    /**
     * @return array<string, list<string>>
     */
    private function getBrickUsage(): array
    {
        $usage = [];

        foreach ($this->getBrickDefinitions() as $key => $definition) {
            $usage[$key] = [];

            foreach ($definition->getClassDefinitions() as $attachment) {
                $usage[$key][] = sprintf('%s.%s', $attachment['classname'] ?? '?', $attachment['fieldname'] ?? '?');
            }
        }

        return $usage;
    }

    /**
     * @return array<string, list<string>>
     */
    private function getFieldCollectionUsage(): array
    {
        $usage = [];

        foreach ($this->getFieldCollectionNames() as $name) {
            $usage[$name] = [];
        }

        foreach ($this->getFieldsOfType(Fieldcollections::class) as $place => $definition) {
            $types = [] !== $definition->getAllowedTypes() ? $definition->getAllowedTypes() : $this->getFieldCollectionNames();

            foreach ($types as $type) {
                $usage[$type][] = $place;
            }
        }

        return $usage;
    }

    /**
     * @return array<string, list<string>>
     */
    private function getClassificationStoreUsage(): array
    {
        $usage = [];

        foreach ((new StoreConfigListing())->getList() as $store) {
            $usage[(string) $store->getName()] = [];
        }

        foreach ($this->getFieldsOfType(Classificationstore::class) as $place => $definition) {
            $usage[$this->getClassificationStoreName($definition->getStoreId())][] = $place;
        }

        return $usage;
    }

    /**
     * Every field of the given type in the installation, keyed by "Class.field".
     *
     * @template T of Data
     *
     * @param class-string<T> $type
     *
     * @return array<string, T>
     */
    private function getFieldsOfType(string $type): array
    {
        $found = [];

        foreach ($this->getClassNames() as $className) {
            $class = ClassDefinition::getByName($className);

            if (!$class instanceof ClassDefinition) {
                continue;
            }

            foreach ($class->getFieldDefinitions() as $definition) {
                if ($definition instanceof $type) {
                    $found[sprintf('%s.%s', $className, $definition->getName())] = $definition;
                }
            }
        }

        return $found;
    }

    private function getClassificationStoreName(int $storeId): string
    {
        return StoreConfig::getById($storeId)?->getName() ?? sprintf('store %d', $storeId);
    }

    /**
     * @return array<string, array<string, string>|string>
     */
    private function getClassificationStoreGroups(int $storeId): array
    {
        $groups = new GroupConfigListing();
        $groups->setCondition('storeId = ?', [$storeId]);

        $described = [];

        foreach ($groups->getList() as $group) {
            try {
                $described[$group->getName()] = $this->getGroupKeys((int) $group->getId());
            } catch (Throwable $exception) {
                $described[$group->getName()] = sprintf('unreadable: %s', $exception->getMessage());
            }
        }

        return $described;
    }

    /**
     * @return array<string, string>
     */
    private function getGroupKeys(int $groupId): array
    {
        $relations = new KeyGroupRelationListing();
        $relations->setCondition('groupId = ?', [$groupId]);

        $keys = [];

        foreach ($relations->getList() as $relation) {
            $keys[$relation->getName()] = $relation->getType();
        }

        return $keys;
    }

    /**
     * @return list<string>
     */
    private function getAllowedElementTypes(Data $definition): array
    {
        $types = [];

        if (method_exists($definition, 'getAssetsAllowed') && true === $definition->getAssetsAllowed()) {
            $types[] = 'assets';
        }

        if (method_exists($definition, 'getDocumentsAllowed') && true === $definition->getDocumentsAllowed()) {
            $types[] = 'documents';
        }

        return $types;
    }

    /**
     * @return list<string>
     */
    private function getRelationTargets(Data $definition): array
    {
        if (!method_exists($definition, 'getClasses')) {
            return [];
        }

        $targets = [];

        foreach ($definition->getClasses() as $entry) {
            $name = is_array($entry) ? ($entry['classes'] ?? null) : $entry;

            if (is_string($name) && '' !== $name) {
                $targets[] = $name;
            }
        }

        return $targets;
    }
}
