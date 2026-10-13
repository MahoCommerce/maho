<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

uses(Tests\MahoBackendTestCase::class);

describe('Maho_FeedManager_Model_Uploader for the Facebook API', function () {
    it('names the file part after the feed file and not after the local temp file', function (): void {
        $body = '';
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$body): MockResponse {
            $source = $options['body'];
            if ($source instanceof Closure) {
                while (($chunk = $source(16372)) !== '') {
                    $body .= $chunk;
                }
            } else {
                $body = is_iterable($source) ? implode('', iterator_to_array($source, false)) : (string) $source;
            }
            return new MockResponse('{"id":"1"}', ['http_code' => 200]);
        });

        $destination = Mage::getModel('feedmanager/destination')
            ->setData('type', Maho_FeedManager_Model_Destination::TYPE_FACEBOOK_API)
            ->setConfigArray(['catalog_id' => '123', 'access_token' => 'token']);
        $uploader = new class ($destination, $client) extends Maho_FeedManager_Model_Uploader {
            public function __construct(Maho_FeedManager_Model_Destination $destination, private HttpClientInterface $client)
            {
                parent::__construct($destination);
            }

            #[\Override]
            protected function _createHttpClient(int $timeout): HttpClientInterface
            {
                return $this->client;
            }
        };

        $localPath = (string) tempnam(sys_get_temp_dir(), 'maho_feed_');
        file_put_contents($localPath, "id,title\n1,A\n");
        try {
            $success = $uploader->upload($localPath, 'feed.csv');
        } finally {
            unlink($localPath);
        }

        expect($success)->toBeTrue()
            ->and($body)->toContain('filename="feed.csv"')
            ->and($body)->toContain('Content-Type: text/csv')
            ->and($body)->not->toContain(basename($localPath));
    });
});
