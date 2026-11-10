<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

uses(Tests\MahoBackendTestCase::class);

/**
 * The Refresh Models button of the admin and the save of an API key keep the model list of a provider through
 * Maho_Ai_Model_Platform_ModelFetcher::cacheModels() (#1348).
 */

afterEach(function (): void {
    Mage::getModel('core/config')->deleteConfig('ai/models_cache/openai');
    Mage::app()->getCache()->cleanType('config');
});

it('keeps the fetched model list under the cache path that the model dropdown reads', function (): void {
    Mage::app()->getStore()->setConfig('ai/general/openai_api_key', Mage::helper('core')->encrypt('sk-test'));
    $fetcher = new Maho_Ai_Model_Platform_ModelFetcher()->setHttpClient(new MockHttpClient(
        new MockResponse((string) json_encode(['data' => [['id' => 'gpt-test'], ['id' => 'whisper-1']]])),
    ));

    $models = $fetcher->cacheModels('openai');
    Mage::app()->getStore()->resetConfig();
    $stored = Mage::getResourceModel('core/config_data_collection')
        ->addFieldToFilter('path', 'ai/models_cache/openai')
        ->addFieldToFilter('scope', 'default')
        ->getFirstItem()
        ->getValue();

    expect($models)->toBe([['value' => 'gpt-test', 'label' => 'gpt-test']])
        ->and(Mage::helper('core')->jsonDecode((string) $stored))->toBe($models);
});
