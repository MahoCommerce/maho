---
description: Use Redis for both cache and session storage in Maho out of the box via symfony/cache, configured in local.xml with no third-party plugin needed.
---

# Redis (cache & sessions)

Since [Maho 25.5](../blog/posts/2025-05-15-maho-25.5-announcement.md) adds a completely new cache subsystem
based on [symfony/cache](https://symfony.com/doc/current/components/cache.html){target=_blank}, Maho
supports Redis for both cache and session storage out of the box, no 3rd party plugin needed.

## Cache

If you want to enable it, and you've a Redis database available, simply head out to your `local.xml` file
and configure the `global/cache` section this way:

```xml
<global>
    <cache>
        <lifetime>86400</lifetime> <!-- optional, default is 7200 -->
        <backend>redis</backend> <!-- optional, default is file -->
        <backend_options> <!-- optional for "file", required for "redis" -->
            <dsn>redis://localhost/0</dsn> <!-- TCP connection, database 0 -->
        </backend_options>
    </cache>
</global>
```

The `dsn` element supports many possible options, check out of the
[full documentation at Symfony's website](https://symfony.com/doc/current/components/cache/adapters/redis_adapter.html#configure-the-connection){target=_blank}.
For example, using a **Unix socket** (often faster than TCP on the same host):

```xml
<dsn>redis:///var/run/redis/redis.sock/0</dsn>
<!-- Unix socket, database 0 -->
```

## Sessions

Redis can also be used for session storage, independently from the cache:

```xml
<global>
    <session_save>redis</session_save>
    <redis_session>
        <dsn>redis:///var/run/redis/redis.sock/1</dsn> <!-- use a different database than the cache -->
        <key_prefix>maho_session:</key_prefix> <!-- optional -->
    </redis_session>
</global>
```

Sessions use the same DSN format as the cache (both TCP `redis://host:port/db` and Unix socket
`redis:///path/to/socket/db` are supported). It's good practice to point sessions to a
**different database number** than the cache.

## Complete example with both cache and sessions

```xml
<global>
    <session_save>redis</session_save>
    <redis_session>
        <dsn>redis:///var/run/redis/redis.sock/1</dsn>
        <key_prefix>maho_session:</key_prefix>
    </redis_session>
    <cache>
        <lifetime>86400</lifetime>
        <backend>redis</backend>
        <backend_options>
            <dsn>redis:///var/run/redis/redis.sock/0</dsn>
        </backend_options>
    </cache>
</global>
```

## Important: Redis `maxmemory-policy` requirement

Maho's cache backend uses Symfony's `RedisTagAwareAdapter`, which **requires** a
`maxmemory-policy` of either `noeviction` or any `volatile-*` value (e.g. `volatile-lfu`,
`volatile-lru`). The tag sets are stored **without** a TTL and must survive the cached items
that have one, something the `allkeys-*` policies cannot guarantee.

Policies like `allkeys-lfu` or `allkeys-lru` cause the cache backend to **silently fail** to
save any data, with no visible error.

Configure Redis accordingly in `/etc/redis/redis.conf`:

```
maxmemory-policy volatile-lfu
```

`volatile-lfu` provides the same LFU eviction behavior as `allkeys-lfu`, but only evicts keys
that have a TTL set (cache data), preserving tag metadata keys that have no expiration. This
requirement applies even when `maxmemory` is `0` (unlimited), because the adapter checks the
reported policy regardless of whether eviction would actually happen. On managed Redis (AWS
ElastiCache, Redis Cloud, etc.) change it from the provider's parameter group / dashboard.

You can also apply the change at runtime (set it in `redis.conf` too, or it's lost on restart):

```bash
redis-cli CONFIG SET maxmemory-policy volatile-lfu
```

!!! note "Sessions are not affected by this restriction"

    Session storage uses Symfony's plain `RedisAdapter` (no cache tags), so the
    `noeviction` / `volatile-*` requirement does **not** apply to the session database.
    Since session keys always carry a TTL, a `volatile-*` policy is still the sensible
    choice if you share the same Redis instance.
