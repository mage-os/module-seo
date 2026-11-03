# MageOS_Seo — Documentation

The SEO module provides structured data (JSON-LD), Open Graph meta tags, canonical URL management, robots meta control, XML sitemaps, and per-category and per-product SEO configuration for MageOS / Magento 2. The llms documents and AI-crawler directives are in [MageOS_Aeo](https://github.com/mage-os-lab/module-aeo).

---

## Feature docs

| Topic | File | Who it's for |
|---|---|---|
| [Structured Data (JSON-LD)](structured-data.md) | How JSON-LD output works, which schemas are produced on which pages | Developer / SEO manager |
| [Product Schema Templates](product-schema-templates.md) | The 16 built-in product templates and how to add new ones | Developer / merchandiser |
| [Organisation Settings](organisation.md) | Configuring site identity, logo, socials, contact — per store/website | Admin / developer |
| [Open Graph Tags](og-tags.md) | OG tags on product, category and CMS pages, and the per-page Open Graph fields | Admin / SEO manager |
| [Canonical URLs](canonical-urls.md) | How canonicals are managed and deduplicated | Developer / SEO manager |
| [Robots Meta](robots-meta.md) | Global defaults and per-page overrides | Admin / SEO manager |
| [Per-Category SEO](category-seo.md) | Schema template, field config, robots, ItemList per category | Admin / merchandiser |
| [Per-Product SEO](product-seo.md) | Field overrides and robots meta per product per store | Admin / merchandiser |
| [AI Discoverability (llms.txt)](https://github.com/mage-os-lab/module-aeo/blob/main/docs/llms-txt.md) | In MageOS_Aeo: `/llms.txt`, `/llms-full.txt` and `/llms.jsonl` — what they contain | Admin / developer |
| [Hreflang Alternates & Sitemap](hreflang.md) | Head alternates and those in `sitemap.xml` — what appears in them | Developer / SEO manager |
| [XML Sitemap](sitemap.md) | The sitemap generator, its file layout, rebuilding on change, and how to extend it | Developer / SEO manager |
| [When SEO Files Are Out of Date](rebuild-problems.md) | The admin message when a sitemap or feed rebuild fails, when it is retried, and how to retry sooner | Admin / developer |
| [UCP Profile](https://github.com/mage-os-lab/module-agentic/blob/main/docs/ucp.md) | In MageOS_Agentic: `/.well-known/ucp` — what it declares, registering a UCP service or capability, signing keys | Developer |
| [Pre-generated Feeds](https://github.com/mage-os-lab/module-aeo/blob/main/docs/feeds.md) | In MageOS_Aeo: the machinery behind the three llms documents — rebuilds, caching, storage, multi-server, CLI | Developer / DevOps |
| [Extending the Module](extending.md) | Adding providers, builders, and section content | Developer |
| [Translations](translations.md) | The shipped locales, what is translated, overriding a phrase, adding a locale | Admin / developer |

---

## Quick setup checklist

After installing and running `bin/magento setup:upgrade`:

1. Go to **Marketing → SEO → Organization** and fill in Name, URL, Description, Logo, and any social profiles. Without this, JSON-LD (and MageOS_Aeo's `/llms.txt`, when installed) will output empty values.
2. Go to **Stores → Configuration → MageOS SEO** and verify the defaults in its two sections (SEO, SEO Merchant Policies) suit your store.
3. Assign a schema template to each top-level category via **Catalog → Categories → SEO (Structured Data) tab**.
4. Flush the cache.

---

## Admin menu locations

| Menu path | Purpose |
|---|---|
| Marketing → SEO → Organization | Site identity settings — name, URL, logo, socials |
| Stores → Configuration → MageOS SEO | All feature toggles and defaults, in two sections (MageOS_Aeo and MageOS_Agentic add one each) |
| Catalog → Categories → (open a category) → SEO (Structured Data) | Per-category schema template and overrides |
| Catalog → Products → (open a product) → Advanced SEO | Per-product field overrides and robots |

---

## Database tables

| Table | Purpose |
|---|---|
| `mageos_seo_organization` | Organisation identity settings, one row per scope (store/website/default) |
| `mageos_seo_category_config` | Per-category SEO overrides, one row per category per store view |
| `mageos_seo_product_override` | Per-product field overrides, one row per product per store view |

The Open Graph title, description and image are EAV attributes on products and categories
(`og_title`, `og_description`, `og_image`). On CMS pages they are `cms_page` columns of the same
names. See [og-tags.md](og-tags.md#setting-them-per-page).

The FAQ table, `mageos_faq`, is [MageOS_Faq](https://github.com/mage-os-lab/module-faq)'s.

Records go when what they describe goes. `mageos_seo_category_config` and
`mageos_seo_product_override` carry foreign keys with `ON DELETE CASCADE`
to `catalog_category_entity`, `catalog_product_entity` and `store`, so deleting a category, a
product or a store view removes its SEO records with it — including when a deleted website or
store group takes its store views down with it, which happens in the database without any event
a module could observe.

`mageos_seo_organization` is the exception: its `scope_id` points at a website or a store view
depending on the `scope` column, which no single foreign key can express (`core_config_data` is
built the same way). `MageOS\Seo\Observer\RemoveOrganizationOnScopeDelete` clears it instead,
exactly as core clears its configuration table from `Website::beforeDelete()` and
`Group::beforeDelete()`.

---

## System config paths (for programmatic access)

All paths live under `mageos_seo_general/`:

| Path | Default | Notes |
|---|---|---|
| `mageos_seo_general/og_tags/enabled` | 1 | Master switch for OG tags |
| `mageos_seo_general/og_tags/resize_image` | 0 | Resize the product `og:image` to 1200 × 630, with `og:image:width` / `og:image:height`. See [og-tags.md](og-tags.md#image-size) |
| `mageos_seo_general/structured_data/enabled` | 1 | Master switch for JSON-LD |
| `mageos_seo_general/structured_data/default_product_template` | GenericProduct | Fallback template |
| `mageos_seo_general/structured_data/category_item_list_enabled` | 1 | ItemList on category pages |
| `mageos_seo_general/structured_data/category_item_list_max` | 36 | Max items in ItemList |
| `mageos_seo_general/structured_data/has_variant_max` | 50 | Most sellable children a configurable product may have and still be a ProductGroup of its variants; more gets one AggregateOffer (0 = always the AggregateOffer). Global only. See [structured-data.md](structured-data.md#configurable-products) |
| `mageos_seo_general/structured_data/publish_zero_price` | 0 | Publish a Price of Zero: 0 leaves a price of 0 out of the structured data, Open Graph and llms.jsonl (usually a price hidden from the visitor); 1 offers a simple product priced 0 as free. See [structured-data.md](structured-data.md) |
| `mageos_seo_general/canonical/cms_enabled` | 1 | Canonical link on CMS pages and the home page. See [canonical-urls.md](canonical-urls.md#where-canonicals-are-set) |
| `mageos_seo_general/hreflang/enabled` | 1 | Hreflang alternates in the page head. See [hreflang.md](hreflang.md) |
| `mageos_seo_general/robots_meta/product_default` | *(empty)* | Default for product pages (empty = Magento's Design → Search Engine Robots setting) |
| `mageos_seo_general/robots_meta/category_default` | *(empty)* | Default for category pages (empty = Magento's Design → Search Engine Robots setting) |
| `mageos_seo_general/robots_meta/search_default` | *(empty)* | Default for search result pages, quick and advanced (empty = Magento's Design → Search Engine Robots setting). See [robots-meta.md](robots-meta.md#global-defaults) on robots meta against robots.txt |

All paths support store-view and website scope except `has_variant_max`, which is global only.
