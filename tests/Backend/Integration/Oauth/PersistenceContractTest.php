<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Maho\Db\Adapter\AdapterInterface;

uses(Tests\MahoBackendTestCase::class);

it('stores the nonce without looking up an identity value', function (): void {
    $coreResource = Mage::getSingleton('core/resource');
    $connection = $coreResource->getConnection('core_write');
    $table = $coreResource->getTableName('oauth/nonce');
    $nonce = Mage::getModel('oauth/nonce')->setNonce(bin2hex(random_bytes(16)))->setTimestamp(123);
    $adapter = $this->createMock(AdapterInterface::class);
    $adapter->method('prepareColumnValue')->willReturnCallback($connection->prepareColumnValue(...));
    $adapter->expects($this->once())->method('insert')->willReturnCallback($connection->insert(...));
    $adapter->expects($this->never())->method('lastInsertId');
    $resource = $this->getMockBuilder(Mage_Oauth_Model_Resource_Nonce::class)
        ->onlyMethods(['_getWriteAdapter'])
        ->getMock();
    $resource->method('_getWriteAdapter')->willReturn($adapter);

    $connection->beginTransaction();
    try {
        $resource->save($nonce);
        $storedTimestamp = $connection->fetchOne(
            $connection->select()->from($table, ['timestamp'])->where('nonce = ?', $nonce->getNonce()),
        );

        expect($storedTimestamp)->not->toBeFalse()
            ->and((int) $storedTimestamp)->toBe(123)
            ->and($nonce->getId())->toBeNull();
    } finally {
        $connection->rollBack();
    }
});

it('still reads the generated identity for an ordinary resource', function (): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    try {
        $variable = Mage::getModel('core/variable')->setCode('resource_identity_fixture')->setName('Resource identity fixture');
        $variable->save();

        expect((int) $variable->getId())->toBeGreaterThan(0);
        expect(Mage::getModel('core/variable')->load($variable->getId())->getCode())->toBe('resource_identity_fixture');
    } finally {
        $connection->rollBack();
    }
});

it('maps a rejected request token claim to the OAuth token_used response', function (): void {
    $resource = $this->createMock(Mage_Oauth_Model_Resource_Token::class);
    $resource->expects($this->once())->method('claimRequestToken')->willReturn(false);
    $token = $this->getMockBuilder(Mage_Oauth_Model_Token::class)->onlyMethods(['getResource'])->getMock();
    $token->method('getResource')->willReturn($resource);
    $token->setId(1)->setType(Mage_Oauth_Model_Token::TYPE_REQUEST);

    try {
        $token->convertToAccess();
        $this->fail('Expected the rejected claim to throw.');
    } catch (Mage_Core_Exception $error) {
        $response = new Mage_Core_Controller_Response_Http();
        $body = new Mage_Oauth_Model_Server()->reportProblem($error, $response);
        parse_str($body, $parameters);

        expect($response->getHttpResponseCode())->toBe(401)
            ->and($parameters['oauth_problem'])->toBe('token_used')
            ->and($error)->toBeInstanceOf(Mage_Oauth_Exception::class);
    }
});
