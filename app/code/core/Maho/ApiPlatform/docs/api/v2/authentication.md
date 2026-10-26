---
description: "Authenticate to the Maho v2 API with JWT bearer tokens: customer login, OAuth2 client_credentials, and legacy api_user grant types, plus token refresh."
---

# Authentication <span class="version-badge">v26.7+</span>

### JWT Token Authentication

All authenticated endpoints require a Bearer token in the `Authorization` header.

**Get a token:**

The endpoint dispatches by `grant_type`. Supported grants: `customer` (default), `client_credentials`, `api_user`.

!!! note "Which grant should I use?"
    Use `client_credentials` for new integrations. The `api_user` grant is a **transitional bridge**: it authenticates the API user accounts defined in the admin under **System > Web Services** (the accounts used by the SOAP-era APIs, part of the now-deprecated `Mage_Api` module) and issues a JWT carrying that user's ACL role permissions. It lets existing integrations move to the v2 API without re-provisioning credentials, but it will be retired together with the legacy API modules - plan to migrate those accounts to `client_credentials` service accounts.

=== "curl"

    ```bash
    # Customer login (grant_type defaults to "customer")
    curl -X POST /api/rest/v2/auth/token \
      -H 'Content-Type: application/json' \
      -d '{"email": "customer@example.com", "password": "password123"}'

    # OAuth2 client_credentials (recommended for integrations)
    curl -X POST /api/rest/v2/auth/token \
      -H 'Content-Type: application/json' \
      -d '{"grant_type": "client_credentials", "client_id": "...", "client_secret": "..."}'

    # Legacy API user (username + api_key)
    curl -X POST /api/rest/v2/auth/token \
      -H 'Content-Type: application/json' \
      -d '{"grant_type": "api_user", "username": "admin", "api_key": "..."}'
    ```

=== "JavaScript"

    ```javascript
    // Customer login (grant_type defaults to "customer")
    let res = await fetch('/api/rest/v2/auth/token', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: 'customer@example.com', password: 'password123' })
    });
    let data = await res.json();

    // OAuth2 client_credentials (recommended for integrations)
    res = await fetch('/api/rest/v2/auth/token', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ grant_type: 'client_credentials', client_id: '...', client_secret: '...' })
    });
    data = await res.json();

    // Legacy API user (username + api_key)
    res = await fetch('/api/rest/v2/auth/token', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ grant_type: 'api_user', username: 'admin', api_key: '...' })
    });
    data = await res.json();
    ```

=== "Python"

    ```python
    import requests

    # Customer login (grant_type defaults to "customer")
    res = requests.post('/api/rest/v2/auth/token',
        json={'email': 'customer@example.com', 'password': 'password123'})
    data = res.json()

    # OAuth2 client_credentials (recommended for integrations)
    res = requests.post('/api/rest/v2/auth/token',
        json={'grant_type': 'client_credentials', 'client_id': '...', 'client_secret': '...'})
    data = res.json()

    # Legacy API user (username + api_key)
    res = requests.post('/api/rest/v2/auth/token',
        json={'grant_type': 'api_user', 'username': 'admin', 'api_key': '...'})
    data = res.json()
    ```

**Response:**
```json
{
  "token": "eyJ...",
  "token_type": "Bearer",
  "expires_in": 3600,
  "customer": {"id": 1, "email": "...", "firstName": "...", "lastName": "..."}
}
```

`token_type` and `expires_in` follow the OAuth 2.0 (RFC 6749) snake_case convention; the other fields use the API's usual camelCase. `customer` is populated for the `customer` grant; `apiUser` and `permissions` are populated for `client_credentials` / `api_user` grants. There is no separate `refresh_token` field, call `/auth/refresh` with the existing JWT in the `Authorization` header to get a new token.

**Use the token:**

=== "curl"

    ```bash
    curl /api/rest/v2/products \
      -H 'Authorization: Bearer eyJ...'
    ```

=== "JavaScript"

    ```javascript
    const res = await fetch('/api/rest/v2/products', {
      headers: { 'Authorization': 'Bearer eyJ...' }
    });
    const data = await res.json();
    ```

=== "Python"

    ```python
    import requests

    res = requests.get('/api/rest/v2/products',
        headers={'Authorization': 'Bearer eyJ...'})
    data = res.json()
    ```

**Refresh a token:** send the current (still-valid) JWT as a Bearer token; the body is ignored.

=== "curl"

    ```bash
    curl -X POST /api/rest/v2/auth/refresh \
      -H 'Authorization: Bearer eyJ...'
    ```

=== "JavaScript"

    ```javascript
    const res = await fetch('/api/rest/v2/auth/refresh', {
      method: 'POST',
      headers: { 'Authorization': 'Bearer eyJ...' }
    });
    const data = await res.json();
    ```

=== "Python"

    ```python
    import requests

    res = requests.post('/api/rest/v2/auth/refresh',
        headers={'Authorization': 'Bearer eyJ...'})
    data = res.json()
    ```

**Other auth endpoints:**
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/rest/v2/auth/logout` | Revoke the current token |

Password reset and "current customer" live under the Customer resource, see [Customers](endpoints.md#customers).

### Permission Levels

| Level | Access |
|-------|--------|
| **Public** | Store config, countries, categories, products, CMS, blog |
| **Customer** | Own cart, orders, addresses, wishlist, reviews |
| **Admin / API** | All resources, CRUD on products, orders, inventory, coupons, credit memos, shipments |
