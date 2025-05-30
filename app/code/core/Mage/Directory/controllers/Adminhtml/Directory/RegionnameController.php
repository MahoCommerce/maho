<?php

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Directory_Adminhtml_Directory_RegionnameController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Init actions
     */
    protected function _initAction(): self
    {
        $this->loadLayout()
            ->_setActiveMenu('system/directory/region_names')
            ->_addBreadcrumb(
                Mage::helper('adminhtml')->__('System'),
                Mage::helper('adminhtml')->__('System'),
            )
            ->_addBreadcrumb(
                Mage::helper('adminhtml')->__('Directory Management'),
                Mage::helper('adminhtml')->__('Directory Management'),
            )
            ->_addBreadcrumb(
                Mage::helper('adminhtml')->__('Region Names'),
                Mage::helper('adminhtml')->__('Region Names'),
            );
        return $this;
    }

    /**
     * Index action - show region names grid
     */
    public function indexAction(): void
    {
        $this->_title($this->__('System'))
            ->_title($this->__('Directory Management'))
            ->_title($this->__('Region Names'));

        $this->_initAction();
        $this->renderLayout();
    }

    /**
     * Grid action for AJAX requests
     */
    public function gridAction(): void
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * New region name action
     */
    public function newAction(): void
    {
        $this->_forward('edit');
    }

    /**
     * Edit region name action
     */
    public function editAction(): void
    {
        $locale = $this->getRequest()->getParam('locale');
        $regionId = $this->getRequest()->getParam('region_id');

        if ($locale && $regionId) {
            // Load the region name data from the database
            $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
            $resource = Mage::getSingleton('core/resource');
            $select = $adapter->select()
                ->from($resource->getTableName('directory_country_region_name'))
                ->where('locale = ?', $locale)
                ->where('region_id = ?', $regionId);

            $data = $adapter->fetchRow($select);

            if (!$data) {
                Mage::getSingleton('adminhtml/session')->addError(
                    Mage::helper('adminhtml')->__('This region name no longer exists.'),
                );
                $this->_redirect('*/*/');
                return;
            }

            // Load the region for additional context
            $region = Mage::getModel('directory/region')->load($regionId);
            $data['region'] = $region;
        } else {
            $data = [];
        }

        $this->_title($locale && $regionId ? $this->__('Edit Region Name') : $this->__('New Region Name'));

        $formData = Mage::getSingleton('adminhtml/session')->getFormData(true);
        if (!empty($formData)) {
            $data = array_merge($data, $formData);
        }

        Mage::register('current_region_name', $data);

        $this->_initAction()
            ->_addBreadcrumb(
                ($locale && $regionId) ? Mage::helper('adminhtml')->__('Edit Region Name') : Mage::helper('adminhtml')->__('New Region Name'),
                ($locale && $regionId) ? Mage::helper('adminhtml')->__('Edit Region Name') : Mage::helper('adminhtml')->__('New Region Name'),
            )
            ->renderLayout();
    }

    /**
     * Save region name action
     */
    public function saveAction(): void
    {
        if ($data = $this->getRequest()->getPost()) {
            $locale = $this->getRequest()->getParam('locale');
            $regionId = $this->getRequest()->getParam('region_id');

            try {
                $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
                $resource = Mage::getSingleton('core/resource');
                $table = $resource->getTableName('directory_country_region_name');

                if ($locale && $regionId) {
                    // Update existing record
                    $adapter->update(
                        $table,
                        ['name' => $data['name']],
                        ['locale = ?' => $locale, 'region_id = ?' => $regionId],
                    );
                } else {
                    // Insert new record
                    $adapter->insert($table, [
                        'locale' => $data['locale'],
                        'region_id' => $data['region_id'],
                        'name' => $data['name'],
                    ]);
                }

                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('adminhtml')->__('The region name has been saved.'),
                );
                Mage::getSingleton('adminhtml/session')->setFormData(false);

                if ($this->getRequest()->getParam('back')) {
                    $this->_redirect('*/*/edit', [
                        'locale' => $data['locale'] ?? $locale,
                        'region_id' => $data['region_id'] ?? $regionId,
                    ]);
                    return;
                }
                $this->_redirect('*/*/');
                return;
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                Mage::getSingleton('adminhtml/session')->setFormData($data);
                $this->_redirect('*/*/edit', [
                    'locale' => $locale,
                    'region_id' => $regionId,
                ]);
                return;
            }
        }

        Mage::getSingleton('adminhtml/session')->addError(
            Mage::helper('adminhtml')->__('Unable to find region name to save.'),
        );
        $this->_redirect('*/*/');
    }

    /**
     * Delete region name action
     */
    public function deleteAction(): void
    {
        $locale = $this->getRequest()->getParam('locale');
        $regionId = $this->getRequest()->getParam('region_id');

        if ($locale && $regionId) {
            try {
                $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
                $resource = Mage::getSingleton('core/resource');
                $table = $resource->getTableName('directory_country_region_name');

                $adapter->delete($table, [
                    'locale = ?' => $locale,
                    'region_id = ?' => $regionId,
                ]);

                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('adminhtml')->__('The region name has been deleted.'),
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        } else {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('adminhtml')->__('Unable to find a region name to delete.'),
            );
        }
        $this->_redirect('*/*/');
    }

    /**
     * Mass delete action
     */
    public function massDeleteAction(): void
    {
        $regionNameIds = $this->getRequest()->getParam('region_name');
        if (!is_array($regionNameIds)) {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('adminhtml')->__('Please select region name(s).'),
            );
        } else {
            try {
                $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
                $resource = Mage::getSingleton('core/resource');
                $table = $resource->getTableName('directory_country_region_name');

                foreach ($regionNameIds as $regionNameId) {
                    [$locale, $regionId] = explode('|', $regionNameId);
                    $adapter->delete($table, [
                        'locale = ?' => $locale,
                        'region_id = ?' => $regionId,
                    ]);
                }

                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('adminhtml')->__('Total of %d record(s) were deleted.', count($regionNameIds)),
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }
        $this->_redirect('*/*/index');
    }

    /**
     * Check ACL permissions
     */
    #[\Override]
    protected function _isAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed('system/directory/region_names');
    }
}
