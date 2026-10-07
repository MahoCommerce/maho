<?php

/**
 * Serve the condition types of segment trees from the metadata document, one type at a time,
 * so that each answer stays small enough for an assistant to read in full.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CustomerSegmentation
 */

declare(strict_types=1);

namespace Maho\CustomerSegmentation\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\Exception\NotFoundException;

final class CustomerSegmentConditionTypeProvider extends \Maho\ApiPlatform\Provider
{
    use ConditionMetadataTrait;
    use SegmentApiTrait;

    /**
     * @return TraversablePaginator<CustomerSegmentConditionType>|CustomerSegmentConditionType
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator|CustomerSegmentConditionType
    {
        $this->assertSegmentAcl(CustomerSegment::ACL_MANAGE);
        $document = $this->conditionMetadata($this->adminLocale());
        $labels = $this->groupLabels($document);

        if (!$operation instanceof CollectionOperationInterface) {
            $type = CustomerSegmentConditionType::TYPE_PREFIX . (string) ($uriVariables['code'] ?? '');
            if (!isset($document['types'][$type])) {
                throw new NotFoundException('Condition type not found');
            }
            return $this->toTypeDto($type, $document['types'][$type], $labels[$type] ?? null, true);
        }

        $items = [];
        foreach ($document['types'] as $type => $description) {
            if (str_starts_with((string) $type, CustomerSegmentConditionType::TYPE_PREFIX)) {
                $items[] = $this->toTypeDto((string) $type, $description, $labels[$type] ?? null, false);
            }
        }

        return new TraversablePaginator(new \ArrayIterator($items), 1, max(1, count($items)), count($items));
    }

    /**
     * @param array<string, mixed> $description
     */
    private function toTypeDto(string $type, array $description, ?string $label, bool $full): CustomerSegmentConditionType
    {
        $dto = new CustomerSegmentConditionType();
        $dto->code = substr($type, strlen(CustomerSegmentConditionType::TYPE_PREFIX));
        $dto->type = $type;
        $dto->kind = (string) ($description['kind'] ?? 'leaf');
        $dto->label = $label ?? (isset($description['label']) ? (string) $description['label'] : null);

        foreach ($description['attributes'] ?? [] as $attribute) {
            $dto->attributes[] = $full ? $attribute : ['code' => $attribute['code'], 'label' => $attribute['label']];
        }
        if ($full && $dto->kind === 'combine') {
            $dto->combine = ['aggregator' => $description['aggregator'] ?? null, 'value' => $description['value'] ?? null];
        }

        return $dto;
    }

    /**
     * Return the label of the group that lists each type among the children of the root, such as Order History.
     *
     * @param array<string, mixed> $document
     * @return array<string, string>
     */
    private function groupLabels(array $document): array
    {
        $root = $document['types'][CustomerSegment::ROOT_CONDITIONS] ?? [];
        $labels = [];
        foreach ($root['children'] ?? [] as $child) {
            foreach ($child['options'] ?? [] as $option) {
                $labels[$option['type']] ??= (string) $child['label'];
            }
        }
        return $labels;
    }
}
