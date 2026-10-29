<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

/**
 * The role editor allows each ticked resource and all its parents, so a role with one leaf also has
 * the parent of its sibling leaves. Each admin action must check its own leaf, not only the parent.
 */

/**
 * Log in an admin user whose role allows the given resources, as the role editor saves it.
 *
 * @param list<string> $resources
 */
function actionAclLogin(array $resources): void
{
    $acl = Mage::getModel('admin/acl');
    Mage::getSingleton('admin/config')->loadAclResources($acl);

    $allowed = [];
    foreach ($resources as $resource) {
        $path = 'admin';
        $allowed[$path] = true;
        foreach (explode('/', $resource) as $part) {
            $path .= '/' . $part;
            if (!$acl->hasResource($path)) {
                throw new RuntimeException("Unknown ACL resource $path");
            }
            $allowed[$path] = true;
        }
    }

    $rules = [];
    foreach ($acl->getResources() as $resourceId) {
        $rules[] = [
            'role_type' => 'G',
            'role_id' => 900001,
            'resource_id' => $resourceId,
            'privileges' => '',
            'assert_id' => 0,
            'permission' => isset($allowed[$resourceId]) ? 'allow' : 'deny',
        ];
    }

    $resource = Mage::getResourceModel('admin/acl');
    $resource->loadRoles($acl, [
        ['role_id' => 900001, 'parent_id' => 0, 'role_type' => 'G', 'user_id' => 0],
        ['role_id' => 900002, 'parent_id' => 900001, 'role_type' => 'U', 'user_id' => 900003],
    ]);
    $resource->loadRules($acl, $rules);

    $session = Mage::getSingleton('admin/session');
    $session->setUser(Mage::getModel('admin/user')->setUserId(900003));
    $session->setAcl($acl);
}

function actionAclRequest(string $action, array $params = [], ?array $post = null): Mage_Core_Controller_Request_Http
{
    $request = $post === null
        ? new Mage_Core_Controller_Request_Http()
        : new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/index.php/admin/acl/test', 'POST', $post));

    return $request->setActionName($action)->setParams($params);
}

function actionAclIsAllowed(Mage_Adminhtml_Controller_Action $controller): bool
{
    return (bool) (fn() => $this->_isAllowed())->call($controller);
}

afterEach(function (): void {
    $session = Mage::getSingleton('admin/session');
    $session->setUser(null);
    $session->setAcl(null);
});

it('checks the leaf of each action', function (string $class, string $action, array $params, array $granted, bool $expected): void {
    actionAclLogin($granted);
    $controller = new $class(actionAclRequest($action, $params), new Mage_Core_Controller_Response_Http());

    expect(actionAclIsAllowed($controller))->toBe($expected);
})->with(function (): Generator {
    $order = Mage_Adminhtml_Sales_OrderController::class;
    $view = ['sales/order/actions/view'];

    foreach (['massCancel' => 'cancel', 'massHold' => 'hold', 'massUnhold' => 'unhold'] as $action => $leaf) {
        yield "order $action denied without $leaf" => [$order, $action, [], $view, false];
        yield "order $action allowed with $leaf" => [$order, $action, [], ["sales/order/actions/$leaf"], true];
    }
    yield 'order voidPayment denied without invoice' => [$order, 'voidPayment', [], $view, false];
    yield 'order voidPayment allowed with invoice' => [$order, 'voidPayment', [], ['sales/order/actions/invoice'], true];
    foreach (['address', 'addressSave', 'guestOrderEmailChange'] as $action) {
        yield "order $action denied without edit" => [$order, $action, [], $view, false];
        yield "order $action allowed with edit" => [$order, $action, [], ['sales/order/actions/edit'], true];
    }
    yield 'order pdfcreditmemos denied without credit memos' => [$order, 'pdfcreditmemos', [], $view, false];
    yield 'order pdfcreditmemos allowed with order credit memos' => [$order, 'pdfcreditmemos', [], ['sales/order/actions/creditmemo'], true];
    yield 'order pdfcreditmemos allowed with credit memo grid' => [$order, 'pdfcreditmemos', [], ['sales/order', 'sales/creditmemo'], true];
    yield 'order pdfdocs allowed to order viewers' => [$order, 'pdfdocs', [], $view, true];
    yield 'order grid allowed to order viewers' => [$order, 'index', [], $view, true];
    yield 'order export allowed to order viewers' => [$order, 'exportCsv', [], $view, true];
    yield 'order cancel denied without cancel' => [$order, 'cancel', [], $view, false];

    $edit = Mage_Adminhtml_Sales_Order_EditController::class;
    foreach (['start', 'index', 'loadBlock', 'save', 'cancel'] as $action) {
        yield "order edit $action denied to create" => [$edit, $action, [], ['sales/order/actions/create'], false];
        yield "order edit $action allowed with edit" => [$edit, $action, [], ['sales/order/actions/edit'], true];
    }

    $shopcart = Mage_Adminhtml_Report_ShopcartController::class;
    yield 'abandoned cart export denied to products in cart' => [$shopcart, 'exportAbandonedCsv', [], ['report/shopcart/product'], false];
    yield 'abandoned cart export allowed with abandoned' => [$shopcart, 'exportAbandonedExcel', [], ['report/shopcart/abandoned'], true];
    yield 'customer cart export denied to products in cart' => [$shopcart, 'exportCustomerCsv', [], ['report/shopcart/product'], false];
    yield 'products in cart export allowed with product' => [$shopcart, 'exportProductCsv', [], ['report/shopcart/product'], true];

    $tagReport = Mage_Adminhtml_Report_TagController::class;
    yield 'tag customer detail denied to popular' => [$tagReport, 'customerDetail', [], ['report/tags/popular'], false];
    yield 'tag customer detail export denied to popular' => [$tagReport, 'exportCustomerDetailCsv', [], ['report/tags/popular'], false];
    yield 'tag customer detail allowed with customer' => [$tagReport, 'customerDetail', [], ['report/tags/customer'], true];
    yield 'tag detail allowed with popular' => [$tagReport, 'tagDetail', [], ['report/tags/popular'], true];
    yield 'tag product detail denied to popular' => [$tagReport, 'productDetail', [], ['report/tags/popular'], false];
    yield 'tag popular export denied to customer' => [$tagReport, 'exportPopularExcel', [], ['report/tags/customer'], false];

    $productReport = Mage_Adminhtml_Report_ProductController::class;
    yield 'downloads report denied to low stock' => [$productReport, 'downloads', [], ['report/products/lowstock'], false];
    yield 'downloads export denied to low stock' => [$productReport, 'exportDownloadsCsv', [], ['report/products/lowstock'], false];
    yield 'downloads report allowed with downloads' => [$productReport, 'downloads', [], ['report/products/downloads'], true];
    yield 'downloads export allowed with downloads' => [$productReport, 'exportDownloadsExcel', [], ['report/products/downloads'], true];
    yield 'viewed export denied to sold' => [$productReport, 'exportViewedCsv', [], ['report/products/sold'], false];
    yield 'sold export allowed with sold' => [$productReport, 'exportSoldExcel', [], ['report/products/sold'], true];

    $reviewReport = Mage_Adminhtml_Report_ReviewController::class;
    yield 'review product detail denied to customer reviews' => [$reviewReport, 'productDetail', [], ['report/review/customer'], false];
    yield 'review product detail export allowed with product reviews' => [$reviewReport, 'exportProductDetailCsv', [], ['report/review/product'], true];
    yield 'review customer export denied to product reviews' => [$reviewReport, 'exportCustomerExcel', [], ['report/review/product'], false];

    $salesReport = Mage_Adminhtml_Report_SalesController::class;
    yield 'bestsellers export denied to sales report' => [$salesReport, 'exportBestsellersCsv', [], ['report/salesroot/sales'], false];
    yield 'bestsellers export allowed with bestsellers' => [$salesReport, 'exportBestsellersCsv', [], ['report/products/bestsellers'], true];
    yield 'tax report export denied to sales report' => [$salesReport, 'exportTaxCsv', [], ['report/salesroot/sales'], false];
    yield 'coupons report export denied to sales report' => [$salesReport, 'exportCouponsExcel', [], ['report/salesroot/sales'], false];
    yield 'sales report export allowed with sales' => [$salesReport, 'exportSalesExcel', [], ['report/salesroot/sales'], true];

    $customerReport = Mage_Adminhtml_Report_CustomerController::class;
    yield 'customer orders export denied to new accounts' => [$customerReport, 'exportOrdersCsv', [], ['report/customers/accounts'], false];
    yield 'customer orders export allowed with orders' => [$customerReport, 'exportOrdersCsv', [], ['report/customers/orders'], true];
    yield 'customer totals export denied to new accounts' => [$customerReport, 'exportTotalsExcel', [], ['report/customers/accounts'], false];

    $report = Mage_Adminhtml_ReportController::class;
    yield 'search terms export denied to other reports' => [$report, 'exportSearchCsv', [], ['report/customers/accounts'], false];
    yield 'search terms export allowed with search' => [$report, 'exportSearchExcel', [], ['report/search'], true];

    $restRoles = Mage_Api2_Adminhtml_Api2_RoleController::class;
    yield 'rest role new denied to delete' => [$restRoles, 'new', [], ['system/api/rest_roles/delete'], false];
    yield 'rest role new allowed with add' => [$restRoles, 'new', [], ['system/api/rest_roles/add'], true];
    yield 'rest role create denied to edit' => [$restRoles, 'save', [], ['system/api/rest_roles/edit'], false];
    yield 'rest role create allowed with add' => [$restRoles, 'save', [], ['system/api/rest_roles/add'], true];
    yield 'rest role update denied to add' => [$restRoles, 'save', ['id' => '3'], ['system/api/rest_roles/add'], false];
    yield 'rest role update allowed with edit' => [$restRoles, 'save', ['id' => '3'], ['system/api/rest_roles/edit'], true];
    yield 'rest role delete denied to add' => [$restRoles, 'delete', ['id' => '3'], ['system/api/rest_roles/add'], false];
    yield 'rest role delete allowed with delete' => [$restRoles, 'delete', ['id' => '3'], ['system/api/rest_roles/delete'], true];
    yield 'rest role edit page allowed to any rest role leaf' => [$restRoles, 'edit', ['id' => '3'], ['system/api/rest_roles/add'], true];

    $restAttributes = Mage_Api2_Adminhtml_Api2_AttributeController::class;
    yield 'rest attributes denied to rest roles' => [$restAttributes, 'index', [], ['system/api/rest_roles/add'], false];
    yield 'rest attributes save denied to rest roles' => [$restAttributes, 'save', [], ['system/api/rest_roles/add'], false];
    yield 'rest attributes save denied without edit' => [$restAttributes, 'save', [], ['system/api/rest_attributes'], false];
    yield 'rest attributes save allowed with edit' => [$restAttributes, 'save', [], ['system/api/rest_attributes/edit'], true];
    yield 'rest attributes page allowed with attributes' => [$restAttributes, 'edit', [], ['system/api/rest_attributes'], true];

    $config = Mage_Adminhtml_System_ConfigController::class;
    yield 'table rates export denied to design section' => [$config, 'exportTablerates', [], ['system/config/design'], false];
    yield 'table rates export allowed with shipping methods' => [$config, 'exportTablerates', [], ['system/config/carriers'], true];
    yield 'config edit allowed with design section' => [$config, 'edit', [], ['system/config/design'], true];

    $testEmail = Mage_Adminhtml_System_Config_TestEmailController::class;
    yield 'test email denied to design section' => [$testEmail, 'send', [], ['system/config/design'], false];
    yield 'test email allowed with system section' => [$testEmail, 'send', [], ['system/config/system'], true];

    $ai = Maho_Ai_Adminhtml_AiController::class;
    yield 'ai model fetch denied to design section' => [$ai, 'fetchModels', [], ['system/config/design'], false];
    yield 'ai model fetch allowed with ai section' => [$ai, 'fetchModels', [], ['system/config/ai'], true];

    $taxRate = Mage_Adminhtml_Tax_RateController::class;
    yield 'tax rate import denied without import export' => [$taxRate, 'importPost', [], ['sales/tax/rates'], false];
    yield 'tax rate export denied without import export' => [$taxRate, 'exportPost', [], ['sales/tax/rates'], false];
    yield 'tax rate import allowed with import export' => [$taxRate, 'importPost', [], ['sales/tax/import_export'], true];
    yield 'tax rate grid export allowed with rates' => [$taxRate, 'exportCsv', [], ['sales/tax/rates'], true];
});

it('checks Create, or Reorder during a reorder, for the steps of order creation', function (string $action, bool $reordering, array $granted, bool $expected): void {
    actionAclLogin($granted);
    Mage::getSingleton('adminhtml/session_quote')->setReordered($reordering ? 12 : null);
    $controller = new Mage_Adminhtml_Sales_Order_CreateController(actionAclRequest($action), new Mage_Core_Controller_Response_Http());

    expect(actionAclIsAllowed($controller))->toBe($expected);
})->with(function (): Generator {
    foreach (['loadBlock', 'processData', 'addConfigured', 'configureProductToAdd', 'configureQuoteItems'] as $action) {
        yield "$action denied to order viewer" => [$action, false, ['sales/order/actions/view'], false];
        yield "$action allowed with create" => [$action, false, ['sales/order/actions/create'], true];
        yield "$action allowed with reorder during a reorder" => [$action, true, ['sales/order/actions/reorder'], true];
        yield "$action denied with reorder outside a reorder" => [$action, false, ['sales/order/actions/reorder'], false];
    }
    yield 'start denied to order viewer' => ['start', false, ['sales/order/actions/view'], false];
    yield 'start allowed with create' => ['start', false, ['sales/order/actions/create'], true];
    yield 'index allowed with create' => ['index', false, ['sales/order/actions/create'], true];
    yield 'save during a reorder denied to create' => ['save', true, ['sales/order/actions/create'], false];
});

it('allows Pending Tags to act only on tags that are still pending', function (string $action, array $params, array $statuses, array $granted, bool $expected): void {
    actionAclLogin($granted);
    $controller = new class (actionAclRequest($action, $params), new Mage_Core_Controller_Response_Http(), $statuses) extends Mage_Adminhtml_TagController {
        /**
         * @param array<int, int> $statuses
         */
        public function __construct(
            Mage_Core_Controller_Request_Http $request,
            Mage_Core_Controller_Response_Http $response,
            private readonly array $statuses,
        ) {
            parent::__construct($request, $response);
        }

        #[\Override]
        protected function _getTagStatuses(array $tagIds): array
        {
            return array_intersect_key($this->statuses, array_flip($tagIds));
        }
    };

    expect(actionAclIsAllowed($controller))->toBe($expected);
})->with(function (): Generator {
    $pendingOnly = ['catalog/tag/pending'];
    $all = ['catalog/tag/all'];
    $pending = Mage_Tag_Model_Tag::STATUS_PENDING;
    $approved = Mage_Tag_Model_Tag::STATUS_APPROVED;

    yield 'all tags grid denied to pending' => ['index', [], [], $pendingOnly, false];
    yield 'all tags ajax grid denied to pending' => ['ajaxGrid', [], [], $pendingOnly, false];
    yield 'all tags grid allowed with all' => ['index', [], [], $all, true];
    yield 'pending grid denied to all' => ['pending', [], [], $all, false];
    yield 'pending ajax grid allowed with pending' => ['ajaxPendingGrid', [], [], $pendingOnly, true];
    yield 'new tag denied to pending' => ['new', [], [], $pendingOnly, false];
    yield 'edit approved tag denied to pending' => ['edit', ['tag_id' => '5'], [5 => $approved], $pendingOnly, false];
    yield 'edit pending tag allowed with pending' => ['edit', ['tag_id' => '5'], [5 => $pending], $pendingOnly, true];
    yield 'save pending tag allowed with pending' => ['save', ['tag_id' => '5'], [5 => $pending], $pendingOnly, true];
    yield 'delete approved tag denied to pending' => ['delete', ['tag_id' => '5'], [5 => $approved], $pendingOnly, false];
    yield 'delete missing tag denied to pending' => ['delete', ['tag_id' => '5'], [], $pendingOnly, false];
    yield 'mass delete with an approved tag denied to pending' => ['massDelete', ['tag' => ['5', '6']], [5 => $pending, 6 => $approved], $pendingOnly, false];
    yield 'mass delete with an invalid id denied to pending' => ['massDelete', ['tag' => ['5', '6abc']], [5 => $pending, 6 => $approved], $pendingOnly, false];
    yield 'mass status of pending tags allowed with pending' => ['massStatus', ['tag' => ['5', '6']], [5 => $pending, 6 => $pending], $pendingOnly, true];
    yield 'delete approved tag allowed with all' => ['delete', ['tag_id' => '5'], [5 => $approved], $all, true];
});

it('checks the posted type and the saved type of a tax class', function (array $post, ?string $savedType, array $granted, bool $expected): void {
    actionAclLogin($granted);
    $controller = new class (actionAclRequest('save', [], $post), new Mage_Core_Controller_Response_Http(), $savedType) extends Mage_Adminhtml_Tax_ClassController {
        public function __construct(
            Mage_Core_Controller_Request_Http $request,
            Mage_Core_Controller_Response_Http $response,
            private readonly ?string $savedType,
        ) {
            parent::__construct($request, $response);
        }

        #[\Override]
        protected function _getClassType(int $classId): ?string
        {
            return $this->savedType;
        }
    };

    expect(actionAclIsAllowed($controller))->toBe($expected);
})->with(function (): Generator {
    $customer = ['sales/tax/classes_customer'];
    $product = ['sales/tax/classes_product'];

    yield 'new product class denied to customer classes' => [['class_type' => 'PRODUCT'], null, $customer, false];
    yield 'new customer class allowed with customer classes' => [['class_type' => 'CUSTOMER'], null, $customer, true];
    yield 'new product class allowed with product classes' => [['class_type' => 'PRODUCT'], null, $product, true];
    yield 'product class changed to customer denied to customer classes' => [['class_type' => 'CUSTOMER', 'class_id' => '2'], 'PRODUCT', $customer, false];
    yield 'product class renamed denied to customer classes' => [['class_name' => 'X', 'class_id' => '2'], 'PRODUCT', $customer, false];
    yield 'missing class denied to customer classes' => [['class_type' => 'CUSTOMER', 'class_id' => '99'], null, $customer, false];
    yield 'customer class edited with customer classes' => [['class_type' => 'CUSTOMER', 'class_id' => '3'], 'CUSTOMER', $customer, true];
    yield 'class type changed allowed with both' => [['class_type' => 'PRODUCT', 'class_id' => '3'], 'CUSTOMER', [...$customer, ...$product], true];
    yield 'post without type or id denied' => [['class_name' => 'X'], null, [...$customer, ...$product], false];
});

it('hides the Edit controls of the order view without the Edit permission', function (): void {
    $block = Mage::app()->getLayout()->createBlock('adminhtml/sales_order_view_info');

    actionAclLogin(['sales/order/actions/view']);
    expect($block->canEditOrder())->toBeFalse()
        ->and($block->getAddressEditLink(Mage::getModel('sales/order_address')->setId(5)))->toBe('');

    actionAclLogin(['sales/order/actions/edit']);
    expect($block->canEditOrder())->toBeTrue();
});

it('shows the REST role buttons only with their permission', function (): void {
    actionAclLogin(['system/api/rest_roles/add']);
    $buttons = Mage::app()->getLayout()->createBlock('api2/adminhtml_roles_buttons');
    expect($buttons->getSaveButtonHtml())->not->toBe('');

    $buttons->setRole(Mage::getModel('api2/acl_global_role')->setId(900));
    expect($buttons->getSaveButtonHtml())->toBe('')
        ->and($buttons->getDeleteButtonHtml())->toBe('');

    actionAclLogin(['system/api/rest_roles/edit']);
    expect($buttons->getSaveButtonHtml())->not->toBe('');
});

it('shows the Add button of the REST roles grid only with the Add permission', function (array $granted, bool $expected): void {
    actionAclLogin($granted);
    $container = new Mage_Api2_Block_Adminhtml_Roles();
    $buttons = (fn(): array => $this->_buttons)->call($container);

    expect(isset($buttons[0]['add']))->toBe($expected);
})->with([
    'denied to edit' => [['system/api/rest_roles/edit'], false],
    'allowed with add' => [['system/api/rest_roles/add'], true],
]);

it('shows the Save button of the REST attributes only with the Edit permission', function (): void {
    actionAclLogin(['system/api/rest_attributes']);
    $buttons = Mage::app()->getLayout()->createBlock('api2/adminhtml_attribute_buttons');
    expect($buttons->getSaveButtonHtml())->toBe('');

    actionAclLogin(['system/api/rest_attributes/edit']);
    expect($buttons->getSaveButtonHtml())->not->toBe('');
});
