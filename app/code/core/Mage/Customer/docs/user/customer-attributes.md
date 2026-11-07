---
description: How to add a field to customers and customer addresses - customer and address attributes, the forms that show them, attribute sets per customer group, website settings, and the checkout.
admin_pages: [eav_attribute, eav_set, customer_group]
---

# Customer and Address Attributes

A customer attribute is a field of a customer, such as **Date of Birth**. An address attribute is a
field of an address, such as **Company**. You can add your own attributes, for example a company
registration number for business customers, or delivery hours for an address.

## Add an attribute

1. Go to **System > Attributes > Customers**, or to **Customer Addresses** for an address
   field.
2. Click **Add**.
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

## Check where a field shows

Three settings decide if a customer sees a field: the forms of the attribute, the attribute set of
the customer group, and the mode of the website. The edit page of a saved attribute starts with a
summary of the three:

- The forms that show the attribute.
- The customer groups whose set holds the attribute, and the groups that do not see it. An
  attribute in no set shows to every group.
- The mode of each website: **Hidden**, **Optional** or **Required**.

When a customer does not see a field, read this summary first.

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

1. Go to **System > Attributes > Customers**, click **Attribute Sets**, and click **Add**.
2. Enter **Wholesale**, select **Default** in **Based On**, and click **Save**.
3. Drag the new attribute from **Unassigned Attributes** to a group of the **Wholesale** set, and
   click **Save**.
4. Go to **Customers > Customer Groups** and open the **Wholesale** group.
5. Select **Wholesale** in **Customer Attribute Set**, and click **Save**.

A form shows an attribute when the attribute is in the set of the group, or when it is in no set.
So a new attribute shows to every group until you add it to a set.

The set page lists only the attributes that you added. The fields of the platform, such as
**First Name** and **Email**, are in every set, and you cannot remove them.

The groups of the set divide the forms:

- The attributes of the first group of the set, and the attributes in no set, show with the fields
  of the platform.
- Each other group of the set that holds a field of the form shows as a part with its own title,
  for example **Company Information**. The title is the name of the group in the store view of the
  customer. The admin customer page shows each group as a part too, with its Admin name.

The set page says this too, and it names the first group of the set.

## Name a group for each store view

1. On the set page, click a group and click **Rename**, or click **Add** to make a group.
2. Enter the name in **Admin**. The admin uses it, and it must be unique in the set.
3. To show another name in a store view, enter it in the row of that store view. A store view with
   no name shows the Admin name.
4. Click **Save** in the window, then click **Save** on the set page.

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
- **Use Default**: the website uses **Show in Forms** and **Values Required** of the attribute. The
  option shows the result, for example **Use Default (Optional)**.

A store with one website does not show the **Websites** part, because the default already decides.

Use a website setting when the field depends on the website, and an attribute set when it depends
on the customer group.

## Address attributes on orders

The checkout copies the values of the address attributes to the order:

- The cart and the order keep the value that the customer selected.
- The order also keeps the label of a **Dropdown**, a **Multiple Select** or a **Yes/No** option, in the
  language of the store view of the order. So the order shows what the customer saw, also after you
  change or delete the option.
- A reorder from the admin copies the values back to the new cart.

Each address attribute adds a column with its **Attribute Code** to the order addresses. So the code of
an address attribute cannot be the name of a field that orders already have, such as `subtotal`. When you
delete an address attribute, the carts and the orders keep its values. So a new address attribute cannot
use the code of a deleted one.

The order page in the admin, the order emails, the invoices and the other PDFs show each address
attribute that has a value, as a line with its label, for example **Delivery Hours: 9-17**. An
attribute that only the **Admin Customer Address** form shows stays out, because the emails go to the
customer. To put a value in another place, add `{{var attribute_code}}` to the address template in
**System > Configuration > Customers > Customer Configuration > Address Templates**. The store then
does not add the line.

## The API

The REST, GraphQL and MCP APIs read and write the values in `customAttributes`, keyed by attribute code.
A **Dropdown** is an option ID, a **Multiple Select** a list of option IDs, and a **Yes/No** `true` or
`false`. A write follows the **Input Rules** of the attribute. The order addresses return the option IDs
too, and `customAttributeLabels` returns the labels that the customer saw.

An admin token or an API token reads and writes the attributes of the **Admin Customer** and the
**Admin Customer Address** forms. `GET /api/rest/v2/custom-attributes?entityType=customer` and
`?entityType=customer_address` list the attributes, their options and their attribute sets.

A customer token, and a guest who registers or checks out, reads and writes only the attributes that
the storefront forms show to the customer group on the website, as on the storefront:

- A registration takes the attributes of **Customer Registration**, and must send each required one.
- An update of the customer takes the attributes of **Customer Account Edit**.
- An address of the address book and an address of the cart take the attributes of **Address Book and
  Checkout**. A new address of the address book must send each required one.
- A cart address, set with the address endpoints of the cart or in the body of the place-order
  request, must send each required one, because the checkout sets the whole address at once. On the
  cart, `sameAsShipping` copies the values of the shipping address to the billing address. The order
  copies the values from the cart.

`GET /api/rest/v2/customer-forms/{form}` and the GraphQL `customerForm` query list the fields of a
storefront form for the customer group of the caller: the label in the store view, the input type, the
options, the required flag and the group title. The form is `customer_account_create`,
`customer_account_edit`, `checkout_register`, `customer_address_edit` or `customer_register_address`.
