---
description: Let customers and guests download or delete their personal data (GDPR), and keep the proof of each terms and conditions text that a customer accepted.
admin_pages: [customer_privacy, checkout_agreement/edit, customer/edit]
---

# Privacy Center <span class="version-badge">v27.1+</span>

The Privacy Center answers the requests of the GDPR from the storefront. A customer or a guest asks for a copy of the personal data, or for its erasure. The store keeps the proof of each terms and conditions text that a customer accepted.

!!! warning "Not legal advice"
    The Privacy Center is a tool. It does not make a store compliant by itself. Confirm the periods, the texts and the process with your own counsel.

## Settings

The Privacy Center is on in a new store. Its settings are in **System > Configuration > Customers > Privacy Center**. To turn it off, set **General > Enabled** to **No**.

The Privacy Center needs two background processes:

- The cron of the store. It expires the old links, runs the erasures and deletes the old export files.
- The message queue. It writes the export files.

The customer account has a **Privacy Center** link, and the footer has a **Privacy Request** link to the form for guests at `/privacy/request`.

## What a customer can do

The **Privacy Center** of the customer account has three pages:

- **My Personal Data**: the customer asks for a copy of the data. The export starts at once, because the login proves the owner of the account. The customer gets an email with a download link.
- **Terms and Conditions**: the terms that the customer accepted, with the version and the date. A customer accepts here the registration terms that wait for an acceptance.
- **Delete My Account**: the customer asks for the erasure of the account. An erasure always waits for the link of its confirmation email. The customer can cancel it until it runs.

A guest uses the form at `/privacy/request`. The guest gives an email and the type of request, then confirms the request with the link of an email. A request for the email of a customer account is a request of that customer. The form gives the same answer for any email, so it does not tell who has an account.

## The export

The export is a ZIP file with two files:

- `data.html`, for a person to read.
- `data.json`, for another service to read.

It holds the account, the address book, the orders, the product reviews, the wishlist, the newsletter subscription, the product alerts, the accepted terms, the privacy requests, the revocation requests of the orders and the social login links. It never holds a password or a security token. For a guest, it holds the guest orders, the newsletter subscription, the accepted terms and the revocation requests of the email.

The download link works for the time in **Download Link Lifetime (Hours)**. Then the cron deletes the file.

## The erasure

An erasure runs when each of these conditions is true:

1. The owner of the email confirmed the request.
2. The cooling-off period of **Erasure Cooling-Off Period (Days)** ended.
3. A user approved the erasure, when **Erasures Need Approval** is **Yes**.
4. The customer has no open order. An order is open until it is complete, closed or canceled.

The erasure deletes the customer account with its addresses, wishlist, carts, product alerts, newsletter subscription, saved PayPal payment methods and social login links. It deletes the logs that name the customer. It removes the name of the customer from the reviews, or deletes the reviews, as **Reviews of an Erased Customer** says.

The erasure keeps these records, because the law requires them:

- The orders, the invoices, the shipments and the credit memos.
- The proof of each accepted terms and conditions text.
- The revocation requests, as the receipt of each withdrawal.
- The privacy request itself, as the proof of the erasure.

The customer gets an email when the erasure is complete.

## Customers > Privacy Requests

This grid lists every request. The counters above the grid show the requests that wait for a confirmation, the erasures that wait for an approval, the requests that are open for more than 23 days, and the requests completed in the last 30 days. The GDPR gives the store one month to answer a request.

Open a request to act on it:

- **Approve Erasure**: the erasure runs at the end of its cooling-off period.
- **Export Now** or **Erase Now**: run the request now. **Erase Now** skips the cooling-off period and the open order check. You cannot undo an erasure.
- **Send Email Again**: send a new confirmation link, or a new download link.
- **Download Export**: get the export file, to give it to the customer by another channel.
- **Reject**: refuse the request. Add the reason in a note, and tell the customer.

The **History** box shows each step of the request, with the result of each part of an erasure.

A customer who asks by letter or by phone gets a request from the **Privacy** tab of the customer page. Check the identity of the customer first: the request starts confirmed.

## Versions of the terms and conditions

Each save of **Sales > Terms and Conditions** that changes the name, the checkbox text or the content makes a new version. The store keeps the text of each version, also after you delete the terms. The edit page lists the versions with the number of acceptances of each.

The **Placement and Consent** box of the edit page has three settings:

- **Show in the Checkout**: the customer accepts the terms to place an order.
- **Show in the Registration Form**: the customer accepts the terms to create an account. An existing customer who did not accept them sees a notice in the customer account.
- **Ask Again When the Text Changes**: with **Yes**, a customer who accepted an older version must accept the new version.

The store saves an acceptance in the checkout, in the registration form, in the customer account and through the API. Each acceptance keeps the version, the date, the order, the IP address and the browser of the customer. The **Privacy** tab of the customer page lists them.
