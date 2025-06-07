<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Directory_Adminhtml_Directory_CountryController extends Mage_Adminhtml_Controller_Action
{
    protected function initAction(): self
    {
        $this->loadLayout()
            ->_setActiveMenu('system/directory/countries')
            ->_addBreadcrumb(
                Mage::helper('adminhtml')->__('System'),
                Mage::helper('adminhtml')->__('System'),
            )
            ->_addBreadcrumb(
                Mage::helper('adminhtml')->__('Directory Management'),
                Mage::helper('adminhtml')->__('Directory Management'),
            )
            ->_addBreadcrumb(
                Mage::helper('adminhtml')->__('Countries'),
                Mage::helper('adminhtml')->__('Countries'),
            );
        return $this;
    }

    public function indexAction(): void
    {
        $this->_title($this->__('System'))
            ->_title($this->__('Directory Management'))
            ->_title($this->__('Countries'));

        $this->initAction();
        $this->renderLayout();
    }

    public function gridAction(): void
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    public function newAction(): void
    {
        $this->_forward('edit');
    }

    public function editAction(): void
    {
        $id = $this->getRequest()->getParam('id');
        $model = Mage::getModel('directory/country');

        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                Mage::getSingleton('adminhtml/session')->addError(
                    Mage::helper('adminhtml')->__('This country no longer exists.'),
                );
                $this->_redirect('*/*/');
                return;
            }
        }

        $this->_title($model->getId() ? $model->getName() : $this->__('New Country'));

        $data = Mage::getSingleton('adminhtml/session')->getFormData(true);
        if (!empty($data)) {
            $model->setData($data);
        }

        Mage::register('current_country', $model);

        $this->initAction()
            ->_addBreadcrumb(
                $id ? Mage::helper('adminhtml')->__('Edit Country') : Mage::helper('adminhtml')->__('New Country'),
                $id ? Mage::helper('adminhtml')->__('Edit Country') : Mage::helper('adminhtml')->__('New Country'),
            )
            ->renderLayout();
    }

    public function saveAction(): void
    {
        if ($data = $this->getRequest()->getPost()) {
            $id = $this->getRequest()->getParam('id');
            $model = Mage::getModel('directory/country');

            if ($id) {
                $model->load($id);
                if (!$model->getId()) {
                    Mage::getSingleton('adminhtml/session')->addError(
                        Mage::helper('adminhtml')->__('This country no longer exists.'),
                    );
                    $this->_redirect('*/*/');
                    return;
                }
            }

            // Server-side validation
            if (!$this->_validateCountryData($data)) {
                Mage::getSingleton('adminhtml/session')->setFormData($data);
                $this->_redirect('*/*/edit', ['id' => $id]);
                return;
            }

            $model->addData($data);

            try {
                $model->save();

                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('adminhtml')->__('The country has been saved.'),
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
                $this->_redirect('*/*/edit', ['id' => $id]);
                return;
            }
        }

        Mage::getSingleton('adminhtml/session')->addError(
            Mage::helper('adminhtml')->__('Unable to find country to save.'),
        );
        $this->_redirect('*/*/');
    }

    public function deleteAction(): void
    {
        if ($id = $this->getRequest()->getParam('id')) {
            try {
                $model = Mage::getModel('directory/country');
                $model->load($id);
                if (!$model->getId()) {
                    Mage::getSingleton('adminhtml/session')->addError(
                        Mage::helper('adminhtml')->__('Unable to find a country to delete.'),
                    );
                } else {
                    // Check for dependent regions before deletion
                    if ($this->_hasRegions($id)) {
                        Mage::getSingleton('adminhtml/session')->addError(
                            Mage::helper('adminhtml')->__('Cannot delete country with existing regions. Please delete all regions first.'),
                        );
                    } else {
                        $model->delete();
                        Mage::getSingleton('adminhtml/session')->addSuccess(
                            Mage::helper('adminhtml')->__('The country has been deleted.'),
                        );
                    }
                }
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }
        $this->_redirect('*/*/');
    }

    public function massDeleteAction(): void
    {
        $countryIds = $this->getRequest()->getParam('country');
        if (!is_array($countryIds)) {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('adminhtml')->__('Please select country(s).'),
            );
        } else {
            try {
                $deletedCount = 0;
                $skippedCount = 0;

                foreach ($countryIds as $countryId) {
                    if ($this->_hasRegions($countryId)) {
                        $skippedCount++;
                    } else {
                        $country = Mage::getModel('directory/country')->load($countryId);
                        $country->delete();
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
                        Mage::helper('adminhtml')->__('%d country(s) were skipped because they have existing regions.', $skippedCount),
                    );
                }
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }
        $this->_redirect('*/*/index');
    }

    protected function _validateCountryData(array $data): bool
    {
        $errors = [];

        // Validate country ID
        if (!$this->getRequest()->getParam('id')) {
            if (empty($data['country_id'])) {
                $errors[] = Mage::helper('adminhtml')->__('Country ID is required.');
            } elseif (!preg_match('/^[A-Z]{2}$/', $data['country_id'])) {
                $errors[] = Mage::helper('adminhtml')->__('Country ID must be exactly 2 uppercase letters.');
            }
        }

        // Validate ISO2 code if provided
        if (!empty($data['iso2_code']) && !preg_match('/^[A-Z]{2}$/', $data['iso2_code'])) {
            $errors[] = Mage::helper('adminhtml')->__('ISO2 code must be exactly 2 uppercase letters.');
        }

        // Validate ISO3 code if provided
        if (!empty($data['iso3_code']) && !preg_match('/^[A-Z]{3}$/', $data['iso3_code'])) {
            $errors[] = Mage::helper('adminhtml')->__('ISO3 code must be exactly 3 uppercase letters.');
        }

        // Check for duplicate country ID (only for new countries)
        if (!$this->getRequest()->getParam('id')) {
            $existingCountry = Mage::getModel('directory/country')->load($data['country_id']);
            if ($existingCountry->getId()) {
                $errors[] = Mage::helper('adminhtml')->__('A country with this ID already exists.');
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

    protected function _hasRegions(string $countryId): bool
    {
        $collection = Mage::getResourceModel('directory/region_collection')
            ->addFieldToFilter('country_id', $countryId);

        return $collection->getSize() > 0;
    }

    #[\Override]
    protected function _isAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed('system/directory/countries');
    }
}
