<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

declare(strict_types=1);

class Mage_Directory_Adminhtml_Directory_RegionController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'system/directory/regions';

    protected function _initRegion(): Mage_Directory_Model_Region|false
    {
        $id = $this->getRequest()->getParam('id');
        $model = Mage::getModel('directory/region');

        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                return false;
            }
        }

        Mage::register('current_region', $model);
        return $model;
    }

    protected function initAction(): self
    {
        $this->loadLayout()
            ->_setActiveMenu('system/directory/regions')
            ->_addBreadcrumb(
                Mage::helper('directory')->__('System'),
                Mage::helper('directory')->__('System'),
            )
            ->_addBreadcrumb(
                Mage::helper('directory')->__('Directory Management'),
                Mage::helper('directory')->__('Directory Management'),
            )
            ->_addBreadcrumb(
                Mage::helper('directory')->__('Regions'),
                Mage::helper('directory')->__('Regions'),
            );
        return $this;
    }

    #[Maho\Config\Route('/admin/directory_region/index')]
    public function indexAction(): void
    {
        $this->_title($this->__('System'))
            ->_title($this->__('Directory Management'))
            ->_title($this->__('Regions'));

        $this->initAction();
        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/directory_region/grid')]
    public function gridAction(): void
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/directory_region/new')]
    public function newAction(): void
    {
        $this->_forward('edit');
    }

    #[Maho\Config\Route('/admin/directory_region/edit')]
    public function editAction(): void
    {
        $model = $this->_initRegion();

        if ($model === false) {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('directory')->__('This region no longer exists.'),
            );
            $this->_redirect('*/*/');
            return;
        }

        $data = Mage::getSingleton('adminhtml/session')->getFormData(true);
        if (!empty($data)) {
            $model->addData($data);
        }

        $this->initAction();

        if ($model->getId()) {
            $this->_title($model->getName())
                ->_addBreadcrumb(
                    Mage::helper('directory')->__('Edit Region'),
                    Mage::helper('directory')->__('Edit Region'),
                );
        } else {
            $this->_title($this->__('New Region'))
                ->_addBreadcrumb(
                    Mage::helper('directory')->__('New Region'),
                    Mage::helper('directory')->__('New Region'),
                );
        }

        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/directory_region/save')]
    public function saveAction(): void
    {
        $model = $this->_initRegion();
        $data = $this->getRequest()->getPost();

        try {
            if (empty($data)) {
                Mage::throwException(Mage::helper('adminhtml')->__('Unable to complete this request.'));
            }

            if ($model === false) {
                Mage::getSingleton('adminhtml/session')->addError(
                    Mage::helper('directory')->__('This region no longer exists.'),
                );
                $this->_redirect('*/*/');
                return;
            }

            $model->addData($data);

            $errors = $model->validate();
            if (is_array($errors)) {
                Mage::throwException(implode(' ', $errors));
            }

            $model->save();

            Mage::getSingleton('adminhtml/session')->addSuccess(
                Mage::helper('directory')->__('The region has been saved.'),
            );
            Mage::getSingleton('adminhtml/session')->setFormData(false);

            if ($this->getRequest()->getParam('back')) {
                $this->_redirect('*/*/edit', ['id' => $model->getId()]);
                return;
            }
            $this->_redirect('*/*/');
            return;
        } catch (Mage_Core_Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('adminhtml')->__('Internal Error'));
            Mage::logException($e);
        }

        Mage::getSingleton('adminhtml/session')->setFormData($data);

        if ($model->getId()) {
            $this->_redirect('*/*/edit', ['id' => $model->getId()]);
        } else {
            $this->_redirect('*/*/new');
        }
    }

    #[Maho\Config\Route('/admin/directory_region/delete')]
    public function deleteAction(): void
    {
        $model = $this->_initRegion();

        try {
            if ($model === false || !$model->getId()) {
                Mage::throwException(
                    Mage::helper('directory')->__('This region no longer exists.'),
                );
            }

            if ($model->hasTranslation()) {
                Mage::getSingleton('adminhtml/session')->addError(
                    Mage::helper('directory')->__('Cannot delete region with existing region names. Please delete all region names first.'),
                );
                $this->_redirect('*/*/edit', ['_current' => true]);
                return;
            }

            $model->delete();

            Mage::getSingleton('adminhtml/session')->addSuccess(
                Mage::helper('directory')->__('The region has been deleted.'),
            );
        } catch (Mage_Core_Exception $e) {
            $error = $e->getMessage();
        } catch (Exception $e) {
            $error = Mage::helper('adminhtml')->__('Internal Error');
            Mage::logException($e);
        }

        if (isset($error)) {
            Mage::getSingleton('adminhtml/session')->addError($error);
        }

        $this->_redirect('*/*/');
    }

    #[Maho\Config\Route('/admin/directory_region/massDelete')]
    public function massDeleteAction(): void
    {
        $regionIds = $this->getRequest()->getPost('regions');

        try {
            if (!is_array($regionIds)) {
                Mage::throwException(
                    Mage::helper('directory')->__('Please select region(s).'),
                );
            }

            $deletedCount = 0;
            $skippedCount = 0;

            foreach ($regionIds as $regionId) {
                $model = Mage::getModel('directory/region')->load($regionId);

                if (!$model->getId() || $model->hasTranslation()) {
                    $skippedCount++;
                } else {
                    $model->delete();
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
                    Mage::helper('directory')->__('%d region(s) were skipped because they have existing region names.', $skippedCount),
                );
            }
        } catch (Mage_Core_Exception $e) {
            $error = $e->getMessage();
        } catch (Exception $e) {
            $error = Mage::helper('adminhtml')->__('Internal Error');
            Mage::logException($e);
        }

        if (isset($error)) {
            Mage::getSingleton('adminhtml/session')->addError($error);
        }
        $this->_redirect('*/*/');
    }

    #[Maho\Config\Route('/admin/directory_region/translationGrid')]
    public function translationGridAction(): void
    {
        $this->_initRegion();
        $this->loadLayout()->renderLayout();
    }

    #[Maho\Config\Route('/admin/directory_region/translationSave')]
    public function translationSaveAction(): void
    {
        $model = $this->_initRegion();
        $data = $this->getRequest()->getPost();

        try {
            if (empty($data) || !$this->getRequest()->isAjax()) {
                Mage::throwException(Mage::helper('adminhtml')->__('Unable to complete this request.'));
            }

            if ($model === false || !$model->getId()) {
                Mage::throwException(Mage::helper('directory')->__('This region no longer exists.'));
            }

            $errors = $model->validateTranslation($data);
            if (is_array($errors)) {
                Mage::throwException(implode(' ', $errors));
            }

            $model->saveTranslation($data);
            $this->getResponse()->setBodyJson(['success' => true]);

        } catch (Mage_Core_Exception $e) {
            $error = $e->getMessage();
        } catch (Exception $e) {
            $error = Mage::helper('adminhtml')->__('Internal Error');
            Mage::logException($e);
        }

        if (isset($error)) {
            Mage::getSingleton('adminhtml/session')->setFormData($data);
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $error]);
        }
    }

    #[Maho\Config\Route('/admin/directory_region/translationMassDelete')]
    public function translationMassDeleteAction(): void
    {
        $model = $this->_initRegion();
        $localeIds = $this->getRequest()->getPost('locale_id');

        try {
            if (!$this->getRequest()->isAjax()) {
                Mage::throwException(Mage::helper('adminhtml')->__('Unable to complete this request.'));
            }

            if ($model === false || !$model->getId()) {
                Mage::throwException(Mage::helper('directory')->__('This region no longer exists.'));
            }

            if (!is_array($localeIds)) {
                Mage::throwException(
                    Mage::helper('directory')->__('Please select region name(s).'),
                );
            }

            $deletedCount = 0;

            foreach ($localeIds as $localeId) {
                if (!str_contains($localeId, '|')) {
                    continue;
                }
                [, $locale] = explode('|', $localeId);
                $result = $model->deleteTranslation($locale);
                if ($result === true) {
                    $deletedCount++;
                }
            }

            $this->getResponse()->setBodyJson([
                'success' => true,
                'message' => Mage::helper('adminhtml')->__('Total of %d record(s) were deleted.', $deletedCount),
            ]);
        } catch (Mage_Core_Exception $e) {
            $error = $e->getMessage();
        } catch (Exception $e) {
            $error = Mage::helper('adminhtml')->__('Internal Error');
            Mage::logException($e);
        }

        if (isset($error)) {
            $this->getResponse()->setBodyJson([
                'error' => true,
                'message' => $error,
            ]);
        }
    }

    #[Maho\Config\Route('/admin/directory_region/import')]
    public function importAction(): void
    {
        $request = $this->getRequest();
        $countries = array_filter(array_map(trim(...), explode(',', (string) $request->getParam('countries'))));
        $locales = array_map(trim(...), explode(',', (string) $request->getParam('locales', 'en_US')));
        $updateExisting = $request->getParam('updateExisting') === 'true';
        $dryRun = $request->getParam('dryRun') === 'true';
        if ($countries === []) {
            // mahoFetch throws on the error field
            $this->getResponse()->setBodyJson(['error' => 'Countries parameter is required']);
            return;
        }

        $output = '';
        $log = function (string $message, string $level) use (&$output): void {
            $output .= match ($level) {
                'error' => '❌ ',
                'comment', 'debug' => 'ℹ️  ',
                default => '✅ ',
            } . $message . "\n";
        };
        $totals = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'total' => 0];
        try {
            /** @var Mage_Directory_Service_Region $service */
            $service = Mage::getService('directory/region');
            foreach ($countries as $country) {
                $output .= "\n🌍 Processing country: {$country}\n";
                $result = $service->import($country, $locales, $updateExisting, $dryRun, $log);
                foreach ($totals as $key => $count) {
                    $totals[$key] = $count + $result[$key];
                }
            }
        } catch (Mage_Core_Exception $e) {
            $this->getResponse()->setBodyJson(['error' => $e->getMessage()]);
            return;
        } catch (Throwable $e) {
            Mage::logException($e);
            $this->getResponse()->setBodyJson(['error' => $e->getMessage()]);
            return;
        }

        $output .= "\n📊 SUMMARY\n";
        $output .= '🏁 Countries processed: ' . count($countries) . "\n";
        $output .= "📥 Imported: {$totals['imported']}\n";
        $output .= "📝 Updated: {$totals['updated']}\n";
        $output .= "⏭️  Skipped: {$totals['skipped']}\n";
        $output .= "📦 Total: {$totals['total']}\n";
        $output .= $dryRun ? "\n⚠️  This was a DRY RUN - no changes were made.\n" : "\n✨ Import completed successfully!\n";

        $this->getResponse()->setBodyJson(['success' => true, 'output' => $output]);
    }
}
