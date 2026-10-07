<?php

/**
 * Map the conditions tree of a segment between the model and the API resource, with labels in the admin locale.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CustomerSegmentation
 */

declare(strict_types=1);

namespace Maho\CustomerSegmentation\Api;

use Maho\ApiPlatform\Trait\AuthenticationTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\ObjectMapper\TransformCallableInterface;

/**
 * @implements TransformCallableInterface<object, object>
 */
final class CustomerSegmentConditionsTransform implements TransformCallableInterface
{
    use AuthenticationTrait;
    use ConditionMetadataTrait;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    /**
     * To the resource: return the tree of the segment in the wire format.
     * To the segment: check the wire tree, put it into the segment and return the root condition.
     *
     * @throws \Mage_Core_Exception_Input with one error for each problem of the tree
     */
    #[\Override]
    public function __invoke(mixed $value, object $source, ?object $target): mixed
    {
        $locale = $this->adminLocale();
        $document = $this->conditionMetadata($locale);
        $writer = new \Mage_Rule_Model_Condition_TreeWriter($document);

        if ($source instanceof \Maho_CustomerSegmentation_Model_Segment) {
            return \Mage_Rule_Model_Condition_Metadata::runInLocale($locale, fn(): array => $writer->readTree($source, 'conditions'));
        }

        if (!$target instanceof \Maho_CustomerSegmentation_Model_Segment) {
            return $value;
        }

        return \Mage_Rule_Model_Condition_Metadata::runInLocale($locale, function () use ($value, $target, $document, $writer): mixed {
            $validator = new \Mage_Rule_Model_Condition_TreeValidator($this->conditionMetadataModel(), $document);
            $tree = $value ?? ['type' => CustomerSegment::ROOT_CONDITIONS, 'aggregator' => 'all', 'value' => true, 'conditions' => []];
            $result = $validator->validate('conditions', $tree, $target->getId() ? $writer->readTree($target, 'conditions') : null);

            $errors = new \Mage_Core_Exception_Input();
            foreach ($result['errors'] as $error) {
                $errors->addError($error['field'], $error['message']);
            }
            $errors->throwIfErrors();

            if ($result['tree'] !== null) {
                $writer->replaceTree($target, 'conditions', $result['tree']);
            }
            return $target->getConditions();
        });
    }
}
