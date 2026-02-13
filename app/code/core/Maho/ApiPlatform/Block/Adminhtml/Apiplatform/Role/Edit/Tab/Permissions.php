<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

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
        $resourcesByGroup = Mage::registry('api_resources') ?: [];
        $currentPermissions = Mage::registry('api_role_permissions') ?: [];
        $hasAll = in_array('all', $currentPermissions, true);

        $form = new Maho\Data\Form();
        $form->setHtmlIdPrefix('perm_');

        // Full access checkbox in its own fieldset
        $mainFieldset = $form->addFieldset('permissions_main', [
            'legend' => $this->__('Resource Access'),
        ]);

        $mainFieldset->addField('perm_all', 'checkbox', [
            'name'    => 'permissions[]',
            'label'   => $this->__('Full Access (All Resources)'),
            'value'   => 'all',
            'checked' => $hasAll,
            'onclick' => 'toggleAllPermissions(this)',
        ]);

        // Per-group fieldsets with checkboxes per operation
        foreach ($resourcesByGroup as $groupName => $resources) {
            $groupKey = strtolower(str_replace(' ', '_', $groupName));
            $fieldset = $form->addFieldset('permissions_' . $groupKey, [
                'legend' => $this->__('%s Resources', $groupName),
            ]);

            foreach ($resources as $resourceId => $config) {
                $label = $config['label'];
                $operations = $config['operations'];

                foreach ($operations as $operation => $operationLabel) {
                    $permValue = $resourceId . '/' . $operation;
                    $isChecked = $hasAll || in_array($permValue, $currentPermissions, true);
                    $fieldId = str_replace(['/', '-'], '_', $resourceId) . '_' . $operation;

                    $fieldset->addField('perm_' . $fieldId, 'checkbox', [
                        'name'    => 'permissions[]',
                        'label'   => $this->__('%s - %s', $label, $operationLabel),
                        'value'   => $permValue,
                        'checked' => $isChecked,
                        'class'   => 'resource-permission',
                    ]);
                }
            }
        }

        // JavaScript for "All" toggle
        $mainFieldset->addField('permissions_js', 'note', [
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
