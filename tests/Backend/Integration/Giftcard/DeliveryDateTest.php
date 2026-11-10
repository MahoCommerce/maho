<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A purchased card with a delivery date after today gets its email from the cron on that date. The invoice
 * activates the card at once. Maho_Giftcard_Service_Giftcard::sendToRecipient() decides (#1348).
 */

/**
 * Pay an invoice of a new order of the store 1 with one gift card of $buyRequest, and return the card.
 *
 * @param array<string, mixed> $buyRequest
 */
function giftcardDeliveryInvoice(array $buyRequest): Maho_Giftcard_Model_Giftcard
{
    $order = Mage::getModel('sales/order')->setStoreId(1)->setBaseToOrderRate(1.0)
        ->setIncrementId('PEST-GCD-' . uniqid())->setBaseCurrencyCode('USD')->setOrderCurrencyCode('USD');
    $order->save();
    test()->orders[] = $order;
    $orderItem = Mage::getModel('sales/order_item')->setOrderId($order->getId())->setProductType('giftcard')
        ->setSku('PEST-GCD')->setName('Gift Card')->setQtyOrdered(1)
        ->setProductOptions(['info_buyRequest' => $buyRequest + [
            'giftcard_amount' => 30, 'giftcard_recipient_email' => 'friend@example.com', 'giftcard_recipient_name' => 'Friend',
        ]]);
    $orderItem->save();
    $invoice = new class extends Mage_Sales_Model_Order_Invoice {
        /** @var list<Mage_Sales_Model_Order_Invoice_Item> */
        public array $items = [];

        #[\Override]
        public function getAllItems()
        {
            return $this->items;
        }
    };
    $invoice->setOrder($order)->setState(Mage_Sales_Model_Order_Invoice::STATE_PAID);
    $invoice->items = [Mage::getModel('sales/order_invoice_item')->setOrderItem($orderItem)->setQty(1)];

    Mage::getModel('giftcard/observer')->createGiftcardsOnInvoicePaid(new \Maho\Event\Observer([
        'event' => new \Maho\Event(['invoice' => $invoice]),
    ]));

    return Mage::getModel('giftcard/giftcard')->getCollection()
        ->addFieldToFilter('purchase_order_id', $order->getId())->getFirstItem();
}

/**
 * @return list<array{subject: string, from: string, recipients: array<mixed>, event: ?string}> the emails of the card
 *         $giftcard that the queue got in this test
 */
function giftcardDeliveryEmails(Maho_Giftcard_Model_Giftcard $giftcard): array
{
    $emails = [];
    foreach (fetchQueueRows() as $row) {
        if ((int) $row['message_id'] <= test()->lastMessageId || $row['message_class'] !== Mage_Core_Model_Email_SendMessage::class) {
            continue;
        }
        $message = \Maho\Queue\QueueManager::serializer()->decode([
            'body' => (string) $row['body'],
            'headers' => ['type' => (string) $row['message_class']],
        ])->getMessage();
        if ($message->entityType === 'giftcard' && $message->entityId === (int) $giftcard->getId()) {
            $emails[] = ['subject' => $message->subject, 'from' => $message->fromName, 'recipients' => $message->recipients, 'event' => $message->eventType];
        }
    }
    return $emails;
}

function giftcardDeliveryReload(Maho_Giftcard_Model_Giftcard $giftcard): Maho_Giftcard_Model_Giftcard
{
    return Mage::getModel('giftcard/giftcard')->load($giftcard->getId());
}

/**
 * Move the scheduled time of $giftcard to one minute ago, as when its delivery date comes.
 */
function giftcardDeliveryMakeDue(Maho_Giftcard_Model_Giftcard $giftcard): void
{
    $resource = Mage::getSingleton('core/resource');
    $resource->getConnection('core_write')->update(
        $resource->getTableName('giftcard/giftcard'),
        ['email_scheduled_at' => agoUtc(60)],
        ['giftcard_id = ?' => $giftcard->getId()],
    );
}

function giftcardDeliveryStoreDate(string $modify): string
{
    return Mage::app()->getLocale()->utcToStore(1)->modify($modify)->format(Mage_Core_Model_Locale::DATE_FORMAT);
}

beforeEach(function (): void {
    $this->orders = [];
    $this->lastMessageId = (int) max(array_column(fetchQueueRows(), 'message_id') ?: [0]);
});

afterEach(function (): void {
    foreach ($this->orders as $order) {
        foreach (Mage::getModel('giftcard/giftcard')->getCollection()->addFieldToFilter('purchase_order_id', $order->getId()) as $giftcard) {
            $giftcard->delete();
        }
        $order->delete();
    }
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
});

describe('a card with a delivery date after today', function (): void {
    it('is active at invoice, gets no email then, and waits for the start of the date in the store timezone', function (): void {
        $date = giftcardDeliveryStoreDate('+3 days');

        $giftcard = giftcardDeliveryReload(giftcardDeliveryInvoice(['giftcard_delivery_date' => $date]));

        $expected = Mage::app()->getLocale()->storeToUtc(1, "{$date} 00:00:00")->format(Mage_Core_Model_Locale::DATETIME_FORMAT);
        expect($giftcard->getStatus())->toBe(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE)
            ->and($giftcard->getEmailSentAt())->toBeNull()
            ->and($giftcard->getData('email_scheduled_at'))->toBe($expected)
            ->and(giftcardDeliveryEmails($giftcard))->toBe([]);
    });

    it('gets no email from the cron before the date', function (): void {
        $giftcard = giftcardDeliveryInvoice(['giftcard_delivery_date' => giftcardDeliveryStoreDate('+1 day')]);

        Mage::getModel('giftcard/cron')->processScheduledEmails();

        expect(giftcardDeliveryEmails($giftcard))->toBe([])
            ->and(giftcardDeliveryReload($giftcard)->getEmailSentAt())->toBeNull();
    });

    it('gets from the cron on its date the email that an immediate send gives, in the store of the order', function (): void {
        $immediate = giftcardDeliveryInvoice([]);
        $scheduled = giftcardDeliveryInvoice(['giftcard_delivery_date' => giftcardDeliveryStoreDate('+1 day')]);
        expect(giftcardDeliveryEmails($scheduled))->toBe([]);
        giftcardDeliveryMakeDue($scheduled);

        // The cron runs in the admin store, not in the store of the order
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
        Mage::getModel('giftcard/cron')->processScheduledEmails();

        $emails = giftcardDeliveryEmails($scheduled);
        expect($emails)->toHaveCount(1)
            ->and($emails)->toBe(giftcardDeliveryEmails($immediate))
            ->and($emails[0]['from'])->toBe(Mage::getStoreConfig('trans_email/ident_general/name', 1))
            ->and(giftcardDeliveryReload($scheduled)->getEmailSentAt())->not->toBeNull();

        Mage::getModel('giftcard/cron')->processScheduledEmails();
        expect(giftcardDeliveryEmails($scheduled))->toHaveCount(1);
    });

    it('gets no second email from the cron when the admin sent it by hand before the date', function (): void {
        $giftcard = giftcardDeliveryInvoice(['giftcard_delivery_date' => giftcardDeliveryStoreDate('+1 day')]);
        Mage::getService('giftcard/giftcard')->sendEmail(giftcardDeliveryReload($giftcard), 1);
        giftcardDeliveryMakeDue($giftcard);

        Mage::getModel('giftcard/cron')->processScheduledEmails();

        expect(giftcardDeliveryEmails($giftcard))->toHaveCount(1);
    });
});

it('emails the card at invoice when the delivery date is not after today', function (?string $modify): void {
    $giftcard = giftcardDeliveryReload(giftcardDeliveryInvoice(
        $modify === null ? [] : ['giftcard_delivery_date' => giftcardDeliveryStoreDate($modify)],
    ));

    expect($giftcard->getEmailSentAt())->not->toBeNull()
        ->and($giftcard->getData('email_scheduled_at'))->toBeNull()
        ->and(giftcardDeliveryEmails($giftcard))->toHaveCount(1);
})->with([
    'no date' => [null],
    'today' => ['today'],
    'a past date' => ['-2 days'],
]);

it('emails the card at invoice when an older order holds a delivery date that is not valid', function (): void {
    $giftcard = giftcardDeliveryReload(giftcardDeliveryInvoice(['giftcard_delivery_date' => 'next friday']));

    expect($giftcard->getEmailSentAt())->not->toBeNull()
        ->and($giftcard->getData('email_scheduled_at'))->toBeNull();
});
