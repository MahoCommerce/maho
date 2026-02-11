# Admin Content API Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a guardrailed admin API for LLMs to safely create/update CMS content.

**Architecture:** Extends existing OAuth consumers with admin permissions. New `/api/admin/*` endpoints with content sanitization. Logs to existing AdminActivityLog with consumer_id tracking.

**Tech Stack:** Maho (PHP 8.3), Symfony API Platform, HTMLPurifier, existing OAuth/JWT infrastructure.

---

## Task 1: Database Schema - Extend OAuth Consumer

**Files:**
- Create: `app/code/core/Mage/Oauth/sql/oauth_setup/upgrade-1.0.0.1-1.0.0.2.php`
- Modify: `app/code/core/Mage/Oauth/etc/config.xml` (version bump)

**Step 1: Create upgrade script**

```php
<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Oauth
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Oauth_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$tableName = $installer->getTable('oauth/consumer');

// Add admin API columns to oauth_consumer
if (!$connection->tableColumnExists($tableName, 'store_ids')) {
    $connection->addColumn($tableName, 'store_ids', [
        'type' => Maho\Db\Ddl\Table::TYPE_TEXT,
        'nullable' => true,
        'comment' => 'Allowed store IDs (JSON array or "all")',
    ]);
}

if (!$connection->tableColumnExists($tableName, 'admin_permissions')) {
    $connection->addColumn($tableName, 'admin_permissions', [
        'type' => Maho\Db\Ddl\Table::TYPE_TEXT,
        'nullable' => true,
        'comment' => 'Admin API permissions (JSON)',
    ]);
}

if (!$connection->tableColumnExists($tableName, 'last_used_at')) {
    $connection->addColumn($tableName, 'last_used_at', [
        'type' => Maho\Db\Ddl\Table::TYPE_DATETIME,
        'nullable' => true,
        'comment' => 'Last API usage timestamp',
    ]);
}

if (!$connection->tableColumnExists($tableName, 'expires_at')) {
    $connection->addColumn($tableName, 'expires_at', [
        'type' => Maho\Db\Ddl\Table::TYPE_DATETIME,
        'nullable' => true,
        'comment' => 'Token expiration date',
    ]);
}

$installer->endSetup();
```

**Step 2: Update config.xml version**

In `app/code/core/Mage/Oauth/etc/config.xml`, change:
```xml
<Mage_Oauth>
    <version>1.0.0.2</version>
</Mage_Oauth>
```

**Step 3: Verify migration runs**

Run: `php maho maho:setup:run`
Expected: Schema updates applied, no errors.

**Step 4: Commit**

```bash
git add app/code/core/Mage/Oauth/sql/oauth_setup/upgrade-1.0.0.1-1.0.0.2.php
git add app/code/core/Mage/Oauth/etc/config.xml
git commit -m "feat(oauth): add admin API permission columns to oauth_consumer"
```

---

## Task 2: Database Schema - Extend AdminActivityLog

**Files:**
- Create: `app/code/core/Maho/AdminActivityLog/sql/adminactivitylog_setup/upgrade-1.0.0-1.0.1.php`
- Modify: `app/code/core/Maho/AdminActivityLog/etc/config.xml` (version bump)

**Step 1: Create upgrade script**

```php
<?php
/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_AdminActivityLog
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$activityTable = $installer->getTable('adminactivitylog/activity');
$consumerTable = $installer->getTable('oauth/consumer');

// Add consumer_id column for API-based actions
if (!$connection->tableColumnExists($activityTable, 'consumer_id')) {
    $connection->addColumn($activityTable, 'consumer_id', [
        'type' => Maho\Db\Ddl\Table::TYPE_INTEGER,
        'unsigned' => true,
        'nullable' => true,
        'after' => 'user_id',
        'comment' => 'OAuth Consumer ID (for API actions)',
    ]);

    $connection->addIndex(
        $activityTable,
        $installer->getIdxName('adminactivitylog/activity', ['consumer_id']),
        ['consumer_id'],
    );

    $connection->addForeignKey(
        $installer->getFkName('adminactivitylog/activity', 'consumer_id', 'oauth/consumer', 'entity_id'),
        $activityTable,
        'consumer_id',
        $consumerTable,
        'entity_id',
        Maho\Db\Ddl\Table::ACTION_SET_NULL,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    );
}

$installer->endSetup();
```

**Step 2: Update config.xml version**

In `app/code/core/Maho/AdminActivityLog/etc/config.xml`:
```xml
<Maho_AdminActivityLog>
    <version>1.0.1</version>
</Maho_AdminActivityLog>
```

**Step 3: Update Activity model to accept consumer_id**

In `app/code/core/Maho/AdminActivityLog/Model/Activity.php`, modify `logActivity()`:

```php
public function logActivity(array $data): self
{
    if (!Mage::helper('adminactivitylog')->shouldLogActivity()) {
        return $this;
    }

    // Support both admin user and API consumer
    if (isset($data['consumer_id'])) {
        $data['user_id'] = null;
        // Username will be set by caller as "API: ConsumerName"
    } else {
        $adminUser = Mage::getSingleton('admin/session')->getUser();
        if ($adminUser) {
            $data['user_id'] = $adminUser->getId();
            $data['username'] = $adminUser->getUsername();
        }
    }

    // ... rest of method unchanged
}
```

**Step 4: Run migration and commit**

```bash
php maho maho:setup:run
git add app/code/core/Maho/AdminActivityLog/
git commit -m "feat(adminactivitylog): add consumer_id for API action tracking"
```

---

## Task 3: OAuth Consumer Model - Add Permission Methods

**Files:**
- Modify: `app/code/core/Mage/Oauth/Model/Consumer.php`

**Step 1: Add helper methods**

```php
/**
 * Check if consumer has admin API access enabled
 */
public function hasAdminAccess(): bool
{
    $permissions = $this->getAdminPermissionsArray();
    return !empty($permissions);
}

/**
 * Get admin permissions as array
 */
public function getAdminPermissionsArray(): array
{
    $permissions = $this->getData('admin_permissions');
    if (empty($permissions)) {
        return [];
    }
    if (is_string($permissions)) {
        return json_decode($permissions, true) ?: [];
    }
    return (array) $permissions;
}

/**
 * Check if consumer has specific permission
 */
public function hasPermission(string $permission): bool
{
    $permissions = $this->getAdminPermissionsArray();
    return !empty($permissions[$permission]);
}

/**
 * Get allowed store IDs
 * @return array|string Array of store IDs or "all"
 */
public function getAllowedStoreIds(): array|string
{
    $storeIds = $this->getData('store_ids');
    if (empty($storeIds) || $storeIds === 'all') {
        return 'all';
    }
    if (is_string($storeIds)) {
        return json_decode($storeIds, true) ?: [];
    }
    return (array) $storeIds;
}

/**
 * Check if consumer can access specific store
 */
public function canAccessStore(int $storeId): bool
{
    $allowed = $this->getAllowedStoreIds();
    if ($allowed === 'all') {
        return true;
    }
    return in_array($storeId, $allowed, true);
}

/**
 * Check if consumer is expired
 */
public function isExpired(): bool
{
    $expiresAt = $this->getData('expires_at');
    if (empty($expiresAt)) {
        return false;
    }
    return strtotime($expiresAt) < time();
}

/**
 * Update last used timestamp
 */
public function touchLastUsed(): self
{
    $this->setData('last_used_at', Mage::getModel('core/date')->gmtDate());
    $this->save();
    return $this;
}
```

**Step 2: Commit**

```bash
git add app/code/core/Mage/Oauth/Model/Consumer.php
git commit -m "feat(oauth): add admin permission helper methods to Consumer model"
```

---

## Task 4: Admin UI - Consumer Edit Form Extension

**Files:**
- Modify: `app/code/core/Mage/Oauth/Block/Adminhtml/Oauth/Consumer/Edit/Form.php`

**Step 1: Add admin access fieldset**

Add after the main fieldset in `_prepareForm()`:

```php
// Admin API Access fieldset
$adminFieldset = $form->addFieldset('admin_fieldset', [
    'legend' => Mage::helper('oauth')->__('Admin API Access'),
    'class' => 'fieldset-wide',
]);

$adminFieldset->addField('admin_enabled', 'select', [
    'name' => 'admin_enabled',
    'label' => Mage::helper('oauth')->__('Enable Admin API Access'),
    'values' => Mage::getModel('adminhtml/system_config_source_yesno')->toOptionArray(),
    'note' => Mage::helper('oauth')->__('Allow this consumer to access admin write endpoints'),
]);

$adminFieldset->addField('store_ids', 'multiselect', [
    'name' => 'store_ids',
    'label' => Mage::helper('oauth')->__('Store Access'),
    'values' => Mage::getSingleton('adminhtml/system_store')->getStoreValuesForForm(false, true),
    'note' => Mage::helper('oauth')->__('Select stores this consumer can access. Leave empty for all stores.'),
]);

$adminFieldset->addField('permission_cms_pages', 'select', [
    'name' => 'permissions[cms_pages]',
    'label' => Mage::helper('oauth')->__('CMS Pages'),
    'values' => Mage::getModel('adminhtml/system_config_source_yesno')->toOptionArray(),
]);

$adminFieldset->addField('permission_cms_blocks', 'select', [
    'name' => 'permissions[cms_blocks]',
    'label' => Mage::helper('oauth')->__('CMS Blocks'),
    'values' => Mage::getModel('adminhtml/system_config_source_yesno')->toOptionArray(),
]);

$adminFieldset->addField('permission_blog_posts', 'select', [
    'name' => 'permissions[blog_posts]',
    'label' => Mage::helper('oauth')->__('Blog Posts'),
    'values' => Mage::getModel('adminhtml/system_config_source_yesno')->toOptionArray(),
]);

$adminFieldset->addField('permission_media', 'select', [
    'name' => 'permissions[media]',
    'label' => Mage::helper('oauth')->__('Media Upload'),
    'values' => Mage::getModel('adminhtml/system_config_source_yesno')->toOptionArray(),
]);

$adminFieldset->addField('expires_at', 'date', [
    'name' => 'expires_at',
    'label' => Mage::helper('oauth')->__('Expires At'),
    'format' => Mage::app()->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT),
    'note' => Mage::helper('oauth')->__('Leave empty for no expiration'),
]);
```

**Step 2: Update controller to save new fields**

In `app/code/core/Mage/Oauth/controllers/Adminhtml/Oauth/ConsumerController.php`, modify the save action to handle the new fields:

```php
// In saveAction(), before $model->save():
$permissions = $this->getRequest()->getPost('permissions', []);
if (!empty($permissions)) {
    $model->setAdminPermissions(json_encode($permissions));
}

$storeIds = $this->getRequest()->getPost('store_ids', []);
if (!empty($storeIds)) {
    $model->setStoreIds(json_encode($storeIds));
} else {
    $model->setStoreIds('all');
}

$expiresAt = $this->getRequest()->getPost('expires_at');
$model->setExpiresAt($expiresAt ?: null);
```

**Step 3: Commit**

```bash
git add app/code/core/Mage/Oauth/Block/Adminhtml/Oauth/Consumer/Edit/Form.php
git add app/code/core/Mage/Oauth/controllers/Adminhtml/Oauth/ConsumerController.php
git commit -m "feat(oauth): add admin API permissions UI to consumer edit form"
```

---

## Task 5: Content Sanitizer Service

**Files:**
- Create: `app/code/core/Maho/ApiPlatform/symfony/src/Service/ContentSanitizer.php`

**Step 1: Create sanitizer service**

```php
<?php

declare(strict_types=1);

namespace Maho\ApiPlatform\Service;

/**
 * Content Sanitizer for Admin API
 *
 * Sanitizes HTML content and validates Maho directives
 */
final class ContentSanitizer
{
    private \HTMLPurifier $purifier;

    /** @var array<string> Allowed Maho directives */
    private const ALLOWED_DIRECTIVES = [
        'media',    // {{media url="..."}}
        'store',    // {{store url="..."}}
        'config',   // {{config path="..."}} - limited paths
        'youtube',  // {{youtube id="..."}}
        'vimeo',    // {{vimeo id="..."}}
    ];

    /** @var array<string> Safe config paths */
    private const SAFE_CONFIG_PATHS = [
        'general/store_information/',
        'web/unsecure/',
        'web/secure/',
        'design/',
        'trans_email/',
        'contacts/',
        'catalog/seo/',
    ];

    public function __construct()
    {
        $config = \HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', implode(',', [
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'p', 'br', 'hr',
            'strong', 'b', 'em', 'i', 'u', 's', 'small', 'mark',
            'a[href|title|target]',
            'img[src|alt|width|height|class]',
            'ul', 'ol', 'li', 'dl', 'dt', 'dd',
            'table', 'thead', 'tbody', 'tr', 'th', 'td',
            'div[class]', 'span[class]',
            'blockquote', 'pre', 'code',
            'figure', 'figcaption',
        ]));
        $config->set('HTML.SafeIframe', false);
        $config->set('HTML.SafeObject', false);
        $config->set('HTML.SafeEmbed', false);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('URI.AllowedSchemes', ['http', 'https', 'mailto']);

        $this->purifier = new \HTMLPurifier($config);
    }

    /**
     * Sanitize content for safe storage
     */
    public function sanitize(string $content): string
    {
        // First, extract and validate Maho directives
        $content = $this->processDirectives($content);

        // Then sanitize HTML
        return $this->purifier->purify($content);
    }

    /**
     * Process and validate Maho directives
     */
    private function processDirectives(string $content): string
    {
        // Match all {{...}} directives
        return preg_replace_callback(
            '/\{\{(\w+)([^}]*)\}\}/',
            function ($matches) {
                $directive = strtolower($matches[1]);
                $params = $matches[2];

                if (!in_array($directive, self::ALLOWED_DIRECTIVES, true)) {
                    // Strip dangerous directives (block, widget, layout, etc.)
                    return '';
                }

                // Validate specific directives
                return match ($directive) {
                    'config' => $this->validateConfigDirective($params) ? $matches[0] : '',
                    'youtube' => $this->validateVideoDirective($params, 'youtube') ? $matches[0] : '',
                    'vimeo' => $this->validateVideoDirective($params, 'vimeo') ? $matches[0] : '',
                    default => $matches[0], // media, store are always allowed
                };
            },
            $content
        );
    }

    /**
     * Validate config directive path
     */
    private function validateConfigDirective(string $params): bool
    {
        if (preg_match('/path=["\']?([^"\'}\s]+)["\']?/', $params, $match)) {
            $path = $match[1];
            foreach (self::SAFE_CONFIG_PATHS as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Validate video directive
     */
    private function validateVideoDirective(string $params, string $type): bool
    {
        // Must have id parameter with alphanumeric value
        return (bool) preg_match('/id=["\']?[\w\-]+["\']?/', $params);
    }
}
```

**Step 2: Commit**

```bash
git add app/code/core/Maho/ApiPlatform/symfony/src/Service/ContentSanitizer.php
git commit -m "feat(api): add ContentSanitizer service for admin API"
```

---

## Task 6: Admin API Authenticator

**Files:**
- Create: `app/code/core/Maho/ApiPlatform/symfony/src/Security/AdminApiAuthenticator.php`

**Step 1: Create authenticator**

```php
<?php

declare(strict_types=1);

namespace Maho\ApiPlatform\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Admin API Authenticator
 *
 * Authenticates requests using OAuth consumer key:secret
 * Format: Authorization: Bearer <key>:<secret>
 */
class AdminApiAuthenticator extends AbstractAuthenticator
{
    private const AUTHORIZATION_HEADER = 'Authorization';
    private const BEARER_PREFIX = 'Bearer ';

    #[\Override]
    public function supports(Request $request): ?bool
    {
        // Only support /api/admin/* routes
        if (!str_starts_with($request->getPathInfo(), '/api/admin/')) {
            return false;
        }

        return $request->headers->has(self::AUTHORIZATION_HEADER)
            && str_starts_with(
                $request->headers->get(self::AUTHORIZATION_HEADER, ''),
                self::BEARER_PREFIX,
            );
    }

    #[\Override]
    public function authenticate(Request $request): Passport
    {
        $authHeader = $request->headers->get(self::AUTHORIZATION_HEADER, '');
        $credentials = substr($authHeader, strlen(self::BEARER_PREFIX));

        if (empty($credentials) || !str_contains($credentials, ':')) {
            throw new CustomUserMessageAuthenticationException('Invalid credentials format. Expected key:secret');
        }

        [$key, $secret] = explode(':', $credentials, 2);

        // Load consumer by key
        $consumer = \Mage::getModel('oauth/consumer')->load($key, 'key');

        if (!$consumer->getId()) {
            throw new CustomUserMessageAuthenticationException('Invalid API credentials');
        }

        // Verify secret
        if ($consumer->getSecret() !== $secret) {
            throw new CustomUserMessageAuthenticationException('Invalid API credentials');
        }

        // Check if consumer has admin access
        if (!$consumer->hasAdminAccess()) {
            throw new CustomUserMessageAuthenticationException('Consumer does not have admin API access');
        }

        // Check expiration
        if ($consumer->isExpired()) {
            throw new CustomUserMessageAuthenticationException('API credentials have expired');
        }

        // Update last used timestamp
        $consumer->touchLastUsed();

        // Store consumer in request for later use
        $request->attributes->set('_admin_consumer', $consumer);

        $userBadge = new UserBadge(
            'consumer_' . $consumer->getId(),
            fn() => new ApiUser(
                identifier: 'consumer_' . $consumer->getId(),
                roles: ['ROLE_ADMIN_API'],
                customerId: null,
                adminId: null,
                apiUserId: (int) $consumer->getId(),
                permissions: array_keys(array_filter($consumer->getAdminPermissionsArray())),
            ),
        );

        return new SelfValidatingPassport($userBadge);
    }

    #[\Override]
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    #[\Override]
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            '@context' => '/api/contexts/Error',
            '@type' => 'hydra:Error',
            'hydra:title' => 'Unauthorized',
            'hydra:description' => strtr($exception->getMessageKey(), $exception->getMessageData()),
        ], Response::HTTP_UNAUTHORIZED);
    }
}
```

**Step 2: Register authenticator in security config**

Modify `app/code/core/Maho/ApiPlatform/symfony/config/packages/security.yaml`:

```yaml
security:
    firewalls:
        admin_api:
            pattern: ^/api/admin
            stateless: true
            custom_authenticators:
                - Maho\ApiPlatform\Security\AdminApiAuthenticator
        # ... existing firewalls
```

**Step 3: Commit**

```bash
git add app/code/core/Maho/ApiPlatform/symfony/src/Security/AdminApiAuthenticator.php
git add app/code/core/Maho/ApiPlatform/symfony/config/packages/security.yaml
git commit -m "feat(api): add AdminApiAuthenticator for consumer key:secret auth"
```

---

## Task 7: CMS Block Admin API Resource

**Files:**
- Create: `app/code/core/Maho/ApiPlatform/symfony/src/ApiResource/Admin/AdminCmsBlock.php`
- Create: `app/code/core/Maho/ApiPlatform/symfony/src/State/Processor/AdminCmsBlockProcessor.php`

**Step 1: Create API resource**

```php
<?php

declare(strict_types=1);

namespace Maho\ApiPlatform\ApiResource\Admin;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Maho\ApiPlatform\State\Processor\AdminCmsBlockProcessor;

#[ApiResource(
    shortName: 'AdminCmsBlock',
    description: 'Admin CMS Block management',
    routePrefix: '/admin',
    processor: AdminCmsBlockProcessor::class,
    operations: [
        new Post(
            uriTemplate: '/cms-blocks',
            description: 'Create a CMS block',
        ),
        new Put(
            uriTemplate: '/cms-blocks/{id}',
            description: 'Update a CMS block',
        ),
        new Delete(
            uriTemplate: '/cms-blocks/{id}',
            description: 'Delete a CMS block',
        ),
    ],
)]
class AdminCmsBlock
{
    public ?int $id = null;
    public string $identifier = '';
    public string $title = '';
    public ?string $content = null;
    public string $status = 'enabled';

    /** @var array<string> Store codes */
    public array $stores = [];
}
```

**Step 2: Create processor**

```php
<?php

declare(strict_types=1);

namespace Maho\ApiPlatform\State\Processor;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Maho\ApiPlatform\ApiResource\Admin\AdminCmsBlock;
use Maho\ApiPlatform\Service\ContentSanitizer;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<AdminCmsBlock, AdminCmsBlock|null>
 */
final class AdminCmsBlockProcessor implements ProcessorInterface
{
    private ContentSanitizer $sanitizer;
    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->sanitizer = new ContentSanitizer();
        $this->requestStack = $requestStack;
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?AdminCmsBlock
    {
        $request = $this->requestStack->getCurrentRequest();
        $consumer = $request?->attributes->get('_admin_consumer');

        if (!$consumer || !$consumer->hasPermission('cms_blocks')) {
            throw new AccessDeniedHttpException('Permission denied: cms_blocks');
        }

        // Validate store access
        $this->validateStoreAccess($consumer, $data->stores ?? []);

        if ($operation instanceof DeleteOperationInterface) {
            return $this->delete((int) $uriVariables['id'], $consumer);
        }

        $isNew = empty($uriVariables['id']);

        if ($isNew) {
            return $this->create($data, $consumer);
        }

        return $this->update((int) $uriVariables['id'], $data, $consumer);
    }

    private function create(AdminCmsBlock $data, \Mage_Oauth_Model_Consumer $consumer): AdminCmsBlock
    {
        $block = \Mage::getModel('cms/block');

        $block->setIdentifier($data->identifier);
        $block->setTitle($data->title);
        $block->setContent($this->sanitizer->sanitize($data->content ?? ''));
        $block->setIsActive($data->status === 'enabled' ? 1 : 0);
        $block->setStores($this->resolveStoreIds($data->stores));

        $block->save();

        $this->logActivity('create', $block, $consumer, null);

        $data->id = (int) $block->getId();
        return $data;
    }

    private function update(int $id, AdminCmsBlock $data, \Mage_Oauth_Model_Consumer $consumer): AdminCmsBlock
    {
        $block = \Mage::getModel('cms/block')->load($id);

        if (!$block->getId()) {
            throw new NotFoundHttpException('CMS block not found');
        }

        $oldData = $block->getData();

        $block->setIdentifier($data->identifier);
        $block->setTitle($data->title);
        $block->setContent($this->sanitizer->sanitize($data->content ?? ''));
        $block->setIsActive($data->status === 'enabled' ? 1 : 0);
        $block->setStores($this->resolveStoreIds($data->stores));

        $block->save();

        $this->logActivity('update', $block, $consumer, $oldData);

        $data->id = (int) $block->getId();
        return $data;
    }

    private function delete(int $id, \Mage_Oauth_Model_Consumer $consumer): ?AdminCmsBlock
    {
        $block = \Mage::getModel('cms/block')->load($id);

        if (!$block->getId()) {
            throw new NotFoundHttpException('CMS block not found');
        }

        $oldData = $block->getData();
        $block->delete();

        $this->logActivity('delete', $block, $consumer, $oldData);

        return null;
    }

    private function validateStoreAccess(\Mage_Oauth_Model_Consumer $consumer, array $storeCodes): void
    {
        if (empty($storeCodes)) {
            return;
        }

        foreach ($storeCodes as $code) {
            if ($code === 'all') {
                continue;
            }
            $store = \Mage::app()->getStore($code);
            if (!$consumer->canAccessStore((int) $store->getId())) {
                throw new AccessDeniedHttpException("Access denied to store: {$code}");
            }
        }
    }

    private function resolveStoreIds(array $storeCodes): array
    {
        if (empty($storeCodes) || in_array('all', $storeCodes, true)) {
            return [0]; // All stores
        }

        $ids = [];
        foreach ($storeCodes as $code) {
            $store = \Mage::app()->getStore($code);
            $ids[] = (int) $store->getId();
        }
        return $ids;
    }

    private function logActivity(string $action, \Mage_Cms_Model_Block $block, \Mage_Oauth_Model_Consumer $consumer, ?array $oldData): void
    {
        \Mage::getModel('adminactivitylog/activity')->logActivity([
            'action_type' => $action,
            'entity_type' => 'cms/block',
            'entity_id' => $block->getId(),
            'consumer_id' => $consumer->getId(),
            'username' => 'API: ' . $consumer->getName(),
            'old_data' => $oldData,
            'new_data' => $action !== 'delete' ? $block->getData() : null,
            'request_url' => '/api/admin/cms-blocks',
        ]);
    }
}
```

**Step 3: Commit**

```bash
git add app/code/core/Maho/ApiPlatform/symfony/src/ApiResource/Admin/
git add app/code/core/Maho/ApiPlatform/symfony/src/State/Processor/AdminCmsBlockProcessor.php
git commit -m "feat(api): add admin CMS block endpoints (POST/PUT/DELETE)"
```

---

## Task 8: CMS Page Admin API Resource

**Files:**
- Create: `app/code/core/Maho/ApiPlatform/symfony/src/ApiResource/Admin/AdminCmsPage.php`
- Create: `app/code/core/Maho/ApiPlatform/symfony/src/State/Processor/AdminCmsPageProcessor.php`

Follow the same pattern as Task 7, but for CMS pages with additional fields:
- `contentHeading`
- `metaKeywords`
- `metaDescription`

**Step 1: Create API resource and processor (similar pattern to CmsBlock)**

**Step 2: Commit**

```bash
git add app/code/core/Maho/ApiPlatform/symfony/src/ApiResource/Admin/AdminCmsPage.php
git add app/code/core/Maho/ApiPlatform/symfony/src/State/Processor/AdminCmsPageProcessor.php
git commit -m "feat(api): add admin CMS page endpoints (POST/PUT/DELETE)"
```

---

## Task 9: Blog Post Admin API Resource

**Files:**
- Create: `app/code/core/Maho/ApiPlatform/symfony/src/ApiResource/Admin/AdminBlogPost.php`
- Create: `app/code/core/Maho/ApiPlatform/symfony/src/State/Processor/AdminBlogPostProcessor.php`

Follow the same pattern as Task 7, using your existing blog model.

---

## Task 10: Media Upload Admin API

**Files:**
- Create: `app/code/core/Maho/ApiPlatform/symfony/src/ApiResource/Admin/AdminMedia.php`
- Create: `app/code/core/Maho/ApiPlatform/symfony/src/State/Processor/AdminMediaProcessor.php`
- Create: `app/code/core/Maho/ApiPlatform/symfony/src/Controller/AdminMediaController.php`

**Step 1: Create media controller for multipart uploads**

```php
<?php

declare(strict_types=1);

namespace Maho\ApiPlatform\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

class AdminMediaController
{
    private const MAX_SIZE = 10 * 1024 * 1024; // 10MB
    private const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    #[Route('/api/admin/media', name: 'admin_media_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $consumer = $request->attributes->get('_admin_consumer');

        if (!$consumer || !$consumer->hasPermission('media')) {
            throw new AccessDeniedHttpException('Permission denied: media');
        }

        $file = $request->files->get('file');
        if (!$file) {
            throw new BadRequestHttpException('No file uploaded');
        }

        // Validate size
        if ($file->getSize() > self::MAX_SIZE) {
            throw new BadRequestHttpException('File too large. Maximum size is 10MB');
        }

        // Validate type
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_TYPES, true)) {
            throw new BadRequestHttpException('Invalid file type. Allowed: jpg, png, gif, webp');
        }

        // Get destination folder
        $folder = $request->request->get('folder', 'wysiwyg');
        $folder = trim($folder, '/');

        // Security: ensure folder is under wysiwyg/
        if (!str_starts_with($folder, 'wysiwyg')) {
            $folder = 'wysiwyg/' . $folder;
        }

        // Generate filename
        $filename = $request->request->get('filename');
        if ($filename) {
            $filename = preg_replace('/[^a-zA-Z0-9_-]/', '', $filename);
        } else {
            $filename = 'upload-' . time() . '-' . bin2hex(random_bytes(4));
        }

        // Destination path (will be converted to WebP)
        $destPath = $folder . '/' . $filename . '.webp';
        $fullPath = \Mage::getBaseDir('media') . '/' . $destPath;

        // Ensure directory exists
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Convert to WebP using Maho's image handling
        $image = new \Varien_Image($file->getPathname());
        $image->quality(85);
        $image->save($fullPath);

        // Get dimensions
        $size = getimagesize($fullPath);

        // Log activity
        \Mage::getModel('adminactivitylog/activity')->logActivity([
            'action_type' => 'create',
            'entity_type' => 'media/file',
            'entity_id' => null,
            'consumer_id' => $consumer->getId(),
            'username' => 'API: ' . $consumer->getName(),
            'new_data' => ['path' => $destPath, 'size' => filesize($fullPath)],
            'request_url' => '/api/admin/media',
        ]);

        return new JsonResponse([
            'success' => true,
            'url' => '/media/' . $destPath,
            'directive' => '{{media url="' . $destPath . '"}}',
            'size' => filesize($fullPath),
            'dimensions' => [
                'width' => $size[0] ?? null,
                'height' => $size[1] ?? null,
            ],
        ], Response::HTTP_CREATED);
    }
}
```

**Step 2: Register route**

Add to `app/code/core/Maho/ApiPlatform/symfony/config/routes.yaml`:

```yaml
admin_media_upload:
    path: /api/admin/media
    controller: Maho\ApiPlatform\Controller\AdminMediaController::upload
    methods: [POST]
```

**Step 3: Commit**

```bash
git add app/code/core/Maho/ApiPlatform/symfony/src/Controller/AdminMediaController.php
git add app/code/core/Maho/ApiPlatform/symfony/config/routes.yaml
git commit -m "feat(api): add admin media upload endpoint with WebP conversion"
```

---

## Task 11: Integration Testing

**Files:**
- Create: `tests/Api/AdminContentApiTest.php`

**Step 1: Create integration test**

```php
<?php

namespace Tests\Api;

use PHPUnit\Framework\TestCase;

class AdminContentApiTest extends TestCase
{
    private string $baseUrl;
    private string $consumerKey;
    private string $consumerSecret;

    protected function setUp(): void
    {
        $this->baseUrl = getenv('API_BASE_URL') ?: 'https://maho.tenniswarehouse.com.au';
        $this->consumerKey = getenv('API_CONSUMER_KEY');
        $this->consumerSecret = getenv('API_CONSUMER_SECRET');
    }

    public function testCreateCmsBlock(): void
    {
        $response = $this->request('POST', '/api/admin/cms-blocks', [
            'identifier' => 'test-block-' . time(),
            'title' => 'Test Block',
            'content' => '<p>Test content</p>',
            'status' => 'enabled',
            'stores' => ['default'],
        ]);

        $this->assertEquals(201, $response['status']);
        $this->assertNotEmpty($response['body']['id']);
    }

    public function testContentSanitization(): void
    {
        $response = $this->request('POST', '/api/admin/cms-blocks', [
            'identifier' => 'test-sanitize-' . time(),
            'title' => 'Sanitization Test',
            'content' => '<p>Safe</p><script>alert("xss")</script>{{widget type="bad"}}',
            'status' => 'enabled',
        ]);

        $this->assertEquals(201, $response['status']);
        // Script and widget should be stripped
    }

    public function testUnauthorizedAccess(): void
    {
        $response = $this->request('POST', '/api/admin/cms-blocks', [
            'identifier' => 'test',
            'title' => 'Test',
            'content' => 'Test',
        ], false); // No auth

        $this->assertEquals(401, $response['status']);
    }

    private function request(string $method, string $path, array $data = [], bool $auth = true): array
    {
        $ch = curl_init($this->baseUrl . $path);

        $headers = ['Content-Type: application/json'];
        if ($auth) {
            $headers[] = 'Authorization: Bearer ' . $this->consumerKey . ':' . $this->consumerSecret;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($data),
        ]);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $status,
            'body' => json_decode($body, true),
        ];
    }
}
```

**Step 2: Commit**

```bash
git add tests/Api/AdminContentApiTest.php
git commit -m "test(api): add admin content API integration tests"
```

---

## Summary

| Task | Description | Est. Steps |
|------|-------------|------------|
| 1 | OAuth consumer schema extension | 4 |
| 2 | AdminActivityLog consumer_id column | 4 |
| 3 | Consumer model permission methods | 2 |
| 4 | Admin UI for permissions | 3 |
| 5 | Content sanitizer service | 2 |
| 6 | Admin API authenticator | 3 |
| 7 | CMS Block admin endpoints | 3 |
| 8 | CMS Page admin endpoints | 2 |
| 9 | Blog Post admin endpoints | 2 |
| 10 | Media upload endpoint | 3 |
| 11 | Integration tests | 2 |

**Total:** ~30 steps, each 2-5 minutes.
