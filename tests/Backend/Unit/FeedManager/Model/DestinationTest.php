<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('FeedManager Destination Model', function () {
    beforeEach(function () {
        $this->destination = Mage::getModel('feedmanager/destination');
    });

    test('can create new destination instance', function () {
        expect($this->destination)->toBeInstanceOf(Maho_FeedManager_Model_Destination::class);
        expect($this->destination->getId())->toBeNull();
    });

    test('has correct type constants', function () {
        expect(Maho_FeedManager_Model_Destination::TYPE_SFTP)->toBe('sftp');
        expect(Maho_FeedManager_Model_Destination::TYPE_FTP)->toBe('ftp');
        expect(Maho_FeedManager_Model_Destination::TYPE_GOOGLE_API)->toBe('google_api');
        expect(Maho_FeedManager_Model_Destination::TYPE_FACEBOOK_API)->toBe('facebook_api');
    });

    test('can set and get config as array', function () {
        $config = [
            'host' => 'sftp.example.com',
            'port' => '22',
            'username' => 'testuser',
        ];

        $this->destination->setConfigArray($config);
        $retrieved = $this->destination->getConfigArray();

        expect($retrieved)->toBeArray();
        expect($retrieved['host'])->toBe('sftp.example.com');
        expect($retrieved['port'])->toBe('22');
    });

    test('can get specific config value', function () {
        $this->destination->setConfigArray([
            'host' => 'sftp.example.com',
            'port' => '22',
        ]);

        expect($this->destination->getConfigValue('host'))->toBe('sftp.example.com');
        expect($this->destination->getConfigValue('port'))->toBe('22');
        expect($this->destination->getConfigValue('nonexistent', 'default'))->toBe('default');
    });

    test('can set specific config value', function () {
        $this->destination->setConfigArray(['host' => 'old.example.com']);
        $this->destination->setConfigValue('host', 'new.example.com');
        $this->destination->setConfigValue('port', '22');

        expect($this->destination->getConfigValue('host'))->toBe('new.example.com');
        expect($this->destination->getConfigValue('port'))->toBe('22');
    });

    test('returns type options for dropdown', function () {
        $options = Maho_FeedManager_Model_Destination::getTypeOptions();

        expect($options)->toBeArray();
        expect($options)->toHaveKey('sftp');
        expect($options)->toHaveKey('ftp');
        expect($options)->toHaveKey('google_api');
        expect($options)->toHaveKey('facebook_api');
    });

    test('returns required config fields per type', function () {
        $sftpFields = Maho_FeedManager_Model_Destination::getRequiredConfigFields('sftp');
        expect($sftpFields)->toContain('host');
        expect($sftpFields)->toContain('username');
        expect($sftpFields)->toContain('remote_path');

        $ftpFields = Maho_FeedManager_Model_Destination::getRequiredConfigFields('ftp');
        expect($ftpFields)->toContain('host');
        expect($ftpFields)->toContain('password');

        $googleFields = Maho_FeedManager_Model_Destination::getRequiredConfigFields('google_api');
        expect($googleFields)->toContain('merchant_id');
    });

    test('validates config correctly', function () {
        $this->destination->setType('sftp');
        $this->destination->setConfigArray([
            'host' => 'sftp.example.com',
            'port' => '22',
            'username' => 'user',
            'auth_type' => 'password',
            'password' => 'pass',
            'remote_path' => '/',
        ]);

        $errors = $this->destination->validateConfig();
        expect($errors)->toBe([]);
    });

    test('validation fails for missing required fields', function () {
        $this->destination->setType('sftp');
        $this->destination->setConfigArray([
            'host' => 'sftp.example.com',
            // Missing: username, auth_type, remote_path
        ]);

        $errors = $this->destination->validateConfig();
        expect(count($errors))->toBeGreaterThan(0);
    });

    test('can save and load destination', function () {
        $this->destination->setName('Test SFTP');
        $this->destination->setType('sftp');
        $this->destination->setConfigArray(['host' => 'test.example.com']);
        $this->destination->setIsEnabled(1);
        $this->destination->save();

        expect($this->destination->getId())->toBeGreaterThan(0);

        $loaded = Mage::getModel('feedmanager/destination')->load($this->destination->getId());
        expect($loaded->getName())->toBe('Test SFTP');
        expect($loaded->getType())->toBe('sftp');
        expect($loaded->getConfigValue('host'))->toBe('test.example.com');

        // Cleanup
        $loaded->delete();
    });
});
