<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_ApiPlatform_Block_Adminhtml_Apiplatform_Role_Edit_Tab_Permissions extends Mage_Adminhtml_Block_Widget implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('api-platform/role/permissions.phtml');
    }

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

    public function getEverythingAllowed(): bool
    {
        $permissions = Mage::registry('api_role_permissions') ?: [];
        return in_array('all', $permissions, true);
    }

    /**
     * Build tree JSON for MahoTree from ApiPermissionRegistry
     *
     * Structure: group → resource → operations (leaf nodes with permission IDs)
     */
    public function getResTreeJson(): string
    {
        $resourcesByGroup = Mage::registry('api_resources') ?: [];
        $currentPermissions = Mage::registry('api_role_permissions') ?: [];

        $tree = [];

        foreach ($resourcesByGroup as $groupName => $resources) {
            $groupNode = [
                'text' => $this->__('%s Resources', $groupName),
                'id' => 'group_' . strtolower(str_replace(' ', '_', $groupName)),
                'children' => [],
            ];

            foreach ($resources as $resourceId => $config) {
                $resourceNode = [
                    'text' => $config['label'],
                    'id' => 'resource_' . $resourceId,
                    'children' => [],
                ];

                foreach ($config['operations'] as $operation => $operationLabel) {
                    $permValue = $resourceId . '/' . $operation;
                    $operationNode = [
                        'text' => $operationLabel,
                        'id' => $permValue,
                    ];

                    if (in_array($permValue, $currentPermissions, true)) {
                        $operationNode['checked'] = true;
                    }

                    $resourceNode['children'][] = $operationNode;
                }

                $groupNode['children'][] = $resourceNode;
            }

            $tree[] = $groupNode;
        }

        return Mage::helper('core')->jsonEncode($tree);
    }
}
