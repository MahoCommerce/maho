---
description: Let customers buy without a customer account - the Allow Guest Checkout setting, its scope, the rule for carts with downloadable products, and what a guest can do after the order.
admin_pages: [system_config/edit]
---

# Guest Checkout

With guest checkout, a customer can place an order without a customer account. The customer
enters an email address and the addresses at checkout, and the order has no customer account.

## Turning guest checkout on or off

Go to **System > Configuration > Sales > Checkout**, group **Checkout Options**, and set
**Allow Guest Checkout**. With Yes (the default), a customer can check out as a guest. With No,
the customer must register or log in.

The setting has the scope **[STORE VIEW]**, so each store view can have its own value (see
[Scope](scope.md)). When guest checkout seems on in **Default Config** but a store view still asks
for an account, check the value of that store view and of its website.

## Carts with downloadable products

Go to **System > Configuration > Catalog > Catalog**, group **Downloadable Product Options**, and
look at **Disable Guest Checkout if Cart Contains Downloadable Items**. This setting has the
scope **[WEBSITE]**.

| Value | What happens |
|-------|--------------|
| Yes (the default) | A cart that holds at least one downloadable product needs a customer account, also when Allow Guest Checkout is Yes. The Shareable setting of the links does not change this |
| No | A guest can buy a downloadable product. After the order, the guest can download only the links that are **Shareable**. A link that is not shareable needs a logged-in customer with the same account as the order, and a guest order has no account |

So with the value No, set the links of your downloadable products to Shareable, or guests pay for
files that they cannot download.

## Customer accounts turned off

**System > Configuration > Customers > Customer Configuration > Account Options > Enable Customer
Accounts in Frontend** turns off registration, login and the account pages of the storefront. Its
help text says that only guest checkout is then available, so keep **Allow Guest Checkout** set to
Yes in the same scope.

## After a guest order

- The order has no customer account. In **Sales > Orders**, the customer group of the order is
  NOT LOGGED IN.
- The guest finds the order on the **Orders and Returns** page of the storefront, with the order
  number, the billing last name and the email address or the post code.
- The tax of a guest uses the tax class of the NOT LOGGED IN customer group (see
  [Tax in the cart and in the order](tax-calculation.md)).

## Troubleshooting

| What you see | Why | What to do |
|--------------|-----|------------|
| The checkout asks for an account, but Allow Guest Checkout is Yes | The cart holds a downloadable product, and **Disable Guest Checkout if Cart Contains Downloadable Items** is Yes | Set that setting to No, and make the links Shareable |
| Guest checkout works in one store view and not in another | The other store view, or its website, has its own value of **Allow Guest Checkout** | Select that scope in **Current Configuration Scope** and check the value |
| A guest cannot download a file that they bought | The link was not Shareable when the guest bought it. The order keeps the Shareable value that the link had at the purchase | For the next orders, set the link to Shareable on the **Downloadable Information** tab of the product. This does not change an order that is already placed. A link with the value Use config reads **Shareable** in **System > Configuration > Catalog > Catalog**, group **Downloadable Product Options**, at each download. For any other link, send the file to the customer another way |

## Related

- [Scope](scope.md) for values per website and store view.
- [Tax in the cart and in the order](tax-calculation.md) for the tax of guests.
