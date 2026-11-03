<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Maho\Blog\Api\BlogCategory;
use Maho\Blog\Api\BlogCategoryProcessor;
use Maho\Blog\Api\BlogPost;
use Maho\Blog\Api\BlogPostProcessor;
use Maho\Blog\Api\BlogPostProvider;
use Maho\Import\Importer\AbstractCmsImporter;
use Maho\Import\Importer\BlogPosts;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Helpers\AdminApiParity;
use Tests\Helpers\TransportParity;

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin pages, the API, the storefront and the importer load and save blog posts and categories
 * through Maho_Blog_Service_Post and Maho_Blog_Service_Category (#1348).
 */

class BlogParitySpyService extends Maho_Blog_Service_Post
{
    /** @var list<Maho_Blog_Model_Post> */
    public array $saved = [];

    #[\Override]
    public function save(Maho_Blog_Model_Post $post): Maho_Blog_Model_Post
    {
        $this->saved[] = $post;
        return parent::save($post);
    }
}

/**
 * @param array<string, mixed> $fields
 * @return array{success: list<string>, errors: list<string>}
 */
function blogParityAdmin(string $controller, string $action, array $fields): array
{
    return TransportParity::admin($controller, $action, $fields);
}

/**
 * @param class-string<BlogPost|BlogCategory> $dtoClass
 * @param array<string, mixed> $body
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function blogParityApi(string $dtoClass, array $body, ?int $id = null, bool $delete = false): array
{
    $path = $dtoClass === BlogPost::class ? '/api/rest/v2/blog-posts' : '/api/rest/v2/blog-categories';
    $method = $delete ? 'DELETE' : ($id ? 'PUT' : 'POST');
    $request = AdminApiParity::jsonRequest($method, $path . ($id ? "/{$id}" : ''), $body);
    $dto = new $dtoClass();
    foreach ($body as $property => $value) {
        $dto->{$property} = $value;
    }
    $processor = $dtoClass === BlogPost::class
        ? new BlogPostProcessor(AdminApiParity::adminSecurity())
        : new BlogCategoryProcessor(AdminApiParity::adminSecurity());
    $operation = $delete ? new Delete() : ($id ? new Put() : new Post());

    return AdminApiParity::api($request, fn() => $processor->process($dto, $operation, $id ? ['id' => $id] : [], ['request' => $request]));
}

function blogParityCleanup(): void
{
    foreach (Mage::getModel('blog/post')->getCollection()->addAttributeToFilter('url_key', ['like' => 'blog-parity-%']) as $post) {
        $post->delete();
    }
    foreach (Mage::getModel('blog/category')->getCollection()->addAttributeToFilter('url_key', ['like' => 'blog-parity-%']) as $category) {
        if (Mage::getModel('blog/category')->load($category->getId())->getId()) {
            $category->delete();
        }
    }
}

function blogParityPost(string $urlKey, array $data = []): Maho_Blog_Model_Post
{
    $post = Mage::getService('blog/post')->newPost()->addData(['title' => 'Blog Parity', 'url_key' => $urlKey] + $data);
    return Mage::getService('blog/post')->save($post);
}

function blogParityCategory(string $urlKey, int $parentId = 0): Maho_Blog_Model_Category
{
    $category = Mage::getService('blog/category')->newCategory()->addData(['name' => 'Blog Parity', 'url_key' => $urlKey, 'parent_id' => $parentId]);
    return Mage::getService('blog/category')->save($category);
}

beforeEach(function (): void {
    if (!Mage::helper('core')->isModuleEnabled('Maho_Blog')) {
        test()->markTestSkipped('Maho_Blog is disabled');
    }
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    blogParityCleanup();
});

afterEach(fn() => blogParityCleanup());

describe('blog posts', function (): void {
    it('refuses a URL key that another post of the same store view has, on the admin page and in the API', function (): void {
        blogParityPost('blog-parity-taken');

        $admin = blogParityAdmin(Maho_Blog_Adminhtml_Blog_PostController::class, 'save', [
            'title' => 'Blog Parity Admin', 'url_key' => 'blog-parity-taken', 'stores' => [1], 'is_active' => 1,
        ]);
        $api = blogParityApi(BlogPost::class, ['title' => 'Blog Parity Api', 'urlKey' => 'blog-parity-taken', 'stores' => ['1']]);

        expect($admin['success'])->toBe([])
            ->and($admin['errors'])->toBe(['Another post of the same store view has the URL key "blog-parity-taken".'])
            ->and($api['status'])->toBe(409)
            ->and($api['errors'])->toBe($admin['errors']);
    });

    it('refuses a URL key outside the format and a missing title with the same errors', function (): void {
        $admin = blogParityAdmin(Maho_Blog_Adminhtml_Blog_PostController::class, 'save', [
            'title' => '', 'url_key' => 'Blog_Parity', 'stores' => [0], 'is_active' => 1,
        ]);
        $api = blogParityApi(BlogPost::class, ['title' => '', 'urlKey' => 'Blog_Parity']);

        expect($admin['errors'])->toBe([
            'The post title is required.',
            'The URL key "Blog_Parity" must have only lowercase letters, digits and dashes.',
        ])
            ->and($api['status'])->toBe(422)
            ->and($api['errors'])->toBe($admin['errors'])
            ->and($api['fields'])->toBe(['title', 'urlKey']);
    });

    it('refuses an unknown category and an image path outside media/blog on every write path', function (): void {
        $api = blogParityApi(BlogPost::class, ['title' => 'Blog Parity', 'urlKey' => 'blog-parity-refused', 'categoryIds' => [99999999]]);
        $post = Mage::getService('blog/post')->newPost()->addData(['title' => 'Blog Parity', 'url_key' => 'blog-parity-image', 'image' => '../../app/etc/local.xml']);

        expect($api['status'])->toBe(422)
            ->and($api['errors'])->toBe(['Unknown blog category ID(s): 99999999'])
            ->and(fn() => Mage::getService('blog/post')->save($post))->toThrow(Mage_Core_Exception_Input::class);
    });

    it('saves the posts of the importer through the service', function (): void {
        $spy = new BlogParitySpyService();
        Mage::unregister('_service/blog/post');
        Mage::register('_service/blog/post', $spy);

        $contentDir = sys_get_temp_dir() . '/blog-parity-' . uniqid();
        mkdir($contentDir);
        file_put_contents($contentDir . '/post.html', '<p>Body</p>');
        $csv = tempnam(sys_get_temp_dir(), 'blog') . '.csv';
        file_put_contents($csv, "url_key,stores,title,content_file\nblog-parity-import," . Mage::app()->getStore(1)->getCode() . ",Blog Parity,post.html\n");

        new BlogPosts()->import($csv, [AbstractCmsImporter::OPTION_CONTENT_DIR => $contentDir]);

        expect(array_map(fn($post) => $post->getUrlKey(), $spy->saved))->toBe(['blog-parity-import']);
        unlink($csv);
        unlink($contentDir . '/post.html');
        rmdir($contentDir);
    });

    it('hides a scheduled post from the storefront and from an API reader without backend access', function (): void {
        $tomorrow = Mage::app()->getLocale()->utcToStore()->modify('+1 day')->format(Mage_Core_Model_Locale::DATE_FORMAT);
        $post = blogParityPost('blog-parity-scheduled', ['publish_date' => $tomorrow]);
        $storeId = (int) Mage::app()->getStore()->getId();

        expect(fn() => Mage::getService('blog/post')->getVisibleById((int) $post->getId(), $storeId))
            ->toThrow(Mage_Core_Exception_NoSuchEntity::class)
            ->and(new BlogPostProvider()->provide(new Get(class: BlogPost::class), ['id' => $post->getId()]))->toBeNull();
    });
});

describe('blog categories', function (): void {
    it('refuses to move a category under its own child, on the admin page and in the API', function (): void {
        $parent = blogParityCategory('blog-parity-parent');
        $child = blogParityCategory('blog-parity-child', (int) $parent->getId());

        $admin = blogParityAdmin(Maho_Blog_Adminhtml_Blog_CategoryController::class, 'save', [
            'id' => $parent->getId(), 'category' => ['parent_id' => $child->getId()],
        ]);
        $api = blogParityApi(BlogCategory::class, ['parentId' => (int) $child->getId()], (int) $parent->getId());

        expect($admin['errors'])->toBe(['A blog category cannot be moved under itself or its descendants'])
            ->and($api['status'])->toBe(422)
            ->and($api['errors'])->toBe($admin['errors'])
            ->and($api['fields'])->toBe(['parentId']);
    });

    it('deletes the subtree of a category on the admin page and in the API', function (): void {
        $adminParent = blogParityCategory('blog-parity-admin');
        $adminChild = blogParityCategory('blog-parity-admin-child', (int) $adminParent->getId());
        $apiParent = blogParityCategory('blog-parity-api');
        $apiChild = blogParityCategory('blog-parity-api-child', (int) $apiParent->getId());

        $admin = blogParityAdmin(Maho_Blog_Adminhtml_Blog_CategoryController::class, 'delete', ['id' => $adminParent->getId()]);
        $api = blogParityApi(BlogCategory::class, [], (int) $apiParent->getId(), delete: true);

        expect($admin['success'])->toBe(['Category was successfully deleted'])
            ->and($api['status'])->toBe(200)
            ->and(Mage::getModel('blog/category')->load($adminChild->getId())->getId())->toBeNull()
            ->and(Mage::getModel('blog/category')->load($apiChild->getId())->getId())->toBeNull();
    });
});
