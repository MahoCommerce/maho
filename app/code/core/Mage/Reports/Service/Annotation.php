<?php

/**
 * The notes on the dates of the report charts: the notes of the administrators and the start of each promotion.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

class Mage_Reports_Service_Annotation
{
    public function newAnnotation(): Mage_Reports_Model_Annotation
    {
        return Mage::getModel('reports/annotation');
    }

    /** @throws Mage_Core_Exception_NoSuchEntity */
    public function getById(int $id): Mage_Reports_Model_Annotation
    {
        $annotation = $this->newAnnotation()->load($id);
        if ($annotation->getId() === null) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('reports')->__('The note with ID %s does not exist.', $id));
        }
        return $annotation;
    }

    /**
     * Check and save a note. A new note belongs to $adminUserId. Only its owner changes it, and anyone changes a note
     * whose owner was deleted.
     *
     * @throws Mage_Core_Exception_Forbidden
     * @throws Mage_Core_Exception_Input
     */
    public function save(Mage_Reports_Model_Annotation $annotation, int $adminUserId): Mage_Reports_Model_Annotation
    {
        $helper = Mage::helper('reports');
        if ($annotation->getId() === null) {
            $annotation->setAdminUserId($adminUserId);
        } else {
            $this->assertOwner($annotation, $adminUserId);
        }
        new Mage_Core_Exception_Input($helper->__('The note is not valid.'))
            ->addValidationResult($annotation->validate(), 'title')
            ->throwIfErrors();
        $annotation->setTitle(trim((string) $annotation->getTitle()))->save();
        return $annotation;
    }

    /** @throws Mage_Core_Exception_Forbidden */
    public function delete(Mage_Reports_Model_Annotation $annotation, int $adminUserId): void
    {
        $this->assertOwner($annotation, $adminUserId);
        $annotation->delete();
    }

    /**
     * The notes between two local days, and the cart price rules that start on one of them, oldest first.
     *
     * @return list<array{id: ?int, date: string, title: string, source: string, adminUserId: ?int}>
     */
    public function between(string $from, string $to): array
    {
        $notes = [];
        $collection = Mage::getResourceModel('reports/annotation_collection')
            ->addFieldToFilter('annotation_date', ['from' => $from, 'to' => $to])
            ->setOrder('annotation_date', 'ASC');
        foreach ($collection as $annotation) {
            if ($annotation instanceof Mage_Reports_Model_Annotation) {
                $notes[] = [
                    'id' => (int) $annotation->getId(),
                    'date' => substr((string) $annotation->getAnnotationDate(), 0, 10),
                    'title' => (string) $annotation->getTitle(),
                    'source' => 'note',
                    'adminUserId' => $annotation->getAdminUserId(),
                ];
            }
        }
        $rules = Mage::getResourceModel('salesrule/rule_collection')
            ->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('from_date', ['from' => $from, 'to' => $to]);
        foreach ($rules as $rule) {
            $notes[] = [
                'id' => null,
                'date' => substr((string) $rule->getData('from_date'), 0, 10),
                'title' => Mage::helper('reports')->__('Promotion starts: %s', (string) $rule->getData('name')),
                'source' => 'promotion',
                'adminUserId' => null,
            ];
        }
        usort($notes, static fn(array $a, array $b): int => strcmp($a['date'], $b['date']));
        return $notes;
    }

    /** @throws Mage_Core_Exception_Forbidden */
    private function assertOwner(Mage_Reports_Model_Annotation $annotation, int $adminUserId): void
    {
        $owner = $annotation->getAdminUserId();
        if ($owner !== null && $owner !== $adminUserId) {
            throw new Mage_Core_Exception_Forbidden(Mage::helper('reports')->__('Only the author of a note can change or delete it.'));
        }
    }
}
