---
description: How to add a field to customers and customer addresses - customer and address attributes, the forms that show them, attribute sets per customer group, website settings, and the checkout.
admin_pages: [customer_attribute, customer_set, customer_address_attribute, customer_address_set, customer_group]
---

# Customer and Address Attributes

A customer attribute is a field of a customer, such as **Date of Birth**. An address attribute is a
field of an address, such as **Company**. You can add your own attributes, for example a company
registration number for business customers, or delivery hours for an address.

## Add an attribute

1. Go to **Customers > Attributes > Manage Customer Attributes**, or to **Manage Address
   Attributes** for an address field.
2. Click **Add New Attribute**.
3. Enter the **Attribute Code** and select the **Input Type**. You cannot change them later. An
   address attribute can also use **Multiple Lines**, with the number of lines in **Lines Count**.
4. In **Used in Forms**, select the forms that show the field. Hold the Ctrl or Cmd key to select
   more than one form.
5. On the **Manage Label / Options** tab, enter the label, and the options of a Dropdown or a
   Multiple Select.
6. Click **Save**.

The forms of a customer attribute:

| Form | Where it shows |
|---|---|
| Admin Customer | The **Account Information** tab of a customer in the admin |
| Admin Order Creation | The account part of a new order in the admin |
| Customer Registration | The registration page of the storefront |
| Customer Account Edit | **Account Information** in the customer account |
| Checkout Registration | The account part of the checkout, when the customer registers there |

The forms of an address attribute:

| Form | Where it shows |
|---|---|
| Admin Customer Address | The addresses of a customer, and the addresses of a new order, in the admin |
| Address Book and Checkout | The address book of the customer account, and the billing and shipping steps of the checkout |
| Customer Registration | The address part of the registration page, when the store asks for an address there |

**Show in Forms** set to **No** hides the field in every form. **Sort Order** sets the place of the
field in a form.

## Input rules

The values that a customer enters must follow the **Input Rules** of the attribute:

- **Input Filter**: **Strip HTML Tags** removes HTML from the value. **Escape HTML Entities** keeps
  the tags as text.
- **Minimum Text Length** and **Maximum Text Length**: the number of characters of a text.
- **Value Format**: letters, numbers, letters and numbers, an email address, or a URL.

## Fields for each customer group

An attribute set is a list of attributes. Each customer group uses one customer set and one address
set. A group with no set uses the default set of the entity.

For example, wholesale customers give a company registration number, and retail customers do not:

1. Go to **Customers > Attributes > Customer Attribute Sets** and click **Add**.
2. Enter **Wholesale**, select **Default** in **Based On**, and click **Save**.
3. Drag the new attribute from **Unassigned Attributes** to a group of the **Wholesale** set, and
   click **Save**.
4. Go to **Customers > Customer Groups** and open the **Wholesale** group.
5. Select **Wholesale** in **Customer Attribute Set**, and click **Save**.

A form shows an attribute when the attribute is in the set of the group, or when it is in no set.
So a new attribute shows to every group until you add it to a set.

The store finds the group of a form in this order:

1. The group of the customer.
2. For an address in the checkout, the group of the cart. A guest has the **NOT LOGGED IN** group.
3. For a customer who registers, the **Default Group** in **System > Configuration > Customers >
   Customer Configuration > Create New Account Options**. This setting can be different for each
   store view, so a business website can give its new customers a business group and its fields.

When a customer moves to another group, the values of the fields of the old set stay in the
database. The customer account edit page shows the fields of the new set.

## Fields for each website

The **Websites** part of an attribute can change the default for one website:

- **Hidden**: the forms of the website do not show the field.
- **Optional**: the forms show the field, and the customer can leave it empty.
- **Required**: the forms show the field, and the customer must fill it in.
- **Use Default**: the website uses **Show in Forms** and **Values Required** of the attribute.

Use a website setting when the field depends on the website, and an attribute set when it depends
on the customer group.

## Address attributes on orders

The checkout copies the values of the address attributes to the order:

- The cart keeps the value that the customer selected.
- The order keeps the label of a **Dropdown**, a **Multiple Select** or a **Yes/No** option. So the order
  shows what the customer saw, also after you change or delete the option.
- A reorder from the admin copies the values back to the new cart.

Each address attribute adds a column with its **Attribute Code** to the order addresses. So the code of
an address attribute cannot be the name of a field that orders already have, such as `subtotal`. When you
delete an address attribute, the orders keep its values.

An order email or an address template shows a value with `{{var attribute_code}}`.

## The API

The REST, GraphQL and MCP APIs read and write the values in `customAttributes`, keyed by attribute code.
Only an admin token or an API token can see this field. A write follows the **Input Rules** of the
attribute, and the attribute must be in the **Admin Customer** or the **Admin Customer Address** form.
