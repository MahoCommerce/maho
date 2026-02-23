<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

uses(Tests\MahoBackendTestCase::class);

/**
 * Helper function to create a real Post operation for testing
 */
function createPostOperation(string $name): \ApiPlatform\Metadata\Post
{
    return new \ApiPlatform\Metadata\Post(name: $name);
}

describe('Newsletter API - Subscription', function (): void {
    beforeEach(function (): void {
        // Clean up test subscribers
        $testEmails = [
            'newsletter-test@example.com',
            'newsletter-test2@example.com',
            'invalid-email',
        ];

        foreach ($testEmails as $email) {
            $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($email);
            if ($subscriber->getId()) {
                $subscriber->delete();
            }
        }
    });

    describe('Subscribe Operation', function (): void {
        it('subscribes a new email address', function (): void {
            $processor = new \Maho\ApiPlatform\State\Processor\NewsletterProcessor(
                $this->createMock(\Symfony\Bundle\SecurityBundle\Security::class),
            );

            $dto = new \Maho\ApiPlatform\ApiResource\NewsletterSubscription();
            $dto->email = 'newsletter-test@example.com';

            $operation = createPostOperation('subscribe');

            $result = $processor->process($dto, $operation, [], []);

            expect($result)->toBeInstanceOf(\Maho\ApiPlatform\ApiResource\NewsletterSubscription::class);
            expect($result->email)->toBe('newsletter-test@example.com');
            expect($result->status)->toBeIn(['subscribed', 'not_active']);
            expect($result->message)->not->toBeNull();
        });

        it('handles already subscribed email', function (): void {
            // First subscribe
            $subscriber = Mage::getModel('newsletter/subscriber');
            $subscriber->subscribe('newsletter-test@example.com');

            // Try to subscribe again
            $processor = new \Maho\ApiPlatform\State\Processor\NewsletterProcessor(
                $this->createMock(\Symfony\Bundle\SecurityBundle\Security::class),
            );

            $dto = new \Maho\ApiPlatform\ApiResource\NewsletterSubscription();
            $dto->email = 'newsletter-test@example.com';

            $operation = createPostOperation('subscribe');

            $result = $processor->process($dto, $operation, [], []);

            expect($result->isSubscribed)->toBeTrue();
            expect($result->message)->toContain('already subscribed');
        });

        it('rejects invalid email address', function (): void {
            $processor = new \Maho\ApiPlatform\State\Processor\NewsletterProcessor(
                $this->createMock(\Symfony\Bundle\SecurityBundle\Security::class),
            );

            $dto = new \Maho\ApiPlatform\ApiResource\NewsletterSubscription();
            $dto->email = 'invalid-email';

            $operation = createPostOperation('subscribe');

            expect(fn() => $processor->process($dto, $operation, [], []))
                ->toThrow(\Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class);
        });

        it('rejects empty email address', function (): void {
            $processor = new \Maho\ApiPlatform\State\Processor\NewsletterProcessor(
                $this->createMock(\Symfony\Bundle\SecurityBundle\Security::class),
            );

            $dto = new \Maho\ApiPlatform\ApiResource\NewsletterSubscription();
            $dto->email = '';

            $operation = createPostOperation('subscribe');

            expect(fn() => $processor->process($dto, $operation, [], []))
                ->toThrow(\Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class);
        });
    });

    describe('Unsubscribe Operation', function (): void {
        it('unsubscribes an existing subscriber', function (): void {
            // First subscribe
            $subscriber = Mage::getModel('newsletter/subscriber');
            $subscriber->subscribe('newsletter-test@example.com');

            // Now unsubscribe
            $processor = new \Maho\ApiPlatform\State\Processor\NewsletterProcessor(
                $this->createMock(\Symfony\Bundle\SecurityBundle\Security::class),
            );

            $dto = new \Maho\ApiPlatform\ApiResource\NewsletterSubscription();
            $dto->email = 'newsletter-test@example.com';

            $operation = createPostOperation('unsubscribe');

            $result = $processor->process($dto, $operation, [], []);

            expect($result->isSubscribed)->toBeFalse();
            expect($result->status)->toBe('unsubscribed');
            expect($result->message)->toContain('unsubscribed');
        });

        it('handles unsubscribe for non-existent email', function (): void {
            $processor = new \Maho\ApiPlatform\State\Processor\NewsletterProcessor(
                $this->createMock(\Symfony\Bundle\SecurityBundle\Security::class),
            );

            $dto = new \Maho\ApiPlatform\ApiResource\NewsletterSubscription();
            $dto->email = 'nonexistent@example.com';

            $operation = createPostOperation('unsubscribe');

            $result = $processor->process($dto, $operation, [], []);

            expect($result->isSubscribed)->toBeFalse();
            expect($result->status)->toBe('unsubscribed');
            expect($result->message)->toContain('not subscribed');
        });
    });
});

describe('Newsletter API - Status Provider', function (): void {
    it('maps subscriber status correctly', function (): void {
        $provider = new \Maho\ApiPlatform\State\Provider\NewsletterProvider(
            $this->createMock(\Symfony\Bundle\SecurityBundle\Security::class),
        );

        // Use reflection to test private method
        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('mapSubscriberStatus');
        $method->setAccessible(true);

        expect($method->invoke($provider, Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED))
            ->toBe('subscribed');
        expect($method->invoke($provider, Mage_Newsletter_Model_Subscriber::STATUS_NOT_ACTIVE))
            ->toBe('not_active');
        expect($method->invoke($provider, Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED))
            ->toBe('unsubscribed');
        expect($method->invoke($provider, Mage_Newsletter_Model_Subscriber::STATUS_UNCONFIRMED))
            ->toBe('unconfirmed');
        expect($method->invoke($provider, 999))
            ->toBe('unknown');
    });
});

describe('NewsletterSubscription DTO', function (): void {
    it('has correct default values', function (): void {
        $dto = new \Maho\ApiPlatform\ApiResource\NewsletterSubscription();

        expect($dto->email)->toBeNull();
        expect($dto->customerId)->toBeNull();
        expect($dto->status)->toBe('');
        expect($dto->isSubscribed)->toBeFalse();
        expect($dto->message)->toBeNull();
        expect($dto->confirmationRequired)->toBeFalse();
    });

    it('accepts all property values', function (): void {
        $dto = new \Maho\ApiPlatform\ApiResource\NewsletterSubscription();
        $dto->email = 'test@example.com';
        $dto->customerId = 123;
        $dto->status = 'subscribed';
        $dto->isSubscribed = true;
        $dto->message = 'Success message';
        $dto->confirmationRequired = true;

        expect($dto->email)->toBe('test@example.com');
        expect($dto->customerId)->toBe(123);
        expect($dto->status)->toBe('subscribed');
        expect($dto->isSubscribed)->toBeTrue();
        expect($dto->message)->toBe('Success message');
        expect($dto->confirmationRequired)->toBeTrue();
    });
});

describe('Newsletter Subscriber Model Integration', function (): void {
    beforeEach(function (): void {
        $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail('integration-test@example.com');
        if ($subscriber->getId()) {
            $subscriber->delete();
        }
    });

    afterEach(function (): void {
        $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail('integration-test@example.com');
        if ($subscriber->getId()) {
            $subscriber->delete();
        }
    });

    it('can subscribe and unsubscribe using Mage model', function (): void {
        // Subscribe
        $subscriber = Mage::getModel('newsletter/subscriber');
        $status = $subscriber->subscribe('integration-test@example.com');

        expect($status)->toBeIn([
            Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED,
            Mage_Newsletter_Model_Subscriber::STATUS_NOT_ACTIVE,
        ]);

        // Load and verify
        $loaded = Mage::getModel('newsletter/subscriber')->loadByEmail('integration-test@example.com');
        expect($loaded->getId())->not->toBeNull();
        expect($loaded->getSubscriberEmail())->toBe('integration-test@example.com');

        // Unsubscribe
        $loaded->setCheckCode($loaded->getCode()); // Required for unsubscribe without email confirmation
        $loaded->unsubscribe();

        // Verify unsubscribed
        $unsubscribed = Mage::getModel('newsletter/subscriber')->loadByEmail('integration-test@example.com');
        expect($unsubscribed->getSubscriberStatus())
            ->toBe(Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED);
    });

    it('loadByEmail returns empty model for non-existent email', function (): void {
        $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail('does-not-exist@example.com');

        expect($subscriber->getId())->toBeNull();
        expect($subscriber->getSubscriberEmail())->toBeNull();
    });
});
