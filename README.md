# MageOS_Seo

A comprehensive Magento 2 **SEO + AEO** module: JSON-LD structured data, Open Graph / Twitter meta, canonical & robots management, hreflang, sitemaps, and answer-engine identity (LocalBusiness / Article / Event / Speakable). Three modules build on it: [MageOS_Faq](https://github.com/mage-os-lab/module-faq) adds FAQ rich results, [MageOS_Aeo](https://github.com/mage-os-lab/module-aeo) the `/llms.txt`, `/llms-full.txt` and `/llms.jsonl` documents and AI-crawler robots directives, and [MageOS_Agentic](https://github.com/mage-os-lab/module-agentic) the `/.well-known/ucp` agentic commerce profile.

Every cross-cutting concern is built as an **extensible provider pool** — a second module contributes a provider through its own `di.xml` without ever editing this module.

---

## What it covers

- **SEO** — structured data, meta tags, canonicals, robots meta, pagination handling, hreflang.
- **AEO** (Answer Engine Optimisation) — LocalBusiness, Article, Event, Speakable, aggregate ratings, merchant policies, and the FAQ source pool that FAQ rich results draw on.
- **The shared layer its companion modules build on** — the rebuild queue and `seo:rebuild` command, the FAQ source pool, the Organization, the public-path registry, and the admin tab and ACL resource their settings sit under.

---

## Features

### Structured data (JSON-LD)

- Organization, WebSite, BreadcrumbList, CollectionPage, and per-product schemas output as `<script type="application/ld+json">` in `<head>`, all cross-referenced by a shared `@id`.
- **16 product schema templates** — GenericProduct, Food, Apparel, Jewelry, HomeDecor, Book, Software, Toy, HealthProduct, Cosmetics, Pet, ArtAndCraft, ElectronicsSimple, Tool, Stationery, LocalExperience.
- **Configurable products as a ProductGroup** — each sellable child a variant with its own offer, `variesBy` from the product's own configurable attributes, variant URLs that preselect the options; above a configurable limit, one **AggregateOffer** (lowPrice/highPrice) over the children's prices.
- **Aggregate ratings** — a priority pool with a native Magento reviews provider built in; review vendors (Yotpo, Trustpilot, …) plug in a higher-priority provider.
- **Merchant policies** — shipping details, return policy, and item condition merged into product offers via an offer-enricher pool (required for Google Merchant free listings).

### Meta & crawl control

- **Open Graph + Twitter** — og:title/description/image/type/site_name/locale on product, category, CMS and site pages, and the matching X card tags: `summary_large_image` or `summary` by whether the page has an image, with the title, description and image repeated as `twitter:*`. Each product, category and CMS page can set its own Open Graph title, description and image. Otherwise the meta title and description are used. Product images can be published at 1200 × 630, with their size. See [docs/og-tags.md](docs/og-tags.md).
- **Canonical URL management** — automatic canonicals on CMS and home pages, deduplicated if one is already present. Product and category canonicals are Magento core's, **off by default** (Catalog → Search Engine Optimization); `CanonicalUrlManagerInterface` lets a module such as a product-variant URL module replace one. See [docs/canonical-urls.md](docs/canonical-urls.md).
- **Robots meta pool** — global INDEX/FOLLOW defaults for product, category, **and CMS** pages, overridable per category/product, plus a pagination provider for `?p=N` pages and enriched directives (`max-snippet`, `max-image-preview`, `noarchive`, `noai`, …).

### Multistore

- **hreflang** — `<head>` alternate links, and the same alternates inline in `sitemap.xml`, with language-only and x-default handling. Resolvers are a pool (product/category/CMS built in; vendor/blog pages plug in).

### Answer-engine (AEO)

- **FAQ source pool** — `FaqSourceProviderInterface` sources (empty by default) that MageOS_Aeo's llms documents read their FAQ groups from. [MageOS_Faq](https://github.com/mage-os-lab/module-faq) registers its FAQ table here, and adds the FAQ Manager, widget, Page Builder content type and FAQPage JSON-LD.
- **LocalBusiness** — address, telephone and email on every organisation type; geo coordinates and price range for local businesses.
- **Article / Event / Speakable** — bridge pools (empty by default) fed by blog/event modules, plus a configurable Speakable selector set.

### Translations

- **en_US** (the source), **en_GB** and **nl_NL**. The admin follows the admin user's locale. See [docs/translations.md](docs/translations.md).

---

## Requirements

- PHP 8.3 – 8.5
- Magento Open Source / Mage-OS **2.4.7 or newer** (`magento/framework
  ^103.0.7`), including 2.4.9 on PHP 8.5. The module runs unmodified across the
  whole range and carries no version polyfills: 2.4.7 is the first release with
  `Magento\Framework\ObjectManager\ResetAfterRequestInterface`, which the
  request-scoped services implement directly.
- Magento MSI (`Inventory*`) modules — optional. While `Magento_InventorySales`
  and `Magento_InventoryIndexer` are installed and enabled, product availability
  is read from MSI's stock index for each website's stock; without them, from
  CatalogInventory's. Composer suggests `magento/module-inventory-sales`,
  `-sales-api` and `-indexer`
  ([structured-data.md](docs/structured-data.md#an-offers-availability)).

---

## Installation

```bash
composer require mage-os/module-seo
bin/magento module:enable MageOS_Seo
bin/magento setup:upgrade
bin/magento cache:flush
```

This module replaces `mage-os/module-meta-robots-tag` and `mage-os/module-hreflang` and conflicts
with both, so it does not install on Mage-OS 3.x. Remove them in the same step — don't disable them
(see [docs/robots-meta.md](docs/robots-meta.md#removing-it)):

```bash
composer remove mage-os/module-meta-robots-tag mage-os/module-hreflang --no-update
composer require mage-os/module-seo
```

---

## Important: configure Organisation before going live

> **The module works immediately after install, but structured data and the Organisation JSON-LD node will be empty until you fill in the Organisation details** (and so will MageOS_Aeo's `/llms.txt`, when installed).

Go to **Marketing > SEO > Organization** and complete all fields before putting the site live.

| Field | Purpose |
| --- | --- |
| Name | Your organisation's display name (falls back to store name in llms.txt if blank) |
| URL | Canonical URL of your organisation (e.g. `https://example.com`) |
| Description | Short tagline — shown in JSON-LD and at the top of `/llms.txt` |
| Organisation type | Schema.org `@type`: Organization, Corporation, NGO, etc. |
| Logo | The theme's header logo, or an uploaded image |
| Logo width / height | Pixel dimensions — required for valid Organization schema |
| Social profiles | Social profile URLs (Twitter, LinkedIn, etc.) |
| Contact point | contactType, email, availableLanguage for the ContactPoint node |
| Local presence | Address, geo coordinates, telephone, email, price range (LocalBusiness AEO) |

Without a Name and URL saved, the Organization node in JSON-LD will render with empty values, which search engines and validators will flag as invalid.

---

## Admin configuration

**Stores > Configuration > MageOS SEO** holds two sections. MageOS_Aeo, when installed, adds AI Information & Crawlers, and MageOS_Agentic adds Agentic Commerce (UCP).

### SEO Configuration (`mageos_seo_general`)

| Group | Key settings | Default |
| --- | --- | --- |
| Open Graph Tags | Enable OG/Twitter tags, publish product images at the Open Graph size (1200 × 630) | Yes / No |

Category and CMS page Open Graph images are Media Gallery renditions. Set **Advanced > System > Media
Gallery Image Optimization** to 1200 × 630 (`system/media_gallery_renditions/width` and `height`). See
[og-tags.md](docs/og-tags.md#rendition-size).


| Structured Data (JSON-LD) | Master switch, default product template, ItemList toggle & max, most variants per configurable product, aggregate rating, publish a price of zero | Yes / GenericProduct |
| Canonical URLs | Canonical link on CMS pages and the home page | Yes |
| Robots Meta | Product / category / **CMS** / search results defaults, pagination policy | *(empty — Magento default applies)* |
| Hreflang | Enable, language-only, sitemap | Yes |
| Answer Engine (AEO) | Speakable toggle + CSS selectors | No |

### SEO Merchant Policies (`mageos_seo_merchant`)

| Group | Purpose | Default |
| --- | --- | --- |
| Item Condition | Default schema.org itemCondition on offers | NewCondition |
| Return Policy | `hasMerchantReturnPolicy` on offers | Off |
| Shipping Details | `OfferShippingDetails` on offers | Off |

---

## Per-category & per-product SEO

In the **category** edit form, an **SEO (Structured Data)** fieldset adds: schema template, enabled optional fields, field overrides, ItemList toggle, and robots meta. Template/field settings inherit from ancestor categories when unset.

In the **product** edit form, an **Advanced SEO** tab adds store-specific field overrides and a robots-meta override.

---

## FAQ rich results

FAQs, their admin, the widget, the Page Builder content type and the FAQPage JSON-LD are in [MageOS_Faq](https://github.com/mage-os-lab/module-faq). This module keeps the FAQ source pool it registers with.

---

## AI discoverability

`/llms.txt`, `/llms-full.txt`, `/llms.jsonl` and the AI-crawler directives in `robots.txt` are in [MageOS_Aeo](https://github.com/mage-os-lab/module-aeo). This module keeps the rebuild queue they are built through, and the Organization, locale and FAQ sources they describe.

---

## Product schema templates

Each template maps to a `ProductSchemaBuilderInterface` implementation. Every template emits a
`schema.org/Product` node (Google's Product rich results and merchant listings require the
Product type); templates for creative works add a secondary type alongside Product, and
category-specific data with no valid Product property is expressed via `additionalProperty`:

| Code | Label | Schema type |
| --- | --- | --- |
| GenericProduct | Generic Product | Product |
| Food | Food & Grocery | Product |
| Apparel | Clothing & Apparel | Product |
| Jewelry | Jewelry | Product |
| HomeDecor | Home Decor & Furniture | Product |
| Book | Books | Product + Book |
| Software | Software & Apps | Product + SoftwareApplication |
| Toy | Toys & Games | Product |
| HealthProduct | Health & Wellness | Product |
| Cosmetics | Beauty & Cosmetics | Product |
| Pet | Pet Supplies | Product |
| ArtAndCraft | Art & Craft | Product + VisualArtwork |
| ElectronicsSimple | Electronics | Product |
| Tool | Tools & Hardware | Product |
| Stationery | Stationery & Office | Product |
| LocalExperience | Local Experience | Product |

The default template (`GenericProduct`) is used when no template is configured for the product's category. Change it under **Stores > Configuration > MageOS SEO > SEO > Structured Data (JSON-LD) > Default Product Schema Template**.

---

## Extending the module

Every cross-cutting concern is a provider pool wired via `di.xml`, so another module contributes a provider from its **own** `di.xml` without modifying this one. Most provider interfaces expose `getHandles()` (`['*']` = all pages) for layout-handle scoping; resolution is *collect-all*, *one winner* (highest sortOrder or priority), or *a lookup by key* (template code, path segment, group name).

| Extension point | Interface | Resolution |
| --- | --- | --- |
| Structured data providers | `StructuredDataProviderInterface` | collect-all |
| Meta tag providers | `MetaTagProviderInterface` | collect-all |
| Page title providers | `PageTitleProviderInterface` | highest sortOrder with a title wins |
| Product schema builders | `ProductSchemaBuilderInterface` | by template code |
| Robots meta providers | `RobotsMetaProviderInterface` | highest sortOrder with a value wins |
| Aggregate rating providers | `AggregateRatingProviderInterface` | highest-priority non-null |
| Offer enrichers | `OfferEnricherInterface` | collect-all (merged into every product and variant offer) |
| Variant URLs | `ProductVariantUrlResolverInterface` | one (di.xml preference) |
| Hreflang resolvers | `HreflangResolverInterface` | collect-all |
| Article / Event data providers | `ArticleDataProviderInterface` / `EventDataProviderInterface` | collect-all |
| FAQ source providers | `FaqSourceProviderInterface` | collect-all |
| Rebuild queue groups | `Api\Rebuild\GroupHandlerInterface` | by group name ([docs/extending.md](docs/extending.md#rebuilding-your-own-output-through-the-queue)) |
| Public documents (no session) | `Api\Router\PublicPathsInterface` (di.xml `paths` / `prefixes`) | lookup by path |

Example — add a robots-meta provider for blog pages from your module's `di.xml`:

```xml
<type name="MageOS\Seo\Model\RobotsMeta\Resolver">
    <arguments>
        <argument name="providers" xsi:type="array">
            <item name="blog" xsi:type="object">Vendor\Blog\Model\BlogRobotsProvider</item>
        </argument>
    </arguments>
</type>
```

---

## Development

```bash
composer install

# Run all quality gates
composer test

# Or individually
vendor/bin/phpunit -c phpunit.xml.dist --testsuite unit
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/php-cs-fixer fix --dry-run --diff --allow-risky=yes
vendor/bin/phpcs --standard=phpcs.xml.dist
XDEBUG_MODE=coverage vendor/bin/infection --threads=4  # gate: minMsi in infection.json5
```

Integration tests live under `Test/Integration/` and run in CI against a live Magento install via [`graycoreio/github-actions-magento2`](https://github.com/graycoreio/github-actions-magento2). They cannot be run locally without a full Magento installation.
