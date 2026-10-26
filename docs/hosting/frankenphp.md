---
description: Run Maho on FrankenPHP, the modern Caddy-based PHP server, using Maho's official Docker images for automatic SSL, HTTP/3 and high performance.
---

[FrankenPHP](https://frankenphp.dev/){target=_blank} is a modern application server for PHP built on top
of the [Caddy](https://caddyserver.com/){target=_blank} web server.

Maho runs perfectly on Apache, Nginx or Caddy (with PHP-FPM for all the previous)
but lately our webserver of choice is definitely [FrankenPHP](https://frankenphp.dev){target=_blank},
because it bundle together the Caddy web server, SSL termination with automatic certificate generation
and the PHP runtime, all in a single process and with great performance!

It also has a lot of great features (HTTP3, early hints and so much more),
check the official documentation for all the details.

## Maho's official docker images

Maho provides **official Docker images based on FrankenPHP**, making it easy to run Maho in containerized environments.
These images are automatically built via Docker Cloud Build and are available on
[hub.docker.com/r/mahocommerce/maho](https://hub.docker.com/r/mahocommerce/maho){target=_blank}.

The tags for Maho 26.7 and later, `latest` and `nightly` included, ship a Maho Caddyfile at
`/etc/caddy/Caddyfile`. It routes the `/api/*` paths, denies access to hidden and private files, and
sets the security headers. Older tags keep the default site block of the base image, because Maho
before 26.7 has no `rest.php` entry point.

The plain `dunglas/frankenphp` base image has none of these rules. An image that you build on that
base yourself therefore needs its own Caddyfile. Copy the site block from
[Web Server Configuration](web-server.md#caddy-and-frankenphp).

To change the configuration of an official image, mount your own file over
`/etc/frankenphp/Caddyfile`, which is the path the container runs, or set the
`CADDY_SERVER_EXTRA_DIRECTIVES` environment variable to add directives to the site block.


## Static binary building

Creating a static binary for your application means bundling all the files of your PHP application
and the whole web server into a single binary file. This opens a whole new set of possibilities for deployment.

For Maho projects you can create it with:

```shell
# Clone FrankenPHP repository
git clone https://github.com/dunglas/frankenphp
cd frankenphp

# Build the standalone Maho app
EMBED=/path/to/your/mahoproject ./build-static.sh

# Now you can run the web server + your app with
cd dist
./frankenphp-mac-arm64 php-server --domain localhost

# And run the Maho CLI tool with
./frankenphp-mac-arm64 php-cli maho
```

!!! warning "php-server does not apply the Maho rules"
    The `php-server` command runs a default configuration. It serves the storefront, but it does
    not route `/api/*` and it does not deny hidden files. Write a Caddyfile with the site block from
    [Web Server Configuration](web-server.md#caddy-and-frankenphp), then start the binary with
    `./frankenphp-mac-arm64 run --config /path/to/Caddyfile` instead.

!!! info
    For more info on options and configurations, check
    [FrankenPHP documentation](https://frankenphp.dev/docs/embed/){target=_blank}.

!!! warning
    Something that you'll have to consider is that the `local.xml` file will be bundled in the binary,
    and you won't be able to change it later, same for other folders like `var` and `media`.
    This is why this feature is still considered experimental,[get involved](../community/contributing.md)
    if you want to help.
