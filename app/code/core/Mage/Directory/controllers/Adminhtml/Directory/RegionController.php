<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Directory_Adminhtml_Directory_RegionController extends Mage_Adminhtml_Controller_Action
{
    protected function initAction(): self
    {
        $this->loadLayout()
            ->_setActiveMenu('system/directory/regions')
            ->_addBreadcrumb(
                Mage::helper('adminhtml')->__('System'),
                Mage::helper('adminhtml')->__('System'),
            )
            ->_addBreadcrumb(
                Mage::helper('adminhtml')->__('Directory Management'),
                Mage::helper('adminhtml')->__('Directory Management'),
            )
            ->_addBreadcrumb(
                Mage::helper('adminhtml')->__('Regions'),
                Mage::helper('adminhtml')->__('Regions'),
            );
        return $this;
    }

    /**
     * Index action - show regions grid
     */
    public function indexAction(): void
    {
        $this->_title($this->__('System'))
            ->_title($this->__('Directory Management'))
            ->_title($this->__('Regions'));

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
     * New region action
     */
    public function newAction(): void
    {
        $this->_forward('edit');
    }

    /**
     * Edit region action
     */
    public function editAction(): void
    {
        $id = $this->getRequest()->getParam('id');
        $model = Mage::getModel('directory/region');

        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                Mage::getSingleton('adminhtml/session')->addError(
                    Mage::helper('adminhtml')->__('This region no longer exists.'),
                );
                $this->_redirect('*/*/');
                return;
            }
        }

        $this->_title($model->getId() ? $model->getName() : $this->__('New Region'));

        $data = Mage::getSingleton('adminhtml/session')->getFormData(true);
        if (!empty($data)) {
            $model->setData($data);
        }

        Mage::register('current_region', $model);

        $this->initAction()
            ->_addBreadcrumb(
                $id ? Mage::helper('adminhtml')->__('Edit Region') : Mage::helper('adminhtml')->__('New Region'),
                $id ? Mage::helper('adminhtml')->__('Edit Region') : Mage::helper('adminhtml')->__('New Region'),
            )
            ->renderLayout();
    }

    /**
     * Save region action
     */
    public function saveAction(): void
    {
        if ($data = $this->getRequest()->getPost()) {
            $id = $this->getRequest()->getParam('id');
            $model = Mage::getModel('directory/region');

            if ($id) {
                $model->load($id);
                if (!$model->getId()) {
                    Mage::getSingleton('adminhtml/session')->addError(
                        Mage::helper('adminhtml')->__('This region no longer exists.'),
                    );
                    $this->_redirect('*/*/');
                    return;
                }
            }

            // Server-side validation
            if (!$this->_validateRegionData($data)) {
                Mage::getSingleton('adminhtml/session')->setFormData($data);
                $this->_redirect('*/*/edit', ['id' => $this->getRequest()->getParam('id')]);
                return;
            }

            $model->setData($data);

            try {
                $model->save();
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('adminhtml')->__('The region has been saved.'),
                );
                Mage::getSingleton('adminhtml/session')->setFormData(false);

                if ($this->getRequest()->getParam('back')) {
                    $this->_redirect('*/*/edit', ['id' => $model->getId()]);
                    return;
                }
                $this->_redirect('*/*/');
                return;
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                Mage::getSingleton('adminhtml/session')->setFormData($data);
                $this->_redirect('*/*/edit', ['id' => $this->getRequest()->getParam('id')]);
                return;
            }
        }

        Mage::getSingleton('adminhtml/session')->addError(
            Mage::helper('adminhtml')->__('Unable to find region to save.'),
        );
        $this->_redirect('*/*/');
    }

    /**
     * Delete region action
     */
    public function deleteAction(): void
    {
        if ($id = $this->getRequest()->getParam('id')) {
            try {
                $model = Mage::getModel('directory/region');
                $model->load($id);
                if (!$model->getId()) {
                    Mage::getSingleton('adminhtml/session')->addError(
                        Mage::helper('adminhtml')->__('Unable to find a region to delete.'),
                    );
                } else {
                    // Check for dependent region names before deletion
                    if ($this->_hasRegionNames($id)) {
                        Mage::getSingleton('adminhtml/session')->addError(
                            Mage::helper('adminhtml')->__('Cannot delete region with existing region names. Please delete all region names first.'),
                        );
                    } else {
                        $model->delete();
                        Mage::getSingleton('adminhtml/session')->addSuccess(
                            Mage::helper('adminhtml')->__('The region has been deleted.'),
                        );
                    }
                }
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }
        $this->_redirect('*/*/');
    }

    /**
     * Mass delete action
     */
    public function massDeleteAction(): void
    {
        $regionIds = $this->getRequest()->getParam('region');
        if (!is_array($regionIds)) {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('adminhtml')->__('Please select region(s).'),
            );
        } else {
            try {
                $deletedCount = 0;
                $skippedCount = 0;

                foreach ($regionIds as $regionId) {
                    if ($this->_hasRegionNames($regionId)) {
                        $skippedCount++;
                    } else {
                        $region = Mage::getModel('directory/region')->load($regionId);
                        $region->delete();
                        $deletedCount++;
                    }
                }

                if ($deletedCount > 0) {
                    Mage::getSingleton('adminhtml/session')->addSuccess(
                        Mage::helper('adminhtml')->__('Total of %d record(s) were deleted.', $deletedCount),
                    );
                }

                if ($skippedCount > 0) {
                    Mage::getSingleton('adminhtml/session')->addWarning(
                        Mage::helper('adminhtml')->__('%d region(s) were skipped because they have existing region names.', $skippedCount),
                    );
                }
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }
        $this->_redirect('*/*/index');
    }

    /**
     * Validate region data
     */
    protected function _validateRegionData(array $data): bool
    {
        $errors = [];

        // Validate country ID
        if (empty($data['country_id'])) {
            $errors[] = Mage::helper('adminhtml')->__('Country is required.');
        } else {
            // Check if country exists
            $country = Mage::getModel('directory/country')->load($data['country_id']);
            if (!$country->getId()) {
                $errors[] = Mage::helper('adminhtml')->__('Selected country does not exist.');
            }
        }

        // Validate default name
        if (empty($data['default_name'])) {
            $errors[] = Mage::helper('adminhtml')->__('Default name is required.');
        } elseif (strlen($data['default_name']) > 255) {
            $errors[] = Mage::helper('adminhtml')->__('Default name cannot be longer than 255 characters.');
        }

        // Validate code if provided
        if (!empty($data['code']) && strlen($data['code']) > 32) {
            $errors[] = Mage::helper('adminhtml')->__('Region code cannot be longer than 32 characters.');
        }

        // Check for duplicate region code within the same country
        if (!empty($data['code']) && !empty($data['country_id'])) {
            $currentRegionId = $this->getRequest()->getParam('id');
            $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
            $resource = Mage::getSingleton('core/resource');
            $table = $resource->getTableName('directory_country_region');

            $select = $adapter->select()
                ->from($table, 'COUNT(*)')
                ->where('country_id = ?', $data['country_id'])
                ->where('code = ?', $data['code']);
            
            // If editing, exclude current region from check
            if ($currentRegionId) {
                $select->where('region_id != ?', $currentRegionId);
            }

            if ((int) $adapter->fetchOne($select) > 0) {
                $errors[] = Mage::helper('adminhtml')->__('A region with code "%s" already exists in this country.', $data['code']);
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
     * Check if region has region names
     */
    protected function _hasRegionNames(string|int $regionId): bool
    {
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
        $resource = Mage::getSingleton('core/resource');
        $table = $resource->getTableName('directory_country_region_name');

        $select = $adapter->select()
            ->from($table, 'COUNT(*)')
            ->where('region_id = ?', $regionId);

        return (int) $adapter->fetchOne($select) > 0;
    }

    /**
     * Check ACL permissions
     */
    #[\Override]
    protected function _isAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed('system/directory/regions');
    }
}
