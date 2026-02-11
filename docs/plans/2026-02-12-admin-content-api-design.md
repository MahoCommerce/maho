# Admin Content API Design

**Date:** 2026-02-12
**Status:** Approved
**Scope:** LLM-safe admin API for content management

## Overview

A guardrailed API layer that allows LLMs (and other programmatic clients) to safely create and update CMS content without direct database access. Uses existing OAuth consumer infrastructure with added admin permissions.

## Scope

**In scope (Phase 1):**
- CMS Pages (create, update, delete)
- CMS Blocks / Static Blocks (create, update, delete)
- Blog Posts (create, update, delete)
- Media uploads (images only, auto-convert to WebP)

**Out of scope (future phases):**
- Theme configuration API
- Product/Category content updates
- Layout XML modifications

## API Endpoints

All endpoints require `Authorization: Bearer <consumer_key>:<consumer_secret>` header.

| Method | Endpoint | Purpose |
|--------|----------|---------|
| `POST` | `/api/admin/cms-pages` | Create CMS page |
| `PUT` | `/api/admin/cms-pages/{id}` | Update CMS page |
| `DELETE` | `/api/admin/cms-pages/{id}` | Delete CMS page |
| `POST` | `/api/admin/cms-blocks` | Create static block |
| `PUT` | `/api/admin/cms-blocks/{id}` | Update static block |
| `DELETE` | `/api/admin/cms-blocks/{id}` | Delete static block |
| `POST` | `/api/admin/blog-posts` | Create blog post |
| `PUT` | `/api/admin/blog-posts/{id}` | Update blog post |
| `DELETE` | `/api/admin/blog-posts/{id}` | Delete blog post |
| `POST` | `/api/admin/media` | Upload image |
| `GET` | `/api/admin/media` | List files in folder |
| `DELETE` | `/api/admin/media/{path}` | Delete file |

### Request Headers

```
Authorization: Bearer <consumer_key>:<consumer_secret>
X-Store-Code: default  (required if token has multi-store access)
Content-Type: application/json
```

## Request Payloads

### CMS Page

```json
{
  "identifier": "summer-sale",
  "title": "Summer Sale 2026",
  "contentHeading": "Hot Deals for Summer",
  "content": "<h2>Up to 50% Off</h2><p>Shop our biggest sale...</p>",
  "metaKeywords": "summer, sale, tennis",
  "metaDescription": "Shop our summer sale with up to 50% off tennis gear.",
  "status": "enabled",
  "stores": ["default", "au"]
}
```

### CMS Block

```json
{
  "identifier": "homepage-banner",
  "title": "Homepage Hero Banner",
  "content": "<div class=\"hero\"><img src=\"{{media url=\"wysiwyg/banners/summer.webp\"}}\" alt=\"Summer Sale\"/></div>",
  "status": "enabled",
  "stores": ["default"]
}
```

### Blog Post

```json
{
  "identifier": "summer-tennis-tips",
  "title": "5 Tips for Summer Tennis",
  "shortContent": "Beat the heat with these essential tips...",
  "content": "<p>Full article content here...</p>{{youtube id=\"abc123\"}}",
  "author": "Tennis Pro",
  "status": "enabled",
  "publishedAt": "2026-02-15T09:00:00Z",
  "stores": ["default"]
}
```

### Media Upload

```
POST /api/admin/media
Content-Type: multipart/form-data

file: <binary>
folder: wysiwyg/banners    (optional, defaults to wysiwyg/)
filename: summer-hero      (optional, sanitized or auto-generated)
```

**Response:**
```json
{
  "success": true,
  "url": "/media/wysiwyg/banners/summer-hero.webp",
  "directive": "{{media url=\"wysiwyg/banners/summer-hero.webp\"}}",
  "size": 145832,
  "dimensions": { "width": 1920, "height": 600 }
}
```

### Store Assignment Rules

- `stores` accepts array of store codes
- Use `["all"]` or omit to assign to all stores (if token permits)
- Token must have access to all specified stores, else 403
- On update, can only modify stores the token has access to

## Authentication

### Extend OAuth Consumer Table

Add columns to existing `oauth_consumer` table:

```sql
ALTER TABLE oauth_consumer
  ADD COLUMN store_ids TEXT NULL
    COMMENT 'JSON array of store IDs or "all"',
  ADD COLUMN admin_permissions TEXT NULL
    COMMENT 'JSON: {"cms_pages":true,"cms_blocks":true,"blog_posts":true,"media":true}',
  ADD COLUMN last_used_at DATETIME NULL,
  ADD COLUMN expires_at DATETIME NULL;
```

### Admin UI

In **System → OAuth → Consumers**, add new section:

```
┌─────────────────────────────────────────────────────────┐
│ Admin API Access                                        │
│ ─────────────────                                       │
│ ☑ Enable Admin API Access                               │
│                                                         │
│ Store Access:                                           │
│ ◉ All Stores                                            │
│ ○ Selected Stores: [dropdown multi-select]              │
│                                                         │
│ Permissions:                                            │
│ ☑ CMS Pages (create, update, delete)                    │
│ ☑ CMS Blocks (create, update, delete)                   │
│ ☑ Blog Posts (create, update, delete)                   │
│ ☑ Media Upload (upload, list, delete)                   │
│                                                         │
│ Expires: [ Never ▼ ] or [date picker]                   │
└─────────────────────────────────────────────────────────┘
```

## Content Sanitization

### Allowed HTML Tags

```
h1, h2, h3, h4, h5, h6, p, br, hr,
strong, b, em, i, u, s, small, mark,
a (href only, no javascript:),
img (src, alt, width, height only),
ul, ol, li, dl, dt, dd,
table, thead, tbody, tr, th, td,
div, span, blockquote, pre, code,
figure, figcaption
```

### Allowed Maho Directives

```
{{media url="..."}}      - Reference uploaded media
{{store url="..."}}      - Internal store links
{{config path="..."}}    - Limited to safe paths (store name, contact email)
{{youtube id="..."}}     - YouTube embed (privacy-enhanced)
{{vimeo id="..."}}       - Vimeo embed
```

### Stripped Content

- `<script>`, `<iframe>`, `<object>`, `<embed>`, `<form>`
- Event handlers: `onclick`, `onerror`, `onload`, etc.
- `javascript:` URLs
- `<style>` tags (inline `style=""` allowed but filtered)
- Dangerous directives: `{{block}}`, `{{widget}}`, `{{layout}}`

### Video Directive Output

`{{youtube id="dQw4w9WgXcQ"}}` renders:

```html
<div class="video-embed video-embed--youtube">
  <iframe
    src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"
    frameborder="0"
    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
    allowfullscreen>
  </iframe>
</div>
```

## Media Upload Constraints

| Constraint | Value |
|------------|-------|
| Max file size | 10MB |
| Allowed types | jpg, jpeg, png, gif, webp |
| Output format | WebP (auto-converted) |
| Destination | `wysiwyg/` and subfolders only |
| Filename | Sanitized original or auto-generated |
| Subfolder creation | Allowed (e.g., `wysiwyg/banners/`) |

## Audit Logging

### Extend Existing Table

Add column to `adminactivitylog_activity`:

```sql
ALTER TABLE adminactivitylog_activity
  ADD COLUMN consumer_id INT UNSIGNED NULL AFTER user_id,
  ADD INDEX idx_consumer_id (consumer_id),
  ADD FOREIGN KEY (consumer_id) REFERENCES oauth_consumer(entity_id)
      ON DELETE SET NULL ON UPDATE CASCADE;
```

### Log Entries

| Source | user_id | consumer_id | username |
|--------|---------|-------------|----------|
| Admin UI | 123 | null | "admin" |
| Admin API | null | 456 | "API: Consumer Name" |

### What Gets Logged

| Action | old_data | new_data |
|--------|----------|----------|
| Create | null | Full content JSON |
| Update | Previous state | New state |
| Delete | Previous state | null |

Data is encrypted at rest (existing behavior).

## Error Responses

Standard API Platform error format:

```json
{
  "@context": "/api/contexts/Error",
  "@type": "hydra:Error",
  "hydra:title": "Forbidden",
  "hydra:description": "Token does not have permission for cms_pages"
}
```

| Status | Meaning |
|--------|---------|
| 400 | Invalid payload (validation errors) |
| 401 | Missing or invalid token |
| 403 | Token lacks required permission or store access |
| 404 | Resource not found |
| 413 | File too large (media upload) |
| 415 | Unsupported media type |
| 422 | Content sanitization rejected dangerous content |

## Future Considerations

**Phase 2 (separate PRs):**
- CMS/Block versioning (content history with rollback)
- CMS/Block scheduling (publish_at / unpublish_at)

**Phase 3:**
- Theme configuration API
- Product/Category description updates

## Implementation Notes

- Use HTMLPurifier (already in Maho) for sanitization
- Media uploads go through existing WebP conversion layer
- API processors call existing `adminactivitylog/activity::logActivity()`
- Reuse existing `CmsPage`, `CmsBlock` API resources, add `Post`/`Put`/`Delete` operations
