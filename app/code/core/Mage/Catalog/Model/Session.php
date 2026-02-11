<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method $this setBeforeCompareUrl(string $value)
 * @method array getFormData()
 * @method $this setFormData(array $value)
 * @method int getLastViewedCategoryId()
 * @method int getLastViewedProductId()
 * @method $this setLastViewedProductId(int $value)
 * @method int getLastVisitedCategoryId()
 * @method string getLimitPage()
 * @method bool getParamsMemorizeDisabled()
 * @method string getSortDirection()
 * @method string getSortOrder()
 * @method $this unsDisplayMode()
 * @method $this unsLimitPage()
 * @method $this unsSortDirection()
 * @method $this unsSortOrder()
 */

class Mage_Catalog_Model_Session extends Mage_Core_Model_Session_Abstract
{
    public function __construct()
    {
        $this->init('catalog');
    }

    /**
     * @return string
     */
    public function getDisplayMode()
    {
        return $this->_getData('display_mode');
    }
}
