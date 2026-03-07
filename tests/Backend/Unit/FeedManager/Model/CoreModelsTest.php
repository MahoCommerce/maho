<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Feed Model', function () {
    beforeEach(function () {
        $this->feed = Mage::getModel('feedmanager/feed');
    });

    test('can create new feed instance', function () {
        expect($this->feed)->toBeInstanceOf(Maho_FeedManager_Model_Feed::class);
        expect($this->feed->getId())->toBeNull();
    });

    test('has configurable mode constants', function () {
        expect(Maho_FeedManager_Model_Feed::CONFIGURABLE_MODE_SIMPLE_ONLY)->toBe('simple_only');
        expect(Maho_FeedManager_Model_Feed::CONFIGURABLE_MODE_CHILDREN_ONLY)->toBe('children_only');
        expect(Maho_FeedManager_Model_Feed::CONFIGURABLE_MODE_BOTH)->toBe('both');
    });

    test('has status constants', function () {
        expect(Maho_FeedManager_Model_Feed::STATUS_ENABLED)->toBe(1);
        expect(Maho_FeedManager_Model_Feed::STATUS_DISABLED)->toBe(0);
    });

    test('getConfigurableModeOptions returns non-empty array with 3 modes', function () {
        $options = Maho_FeedManager_Model_Feed::getConfigurableModeOptions();

        expect($options)->toBeArray();
        expect($options)->toHaveCount(3);
        expect($options)->toHaveKeys([
            Maho_FeedManager_Model_Feed::CONFIGURABLE_MODE_SIMPLE_ONLY,
            Maho_FeedManager_Model_Feed::CONFIGURABLE_MODE_CHILDREN_ONLY,
            Maho_FeedManager_Model_Feed::CONFIGURABLE_MODE_BOTH,
        ]);
    });

    test('can set and get basic attributes', function () {
        $this->feed->setName('Test Feed');
        $this->feed->setPlatform('google');
        $this->feed->setStoreId(1);
        $this->feed->setIsEnabled(1);
        $this->feed->setFilename('test-feed');
        $this->feed->setFileFormat('xml');

        expect($this->feed->getName())->toBe('Test Feed');
        expect($this->feed->getPlatform())->toBe('google');
        expect((int) $this->feed->getStoreId())->toBe(1);
        expect((int) $this->feed->getIsEnabled())->toBe(1);
        expect($this->feed->getFilename())->toBe('test-feed');
        expect($this->feed->getFileFormat())->toBe('xml');
    });

    test('isEnabled returns correct boolean', function () {
        $this->feed->setIsEnabled(1);
        expect($this->feed->isEnabled())->toBeTrue();

        $this->feed->setIsEnabled(0);
        expect($this->feed->isEnabled())->toBeFalse();
    });

    test('can save and load feed', function () {
        $filename = 'test-feed-' . uniqid();
        $this->feed->setName('Test Feed Save');
        $this->feed->setPlatform('google');
        $this->feed->setStoreId(1);
        $this->feed->setIsEnabled(1);
        $this->feed->setFilename($filename);
        $this->feed->setFileFormat('xml');
        $this->feed->save();

        expect($this->feed->getId())->toBeGreaterThan(0);
        expect($this->feed->getCreatedAt())->not()->toBeEmpty();
        expect($this->feed->getUpdatedAt())->not()->toBeEmpty();

        $loaded = Mage::getModel('feedmanager/feed')->load($this->feed->getId());
        expect($loaded->getName())->toBe('Test Feed Save');
        expect($loaded->getPlatform())->toBe('google');
        expect($loaded->getFilename())->toBe($filename);

        $loaded->delete();
    });

    test('getConditions returns Combine instance', function () {
        $conditions = $this->feed->getConditions();
        expect($conditions)->toBeInstanceOf(Maho_FeedManager_Model_Rule_Condition_Combine::class);
    });

    test('getForm returns Form instance', function () {
        $form = $this->feed->getForm();
        expect($form)->toBeInstanceOf(Maho\Data\Form::class);
    });

    test('getOutputFilePath returns path with correct extension', function () {
        $this->feed->setFilename('my-feed');
        $this->feed->setFileFormat('xml');

        $path = $this->feed->getOutputFilePath();
        expect($path)->toEndWith('my-feed.xml');
    });

    test('getOutputUrl returns URL with correct extension', function () {
        $this->feed->setFilename('my-feed');
        $this->feed->setFileFormat('csv');

        $url = $this->feed->getOutputUrl();
        expect($url)->toEndWith('my-feed.csv');
    });

    test('getAttributeMappings returns a collection instance', function () {
        $mappings = $this->feed->getAttributeMappings();
        expect($mappings)->toBeInstanceOf(Maho_FeedManager_Model_Resource_AttributeMapping_Collection::class);
    });

    test('getLogs returns a collection instance', function () {
        $logs = $this->feed->getLogs();
        expect($logs)->toBeInstanceOf(Maho_FeedManager_Model_Resource_Log_Collection::class);
    });

    test('beforeSave strips feed format extensions from filename', function () {
        $this->feed->setName('Extension Strip Test');
        $this->feed->setPlatform('google');
        $this->feed->setStoreId(1);
        $this->feed->setIsEnabled(1);
        $this->feed->setFilename('my-feed.xml');
        $this->feed->setFileFormat('xml');
        $this->feed->save();

        expect($this->feed->getFilename())->toBe('my-feed');

        $this->feed->delete();
    });
});

describe('Log Model', function () {
    beforeEach(function () {
        $this->log = Mage::getModel('feedmanager/log');
    });

    test('can create new log instance', function () {
        expect($this->log)->toBeInstanceOf(Maho_FeedManager_Model_Log::class);
        expect($this->log->getId())->toBeNull();
    });

    test('has status constants', function () {
        expect(Maho_FeedManager_Model_Log::STATUS_RUNNING)->toBe('running');
        expect(Maho_FeedManager_Model_Log::STATUS_COMPLETED)->toBe('completed');
        expect(Maho_FeedManager_Model_Log::STATUS_FAILED)->toBe('failed');
    });

    test('has upload status constants', function () {
        expect(Maho_FeedManager_Model_Log::UPLOAD_STATUS_PENDING)->toBe('pending');
        expect(Maho_FeedManager_Model_Log::UPLOAD_STATUS_SUCCESS)->toBe('success');
        expect(Maho_FeedManager_Model_Log::UPLOAD_STATUS_FAILED)->toBe('failed');
        expect(Maho_FeedManager_Model_Log::UPLOAD_STATUS_SKIPPED)->toBe('skipped');
    });

    test('can save and load log with feed_id and status', function () {
        // Create a feed first
        $feed = Mage::getModel('feedmanager/feed');
        $feed->setName('Log Test Feed');
        $feed->setPlatform('google');
        $feed->setStoreId(1);
        $feed->setIsEnabled(1);
        $feed->setFilename('log-test-' . uniqid());
        $feed->setFileFormat('xml');
        $feed->save();

        $startedAt = Mage_Core_Model_Locale::now();
        $this->log->setFeedId((int) $feed->getId());
        $this->log->setStatus(Maho_FeedManager_Model_Log::STATUS_RUNNING);
        $this->log->setStartedAt($startedAt);
        $this->log->save();

        expect($this->log->getId())->toBeGreaterThan(0);

        $loaded = Mage::getModel('feedmanager/log')->load($this->log->getId());
        expect((int) $loaded->getFeedId())->toBe((int) $feed->getId());
        expect($loaded->getStatus())->toBe(Maho_FeedManager_Model_Log::STATUS_RUNNING);
        expect($loaded->getStartedAt())->toBe($startedAt);

        $loaded->delete();
        $feed->delete();
    });

    test('getErrorsArray returns empty array when no errors', function () {
        expect($this->log->getErrorsArray())->toBe([]);
    });
});

describe('Destination Model', function () {
    beforeEach(function () {
        $this->destination = Mage::getModel('feedmanager/destination');
    });

    test('can create new destination instance', function () {
        expect($this->destination)->toBeInstanceOf(Maho_FeedManager_Model_Destination::class);
        expect($this->destination->getId())->toBeNull();
    });

    test('has type constants', function () {
        expect(Maho_FeedManager_Model_Destination::TYPE_SFTP)->toBe('sftp');
        expect(Maho_FeedManager_Model_Destination::TYPE_FTP)->toBe('ftp');
        expect(Maho_FeedManager_Model_Destination::TYPE_GOOGLE_API)->toBe('google_api');
        expect(Maho_FeedManager_Model_Destination::TYPE_FACEBOOK_API)->toBe('facebook_api');
    });

    test('has status constants', function () {
        expect(Maho_FeedManager_Model_Destination::STATUS_SUCCESS)->toBe('success');
        expect(Maho_FeedManager_Model_Destination::STATUS_FAILED)->toBe('failed');
    });

    test('can set and get attributes', function () {
        $this->destination->setName('Test Destination');
        $this->destination->setType('sftp');
        $this->destination->setIsEnabled(1);

        expect($this->destination->getName())->toBe('Test Destination');
        expect($this->destination->getType())->toBe('sftp');
        expect((int) $this->destination->getIsEnabled())->toBe(1);
    });

    test('getConfigArray returns empty array when config is null', function () {
        expect($this->destination->getConfigArray())->toBe([]);
    });

    test('getConfigArray returns empty array when config is empty string', function () {
        $this->destination->setConfig('');
        expect($this->destination->getConfigArray())->toBe([]);
    });
});

describe('DynamicRule Model', function () {
    beforeEach(function () {
        $this->rule = Mage::getModel('feedmanager/dynamicRule');
    });

    test('can create new dynamic rule instance', function () {
        expect($this->rule)->toBeInstanceOf(Maho_FeedManager_Model_DynamicRule::class);
        expect($this->rule->getId())->toBeNull();
    });

    test('has output type constants', function () {
        expect(Maho_FeedManager_Model_DynamicRule::OUTPUT_TYPE_STATIC)->toBe('static');
        expect(Maho_FeedManager_Model_DynamicRule::OUTPUT_TYPE_ATTRIBUTE)->toBe('attribute');
        expect(Maho_FeedManager_Model_DynamicRule::OUTPUT_TYPE_COMBINED)->toBe('combined');
    });

    test('can set and get attributes', function () {
        $this->rule->setName('Test Rule');
        $this->rule->setCode('test_rule');
        $this->rule->setIsEnabled(1);

        expect($this->rule->getName())->toBe('Test Rule');
        expect($this->rule->getCode())->toBe('test_rule');
        expect((int) $this->rule->getIsEnabled())->toBe(1);
    });

    test('getForm returns Form instance', function () {
        $form = $this->rule->getForm();
        expect($form)->toBeInstanceOf(Maho\Data\Form::class);
    });
});
