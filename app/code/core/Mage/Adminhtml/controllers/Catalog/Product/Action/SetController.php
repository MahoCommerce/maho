<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Catalog_Product_Action_SetController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'catalog/attributes/sets';

    #[\Override]
    protected function _construct()
    {
        // Define module dependent translate
        $this->setUsedModuleName('Mage_Catalog');
    }

    public function saveAction()
    {
        $request = $this->getRequest();

        /*
        $collection = Mage::getResourceModel('catalog/product_collection');
        $collection->getConnection()
            ->update(
                $collection->getTable('catalog/product'),
                ['attribute_set_id' => $request->getParam('attribute_set')],
                'entity_id IN (' . implode(',', $request->getParam('product')) . ')',
            );
*/
        $this->_getSession()->addSuccess(
            $this->__('%d product(s) were updated', sizeof($request->getParam('product') ?? [])),
        );

        $this->checkMissingDataFromMandatoryAttributes();

        $this->_redirect('*/catalog_product/', [
            'store' => (int) $request->getParam('store', Mage_Core_Model_App::ADMIN_STORE_ID),
        ]);
    }

    protected function checkMissingDataFromMandatoryAttributes()
    {
        $coreResource = Mage::getModel('core/resource');
        $readConnection = $coreResource->getConnection('core_read');
        $requiredAttributesBySetAndType = [];
        $requiredAttributes = $readConnection->fetchAll(
            "SELECT eas.attribute_set_id, ea.attribute_id, ea.backend_type
                FROM " . $coreResource->getTableName('eav/attribute') . " ea
                JOIN " . $coreResource->getTableName('catalog/eav_attribute') . " cea ON ea.attribute_id = cea.attribute_id
                JOIN " . $coreResource->getTableName('eav/entity_type') . " eet ON ea.entity_type_id = eet.entity_type_id
                JOIN " . $coreResource->getTableName('eav/attribute_set') . " eas ON eet.entity_type_id = eas.entity_type_id
                WHERE eet.entity_type_code = 'catalog_product'
                AND ea.is_required = 1 AND ea.backend_type <> 'static' AND cea.is_visible = 1
                ORDER BY attribute_set_id, attribute_id, backend_type");
        foreach ($requiredAttributes as $attribute) {
            $requiredAttributesBySetAndType[$attribute['attribute_set_id']][$attribute['backend_type']][] = $attribute['attribute_id'];
        }

        $brokenProducts = [];
        $productTable = $coreResource->getTableName('catalog/product');
        foreach ($requiredAttributesBySetAndType as $attributeSetId => $attributes) {
            foreach ($attributes as $backendType => $attributeIds) {
                $attributeDataTable = $coreResource->getTableName('catalog_product_entity_' . $backendType);
                $attributeIds = implode(',', $attributeIds);
                $results = $readConnection->fetchCol(
                    "SELECT DISTINCT cpe.sku 
                    FROM $productTable cpe
                    LEFT JOIN $attributeDataTable cpev ON (cpe.entity_id = cpev.entity_id AND cpev.attribute_id IN ($attributeIds))
                    WHERE cpe.attribute_set_id = $attributeSetId
                    AND cpev.value_id IS NULL");
                $brokenProducts = [...$brokenProducts, ...$results];
            }
        }
        $brokenProducts = array_unique($brokenProducts);
        $brokenProducts = array_values($brokenProducts);
        natcasesort($brokenProducts);

        $numOfBrokenProducts = sizeof($brokenProducts);
        $brokenProducts = array_slice($brokenProducts, 0, 50);
        $brokenProducts = implode(', ', $brokenProducts);

        if ($numOfBrokenProducts) {
            $this->_getSession()->addWarning(
                $this->__('%d product(s) do not have any data for some required attributes: %s.', $numOfBrokenProducts, $brokenProducts),
            );
        }
    }
}
