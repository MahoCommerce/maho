---
description: Put the Maho media, exports, imports, feeds and sitemaps on S3, Google Cloud Storage or Azure Blob Storage, so that several web nodes can run with no shared disk.
---

# Shared storage <span class="version-badge">v26.11+</span>

Maho keeps the files that all web nodes must see on **storage mounts**. A mount is a named
[Flysystem](https://flysystem.thephpleague.com/){target=_blank} filesystem. By default, each
mount is a local folder, and a single-node store needs no configuration.

To run several nodes with no shared disk, point the mounts at a bucket in `app/etc/local.xml`.
Each node then reads and writes the same files, and a node can start with an empty `public/media`.

## The mounts

| Mount              | Local folder                    | Contents                                                |
|--------------------|---------------------------------|---------------------------------------------------------|
| `media`            | `public/media`                  | Product, category, blog and CMS images, feed files      |
| `custom_options`   | `public/media/custom_options`   | Files that customers upload with a product custom option |
| `downloadable`     | `public/media/downloadable`     | Files that customers buy, and their samples             |
| `customer`         | `public/media/customer`         | Files that customers upload with a customer attribute   |
| `customer_address` | `public/media/customer_address` | Files that customers upload with an address attribute   |
| `exports`          | `var/export`                    | Dataflow export files                                   |
| `imports`          | `var/import`                    | Dataflow import files and profile uploads               |
| `feeds`            | `var/feedmanager`               | Work files of a feed that the admin generates in batches: a state file and one part file for each batch. The finished feed goes to `media`. |
| `sitemaps`         | `public`                        | The sitemap files                                       |
| `importexport_exports` | `var/importexport/export`   | Files of System > Import/Export > Export. A daily cron job deletes the files that are older than 7 days |

The cache, the sessions, the logs, `var/tmp` and the locks are not on a mount. Put the cache and
the sessions on [Redis](redis.md). Each node keeps its own logs and temporary files. System >
Import/Export keeps its uploaded files on the local disk of the node that receives the upload. A
queue worker runs the import from the rows in the database, and a product import reads its images
from `media/import` on the node of that worker. On more
than one node, set the lock backend to `db`, so that all nodes share the locks: see
[locking across multiple servers](cron.md#locking-across-multiple-servers).

## Install the adapter package

Maho includes the local adapter only. Install the package for your storage service:

| Service                                                     | `<type>` | Package                                 |
|-------------------------------------------------------------|----------|-----------------------------------------|
| Amazon S3 and S3-compatible (MinIO, Cloudflare R2, Spaces)  | `s3`     | `league/flysystem-aws-s3-v3`            |
| Google Cloud Storage                                        | `gcs`    | `league/flysystem-google-cloud-storage` |
| Azure Blob Storage                                          | `azure`  | `azure-oss/storage-blob-flysystem`      |

```bash
composer require league/flysystem-aws-s3-v3
```

## Configure a mount

Add a `<storage>` block to `app/etc/local.xml`. A mount in `local.xml` replaces the adapter of the
mount with the same name. This example puts the `media` mount on S3:

```xml
<config>
    <global>
        <storage>
            <mounts>
                <media>
                    <adapter>
                        <type>s3</type>
                        <bucket>my-store-media</bucket>
                        <prefix>media</prefix>
                        <region>eu-west-1</region>
                        <key>AKIA...</key>
                        <secret>...</secret>
                    </adapter>
                </media>
            </mounts>
        </storage>
    </global>
</config>
```

The media URLs come from the **Base Media URL** of the store configuration, also when the mount is
on a bucket. To serve the media from a CDN, set the Base Media URL of each store to the CDN, as you
do with a local disk:

```bash
./maho config:set web/unsecure/base_media_url https://cdn.example.com/media/
./maho config:set web/secure/base_media_url https://cdn.example.com/media/
```

Then every media URL points to the CDN: the product images, the blog and category images, the
logo, the `{{media url=...}}` directives, and the images of the extensions.

The elements of a mount:

- `<visibility>`: `public` or `private`, the visibility that Maho sets on each write. Do not set it
  on an S3 bucket with ACLs disabled, which is the default for new buckets. Give read access with
  a bucket policy instead.
- `<adapter>`: the storage service. `<type>` selects the adapter, and the other elements are its
  options.
- `<public_url>`: the URL prefix of the files, for a mount that you declare yourself. Without it,
  Maho uses the URL that the adapter gives, which is usually the bucket URL. Do not set it on
  `media` or `sitemaps`: they follow the store configuration.

The adapter options:

- **local** (the default): `file_mode` and `dir_mode`, each three octal digits. A new folder gets
  `dir_mode`, 0777 by default, and the umask of the process reduces it, as with `mkdir`. A file
  on a mount with `<visibility>public</visibility>` gets `file_mode`, 0644 by default. Use 0664
  and 0775 when the web server user and the command line user share a group. A listing leaves out
  symbolic links, so the admin media browser does not show a linked folder or file. A read or a
  write through a link still works.
- **s3**: `bucket` (required), `prefix`, `region` (default `us-east-1`), `key` and `secret`,
  `endpoint`, `use_path_style_endpoint`. Set both `key` and `secret`, or neither. With neither,
  the AWS SDK finds the credentials itself, for example from an instance role. MinIO needs
  `endpoint` and `use_path_style_endpoint`.
- **gcs**: `bucket` (required), `prefix`, `project_id`, `key_file`. `key_file` is the path to a
  service account JSON file. Without it, the Google client finds the credentials itself.
- **azure**: `container` (required), `connection_string` (required), `prefix`,
  `public_container`. Set `public_container` when the container serves files without a
  signature.

Use one bucket or one prefix for each mount. Only the `media` mount holds public files. Keep the
other mounts in a bucket that allows no public read.

## Private files

The `custom_options`, `downloadable`, `customer`, `customer_address` and `importexport_exports`
mounts hold files that only Maho can serve. The local folders of the first four mounts are below
`public/media`, and the web server denies them. The local folder of `importexport_exports` is
`var/importexport/export`, outside the web root.

A private mount does not follow the `media` mount. When you move `media` to a bucket, move each
private mount too, or its files stay on the disk of the node that received them. Put the private
mounts in a bucket that allows no public read, never in the public media bucket:

```xml
<downloadable>
    <adapter>
        <type>s3</type>
        <bucket>my-store-private</bucket>
        <prefix>downloadable</prefix>
        <region>eu-west-1</region>
    </adapter>
</downloadable>
```

Do the same for `custom_options`, `customer`, `customer_address` and `importexport_exports`, each
with its own prefix.

Make sure that a private file answers `403`, and that a product image answers `200`:

```bash
curl -I https://my-store-private.s3.eu-west-1.amazonaws.com/downloadable/files/links/a/b/file.zip
curl -I https://my-store-media.s3.eu-west-1.amazonaws.com/media/catalog/product/a/b/image.jpg
```

A customer downloads a purchased file through Maho. When the mount can sign a URL, Maho sends a
redirect to a signed URL that expires after 60 seconds. Otherwise, Maho streams the file itself.

## Resized images

Maho creates the resized files of the product, category, blog and swatch images before a page
links to them, at the widths of the [image slots](../developer/theme-development.md#image-slots) of
the theme. It stores them on the `media` mount, below the `cache/` folder of each image folder, such
as `catalog/product/cache/`. The swatch images are the files in `wysiwyg/swatches`. A swatch with
only a color has no file: the page draws it from the color.

- A save in the admin or through the API creates the files of the new images, after the database
  commit. This covers products, product duplicates, categories, blog posts, the placeholders and
  an upload to `wysiwyg/swatches`. The other new gallery images of a product go to the queue, so a
  save with many images returns fast.
- An import queues them on the `image` queue, in the `slow` worker pool unless you route it
  elsewhere: see [worker pools](../developer/message-queue.md#worker-pools).
- A resize that fails goes to the queue, which tries it again.
- `storage:image:resize` creates all the files that are missing.

So the bucket or the CDN serves every resized image directly, and it needs no failover rule. A file
can still go missing, for example after an extension writes an image with SQL. On a local disk, the
web server sends the request for a missing resized file to Maho, which creates the file and returns
it. `public/.htaccess` and Caddy do this already, and nginx needs the rule in
[web server](web-server.md#nginx). Maho accepts only a path of image settings that a store view
uses and of an original that exists, so any other path gets a 404 and writes nothing.

A bucket never sends a missing file to Maho by itself. There, the queue and `storage:image:resize` repair
the files. To repair a file at the first request, configure the CDN to send a miss below
`/media/<folder>/cache/` to Maho at the same path. Maho answers at `/media/` followed by the path
on the `media` mount, writes the file to the bucket, and returns it. The next request gets the
file from the bucket.

- S3 answers `403`, not `404`, for a missing file when the bucket policy does not allow listing.
  Send both codes to Maho.
- In CloudFront, make an origin group with the bucket as the primary origin and the Maho host as
  the secondary origin. Set the failover on `403` and `404`, and use the group for the behavior
  of `media/*/cache/*`.
- On another CDN, use its rule for a failover or a fallback origin, such as a Cloudflare Worker
  or a Bunny edge rule.

When the theme or the image settings change, the image URLs change at once, and the queue creates
the new files. A config save checks this at once, and the `image_settings_check` cron job checks it
every 15 minutes: it queues the files of each settings folder or width folder that is missing on
the `media` mount. Until the files exist, a local disk creates each file on its first request. A
bucket shows each image when the queue has created it, or at once with the CDN rule above. So on a
bucket without that rule, run `storage:image:resize` in a deploy that changes the slots, the ratio
or the fit of a theme, before the store opens.

### Image sizes

An image with the ratio `original` gets its `width` and `height` from the size of its original.
Maho keeps that size in the `core_image_size` table, so a page never reads an original from a
bucket. A cache flush does not touch the table.

- The resizer stores the size each time that it reads an original.
- `storage:image:resize` stores the size of each original that has none.
- When Maho writes, moves or deletes a file on a mount, it deletes the stored size of that file.
  The next resize stores the new size.
- On a local disk, a page reads the size of an original that has no stored size, and stores it.
- On a bucket, a page shows an original that has no stored size without `width` and `height`, and
  the `image` queue reads its size. The next page has it.
- The stored sizes also tell whether a swatch image in `wysiwyg/swatches` exists. On a bucket, a
  missing file has a size of 0 x 0, so a page with color swatches does not ask the bucket for each
  color. On a local disk, a missing file has no stored size, so a file that you copy there later
  shows.

A change to the originals outside Maho, for example with the bucket console or with a restore,
keeps the old sizes. After such a change, click **Flush Catalog Images Cache** in **System > Cache
Management**. The queue reads all the sizes again, and the old sizes stay until it replaces them.

## Move an existing store

Do these steps on one node, with the old local folders still in place:

1. Install the adapter package.
2. Add the `<storage>` block to `app/etc/local.xml`.
3. Copy the files to the bucket:

    ```bash
    ./maho storage:migrate --dry-run
    ./maho storage:migrate
    ```

4. Flush the cache with `./maho cache:flush`.
5. Create the resized images that are missing:

    ```bash
    ./maho storage:image:resize
    ```

6. If the `sitemaps` mount moved, generate the sitemaps again. Then delete the old sitemap files
   from `public/`, because the web server serves a local file before it asks Maho.
7. Check the store. Then deploy the same `local.xml` on the other nodes.
8. Delete the old local folders of the mounts that moved. For `sitemaps`, delete only the sitemap
   files, never `public/`.

`storage:migrate` without an argument copies every mount that is not local. To copy some mounts
only, give their names: `./maho storage:migrate media exports`. The command has these features:

- It never changes the local files.
- It skips a file that is on the bucket already with the same size. If the command stops, run it
  again: it copies only the files that are missing.
- It copies to a bucket with 16 parallel jobs, because each upload waits for the bucket to
  answer. `--jobs=N` sets another number, and `--jobs=1` copies in one process. A copy to a
  local folder uses one job. The upload bandwidth of the server limits the speed: when more
  jobs do not copy faster, the bandwidth is the limit.
- It does not copy the folders that Maho creates again: `catalog/swatches` and `tmp` in the
  `media` mount. `--include-cache` copies them, and `--exclude=FOLDER` excludes more folders. It
  copies the resized images, because no request creates them again.
- It does not copy the folder of another mount. `media` leaves out `custom_options`,
  `downloadable`, `customer` and `customer_address`, so a private file never lands in the public
  bucket. Each private mount copies its own folder.
- It does not copy the `sitemaps` mount, because its local folder is `public/`. Generate the
  sitemaps again after the switch, then delete the old sitemap files from `public/`.
- It returns an error code when a file fails, and lists the files that failed.

`storage:image:resize` creates the resized files that are missing, for all images, or for one
entity with `--entity=product`, `--entity=category`, `--entity=blog` or `--entity=swatch`. When a
file fails, run the command again: it creates only what is missing. On a bucket, it resizes with
16 parallel jobs, because each image waits for a read and for one write per width. `--jobs=N` sets
another number. Then it stores the size of each original that has no stored size. Run it once after
the upgrade to 26.11, then flush the full page cache:

- On a bucket without a CDN rule that sends a miss to Maho, run it before you open the store.
  Without it, the images are missing until the queue has created them.
- On a local disk with the web server rule, it is optional. Maho creates a missing file on its
  first request, and the `image_settings_check` cron job queues the missing files within 15
  minutes. Run it to avoid many resizes at once on the first visits.

`storage:image:clean` deletes the resized files of the image settings that no store view uses, for example
after a theme change, and the resized files of the releases before 26.11. It also deletes the stored
sizes of the originals that no record uses.

## For extension developers

A file that all nodes must see goes through a named mount. A file that one node uses alone stays
on the local disk.

- Get a mount with `Mage::getStorage('media')`. It is a Flysystem `Filesystem`, so use `write()`,
  `read()`, `fileExists()`, `delete()` and `listContents()` on it.
- List the files below a folder at any depth with `$mount->listFiles('my_module')`. The result
  holds files only, and it is the same on every adapter.
- Get the URL of a file with `$mount->publicUrl($path)`. It gives the correct URL for every mount,
  also for a mount that you declare. On `media`, it gives the same URL as
  `Mage::getBaseUrl('media')` and the path.
- Store an upload with `$uploader->saveToStorage(Mage::getStorage('media'), 'my_module')`. The
  upload goes from the PHP temporary file to the mount, with no local copy.
- Before you read or delete a file whose name comes from a request or from the database, check
  the name with `\Maho\Io::getPathWithinMount($mount, 'my_module', $name)`. It returns `null` when
  the name leaves the folder.
- A system configuration file field (a subclass of
  `Mage_Adminhtml_Model_System_Config_Backend_File`) stores its file on the `media` mount.
  `_getUploadDir()` returns a path on the mount, such as `my_module/logo`. An absolute folder
  below `public/media` still works, and any other absolute folder is refused.
- Write a file that a visitor or a crawler can read at any time with `moveAtomic()`. It writes the
  whole file in one step.
- Each `write()`, `writeStream()`, `move()`, `copy()`, `delete()` and `deleteDirectory()` of a mount
  dispatches the `storage_mount_change` event with the `mount`, the changed `paths` and the
  deleted `folders`. Maho uses it to delete the stored size of an image. To copy files that do
  not change, such as a migration, use `$mount->withoutChangeEvents()`.
- Get the width and the height of an image on a mount with
  `Mage::getSingleton('core/image_size')->get($mount, $path)`. It never reads a file on a bucket.
- Before a template shows the images of a list, call
  `Mage::getSingleton('core/image_size')->preload('my_module', $files)`. It reads the sizes of all
  the images with one query. Maho does this for the storefront product collections, the product
  gallery and the blog posts.
- Keep the cache, the sessions, the logs, the temporary files and the locks on the local disk.
  Use `core/lock` for a lock that all nodes must see.

To give the images of your module resized files, declare an image entity in its `config.xml`:

```xml
<config>
    <global>
        <images>
            <entities>
                <my_module>
                    <source>my_module/images</source>
                    <provider>my_module/image</provider>
                    <background>my_module/images/background_color</background>
                </my_module>
            </entities>
        </images>
    </global>
</config>
```

- `<source>` is the folder of the originals on the `media` mount.
- `<provider>` is a model that implements `Mage_Core_Model_Image_ProviderInterface`. Its
  `getImageFiles()` returns every original, for `storage:image:resize`.
- `<background>` is optional. It is the store config path of the color that fills the box of the
  `contain` fit. Without it, the color is white.
- `<watermark>` is optional. It is the start of the store config paths of a watermark, such as
  `design/watermark/image`. Maho reads `_image`, `_size`, `_position` and `_imageOpacity` after it,
  and looks for the file in the `watermark` folder below `<source>`.

A template then asks for the attributes of one slot:
`Mage::helper('core/image')->init('my_module', $file)?->getImgAttributes('listing')`. A theme sets
the ratio and the fit of the entity in its `theme.xml`, under the same name.

When the image is the value of an EAV attribute, give the attribute a backend model that extends
`Mage_Eav_Model_Entity_Attribute_Backend_Image` and returns the entity name from
`getImageEntity()`. The backend does the rest:

- It saves an upload to `<source>` and creates the resized files after the database commit. It
  does the same for a file name that an import sets.
- It deletes the old file, with its resized files, when no record uses it any more.
- Its `getImageFiles()` lists every value of the attribute, so the provider can return it.

The category and blog post images use this backend. For an image that is not an EAV attribute,
call `Mage::getSingleton('core/image_resizer')->createFilesAfterCommit('my_module', [$file])` after
a save that changes it.

To write to a folder that no mount covers, declare a mount in the `config.xml` of your module.
Declare only a folder that your module writes:

```xml
<config>
    <global>
        <storage>
            <mounts>
                <my_module_reports>
                    <dir>var</dir>
                    <path>my_module/reports</path>
                </my_module_reports>
            </mounts>
        </storage>
    </global>
</config>
```

`<dir>` is a folder that Maho knows, such as `var` or `media`. `<path>` is the folder below it.
An operator can then move this mount to a bucket in `local.xml`, like the core mounts.

A bucket is not a disk. Test your code on a bucket, because these operations change:

- `move()` is a copy and a delete.
- A deep `listContents()` returns all the files, but the folders only on some adapters. Use
  `listFiles()` when you need the files.
- There are no locks, no seek and no partial reads.
