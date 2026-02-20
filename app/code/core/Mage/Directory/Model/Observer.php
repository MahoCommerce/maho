<?php

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Directory_Model_Observer
{
    public const CRON_STRING_PATH = 'crontab/jobs/currency_rates_update/schedule/cron_expr';
    public const IMPORT_ENABLE = 'currency/import/enabled';
    public const IMPORT_SERVICE = 'currency/import/service';

    public const XML_PATH_ERROR_TEMPLATE = 'currency/import/error_email_template';
    public const XML_PATH_ERROR_IDENTITY = 'currency/import/error_email_identity';
    public const XML_PATH_ERROR_RECIPIENT = 'currency/import/error_email';

    /**
     * @throws Mage_Core_Exception
     */
    public function scheduledUpdateCurrencyRates()
    {
        $importWarnings = [];
        if (!Mage::getStoreConfig(self::IMPORT_ENABLE) || !Mage::getStoreConfig(self::CRON_STRING_PATH)) {
            return;
        }

        $service = Mage::getStoreConfig(self::IMPORT_SERVICE);
        if (!$service) {
            $importWarnings[] = Mage::helper('directory')->__('FATAL ERROR:') . ' ' . Mage::helper('directory')->__('Invalid Import Service specified.');
        }

        try {
            $importModel = Mage::getModel(Mage::getConfig()->getNode('global/currency/import/services/' . $service . '/model')->asArray());
        } catch (Exception $e) {
            $importWarnings[] = Mage::helper('directory')->__('FATAL ERROR:') . ' ' . Mage::throwException(Mage::helper('directory')->__('Unable to initialize the import model.'));
        }

        if (!isset($importModel)) {
            return;
        }
        assert($importModel instanceof \Mage_Directory_Model_Currency_Import_Abstract);

        $rates = $importModel->fetchRates();
        $errors = $importModel->getMessages();

        if (count($errors)) {
            foreach ($errors as $error) {
                $importWarnings[] = Mage::helper('directory')->__('WARNING:') . ' ' . $error;
            }
        }

        if (!count($importWarnings)) {
            Mage::getModel('directory/currency')->saveRates($rates);
        } else {
            $translate = Mage::getSingleton('core/translate');
            /** @var Mage_Core_Model_Translate $translate */
            $translate->setTranslateInline(false);

            /** @var Mage_Core_Model_Email_Template $mailTemplate */
            $mailTemplate = Mage::getModel('core/email_template');
            $mailTemplate
                ->setDesignConfig([
                    'area' => Mage_Core_Model_App_Area::AREA_FRONTEND,
                ])
                ->sendTransactional(
                    Mage::getStoreConfig(self::XML_PATH_ERROR_TEMPLATE),
                    Mage::getStoreConfig(self::XML_PATH_ERROR_IDENTITY),
                    Mage::getStoreConfig(self::XML_PATH_ERROR_RECIPIENT),
                    null,
                    [
                        'warnings' => implode("\n", $importWarnings),
                    ],
                );

            $translate->setTranslateInline(true);
        }
    }
}
