---
description: The state and the status of an order, how an order moves from New to Complete, and which orders still need a shipment.
admin_pages: [sales_order, sales_order_shipment, sales_order_invoice, sales_order_creditmemo]
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

1. A new order is in the state New, with the status Pending, unless the payment method sends the
   customer to a payment provider.
2. An invoice or a shipment moves a New order to Processing.
3. When nothing is left to invoice and nothing is left to ship, the order becomes Complete.
4. A credit memo for the full amount makes the order Closed.

## Which orders need a shipment

The **Ship** button shows on an order that has items left to ship. The order must not be canceled,
on hold or in payment review, and it must have items that need a shipment: a virtual or a
downloadable item needs none.

So the orders that wait for a shipment are in the state New, Pending Payment or Processing. A New
order, with the status Pending, has no invoice yet: the store lets you ship it, but the payment is
not recorded. A Pending Payment order waits for the payment provider: the store lets you ship it
too, but the payment is not confirmed. Check the payment before you ship either of them.

To see them, go to **Sales > Orders** and filter the **Status** column. A Processing order can
already have a shipment for some items: open it and check the **Shipments** tab.

## What the store refuses

The admin and the API apply the same rules to an order and to its documents:

- **Cancel**, **Hold** and **Unhold** show an error when the state of the order does not allow the
  action. For example, an order with an invoice for every item cannot be canceled.
- A comment on an order needs a text, unless it changes the status.
- An invoice cannot have a quantity that is more than the quantity that is left to invoice. A
  quantity with decimals is refused for an item that does not accept one.
- When the payment method ships the order with its invoice, the store refuses a shipment by itself.
  Use the **Invoice and Ship** button instead.
- Each tracking number needs a number and a carrier.
- A credit memo must refund more than zero, and not more than the amount that the order can still
  refund. A refund from an invoice uses an invoice of the same order.
