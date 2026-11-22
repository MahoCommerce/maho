<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\Helpers\AdminRoleFixture;

uses(Tests\MahoBackendTestCase::class);

/**
 * The Profiles (GUI) permission saves and runs only the actions XML that the profile wizard makes from its fields.
 */

/**
 * Return a GUI controller that records the calls to _initProfile(), _redirect() and renderLayout().
 * With a $profile, _initProfile() registers it as the current profile.
 *
 * @param array<string, mixed> $params
 * @param array<string, mixed>|null $post
 */
function dataflowGuiController(string $action, array $params = [], ?array $post = null, ?Mage_Dataflow_Model_Profile $profile = null): Mage_Adminhtml_System_Convert_GuiController
{
    $request = $post === null
        ? new Mage_Core_Controller_Request_Http()
        : new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/index.php/admin/system_convert_gui/' . $action, 'POST', $post));
    $request->setActionName($action)->setParams($params);

    return new class ($request, new Mage_Core_Controller_Response_Http(), $profile) extends Mage_Adminhtml_System_Convert_GuiController {
        /** @var list<array{0: mixed, 1: array<string, mixed>}> */
        public array $initProfileCalls = [];

        /** @var list<string> */
        public array $redirects = [];

        public bool $rendered = false;

        public function __construct(
            Mage_Core_Controller_Request_Http $request,
            Mage_Core_Controller_Response_Http $response,
            private readonly ?Mage_Dataflow_Model_Profile $profile,
        ) {
            parent::__construct($request, $response);
        }

        #[\Override]
        protected function _initProfile(string $idFieldName = 'id'): static|false
        {
            $this->initProfileCalls[] = [$this->getRequest()->getParam($idFieldName), $this->getRequest()->getPost()];
            if ($this->profile === null) {
                return false;
            }
            Mage::register('current_convert_profile', $this->profile, true);
            return $this;
        }

        #[\Override]
        protected function _redirect($path, $arguments = [])
        {
            $this->redirects[] = $path;
            return $this;
        }

        #[\Override]
        public function loadLayout($ids = null, $generateBlocks = true, $generateXml = true)
        {
            return $this;
        }

        #[\Override]
        public function renderLayout($output = '')
        {
            $this->rendered = true;
            return $this;
        }
    };
}

/**
 * The wizard fields of the Export All Products profile of the Dataflow data install.
 *
 * @return array<string, mixed>
 */
function dataflowGuiExportData(): array
{
    $noFilter = ['from' => '', 'to' => ''];
    return [
        'file' => ['type' => 'file', 'filename' => 'export_all_products.csv', 'path' => 'var/export'],
        'parse' => ['type' => 'csv', 'delimiter' => ',', 'enclose' => '"', 'fieldnames' => 'true'],
        'map' => ['only_specified' => '', 'product' => ['db' => [], 'file' => []]],
        'product' => ['filter' => ['name' => '', 'sku' => '', 'type' => '0', 'price' => $noFilter, 'qty' => $noFilter]],
    ];
}

/**
 * @param array<string, mixed>|string $guiData
 */
function dataflowGuiProfile(array|string $guiData): Mage_Dataflow_Model_Profile
{
    return Mage::getModel('dataflow/profile')->setData([
        'profile_id' => 1,
        'entity_type' => 'product',
        'direction' => 'export',
        'data_transfer' => 'file',
        'store_id' => 0,
        'gui_data' => $guiData,
        'actions_xml' => '<action type="tampered"/>',
    ]);
}

beforeEach(function (): void {
    $this->post = $_POST;
});

afterEach(function (): void {
    $_POST = $this->post;
    Mage::unregister('current_convert_profile');
    Mage::getSingleton('adminhtml/session')->getMessages(true);
    if (isset($this->user)) {
        AdminRoleFixture::delete($this->user);
    }
});

it('saves the profile that the check read, without a posted actions_xml', function (): void {
    $controller = dataflowGuiController('save', ['profile_id' => '1'], [
        'profile_id' => '2',
        'name' => 'Export',
        'entity_type' => 'product',
        'gui_data' => ['file' => ['type' => 'file']],
        'actions_xml' => '<action type="catalog/product" method="load"/>',
    ]);

    $controller->saveAction();

    expect($controller->initProfileCalls)->toHaveCount(1)
        ->and($controller->initProfileCalls[0][0])->toBe(1)
        ->and(array_keys($controller->initProfileCalls[0][1]))->toBe(['name', 'entity_type', 'gui_data']);
});

it('refuses to save a post without the wizard fields', function (array $post): void {
    $controller = dataflowGuiController('save', [], $post);

    $controller->saveAction();

    expect($controller->initProfileCalls)->toBe([])
        ->and($controller->redirects)->toBe(['*/*']);
})->with([
    'no entity type' => [['name' => 'Export', 'entity_type' => '', 'gui_data' => [], 'actions_xml' => '<action/>']],
    'unknown entity type' => [['name' => 'Export', 'entity_type' => 'order', 'gui_data' => [], 'actions_xml' => '<action/>']],
    'no wizard fields' => [['name' => 'Export', 'entity_type' => 'product', 'gui_data' => 'x', 'actions_xml' => '<action/>']],
]);

it('runs the actions XML of the wizard fields without the Advanced Profiles permission', function (): void {
    $this->user = AdminRoleFixture::login(['admin/system/convert/gui']);
    $profile = dataflowGuiProfile(dataflowGuiExportData());
    $controller = dataflowGuiController('run', ['id' => '1'], null, $profile);

    $controller->runAction();

    expect($controller->rendered)->toBeTrue()
        ->and($profile->getActionsXml())->toContain('<action type="catalog/convert_adapter_product" method="load">')
        ->and($profile->getActionsXml())->not->toContain('tampered');
});

it('runs the stored actions XML with the Advanced Profiles permission', function (): void {
    $this->user = AdminRoleFixture::login(['admin/system/convert/gui', 'admin/system/convert/profiles']);
    $profile = dataflowGuiProfile(dataflowGuiExportData());
    $controller = dataflowGuiController('run', ['id' => '1'], null, $profile);

    $controller->runAction();

    expect($controller->rendered)->toBeTrue()
        ->and($profile->getActionsXml())->toBe('<action type="tampered"/>');
});

it('refuses to run a profile without the wizard fields without the Advanced Profiles permission', function (): void {
    $this->user = AdminRoleFixture::login(['admin/system/convert/gui']);
    $profile = dataflowGuiProfile('');
    $controller = dataflowGuiController('run', ['id' => '1'], null, $profile);

    $controller->runAction();

    expect($controller->rendered)->toBeFalse()
        ->and($controller->redirects)->toBe(['*/*/edit'])
        ->and($profile->getActionsXml())->toBe('<action type="tampered"/>');
});
