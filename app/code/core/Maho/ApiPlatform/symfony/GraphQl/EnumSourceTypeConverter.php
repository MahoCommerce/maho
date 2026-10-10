<?php

/**
 * Gives a GraphQL enum type to every string property that names an EnumSource.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\GraphQl;

use ApiPlatform\GraphQl\Type\TypeConverterInterface;
use ApiPlatform\GraphQl\Type\TypesContainerInterface;
use ApiPlatform\Metadata\Exception\OperationNotFoundException;
use ApiPlatform\Metadata\Exception\PropertyNotFoundException;
use ApiPlatform\Metadata\Exception\ResourceClassNotFoundException;
use ApiPlatform\Metadata\GraphQl\Operation;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\Type as GraphQLType;
use Maho\ApiPlatform\Metadata\EnumSource;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * GraphQL builds its types from the PHP types, so the JSON schema enums never reach it. This
 * converter answers a string property that names an EnumSource with an enum type named
 * "<ShortName><Property>Enum". A member takes its name from the value, made into a GraphQL
 * identifier: "INDEX,FOLLOW" becomes INDEX_FOLLOW, "24h" becomes _24h, and "" becomes EMPTY.
 * The member value stays the raw value, so the resolvers and the processors see no change.
 *
 * An integer list, such as website ids, stays an integer: id members like _4 would be worse
 * than the number.
 */
final class EnumSourceTypeConverter implements TypeConverterInterface
{
    public function __construct(
        private readonly TypeConverterInterface $decorated,
        private readonly TypesContainerInterface $typesContainer,
        private readonly PropertyMetadataFactoryInterface $propertyMetadataFactory,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
    ) {}

    #[\Override]
    public function convertPhpType(Type $type, bool $input, Operation $rootOperation, string $resourceClass, string $rootResource, ?string $property, int $depth): GraphQLType|string|null
    {
        $owner = $resourceClass !== '' ? $resourceClass : $rootResource;
        if ($property !== null && $owner !== '' && $type->isIdentifiedBy(TypeIdentifier::STRING)) {
            $enum = $this->enumType($owner, $property);
            if ($enum !== null) {
                return $enum;
            }
        }

        return $this->decorated->convertPhpType($type, $input, $rootOperation, $resourceClass, $rootResource, $property, $depth);
    }

    #[\Override]
    public function resolveType(string $type): ?GraphQLType
    {
        return $this->decorated->resolveType($type);
    }

    private function enumType(string $class, string $property): ?EnumType
    {
        try {
            $source = $this->propertyMetadataFactory->create($class, $property)->getExtraProperties()[EnumSource::KEY] ?? null;
        } catch (PropertyNotFoundException) {
            return null;
        }
        if ($source === null) {
            return null;
        }

        $name = $this->shortName($class) . ucfirst($property) . 'Enum';
        if ($this->typesContainer->has($name)) {
            $type = $this->typesContainer->get($name);

            return $type instanceof EnumType ? $type : null;
        }

        $values = [];
        foreach (EnumSource::values($source) as $value) {
            if (!is_string($value)) {
                return null;
            }
            $member = self::memberName($value);
            if (isset($values[$member])) {
                return null;
            }
            $values[$member] = ['value' => $value];
        }
        if ($values === []) {
            return null;
        }

        $type = new EnumType(['name' => $name, 'values' => $values]);
        $this->typesContainer->set($name, $type);

        return $type;
    }

    private function shortName(string $class): string
    {
        try {
            $shortName = $this->resourceMetadataCollectionFactory->create($class)->getOperation()->getShortName();
        } catch (ResourceClassNotFoundException|OperationNotFoundException) {
            $shortName = null;
        }

        return $shortName ?? new \ReflectionClass($class)->getShortName();
    }

    public static function memberName(string $value): string
    {
        if ($value === '') {
            return 'EMPTY';
        }
        $name = (string) preg_replace('/[^A-Za-z0-9_]/', '_', $value);

        return preg_match('/^[0-9]/', $name) === 1 ? '_' . $name : $name;
    }
}
