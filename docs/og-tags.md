# Open Graph Tags

Open Graph (OG) meta tags tell social networks and link-preview services how to display your pages when shared. The module outputs OG tags on product pages, category pages, and CMS pages.

---

## Enable / disable

**Stores → Configuration → MageOS SEO → SEO → Open Graph Tags → Enable OG Tags**

Default: enabled. This is a per-store-view setting.

When disabled, no `og:` or `product:` meta tags are output anywhere on the site.

---

## What gets output

### Product pages

| Tag | Value |
|---|---|
| `og:type` | `product` |
| `og:title` | Product name |
| `og:url` | Product canonical URL |
| `og:description` | Meta description; when it is empty, the short description, then the description (HTML stripped, truncated to 160 characters) |
| `og:image` | First product image URL |
| `product:price:amount` | Final price formatted to 2 decimal places: the price the structured data publishes (`Model\Product\FinalPrice`) |
| `product:price:currency` | ISO currency code (e.g. `GBP`) |
| `product:availability` | `in stock`, `available for order` (on backorder) or `out of stock`, from the stock index the structured data reads; left out when the stock could not be read |

The price is the one the page shows, including tax when the store shows prices including tax. When
it is not known, both price tags are left out rather than written as `0.00`, which reads as free: a
price of 0 (unless **Publish a Price of Zero** is on, for a simple product), a composite product
(configurable, grouped, bundle) priced 0, which Magento does when no option can price it, or a price
that cannot be read, which is logged with the SKU. The structured data's offer leaves its price out
in the same cases; see [structured-data.md](structured-data.md).

### Category pages

| Tag | Value |
|---|---|
| `og:type` | `website` |
| `og:title` | Category name |
| `og:url` | Category URL |
| `og:description` | Meta description; when it is empty, the category description (HTML stripped, truncated to 160 characters) |
| `og:image` | Category image URL (omitted if no image is set) |

### CMS pages

| Tag | Value |
|---|---|
| `og:type` | `website` |
| `og:title` | CMS page title |
| `og:url` | CMS page canonical URL |
| `og:description` | CMS page meta description |

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
