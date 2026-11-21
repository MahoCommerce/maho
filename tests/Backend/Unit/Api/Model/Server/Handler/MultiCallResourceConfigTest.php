<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

class MultiCallConfigTestResource extends Mage_Api_Model_Resource_Abstract
{
    public function inspect(): bool
    {
        return isset($this->_resourceConfig);
    }
}

it('provides the same resource configuration to call and multiCall', function () {
    $xml = simplexml_load_string(
        '<resources><test><model>' . MultiCallConfigTestResource::class . '</model><public>1</public>'
        . '<methods><inspect><public>1</public></inspect></methods></test></resources>',
        \Maho\Simplexml\Element::class,
    );
    $config = new class ($xml) {
        public function __construct(private \Maho\Simplexml\Element $resources) {}

        public function getResourcesAlias(): \Maho\Simplexml\Element
        {
            return new \Maho\Simplexml\Element('<aliases/>');
        }

        public function getResources(): \Maho\Simplexml\Element
        {
            return $this->resources;
        }
    };
    $session = new class {
        public function isLoggedIn(string $sessionId): bool
        {
            return true;
        }
    };
    $handler = new class ($config, $session) extends Mage_Api_Model_Server_Handler_Abstract {
        public function __construct(private object $config, private object $session) {}

        #[\Override]
        protected function _getConfig()
        {
            return $this->config;
        }

        #[\Override]
        protected function _getSession()
        {
            return $this->session;
        }

        #[\Override]
        protected function _startSession($sessionId = null)
        {
            return $this;
        }

        #[\Override]
        protected function _instaLogin(&$sessionId)
        {
            return $this;
        }

        #[\Override]
        protected function _fault($faultName, $resourceName = null, $customMessage = null)
        {
            throw new RuntimeException($faultName . ': ' . (string) $customMessage);
        }
    };

    expect($handler->call('session', 'test.inspect'))->toBeTrue()
        ->and($handler->multiCall('session', [['test.inspect', []]]))->toBe([true]);
});
