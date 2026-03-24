<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Encrypted System Config Backend', function () {
    test('encrypts value on save and decrypts on load', function () {
        $path = 'test/encryption/' . uniqid();
        $secret = 'my-api-key-12345';

        $backend = Mage::getModel('adminhtml/system_config_backend_encrypted');
        $backend->setPath($path);
        $backend->setScope('default');
        $backend->setScopeId(0);
        $backend->setValue($secret);
        $backend->save();

        $loaded = Mage::getModel('adminhtml/system_config_backend_encrypted');
        $loaded->load($backend->getId());
        expect($loaded->getValue())->toBe($secret);

        $loaded->delete();
    });

    test('double save does not double-encrypt', function () {
        $path = 'test/encryption/' . uniqid();
        $secret = 'double-save-secret';

        $backend = Mage::getModel('adminhtml/system_config_backend_encrypted');
        $backend->setPath($path);
        $backend->setScope('default');
        $backend->setScopeId(0);
        $backend->setValue($secret);
        $backend->save();
        $backend->save();

        $loaded = Mage::getModel('adminhtml/system_config_backend_encrypted');
        $loaded->load($backend->getId());
        expect($loaded->getValue())->toBe($secret);

        $loaded->delete();
    });

});

describe('AdminActivityLog Activity', function () {
    function createActivity(array $oldData, array $newData): Maho_AdminActivityLog_Model_Activity
    {
        $activity = Mage::getModel('adminactivitylog/activity');
        $activity->setData([
            'action_type' => 'test',
            'object_type' => 'test',
            'object_id' => '1',
            'old_data' => Mage::helper('core')->jsonEncode($oldData),
            'new_data' => Mage::helper('core')->jsonEncode($newData),
        ]);
        $activity->save();
        return $activity;
    }

    test('encrypts data on save and decrypts via getters', function () {
        $oldData = ['name' => 'Old Product', 'price' => '19.99'];
        $newData = ['name' => 'New Product', 'price' => '29.99'];

        $activity = createActivity($oldData, $newData);

        $loaded = Mage::getModel('adminactivitylog/activity')->load($activity->getId());
        expect($loaded->getOldData())->toBe($oldData);
        expect($loaded->getNewData())->toBe($newData);

        $loaded->delete();
    });

    test('double save does not double-encrypt', function () {
        $oldData = ['status' => 'enabled'];
        $newData = ['status' => 'disabled'];

        $activity = createActivity($oldData, $newData);
        $activity->save();

        $loaded = Mage::getModel('adminactivitylog/activity')->load($activity->getId());
        expect($loaded->getOldData())->toBe($oldData);
        expect($loaded->getNewData())->toBe($newData);

        $loaded->delete();
    });

    test('raw data in database is encrypted', function () {
        $oldData = ['secret' => 'sensitive-value'];
        $newData = ['secret' => 'new-sensitive-value'];

        $activity = createActivity($oldData, $newData);

        $resource = Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');
        $row = $read->fetchRow(
            $read->select()
                ->from($resource->getTableName('adminactivitylog/activity'))
                ->where('activity_id = ?', $activity->getId()),
        );

        expect($row['old_data'])->not()->toContain('sensitive-value');
        expect($row['new_data'])->not()->toContain('new-sensitive-value');

        $activity->delete();
    });
});

describe('FeedManager Destination', function () {
    function createDestination(array $config): Maho_FeedManager_Model_Destination
    {
        $destination = Mage::getModel('feedmanager/destination');
        $destination->setName('Test Dest ' . uniqid());
        $destination->setType(Maho_FeedManager_Model_Destination::TYPE_SFTP);
        $destination->setIsEnabled(1);
        $destination->setConfigArray($config);
        $destination->save();
        return $destination;
    }

    test('encrypts config on save and decrypts via getConfigArray', function () {
        $config = ['host' => 'ftp.example.com', 'username' => 'admin', 'password' => 's3cret'];

        $destination = createDestination($config);

        $loaded = Mage::getModel('feedmanager/destination')->load($destination->getId());
        expect($loaded->getConfigArray())->toBe($config);

        $loaded->delete();
    });

    test('double save does not double-encrypt', function () {
        $config = ['host' => 'sftp.example.com', 'username' => 'user', 'password' => 'pass123'];

        $destination = createDestination($config);
        $destination->save();

        $loaded = Mage::getModel('feedmanager/destination')->load($destination->getId());
        expect($loaded->getConfigArray())->toBe($config);

        $loaded->delete();
    });

    test('triple save does not corrupt data', function () {
        $config = ['host' => 'ftp.test.com', 'port' => '22'];

        $destination = createDestination($config);
        $destination->save();
        $destination->save();

        $loaded = Mage::getModel('feedmanager/destination')->load($destination->getId());
        expect($loaded->getConfigArray())->toBe($config);

        $loaded->delete();
    });

    test('raw config in database is encrypted', function () {
        $config = ['host' => 'secret-host.example.com', 'password' => 'my-password'];

        $destination = createDestination($config);

        $resource = Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');
        $row = $read->fetchRow(
            $read->select()
                ->from($resource->getTableName('feedmanager/destination'))
                ->where('destination_id = ?', $destination->getId()),
        );

        expect($row['config'])->not()->toContain('secret-host.example.com');
        expect($row['config'])->not()->toContain('my-password');

        $destination->delete();
    });

    test('getConfigValue works after save and reload', function () {
        $config = ['host' => 'sftp.test.com', 'port' => '2222'];

        $destination = createDestination($config);

        $loaded = Mage::getModel('feedmanager/destination')->load($destination->getId());
        expect($loaded->getConfigValue('host'))->toBe('sftp.test.com');
        expect($loaded->getConfigValue('port'))->toBe('2222');
        expect($loaded->getConfigValue('missing', 'default'))->toBe('default');

        $loaded->delete();
    });
});

describe('PayPal Vault Token', function () {
    beforeEach(function () {
        $this->customer = Mage::getModel('customer/customer');
        $this->customer->setWebsiteId(Mage::app()->getStore()->getWebsiteId());
        $this->customer->setEmail('vault-test-' . uniqid() . '@example.com');
        $this->customer->setFirstname('Test');
        $this->customer->setLastname('Customer');
        $this->customer->setPassword('password123');
        $this->customer->save();
    });

    afterEach(function () {
        $this->customer->delete();
    });

    test('encrypts paypal_token_id on save and decrypts on load', function () {
        $tokenId = 'paypal-vault-token-' . uniqid();

        $token = Mage::getModel('maho_paypal/vault_token');
        $token->setCustomerId($this->customer->getId());
        $token->setPaypalTokenId($tokenId);
        $token->setPaymentSourceType('card');
        $token->setCardLastFour('4242');
        $token->setCardBrand('visa');
        $token->save();

        $loaded = Mage::getModel('maho_paypal/vault_token')->load($token->getId());
        expect($loaded->getPaypalTokenId())->toBe($tokenId);

        $loaded->delete();
    });

    test('double save does not double-encrypt', function () {
        $tokenId = 'paypal-vault-double-' . uniqid();

        $token = Mage::getModel('maho_paypal/vault_token');
        $token->setCustomerId($this->customer->getId());
        $token->setPaypalTokenId($tokenId);
        $token->setPaymentSourceType('paypal');
        $token->save();
        $token->save();

        $loaded = Mage::getModel('maho_paypal/vault_token')->load($token->getId());
        expect($loaded->getPaypalTokenId())->toBe($tokenId);

        $loaded->delete();
    });

    test('hash is consistent across saves', function () {
        $tokenId = 'paypal-hash-test-' . uniqid();
        $expectedHash = hash('sha256', $tokenId);

        $token = Mage::getModel('maho_paypal/vault_token');
        $token->setCustomerId($this->customer->getId());
        $token->setPaypalTokenId($tokenId);
        $token->setPaymentSourceType('card');
        $token->save();

        $resource = Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');
        $row = $read->fetchRow(
            $read->select()
                ->from($resource->getTableName('maho_paypal/vault_token'))
                ->where('token_id = ?', $token->getId()),
        );

        expect($row['paypal_token_id_hash'])->toBe($expectedHash);
        expect($row['paypal_token_id'])->not()->toBe($tokenId);

        $token->save();

        $row2 = $read->fetchRow(
            $read->select()
                ->from($resource->getTableName('maho_paypal/vault_token'))
                ->where('token_id = ?', $token->getId()),
        );

        expect($row2['paypal_token_id_hash'])->toBe($expectedHash);

        $token->delete();
    });
});

describe('Encryption key regeneration (recryptTable)', function () {
    function simulateKeyRegeneration(string $table, string $primaryKey, array $columns): void
    {
        $oldKey = Mage::getEncryptionKeyAsBinary();
        $newKey = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);

        $decryptWithOldKey = function (string $data) use ($oldKey): string {
            $decoded = sodium_base642bin($data, SODIUM_BASE64_VARIANT_ORIGINAL);
            $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $oldKey);
            return $plaintext !== false ? $plaintext : '';
        };

        $encryptWithNewKey = function (string $data) use ($newKey): string {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $ciphertext = sodium_crypto_secretbox($data, $nonce, $newKey);
            return sodium_bin2base64($nonce . $ciphertext, SODIUM_BASE64_VARIANT_ORIGINAL);
        };

        Mage::helper('core')->recryptTable(
            $table,
            $primaryKey,
            $columns,
            $encryptWithNewKey,
            $decryptWithOldKey,
        );

        // Swap the app encryption key to the new one so subsequent decrypts use it
        // We do this by writing to the config XML in memory
        $newKeyHex = sodium_bin2hex($newKey);
        Mage::app()->getConfig()->setNode('global/crypt/key', $newKeyHex);
    }

    test('AdminActivityLog data survives key regeneration', function () {
        $oldData = ['api_key' => 'secret-key-123'];
        $newData = ['api_key' => 'secret-key-456'];

        $activity = Mage::getModel('adminactivitylog/activity');
        $activity->setData([
            'action_type' => 'test',
            'object_type' => 'test',
            'object_id' => '1',
            'old_data' => Mage::helper('core')->jsonEncode($oldData),
            'new_data' => Mage::helper('core')->jsonEncode($newData),
        ]);
        $activity->save();

        simulateKeyRegeneration(
            Mage::getSingleton('core/resource')->getTableName('adminactivitylog/activity'),
            'activity_id',
            ['old_data', 'new_data'],
        );

        $loaded = Mage::getModel('adminactivitylog/activity')->load($activity->getId());
        expect($loaded->getOldData())->toBe($oldData);
        expect($loaded->getNewData())->toBe($newData);

        $loaded->delete();
    });

    test('FeedManager Destination config survives key regeneration', function () {
        $config = ['host' => 'sftp.example.com', 'username' => 'admin', 'password' => 'top-secret'];

        $destination = Mage::getModel('feedmanager/destination');
        $destination->setName('Recrypt Test ' . uniqid());
        $destination->setType(Maho_FeedManager_Model_Destination::TYPE_SFTP);
        $destination->setIsEnabled(1);
        $destination->setConfigArray($config);
        $destination->save();

        simulateKeyRegeneration(
            Mage::getSingleton('core/resource')->getTableName('feedmanager/destination'),
            'destination_id',
            ['config'],
        );

        $loaded = Mage::getModel('feedmanager/destination')->load($destination->getId());
        expect($loaded->getConfigArray())->toBe($config);

        $loaded->delete();
    });

    test('PayPal Vault Token survives key regeneration', function () {
        $tokenId = 'paypal-recrypt-test-' . uniqid();

        $customer = Mage::getModel('customer/customer');
        $customer->setWebsiteId(Mage::app()->getStore()->getWebsiteId());
        $customer->setEmail('recrypt-test-' . uniqid() . '@example.com');
        $customer->setFirstname('Test');
        $customer->setLastname('Customer');
        $customer->setPassword('password123');
        $customer->save();

        $token = Mage::getModel('maho_paypal/vault_token');
        $token->setCustomerId($customer->getId());
        $token->setPaypalTokenId($tokenId);
        $token->setPaymentSourceType('card');
        $token->setCardLastFour('1234');
        $token->save();

        simulateKeyRegeneration(
            Mage::getSingleton('core/resource')->getTableName('maho_paypal/vault_token'),
            'token_id',
            ['paypal_token_id'],
        );

        $loaded = Mage::getModel('maho_paypal/vault_token')->load($token->getId());
        expect($loaded->getPaypalTokenId())->toBe($tokenId);

        $loaded->delete();
        $customer->delete();
    });
});
