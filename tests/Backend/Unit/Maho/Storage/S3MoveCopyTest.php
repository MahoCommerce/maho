<?php

/**
 * Moves and copies on an S3 mount that MountRegistry builds, against a mocked client that answers like Cloudflare R2.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Storage
 */

declare(strict_types=1);

use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use GuzzleHttp\Psr7\Response;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToMoveFile;
use Maho\Storage\Mount;
use Maho\Storage\MountDefinition;
use Maho\Storage\MountRegistry;

/**
 * Builds the s3 mount that local.xml declares with $xml, and logs each command that its client sends.
 * GetObjectAcl fails with 501, as on Cloudflare R2, which has no object ACLs.
 *
 * @param list<string> $sent
 */
function storageS3MockMount(string $xml, array &$sent): Mount
{
    $node = new Mage_Core_Model_Config_Element(
        "<media>$xml<adapter><type>s3</type><bucket>b</bucket><region>auto</region><key>k</key><secret>s</secret></adapter></media>",
    );
    $definition = MountDefinition::fromElement('media', $node, fn(string $type = 'base'): string => sys_get_temp_dir());
    $mount = new ReflectionMethod(MountRegistry::class, 'build')->invoke(null, $definition);
    $adapter = new ReflectionProperty(Mount::class, 'adapter')->getValue($mount);
    $client = new ReflectionProperty(AwsS3V3Adapter::class, 'client')->getValue($adapter);

    $respond = function (CommandInterface $command) use (&$sent): Result|S3Exception {
        $sent[] = $command->getName() . (isset($command['ACL']) ? " ACL={$command['ACL']}" : '');
        if ($command->getName() === 'GetObjectAcl') {
            return new S3Exception('Not Implemented', $command, ['code' => 'NotImplemented', 'response' => new Response(501)]);
        }
        return new Result($command->getName() === 'HeadObject' ? ['ContentLength' => 1] : []);
    };
    $client->getHandlerList()->setHandler(new MockHandler(array_fill(0, 5, $respond)));

    return $mount->withoutChangeEvents();
}

describe('Maho\Storage\Mount on S3 without object ACLs', function () {
    beforeEach(function (): void {
        if (!class_exists(AwsS3V3Adapter::class)) {
            $this->markTestSkipped('league/flysystem-aws-s3-v3 is not installed');
        }
    });

    it('moves and copies without reading the source ACL when local.xml asks for it', function (string $xml, string $acl): void {
        $sent = [];
        $mount = storageS3MockMount($xml, $sent);
        $mount->move('tmp/a.jpg', 'a.jpg');
        $mount->copy('a.jpg', 'b.jpg');

        expect($sent)->toBe(['HeadObject', "CopyObject ACL=$acl", 'DeleteObject', 'HeadObject', "CopyObject ACL=$acl"]);
    })->with([
        'retain_visibility 0' => ['<retain_visibility>0</retain_visibility>', 'private'],
        'visibility public, retain_visibility 0' => ['<visibility>public</visibility><retain_visibility>0</retain_visibility>', 'public-read'],
    ]);

    it('reads the source ACL by default', function (string $xml): void {
        $sent = [];
        $mount = storageS3MockMount($xml, $sent);

        expect(fn() => $mount->move('tmp/a.jpg', 'a.jpg'))->toThrow(UnableToMoveFile::class)
            ->and(fn() => $mount->copy('tmp/a.jpg', 'a.jpg'))->toThrow(UnableToCopyFile::class)
            ->and($sent)->toBe(['GetObjectAcl', 'GetObjectAcl']);
    })->with([
        'neither element' => [''],
        'visibility public' => ['<visibility>public</visibility>'],
        'visibility public, retain_visibility 1' => ['<visibility>public</visibility><retain_visibility>1</retain_visibility>'],
    ]);
})->group('storage-s3');
