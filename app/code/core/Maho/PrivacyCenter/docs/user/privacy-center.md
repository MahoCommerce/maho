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

- **My Personal Data**: the customer asks for a copy of the data, and confirms the request with the link of an email. The link proves the email, so the export also holds the guest orders of that email. The customer then gets an email with a download link.
- **Terms and Conditions**: the terms that the customer accepted, with the version and the date. A customer accepts here the registration terms that wait for an acceptance.
- **Delete My Account**: the customer asks for the erasure of the account. An erasure always waits for the link of its confirmation email. The customer can cancel it until it runs.

A guest uses the form at `/privacy/request`. The guest gives an email and the type of request, then confirms the request with the link of an email. A request for the email of a customer account is a request of that customer. The form gives the same answer for any email, so it does not tell who has an account.

## The export

The export is a ZIP file with two files:

- `data.html`, for a person to read.
- `data.json`, for another service to read.

It holds the account, the address book, the orders, the product reviews, the wishlist, the newsletter subscription, the product alerts, the accepted terms, the privacy requests, the revocation requests, the social login links and the gift cards that the customer sent or received. The orders, the newsletter subscriptions and the revocation requests include the ones of the email without an account. It never holds a password, a security token or the code of a gift card. A gift card that the customer sent shows no balance, because the balance belongs to the recipient. For a guest, it holds the guest orders, the newsletter subscription, the accepted terms, the revocation requests and the gift cards of the email.

The download link works for the time in **Download Link Lifetime (Hours)**. Then the cron deletes the file.

## The erasure

An erasure runs when each of these conditions is true:

1. The owner of the email confirmed the request.
2. The cooling-off period of **Erasure Cooling-Off Period (Days)** ended.
3. A user approved the erasure, when **Erasures Need Approval** is **Yes**.
4. The customer has no open order. An order is open until it is complete, closed or canceled.

The erasure deletes the customer account with its addresses, wishlist, carts, product alerts, newsletter subscription, saved PayPal payment methods and social login links. It deletes the gift messages of the carts, the visits of the customer with their IP address and browser, and the other logs that name the customer. It removes the name of the customer from the reviews, or deletes the reviews, as **Reviews of an Erased Customer** says.

The erasure also removes the data that no law requires from the records that it keeps:

- On the orders, it removes the IP address, the birth date and the gender.
- On the gift cards that the customer sent or received, it removes the name, the email and the message of the customer. The code and the balance stay, so the card keeps its value. A card whose email is not sent yet keeps its data until the email is sent.
- On the PayPal payment notifications that the store keeps, it removes the content of each notification that names the email.

The erasure keeps these records, because the law requires them:

- The orders, the invoices, the shipments and the credit memos, with the names, the email, the addresses, the phone and the VAT number. The orders also keep the values of the custom customer attributes, because a store can use one for invoice data, such as a fiscal code.
- The proof of each accepted terms and conditions text.
- The revocation requests, as the receipt of each withdrawal.
- The privacy request itself, as the proof of the erasure.

The customer gets an email when the erasure is complete.

## The other rights of the GDPR

The Privacy Center handles the right of access, the right to data portability (the `data.json` file of the export) and the right to erasure. The store answers the other rights with the pages that it already has:

- **Rectification**: the customer corrects the name, the email and the addresses in the customer account. A user corrects the other data, such as the address of an order, in the admin.
- **Objection to direct marketing**: the customer unsubscribes from the newsletter in the customer account, or with the link in each newsletter.
- **Withdrawal of a consent**: a terms and conditions text is the contract of an order or of an account, not a consent. The customer ends the contract of the account with an erasure. Do not use a terms and conditions text to ask for a consent that the customer can withdraw, such as for marketing. Use the newsletter subscription for marketing.

A customer who has another request, such as an objection to other processing, writes to the store. Answer it within one month, as for the other requests.

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
