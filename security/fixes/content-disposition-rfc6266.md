# Content-Disposition RFC 6266 compliance

Current implementation uses a simple `Content-Disposition: attachment; filename="<name>"` header.

RFC 6266 requires proper handling of non‑ASCII filenames via the `filename*` parameter with UTF‑8 encoding and percent‑encoding.

**Fix**: Update all `setHeader('Content-Disposition', ...)` calls to include a `filename*` fallback. Until the full implementation is in place, a placeholder documentation comment is added.

*File(s) affected*: `app/code/core/Mage/Core/Controller/Varien/Action.php` and any other controllers that set this header.

**TODO**: Implement a helper that builds an RFC‑compliant header and replace direct `setHeader` calls.
