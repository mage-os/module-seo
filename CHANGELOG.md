# Changelog

All notable changes to this module are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow
[Semantic Versioning](https://semver.org/). Releases are cut from git tags —
the tag is the source of truth for the version (composer.json carries no
hardcoded version field).

## [Unreleased]

### Fixed

- **One BreadcrumbList on Hyvä, not two**
  ([#55](https://github.com/mage-os/module-seo/issues/55)). Hyvä's breadcrumbs template prints its
  own BreadcrumbList JSON-LD unless the block's `skip_ld_json_schema` flag is set, so Hyvä pages
  carried the theme's and this module's. A frontend plugin on `Magento\Theme\Block\Html\Breadcrumbs`
  now sets the flag while structured data is enabled and the page is not one of the provider's
  `excludedHandles`. It covers subclasses and blocks recreated after layout. Luma's template never
  reads the flag.

## [2.2.1] — 2026-10-29

### Fixed

- **No `aggregateRating` when a product's reviews carry no rating votes**
  ([#47](https://github.com/mage-os-lab/module-seo/issues/47)). Magento counts every approved review
  in a store but averages only their votes, so a store whose reviews have none — no rating is
  assigned to that store view, or the reviewer picked none — has a review summary of 0. The native
  provider emitted that as `"ratingValue": "0"` beside `"worstRating": "1"`, which Search Console
  reports as "Rating value is out of range". It now returns no rating there, as it does for a
  product with no reviews, so the Product carries no `aggregateRating`. One star is the lowest
  rating a vote gives, and still comes out as `1`.

## [2.2.0] — 2026-10-28

### Changed

- **Conflicts with `mage-os/module-meta-robots-tag` and `mage-os/module-hreflang`**, which this
  module replaces in Mage-OS 4.0. Mage-OS 3.x stays on 2.1.
- **Remove those modules, don't disable them:** `setup:upgrade` drops the `cms_page` columns of a
  disabled module before the import can read them. The docs and the System Message now say so.
- **MSI is optional** (2.0.0 required it). Composer suggests `magento/module-inventory-sales`,
  `-sales-api` and `-indexer` instead of requiring them, and `module:disable` no longer refuses to
  disable them.
  - While `Magento_InventorySales` and `Magento_InventoryIndexer` are installed and enabled,
    availability is read from MSI's stock index, as before.
  - Without them it is read from CatalogInventory's, `cataloginventory_stock_status`. That is the
    table MSI reads for its default stock, so a website on the default stock gets the same answers.
  - `setup:di:compile` works with MSI's packages removed: no constructor, parent, interface or
    di.xml of this module names an MSI class, and a unit test keeps it so.
  - See [structured-data.md](docs/structured-data.md#without-msi).
- **`magento/module-inventory-configuration` and `-configuration-api` are no longer required.**
  Which product types have a quantity of their own is read from CatalogInventory's
  `getIsQtyTypeIds()`, which is what MSI's check reads too.
- **Constructor:** `Model\Product\AvailabilityResolver` takes `Model\Product\Stock\StockReaderInterface`
  in place of `StoreManagerInterface` and MSI's `StockResolverInterface`,
  `GetStockItemsDataInterface` and `IsSourceItemManagementAllowedForProductTypeInterface`. The class
  is not `@api`. The availability it gives through `Api\Product\AvailabilityResolverInterface` is
  unchanged.

## [2.1.0] — 2026-10-20

### Fixed

- **A sitemap in `pub/` generates and deletes cleanly**
  ([#43](https://github.com/mage-os-lab/module-seo/issues/43)). With the sitemap path `/`, Magento's
  default, generation failed after writing its files, on every run, and its stale files were never
  removed; deleting the sitemap left its files too. Magento's directory listing passes every entry
  through its deny list, which refuses `pub/.htaccess`. The sitemap's directory is now listed
  through its driver (`Model\Sitemap\DirectoryNames`), which validates the directory itself and
  lists local disk and remote storage alike. `Model\Sitemap\Writer` and `Model\Sitemap\FileRemover`
  take it.

## [2.0.0] — 2026-10-12

The next release is **2.0.0**: other modules now reach this one through `Api` interfaces, and MSI
is required. MageOS_Aeo, MageOS_Faq and MageOS_Agentic require `^2.0`.

### Upgrading to 2.0.0

Every breaking change since 1.2.1, each described under its heading below:

- **MSI is required.** Composer now requires `magento/module-inventory-sales`,
  `-inventory-indexer` and `-inventory-configuration`, and `module:disable` refuses to disable
  them while this module is enabled. Availability is read through their service contracts.
- **Type against the `Api` interfaces.** Every class another module uses has an interface in
  `Api/`, with a preference to the class (the table in `docs/extending.md`, "The module's
  contract"). The classes themselves are not `@api`: their constructors may change in any
  release, and some do in this one. A di.xml that fills `HandlerPool`, `PublicPaths` or the FAQ
  `SourcePool` can name the interface instead; the class name still works.
- **`Service\CurrencyService` is removed.** `Api\SchemaPriceInterface` (`Model\Price\SchemaPrice`)
  formats an amount as `0.00` and gives the current currency code, which was all of it that was
  used.
- **`CanonicalUrlManager::setCanonical()` takes two arguments.** The third, `$urlKey`, was ignored;
  a call passing it fails. Type against `Api\CanonicalUrlManagerInterface`.
- **`ProblemLog::ALL` is `Api\Rebuild\ProblemLogInterface::ALL`.**
- **`AvailabilityResolver::resolve()` and `best()` return null** when the stock could not be read,
  where they returned OutOfStock.
- **`Api\Data\OrganizationInterface` has `getLogoSource()` and `setLogoSource()`.** A class
  implementing the interface itself must add them. `getLogoPath()` is the custom logo only.
- **Search results pages default to `NOINDEX,FOLLOW`.** A value already saved is kept.
- **The ACL resources moved;** their IDs did not, so roles keep their grants.
- **The `rs_` attribute fallbacks are gone.**
- **Product pages lose Luma's microdata** while the structured data is on: set **Remove the
  Theme's Product Microdata** to No to keep it.
- **Sitemap file names have a set** (`…-a-1.xml`, `…-b-1.xml`); the index keeps its name.
- **Shipping rates are in the base currency.** Re-enter one entered in a different display
  currency.
- **Flush the page cache once after upgrading:** pages cached before carry none of the new cache
  tags.
- **Constructors changed** throughout; a subclass that overrides one follows. The lists are under
  Changed.

### Added

- **`Api` interfaces for every class the companion modules use** (review M8, decision D7b):
  - `Api\Rebuild\`: `RegenerationRequesterInterface`, `InvalidatorInterface`,
    `ProblemLogInterface`, `ChangeInspectorInterface`, `HandlerPoolInterface`;
  - `Api\Router\`: `PublicPathsInterface`, `CanonicalPathRedirectInterface`;
  - `Api\Product\`: `AvailabilityResolverInterface`, `FinalPriceInterface`,
    `SchemaTemplatesInterface`, `OfferSummaryInterface`;
  - `Api\Robots\PageDirectivesInterface`;
  - `Api\`: `SchemaPriceInterface`, `FaqSourcePoolInterface`, `ContactEmailInterface`,
    `StorefrontSettingsInterface`, `CanonicalUrlManagerInterface`.

  Each holds what other modules use and nothing more, and is `@api`. Two classes stay classes,
  extended without `@api`: `Exception\RebuildInProgressException` and
  `Model\ResourceModel\AbstractConnectedResource`. See `docs/extending.md`, "The module's contract".
- **`Model\Product\OfferSummary`** (`Api\Product\OfferSummaryInterface`): a product's price, a
  configurable's price range and its best variant's availability, as the offer publishes them.
  The offer is built from it, and MageOS_Aeo's llms.jsonl reads it, so the two agree.
- **`Model\RobotsMeta\PageDirectives`** (`Api\Robots\PageDirectivesInterface`): the robots
  directive each product and category page has, categories inherited along their path, core's
  default where this module has none. The sitemap leaves NOINDEX pages out through it, and
  MageOS_Aeo's feeds do too.
- **A feed reads a page of configurables' variants at once.** `Api\Product\OfferSummaryInterface`
  has `prefetch()`: the variants of a page of products in one of core's child collections
  (`Model\Product\Variant\ChildLoader`, `Model\ResourceModel\VariantLinks`), their stock in one
  lookup. Read one configurable at a time through core's provider, each cost ten queries, its
  gallery and configurable attributes among them; MageOS_Aeo's llms.jsonl build on Luma's sample
  catalogue went from 2,440 queries to 821. Product pages keep core's path.
- **`Api\Product\VariantImageInterface`:** a product's base image, or for a configurable without
  one, its first sold variant's, from the variants already read. Core's `getImage()` loads all of a
  configurable's variants for that.
- **The variants described are the ones the storefront sells, in every area.** Core registers its
  configurable status and stock filters for the frontend only, so a feed built by the queue or cron
  included disabled variants. `Model\Product\Variant\StorefrontOptionsFilter` (di.xml) applies both
  wherever `ChildProducts` is asked. A module that adds a filter to core's frontend composite adds
  it there too, for the feeds. `ChildProducts` takes the filter, `VariantLinks`, `ChildLoader` and a
  logger.
- **Remove the Theme's Product Microdata** (Structured Data (JSON-LD), default Yes, per store
  view; review M9, decision D4a). Luma's product page marks the product up in microdata, its name,
  price, image and reviews, which search engines read as a second Product beside this module's.
  While the page has this module's Product JSON-LD, the microdata attributes and `<meta>`/`<link>`
  elements are removed from the product page (`Plugin\View\RemoveThemeMicrodata`). Scripts and
  styles are left alone. See `docs/structured-data.md`, "The theme's microdata".

### Fixed

- **A product's schema template no longer follows the visitor's browsing** (review M6, decision
  D9b). It came from the category the session last visited, so the page cache kept whichever
  template the first visitor's history gave. It now comes from the URL's category when that is one
  of the product's categories under the store's root, else from the product's deepest category
  under the root, ties going to the lowest ID (`Model\Product\SchemaCategory`).
- **A stock lookup that fails no longer says OutOfStock** (decision D8a). The offer, Open Graph
  and MageOS_Aeo's llms.jsonl leave availability out instead, and the failure is logged as before.
- **Open Graph's `product:availability` is read from the stock index,** as the structured data's
  is, and uses Meta's values: `in stock`, `available for order` (backorders) or `out of stock`.
  It came from `isSalable()` and wrote `oos` for out of stock. With the stock unreadable, the tag is
  left out.
- **A CMS page's `og:image` renders no widget twice** (review M10). The whole content went through
  the page filter to find the first image, rendering every widget on it a second time, and any
  failure was swallowed. Only the first image's `src` is filtered now, so `{{media url=…}}` still
  resolves, and a failure is logged.
- **An article or event no longer points at an Organization the page doesn't have.** A BlogPosting's
  author and publisher, and an Event's organizer, referenced the Organization's `@id` whether or not
  one was published; with none configured it was the bare `/#organization`, which no node carries.
  They are now left out until the Organization has a name, the point at which it is published
  (`Model\StructuredData\OrganizationId::getPublishedId()`, which replaces `getId()`, and
  `isPublished()`, which `OrganizationProvider` now asks too). No module registered an article or
  event provider until mage-os/module-blog-seo, so nothing showed it before.
- **`/llms.txt/`, with a trailing slash, 301s to `/llms.txt`** (`CanonicalPathRedirect`), as a
  query string does. It was served as a second copy, with its own cache entry. The same holds for
  every path the redirect guards, `/.well-known/ucp` included.
- **Shipping times no longer invent or invert a bound**
  ([#32](https://github.com/mage-os-lab/module-seo/issues/32)). A missing minimum or maximum was
  published as 0, so a minimum of 2 alone became 2 to 0 days, and an inverted, negative or
  fractional value was published as it stood. A time is now a maximum number of days, with a minimum
  or without, as Google's shipping-policy reference reads it. The admin refuses the rest, whether
  saved in the form or with `bin/magento config:set`. A value stored past that check is left out,
  and logged at most once every five minutes per store view.
- **The shipping rate is converted to the currency shown**
  ([#33](https://github.com/mage-os-lab/module-seo/issues/33)). The configured number was
  published under the display currency's code without converting it. The rate is now in the
  base currency, as product prices and core's flat-rate shipping are, and is converted to the
  currency the visitor sees. **A store whose display currency differs from its base currency, and
  which entered the rate in the display currency, should enter it again in the base currency.**
- **A missing shipping rate is no longer published as free shipping.** A blank rate became 0.00. A
  rate is now required while shipping details are on, and must be an amount of 0 or more. Without
  one, or with one that cannot be converted, the shipping details are left out.
- **Changing SEO settings purges the cached pages built from them**
  ([#21](https://github.com/mage-os-lab/module-seo/issues/21)). Pages carried only the
  Organization's cache tag, so a change to a category's, product's or CMS page's SEO settings was
  served from the page cache until it expired. A category's change missed most: the categories
  beneath it inherit its settings and the products in them read it for their schema. Each page now
  carries a tag for every entity whose settings it read, ancestors included. Every write to the
  settings — the admin forms, imports, the repositories — purges those tags once committed, for
  the built-in cache and Varnish alike. Moving or deleting a category purges what inherited from
  it. A CMS page moving between translation groups purges both groups' pages, from any write, not
  only the admin form. **After upgrading, flush the page cache once:** pages cached before carry
  none of the new tags. Changes in Stores → Configuration still need the Page Cache refreshed, as
  core's do. See docs/extending.md, "Cache considerations".
- **Rebuilds are queued once the change is committed**
  ([#22](https://github.com/mage-os-lab/module-seo/issues/22)). A save queued its rebuild during
  the save, before the commit: on AMQP a consumer could rebuild from the data before the change,
  and a rolled-back change was still rebuilt. `RegenerationRequester::request()` inside a
  transaction now waits for the commit, and a rollback drops it; outside one it queues at once.
- **A long-running consumer builds from current data**
  ([module-aeo #9](https://github.com/mage-os-lab/module-aeo/issues/9)). Magento resets nothing
  between a consumer's messages, so the Organization, stock IDs, the sitemap list and the
  configuration stayed as the first message loaded them. Before each build the consumer now resets
  the services listed for `Model\Rebuild\BuildFreshness`, and reloads the configuration and store
  list when a configuration change was saved since.
- **The consumer no longer spins while another process holds a rebuild's lock.** It put the
  message straight back, and took it again at once. It now waits for the lock, up to 10 minutes,
  before putting it back.
- **Switching sitemap generation or Rebuild on Change on queues a rebuild.** It queued none:
  whether any sitemap is rebuilt was asked of the configuration before the save, when both were
  still off.
- **An offer says BackOrder only for a product that sells through backorders**
  ([#30](https://github.com/mage-os-lab/module-seo/issues/30)). A product that could only be
  backordered said InStock, and one nobody could order said BackOrder whenever backorders were
  configured. Availability is now read as the storefront reads it, from MSI's stock index:
  OutOfStock when not salable, InStock with a quantity above zero, BackOrder when salable at zero or
  below with stock managed. A configurable's offer takes its best variant's availability. Like the
  page, reservations are not subtracted. See `docs/structured-data.md`, "An offer's availability".
- **`priceValidUntil` belongs to the price published**
  ([#31](https://github.com/mage-os-lab/module-seo/issues/31)). A special price starting next month
  lent its end date to this month's price, and so did a special a lower catalog rule beat. The date
  is now published only for a special price core's `SpecialPrice` has in effect today and that is
  the price the offer publishes.
- **The offer's price is the price the page shows**
  ([#40](https://github.com/mage-os-lab/module-seo/issues/40)). On a store that shows prices
  including tax, the offer gave the price excluding it: the page said £340.00, the JSON-LD
  `283.33`. `Model\Product\FinalPrice` now reads the final price's amount with the adjustments the
  store displays, as core's price box does. That fixes the offer, an AggregateOffer's range, each
  variant's offer, Open Graph and MageOS_Aeo's llms.jsonl alike. A store that shows prices
  excluding tax sees no change.
- **A price of 0 is not published as free, unless you say so**
  ([#41](https://github.com/mage-os-lab/module-seo/issues/41)). A store that hides prices from
  guests showed `"price": "0.00"`, which Google reads as free. A final price of 0 is now unknown,
  so there is no price in the structured data, Open Graph or llms.jsonl, unless the new setting
  **Publish a Price of Zero** (Structured Data (JSON-LD), default No) is on; then a simple product
  priced 0 is offered as free. A price below 0 is never published.
- **Open Graph leaves out a price that is not known**
  ([#38](https://github.com/mage-os-lab/module-seo/issues/38)). An unavailable configurable said
  `product:price:amount` 0.00 while its structured data had no price, and a price that could not be
  read was dropped silently. Open Graph now takes the price from `Model\Product\FinalPrice`, as the
  structured data does: no price tags when it's unknown, and a failed read is logged.
- **A store view with hreflang off is no alternate**
  ([#28](https://github.com/mage-os-lab/module-seo/issues/28)). Enabled store views linked to it in
  the head and the sitemap, and it could be x-default, though its own pages link back to nothing.
  It could also take a code shared with an enabled store view of a higher ID. `StoreLocaleMap` now
  leaves it out, so none of these sees it.
- **Hreflang codes are checked whatever set them**
  ([#29](https://github.com/mage-os-lab/module-seo/issues/29)). Only the admin checked them: a code
  from `config.php`, `env.php`, `config:set` or the database was published as it was, malformed,
  with a region that doesn't exist (`en-UK`) or twice. The codes, and the locale code a store view
  falls back on, now pass the admin's own check as they are read; one that fails is left out and
  logged as a warning at most once every five minutes. A store view whose locale has no two-letter
  language, such as `fil_PH`, now needs a configured code to take part.
- **x-default can be set to none**
  ([#35](https://github.com/mage-os-lab/module-seo/issues/35)). The field said "Leave unset" but had
  no empty choice, and offered inactive store views. It now starts with "-- None --" and lists active
  store views only.
- **Clearing every excluded store view saves**
  ([#36](https://github.com/mage-os-lab/module-seo/issues/36)). An empty multiselect sent nothing, so
  the previous exclusions stayed.
- **A sitemap that fails to publish stays whole**
  ([#24](https://github.com/mage-os-lab/module-seo/issues/24)). The files replaced their names one
  at a time, then the index, so a failure part-way left the old index listing a mix of new and old
  files. And they were moved in from the system's temporary directory, which on most setups is
  another filesystem, where a move is a copy into the live name that a crawler can read
  half-written. Each kind of page now has two sets of files: a rebuild writes the set the index does
  not list, every file under a temporary name next to its own, and switches the index last. See
  `docs/sitemap.md`, "Two sets, one index".
- **Installations sharing a temporary directory no longer write over each other's sitemaps**
  ([#25](https://github.com/mage-os-lab/module-seo/issues/25)). Files were staged in the system's
  temporary directory under the sitemap's own path and name, so two installations with the same
  sitemap could publish each other's files. Nothing is staged outside the sitemap's directory now.
- **Sitemaps are written to remote storage.** With Magento_RemoteStorage, this module wrote the
  sitemap to the local `pub/` of the server generating it, while the admin grid and every other
  server looked in the bucket; every Site Map save and `setup:upgrade` then queued it again as
  missing. The Writer, the missing check and the clean-up are given `fullRemoteFilesystem`, as
  core's sitemap is.
- **Deleting a sitemap removes its files.** Magento's Delete removes only the index, and deleting a
  store view removes its entries without removing a file, so the rest stayed reachable, listed
  nowhere. They are removed once the deletion commits, unless another entry uses them.
- **A new name, image or caption rewrites the products' sitemap files**
  ([#23](https://github.com/mage-os-lab/module-seo/issues/23)). While a sitemap lists product
  images (Magento's default), a product's row carries its name as the image title, its images and
  their captions, but only a URL key, status, visibility or website change queued a rewrite; the
  rest waited for Magento's scheduled generation, which is off by default. A change to the name,
  base image or thumbnail — saved or mass-updated — or to the gallery now queues the products. The
  gallery is compared on what is shown — images, captions, hidden, position — so saving the
  product form unchanged queues nothing.
- **A product import rewrites the products' sitemap files.** Magento's import writes to the
  database directly, so no save reported it; the products are now queued once an import finishes.
- **A failed import from MageOS_MetaRobotsTag or MageOS_Hreflang is no longer recorded as done**
  ([#27](https://github.com/mage-os-lab/module-seo/issues/27)). The data patches caught the failure,
  so Magento recorded them as applied and never ran them again. A failed database write did not
  even reach that point: it made the upgrade itself fail. Now `setup:upgrade` tries each import
  once and never fails because of it, and whether it finished is the module's own record (the
  `mageos_seo_legacy_import` flag). While one has not finished, and its module left data behind,
  the admin shows a System Message. `bin/magento seo:import-legacy` runs the imports or repeats
  them, and `--dry-run` reports what they would write. An installation that ran the earlier
  imports has no record that they finished, so the message shows there until the command is run.
- **The imports never replace a value already set in this module**
  ([#26](https://github.com/mage-os-lab/module-seo/issues/26)). A product's, category's or CMS
  page's robots setting was overwritten with the one MageOS_MetaRobotsTag's flags gave, and the
  x-default store view with MageOS_Hreflang's. Each import now fills only what is empty, and
  reports each value it kept, with both values, in the log and the command's output. Where this
  module's hreflang is set to No for a store view that MageOS_Hreflang served, the No is kept, and
  MageOS_Hreflang stays on for that store view instead of being switched off.
- **MageOS_MetaRobotsTag's flags are imported per store view.** Its flags are store-scoped, and
  that module applies them to each store view's own default robots, but the import read them at
  one store view and resolved them against the default scope's robots only. A flag set for one store
  view was applied to all of them, and a store view with another default, such as a staging store
  view's `NOINDEX,NOFOLLOW`, got the wrong directive. Each store view an entity is on now gets the
  directive it was served: one setting for all store views where they agree, one per store view
  where they differ. Once the import has finished, the admin advises disabling MageOS_MetaRobotsTag,
  which keeps applying its flags while it is enabled. See `docs/robots-meta.md`, "Moving from
  MageOS_MetaRobotsTag". Settings imported by earlier versions are not revisited.
- **The Organization's design logo is each store view's own, and follows changes**
  ([#37](https://github.com/mage-os-lab/module-seo/issues/37)).
  - "Use Design Logo" stored the admin's design logo URL at the time of saving. Whatever scope was
    being edited, it was the admin store view's logo, on the admin's media URL. The form then
    guessed the choice by comparing that URL with today's design logo, so a changed theme logo
    turned it into a frozen custom logo.
  - The choice is now stored, and the logo is worked out for each store view when a page is
    rendered: its own design logo under its own media URL, or the custom upload under its own
    media URL, never the admin's, which may not be public.
  - A design logo whose file is missing is not published.
  - The form says what the scope being edited publishes.
  - **A record saved before this keeps publishing its stored logo URL until it is saved again with
    "Use Design Logo" or a new upload.**
- **A "Static block only" category no longer loads its whole product collection** (review M1).
  - Magento renders no product list on such a category, so the collection the category ItemList
    reads was unloaded. Reading it loaded every product the category matched, unpaginated, on
    every uncached request, to list products the page does not show.
  - There is now no ItemList where the page shows no products: a "Static block only" category, or
    a collection the listing did not load. The ItemList never loads the collection itself.
- **A category's later pages keep its robots restrictions**
  ([#34](https://github.com/mage-os-lab/module-seo/issues/34)).
  - With Override Robots on Paginated Pages on, page 2 and later took the Paginated Pages Robots
    value outright. A category set to `NOINDEX`, on itself, a parent or the Category Pages
    default, became `INDEX` on page 2 when that value was `INDEX,FOLLOW`.
  - The value can now only add restrictions: every restriction of the category's directive is
    kept on every page. A category that should be indexed on its later pages is set to `INDEX`.
  - The setting's help and `docs/robots-meta.md` say so.
- **A logo upload must be the image its name says** ([#20](https://github.com/mage-os-lab/module-seo/issues/20)).
  - Any file `getimagesize()` recognised passed under an image extension, a Flash movie named
    `.jpg` among them. Core's own validator lets through the types it does not list.
  - A logo is now a JPEG, PNG, GIF or WebP whose bytes match its extension.
  - It must be complete, its structure walked to the end its format defines. Decoders draw a JPEG,
    or under GD a GIF, that is cut short.
  - It must decode: with Magento's image adapter, GD2 or ImageMagick, or for WebP with whichever of
    ImageMagick and GD can read it.
  - WebP is new. Where nothing on the server can decode WebP, a WebP upload is refused with a
    message.
  - The type check never relies on an adapter, since ImageMagick decodes SVG and PDF too.

### Changed

- Indexer lag is documented, not handled. With indexers on Update by Schedule, a rebuild can run
  before the indexers have caught up with the same change; the next change, or the nightly
  generation, catches up. See `docs/sitemap.md`, "When a rebuild runs".
- **Availability is read a batch of products at a time.** `Model\Product\AvailabilityResolver` has
  `resolveMany()`, `prefetch()` and `best()` beside `resolve()`. A batch costs one stock-index query
  (MSI's `@api` `GetStockItemsDataInterface`), plus one for whether stock is managed when some
  product is salable at zero or below (`Model\ResourceModel\StockItemManageStock`). A configurable's
  variants are read as one batch. Before, each product ran MSI's per-product condition chain: outside
  the storefront areas, as in a feed build, a 15-variant configurable took 149 queries and takes 14
  now. MageOS_Aeo's llms.jsonl reads availability through it.
- **Constructors:** `Model\Product\AvailabilityResolver` takes MSI's `GetStockItemsDataInterface`,
  `IsSourceItemManagementAllowedForProductTypeInterface`, the new manage-stock resource model,
  `StockConfigurationInterface` and a logger, in place of `IsProductSalableInterface` and
  `GetStockItemConfigurationInterface`. `Model\Product\OfferBuilder` no longer takes `DateTime`.
  `Model\Product\Variant\ProductGroupBuilder` takes `AvailabilityResolver`,
  `Model\MetaTag\Provider\ProductMetaProvider` takes `FinalPrice`, and `Model\Product\FinalPrice`
  takes `Model\Config`. `Model\Hreflang\StoreLocaleMap` also takes `Hreflang\CodeList`, a cache and a
  logger. None of them is `@api`, but a subclass that overrides one of these constructors must
  follow.
- **Sitemap file names have a set: `sitemap-1-products-a-1.xml`, `…-b-1.xml`.** The index keeps its
  name, so nothing needs resubmitting. A sitemap written with the earlier names is written whole at
  its next rebuild, and its earlier files go at the rebuild after. Search Console shows the index's
  children under their new names after each rebuild of their kind.
- **Requires `magento/module-remote-storage`,** which declares `fullRemoteFilesystem`. It is part of
  every Magento and Mage-OS installation.
- **Sitemap classes:** `Model\Sitemap\Writer` takes `Model\Sitemap\FileNames` (new: every name a
  sitemap's files can have) and a logger, and has `abandon()`, which the Generator calls when a
  generation fails. `Model\Sitemap\Generator` has `getFileTypes()`. New: `Model\Sitemap\FileRemover`,
  `Plugin\Sitemap\RemoveFilesOnDelete`, `Observer\RemoveSitemapFilesOnScopeDelete`. None is `@api`.
- **Invalidation:** `Model\Sitemap\RebuildableSitemaps` takes core's `Magento\Sitemap\Helper\Data`
  and has `listsProductImages()`; `Model\Rebuild\ChangeInspector` has `galleryChanged()`;
  `Model\Sitemap\InvalidationPolicy::EVENT_PRODUCT_IMPORT`.
- **The consumer resets the hreflang store map before each build,** with the other services listed
  for `Model\Rebuild\BuildFreshness`, so a hreflang setting changed since shows in the next rebuild.
  `Hreflang\CodeList` reads the country list once per request, not once per check.
- **Requires `magento/module-catalog-inventory`**, for the stock item's manage-stock setting and its
  configured default. It is part of every installation with MSI.
- **Legacy imports:** `Model\Migration\MetaRobotsTagImporter` takes the new
  `Model\Migration\MetaRobotsTagDirectives`, the three repositories and the purger. It and
  `Model\Migration\HreflangImporter` implement the new `Model\Migration\LegacyImporterInterface`:
  `import()` takes `$dryRun` and returns a report with `notes`, and `describe()` words it. The data
  patches take the new `Model\Migration\LegacyImport`, which runs the imports and records which
  finished. New: `Console\Command\ImportLegacyCommand`, `Model\System\Message\LegacyImportStatus`.
  None is `@api`.
- **Paginated robots:** `Model\RobotsMeta\Provider\CatalogPaginationRobotsProvider` takes
  `CategoryRobotsProvider` and `DirectiveComposer`; `DirectiveComposer` has `restrictive()`.
  Neither is `@api`.
- **Organization logo:**
  - **`Api\Data\OrganizationInterface` (`@api`) has `getLogoSource()` and `setLogoSource()`,** with
    `LOGO_SOURCE_DESIGN` and `LOGO_SOURCE_CUSTOM`, and a `logo_source` column. A class
    implementing the interface itself must add them.
  - `getLogoPath()` is now the custom logo only, a media-relative path.
  - `UploadLogo` returns that path as `file`.
  - New: `Model\Organization\LogoUrl`, `LogoFileValidator`, `ImageCompleteness` and `WebpSupport`.
  - `Model\StructuredData\Provider\OrganizationProvider` and `Controller\Adminhtml\Organization\Save`
    take `LogoUrl`. `Ui\DataProvider\OrganizationDataProvider` takes it in place of the scope config
    and store manager, and `UploadLogo` takes `LogoFileValidator`.
- **MSI is required** (review M3, decision D1b). Composer requires
  `magento/module-inventory-sales`, `-inventory-indexer` and `-inventory-configuration`, which
  supply the stock services availability is read through. Core's dependency check reads
  composer.json, so `module:disable` now refuses them while this module is enabled.
- **Search results pages default to `NOINDEX,FOLLOW`** (decision D10a), Google's advice for
  internal search. They defaulted to "Use Magento Default". A value saved for them is kept. See
  `docs/robots-meta.md`.
- **The permissions sit under their menus** (decision D11a). `MageOS_Seo::seo`, with
  `MageOS_Seo::organization`, is under Marketing, where the SEO menu is; it was a top-level node
  beside Marketing and Stores. `MageOS_Seo::config` is under Stores → Settings → Configuration,
  with every other configuration section's. The IDs are unchanged, so roles keep their grants.
- **`CanonicalUrlManager` implements `Api\CanonicalUrlManagerInterface`** (decision D14), and
  `setCanonical()` drops its third argument, `$urlKey`, which nothing read.
- **Constructors (2.0):**
  - `Model\Product\OfferBuilder` takes `StoreManagerInterface`, `Api\SchemaPriceInterface`,
    `OfferEnricherPool` and `Model\Product\OfferSummary`; `buildAggregateOffer()` takes the
    children, the low price and the high price.
  - `Model\MetaTag\Provider\ProductMetaProvider` takes `Api\SchemaPriceInterface` and
    `AvailabilityResolver`; `Model\MetaTag\Provider\CmsPageMetaProvider` takes a logger.
  - `Model\Product\OfferEnricher\ShippingDetailsEnricher` takes `Api\SchemaPriceInterface` in place
    of `CurrencyService`.
  - `Model\StructuredData\Provider\ProductSchemaProvider` takes `Model\Product\SchemaCategory`.
  - `Model\Sitemap\Robots\Enricher` takes `Config`, `Api\Robots\PageDirectivesInterface`,
    `CmsPageRobotsProvider` and `CmsPageResolver`: products' and categories' directives come from
    `PageDirectives`.
- **Composer requires `magento/module-eav` and `magento/module-catalog-url-rewrite`,** which the
  code already used.

### Removed

- **`Service\CurrencyService`** and its test. `Api\SchemaPriceInterface` replaces the two methods
  in use. The rest was unused, and `currencyFormatter()` passed a symbol where `NumberFormatter`
  wants an ISO code.
- **The `rs_` attribute fallbacks** in the Generic, Home Decor and Apparel builders. An attribute
  such as `rs_weight` came from the module's origin; this module defines none, and none is read.
- **`ProblemLog::ALL`**, now `Api\Rebuild\ProblemLogInterface::ALL`.

## [1.2.1] — 2026-10-05

### Added

- **SEO files that are out of date are shown in the admin,** so a failing rebuild is not noticed only
  in the log, weeks later. See `docs/rebuild-problems.md`.
  - The System Messages bar lists each problem until a rebuild gets through: which files, for which
    store or Site Map, since when, when they are retried without anyone acting, and why. Admins whose
    role includes Marketing → SEO (`MageOS_Seo::seo`) see it.
  - The inbox gets one entry when a problem first appears.
  - A problem is a rebuild that failed (the previous file is still served), a file written incomplete
    (reported by the module that builds it), or a rebuild queued over an hour ago that the queue has
    not picked up.
  - Times are in the admin's language and the configured timezone. The retry time is the next run of
    the cron job that rebuilds the files, worked out as Magento's cron works it out: core's sitemap
    cron while Site Map generation is enabled, or the job a handler names.
  - What a sitemap rebuild, `seo:rebuild -g` or another module's handler returns is recorded by the
    queue consumer and the command. Core's sitemap cron and the Generate button settle the sitemap
    they write.
- **`Api\Rebuild\GroupDescriptionInterface`** (`@api`), for a rebuild handler to label its files and
  name the cron job that retries them. Optional; see `docs/extending.md`.
- `Model\Rebuild\ProblemLog`, for a handler to report a file written incomplete (`degraded()`) and
  to bracket a rebuild that does not go through the queue.
- `bin/magento seo:rebuild -g sitemaps-missing` runs the sitemaps' first build, so every group the
  admin message lists has a command.
- Requires `magento/module-admin-notification` and `magento/module-cron`.
- `Model\Config::getReturnPolicyUrl()`: the store view's returns policy page, or null while the
  return policy is off. The offers' `merchantReturnLink` and MageOS_Aeo's Policies section in
  `/llms.txt` read it; `ReturnPolicyEnricher`'s constructor takes `Config` last.

### Changed

- The constructors of `Model\Rebuild\RegenerateConsumer`, `Model\Rebuild\RegenerationRequester`,
  `Console\Command\RegenerateFeedsCommand` and `Plugin\Sitemap\UseSeoGenerator` take
  `Model\Rebuild\ProblemLog` (the plugin also `RebuildGroup`).

### Fixed

- **A product whose price is not known has no offer, instead of one priced 0.00.** An out-of-stock
  configurable has no saleable child, so Magento prices it 0, and its JSON-LD said it was free.
  - The product node now has no `offers` when the price is not known: Google reads 0 as free, and
    requires a price on every Offer.
  - Unknown means a composite product (configurable, grouped, bundle) priced 0, or a price lookup
    that throws, which is now logged with the SKU.
  - A simple product priced 0 keeps its `0.00`: it is free.
  - New: `Model\Product\FinalPrice` makes the decision, for the structured data and for
    MageOS_Aeo's `/llms.jsonl`.
  - `OfferBuilder`'s constructor takes it after `ChildProducts`. `OfferBuilder::build()` returns an
    empty array when the price is not known, and `AbstractBuilder::buildBase()` and the
    ProductGroup's variants then leave `offers` out.
  - See `docs/structured-data.md`.

## [1.2.0] — 2026-10-04

Pre-release review hardening pass (July 2026). Breaking renames are included
deliberately: nothing has shipped yet, so names are settled now, before they
become public contract.

### Fixed — llms.txt review (September 2026)

Checked against the llms.txt spec (v2, 10 August 2026), the reference parser
(`llms_txt` on PyPI, linked from llmstxt.org) and Lighthouse's llms-txt audit.

- **llms.txt follows the format.** Items under an H2 are `- [name](url)` links
  (Lighthouse fails a file with no markdown link, and the reference parser fails on
  any non-link line). Base URL, locale, search template, structured data and contact
  move to the details list before the first H2. The summary is one line; with no
  description there is no blockquote. FAQ entries are one-line plain text in the
  details. Square brackets in link labels become round ones.
- **Structured data line** names schema.org types instead of template codes, and no
  longer says "in use" for every registered template.
- **Sitemap link** is core's URL of the sitemap configured under Marketing → Site Map,
  not a hardcoded `/sitemap.xml`; no link when the store view has none.
- **`/llms.txt` answers 404, not 503, before the first build**, with `Retry-After` and
  `Cache-Control: no-store`. Lighthouse scores a 5xx as a failure and a 4xx as not
  applicable. `/llms-full.txt` and `/llms.jsonl` keep 503.
- **Disabled files are not claimed by the router**, so a static file or another module
  can serve the path.
- **The llms router runs before core's URL-rewrite router** (sortOrder 15, was 20).
  - The two shared a sortOrder, so their order depended on how the di.xml files merged.
  - On Mage-OS 3.5 the rewrite router ran first, so a URL rewrite for an enabled file's path
    could take that path. Such a rewrite is a redirect or a CMS page's rewrite.
- **`/llms.jsonl` description** is one line and cut at a word boundary, with `…`.
- `LlmsTxtBuilder` takes a new `SitemapUrlResolver` argument (before
  `sectionProviders`), and `LlmsTxtRouter` takes `Model\Aeo\Config`; with DI nothing
  changes.

### Fixed — prices

- **Prices were converted to the display currency twice** in product JSON-LD offers
  (`price`, `lowPrice`/`highPrice`), the Open Graph `product:price:amount` tag and
  `/llms.jsonl`, when the display currency differs from the base currency. PriceInfo
  amounts are already in the current currency — core `RegularPrice::getValue()` calls
  `PriceCurrency::convert()`, `SpecialPrice` and `CatalogRulePrice` call
  `convertAndRound()` — so the conversion added for display-currency prices applied the
  rate a second time. They are now used as they are.
- **One formatter for every machine-readable amount:** `CurrencyService::formatAmountForLlms()`
  writes the offers' prices, `product:price:amount`, the `/llms.jsonl` price and the shipping
  rate with two decimals, a full stop and no thousands separator, whatever the store's locale.
  The locale's display format (`1.234,50` in Dutch) is not a valid schema.org price.
- **`CurrencyService::convertFromBase()` is removed.** Nothing calls it after the fix above.

### Added

- **Canonical URLs → Use Canonical Link Meta Tag For CMS Pages** (`mageos_seo_general/canonical/cms_enabled`,
  per store view, default Yes): turns off the canonical link this module adds to CMS pages and the
  home page. Named like core's "Use Canonical Link Meta Tag For Categories/Products". See
  `docs/canonical-urls.md`.
- **Translations:** `i18n/en_US.csv` (the source), `en_GB.csv` (British spelling, only where it
  differs) and `nl_NL.csv`. Everything user-facing is now translatable:
  - the configuration, forms, menu and ACL titles;
  - the source models' option labels;
  - the product schema templates' names and field labels;
  - the llms documents' own text, written in each store view's language.
  - See `docs/translations.md`, including how to override a phrase and add a locale.
- **`FaqRepositoryInterface::getList(SearchCriteriaInterface)`**, returning the new
  `Api\Data\FaqSearchResultsInterface`: FAQ entries by Magento's standard search criteria, with the
  total that matched. Extension attributes declared with a `<join>` are joined in. See
  `docs/extending.md#repository-api`.
- **Extension attributes on `FaqInterface` and `OrganizationInterface`.** Both extend
  `ExtensibleDataInterface`, so other modules add fields through `extension_attributes.xml`.
  `getExtensionAttributes()` never returns null.
- **`@api` on every interface under `Api/`**, which marks the module's contract, and a unit test
  that fails if an interface there lacks it.
- Configurable products are described as a `ProductGroup` of their variants, per Google's
  product-variant guidance, when they have up to **Most Variants per Configurable Product**
  (`has_variant_max`, default 50) sellable children: `productGroupID`, `variesBy`, and one
  `Product` per child with its own offer and the group's description — the offers move off the
  group. What a variant varies by
  comes from the product's own configurable attributes, no map: codes naming one of Google's six
  (`color`, `size`, `material`, `pattern`, `suggested_gender`, `suggested_age`) are written as that
  property and listed in `variesBy`; any other becomes an `additionalProperty`. Each variant's
  offer URL is the product URL with `?{attribute_code}={option_id}`, which Luma's swatches
  preselect; `Api\ProductVariantUrlResolverInterface` (`@api`) replaces the rule. With the
  template's GTIN field enabled, each variant carries its own GTIN, loaded in one query from
  `gtin13`, `gtin`, `barcode` or `ean`. See `docs/structured-data.md#configurable-products`.
- A sitemap generator for the sitemaps configured under Marketing → Site Map, selected by the
  new **Catalog → XML Sitemap → Generation Settings → Generator** (default **MageOS SEO**;
  **Magento** leaves core's generator in charge). It lists the same URLs as core's, writes one file
  per kind of page under a `sitemap.xml` index, streams the catalogue whatever core's Generation
  Method says, and never lets a file pass the configured size. Products are read a page at a time
  by its own reader (`ResourceModel\Sitemap\ProductStream`) with core's product sitemap query, on
  every supported version; plugins on core's `prepareSelectStatement()` hook still apply. See
  `docs/sitemap.md`.
- Hreflang alternates inline in `sitemap.xml`, beside each URL, as the MageOS SEO generator writes
  it — the same set the page's head declares, and only where the URL is among its own alternates.
- The MageOS SEO sitemap generator leaves out pages served NOINDEX (**Generation Settings → Leave
  Out NOINDEX Pages**, default Yes). The directive is the page's own — its override, the page
  type's default, else core's Design → Search Engine Robots — so a store set to NOINDEX there gets
  an empty sitemap.
- Sitemaps kept current between generations (**Generation Settings → Rebuild on Change**, default
  Yes): a change to what a sitemap lists — a product's URL key, status, visibility or websites, a
  category, a CMS page, a robots directive or translation group, a store view, a sitemap-related
  setting — rewrites that kind of page in the store view's sitemaps, through the feed queue,
  leaving the other files and their dates as they were. A sitemap with no file — a Site Map entry
  saved without generating it, or one whose file has gone — is written whole shortly after its
  entry is saved and after every setup run (the `sitemaps-missing` queue group), where Magento
  would wait for **Generate** or its own schedule, which is off by default. One lock per sitemap
  keeps a rebuild, the Generate button and Magento's cron from writing it at once. Other modules ask for their own
  type through `Api\Sitemap\RebuildRequesterInterface`, and
  `mageos:seo:feeds:regenerate -g sitemap-{type}` (or `'sitemap-*'`) rebuilds in process. See
  `docs/sitemap.md`.
- Sitemap extension points (`Api\Sitemap\*`): items carry their entity and a data bag; providers
  say which file their items belong in and stream them; enrichers, filters and row renderers are
  registered on the generator. Providers registered on core's composite keep working, written
  under "other".
- Hreflang: several codes per store view, validated against the country directory; x-default per
  website; the `mageos_seo_hreflang_alternates_after` event; CMS translation groups, which purge
  the other translations' cached pages when a page joins, leaves or is deleted.
- Per-CMS-page robots override, and `noarchive` with every index/follow combination.
- Data patches carrying `MageOS_MetaRobotsTag`'s flags and `MageOS_Hreflang`'s settings and CMS
  links into this module; the latter then switches `MageOS_Hreflang`'s head output off. Its
  sitemap setting is left alone: where this module generates the sitemap, `MageOS_Hreflang`'s rows
  are never written, and where Magento's generator is selected they are the only alternates.
- `bin/magento mageos:seo:feeds:regenerate [-g llms|jsonl]` rebuilds the
  pre-generated feeds in the running process, for deployment scripts and manual
  rebuilds. It reports per-store-view failures and exits non-zero on any, and ends with
  `Done.`
- Every `setup:install` / `setup:upgrade` queues a rebuild of the feeds the store
  views can build, so a fresh install (or a deployment that cleared `var/`) no
  longer serves `503` on `/llms.txt` until the nightly cron runs.
- **Robots Meta Defaults → Search Results Pages Default** (`mageos_seo_general/robots_meta/search_default`,
  per store view, empty by default so core's Design → Search Engine Robots decides, like the product,
  category and CMS defaults): the robots meta for quick and advanced search results
  (`RobotsMeta\Provider\SearchResultsRobotsProvider`). `docs/robots-meta.md` explains `NOINDEX,FOLLOW`
  here against a robots.txt `Disallow`, and why not both.
- **AI Discoverability → FAQ Groups** (`mageos_seo_general/llms_txt/faq_groups`, per store view,
  default `global`): which FAQ groups `/llms.txt` (the first 5 questions) and `/llms-full.txt`
  (all) include, in order. Selecting none leaves FAQs out. Only a group named `global` was read
  before, which only the FAQ form's field notice said. See `docs/llms-txt.md#faq-section`.
- `Api\UcpServiceProviderInterface` and `Model\Ucp\ServicePool`: a module that serves a UCP service
  declares each transport binding in `/.well-known/ucp` from its own di.xml. Every service and
  capability entry is checked against the UCP 2026-08-25 business schema (`Model\Ucp\EntryValidator`),
  including the namespace binding of its `schema` URL. An entry that fails is logged with its
  provider and the reasons, and left out. See `docs/ucp.md`.
- `Api\Rebuild\GroupHandlerInterface` (`@api`) and `Model\Rebuild\HandlerPool`: a module hands
  its own pre-generated output to the rebuild queue the sitemaps and the llms documents use.
  - The queue collapses repeated requests, skips a group no store view can build, and re-queues a
    rebuild refused as already running.
  - `mageos:seo:feeds:regenerate` and every setup run pick the groups up.
  - `Exception\RebuildInProgressException` is the common parent of the "already running" refusals.

  See `docs/extending.md#rebuilding-your-own-output-through-the-queue`.

### Removed

- **Moved to MageOS_Agentic** ([mage-os/module-agentic](https://github.com/mage-os-lab/module-agentic)):
  the `/.well-known/` endpoint registry and router, the UCP business profile at `/.well-known/ucp`
  and the `ucp:keygen` command. Install that module to keep serving the profile; it requires this
  one.
  - **Breaking: the namespace is `MageOS\Agentic\`**, where it was `MageOS\Seo\`:
    `Api\WellKnownEndpointInterface`, `Api\UcpCapabilityProviderInterface`,
    `Api\UcpServiceProviderInterface`, `Model\WellKnown\*`, `Model\Ucp\*`,
    `Model\Router\WellKnownRouter`, `Controller\Wellknown\Index` and
    `Console\Command\UcpKeygenCommand`.
  - **Unchanged:** the settings (`mageos_agentic/*`), the ACL resource `MageOS_Agentic::config`, the
    internal URL `mageos-agentic/…` and the command name.
  - **`/.well-known/security.txt` is removed, not moved.** Core's Magento_Securitytxt serves it:
    configure it under **Stores → Configuration → Security → Security.txt**. Gone with it: the
    settings `mageos_agentic/security_txt/*` (saved values stay in `core_config_data`, unread),
    `Model\Ucp\SecurityTxtBuilder`, `Model\WellKnown\Endpoint\SecurityTxtEndpoint`,
    `Model\Config\Backend\SecurityTxtExpires` and `Block\Adminhtml\System\Config\Field\Date`.
  - `Model\Router\PublicPaths` and `Model\Router\CanonicalPathRedirect` stay here: MageOS_Agentic
    registers its paths with them.
  - Other entries in this release that mention the UCP profile, ai-plugin.json or security.txt
    describe them before the move.
- **Moved to MageOS_Faq** ([mage-os/module-faq](https://github.com/mage-os-lab/module-faq)):
  the FAQ entity and its `mageos_faq` table, the repository, the FAQ Manager, the widget, the Page
  Builder content type and the FAQPage JSON-LD. Install that module to keep them; it requires this
  one.
  - **Breaking: the namespace is `MageOS\Faq\`**, where it was `MageOS\Seo\`:
    `Api\FaqRepositoryInterface`, `Api\FaqCollectorInterface`, `Api\Data\FaqInterface`,
    `Api\Data\FaqSearchResultsInterface`, `Model\Faq`, `Model\FaqRepository`, `Model\Faq\GroupReader`,
    `Model\Faq\Collector`, `Model\Faq\Source\TableFaqSource`, `Model\ResourceModel\Faq`,
    `Block\FaqJsonLd`, `Block\AbstractFaqElement`, `Block\Widget\FaqList`, and the admin blocks,
    controllers and UI classes. The templates are `MageOS_Faq::faq/…`.
  - **Unchanged:** the table and its rows, the ACL resource and menu item `MageOS_Faq::faq`, the
    admin URLs, the cache tags, the model events, the widget id, the Page Builder content type and
    the CSS classes.
  - **Stays here:** the FAQ source pool (`Model\Faq\SourcePool`) and its contract
    (`Api\FaqSourceProviderInterface`), which the llms documents read; MageOS_Faq registers its table
    there. Without a source registered, the llms documents have no FAQ section.
  - `magento/module-widget` and the `magento/module-page-builder` suggestion go with it, and
    Magento_Widget and Magento_PageBuilder leave `etc/module.xml`'s sequence: nothing else here uses
    them.
- **Moved to MageOS_Aeo** ([mage-os/module-aeo](https://github.com/mage-os-lab/module-aeo)):
  `/llms.txt`, `/llms-full.txt` and `/llms.jsonl`, their feed storage and nightly cron job, and the
  AI crawler directives in `robots.txt`. Install that module to keep serving them; it requires this
  one.
  - **Breaking: the namespace is `MageOS\Aeo\`**, where it was `MageOS\Seo\`:
    `Api\LlmsTxtSectionProviderInterface`, `Api\JsonlLineProviderInterface`, `Model\Feed\*`,
    `Model\LlmsTxt\*`, `Model\LlmsJsonl\*`, `Model\Router\LlmsTxtRouter`, `Model\Cache\CleaningMode`,
    `Model\Config\Backend\FeedStorageDir`, `Model\Config\Source\{AiBots,FaqGroups}`, the llms
    controllers and observers, `Plugin\Robots\AppendAiDirectivesPlugin`,
    `Plugin\Catalog\Product\Action\InvalidateJsonlOnMassAttributeUpdate`, `Cron\RegenerateFeeds`,
    `Exception\FeedRebuildInProgressException` and `Setup\Patch\Data\RemoveHreflangSitemap`.
    `Model\Aeo\Config` becomes `MageOS\Aeo\Model\Config`.
  - **Unchanged:** the settings (`mageos_aeo/*`), the ACL resource `MageOS_Aeo::config`, the
    `env.php` key, the storage directory, the cache tags, the cron job, the rebuild lock, the feed
    groups `llms` and `jsonl`, and the internal URLs `mageos-aeo/…`.
  - Their log lines start `MageOS_Aeo:`, and the comment above the AI crawler groups in
    `robots.txt` reads `# AI crawlers (managed by MageOS_Aeo)`.
  - `RemoveHreflangSitemap` keeps its MageOS_Seo class name as an alias, so it is not applied a
    second time.
  - **Stays here:** the rebuild queue, its consumer and `seo:rebuild`, which MageOS_Aeo registers
    its groups with; the FAQ source pool; `Model\Organization\ContactEmail` and the locale, which
    the documents read; and `Model\Router\PublicPaths` and `CanonicalPathRedirect`, which keep
    sessions off their URLs. With MageOS_Aeo not installed, a plain `seo:rebuild` has no group to
    rebuild, and says how to rebuild the sitemaps.
  - `magento/module-robots`, `magento/module-page-cache` and `magento/module-cache-invalidate` go
    with it, and Magento_Robots leaves `etc/module.xml`'s sequence: nothing else here uses them.
  - Other entries in this release that mention the llms documents, the feeds or the AI crawler
    directives describe them before the move.
- `Setup\Patch\Data\RenameOrganisationTypes` and `ResourceModel\Organisation::renameOrgType()`,
  its only user. The patch renamed stored `EducationalOrg` / `GovernmentOrg` types to their
  schema.org names; the table it worked on is dropped by the Organization rename (see Changed), so
  it could never match a row again.
- **Structured Data → Price Valid Until (months)** (`mageos_seo_general/structured_data/price_valid_until_months`),
  with `Model\Config::getPriceValidUntilMonths()` and `Config::XML_SD_PRICE_VALID_UNTIL_MONTHS`: no
  synthetic `priceValidUntil` is made any more (see Changed). Its "0 omits the date" never worked —
  the value was read as at least 1 month. Values already saved stay in `core_config_data`, unread.
- `Model\Ucp\UcpConfig::getSupportEmail()` and `UcpConfig::XML_SUPPORT_EMAIL`. ai-plugin.json's
  contact comes from `Model\Organisation\ContactEmail` (see Fixed).
- **SEO Agentic Commerce (UCP) → Advertised Capabilities**, the five toggles
  (`mageos_seo_ucp/capabilities/{catalog,cart,checkout,identity_linking,order_management}`), and
  **UCP Profile → Merchant ID** (`mageos_seo_ucp/general/merchant_id`). With them go
  `UcpConfig::getEnabledCapabilities()`, `getMerchantId()`, `getDomainHost()` and
  `UcpConfig::XML_UCP_MERCHANT_ID`.
  - The toggles declared UCP capabilities that nothing in the store implements.
  - UCP has no merchant member.

  Values already saved stay in `core_config_data`, unread.
- `/.well-known/ai-plugin.json` and its settings. It now answers 404.
  - **Settings removed:** **SEO Agentic Commerce (UCP) → AI Plugin Manifest**
    (`mageos_seo_ucp/ai_plugin/{enabled,description,legal_url}`) and **Merchant Name**
    (`mageos_seo_ucp/general/merchant_name`), which only the manifest read.
  - **Code removed:** `Model\Ucp\AiPluginBuilder`, `Model\WellKnown\Endpoint\AiPluginEndpoint`,
    `UcpConfig::isAiPluginEnabled()`, `getMerchantName()`, `getBaseUrl()`,
    `getAiPluginDescription()`, `getAiPluginLegalUrl()` and their constants.
    `Ucp\UcpConfig`'s constructor no longer takes `StoreManagerInterface`.
  - **Why:** it made two false claims.
    - It was the manifest of OpenAI's ChatGPT plugins beta, which OpenAI ended in March–April 2024.
      OpenAI's plugins today are packages from its plugin directory, not a manifest a site hosts.
    - Its `auth: none` pointed agents at `catalogProductRepositoryV1`, whose methods need the admin
      ACL `Magento_Catalog::products`. Without core's Allow Anonymous Guest Access, an agent got a
      schema with no operations and a 401.
  - `/llms.txt`'s AI contact is unchanged. Saved values stay in `core_config_data`, unread.
  - Other entries in this release that mention ai-plugin.json describe it before its removal.

- The dedicated `/hreflang-sitemap.xml` and its chunk files. The alternates are in `sitemap.xml`
  now, beside each URL (see Added), which is where Google prefers them. The path answers 404,
  with no redirect; a merchant who submitted it in Search Console should remove it there. The
  upgrade deletes its files from feed storage, clears its pending rebuild and purges its cached
  responses (`Setup\Patch\Data\RemoveHreflangSitemap`). Gone with it: the `hreflang` feed group
  and `mageos:seo:feeds:regenerate -g hreflang`, the `MAGEOS_SEO_HREFLANG_SITEMAP` cache tag, and
  the setting's old label — **Enable /hreflang-sitemap.xml** is now **Add Hreflang Alternates to
  sitemap.xml**, on the same path. Entries below that mention the hreflang sitemap describe it
  before its retirement.
- The `variant_slug_data` request parameter and everything that read it. No module in this
  package or in Magento sets it, so it never changed the output. **Breaking for custom
  templates:** `Api\ProductSchemaBuilderInterface::build()` and `SchemaBuilderPool::build()` no
  longer take `$variantData`, and `AbstractBuilder::buildBase()` takes only the product. A builder
  written against 1.1.0 that still requires the fourth parameter no longer matches the interface,
  a fatal error when the class loads: drop it, or give it a default (`array $variantData = []`) to
  load against both versions. Extra arguments passed to the pool or to `buildBase()` are ignored.
  The product title, meta and JSON-LD providers no longer depend on the request.
- `AbstractBuilder`'s offer code, moved to `Model\Product\OfferBuilder` so variants use the same
  one. **Breaking for custom templates** that declare a constructor or call these: the constructor
  now takes `StoreManagerInterface`, `ImageHelper`, `Config`, `OfferBuilder`,
  `AggregateRatingResolver`, `GtinValidator` (the currency, availability, date and offer-enricher
  arguments are gone), and `resolvePrice()`, `resolveAvailability()`, `getPriceValidUntil()`,
  `resolvePriceRange()`, `buildAggregateOffer()` and the `AVAILABILITY_*` constants are removed. A
  template that extends AbstractBuilder without a constructor of its own is unaffected. To change
  offers, register an `OfferEnricherInterface` or plug in to `OfferBuilder::build()`.

### Fixed

- The FAQ form's Group Identifier note sent the admin to "MageOS SEO → SEO → AI Discoverability
  (llms.txt)"; since the settings moved, it is "MageOS SEO → AI Information & Crawlers → AI
  Discoverability (llms.txt)".
- The category form's **Enabled Optional Fields** multiselect had no options, so no field could be
  chosen there, and a template's optional fields reached the storefront only through override
  values. It now lists the fields of the template in effect for the category and store view being
  edited: the category's own or inherited template, else the store's default. A field stored
  under an earlier template is listed too, marked "(not a field of this template)", so it can be
  cleared. Which template is in effect is decided in one place,
  `Model\Product\SchemaTemplateResolver`, for the form and the storefront alike.
- The store's **Default Product Schema Template** was a text field, so a mistyped code was saved as
  given and the storefront quietly built GenericProduct instead. It is now a select of the
  registered templates, GenericProduct first, so a stored code no template has is shown as
  GenericProduct, the template the storefront uses for it. Stored values are unchanged.
- The return policy's **Applicable Country** and the shipping details' **Destination Country**
  were one typed ISO code each. They reached the structured data as entered, so `gb`, `UK` or
  `England` went to Google, and a shop selling to several countries could name only one.
  - Both are now **Applicable Countries** and **Destination Countries**: multiselects of Magento's
    country list, as core's Allow Countries is. One country is output as before; several are output
    as a list (`"applicableCountry": ["DE", "AT", "CH"]`, and one `DefinedRegion` each in
    `shippingDestination`).
  - **Applies Worldwide** and **Ships Worldwide** hide the countries.
    - Worldwide shipping gives no `shippingDestination`, which Google reads as every country.
    - Google has no worldwide value for returns, so a worldwide return policy lists no countries.
      Its Returns Policy URL (`merchantReturnLink`) is what Google reads instead.
  - Google reads at most 50 return countries. A larger selection is saved, the admin is told on
    save, and the first 50 in the list are output.
  - A stored code the list doesn't have isn't selected, and saving the section clears it, so check
    the countries after upgrading.
- security.txt's **Expires** was a typed timestamp, written out as entered, and could be left
  empty, although RFC 9116 requires it. It is now a calendar date. While security.txt is served,
  the date is required and must be after today. It is served as the end of that day, UTC
  (`2027-01-01T23:59:59Z`). Check it after upgrading: a timestamp saved before shows as its date,
  and is stored as one when the section is saved.
- Saving or deleting a FAQ or the Organisation anywhere but the admin form — the REST API, an
  import, a data patch — left `/llms.txt` and `/llms-full.txt` stale until the nightly rebuild:
  only the admin controllers asked for a rebuild. The models now dispatch their own events
  (`mageos_seo_faq_save_after` / `_delete_after`, `mageos_seo_organisation_save_after` /
  `_delete_after`), and those queue it, however the save was made.
- `/.well-known/ucp` did not follow UCP.
  - **Before:**
    - it served `$schema` (`https://json-schema.org`, which is not a schema), `version`,
      `merchant`, `transports`, and a `capabilities` map of `enabled` flags, none of which the
      spec defines;
    - it declared `dev.ucp.shopping`, and one capability per admin toggle, that nothing in the
      store served.
  - **Now:**
    - it is a UCP 2026-08-25 business profile: `{"ucp": {"version", "services", "capabilities",
      "payment_handlers"}}`, plus `keys`;
    - it declares only what an installed module registers, so out of the box it declares nothing.
  - **Signing key:**
    - it moves from `signing_keys` to `keys`;
    - a stored key carrying any private member (`d`, `p`, `q`, `dp`, `dq`, `qi`, `oth` or `k`, not
      only `d`) refuses the profile;
    - a stored key the schema would reject is logged and left out.

  See `docs/ucp.md`.
- The FAQ widget's questions showed no open/close triangle on Luma and Blank: Magento's LESS reset
  sets `summary { display: block; }`, which hides a `<summary>`'s marker. A module
  `_module.less` restores `display: list-item` (and a pointer cursor) on
  `.mageos-seo-faq__question`. Hyvä is unaffected.
- On Luma, a product page's BreadcrumbList took its category from the category the visitor had last
  browsed (core's session fallback), so the page cache served the first visitor's trail to
  everyone, on a URL whose visible trail is Home › Product. The category is now used only when the
  URL carries it; otherwise the trail is Home › Product. `BreadcrumbListProvider`'s constructor
  takes the request.
- The X (Twitter) card was `summary_large_image` on every page, including those with no image (a
  category without one, most CMS pages) and pages with nothing to share (cart, checkout). The card
  is now decided after every provider's tags are collected: `summary_large_image` with an
  `og:image`, `summary` without, none without an `og:title`. `twitter:title`,
  `twitter:description` and `twitter:image` repeat the Open Graph values. A `twitter:*` tag a
  provider sets itself is kept. `docs/og-tags.md` said no `twitter:card` was output, and now
  describes this.
- A Field Value Override for one of a template's own fields undid what the template built from it:
  `AbstractBuilder::applyOverrides()` wrote every override onto the node as given, after the template
  had used it. So a `brand` override (the docs' own example) replaced the `Brand` node with a
  string, a Book's `author` and `publisher` became strings, and `gender`, ages, `warning`,
  `batteriesRequired`, `organizer` and the like appeared a second time as top-level properties
  `Product` does not have. Now an override for a template's own field turns that field on
  (`SchemaBuilderPool`) and the template alone applies it; `applyOverrides()` sets only keys the
  template does not list. **Breaking for custom templates** that relied on `applyOverrides()` to
  set their own fields: read `$overrides[$field]` for them, as the built-in templates do — see
  `docs/extending.md`.
- Apparel's **Weight** and Toy's **Number of Players** were offered as fields but never built. Weight
  is read as Generic reads it (override, else `weight` / `rs_weight`); the player count goes into
  `additionalProperty` (override, else `player_count`), as `Product` has no such property.
- ArtAndCraft's `creator` is a `Person` node, not text.
- A product with a `special_to_date` but no special price published that date as its offer's
  `priceValidUntil`. It now needs a special price with a value (see Changed).
- `/llms.txt` and `/llms-full.txt` printed an empty `> Locale:` line: the store view's locale was
  read from `Store::getLocaleCode()`, which does not exist and fell through to empty data. It is
  now **General → Locale Options → Locale** for the store view, and the line is left out when there
  is none.
- The AI contact in `/llms.txt`, `/llms-full.txt` and `/.well-known/ai-plugin.json` was the store's
  Customer Support email, which Magento ships as `support@example.com` — so an unconfigured store
  published a placeholder. It is now the Organisation's Contact Email; else the Customer Support
  email unless it is still the shipped value; else none (the section is left out, `contact_email`
  is `""`). One class, `Model\Organisation\ContactEmail`, decides it for all three.
- A configuration change to something `/llms.txt` and `/llms-full.txt` show — the locale, the
  Customer Support email, their own settings, the base URLs (`web/`), the category URL suffix
  (`catalog/seo/`) — reached them only with the nightly rebuild. It now queues one, when the value
  changed.
- The Organization node carries `geo` and `priceRange` only on a local business type. schema.org
  gives a plain Organization neither (they come from Place and LocalBusiness); `address`,
  `telephone` and `email` stay on every type. Local business types are `LocalBusiness` by default,
  and a module that stores a subtype adds it to `localBusinessTypes` on
  `Model\StructuredData\Provider\OrganisationProvider` in its di.xml. The admin notice that
  promised this now says it.
- The organisation types **Educational Organization** and **Government Organization** were emitted
  as `EducationalOrg` and `GovernmentOrg`, which are not schema.org types. They are now
  `EducationalOrganization` and `GovernmentOrganization`, and the data patch
  `RenameOrganisationTypes` renames stored records (revertible).
- Speakable is carried by the page's own node — the CMS page's WebPage, the category's
  CollectionPage, the blog post's BlogPosting, and on product pages a WebPage for the product whose
  `mainEntity` is the product node — instead of an anonymous WebPage node, with no `@id` or `url`,
  on every page (so CMS pages had two WebPage nodes). Pages with no page node of their own no
  longer carry it. Built once, by `Model\StructuredData\SpeakableSpecification`.
- The home page is described at the store base URL. Its `og:url`, WebPage `url` and `@id` were
  `/{identifier}` (`/home`) while its canonical was `/`, and the schema could type it `AboutPage`;
  it is now a `WebPage` at `{base}/`, `@id` `{base}/#webpage`, throughout. The canonical, `og:url`,
  the WebPage node and hreflang take the URL from one place, `CmsPageResolver::currentUrl()`.
- The home page is recognised as core's router recognises it: the request's path is empty. The
  `cms_index_index` handle used for it before also matches `/cms` and `/cms/index/index`, which
  claimed the canonical `/` — pointing at a different page when **Default Web URL** serves
  something other than the CMS home page there.
- A home page set by page ID (a numeric `web/default/cms_home_page`, e.g. through `config:set`) got
  no CMS markup on `/`, and the sitemap's NOINDEX check could not read its robots directive. The
  home page is now loaded as core loads it (`Model\Cms\HomePageLoader`): by identifier, or by page ID
  when the value is numeric.
- Configurable products get their `AggregateOffer` on the product page. The range was read from
  core's `FinalPrice::getMinimalPrice()`/`getMaximalPrice()`, which read `minimal_price` data only a
  collection load sets — 0 on a product page — so every configurable showed one `Offer` at its
  lowest price. It is now read from the sellable children's own final prices. Children sharing one
  price keep a single `Offer`.
- `has_variant_max` at 0 read as 50, and the admin refused 0. 0 now means every configurable gets
  the `AggregateOffer`.

- Hreflang URLs use the store view's configured scheme. They followed the current request, so
  anything built from cron or the command line — `/hreflang-sitemap.xml` included — listed an https
  store view's pages as `http://`.
- A page's head declares hreflang alternates only when the set includes the page itself, as Google
  requires. A store view excluded from hreflang used to list every other store view on its pages
  but not itself; and of two CMS translations assigned to one store view, the one its group does not
  name used to declare the other as its own-language version. Both now declare nothing. The head and
  the sitemap apply the rule through one class, `Model\Hreflang\SelfReference`.
- The Organisation and FAQ admin forms declare an ACL resource. Their controllers
  always did, but a UI component's data is also reachable through the generic
  `mui/index/render` endpoint, which checks the component's own `aclResource` and
  not the controller's — so an authenticated administrator with none of this
  module's permissions could read the form data.
- Organisation URLs, social profiles and the logo are validated before they are
  stored. They are published into JSON-LD and Open Graph tags, where they land in
  `href`, `src` and `@id` positions, so only `http`/`https` and genuine relative
  paths are accepted: `javascript:`, `data:` and friends are refused, as is
  `//host/path`, which adopts the page's scheme and points elsewhere. Parsing uses
  `Laminas\Uri`, with a control-character check first, because a URL with an
  embedded newline parses as valid. An invalid Organisation or logo URL refuses the
  save with a message; invalid social profiles are dropped and named.
- `/.well-known/security.txt` fields are cut at the first line break. The document
  is one directive per line (RFC 9116), so a newline in a configured value could
  forge further directives — an `Encryption:` or `Contact:` of someone else's
  choosing — from a field that looks like free text.
- `mageos_seo_general/feeds/storage_dir` is restricted and validated. It is an
  absolute path set from the admin panel, so as it stood an administrator could
  point feed writes at any directory PHP can reach. Inside the installation only
  `var/` is now accepted — the root and every other standard directory (`app/`,
  `bin/`, `dev/`, `generated/`, `lib/`, `pub/`, `setup/`, `update/`, `vendor/`) are
  refused — along with no hidden directories anywhere, no `..`, and the path
  resolved first so a symlink inside `var/` cannot stand for a target outside it.
  The shared mount a multi-server deployment needs is declared in `app/etc/env.php`
  under `mageos_seo/feed_storage_roots`, which the admin panel cannot edit. A declared
  root extends where feeds may go without opening up the codebase: the installation
  rule is applied first, so a root pointing at `pub/`, `app/` or `vendor/` is ignored
  rather than obeyed. The
  rules run on save, with the reason shown, and again when the value is read, since
  a configuration row can arrive from a data patch or the database without passing
  through the form; a refused value is logged and the feeds fall back to
  `var/mageos_seo`.
- The feeds and the `/.well-known/` documents no longer start a session. A session
  sets a cookie, which stops shared caches storing the response at all, and makes
  PHP emit `Pragma: no-cache` over the 24-hour policy the controller just set.
  None of these endpoints read session state. `/.well-known/` also gained the
  canonical-path redirect the feeds already had, so query-string and internal-URL
  variants collapse onto one cacheable URL.
- The hreflang sitemap and the `<link rel="alternate">` tags no longer advertise
  entities a visitor cannot reach. They were built from `url_rewrite` rows filtered
  only on the rewrite's own flags, and a rewrite outlives the state of the thing it
  points at — so disabling a product, deactivating a category or unpublishing a CMS
  page left it listed as an alternate, on every store view, until something else
  removed the row. The queries now join the entity's published state at each row's
  store view: product `status` and `visibility`, category `is_active`, CMS
  `is_active` plus its store assignment, with the store-view value overriding the
  global one as the storefront does. The queries moved to a new
  `Model\ResourceModel\UrlRewrite` — `UrlRewriteFetcher` keeps its two methods as
  accessors and its row-by-row streaming, so the memory profile is unchanged. The
  join to the entity table carries the link field from `MetadataPool`, so
  installations with content staging (`row_id`) work the same way.
- Admin product and category edit pages no longer fail with `ReflectionException:
  Class "…\Form\Modifier\Pool" does not exist`. The SEO form modifiers were
  registered with `<type>` on the virtual-type pool names, which replaced the pool
  definitions in the merged DI config (and dropped every other module's product
  form modifiers). The product modifier now extends Magento_Catalog's
  `<virtualType>`; the category pool is declared in full and wired into the
  category form data provider, so the SEO fieldset also appears on installations
  without Mage-OS's AutomaticTranslation module (which declares the same pool).
- The category "SEO (Structured Data)" and product "Advanced SEO" fieldsets are
  now saved from the admin forms. They were persisted by `afterSave` plugins on
  the catalog repositories, but core's admin save controllers call the models'
  `save()` directly, so the plugins never ran. Persistence now runs from
  admin-area observers: `controller_action_catalog_product_save_entity_after`
  for products (dispatched once, for the form's product only, so configurable
  variations, "Save & Duplicate" copies and "copy to store views" saves in the
  same request are left alone) and `catalog_category_save_commit_after` for
  categories (acting only on the category carrying the posted fieldset). The
  `SaveSeoConfigPlugin` and `SaveSeoOverridesPlugin` classes were removed.
- The product "Advanced SEO" fieldset binds to the form's `data` branch.
  `product_form.xml` declares no form-level `dataScope`, so the fieldset's empty
  scope bound its fields outside the loaded and submitted data: stored values
  never showed, edits were never posted, and a save posted the untouched loaded
  values instead.
- The category form now loads the stored SEO config. Core's category form data
  provider does not apply modifier pools to its data, so the fieldset always
  rendered empty, and once saving worked every category save would have posted
  those empty values over the stored config. A plugin on the provider's
  `getData()` now adds the values.
- A catalog, CMS page or store save no longer takes the SEO feeds offline. Every
  save used to delete the served feed files across all store views and purge the
  page cache before the rebuild was even queued, so `/llms.txt`, `/llms-full.txt`,
  `/llms.jsonl` and `/hreflang-sitemap.xml` answered `503` until the consumer ran,
  and a burst of saves repeated the file sweep and the purge for every save.
  Rebuilds also wrote files in place, so a request could read a partially written
  document.
- SEO-only save problems (invalid override JSON, an SEO-table failure) are
  reported as warnings rather than errors: core's category save controller
  treats any error message as a failed save and sent a newly created category
  back to the "add" page although it had been saved.
- Table names are now resolved through `ResourceConnection::getTableName()` so
  installations with a DB table prefix work (adapter `getTableName()` never
  applied the prefix).
- JSON-LD output uses `JSON_HEX_TAG | JSON_HEX_AMP` instead of a post-encode
  `str_replace` that produced the invalid `\!` escape and corrupted the whole
  payload when content contained `<!--`.
- Offer enricher pool and aggregate-rating resolver are now required constructor
  dependencies: as optional arguments the ObjectManager passed `null` and every
  builder silently ran with empty pools, so merchant policies and
  aggregateRating were never emitted. All optional collaborator arguments across
  the module were made required for the same reason.
- JSON-LD / Open Graph prices are converted to the display currency before being
  paired with the display currency code (previously base-currency amounts were
  labelled with the display code).
- `priceValidUntil` comes only from a special price's end date (see Changed).
  `itemCondition` is no longer hardcoded to NewCondition
  (the configurable ItemConditionEnricher supplies it). Backorderable
  out-of-stock products emit `BackOrder` availability.
- GTIN values are validated (length + GS1 check digit) and emitted under the
  matching property (`gtin8`/`gtin12`/`gtin13`/`gtin14`); invalid values are
  omitted instead of producing Search Console errors. The Electronics, Tool and
  Stationery builders previously set `gtin13` from the barcode attribute without
  this validation (only the override path was validated); they now route the
  attribute value through the validator like every other builder.
- `CanonicalUrlManager` removes existing canonicals by asset content type
  instead of a URL-key pattern that could remove arbitrary CSS/JS assets.
- Per-store category/product SEO overrides read and write the store view from
  the `store`/`store_id` request parameter; previously the adminhtml current
  store (always 0) was used, making per-store overrides unreachable from the UI.
  The category form now also shows values inherited from ancestor categories.
- Catalog SEO fieldset persistence is registered for the admin area only, wraps
  SEO persistence in try/catch (an SEO-table failure no longer aborts an
  already-committed product/category save), and reports invalid override-JSON as
  an admin warning instead of silently wiping stored overrides.
- BreadcrumbList schema now renders on Luma and other themes without a public
  `getCrumbs()` on the breadcrumbs block, via the catalog breadcrumb path.
- FAQ widget no longer sets a block-cache `ttl`, which could FPC-cache pages
  without their FAQPage JSON-LD.
- robots.txt AI-crawler directives emit `Disallow: /` groups only for
  disallowed bots; allowed bots previously received dedicated `Allow: /` groups
  that exempted them from all `User-agent: *` rules.
- Request-scoped shared instances (FAQ collector, product schema registry, CMS
  page resolver, hreflang store-locale map, category config/product-override
  repositories) implement `ResetAfterRequestInterface` so their state cannot
  leak between requests under long-lived application servers (e.g. FrankenPHP
  worker mode). The category repositories also stop caching the DB adapter at
  construction — `ResourceConnection::_resetState()` closes connections between
  requests, which would leave a cached handle stale.

### Changed

- **The CMS canonical and the hreflang alternates are page assets** (review A5), added the way core
  adds its catalog canonicals: `PageConfig::addRemotePageAsset()`. Other code now finds them in the
  page's asset collection.
  - `CanonicalUrlManager::setCanonical()` replaces the CMS canonical like any other.
  - **The canonical** is content type `canonical`. It is added by `Observer\AddCanonicalLink`.
  - **The alternates** are content type `hreflang`, one asset per code, named
    `mageos_seo_hreflang_<code>`. They are added by `Observer\AddHreflangLinks`.
  - Both observers run on `layout_generate_blocks_after`.
  - **Breaking for layout customisations:** the blocks `mageos_seo.canonical` and
    `mageos_seo.hreflang`, their classes (`Block\Canonical`, `Block\Hreflang`) and their templates
    are gone. To turn the output off, use **Use Canonical Link Meta Tag For CMS Pages** (see Added)
    and **Enable Hreflang Tags**.
  - The links print with the other head assets, `<link rel="canonical" href="…" />`, instead of
    in `head.additional`. URLs are escaped as before.
- **Admin text is in US English;** en_GB restores the British forms. "Colour" becomes Color,
  "Organiser" Organizer, "watercolour" watercolor, "catalogue" catalog and "Canonicalisation"
  Canonicalization. Field and template codes are unchanged.
- **The llms documents' headings and labels follow the store view's language.** A store view in
  Dutch gets, for example, "## Belangrijke URL's" where it had "## Key URLs". An English store
  view's documents are unchanged.
- **`ProductSchemaBuilderInterface`:** `getLabel()` and the labels of `getAvailableFields()` are
  translated text (`(string) __('…')`). The docblock now says so, and gives `getAvailableFields()`
  its real type, code => label.
- **`Model\Config\Source\SchemaTemplate` lists the templates only,** GenericProduct first, for the
  store's default. The category form's list, with its inherit option ("Inherit (parent category,
  then the store's default template)", which was "-- Inherit / Use Global Default --"), is the new
  `SchemaTemplate\CategoryOverride`. `ProductSchemaProvider` takes a `SchemaTemplateResolver` in
  place of `Model\Config`, and the category form's `SeoModifier` takes the resolver and the builder
  pool.
- **Breaking: `Model\Category\ProductOverrideRepository` is now `Model\Product\OverrideRepository`**,
  beside the product's other models; it never had anything to do with categories. Its methods are
  unchanged. Code that injects it, or names it in di.xml, must follow.
- **Breaking: `Model\Faq\Repository` is now `Model\Faq\GroupReader`,** which says what it is: the
  storefront's reader of a FAQ group, behind `TableFaqSource`. `Api\FaqRepositoryInterface` remains
  the repository.
- **Breaking: `Model\Category\ConfigRepository::getForCategory()` returns `enabled_fields` and
  `override_fields` decoded,** as arrays, as `Product\OverrideRepository` already did, and
  `decode()` is private. A caller that decoded the result itself must drop that step.
- **`etc/db_schema_whitelist.json` is regenerated.** Magento looks its index and constraint
  entries up by their generated names, so the hand-written reference IDs it carried never matched
  and are gone. The generated name of `mageos_seo_cms_page_config`'s unique key, which was
  missing, is added. The retired `mageos_seo_organisation` and `mageos_seo_faq` stay listed, so an
  upgrade still drops them.
- **Breaking: `Model\LlmsTxt\SectionProviderInterface` is now `Api\LlmsTxtSectionProviderInterface`**
  (`@api`), beside the module's other extension points. A section provider must implement the new
  name; `LlmsTxtBuilder` skips anything else.
- **`Model\Faq` and `Model\Organization` extend `AbstractExtensibleModel`,** so their constructors
  take `ExtensionAttributesFactory` and `AttributeValueFactory` after the registry. Code that
  builds them through the object manager is unaffected.
- **`FaqRepository`'s constructor** takes the FAQ collection factory, the extension attributes join
  processor, the search criteria collection processor and the search results factory.
- **`OrganizationRepositoryInterface` throws Magento's standard exceptions,** as the FAQ repository
  does: `save()` throws `CouldNotSaveException` (a model that isn't an `AbstractModel` used to be
  an `\InvalidArgumentException`, and a database failure came through raw), and `deleteForScope()`
  throws `CouldNotDeleteException`. The original error is the exception's `getPrevious()`.
- **Breaking: the FAQ, the llms documents and the agentic commerce profile use their own
  prefixes** — `mageos_faq`, `mageos_aeo` and `mageos_agentic` — ahead of their move to MageOS_Faq,
  MageOS_Aeo and MageOS_Agentic. Nothing is migrated: after upgrading, enter the moved settings
  again and grant admin roles the new resources. MageOS_Seo keeps `mageos_seo`, including the
  rebuild queue (`mageos.seo.feed.regenerate`).
  - **CLI commands, one colon each:**
    - `mageos:seo:feeds:regenerate` becomes **`seo:rebuild`** (MageOS_Seo's, like the rebuild layer
      it drives); its `-g` groups are unchanged;
    - `mageos:seo:ucp:keygen` becomes **`ucp:keygen`**;
    - with no feed group registered, a plain `seo:rebuild` says there is nothing to rebuild and
      how to rebuild the sitemaps, instead of printing "Done." having done nothing. Sitemaps are
      still rebuilt from the command only when asked for with `-g`.
  - **FAQ:**
    - the table `mageos_seo_faq` becomes `mageos_faq`; `setup:upgrade` creates it empty and drops
      the old one with its rows;
    - the ACL resource and menu item `MageOS_Seo::faq` become `MageOS_Faq::faq`, still under
      Marketing → SEO;
    - the admin moves to `mageos_faq/faq/*`, with the layout handles `mageos_faq_faq_index` /
      `_edit` and the UI components `mageos_faq_listing` / `mageos_faq_form`;
    - the cache tag is `mageos_faq` (and `mageos_faq_group_<identifier>`), and the model events
      are `mageos_faq_save_after` / `_delete_after`;
    - the widget id is `mageos_faq_list`;
    - the Page Builder content type is `mageos_faq`, in core's "Add Content" section; the
      "MageOS SEO" section is gone. Page Builder content saved with the old type no longer
      renders;
    - the list markup's CSS classes are `mageos-faq`, `mageos-faq__heading`, `__item`,
      `__question` and `__answer`. A theme that styled `.mageos-seo-faq*` must follow.
  - **The llms documents and AI crawler directives:**
    - their settings move from the SEO section to their own, **AI Information & Crawlers**:
      `mageos_seo_general/{llms_txt,feeds,ai_robots}/*` become `mageos_aeo/{llms_txt,feeds,ai_robots}/*`,
      under the ACL resource `MageOS_Aeo::config`;
    - the `env.php` key for storage outside `var/` is `mageos_aeo/feed_storage_roots`, and the
      default directory is `var/mageos_aeo/`. `var/mageos_seo/` is no longer read or cleaned, and can
      be deleted;
    - the cache tags are `MAGEOS_AEO_LLMS`, `MAGEOS_AEO_LLMS_FULL` and `MAGEOS_AEO_LLMS_JSONL`, the
      nightly cron job is `mageos_aeo_regenerate_feeds`, and the rebuild lock is
      `mageos_aeo_feed_rebuild`;
    - the internal URLs are `mageos-aeo/…` (they 301 to the documents), and the observer and plugin
      names in events.xml and di.xml carry `mageos_aeo_`.
  - **Agentic commerce:**
    - the section becomes **Agentic Commerce (UCP)**, `mageos_agentic/{general,signing,security_txt}/*`
      (was `mageos_seo_ucp`), under `MageOS_Agentic::config`. Keys made with the old command are
      not carried over: run `bin/magento ucp:keygen` again;
    - the internal URL is `mageos-agentic/wellknown/index/…` (it 301s to the document).
  - **The shared frontName `mageos-seo` is gone:** nothing in MageOS_Seo answers on it any more.
- **Breaking: Organisation is Organization in code**, matching the admin, whose labels are now US
  English ("Organization", "Organization Settings"). Storefront output is unchanged; it already
  said Organization.
  - **Classes** (`Organisation` → `Organization` in each name): `Api\Data\OrganizationInterface`,
    `Api\OrganizationRepositoryInterface`, `Model\Organization`, `Model\OrganizationRepository`,
    `Model\Organization\{ContactEmail,UrlValidator}`, `Model\ResourceModel\Organization` and its
    `Collection`, `Model\StructuredData\OrganizationId`,
    `Model\StructuredData\Provider\OrganizationProvider`,
    `Observer\RemoveOrganizationOnScopeDelete`, `Ui\DataProvider\OrganizationDataProvider`, and the
    admin `Controller\Adminhtml\Organization\*` and `Block\Adminhtml\Organization\SaveButton`.
    Constructor parameters follow (`$organizationRepository`, `$organizationId`).
  - **The table** `mageos_seo_organisation` becomes `mageos_seo_organization`, and **nothing is
    migrated**: `setup:upgrade` creates the new table empty and drops the old one with its rows.
    Enter the Organization settings again after upgrading.
  - **The ACL resource** `MageOS_Seo::organisation` becomes `MageOS_Seo::organization`. A role
    that granted the old one must grant the new one.
  - **Admin URLs** `mageos_seo/organisation/*` become `mageos_seo/organization/*`, with the layout
    handle `mageos_seo_organization_edit` and the UI component `mageos_seo_organization_form`.
  - **The cache tag** is `mageos_seo_organization`.
  - **Events** are `mageos_seo_organization_save_after` / `_delete_after`, and carry the model
    under `organization` as well as `data_object`. The observer names in events.xml follow.
  - **The structured-data provider** is registered in the compositor's pool as `organization`.
- The llms documents', feed storage's and AI crawler directives' settings are read by a class of
  their own, `Model\Aeo\Config`, instead of `Model\Config`.
  - **Moved with the same names:** `isLlmsTxtEnabled()`, `isLlmsFullTxtEnabled()`,
    `isLlmsJsonlEnabled()`, `getLlmsFaqGroups()`, `getFeedStorageDir()`, `isAiRobotsEnabled()`,
    `getAiDisallowedBots()`, and the constants `XML_LLMS_ENABLED`, `XML_LLMS_FULL_ENABLED`,
    `XML_LLMS_JSONL_ENABLED`, `XML_LLMS_FAQ_GROUPS`, `XML_FEEDS_STORAGE_DIR`,
    `XML_AI_ROBOTS_ENABLED` and `XML_AI_ROBOTS_DISALLOWED`.
  - **Constructors changed:** these now take `Model\Aeo\Config` instead of `Model\Config`:
    - the `llms.txt`, `llms-full.txt` and `llms.jsonl` controllers;
    - `Feed\FeedRegenerator`, `Feed\FeedStorage` and `Feed\LlmsInvalidationPolicy`;
    - `LlmsTxt\FaqLlmsSectionProvider`;
    - `Plugin\Robots\AppendAiDirectivesPlugin`.

  The configuration paths, their scopes and what each getter returns are unchanged.
- The paths served as public documents are registered rather than hard-coded.
  `Model\Router\PublicPaths` takes them as `paths` and `prefixes` arguments in di.xml, and its
  `LLMS_ROUTES`, `WELL_KNOWN_PREFIX` and `MODULE_FRONT_NAME` constants are gone.
  - The llms route map is now `Router\LlmsTxtRouter`'s own constant, and the `.well-known/` prefix
    is `Router\WellKnownRouter::PREFIX`.
  - `Model\Feed\CanonicalPathRedirect` moves to `Model\Router\CanonicalPathRedirect`.

  The same paths get no session and the same redirects as before. A module serving its own public
  documents registers its paths in `PublicPaths` to keep sessions off them too.
- **Breaking for custom FAQ sources:** `Api\FaqSourceProviderInterface` gains
  `getIdentifiers(): string[]`, the group identifiers the source has FAQs for. A source that cannot
  list them returns `[]`.
  - `Model\Faq\SourcePool::getIdentifiers()` merges every source's.
  - The llms FAQ Groups setting (`Config\Source\FaqGroups`, whose constructor now takes the pool
    instead of `Faq\Repository`) offers them. So another module's FAQ groups can be chosen too,
    where before only the FAQ table's were listed.
- The rebuild queue is generic, and no longer names the llms documents.
  - **Moved to `Model\Rebuild`:**
    - `Model\Feed\RegenerationRequester` and `Model\Feed\RegenerateConsumer` move there; the
      consumer's handler in `queue_consumer.xml` follows;
    - `Model\Feed\FeedInvalidator` becomes `Model\Rebuild\Invalidator`. Its
      `invalidateLlms()` / `invalidateJsonl()` are replaced by `invalidate(string $group)` for any
      registered group.
  - **Split:**
    - `Model\Feed\InvalidationPolicy` becomes `Model\Sitemap\InvalidationPolicy`,
      `Model\Feed\LlmsInvalidationPolicy` and `Model\Rebuild\ChangeInspector`;
    - `Plugin\Catalog\Product\Action\InvalidateFeedsOnMassAttributeUpdate` becomes
      `InvalidateSitemapOnMassAttributeUpdate` and `InvalidateJsonlOnMassAttributeUpdate`.
  - **Now registered, not hard-coded:** the llms documents register with the queue as
    `Model\Feed\LlmsRebuildHandler`, and the regenerate command and `Setup\RecurringData` read
    their groups from the pool.
  - **Constructors changed:**
    - `RegenerateConsumer` takes the `HandlerPool`;
    - `RegenerateFeedsCommand` takes the `HandlerPool` instead of `FeedRegenerator`;
    - `RecurringData` takes the `Invalidator` and the `HandlerPool`;
    - the FAQ Save and Delete and the Organisation Save admin controllers no longer take
      `FeedInvalidator` (see Fixed).

  The groups, the queue topic, what counts as a relevant change, and what the command does are
  unchanged. Its help now lists the registered groups (`llms, jsonl`) instead of the llms file
  names.
- `Api\UcpCapabilityProviderInterface::getCapabilityData()` returns **one** capability entry
  (`version`, `schema`, …). It is listed under `getCapabilityKey()` along with any other provider's
  entries for that name, where before it was merged into the manifest as given. Constructors
  changed with it:
  - `Ucp\CapabilityPool` takes `EntryValidator` and `LoggerInterface` before `$providers`;
  - `Ucp\ProfileBuilder` takes `ServicePool` and `LoggerInterface`.
- An offer's `priceValidUntil` is a special price's end date and nothing else: published when the
  product has a special price and its `special_to_date` is today or later in store time
  (inclusive). The synthetic date, "today plus N months", is gone — it promised a validity nothing
  in the store backed — so an offer without a special price, or one without an end date, has no
  `priceValidUntil`. A `special_to_date` without a special price no longer publishes a date either.
  **`Model\Product\OfferBuilder`'s constructor** no longer takes `Config`. Another source of dates
  plugs in as an `Api\OfferEnricherInterface`. See `docs/structured-data.md#an-offers-pricevaliduntil`.
- A store view's locale is read in one place, `Model\Config::getLocaleCode()`, by hreflang,
  `og:locale` and the llms documents. Constructors changed with it: `Hreflang\StoreLocaleMap` and
  `MetaTag\Provider\SiteMetaProvider` no longer take `ScopeConfigInterface`; `LlmsTxtBuilder` takes
  `Config` and `Organisation\ContactEmail` before `$sectionProviders`; `Ucp\AiPluginBuilder` takes
  `ContactEmail`; `LlmsTxt\FaqLlmsSectionProvider` takes `Config`. Code that builds them through
  the object manager or di.xml by name is unaffected.
- **hasVariant Max Entries** is now **Most Variants per Configurable Product** (same path,
  `mageos_seo_general/structured_data/has_variant_max`): a configurable with more sellable
  children than this gets one `AggregateOffer` over their whole price range instead of a variant
  list cut short.
- The module requires `magento/module-configurable-product`, which every distribution ships.
- With the MageOS SEO sitemap generator selected — the default — `sitemap.xml` is always a sitemap
  index, listing `{name}-{store}-pages-1.xml`, `-categories-`, `-products-` and so on. Search
  engines need only the one URL they already have. Files core's generator wrote for the same
  sitemap are removed on the first generation.
- The robots dropdowns on the product, category and CMS page forms offer one empty option each,
  saying where the page falls back to. The product form's "Use Category / Global Default" was
  wrong: a product's directive never comes from its category.
- Hreflang codes are deduplicated per code rather than per store view, so two store views on the
  same locale both keep their alternates once either is given its own code.
- `magento/module-sitemap` is now a dependency.
- **Breaking:** the support floor moves to **Magento 2.4.7 and PHP 8.3**
  (`magento/framework ^103.0.7`, `php ~8.3.0 || ~8.4.0 || ~8.5.0`), matching the
  Mage-OS monorepo. With it, the version polyfills go: the bundled
  `Compat/ResetAfterRequestInterface` is deleted, along with the conditional
  `require` in `registration.php` and the `exclude-from-classmap` entry it needed.
  That polyfill declared a *framework-namespace* symbol from a module, and
  `exclude-from-classmap` only applies to a composer package — installed under
  `app/code`, the psr-0 fallback classmapped it and shadowed the real interface on
  `composer dump-autoload -o`. 2.4.7 is the first release that ships the interface,
  so nothing needs supplying. `Model\Cache\CleaningMode` likewise no longer names
  `Magento\Framework\Cache\CacheConstants` (2.4.9-only); the cleaning-mode
  identifier is the same string on every supported version and is written out once,
  with a note on where it came from.
- `magento/module-url-rewrite` and `magento/module-review` are now declared
  dependencies. Both were already required in practice — the hreflang sitemap reads
  `url_rewrite` and the rating provider reads `review_entity_summary` — but neither
  appeared in `composer.json`, because a dependency reached through a table name is
  invisible to a class-based scan. `magento/module-page-builder` is declared as a
  `suggest`: it is needed only for the Page Builder FAQ content type, and
  `etc/module.xml` already sequenced it.
- **Breaking (pre-release):** all `rs_seo`/`rs-seo` names renamed to
  `mageos_seo`/`mageos-seo` (routes, layout handles, block names, DI/observer/
  plugin names, admin form field names); cache tags `RS_*` renamed to
  `MAGEOS_SEO_*`; DB tables renamed from hyphenated `mage-os_seo_*` to
  `mageos_seo_*` with automatic data migration via declarative schema
  (`onCreate="migrateDataFromAnotherTable"`); `OrganizationProvider` renamed to
  `OrganisationProvider`; origin-project `makers_*` layout handles removed in
  favour of a DI-configurable `excludedHandles` argument; `GBP` currency
  fallback removed.
- Robots meta defaults ship empty ("no opinion") so Magento core's
  `design/search_engine_robots` configuration stays in charge until a merchant
  explicitly configures values. Installing the module no longer re-opens
  NOINDEXed environments.
- Organisation logo upload no longer accepts SVG (stored-XSS vector) and
  validates uploaded bytes are a real raster image.
- Admin controllers declare `HttpPostActionInterface`/`HttpGetActionInterface`;
  FAQ delete actions go through POST with form-key validation; FAQ save
  validates required fields server-side.
- The PageTitle compositor is now wired into page rendering via an observer;
  built-in providers only act when an explicit title exists (such as a product's
  meta_title) so core behaviour is unchanged by default.
- Current product/category resolution goes through a single
  `Model\Catalog\CurrentEntity` shim instead of injecting the deprecated
  `Magento\Framework\Registry` into every provider.
- `Model\Cms\CmsPageResolver` resolves CMS pages through the
  `Magento\Cms\Api\GetPageByIdentifierInterface` service contract instead of
  `PageFactory`/`checkIdentifier()`; behaviour is unchanged (a missing
  identifier resolves to `null`).
- composer.json declares all hard module dependencies, a `license` field
  (OSL-3.0), and no longer hardcodes a package version.
- Support floor is Magento/Mage-OS **2.4.6-p15+** (`magento/framework`
  pinned `^103.0.6-p15`). The module runs unchanged across the range: a bundled
  `Compat/ResetAfterRequestInterface` polyfill (loaded from `registration.php`
  behind `interface_exists`) covers versions below 2.4.7 that lack the
  worker-mode reset interface, and `Model\Cache\CleaningMode` resolves the
  full-page-cache cleaning-mode identifier that only 2.4.9's
  `Magento\Framework\Cache\CacheConstants` exposes.
- Feed generation is queue-based: invalidations (and requests hitting a missing
  file) queue a rebuild on the `mageosSeoFeedRegenerate` consumer with duplicate
  requests collapsed via a pending flag; web requests never build feeds and answer
  503 Retry-After only while a feed has never been generated. The nightly cron
  remains as a full-rebuild safety net. The feed storage directory is configurable
  (`mageos_seo_general/feeds/storage_dir`) for multi-server deployments.
- Feed invalidation no longer deletes the served files or purges the page cache.
  Invalidation only queues a rebuild; the consumer and the nightly cron replace each
  file atomically (temporary file + rename) and purge the rebuilt group's cache tags
  once, after all store views are written. The hreflang sitemap writes its chunk
  files before the index and removes surplus chunks afterwards. Files of a feed
  disabled for a store view are removed on the next rebuild. A queued rebuild not
  picked up within an hour is queued again with a logged warning, so a lost message
  or a stopped consumer no longer disables event-driven rebuilds permanently.
  `FeedStorage::deleteForAllStores()` and the `FeedInvalidator::FILES_*` constants
  were removed; cache tags and the cache policy now live in `Model\Feed\FeedCache`.
  Feed files are written with mode `0640` and feed directories with `0750`
  regardless of the process umask (previously `0666`/`0777` minus the umask, i.e.
  world-writable under a `000` umask); cron/consumer and the web PHP user must share
  an owner or group.
- Feed rebuilds are only queued for changes that can affect a feed
  (`Model\Feed\InvalidationPolicy`): a feed that no store view can build is never
  queued (`/llms.jsonl` is disabled by default; the hreflang sitemap needs two
  active store views), and the hreflang sitemap is only rebuilt for URL-relevant
  product, category and CMS page changes. Product saves now also rebuild
  `/llms.txt` and `/llms-full.txt` when they change category product counts (new
  product, changed category assignments or changed website assignments), which
  were previously left stale.
- Deletions now invalidate the feeds they remove content from: a deleted product
  rebuilds all three feeds, a deleted category `/llms.txt`, `/llms-full.txt` and
  the sitemap, a deleted CMS page the sitemap. Previously a deleted entity stayed
  in the served feeds until the nightly rebuild.
- Mass catalogue actions invalidate the feeds too. They write straight to the
  catalogue tables without saving the products, so no save event reports them: a
  mass attribute update (admin "Update attributes", mass enable/disable, and the
  same service elsewhere) rebuilds `/llms.jsonl`, plus the hreflang sitemap when
  `url_key`, `status` or `visibility` is among the updated attributes
  (`Plugin\Catalog\Product\Action\InvalidateFeedsOnMassAttributeUpdate`); a mass
  website assignment change rebuilds all three
  (`catalog_product_to_website_change`).
- The per-category and per-product SEO repositories are built on Magento's model
  layer instead of hand-written SQL. `Model\Category\ConfigRepository` and
  `Model\Category\ProductOverrideRepository` assembled `Select`s and called
  `insertOnDuplicate()` straight on a `ResourceConnection` adapter; they now read
  through collections and write through models and resource models
  (`Model\CategoryConfig`, `Model\ProductOverride` and their resource models and
  collections are new). Public method signatures are unchanged, so nothing that
  uses them changes. Reading a category's configuration is also one query rather
  than one per ancestor level: the whole category path is fetched at once.
- The SEO tables now have foreign keys. `mageos_seo_category_config`,
  `mageos_seo_product_override` and `mageos_seo_faq` reference
  `catalog_category_entity`, `catalog_product_entity` and `store` with
  `ON DELETE CASCADE`, so a deleted category, product or store view takes its SEO
  records with it instead of leaving rows that a later entity with the same ID —
  after a restore or an import that writes explicit IDs — would silently inherit.
  `mageos_seo_organisation` cannot have one: its `scope_id` is a website ID or a
  store view ID depending on the `scope` column, the same shape as
  `core_config_data`. `Observer\RemoveOrganisationOnScopeDelete` clears it as the
  scope is deleted, mirroring core's own `clearScopeData()` calls in
  `Website::beforeDelete()` / `Group::beforeDelete()`, and
  `OrganisationRepositoryInterface` gains `deleteForScope()`.
- Deleting a store view queues the sitemap rebuild from `store_delete_before`
  rather than `store_delete`. The rebuild is also what removes a sitemap that can
  no longer be built, and `store_delete` is dispatched once the store view is
  already gone: deleting the second-to-last store view left one, the sitemap
  counted as unbuildable, nothing was queued, and the surviving store view carried
  on serving a sitemap listing the store view that had just been deleted until the
  nightly rebuild. The store's own feed directory is still removed after the delete
  commits, so a delete that rolls back keeps its files.
- Deleting a store group or a website now invalidates the hreflang sitemap, and its
  store views' feed files are cleaned up. Core removes those store views with a
  database-level cascade that dispatches no `store_delete` event, so nothing had
  noticed them going: the sitemap kept listing them, and their feed directories
  stayed on disk for good. A full rebuild now also sweeps feed directories whose
  store view no longer exists.
- Feed file listings no longer come back stale. `Magento\Framework\Filesystem\Glob`
  memoises every glob result for the life of the process, so a rebuild that listed
  its own output — surplus sitemap chunks, store directories — could act on the
  listing from before it wrote anything. It matters wherever one process rebuilds
  more than once: the queue consumer with `--max-messages`, or the CLI command.
- Moving a category rebuilds `/llms.txt`, `/llms-full.txt` and the hreflang sitemap.
  A move re-parents the category (core regenerates its URL rewrites) and changes the
  shape of the category tree, but dispatches no save event, so nothing invalidated
  the feeds. `/llms.jsonl` carries product URLs without category paths and is
  unaffected.
- Deleting a store view rebuilds the hreflang sitemap — its alternates change —
  and removes that store view's feed directory, which nothing would otherwise
  clean up (`Observer\RemoveFeedFilesOnStoreDelete`,
  `FeedStorage::deleteStoreDirectory()`).
- Feed builds stream to their file instead of assembling the whole document in memory:
  `/llms.jsonl` is written one line at a time from a paged collection, and the hreflang
  sitemap streams its URL rewrites row by row (`UrlRewriteFetcher::fetchAllForType()`
  became `streamAllForType()`, `SitemapGenerator::generate()` became `streamBlocks()`
  plus the document framing, and the new `Model\Hreflang\SitemapFileWriter` writes the
  file set). Peak memory is one page of products rather than the whole feed.
- The hreflang sitemap is built once per alternate set. The document lists every store
  view of the set, so store views sharing one produce byte-identical chunk files: the
  first store view of a set builds them, the rest copy them and write only their own
  index (which carries their base URL). A rebuild costs one catalogue pass per alternate
  set instead of one per store view.
- All feed responses (`/llms.txt`, `/llms-full.txt`, `/llms.jsonl`,
  `/hreflang-sitemap*.xml`) are cacheable for 24 hours
  (`Cache-Control: public, max-age=86400, s-maxage=86400`); the llms feeds
  previously sent `max-age=3600`, and the documentation said one hour.
- Organisation model and the JSON-LD block implement `IdentityInterface`, so saving
  Organisation settings purges the affected FPC/Varnish pages by tag automatically
  (replaces the manual full_page cache-type invalidation).
- Product availability is resolved through the MSI service contracts
  (`IsProductSalableInterface` + backorders via `GetStockItemConfigurationInterface`,
  batched with `AreProductsSalableInterface` for llms.jsonl) via a new
  `AvailabilityResolver` service, replacing the deprecated CatalogInventory
  `StockRegistry`/`Stock` helper which ignores multi-source stock assignment.
  composer dependencies move from `magento/module-catalog-inventory` to
  `magento/module-inventory-sales-api` + `magento/module-inventory-configuration-api`;
  installations that have physically removed the MSI modules cannot use this module.

## [1.1.0] — 2026 (pre-review baseline)

Baseline of the module as donated for Mage-OS review: JSON-LD structured data
(16 product templates, Organisation, FAQ, breadcrumbs, hreflang), meta/OG tags,
robots meta management, llms.txt / llms-full.txt / llms.jsonl endpoints and
.well-known documents.
