<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * API v2 customer segments: fields, conditions, refresh, customers, condition metadata, access,
 * and the same outcome as the admin page, since both call the segment service.
 *
 * @group write
 */

const CSEG_PATH = '/api/rest/v2/customer-segments';

const CSEG_ROOT = 'customersegmentation/segment_condition_combine';

afterAll(function (): void {
    foreach (csegIds() as $segmentId) {
        $segment = Mage::getModel('customersegmentation/segment')->load($segmentId);
        if ($segment->getId()) {
            $segment->delete();
        }
    }
    cleanupTestData();
});

function &csegIds(): array
{
    static $ids = [];
    return $ids;
}

function csegCreate(
    array $fields = [],
    #[\SensitiveParameter]
    ?string $token = null,
): array {
    $response = apiPost(CSEG_PATH, $fields + [
        'name' => 'Pest segment ' . substr(uniqid(), -6),
        'websiteIds' => [1],
    ], $token ?? adminToken());
    if (isset($response['json']['id'])) {
        csegIds()[] = (int) $response['json']['id'];
    }
    return $response;
}

function csegMembers(array $response): array
{
    return $response['json']['member'] ?? $response['json']['hydra:member'] ?? (array_is_list($response['json']) ? $response['json'] : []);
}

function csegLifetimeSalesTree(string $amount): array
{
    return [
        'type' => CSEG_ROOT,
        'aggregator' => 'all',
        'value' => true,
        'conditions' => [[
            'type' => 'customersegmentation/segment_condition_customer_clv',
            'attribute' => 'lifetime_sales',
            'operator' => '>=',
            'value' => $amount,
        ]],
    ];
}

/**
 * Run the save action of the segment admin page with $segmentPost under the "segment" key, as the form posts it.
 */
function csegAdminSave(array $segmentPost, ?int $id = null): void
{
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/admin/customersegmentation_index/save', 'POST', ['segment' => $segmentPost]),
    );
    $request->setRouteName('adminhtml')
        ->setControllerName('customersegmentation_index')
        ->setActionName('save')
        ->setDispatched(true);
    if ($id !== null) {
        $request->setParam('id', $id);
    }
    Mage::app()->setRequest($request);
    new Maho_CustomerSegmentation_Adminhtml_CustomerSegmentation_IndexController($request, new Mage_Core_Controller_Response_Http())
        ->saveAction();
}

describe('Customer segment access', function (): void {

    it('denies every operation without authentication', function (): void {
        expect(apiGet(CSEG_PATH)['status'])->toBe(401);
        expect(apiPost(CSEG_PATH, ['name' => 'x'])['status'])->toBe(401);
        expect(apiGet(CSEG_PATH . '/condition-metadata')['status'])->toBe(401);
    });

    it('denies a token without the permission', function (): void {
        $readToken = serviceToken(['customer-segments/read']);
        expect(apiGet(CSEG_PATH, $readToken)['status'])->toBe(200);
        expect(apiGet(CSEG_PATH . '/condition-metadata', $readToken)['status'])->toBe(200);
        expect(apiPost(CSEG_PATH, ['name' => 'x', 'websiteIds' => [1]], $readToken)['status'])->toBeForbidden();
        expect(apiGet(CSEG_PATH, serviceToken(['customers/read']))['status'])->toBeForbidden();
    });

    it('checks the ACL resource of each action for an admin, as the admin pages do', function (): void {
        $id = (int) csegCreate()['json']['id'];
        $token = adminTokenWithAcl(
            ['admin/customer', 'admin/customer/customersegmentation', 'admin/customer/customersegmentation/manage'],
            'pest_cseg_acl_read',
        );

        expect(apiGet(CSEG_PATH . "/{$id}", $token)['status'])->toBe(200);
        expect(apiGet(CSEG_PATH . "/{$id}/customers", $token)['status'])->toBe(200);
        expect(apiPatch(CSEG_PATH . "/{$id}", ['priority' => 1], $token)['status'])->toBeForbidden();
        expect(apiPost(CSEG_PATH . "/{$id}/refresh", [], $token)['status'])->toBeForbidden();
        expect(apiDelete(CSEG_PATH . "/{$id}", $token)['status'])->toBeForbidden();
        expect(apiGet(CSEG_PATH, adminTokenWithAcl(['admin/customer/manage'], 'pest_cseg_acl_deny'))['status'])->toBeForbidden();
    });

    it('hides the segments of other websites from a store-restricted token', function (): void {
        $foreignWebsiteId = (int) createPriceWebsite('pest_cseg')->getId();
        try {
            $foreign = (int) csegCreate(['websiteIds' => [$foreignWebsiteId]])['json']['id'];
            $own = (int) csegCreate()['json']['id'];
            $token = serviceToken(['customer-segments/read', 'customer-segments/write'], [1]);

            $listed = array_column(csegMembers(apiGet(CSEG_PATH, $token)), 'id');
            expect($listed)->toContain($own)->not->toContain($foreign)
                ->and(apiGet(CSEG_PATH . "/{$own}", $token)['status'])->toBe(200)
                ->and(apiGet(CSEG_PATH . "/{$foreign}", $token)['status'])->toBe(404)
                ->and(apiPost(CSEG_PATH, ['name' => 'x', 'websiteIds' => [$foreignWebsiteId]], $token)['status'])->toBeForbidden();
        } finally {
            deletePriceWebsite('pest_cseg');
        }
    });

    it('refuses a store-restricted token that changes a segment with a website outside its scope', function (): void {
        $foreignWebsiteId = (int) createPriceWebsite('pest_cseg_shared')->getId();
        try {
            $id = (int) csegCreate(['websiteIds' => [1, $foreignWebsiteId]])['json']['id'];
            $token = serviceToken(['customer-segments/read', 'customer-segments/write'], [1]);

            expect(apiPatch(CSEG_PATH . "/{$id}", ['websiteIds' => [1]], $token)['status'])->toBeForbidden()
                ->and(Mage::getModel('customersegmentation/segment')->load($id)->getWebsiteIds())->toBe([1, $foreignWebsiteId]);
        } finally {
            deletePriceWebsite('pest_cseg_shared');
        }
    });
});

describe('Customer segment fields', function (): void {

    it('creates, reads, updates and deletes a segment', function (): void {
        $token = adminToken();
        $create = csegCreate([
            'name' => 'Pest full segment',
            'description' => 'All fields',
            'isActive' => false,
            'customerGroupIds' => [1],
            'refreshMode' => 'manual',
            'priority' => 4,
        ], $token);

        expect($create['status'])->toBe(201);
        $id = (int) $create['json']['id'];
        expect($create['json']['refreshStatus'])->toBe('pending')
            ->and($create['json']['matchedCustomersCount'])->toBe(0);
        $json = apiGet(CSEG_PATH . "/{$id}", $token)['json'];
        expect($json['name'])->toBe('Pest full segment')
            ->and($json['description'])->toBe('All fields')
            ->and($json['isActive'])->toBeFalse()
            ->and($json['websiteIds'])->toBe([1])
            ->and($json['customerGroupIds'])->toBe([1])
            ->and($json['refreshMode'])->toBe('manual')
            ->and($json['priority'])->toBe(4)
            ->and($json['refreshStatus'])->toBe('pending')
            ->and($json['conditions']['type'])->toBe(CSEG_ROOT);

        $patch = apiPatch(CSEG_PATH . "/{$id}", ['isActive' => true, 'customerGroupIds' => [], 'description' => null], $token);
        expect($patch['status'])->toBe(200)
            ->and($patch['json']['isActive'])->toBeTrue()
            ->and($patch['json']['customerGroupIds'])->toBe([])
            ->and($patch['json']['description'] ?? null)->toBeNull()
            ->and($patch['json']['name'])->toBe('Pest full segment')
            ->and($patch['json']['priority'])->toBe(4);

        expect(apiDelete(CSEG_PATH . "/{$id}", $token)['status'])->toBe(204);
        expect(apiGet(CSEG_PATH . "/{$id}", $token)['status'])->toBe(404);
    });

    it('writes each change to the admin activity log', function (): void {
        if (!Mage::helper('adminactivitylog')->isEnabled()) {
            $this->markTestSkipped('The admin activity log is off');
        }
        $token = adminToken();
        $id = (int) csegCreate([], $token)['json']['id'];
        apiPatch(CSEG_PATH . "/{$id}", ['priority' => 2], $token);

        $resource = Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');
        $actions = $read->fetchCol(
            $read->select()
                ->from($resource->getTableName('adminactivitylog/activity'), ['action_type'])
                ->where('entity_type = ?', 'customer_segment')
                ->where('entity_id = ?', $id)
                ->order('activity_id ASC'),
        );

        expect($actions)->toBe(['create', 'update']);
    });

    it('replaces every field with PUT and gives a field that the body leaves out its default value', function (): void {
        $token = adminToken();
        $id = (int) csegCreate([
            'description' => 'Gone after a replace',
            'priority' => 4,
            'customerGroupIds' => [1],
            'conditions' => csegLifetimeSalesTree('500'),
        ], $token)['json']['id'];

        $put = apiPut(CSEG_PATH . "/{$id}", ['name' => 'Pest replaced segment', 'websiteIds' => [1]], $token);

        expect($put['status'])->toBe(200)
            ->and($put['json']['name'])->toBe('Pest replaced segment')
            ->and($put['json']['description'] ?? null)->toBeNull()
            ->and($put['json']['priority'])->toBe(0)
            ->and($put['json']['customerGroupIds'])->toBe([])
            ->and($put['json']['conditions']['conditions'])->toBe([]);
    });

    it('gives the same fields to a POST and to a PUT with the same body', function (): void {
        $token = adminToken();
        $body = ['name' => 'Pest default segment', 'websiteIds' => [1]];
        $id = (int) csegCreate([
            'description' => 'Replaced',
            'isActive' => false,
            'customerGroupIds' => [1],
            'refreshMode' => 'manual',
            'priority' => 4,
            'allowOverlappingSequences' => true,
            'conditions' => csegLifetimeSalesTree('500'),
        ], $token)['json']['id'];

        $post = csegCreate($body, $token);
        $put = apiPut(CSEG_PATH . "/{$id}", $body, $token);

        $fields = static fn(array $json): array => array_diff_key($json, array_flip(['@context', '@id', '@type', 'id', 'matchedCustomersCount', 'refreshStatus', 'lastRefreshAt']));
        expect($post['status'])->toBe(201)
            ->and($put['status'])->toBe(200)
            ->and($fields($put['json']))->toEqual($fields($post['json']));
    });

    it('refuses a PATCH body that is not a JSON merge patch', function (): void {
        $id = (int) csegCreate()['json']['id'];

        expect(apiPatch(CSEG_PATH . "/{$id}", ['priority' => 1], adminToken(), ['Content-Type' => 'application/json'])['status'])->toBe(415);
    });

    it('lists the segments with their trees', function (): void {
        $id = (int) csegCreate(['conditions' => csegLifetimeSalesTree('1')])['json']['id'];
        $list = csegMembers(apiGet(CSEG_PATH . '?itemsPerPage=100', adminToken()));
        $row = array_values(array_filter($list, fn(array $item): bool => $item['id'] === $id))[0] ?? null;

        expect($row)->not->toBeNull()
            ->and($row['conditions']['conditions'][0]['attribute'])->toBe('lifetime_sales');
    });

    it('refuses an unknown or read-only field with a 400 that names each field', function (): void {
        $id = (int) csegCreate()['json']['id'];
        $response = apiPatch(CSEG_PATH . "/{$id}", ['unknown' => true, 'matchedCustomersCount' => 5], adminToken());

        expect($response['status'])->toBe(400)
            ->and(array_column($response['json']['details']['errors'], 'field'))->toBe(['unknown', 'matchedCustomersCount']);
    });

    it('lists every value of a wrong type with a 422 that names each field', function (): void {
        $response = csegCreate(['name' => 5, 'priority' => 'x', 'websiteIds' => '1']);

        expect($response['status'])->toBe(422)
            ->and(array_column($response['json']['details']['errors'], 'field'))
            ->toEqualCanonicalizing(['name', 'priority', 'websiteIds']);
    });

    it('answers a broken business rule of the service with a 422 that names every problem', function (): void {
        $response = csegCreate(['name' => '', 'websiteIds' => [999], 'refreshMode' => 'often']);

        expect($response['status'])->toBe(422)
            ->and($response['json']['message'])->toContain('999', 'auto, manual')
            ->and(array_column($response['json']['details']['errors'], 'field'))->toBe(['name', 'websiteIds', 'refreshMode']);
    });
});

describe('Customer segment conditions', function (): void {

    it('stores a tree and returns it with labels', function (): void {
        $create = csegCreate(['conditions' => csegLifetimeSalesTree('500')]);

        expect($create['status'])->toBe(201);
        $leaf = $create['json']['conditions']['conditions'][0];
        expect($leaf['attribute'])->toBe('lifetime_sales')
            ->and($leaf['operator'])->toBe('>=')
            ->and($leaf['value'])->toBe('500')
            ->and($leaf['label'])->toEndWith('500 ' . Mage::app()->getWebsite(1)->getBaseCurrencyCode());
    });

    it('keeps the stored tree when a PATCH leaves out conditions, and empties it when a PATCH sends null', function (): void {
        $token = adminToken();
        $id = (int) csegCreate(['conditions' => csegLifetimeSalesTree('500')], $token)['json']['id'];

        $kept = apiPatch(CSEG_PATH . "/{$id}", ['priority' => 2], $token);
        expect($kept['status'])->toBe(200)
            ->and($kept['json']['conditions']['conditions'][0]['attribute'])->toBe('lifetime_sales');

        $cleared = apiPatch(CSEG_PATH . "/{$id}", ['conditions' => null], $token);
        expect($cleared['status'])->toBe(200)
            ->and($cleared['json']['conditions']['conditions'])->toBe([]);
    });

    it('refreshes and deletes a segment whose stored tree is deeper than the API accepts', function (): void {
        $tree = ['type' => CSEG_ROOT, 'aggregator' => 'all', 'value' => true, 'conditions' => []];
        for ($level = 0; $level < Mage_Rule_Model_Condition_TreeValidator::MAX_DEPTH; $level++) {
            $tree = ['type' => CSEG_ROOT, 'aggregator' => 'all', 'value' => true, 'conditions' => [$tree]];
        }
        $segment = Mage::getModel('customersegmentation/segment')
            ->setName('Pest deep tree ' . substr(uniqid(), -6))
            ->setWebsiteIds([1])
            ->setIsActive();
        $segment->getConditions()->loadArray($tree);
        $segment->save();
        $id = (int) $segment->getId();
        csegIds()[] = $id;

        $token = adminToken();
        expect(apiPost(CSEG_PATH . "/{$id}/refresh", [], $token)['status'])->toBe(200)
            ->and(apiDelete(CSEG_PATH . "/{$id}", $token)['status'])->toBe(204);
    });

    it('refuses an attribute that the condition type does not have', function (): void {
        $tree = csegLifetimeSalesTree('500');
        $tree['conditions'][0]['attribute'] = 'nope';
        $response = csegCreate(['conditions' => $tree]);

        expect($response['status'])->toBe(422)
            ->and($response['json']['details']['errors'][0]['field'])->toBe('conditions.conditions[0].attribute');
    });

    it('describes the conditions tree and pages the value options of an attribute', function (): void {
        $token = adminToken();
        $metadata = apiGet(CSEG_PATH . '/condition-metadata', $token);
        $types = $metadata['json']['types'] ?? [];

        expect($metadata['status'])->toBe(200)
            ->and($metadata['json']['roots'])->toBe(['conditions' => CSEG_ROOT])
            ->and(array_column($types['customersegmentation/segment_condition_customer_clv']['attributes'], 'code'))->toContain('lifetime_sales')
            ->and(array_column($metadata['json']['scope']['websites'], 'id'))->toContain(1);

        $unchanged = apiGet(CSEG_PATH . '/condition-metadata?knownVersion=' . $metadata['json']['version'], $token);
        expect($unchanged['json']['unchanged'])->toBeTrue()
            ->and($unchanged['json'])->not->toHaveKey('types');

        $withOptions = null;
        foreach ($types as $type => $description) {
            foreach ($description['attributes'] ?? [] as $attribute) {
                if (($attribute['options'] ?? null) !== null) {
                    $withOptions = [$type, $attribute['code']];
                    break 2;
                }
            }
        }
        expect($withOptions)->not->toBeNull();

        $options = apiGet(CSEG_PATH . '/condition-value-options?type=' . urlencode($withOptions[0]) . '&attribute=' . urlencode($withOptions[1]), $token);
        expect($options['status'])->toBe(200)
            ->and($options['json']['items'])->toBeArray()
            ->and($options['json']['totalItems'])->toBeGreaterThanOrEqual(count($options['json']['items']));
        expect(apiGet(CSEG_PATH . '/condition-value-options?type=nope&attribute=x', $token)['status'])->toBe(400);
    });
});

describe('Customer segment refresh', function (): void {

    it('finds the customers of the segment and lists them', function (): void {
        $token = adminToken();
        $id = (int) csegCreate(['conditions' => csegLifetimeSalesTree('0')], $token)['json']['id'];

        $refresh = apiPost(CSEG_PATH . "/{$id}/refresh", [], $token);
        expect($refresh['status'])->toBe(200)
            ->and($refresh['json']['refreshStatus'])->toBe('completed')
            ->and($refresh['json']['lastRefreshAt'])->toBeString();

        $customers = apiGet(CSEG_PATH . "/{$id}/customers?itemsPerPage=100", $token);
        expect($customers['status'])->toBe(200)
            ->and(count(csegMembers($customers)))->toBe(min(100, $refresh['json']['matchedCustomersCount']));
        foreach (csegMembers($customers) as $customer) {
            expect($customer['websiteId'])->toBe(1)
                ->and($customer['email'])->toBeString();
        }
    });

    it('gives an inactive segment no customers', function (): void {
        $token = adminToken();
        $id = (int) csegCreate(['isActive' => false, 'conditions' => csegLifetimeSalesTree('0')], $token)['json']['id'];

        expect(apiPost(CSEG_PATH . "/{$id}/refresh", [], $token)['json']['matchedCustomersCount'])->toBe(0);
    });
});

describe('Customer segment MCP tools', function (): void {

    it('puts every segment tool in the customers section', function (): void {
        $token = adminToken();
        $tools = array_keys(mcpTools($token, mcpSession($token)));

        expect($tools)->toContain(
            'customers_customer_segments_list',
            'customers_customer_segments_get',
            'customers_customer_segments_create',
            'customers_customer_segments_refresh_create',
            'customers_customer_segments_customers_list',
            'customers_customer_segments_condition_metadata_get',
            'customers_customer_segments_condition_value_options_get',
        );
    });

    it('creates a segment with the MCP create tool', function (): void {
        $token = adminToken();
        $name = 'Pest MCP ' . substr(uniqid(), -6);
        $created = mcpTool('customers_customer_segments_create', ['name' => $name, 'websiteIds' => [1]], $token, mcpSession($token));
        $payload = json_decode($created['json']['result']['content'][0]['text'] ?? '{}', true);
        if (isset($payload['id'])) {
            csegIds()[] = (int) $payload['id'];
        }

        expect($payload['name'] ?? null)->toBe($name)
            ->and($payload['id'] ?? null)->toBeInt()
            ->and($payload['refreshStatus'] ?? null)->toBe('pending');
    });
});

describe('Customer segment admin page and API parity', function (): void {

    beforeEach(function (): void {
        Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    });

    it('saves the same segment from the admin form and from the API', function (): void {
        $name = 'Pest parity ' . substr(uniqid(), -6);
        csegAdminSave([
            'name' => $name . ' admin',
            'is_active' => '1',
            'refresh_mode' => 'manual',
            'website_ids' => ['1'],
            'customer_group_ids' => ['', '1'],
        ]);
        $admin = Mage::getModel('customersegmentation/segment')->load($name . ' admin', 'name');
        expect($admin->getId())->not->toBeNull();
        csegIds()[] = (int) $admin->getId();

        $api = csegCreate([
            'name' => $name . ' api',
            'isActive' => true,
            'refreshMode' => 'manual',
            'websiteIds' => [1],
            'customerGroupIds' => [1],
        ]);
        $apiSegment = Mage::getModel('customersegmentation/segment')->load($api['json']['id']);

        foreach ([$admin, $apiSegment] as $segment) {
            expect($segment->getIsActive())->toBeTrue()
                ->and($segment->getRefreshMode())->toBe('manual')
                ->and($segment->getWebsiteIds())->toBe([1])
                ->and($segment->getCustomerGroupIds())->toBe([1]);
        }
    });

    it('refuses an unknown website with the same message on the admin page and in the API', function (): void {
        $name = 'Pest parity refused ' . substr(uniqid(), -6);
        csegAdminSave(['name' => $name, 'website_ids' => ['999']]);

        $errors = array_map(
            fn(Mage_Core_Model_Message_Abstract $message): string => $message->getText(),
            Mage::getSingleton('adminhtml/session')->getMessages(true)->getErrors(),
        );
        $api = csegCreate(['name' => $name, 'websiteIds' => [999]]);

        expect(Mage::getModel('customersegmentation/segment')->load($name, 'name')->getId())->toBeNull()
            ->and($api['status'])->toBe(422)
            ->and($errors)->toBe([$api['json']['message']]);
    });

    it('clears the customer groups when the admin form posts none', function (): void {
        $id = (int) csegCreate(['customerGroupIds' => [1]])['json']['id'];
        csegAdminSave(['name' => 'Pest clear groups ' . substr(uniqid(), -6), 'website_ids' => ['1']], $id);

        expect(Mage::getModel('customersegmentation/segment')->load($id)->getCustomerGroupIds())->toBe([]);
    });

    it('reads the website IDs that the form posts as one text in single store mode', function (): void {
        $websiteId = (int) createPriceWebsite('pest_cseg_admin')->getId();
        try {
            $name = 'Pest single store ' . substr(uniqid(), -6);
            csegAdminSave(['name' => $name, 'website_ids' => "1,{$websiteId}"]);
            $segment = Mage::getModel('customersegmentation/segment')->load($name, 'name');
            csegIds()[] = (int) $segment->getId();

            expect($segment->getWebsiteIds())->toBe([1, $websiteId]);
        } finally {
            deletePriceWebsite('pest_cseg_admin');
        }
    });
});

describe('Customer segment OpenAPI document', function (): void {

    it('shows the description of each operation and an example for each field', function (): void {
        $docs = apiGet('/api/docs.json')['json'];
        $paths = $docs['paths'];

        expect($paths['/api/rest/v2/customer-segments']['post']['description'])->toStartWith('Create a customer segment.')
            ->and($paths['/api/rest/v2/customer-segments/{id}']['patch']['description'])->toStartWith('Change some fields of a customer segment')
            ->and($paths['/api/rest/v2/customer-segments/{id}/refresh']['post']['summary'])->toBe('Refreshes the CustomerSegment resource.')
            ->and($paths['/api/rest/v2/customer-segments/{segmentId}/customers']['get']['description'])->toStartWith('List the customers of a segment');

        $properties = array_diff_key($docs['components']['schemas']['CustomerSegment']['properties'], ['extensions' => true]);
        expect(array_keys(array_filter($properties, fn(array $property): bool => !array_key_exists('example', $property))))->toBe([])
            ->and($properties['conditions']['example']['conditions'][0]['attribute'])->toBe('lifetime_sales');
    });

    it('keeps the generated text for an operation that has only the description of its resource', function (): void {
        $operation = apiGet('/api/docs.json')['json']['paths']['/api/rest/v2/cms-pages']['get'];

        expect($operation['description'])->toBe($operation['summary']);
    });
});
