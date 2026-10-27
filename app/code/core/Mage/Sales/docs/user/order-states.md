---
description: The state and the status of an order, how an order moves from Pending to Complete, and which orders still need a shipment.
admin_pages: [sales_order, sales_order_shipment, sales_order_invoice]
---

# Order States

Each order has a **state** and a **status**. The state is the step of the order in its life, and the
store sets it. The status is the label that the admin and the customer see. Each status belongs to
one state, and a state can have more than one status. The states are the same in every store. The
statuses are not: in **System > Order Statuses**, you can add a status and assign it to a state. So
to find the orders of one step, look at the state, not at the status.

## The states

| State | Status in a new store | What it means |
|-------|-----------------------|---------------|
| New | Pending | The order is placed. It has no invoice and no shipment |
| Pending Payment | Pending Payment | The customer is at the payment provider, and the payment is not confirmed |
| Processing | Processing | The order has an invoice or a shipment, and some work is still open |
| Complete | Complete | Every item is invoiced and shipped |
| Closed | Closed | The order is refunded with a credit memo |
| Canceled | Canceled | The order is canceled |
| On Hold | On Hold | An administrator stopped the order with **Hold** |
| Payment Review | Payment Review, Suspected Fraud | The payment provider asks for a check of the payment |

## How an order moves

1. A new order is Pending, unless the payment method sends the customer to a payment provider.
2. An invoice or a shipment moves a Pending order to Processing.
3. When nothing is left to invoice and nothing is left to ship, the order becomes Complete.
4. A credit memo for the full amount makes the order Closed.

## Which orders need a shipment

The **Ship** button shows on an order that has items left to ship. The order must not be canceled,
on hold or in payment review, and it must have items that need a shipment: a virtual or a
downloadable item needs none.

So the orders that wait for a shipment are in the state Pending, Pending Payment or Processing. A
Pending order has no invoice yet: the store lets you ship it, but the payment is not recorded. A
Pending Payment order waits for the payment provider: the store lets you ship it too, but the
payment is not confirmed. Check the payment before you ship either of them.

To see them, go to **Sales > Orders** and filter the **Status** column. A Processing order can
already have a shipment for some items: open it and check the **Shipments** tab.
