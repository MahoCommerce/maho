---
description: Let the storefront use every Tailwind class that the CMS, blog and catalog content uses - the Node.js toolchain, the queue worker, the cron check, the deploy step, several web nodes, and what happens when a build fails.
---

# Tailwind Build of the Content

The committed `css/styles.css` of a theme has the classes of its templates. A merchant can also
write Tailwind and daisyUI classes in CMS pages, static blocks, blog posts, widgets, product
descriptions and other content: see [HTML and Tailwind classes in content](../user-guide/content-html-and-classes.md).
The content build compiles those classes into a copy of `css/styles.css`, and the storefront loads
the copy instead of the committed file.

The build is optional. Without it, the store serves the committed `css/styles.css`, and a class
that this file does not have has no effect on the storefront.

## Requirements

- **Node.js and the Tailwind CLI.** Run `npm install` in the Maho root. It installs the CLI in
  `node_modules/.bin/tailwindcss`. Without that file, no build runs.
- **`node` in the `PATH` of the worker.** The CLI starts with `#!/usr/bin/env node`. A version
  manager such as nvm adds `node` to the `PATH` of your shell only, so set the `PATH` in the
  crontab, or in the unit file of the worker.
- **A queue worker.** A save queues the build, and a worker runs it. Cron starts a worker, or
  supervisord or systemd keeps `./maho queue:work` running. See the
  [worker pools](../developer/message-queue.md#worker-pools).

To check the setup, run the build by hand, as the user of the worker:

```bash
./maho theme:content:build
```

The command lists the sources of the content, then one row for each theme: `built`, `unchanged`,
`empty` when the content has no classes, or `failed`. It also shows the file of the build on the
media mount.

## How a build starts

| Event | What happens |
|-------|--------------|
| A save of content that changes a field with HTML | A build is queued |
| An import, from **System > Import/Export > Import** or `./maho import:*` | A build is queued when the import ends |
| The cron job `theme_content_check`, every 15 minutes | A build is queued for a theme whose committed `css/styles.css` changed after its last build, that has no build, or whose file is missing from the media mount |
| `./maho theme:content:build` | The build runs at once, with no queue |

A queued build goes to the `theme_content` queue and runs one minute later. The queue drops a second
request while the first one waits, so an import of many products gives one build. The
`theme_content` queue has no routing, so it runs in the `slow` pool.

A script of your own that writes content with SQL queues no build. Run `./maho theme:content:build`
after it.

## After a deploy

The store serves a build only while it comes from the current `css/styles.css` of the theme. So
when a deploy changes that file, for example a Maho upgrade, the store serves the committed file at
once. The cron job queues a new build within 15 minutes. To skip that wait, add the build to the
deploy script, after the cache flush:

```bash
./maho cache:flush
./maho theme:content:build
```

## Where the file goes

The build writes `theme/<package>-<theme>-<hash>.css` to the `media` mount, and the page head links
it from the media URL. On several web nodes with a bucket, all nodes serve the same file: see
[Shared storage](storage.md).

One build serves all the store views. It has the classes of the content of every store view.

The build keeps the current file and the previous one, and deletes the older files. A page that a
full-page cache, such as Varnish, keeps for longer than two builds links a deleted file, and shows
without styles until the cache refreshes it. If the cache keeps pages for a long time, purge it
after a build.

## When a build fails

The Tailwind output goes to the system log (`var/log/system.log` by default). The store keeps the
file of the previous build, or the committed `css/styles.css` when there is none.

The cron job does not repeat a build that failed with the same `css/styles.css`, so a build that
always fails does not run every 15 minutes. The next save of content, a new `css/styles.css` or
`./maho theme:content:build` tries again.

Without the Tailwind CLI, no new build runs. The store keeps the last build until the committed
`css/styles.css` changes.
