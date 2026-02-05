<?php

class Maho_ApiPlatform_Block_Adminhtml_Apiplatform_Role_Edit_Tab_Permissions extends Mage_Adminhtml_Block_Widget_Form implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    #[\Override]
    public function getTabLabel(): string
    {
        return $this->__('Permissions');
    }

    #[\Override]
    public function getTabTitle(): string
    {
        return $this->getTabLabel();
    }

    #[\Override]
    public function canShowTab(): bool
    {
        return true;
    }

    #[\Override]
    public function isHidden(): bool
    {
        return false;
    }

    #[\Override]
    protected function _prepareForm(): static
    {
        $resources = Mage::registry('api_resources') ?: [];
        $currentPermissions = Mage::registry('api_role_permissions') ?: [];
        $hasAll = in_array('all', $currentPermissions, true);

        $form = new Varien_Data_Form();
        $form->setHtmlIdPrefix('perm_');

        $fieldset = $form->addFieldset('permissions_fieldset', [
            'legend' => $this->__('Resource Permissions'),
        ]);

        // Full access checkbox
        $fieldset->addField('perm_all', 'checkbox', [
            'name'    => 'permissions[]',
            'label'   => $this->__('Full Access (All Resources)'),
            'value'   => 'all',
            'checked' => $hasAll,
            'onclick' => 'toggleAllPermissions(this)',
        ]);

        // Per-resource read/write checkboxes
        foreach ($resources as $resourceId => $config) {
            // Support both old format (string label) and new format (array with capabilities)
            if (is_string($config)) {
                $label = $config;
                $hasRead = true;
                $hasWrite = true;
            } else {
                $label = $config['label'];
                $hasRead = $config['read'] ?? true;
                $hasWrite = $config['write'] ?? false;
            }

            $readChecked = $hasAll || in_array($resourceId . '/read', $currentPermissions, true);
            $writeChecked = $hasAll || in_array($resourceId . '/write', $currentPermissions, true);

            if ($hasRead) {
                $fieldset->addField('perm_' . $resourceId . '_read', 'checkbox', [
                    'name'    => 'permissions[]',
                    'label'   => $this->__('%s - Read', $label),
                    'value'   => $resourceId . '/read',
                    'checked' => $readChecked,
                    'class'   => 'resource-permission',
                ]);
            }

            if ($hasWrite) {
                $fieldset->addField('perm_' . $resourceId . '_write', 'checkbox', [
                    'name'    => 'permissions[]',
                    'label'   => $this->__('%s - Write', $label),
                    'value'   => $resourceId . '/write',
                    'checked' => $writeChecked,
                    'class'   => 'resource-permission',
                ]);
            }
        }

        // JavaScript for "All" toggle
        $fieldset->addField('permissions_js', 'note', [
            'text' => '<script type="text/javascript">
                function toggleAllPermissions(el) {
                    var checkboxes = document.querySelectorAll(".resource-permission");
                    for (var i = 0; i < checkboxes.length; i++) {
                        checkboxes[i].checked = el.checked;
                        checkboxes[i].disabled = el.checked;
                    }
                }
                var allCheckbox = document.getElementById("perm_perm_all");
                if (allCheckbox && allCheckbox.checked) { toggleAllPermissions(allCheckbox); }
            </script>',
        ]);

        $this->setForm($form);

        return parent::_prepareForm();
    }
}
