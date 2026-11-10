# Structured Data (JSON-LD)

The module outputs JSON-LD `<script type="application/ld+json">` blocks in the `<head>` of every page. Each block contains one or more schema.org nodes assembled from registered providers.

---

## What gets output on each page type

| Page | Schema nodes |
|---|---|
| All pages | Organization, WebSite (with SearchAction), BreadcrumbList |
| Category page | CollectionPage, ItemList (if enabled) |
| Product page | Product (or sub-type), via the template system; a ProductGroup of its variants for a configurable product (see [Configurable products](#configurable-products)) |
| CMS pages, the home page included | WebPage, `@id` `{url}#webpage` — the home page's URL is the store base URL (see [canonical-urls.md](canonical-urls.md#cms-pages)) |

The Organization and WebSite nodes appear on every page because they are site-wide identity data. The BreadcrumbList node follows the trail the page shows:

- **Hyvä** exposes its breadcrumbs block's crumbs, and the node is built from them — the trail as rendered. Hyvä's breadcrumbs template prints its own BreadcrumbList unless the block has `skip_ld_json_schema` set; the module sets it while structured data is on, so the page carries one BreadcrumbList, not two.
- **Luma** keeps its crumbs to itself and draws the product trail in JavaScript, so the node is rebuilt from core's catalog breadcrumb path. On a product page it has the category only when the URL carries one (a category-path product URL); otherwise it is **Home › Product**, as Luma shows it. Core would otherwise supply the category the visitor last browsed, from their session, and the page cache would keep that one visitor's trail for everyone.
- The last crumb has no `item`: [Google documents](https://developers.google.com/search/docs/appearance/structured-data/breadcrumb) that it is not required there, and uses the page's own URL.

A page that builds its own breadcrumb schema can switch this one off with the `excludedHandles` argument (see [extending.md](extending.md)).

---

## The compositor and provider system

All JSON-LD output flows through a single **Compositor** (`Model\StructuredData\Compositor`). It holds a pool of **providers** — each provider is responsible for one or more schema nodes on specific page types.

Each provider declares which layout handles it applies to:

```php
public function getHandles(): array
{
    return ['catalog_category_view'];  // only on category pages
    // or ['*'] for every page
}
```

The compositor checks the current page's active layout handles against each provider's declared handles, then calls `getSchemas()` on every matching provider. The combined output is serialised to JSON and written into the `<head>`.

---

## Provider pool

Built-in providers, in order:

| Provider | Handle | Output |
|---|---|---|
| `OrganizationProvider` | `*` | Organization node + WebSite node with SearchAction |
| `BreadcrumbListProvider` | `*` | BreadcrumbList (reads layout breadcrumbs block) |
| `CategorySchemaProvider` | `catalog_category_view` | CollectionPage + optional ItemList |
| `ProductSchemaProvider` | `catalog_product_view` | Dispatches to template builder pool |
| `CmsPageSchemaProvider` | `cms_page_view` (every CMS page and the home page) | WebPage node at `CmsPageResolver::currentUrl()` |
| `ArticleSchemaProvider` | `*` | BlogPosting, from the first `ArticleDataProviderInterface` with an article for the page — none registered by default. Author and publisher reference the Organization while one is published |
| `EventSchemaProvider` | `*` | One Event per event from every matching `EventDataProviderInterface` — none registered by default. The organizer references the Organization while one is published |

**References to the Organization.** A node points at the Organization by its `@id`
(`Model\StructuredData\OrganizationId`) only while the Organization is published, which it is once
it has a name: `OrganizationProvider` writes no Organization node without one. Until then a
BlogPosting has no author or publisher and an Event no organizer, rather than an `@id` no node on
the page carries.
| `SpeakableProvider` | `catalog_product_view` | With Speakable on: the product page's WebPage, carrying `speakable` (see [Speakable](#speakable)) |

Bridge modules add their own providers by registering them in their own `di.xml` — the Seo module is never modified.

---

## Product schema registry

The product schema node is assembled in stages, so another module can adjust it without producing a duplicate node:

1. `ProductSchemaProvider` builds the node with the category's template builder, turns a configurable product's node into a ProductGroup of its variants (below), and stores the result in `SchemaRegistry`.
2. Any provider that runs after it can change the node in the registry (`merge()`, `mergeNested()`).
3. The compositor serialises whatever is in the registry as a single node, after every provider has run.

This means the product JSON-LD block always contains exactly one product node, regardless of how many providers contributed to it.

---

## A product whose price is not known has no offer

A product's offer carries its final price, in the display currency. When that price is not known,
the product node has **no `offers` at all**:

- **A composite product nothing can price** — a configurable, grouped or bundle product whose final
  price is 0. Magento prices such a product from its options, and gives it 0 when no option can price
  it: an out-of-stock configurable has no saleable child.
- **A price lookup that fails.** The error is logged (`MageOS_Seo: could not read the final price of
  product "<sku>"`), so the cause can be found.
- **Any other product priced 0**, unless **Publish a Price of Zero** is on (Stores → Configuration →
  MageOS SEO → Structured Data (JSON-LD); default No). A price of 0 is usually one hidden from the
  visitor, as on B2B and trade stores, rather than a free product. With the setting on, a simple
  product priced 0 is offered as free; a composite product priced 0 still has no offer.
- **A price below 0.**

Not a price of 0, because Google reads `"price": 0` as free, and its merchant listings need a price
above zero. And not an Offer without a price either, because Google requires `price` on every Offer.
A Product without `offers` is still eligible for product snippets when it has `aggregateRating` or
`review`.

**The price is the one the page shows.** It is the final price's amount with the adjustments the
store displays: including tax when prices are shown including tax, or both ways, and excluding it
when catalog prices are entered with tax but shown without. Google expects the structured data's
price to match the visible price, and in many countries consumer prices must be shown with tax.

`Model\Product\FinalPrice` makes these decisions for the structured data, Open Graph and
MageOS_Aeo's `/llms.jsonl`, so they never disagree.

---

## An offer's availability

An offer says what the storefront says. On the storefront MSI answers "salable" from its stock
index alone (in the frontend and GraphQL areas its own di.xml puts the index-based check in place
of the full condition chain), so the structured data reads the same index. Without MSI it reads
CatalogInventory's (see [Without MSI](#without-msi)):

| The stock index has the product | `availability` |
| --- | --- |
| not salable, or not at all | `OutOfStock` |
| salable, with a quantity above zero | `InStock` |
| salable at zero or below, stock managed | `BackOrder`: it sells only through backorders |
| salable at zero or below, stock not managed | `InStock` |
| salable, a product type without a quantity of its own (configurable, bundle, grouped) | `InStock` |

A configurable product's offer takes its best variant's availability instead: `InStock` if any
variant is in stock, else `BackOrder` if any can be backordered, else `OutOfStock`. Its own index row
says salable as soon as any child can be bought, backorders included.

**Reservations are not subtracted**, as the storefront doesn't subtract them either: units ordered
but not yet shipped still count until shipping deducts them, so a product whose last units are all
reserved stays `InStock` on the page and in the markup alike. Google compares the markup with the
page.

`Model\Product\AvailabilityResolver` decides this for every offer. It reads a whole batch of
products in one query (MSI's `@api` `GetStockItemsDataInterface`, or CatalogInventory's stock
statuses without MSI), plus one for whether stock is managed, only when some product is salable at
zero or below. A configurable's variants are read as one batch. Before, each product cost MSI's
per-product condition chain: outside the storefront areas, as in a feed build, a 15-variant
configurable took 149 queries, and takes 14 now (Luma's WT02, measured with the DB profiler).

On the storefront the stock read goes through a request cache, so a product page usually pays
nothing for it. With MSI, loading the page's product fills MSI's cache for the product and, for a
configurable, all of its variants (measured on Luma's MH01, 15 variants: the structured data and
Open Graph read no stock of their own). Without MSI, loading the product fills core's
`StockRegistryStorage` for that product, and the resolver reads through it.

**When the stock cannot be read, availability is left out**, and the error is logged with its SKU.
The offer keeps its price and URL, Open Graph has no `product:availability`, and MageOS_Aeo's
llms.jsonl line has no availability. Saying `OutOfStock` would publish a guess as a fact: an
in-stock product would be marked unbuyable for as long as the page stayed cached. A configurable
takes its best variant among those read; when none of those is sellable and some could not be
read, its availability is left out too, since a variant not read may be in stock.

### Without MSI

MSI is optional. `Model\Product\Stock\StockReader` chooses where availability reads stock:

- **MSI's stock index**, for the stock the website sells from, while `Magento_InventorySales` and
  `Magento_InventoryIndexer` are installed and enabled: registered, and enabled in `config.php`, as
  `ModuleListInterface::getOne()` sees them. `ModuleManager::isEnabled()` would not do: it reads
  `config.php` alone, and says yes for a package composer has removed.
- **CatalogInventory's** otherwise: `cataloginventory_stock_status`, the one stock a store without
  MSI has. It is the table MSI itself reads for its default stock, so a website on the default stock
  gets the same availability either way.

Both ways of running without MSI work: the packages installed and the modules disabled, or the
packages removed by a composer `replace`. Nothing in this module names an MSI class where
`setup:di:compile` resolves it (a constructor, a parent or interface, di.xml), so compile works
without the packages. `Model\Product\Stock\MsiStockReader` fetches MSI's services from the object
manager when it runs, and `Test/Unit/Model/Product/Stock/MsiCompileSafetyTest` keeps it that way.

Without MSI, the storefront reads through core's request cache, `StockRegistryStorage`: a product
the page has loaded is answered from it, and what the resolver reads itself, core's
`StockRegistryPreloader` adds to it. Outside the storefront, as in a feed build, the table is read
instead. A queue consumer keeps that cache from one message to the next, so a rebuild could read
stock cached before the change that queued it, and a feed would fill the cache with the catalogue.
MSI's own request cache is storefront-only for the same reasons.

The cost without MSI, measured on Luma's sample data with the DB profiler, against the same
catalogue with MSI:

| | Without MSI | With MSI |
| --- | --- | --- |
| Simple product page (load, structured data, Open Graph) | 22 queries, none of them the resolver's | 26 |
| Configurable page, 15 variants | 37 queries, one of them the resolver's read of the variants | 43 |
| MageOS_Aeo's llms.jsonl, 179 products | 818 queries, 3 of them the resolver's | 821, 6 of them the resolver's |

Without MSI there is no stock to resolve per website, so those lookups go. The availability was the
same in every case.

One difference from MSI: when not one product of a batch has a stock-status row, MSI falls back to
the stock items (its code names disabled products). The CatalogInventory reader reports a product
without a row as out of stock.

**A stock source of your own:** declare a preference for `Model\Product\Stock\StockReaderInterface`.
Its `read($products)` returns each product's `salable` and `quantity` by SKU, and
`getCurrentStockId()` the stock. The interface is not `@api`: its shape may change in any release.

## An offer's priceValidUntil

`priceValidUntil` is published only where the catalogue has the date, never made up, and only for
the price the offer publishes. An offer carries the **special price end date** (`special_to_date`)
when both of these hold:

- the special price is **in effect today**, as core's `SpecialPrice` decides it from the special
  price dates (the end date counts for the whole of that day), and
- it **is the price published**: no lower price, a catalog price rule's say, beats it. The two are
  compared as Magento holds them, before tax and other display adjustments.

In every other case there is no `priceValidUntil`: no special price, a special price without an end
date, one that has ended or not yet begun, or one a lower price beats. A special price starting
next month does not lend its end date to this month's price. Google accepts an Offer without one.

Earlier versions filled the gap with a synthetic date, today plus **Price Valid Until (months)**.
That date promised a validity nothing in the store backed, so it and its setting have been removed.
Values saved for the old setting stay in `core_config_data` and are no longer read.

`Model\Product\OfferBuilder` decides this for every offer, a variant's included. To publish a date
from another source — a catalog price rule's end, a campaign calendar — register an
`Api\OfferEnricherInterface` that returns `priceValidUntil` for the products it knows a date for.
Enrichers are merged into the offer after it is built, so the enricher's date is the one published.

---

## An offer's shippingDetails

With **Stores → Configuration → MageOS SEO → SEO Merchant Policies → Shipping Details → Enable
Shipping Details Schema** on, every offer carries one `OfferShippingDetails`: a rate, a delivery
time, and the destinations (none when shipping is worldwide, which Google reads as every country).
`Model\Product\OfferEnricher\ShippingDetailsEnricher` builds it; `ShippingRules` holds the rules,
which the admin checks on save and the storefront applies when it publishes.

**The rate** is in the **base currency**, as product prices and core's flat-rate shipping price
are, and is converted to the currency the visitor sees, rounded to it. So the rate and the offer's
`priceCurrency` are always the same currency, and agree with what checkout charges. 0.00 is free
shipping. A rate is required while shipping details are on: with none, or one that is not an
amount of 0 or more, or one that cannot be converted, the shipping details are left out rather than
published as free shipping or a wrong amount. Earlier versions published the configured number
under the display currency's code without converting it: a store whose display currency differs
from its base currency, and which entered the rate in the display currency, should enter it again
in the base currency.

**Handling and transit time** follow Google's shipping-policy reference: "you must provide either
`value` … or `maxValue` (for an upper bound)", "`minValue` can optionally be provided", and each
is "a non-negative, whole number" of days.

| Min (days) | Max (days) | Published |
|---|---|---|
| empty | empty | no time |
| empty | 3 | `maxValue: 3` |
| 1 | 3 | `minValue: 1`, `maxValue: 3` |
| 0 | 0 | `minValue: 0`, `maxValue: 0` (the same day) |
| 2 | empty | refused on save; left out |
| 5 | 2 | refused on save; left out |
| -1, 2.5, two | any | refused on save; left out |

Nothing is invented: a minimum alone is not read as a range up to 0, and a maximum alone gets no
minimum of 0. The admin refuses the cases above, whether the group is saved in the admin or one
field with `bin/magento config:set`. A value written past that check — straight to the database,
or saved before it existed — is left out on the storefront and logged as a warning, at most once
every five minutes per store view and time (`Model\Log\OccasionalLog`), so it can be found without
filling the log.

---

## Configurable products

A configurable product is described the way Google's [product variant guidance](https://developers.google.com/search/docs/appearance/structured-data/product-variants) asks, from the children the storefront sells: core's `ConfigurableOptionsProviderInterface`, after its filters (enabled children, and in-stock ones unless out-of-stock products are displayed).

**Up to `has_variant_max` sellable children** (Stores → Configuration → MageOS SEO → SEO → Structured Data (JSON-LD) → *Most Variants per Configurable Product*, default 50) — a `ProductGroup`:

```json
{
  "@type": "ProductGroup",
  "@id": "https://example.com/tee.html#product",
  "name": "Tee",
  "url": "https://example.com/tee.html",
  "sku": "TEE",
  "description": "A soft cotton tee.",
  "productGroupID": "TEE",
  "variesBy": ["https://schema.org/size"],
  "hasVariant": [
    {
      "@type": "Product",
      "name": "Tee S",
      "sku": "TEE-S",
      "gtin13": "4006381333931",
      "description": "A soft cotton tee.",
      "image": "https://example.com/media/catalog/product/t/e/tee-s.jpg",
      "size": "S",
      "additionalProperty": [{ "@type": "PropertyValue", "name": "Fit", "value": "Regular" }],
      "offers": {
        "@type": "Offer",
        "url": "https://example.com/tee.html?size=167&fit=201",
        "price": "10.00",
        "priceCurrency": "USD",
        "availability": "https://schema.org/InStock"
      }
    }
  ]
}
```

- **The group** keeps what the template built — name, description, images, brand, aggregate rating — with `ProductGroup` in `Product`'s place (beside a template's own type: `["ProductGroup", "Book"]`), `productGroupID` set to the SKU, and **no `offers`**: Google wants offers on the variants only. A property the variants differ by is taken off the group, where a template may have set it from the parent product.
- **Each variant** is a `Product` with its name, SKU, the group's description (children rarely have their own, and it is the text the page shows), its first gallery image (else the group's), what it varies by, and its own offer. The offer comes from the same `Model\Product\OfferBuilder` as every other product's — price, availability, `priceValidUntil`, and every registered offer enricher.
- **GTIN.** When the category enables its template's GTIN field (`gtin13`), each variant carries its own GTIN, read in one load from the first non-empty of the `gtin13`, `gtin`, `barcode` and `ean` attributes and validated like every GTIN the module writes — one that fails its check digit is left out.

**What a variant varies by** comes from the product's own configurable attributes; there is no attribute map to maintain. Google's `variesBy` accepts six properties only, so:

| Configurable attribute code | Written on the variant as | In `variesBy` |
|---|---|---|
| `color`, `size`, `material`, `pattern` | that property: `"size": "S"` | yes |
| `suggested_gender` (or `suggestedGender`) | `audience.suggestedGender` on a `PeopleAudience` | yes |
| `suggested_age` (or `suggestedAge`), option labels that are numbers | `audience.suggestedAge` as a `QuantitativeValue` in years | yes |
| `suggested_age` with labels that are not numbers ("Adult") | an `additionalProperty` — a `QuantitativeValue` can't hold them | no |
| any other code — `gender`, `fit`, `shoe_width`, … | an `additionalProperty`: the attribute's store label and the option's | no |

Codes are matched case- and underscore-insensitively. Values are the options' store-view labels.

**Variant URLs** are the product's URL with `?{attribute_code}={option_id}` for each configurable attribute — Google's single-page pattern. The page keeps one canonical URL, and Luma's swatch renderer preselects the options the query names. A theme whose option widget reads something else (Luma's dropdown-only widget reads the URL hash), or a store that gives variants pages of their own, replaces the rule with a preference for `MageOS\Seo\Api\ProductVariantUrlResolverInterface`:

```xml
<preference for="MageOS\Seo\Api\ProductVariantUrlResolverInterface"
            type="YourVendor\Module\Model\VariantUrlResolver"/>
```

**More sellable children than `has_variant_max`, or the setting at 0** — one `Product` whose offer is an `AggregateOffer` from the lowest child's price to the highest, rather than a variant list cut short that would misstate what the store sells. Children that all share one price keep a single `Offer`.

Why a limit at all: Google sets none. Each variant adds an offer to the page, so the setting bounds page weight and build time for configurables with very many children. Their availability is read as one batch however many there are (see [An offer's availability](#an-offers-availability)).

---

## Speakable

**Stores → Configuration → MageOS SEO → SEO → Answer Engine Optimization (AEO) → Enable Speakable Schema** (off by default) marks page sections for text-to-speech, with the CSS selectors in **Speakable CSS Selectors** (defaults: `.page-title`, `.product.attribute.overview`, `.category-description`).

The `speakable` property sits on the node that describes the page — Google: *"Speakable is used by the Article or Webpage object"* — built once by `Model\StructuredData\SpeakableSpecification`:

| Page | Node carrying `speakable` |
|---|---|
| CMS page, home page included | its `WebPage`, `{url}#webpage` |
| Category | its `CollectionPage`, `{url}#collectionpage` |
| Blog post (from a bridge's article data) | its `BlogPosting`, `{url}#article` |
| Product | a `WebPage` for the page, `{url}#webpage`, whose `mainEntity` is the product node `{url}#product` — `speakable` isn't a Product property |
| Anything else (search, contact, account…) | none — there is no page node to carry it |

```json
{
  "@type": "WebPage",
  "@id": "https://example.com/tee.html#webpage",
  "url": "https://example.com/tee.html",
  "name": "Tee",
  "mainEntity": { "@id": "https://example.com/tee.html#product" },
  "speakable": { "@type": "SpeakableSpecification", "cssSelector": [".page-title", ".product.attribute.overview"] }
}
```

Only `cssSelector` is emitted: Google takes `cssSelector` or `xPath`, never both. Google's own use of speakable is narrow — beta, users in the U.S. with Google Home devices set to English, news content read aloud by Google Assistant — which is why the setting is off by default.

---

## Master on/off switch

**Stores → Configuration → MageOS SEO → SEO → Structured Data (JSON-LD) → Enable JSON-LD Output**

When disabled, the `Block\JsonLd` block renders an empty string. No JSON-LD is output anywhere on the site. This is a per-store-view setting.

---

## The theme's microdata

**Stores → Configuration → MageOS SEO → SEO → Structured Data (JSON-LD) → Remove the Theme's
Product Microdata** (default Yes, per store view).

Magento_Catalog's product page layout, which Luma and Blank render, marks the product up in
schema.org microdata as well: `itemscope itemtype="http://schema.org/Product"` on the body and the
page wrapper, `itemprop` on the name, SKU, description, image and price, and `<meta itemprop>`
elements for values the page doesn't show. Magento_Review nests its AggregateRating and Review items
in it. Search engines read that as a second Product beside this module's JSON-LD, with fewer
properties and its own idea of the offer.

With the setting on, `Plugin\View\RemoveThemeMicrodata` removes it from the product page's HTML
after the page is rendered, the way core's `JsFooterPlugin` rewrites the body:

- the attributes `itemscope`, `itemtype`, `itemprop`, `itemid` and `itemref`, everywhere on the
  page: the reviews go with the Product, or they would be Reviews of nothing;
- `<meta>` and `<link>` elements that carry `itemprop`, which exist only for the microdata.

The elements themselves stay. It runs only when the page carries this module's Product JSON-LD
(`SchemaRegistry` has a node), so a page is never left with no product markup: with the JSON-LD
off, the theme's microdata stays. `<script>` and `<style>` contents are left untouched. The page is
rewritten before the page cache stores it. If the rewrite fails, the page is sent as rendered and a
warning is logged.

Set it to No to keep the theme's microdata.

---

## XSS protection

The compositor encodes the JSON with:

```php
json_encode($schemas, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
```

`JSON_HEX_TAG` and `JSON_HEX_AMP` write `<`, `>` and `&` as `<`, `>` and `&`, so
neither `</script>` nor `<!--` can appear inside the inline `<script>` and the output stays valid
JSON. A `str_replace()` after encoding cannot promise both. `Block\ItemListJsonLd`, and MageOS_Faq's
`Block\FaqJsonLd`, encode the same way. Do not bypass this or add your own raw `json_encode()`
output to `<head>`.

---

## Caching

All JSON-LD output is fully FPC-cacheable. The `Block\JsonLd` block has no `cacheable="false"` attribute. Data is URL-keyed; Varnish and the FPC cache one variant per unique URL, so paginated category pages (`?p=2`) get their own correct cache entries.

Organisation data is not configuration: it comes from its own table, `mageos_seo_organization`. `Block\JsonLd` sits on every page and carries the Organisation cache tag (`Model\Organization::CACHE_TAG`, `mageos_seo_organization`), so every cached page is tagged with it. Saving or deleting an Organisation record cleans that tag — core's `AbstractModel` dispatches `clean_cache_by_tags` after either — and the FPC and Varnish drop every page that showed the old values. No cache type is invalidated and nothing needs flushing by hand.

---

## Adding a new provider

See [extending.md](extending.md) for step-by-step instructions.
