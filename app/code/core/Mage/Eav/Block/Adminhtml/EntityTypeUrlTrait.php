<?php

/**
 * Keeps the entity_type request parameter of System > Attributes in the urls of a block.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

trait Mage_Eav_Block_Adminhtml_EntityTypeUrlTrait
{
    /**
     * @param string $route
     * @param array<mixed> $params The route parameters, such as _query, _secure or _store
     * @return string
     */
    #[\Override]
    public function getUrl($route = '', $params = [])
    {
        $entityType = $this->getRequest()->getParam('entity_type');
        if ($entityType !== null && !isset($params['entity_type'])) {
            $params['entity_type'] = $entityType;
        }
        return parent::getUrl($route, $params);
    }
}
