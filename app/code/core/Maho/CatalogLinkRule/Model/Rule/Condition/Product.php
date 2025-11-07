<?php
/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CatalogLinkRule
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/**
 * Source Product Condition
 *
 * @category   Maho
 * @package    Maho_CatalogLinkRule
 */
class Maho_CatalogLinkRule_Model_Rule_Condition_Product extends Mage_CatalogRule_Model_Rule_Condition_Product
{
    /**
     * Initialize the model
     */
    public function __construct()
    {
        parent::__construct();
        $this->setType('cataloglinkrule/rule_condition_product');
    }
}
