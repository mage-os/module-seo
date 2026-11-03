# Open Graph Tags

Open Graph (OG) meta tags tell social networks and link-preview services how to display your pages when shared. The module outputs OG tags on product pages, category pages, and CMS pages.

---

## Enable / disable

**Stores → Configuration → MageOS SEO → SEO → Open Graph Tags → Enable OG Tags**

Default: enabled. This is a per-store-view setting.

When disabled, no `og:` or `product:` meta tags are output anywhere on the site.

## Image size

**Stores → Configuration → MageOS SEO → SEO → Open Graph Tags → Publish Images at the Open Graph Size**

Default: No. This is a per-store-view setting.

This setting applies to products only. Category and CMS page images are always published at their
original URL, as uploaded.

With No, the product `og:image` is the theme's `product_page_image_large`, which Luma and Hyvä do
not resize.

With Yes, product images are resized to **1200 × 630**. Facebook, LinkedIn and X show
large link previews at that size. The size is published too:

```html
<meta property="og:image" content="https://example.com/media/catalog/product/cache/…/w/s/ws12-orange_main_4.jpg"/>
<meta property="og:image:width" content="1200"/>
<meta property="og:image:height" content="630"/>
```

The image keeps its proportions and is centred on a white canvas of exactly that size. So a crawler
knows the size before it fetches the image.

The size is the `mageos_seo_og_image` image, declared in this module's `etc/view.xml`. A theme can
change it in its own view.xml, like any catalog image size:

```xml
<images module="Magento_Catalog">
    <image id="mageos_seo_og_image" type="image">
        <width>1200</width>
        <height>628</height>
    </image>
</images>
```

With `<frame>false</frame>`, the image is only fitted inside the size. Its size is then unknown, so
`og:image:width` / `og:image:height` are left out.

Products are resized by the catalog image helper into `pub/media/catalog/product/cache/`.
`bin/magento catalog:images:resize` creates them in advance. Flush Catalog Images Cache removes
them. A product without an image gets the placeholder, without a size.

### Rendition size

Category and CMS page images come from the Media Gallery. With image optimization on, the gallery
inserts a rendition, at most 1000 × 1000 pixels by default. Set the rendition size to the Open Graph
size:

**Stores → Configuration → Advanced → System → Media Gallery Image Optimization**: Maximum Width
1200, Maximum Height 630.

```bash
bin/magento config:set system/media_gallery_renditions/width 1200
bin/magento config:set system/media_gallery_renditions/height 630
```

The setting is global and applies to every image inserted from the gallery, content images too.
Existing renditions are not regenerated: pick the image again.

---

## What gets output

### Product pages

| Tag | Value |
|---|---|
| `og:type` | `product` |
| `og:title` | Open Graph Title, else the meta title, else the product name |
| `og:url` | Product canonical URL |
| `og:description` | Open Graph Description, else the meta description, else the short description, else the description. HTML and Page Builder styles are stripped. Fallbacks are truncated to 160 characters |
| `og:image` | The image with the Open Graph Image role, else the base image |
| `product:price:amount` | Final price formatted to 2 decimal places: the price the structured data publishes (`Model\Product\FinalPrice`) |
| `product:price:currency` | ISO currency code (e.g. `GBP`) |
| `product:availability` | `in stock`, `available for order` (on backorder) or `out of stock`, from the stock index the structured data reads; left out when the stock could not be read |

The price is the one the page shows, including tax when the store shows prices including tax. When
it is not known, both price tags are left out rather than written as `0.00`, which reads as free: a
price of 0 (unless **Publish a Price of Zero** is on, for a simple product), a composite product
(configurable, grouped, bundle) priced 0, which Magento does when no option can price it, or a price
that cannot be read, which is logged with the SKU. The structured data's offer leaves its price out
in the same cases; see [structured-data.md](structured-data.md).

The Open Graph fields (below) are written for link previews, so they come first. They are used as
written, with HTML stripped. The meta title and description come next. Then the name, the
description and the short description. The order is the same on products, categories and CMS pages.
Categories have no short description, and CMS pages have neither.

### Category pages

| Tag | Value |
|---|---|
| `og:type` | `website` |
| `og:title` | Open Graph Title, else the meta title, else the category name |
| `og:url` | Category URL |
| `og:description` | Open Graph Description, else the meta description, else the category description. HTML is stripped. Fallbacks are truncated to 160 characters |
| `og:image` | Open Graph Image, else the category image, at its original URL. Omitted if neither is set |

### CMS pages

| Tag | Value |
|---|---|
| `og:type` | `website` |
| `og:title` | Open Graph Title, else the meta title, else the CMS page title |
| `og:url` | CMS page canonical URL |
| `og:description` | Open Graph Description, else the meta description (truncated to 160 characters) |
| `og:image` | Open Graph Image, else the first image in the page's content. Omitted if there is none |

---

## Setting them per page

Each product, category and CMS page has an **Open Graph Title**, **Open Graph Description** and
**Open Graph Image**. Leave them empty to use the fallbacks above.

| Page | Where | Stored as |
|---|---|---|
| Product | Title and description: **Search Engine Optimization**. Image: the **Open Graph Image** role in **Images and Videos**, chosen like the base or small image | EAV attributes `og_title`, `og_description`, `og_image` (store view) |
| Category | **Search Engine Optimization**, below the meta description. The image is picked from the Media Gallery | EAV attributes `og_title`, `og_description`, `og_image` (store view) |
| CMS page | **Search Engine Optimization**, below the robots and hreflang fields. The image is picked from the Media Gallery | `cms_page` columns `og_title`, `og_description`, `og_image` |

The title and description are used as written, with HTML stripped. The image should be at least
1200 × 630 pixels. Product and category values have store view scope, like their meta title and
description. CMS pages are already assigned to store views.

The category and CMS page image fields have no Upload button and no drop zone. Click **Select from
Gallery**, then pick an image, or upload a new one inside the Media Gallery (`Magento_MediaGalleryUi`).
The old media browser works too, when **Stores > Configuration > Advanced > System > Media Gallery >
Enable Old Media Gallery** is Yes. The old browser opens in `catalog/category` for categories and in
`wysiwyg` for CMS pages. The Media Gallery can open in another folder.

With image optimization on, the Media Gallery inserts a rendition (`.renditions/...`). It is
published as picked. Renditions are 1000 × 1000 pixels at most by default, below the 1200 × 630 link
previews use. See [Rendition size](#rendition-size).

- A category stores the pick as core's category image does: its `/media/...` path
  (`/media/.renditions/wysiwyg/team.jpg`). `og:image` is that path under the store view's URL.
- A CMS page's `og_image` is stored as the Media Gallery posts it, like core's category image: its
  path from the web root (`/media/.renditions/wysiwyg/team.jpg`). Core's
  `Magento\Catalog\Model\Category\FileInfo` resolves it to a path in the media directory, which is
  published under each store view's media URL.
- A value outside the media directory, such as an absolute `http(s)` URL set by REST, is published
  as is.

### Coming from another Open Graph module

Open Graph modules commonly use these attribute codes and column names. A store that already has
`og_title`, `og_description` and `og_image` keeps its values. `setup:upgrade` updates the attribute
definitions (labels, group, store view scope) and leaves the values alone. For the same reason,
`og_description` stays a varchar on products and categories.

Remove the other module when you no longer need its tags. While both are enabled, both print Open
Graph tags, and the admin forms show both sets of fields.

---

## How OG tags are output

OG tags are rendered by the `Block\MetaTags` block, which is injected into the `<head>` via `default.xml`. The block calls the **MetaTag Compositor**, which collects tags from all registered `MetaTagProviderInterface` providers and outputs them.

Each provider declares which layout handles it applies to — the compositor only calls providers whose handles match the current page.

### Core's product Open Graph is removed

Magento renders its own Open Graph on product pages — `catalog_product_view` pulls in
`catalog_product_opengraph`, which adds the `opengraph.general` block: `og:type`, `og:title`,
`og:image`, `og:description`, `og:url`, and `product:price:amount` / `product:price:currency` from
its `opengraph.currency` child. This module emits every one of those as well, so leaving core's
block in place put two of each tag on every product page, and a crawler meeting two `og:image`
values chooses one itself.

`MageOS\Seo\Observer\RemoveCoreOpenGraph` unsets core's block on `layout_generate_blocks_after`
**only while this module's Open Graph output is enabled**. Switching it off under
**Enable / disable** above hands product pages back to core's tags rather than leaving them with
none. That dependency is why the block is removed at runtime rather than with `remove="true"` in
layout XML, which cannot be made conditional.

---

## Caching

Meta tag output is fully FPC-cacheable. The `Block\MetaTags` block does not use `cacheable="false"`. Each unique URL gets its own cache entry, so different products and categories have independent cached responses.

---

## Twitter / X cards

Every page with an `og:title` also gets X card tags. `MetaTag\Compositor` adds them after it has
collected every provider's tags, because the card type depends on the whole page:

| The page has | Card |
|---|---|
| `og:title` and `og:image` | `twitter:card` = `summary_large_image` |
| `og:title`, no image | `twitter:card` = `summary` |
| no `og:title` (cart, checkout, account) | none |

`twitter:title`, `twitter:description` and `twitter:image` repeat `og:title`, `og:description` and
`og:image`. X [falls back to the Open Graph tags](https://developer.x.com/en/docs/x-for-websites/cards/guides/getting-started)
when they're missing, so they aren't strictly needed there. They're published for readers that
read only `twitter:*`.

**Setting your own.** A provider that returns a `twitter:*` tag itself keeps it: the compositor adds
only the tags a page doesn't already have. A bridge module can set a different card type (say
`player`) or a title written for X:

```php
return [
    ['name' => 'twitter:card', 'content' => 'player'],
    ['name' => 'twitter:title', 'content' => $video->getTitle()],
];
```

The copies follow the Open Graph tags, so turning off **Enable OG Tags** removes them too.
