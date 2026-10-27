---
description: Why the tax in the cart can differ from the tax in the order - which address Maho uses for the tax and when, the default tax destination, prices including or excluding tax, and the calculation settings.
admin_pages: [tax_rule, tax_rate, tax_class_customer, tax_class_product, sales_order/view]
---

# Tax in the Cart and in the Order

The tax of a cart depends on an address. Before the customer enters an address, the store does not
know where the order goes, so it uses a replacement address. When the customer enters the real
address at checkout, the store calculates the tax again. This is the most common reason why the
order shows a different tax than the cart.

The order keeps the tax that the store calculated at the moment the customer placed it.

## Where the tax rates come from

| Menu | What you set |
|------|--------------|
| **Sales > Tax > Manage Tax Zones & Rates** | The rates, for a country, a state and a post code |
| **Sales > Tax > Manage Tax Rules** | Which rates apply to which customer tax class and product tax class |
| **Sales > Tax > Customer Tax Classes** | The tax classes of customers. Each customer group has one, in its **Tax Class** field |
| **Sales > Tax > Product Tax Classes** | The tax classes of products. Each product has one, in its **Tax Class** field |

A rate applies when the address, the customer tax class and the product tax class all match a tax
rule.

## Which address the store uses

Go to **System > Configuration > Sales > Tax > Calculation Settings** and look at
**Tax Calculation Based On**:

| Value | Address |
|-------|---------|
| Shipping Address | The shipping address of the cart. This is the default |
| Billing Address | The billing address of the cart |
| Shipping Origin | The address of the store, from **System > Configuration > Sales > Shipping Settings > Origin** |

With **Shipping Address** or **Billing Address**, the store looks for the address in this order:

1. The address of that type in the cart, if it has a country.
2. If the customer is logged in: the default address of that type in the customer account
   (default shipping address or default billing address), if it has a country.
3. The **Default Tax Destination Calculation** address: **Default Country**, **Default State**
   and **Default Post Code**, in **System > Configuration > Sales > Tax**.

A cart that contains only virtual or downloadable products has no shipping address. The store then
calculates the tax on the billing address of the cart, with the same steps.

The customer tax class comes from the customer group of the customer. A guest uses the tax class
of the **NOT LOGGED IN** customer group.

## Why the cart can differ from the order

| Moment | Address that the store uses for a guest | Address that the store uses for a logged-in customer |
|--------|-----------------------------------------|------------------------------------------------------|
| Product page and category page | Default Tax Destination | Default address of the customer account, else Default Tax Destination |
| Cart, before an address | Default Tax Destination | Default address of the customer account, else Default Tax Destination |
| Cart, after **Estimate Shipping and Tax** | The country, state and post code of the estimate | The same |
| Checkout, after the address step | The address that the customer entered | The address that the customer selected or entered |
| Order | The address of the checkout | The address of the checkout |

So when the Default Tax Destination is in one country or state, and the customer enters an address
in another, the tax changes at checkout. The **Default Country** is United States out of the box.
Set it to the country where most of your customers live.

The tax can also change at checkout when **Enable Automatic Assignment to Customer Group** is on in
**System > Configuration > Customers > Customer Configuration > Create New Account Options**. The
store then checks the VAT number of the address and can move the customer to another customer group.
The new group can have another tax class.

## Prices including or excluding tax

In **Calculation Settings**:

| Setting | What it does |
|---------|--------------|
| Catalog Prices | **Excluding Tax**: the prices that you enter are net prices. **Including Tax**: the prices that you enter contain the tax |
| Shipping Prices | The same choice for the shipping amounts |
| Enable Cross Border Trade | With prices including tax: when Yes, every customer pays the same gross price, whatever their tax rate |

When **Catalog Prices** is **Including Tax** and **Enable Cross Border Trade** is No, the store reads
the price that you entered as a price with the tax rate of the **Shipping Origin**. For a customer
with another tax rate, the store takes that tax out and adds the tax rate of the customer. So the
gross price changes with the address of the customer, and it can change at checkout.

With **Enable Cross Border Trade** set to Yes, the gross price stays the same, and only the share
of tax in it changes.

What the customer sees is a separate choice, in **Price Display Settings** (**Display Product
Prices In Catalog**, **Display Shipping Prices**), **Shopping Cart Display Settings** and **Orders,
Invoices, Credit Memos Display Settings**. These settings change how prices show, not the tax
amount.

## Calculation settings

| Setting | Options | What it does |
|---------|---------|--------------|
| Tax Class for Shipping | A product tax class | The tax class of the shipping amount. Without a class, shipping has no tax. This setting is in the **Tax Classes** group |
| Tax Calculation Method Based On | Unit Price, Row Total, Total (default) | When the store rounds the tax: for each unit, for each cart line, or on the total. The results can differ by a cent |
| Tax Calculation Based On | Shipping Address, Billing Address, Shipping Origin | The address of the tax (see above) |
| Apply Customer Tax | Before Discount, After Discount (default) | If the store calculates the tax on the price before or after the discount |
| Apply Discount On Prices | Excluding Tax, Including Tax | If the discount applies to the net price or to the gross price |
| Apply Tax On | Custom price if available, Original price only | For an order that an admin user creates with a custom price, which price the tax uses |

## Troubleshooting

| What you see | Why | What to do |
|--------------|-----|------------|
| The order has a different tax than the cart | The cart used the Default Tax Destination or the default address of the customer. The order used the address of the checkout | This is expected. Set the **Default Country**, **Default State** and **Default Post Code** to the place of most of your customers |
| The cart shows no tax at all | No tax rule matches the Default Tax Destination | Check the **Default Country** and the tax rules, or ask customers to use **Estimate Shipping and Tax** |
| A logged-in customer sees a different tax than a guest | The store uses the default address of the customer account, and the tax class of the customer group | Check the default address and the customer group of the customer |
| The gross price changes at checkout, with prices including tax | **Enable Cross Border Trade** is No, so the store applies the rate of the customer | Set **Enable Cross Border Trade** to Yes if every customer must pay the same gross price |
| The tax changed after the customer entered a VAT number | **Enable Automatic Assignment to Customer Group** moved the customer to another group | Check the groups in **Create New Account Options** and their tax classes |
| Shipping has no tax | **Tax Class for Shipping** is empty | Select a product tax class for shipping, and add a tax rule for it |
| The order tax and the sum of the line taxes differ by a cent | The rounding of **Tax Calculation Method Based On** | Use the same method for all your stores. **Total** rounds once on the total |
| A downloadable product has the tax of the wrong country | The cart has no shipping address, so the store uses the billing address | Check the billing address of the order |

## Related

- [Scope](scope.md) for tax settings per website and store view.
