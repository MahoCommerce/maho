<?php

/**
 * Maho
 *
 * @package    Tests
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/**
 * API v2 Cart Custom Options Tests (WRITE)
 *
 * Tests adding products with ALL custom option types to a guest cart,
 * including comprehensive file upload security validation.
 *
 * Note: Required option validation in Magento only happens during checkout
 * (PROCESS_MODE_FULL), not during add-to-cart (PROCESS_MODE_LITE).
 * File security validation always applies regardless of process mode.
 *
 * @group write
 */

// ─── Test Product Setup ──────────────────────────────────────────────────────

/**
 * Create a test product with all custom option types.
 * Returns ['product_id' => int, 'sku' => string, 'options' => [...]]
 */
function createTestProductWithOptions(): array
{
    static $testProduct = null;
    if ($testProduct !== null) {
        return $testProduct;
    }

    $token = serviceToken(['products/write', 'products/delete']);

    $sku = 'PEST-OPTS-' . uniqid();
    $create = apiPost('/api/products', [
        'sku' => $sku,
        'name' => 'Custom Options Test Product',
        'price' => 29.99,
        'websiteIds' => [1],
    ], $token);

    if ($create['status'] < 200 || $create['status'] >= 300) {
        throw new \RuntimeException('Failed to create test product: ' . json_encode($create['json']));
    }

    $productId = $create['json']['id'] ?? null;
    if (!$productId) {
        throw new \RuntimeException('No product ID in response: ' . json_encode($create['json']));
    }

    trackCreated('product', (int) $productId);

    // Create all option types
    $optionSpecs = [
        ['title' => 'Required Text Field', 'type' => 'field', 'required' => true, 'price' => 0, 'priceType' => 'fixed', 'maxCharacters' => 50],
        ['title' => 'Optional Text Area', 'type' => 'area', 'required' => false, 'price' => 0, 'priceType' => 'fixed'],
        ['title' => 'Required Dropdown', 'type' => 'drop_down', 'required' => true, 'values' => [
            ['title' => 'Option A', 'price' => 0, 'priceType' => 'fixed', 'sortOrder' => 0],
            ['title' => 'Option B', 'price' => 5.00, 'priceType' => 'fixed', 'sortOrder' => 1],
            ['title' => 'Option C', 'price' => 10.00, 'priceType' => 'fixed', 'sortOrder' => 2],
        ]],
        ['title' => 'Checkbox Group', 'type' => 'checkbox', 'required' => false, 'values' => [
            ['title' => 'Check 1', 'price' => 1.00, 'priceType' => 'fixed', 'sortOrder' => 0],
            ['title' => 'Check 2', 'price' => 2.00, 'priceType' => 'fixed', 'sortOrder' => 1],
        ]],
        ['title' => 'Required Date', 'type' => 'date', 'required' => true, 'price' => 0, 'priceType' => 'fixed'],
        ['title' => 'Required File Upload', 'type' => 'file', 'required' => true, 'price' => 5.00, 'priceType' => 'fixed',
            'fileExtension' => 'jpg,jpeg,png,gif', 'imageSizeX' => 2000, 'imageSizeY' => 2000],
        ['title' => 'Optional File Upload', 'type' => 'file', 'required' => false, 'price' => 0, 'priceType' => 'fixed',
            'fileExtension' => 'jpg,jpeg,png,gif,pdf'],
    ];

    $options = [];
    foreach ($optionSpecs as $spec) {
        $result = apiPost("/api/products/{$productId}/custom-options", $spec, $token);
        if ($result['status'] < 200 || $result['status'] >= 300) {
            throw new \RuntimeException('Failed to create option "' . $spec['title'] . '": ' . json_encode($result['json']));
        }
        $optionData = $result['json'];
        $options[$spec['title']] = $optionData;
    }

    // API Platform doesn't map fileExtension/imageSizeX/Y — set them via DB
    try {
        $write = \Mage::getSingleton('core/resource')->getConnection('core_write');
        foreach ($optionSpecs as $spec) {
            $optionTitle = $spec['title'];
            if (!isset($options[$optionTitle]['id'])) {
                continue;
            }
            $optId = (int) $options[$optionTitle]['id'];
            $updates = [];
            if (!empty($spec['fileExtension'])) {
                $updates[] = 'file_extension = ' . $write->quote($spec['fileExtension']);
            }
            if (!empty($spec['imageSizeX'])) {
                $updates[] = 'image_size_x = ' . (int) $spec['imageSizeX'];
            }
            if (!empty($spec['imageSizeY'])) {
                $updates[] = 'image_size_y = ' . (int) $spec['imageSizeY'];
            }
            if (!empty($updates)) {
                $write->query('UPDATE catalog_product_option SET ' . implode(', ', $updates) . " WHERE option_id = {$optId}");
            }
        }
        // Also set has_options = 1 on the product
        $write->query("UPDATE catalog_product_entity SET has_options = 1, required_options = 1 WHERE entity_id = {$productId}");
    } catch (\Exception $e) {
        // Non-fatal — some tests may still pass without these
    }

    $testProduct = [
        'product_id' => (int) $productId,
        'sku' => $sku,
        'options' => $options,
    ];

    return $testProduct;
}

/**
 * Get option ID by title from test product options
 */
function optionId(string $title): int
{
    $product = createTestProductWithOptions();
    if (!isset($product['options'][$title])) {
        throw new \RuntimeException("Option '{$title}' not found in test product");
    }
    return (int) $product['options'][$title]['id'];
}

/**
 * Get the first value ID for a select-type option
 */
function optionValueId(string $title, int $index = 0): int
{
    $product = createTestProductWithOptions();
    $values = $product['options'][$title]['values'] ?? [];
    if (!isset($values[$index])) {
        throw new \RuntimeException("Value index {$index} not found for option '{$title}'");
    }
    return (int) $values[$index]['id'];
}

/**
 * Generate a minimal valid PNG (1x1 pixel, ~67 bytes)
 */
function generateMinimalPng(): string
{
    $img = imagecreatetruecolor(1, 1);
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefilledrectangle($img, 0, 0, 0, 0, $white);
    ob_start();
    imagepng($img);
    $data = ob_get_clean();
    imagedestroy($img);
    return $data;
}

/**
 * Generate a PNG of specific dimensions
 */
function generatePngWithSize(int $width, int $height): string
{
    $img = imagecreatetruecolor($width, $height);
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefilledrectangle($img, 0, 0, $width - 1, $height - 1, $white);
    ob_start();
    imagepng($img);
    $data = ob_get_clean();
    imagedestroy($img);
    return $data;
}

/**
 * Build all required options (text, dropdown, date, file) for convenience.
 * Override specific ones via $overrides.
 */
function allRequiredOptions(array $overrides = []): array
{
    return array_replace([
        (string) optionId('Required Text Field') => 'Test text',
        (string) optionId('Required Dropdown') => optionValueId('Required Dropdown', 0),
        (string) optionId('Required Date') => '2026-06-15',
    ], $overrides);
}

/**
 * Build default file data for the required file option.
 */
function defaultFileData(): array
{
    return [
        (string) optionId('Required File Upload') => [
            'name' => 'test.png',
            'base64_encoded_data' => base64_encode(generateMinimalPng()),
        ],
    ];
}

/**
 * Helper to create cart and add item with options
 */
function addItemWithOptions(array $options, array $optionsFiles = []): array
{
    $product = createTestProductWithOptions();
    $createResponse = apiPost('/api/guest-carts', []);
    if ($createResponse['status'] !== 201) {
        throw new \RuntimeException('Failed to create cart');
    }
    $cartId = $createResponse['json']['maskedId'];
    trackCreated('quote', (int) $createResponse['json']['id']);

    $payload = [
        'sku' => $product['sku'],
        'qty' => 1,
        'options' => $options,
    ];

    if (!empty($optionsFiles)) {
        $payload['options_files'] = $optionsFiles;
    }

    return apiPost("/api/guest-carts/{$cartId}/items", $payload);
}

// ─── Cleanup ─────────────────────────────────────────────────────────────────

afterAll(function (): void {
    cleanupTestData();
});

// ─── Text Options ────────────────────────────────────────────────────────────

describe('Cart Custom Options — Text (field, area)', function (): void {

    it('adds item with required text field', function (): void {
        $response = addItemWithOptions(allRequiredOptions(), defaultFileData());

        expect($response['status'])->toBe(200);
        expect($response['json']['items'])->not->toBeEmpty();
    });

    it('adds item with optional area provided', function (): void {
        $options = allRequiredOptions([
            (string) optionId('Optional Text Area') => 'A longer text area value with multiple words',
        ]);

        $response = addItemWithOptions($options, defaultFileData());

        expect($response['status'])->toBe(200);
        expect($response['json']['items'])->not->toBeEmpty();
    });

    it('adds item with optional area omitted', function (): void {
        $response = addItemWithOptions(allRequiredOptions(), defaultFileData());

        expect($response['status'])->toBe(200);
    });
});

// ─── Select Options ─────────────────────────────────────────────────────────

describe('Cart Custom Options — Select (drop_down, checkbox)', function (): void {

    it('adds item with required dropdown value B', function (): void {
        $options = allRequiredOptions([
            (string) optionId('Required Dropdown') => optionValueId('Required Dropdown', 1),
        ]);

        $response = addItemWithOptions($options, defaultFileData());

        expect($response['status'])->toBe(200);
    });

    it('adds item with checkbox multiple values', function (): void {
        $options = allRequiredOptions([
            (string) optionId('Checkbox Group') => [
                optionValueId('Checkbox Group', 0),
                optionValueId('Checkbox Group', 1),
            ],
        ]);

        $response = addItemWithOptions($options, defaultFileData());

        expect($response['status'])->toBe(200);
    });
});

// ─── Date Options ────────────────────────────────────────────────────────────

describe('Cart Custom Options — Date', function (): void {

    it('adds item with required date', function (): void {
        $response = addItemWithOptions(allRequiredOptions(), defaultFileData());

        expect($response['status'])->toBe(200);
    });
});

// ─── File Options — Happy Path ───────────────────────────────────────────────

describe('Cart Custom Options — File (happy path)', function (): void {

    it('uploads a base64 PNG file successfully', function (): void {
        $response = addItemWithOptions(allRequiredOptions(), [
            (string) optionId('Required File Upload') => [
                'name' => 'photo.png',
                'base64_encoded_data' => base64_encode(generateMinimalPng()),
            ],
        ]);

        expect($response['status'])->toBe(200);
        expect($response['json']['items'])->not->toBeEmpty();
    });
});

// ─── File Options — Security (Critical) ─────────────────────────────────────

describe('Cart Custom Options — File Security', function (): void {

    it('rejects forbidden extension .php', function (): void {
        $response = addItemWithOptions(allRequiredOptions(), [
            (string) optionId('Required File Upload') => [
                'name' => 'malicious.php',
                'base64_encoded_data' => base64_encode('<?php echo "pwned"; ?>'),
            ],
        ]);

        expect($response['status'])->toBe(400);
        expect($response['json']['message'])->toContain('not allowed');
    });

    it('rejects forbidden extension .phtml', function (): void {
        $response = addItemWithOptions(allRequiredOptions(), [
            (string) optionId('Required File Upload') => [
                'name' => 'template.phtml',
                'base64_encoded_data' => base64_encode('<?php echo "pwned"; ?>'),
            ],
        ]);

        expect($response['status'])->toBe(400);
        expect($response['json']['message'])->toContain('not allowed');
    });

    it('rejects extension not in allowed list', function (): void {
        $response = addItemWithOptions(allRequiredOptions(), [
            (string) optionId('Required File Upload') => [
                'name' => 'document.bmp',
                'base64_encoded_data' => base64_encode(generateMinimalPng()),
            ],
        ]);

        expect($response['status'])->toBe(400);
        expect($response['json']['message'])->toContain('not allowed');
    });

    it('rejects polyshell: .jpg extension with PHP content (CVE-2025-54263)', function (): void {
        $phpContent = '<?php system($_GET["cmd"]); ?>';

        $response = addItemWithOptions(allRequiredOptions(), [
            (string) optionId('Required File Upload') => [
                'name' => 'innocent.jpg',
                'base64_encoded_data' => base64_encode($phpContent),
            ],
        ]);

        // Rejected by getimagesizefromstring — not valid image data
        expect($response['status'])->toBe(400);
    });

    it('rejects image exceeding dimension limits', function (): void {
        // Option has imageSizeX=2000, imageSizeY=2000
        $largePng = generatePngWithSize(2500, 2500);

        $response = addItemWithOptions(allRequiredOptions(), [
            (string) optionId('Required File Upload') => [
                'name' => 'large.png',
                'base64_encoded_data' => base64_encode($largePng),
            ],
        ]);

        expect($response['status'])->toBe(400);
        expect($response['json']['message'])->toContain('image size');
    });

    it('rejects invalid base64 data', function (): void {
        $response = addItemWithOptions(allRequiredOptions(), [
            (string) optionId('Required File Upload') => [
                'name' => 'photo.png',
                'base64_encoded_data' => '!!!not-valid-base64!!!',
            ],
        ]);

        expect($response['status'])->toBe(400);
        expect($response['json']['message'])->toContain('base64');
    });

    it('rejects file data on non-file option type', function (): void {
        $response = addItemWithOptions(allRequiredOptions(), [
            (string) optionId('Required Text Field') => [
                'name' => 'photo.png',
                'base64_encoded_data' => base64_encode(generateMinimalPng()),
            ],
            (string) optionId('Required File Upload') => [
                'name' => 'test.png',
                'base64_encoded_data' => base64_encode(generateMinimalPng()),
            ],
        ]);

        expect($response['status'])->toBe(400);
        expect($response['json']['message'])->toContain('not a valid file-type option');
    });
});

// ─── File Options — Optional ─────────────────────────────────────────────────

describe('Cart Custom Options — File Optional', function (): void {

    it('succeeds when optional file is omitted', function (): void {
        $response = addItemWithOptions(allRequiredOptions(), defaultFileData());

        expect($response['status'])->toBe(200);
    });
});

// ─── Combined Options ────────────────────────────────────────────────────────

describe('Cart Custom Options — Combined', function (): void {

    it('adds item with text + select + file simultaneously', function (): void {
        $options = allRequiredOptions([
            (string) optionId('Optional Text Area') => 'Special instructions for the order',
            (string) optionId('Required Dropdown') => optionValueId('Required Dropdown', 2),
            (string) optionId('Checkbox Group') => [
                optionValueId('Checkbox Group', 0),
            ],
            (string) optionId('Required Date') => '2026-12-25',
        ]);
        $files = [
            (string) optionId('Required File Upload') => [
                'name' => 'design.png',
                'base64_encoded_data' => base64_encode(generateMinimalPng()),
            ],
            (string) optionId('Optional File Upload') => [
                'name' => 'reference.png',
                'base64_encoded_data' => base64_encode(generateMinimalPng()),
            ],
        ];

        $response = addItemWithOptions($options, $files);

        expect($response['status'])->toBe(200);
        expect($response['json']['items'])->not->toBeEmpty();
        expect(count($response['json']['items']))->toBe(1);
    });
});
