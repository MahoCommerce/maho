<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Block_Adminhtml_Destination_Edit_Tab_General extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm(): self
    {
        $destination = $this->_getDestination();

        $form = new Maho\Data\Form();
        $form->setHtmlIdPrefix('destination_');

        // Basic Information
        $fieldset = $form->addFieldset('base_fieldset', [
            'legend' => $this->__('Destination Information'),
        ]);

        if ($destination->getId()) {
            $fieldset->addField('destination_id', 'hidden', [
                'name' => 'destination_id',
            ]);
        }

        $fieldset->addField('name', 'text', [
            'name' => 'name',
            'label' => $this->__('Name'),
            'title' => $this->__('Name'),
            'required' => true,
            'note' => $this->__('A friendly name to identify this destination'),
        ]);

        $fieldset->addField('type', 'select', [
            'name' => 'type',
            'label' => $this->__('Type'),
            'title' => $this->__('Type'),
            'required' => true,
            'values' => Maho_FeedManager_Model_Destination::getTypeOptions(),
            'note' => $this->__('The connection type for this destination'),
        ]);

        $fieldset->addField('is_enabled', 'select', [
            'name' => 'is_enabled',
            'label' => $this->__('Enabled'),
            'title' => $this->__('Enabled'),
            'required' => true,
            'values' => [
                ['value' => 1, 'label' => $this->__('Yes')],
                ['value' => 0, 'label' => $this->__('No')],
            ],
        ]);

        // Add config fields for each type
        $this->_addSftpFields($form, $destination);
        $this->_addFtpFields($form, $destination);
        $this->_addGoogleApiFields($form, $destination);
        $this->_addFacebookApiFields($form, $destination);

        $form->setValues($destination->getData());

        // Set config values
        $config = $destination->getConfigArray();
        foreach ($config as $key => $value) {
            $element = $form->getElement("config_{$key}");
            if ($element) {
                $element->setValue($value);
            }
        }

        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * Append type toggle script after HTML output
     */
    #[\Override]
    protected function _afterToHtml($html)
    {
        $destination = $this->_getDestination();
        $html .= $this->_getTypeToggleScript($destination->getType());
        return parent::_afterToHtml($html);
    }

    /**
     * Get JavaScript for showing/hiding config fieldsets based on type
     */
    protected function _getTypeToggleScript(?string $currentType): string
    {
        $types = array_keys(Maho_FeedManager_Model_Destination::getTypeOptions());
        $currentType = $currentType ?: '';

        return <<<SCRIPT
        <script type="text/javascript">
        (function() {
            var types = ['sftp', 'ftp', 'google_api', 'facebook_api'];
            var typeSelect = document.getElementById('destination_type');

            function toggleFieldsets(selectedType) {
                types.forEach(function(type) {
                    var fieldset = document.getElementById('destination_' + type + '_fieldset');
                    if (fieldset) {
                        fieldset.style.display = (type === selectedType) ? '' : 'none';
                    }
                });
            }

            if (typeSelect) {
                // Initial state
                toggleFieldsets('{$currentType}');

                // On change
                typeSelect.addEventListener('change', function() {
                    toggleFieldsets(this.value);
                });
            }
        })();
        </script>
SCRIPT;
    }

    protected function _addSftpFields(Maho\Data\Form $form, Maho_FeedManager_Model_Destination $destination): void
    {
        $fieldset = $form->addFieldset('sftp_fieldset', [
            'legend' => $this->__('SFTP Configuration'),
            'class' => 'destination-config destination-sftp',
        ]);

        $fieldset->addField('config_host', 'text', [
            'name' => 'config[host]',
            'label' => $this->__('Host'),
            'required' => true,
        ]);

        $fieldset->addField('config_port', 'text', [
            'name' => 'config[port]',
            'label' => $this->__('Port'),
            'value' => '22',
        ]);

        $fieldset->addField('config_username', 'text', [
            'name' => 'config[username]',
            'label' => $this->__('Username'),
            'required' => true,
        ]);

        $fieldset->addField('config_auth_type', 'select', [
            'name' => 'config[auth_type]',
            'label' => $this->__('Authentication'),
            'values' => [
                ['value' => 'password', 'label' => $this->__('Password')],
                ['value' => 'key', 'label' => $this->__('Private Key')],
            ],
        ]);

        $fieldset->addField('config_password', 'password', [
            'name' => 'config[password]',
            'label' => $this->__('Password'),
            'class' => 'auth-password',
        ]);

        $fieldset->addField('config_private_key', 'textarea', [
            'name' => 'config[private_key]',
            'label' => $this->__('Private Key'),
            'class' => 'auth-key',
            'note' => $this->__('Paste your private key content'),
        ]);

        $fieldset->addField('config_remote_path', 'text', [
            'name' => 'config[remote_path]',
            'label' => $this->__('Remote Path'),
            'value' => '/',
            'note' => $this->__('Directory on the remote server'),
        ]);
    }

    protected function _addFtpFields(Maho\Data\Form $form, Maho_FeedManager_Model_Destination $destination): void
    {
        $fieldset = $form->addFieldset('ftp_fieldset', [
            'legend' => $this->__('FTP Configuration'),
            'class' => 'destination-config destination-ftp',
        ]);

        $fieldset->addField('config_ftp_host', 'text', [
            'name' => 'config[host]',
            'label' => $this->__('Host'),
            'required' => true,
        ]);

        $fieldset->addField('config_ftp_port', 'text', [
            'name' => 'config[port]',
            'label' => $this->__('Port'),
            'value' => '21',
        ]);

        $fieldset->addField('config_ftp_username', 'text', [
            'name' => 'config[username]',
            'label' => $this->__('Username'),
            'required' => true,
        ]);

        $fieldset->addField('config_ftp_password', 'password', [
            'name' => 'config[password]',
            'label' => $this->__('Password'),
            'required' => true,
        ]);

        $fieldset->addField('config_passive_mode', 'select', [
            'name' => 'config[passive_mode]',
            'label' => $this->__('Passive Mode'),
            'values' => [
                ['value' => '1', 'label' => $this->__('Yes')],
                ['value' => '0', 'label' => $this->__('No')],
            ],
        ]);

        $fieldset->addField('config_ssl', 'select', [
            'name' => 'config[ssl]',
            'label' => $this->__('Use SSL (FTPS)'),
            'values' => [
                ['value' => '0', 'label' => $this->__('No')],
                ['value' => '1', 'label' => $this->__('Yes')],
            ],
        ]);

        $fieldset->addField('config_ftp_remote_path', 'text', [
            'name' => 'config[remote_path]',
            'label' => $this->__('Remote Path'),
            'value' => '/',
        ]);
    }

    protected function _addGoogleApiFields(Maho\Data\Form $form, Maho_FeedManager_Model_Destination $destination): void
    {
        $fieldset = $form->addFieldset('google_api_fieldset', [
            'legend' => $this->__('Google Merchant Centre Configuration'),
            'class' => 'destination-config destination-google_api',
        ]);

        $fieldset->addField('config_merchant_id', 'text', [
            'name' => 'config[merchant_id]',
            'label' => $this->__('Merchant ID'),
            'required' => true,
        ]);

        $fieldset->addField('config_target_country', 'text', [
            'name' => 'config[target_country]',
            'label' => $this->__('Target Country'),
            'value' => 'AU',
            'note' => $this->__('ISO 3166-1 alpha-2 code (e.g., AU, US, GB)'),
        ]);

        $fieldset->addField('config_service_account_json', 'textarea', [
            'name' => 'config[service_account_json]',
            'label' => $this->__('Service Account JSON'),
            'required' => true,
            'note' => $this->__('Paste your Google service account JSON key'),
        ]);
    }

    protected function _addFacebookApiFields(Maho\Data\Form $form, Maho_FeedManager_Model_Destination $destination): void
    {
        $fieldset = $form->addFieldset('facebook_api_fieldset', [
            'legend' => $this->__('Facebook/Meta Catalog Configuration'),
            'class' => 'destination-config destination-facebook_api',
        ]);

        $fieldset->addField('config_business_id', 'text', [
            'name' => 'config[business_id]',
            'label' => $this->__('Business ID'),
            'note' => $this->__('Optional'),
        ]);

        $fieldset->addField('config_catalog_id', 'text', [
            'name' => 'config[catalog_id]',
            'label' => $this->__('Catalog ID'),
            'required' => true,
        ]);

        $fieldset->addField('config_access_token', 'textarea', [
            'name' => 'config[access_token]',
            'label' => $this->__('Access Token'),
            'required' => true,
            'note' => $this->__('Long-lived access token with catalog_management permission'),
        ]);
    }

    protected function _getDestination(): Maho_FeedManager_Model_Destination
    {
        return Mage::registry('current_destination') ?: Mage::getModel('feedmanager/destination');
    }
}
