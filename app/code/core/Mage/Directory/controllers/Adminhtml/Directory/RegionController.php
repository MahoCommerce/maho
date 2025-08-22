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
    public const ADMIN_RESOURCE = 'system/directory/regions';

    #[\Override]
    public function preDispatch()
    {
        $this->_setForcedFormKeyActions('delete');
        return parent::preDispatch();
    }

    protected function _initRegion(): Mage_Directory_Model_Region|false
    {
        $id = $this->getRequest()->getUserParam('id');
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

    public function indexAction(): void
    {
        $this->_title($this->__('System'))
            ->_title($this->__('Directory Management'))
            ->_title($this->__('Regions'));

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
                Mage::throwException(implode('<br>', $errors));
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

    public function translationGridAction(): void
    {
        $this->_initRegion();
        $this->loadLayout()->renderLayout();
    }

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
                Mage::throwException(implode('<br>', $errors));
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
                [$regionId, $locale] = explode('|', $localeId);
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

    public function importAction(): void
    {
        try {
            $countries = $this->getRequest()->getParam('countries');
            $locales = $this->getRequest()->getParam('locales', 'en_US');
            $updateExisting = $this->getRequest()->getParam('updateExisting') === 'true';
            $dryRun = $this->getRequest()->getParam('dryRun') === 'true';

            if (empty($countries)) {
                throw new Exception('Countries parameter is required');
            }

            // Use the import command
            $importCommand = new \MahoCLI\Commands\SysDirectoryRegionsImport();

            $outputBuffer = '';
            $logger = function ($message, $level = 'info') use (&$outputBuffer) {
                $prefix = match ($level) {
                    'error' => '[ERROR] ',
                    'comment' => '[INFO] ',
                    default => '[INFO] ',
                };
                $outputBuffer .= $prefix . $message . "\n";
            };

            $totalResults = [
                'success' => true,
                'updated' => 0,
                'skipped' => 0,
                'total' => 0,
                'updateRecords' => [],
                'error' => null,
            ];

            // Process each country
            $countriesArray = explode(',', $countries);
            foreach ($countriesArray as $country) {
                $country = trim($country);
                if (empty($country)) {
                    continue;
                }

                $outputBuffer .= "\n=== Processing country: {$country} ===\n";

                $result = $importCommand->importRegionsData($country, [
                    'locales' => $locales,
                    'updateExisting' => $updateExisting,
                    'dryRun' => $dryRun,
                    'verbose' => true,
                ], $logger);

                if (!$result['success']) {
                    $totalResults['success'] = false;
                    $totalResults['error'] = $result['error'] ?? 'Unknown error';
                    break;
                }

                $totalResults['updated'] += $result['updated'];
                $totalResults['skipped'] += $result['skipped'];
                $totalResults['total'] += $result['total'];
                $totalResults['updateRecords'] = array_merge($totalResults['updateRecords'], $result['updateRecords'] ?? []);
            }

            // Add summary to output
            if ($totalResults['success']) {
                $outputBuffer .= "\n=== TOTAL SUMMARY ===\n";
                $outputBuffer .= "Countries processed: " . count($countriesArray) . "\n";
                $outputBuffer .= "Updated: {$totalResults['updated']}\n";
                $outputBuffer .= "Skipped: {$totalResults['skipped']}\n";
                $outputBuffer .= "Total: {$totalResults['total']}\n";

                if ($dryRun) {
                    $outputBuffer .= "\nThis was a DRY RUN - no changes were made.\n";
                    if (!empty($totalResults['updateRecords'])) {
                        $outputBuffer .= "\nRegions that would be updated:\n";
                        foreach ($totalResults['updateRecords'] as $record) {
                            $outputBuffer .= "- {$record['code']}: {$record['existing']}";
                            if (!empty($record['updateMainName'])) {
                                $outputBuffer .= " → {$record['name']}";
                            }
                            if (!empty($record['locales'])) {
                                $localeUpdates = [];
                                foreach ($record['locales'] as $locale => $name) {
                                    $localeUpdates[] = "$locale: $name";
                                }
                                $outputBuffer .= ' (' . implode(', ', $localeUpdates) . ')';
                            }
                            $outputBuffer .= "\n";
                        }
                    }
                }
            }

            if ($totalResults['success']) {
                $response = [
                    'success' => true,
                    'output' => $outputBuffer,
                ];
            } else {
                // For mahoFetch compatibility, use 'error' field which will cause mahoFetch to throw
                $response = [
                    'error' => $totalResults['error'] ?? 'Unknown error',
                ];
            }

            $this->getResponse()->setHeader('Content-type', 'application/json');
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
        } catch (Exception $e) {
            Mage::logException($e);
            $this->getResponse()->setHeader('Content-type', 'application/json');
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode([
                'error' => $e->getMessage(),
            ]));
        }
    }
}
