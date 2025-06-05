<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Directory_Adminhtml_Directory_RegionnameController extends Mage_Adminhtml_Controller_Action
{
    protected function initAction(): self
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

        $this->initAction();
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

        // Debug logging
        Mage::log('Edit Action Debug - URL params: locale=' . ($locale ?? 'NULL') . ', region_id=' . ($regionId ?? 'NULL'), null, 'regionname_edit_debug.log');

        if ($locale && $regionId) {
            // Load the region name data from the database
            $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
            $resource = Mage::getSingleton('core/resource');
            $select = $adapter->select()
                ->from($resource->getTableName('directory_country_region_name'))
                ->where('locale = ?', $locale)
                ->where('region_id = ?', $regionId);

            $data = $adapter->fetchRow($select);

            Mage::log('Edit Action Debug - Fetched data: ' . print_r($data, true), null, 'regionname_edit_debug.log');

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
            Mage::log('Edit Action Debug - No URL params, creating empty data', null, 'regionname_edit_debug.log');
            $data = [];
        }

        $this->_title($locale && $regionId ? $this->__('Edit Region Name') : $this->__('New Region Name'));

        $formData = Mage::getSingleton('adminhtml/session')->getFormData(true);
        if (!empty($formData)) {
            $data = array_merge($data, $formData);
        }

        Mage::register('current_region_name', $data);

        $this->initAction()
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
            $isUpdate = !empty($data['is_update']) && $data['is_update'] === '1';
            $originalLocale = $data['original_locale'] ?? null;
            $originalRegionId = $data['original_region_id'] ?? null;

            // Debug logging
            Mage::log('Save Action Debug:', null, 'regionname_save_debug.log');
            Mage::log('Is Update: ' . ($isUpdate ? 'YES' : 'NO'), null, 'regionname_save_debug.log');
            Mage::log('Original locale: ' . ($originalLocale ?? 'NULL') . ', Original region_id: ' . ($originalRegionId ?? 'NULL'), null, 'regionname_save_debug.log');
            Mage::log('POST data: ' . print_r($data, true), null, 'regionname_save_debug.log');

            // Server-side validation
            if (!$this->_validateRegionNameData($data, $originalLocale, $originalRegionId)) {
                Mage::log('Validation failed', null, 'regionname_save_debug.log');
                Mage::getSingleton('adminhtml/session')->setFormData($data);
                if ($isUpdate) {
                    $this->_redirect('*/*/edit', [
                        'locale' => $originalLocale,
                        'region_id' => $originalRegionId,
                    ]);
                } else {
                    $this->_redirect('*/*/new');
                }
                return;
            }

            try {
                $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
                $resource = Mage::getSingleton('core/resource');
                $table = $resource->getTableName('directory_country_region_name');

                if ($isUpdate) {
                    // Update existing record
                    Mage::log('Updating existing record', null, 'regionname_save_debug.log');
                    $adapter->update(
                        $table,
                        ['name' => $data['name']],
                        ['locale = ?' => $originalLocale, 'region_id = ?' => $originalRegionId],
                    );
                } else {
                    // Insert new record
                    Mage::log('Inserting new record', null, 'regionname_save_debug.log');
                    $insertData = [
                        'locale' => $data['locale'],
                        'region_id' => (int) $data['region_id'],
                        'name' => $data['name'],
                    ];
                    Mage::log('Insert data: ' . print_r($insertData, true), null, 'regionname_save_debug.log');
                    $adapter->insert($table, $insertData);
                    Mage::log('Insert completed', null, 'regionname_save_debug.log');
                }

                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('adminhtml')->__('The region name has been saved.'),
                );
                Mage::getSingleton('adminhtml/session')->setFormData(false);

                if ($this->getRequest()->getParam('back')) {
                    $this->_redirect('*/*/edit', [
                        'locale' => $data['locale'],
                        'region_id' => $data['region_id'],
                    ]);
                    return;
                }
                $this->_redirect('*/*/');
                return;
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                Mage::getSingleton('adminhtml/session')->setFormData($data);
                if ($isUpdate) {
                    $this->_redirect('*/*/edit', [
                        'locale' => $originalLocale,
                        'region_id' => $originalRegionId,
                    ]);
                } else {
                    $this->_redirect('*/*/new');
                }
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
                    // Handle both formats: "en_US|123" (composite_id) or "en_US_123" (id)
                    if (strpos($regionNameId, '|') !== false) {
                        // Composite ID format: en_US|123
                        [$locale, $regionId] = explode('|', $regionNameId);
                    } else {
                        // Generated ID format: en_US_123
                        $parts = explode('_', $regionNameId);
                        if (count($parts) >= 3) {
                            // The region ID is the last part, locale is everything before the last underscore
                            $regionId = array_pop($parts);
                            $locale = implode('_', $parts);
                        } else {
                            continue; // Skip invalid format
                        }
                    }

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
     * Validate region name data
     */
    protected function _validateRegionNameData(array $data, ?string $originalLocale, ?string $originalRegionId): bool
    {
        $errors = [];

        $isUpdate = !empty($data['is_update']) && $data['is_update'] === '1';

        // Validate locale
        if (empty($data['locale'])) {
            $errors[] = Mage::helper('adminhtml')->__('Locale is required.');
        }

        // Validate region ID
        if (empty($data['region_id'])) {
            $errors[] = Mage::helper('adminhtml')->__('Region is required.');
        } else {
            // Check if region exists
            $region = Mage::getModel('directory/region')->load($data['region_id']);
            if (!$region->getId()) {
                $errors[] = Mage::helper('adminhtml')->__('Selected region does not exist.');
            }
        }

        // Validate name
        if (empty($data['name'])) {
            $errors[] = Mage::helper('adminhtml')->__('Region name is required.');
        } elseif (strlen($data['name']) > 255) {
            $errors[] = Mage::helper('adminhtml')->__('Region name cannot be longer than 255 characters.');
        }

        // Check for duplicate locale/region combination
        if (!empty($data['locale']) && !empty($data['region_id'])) {
            $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
            $resource = Mage::getSingleton('core/resource');
            $table = $resource->getTableName('directory_country_region_name');

            $select = $adapter->select()
                ->from($table, 'COUNT(*)')
                ->where('locale = ?', $data['locale'])
                ->where('region_id = ?', $data['region_id']);

            $count = (int) $adapter->fetchOne($select);

            if ($isUpdate) {
                // For updates, it's okay if the record exists and it's the same one we're updating
                // Only error if count > 1 or if count = 1 but it's not the original record
                if ($count > 1 ||
                    ($count === 1 && ($data['locale'] !== $originalLocale || $data['region_id'] != $originalRegionId))) {
                    $errors[] = Mage::helper('adminhtml')->__('A region name for this locale and region combination already exists.');
                }
            } else {
                // For new entries, any existing record is a duplicate
                if ($count > 0) {
                    $errors[] = Mage::helper('adminhtml')->__('A region name for this locale and region combination already exists.');
                }
            }
        }

        // Add errors to session if any
        if (!empty($errors)) {
            foreach ($errors as $error) {
                Mage::getSingleton('adminhtml/session')->addError($error);
            }
            return false;
        }

        return true;
    }

    /**
     * Check ACL permissions
     */
    #[\Override]
    protected function _isAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed('system/directory/regionname');
    }
}
