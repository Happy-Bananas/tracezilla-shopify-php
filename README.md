# tracezilla-shopify-php

Framework-neutral PHP templates for integrating Shopify with the tracezilla
API. The examples compare catalogs and safely preview or create missing
tracezilla SKUs from Shopify variants.

Clone the repository before following the commands below:

```bash
git clone https://github.com/Happy-Bananas/tracezilla-shopify-php.git
cd tracezilla-shopify-php
```

## Hello World: Compare Catalogs

The command paginates Shopify variants and tracezilla SKUs, normalizes both
complete responses, and reports:

- SKU codes present in both systems;
- SKU codes found only in Shopify;
- SKU codes found only in tracezilla.

It never writes to either API. Differences are a successful comparison and
therefore return exit code `0`; configuration or API errors return a non-zero
exit code.

## Run with Docker

Requirements: Docker with the Compose plugin and API credentials for a test
Shopify store and tracezilla team.

```bash
cp .env.example .env
```

Fill in `.env`, then install the pinned dependencies:

```bash
docker compose run --rm php composer install
```

Compare the catalogs and show up to ten rows from each result category:

```bash
docker compose run --rm php php bin/compare-catalogs
```

Change the display limit or request machine-readable output:

```bash
docker compose run --rm php php bin/compare-catalogs --limit=25
docker compose run --rm php php bin/compare-catalogs --json
```

The comparison always uses the complete catalogs. The display limit keeps
terminal output manageable and does not change the summary counts. JSON output
contains the complete result arrays.

## Run the tests

```bash
docker compose run --rm php composer test
```

Tests use in-memory inputs and do not contact Shopify or tracezilla.

## Create missing tracezilla SKUs

Preview at most ten Shopify variants without writing anything:

```bash
docker compose run --rm php php bin/create-tracezilla-skus
```

The result reports records that would be created, already exist, are duplicate,
have no Shopify SKU, or failed. Change the processing limit with `--limit=25`
or add `--json` for structured output.

Execution requires two explicit flags:

```bash
docker compose run --rm php php bin/create-tracezilla-skus --execute --confirm --limit=1
```

Review `ShopifyVariantToTracezillaSkuMapper` before execution. Its `pcs`,
`colli`, weight, and conversion values are example business assumptions that
must be adapted to the customer.

## List Shopify locations

List every location visible to the configured Shopify app:

```bash
docker compose run --rm php php bin/list-shopify-locations
```

Return the complete structured result as JSON:

```bash
docker compose run --rm php php bin/list-shopify-locations --json
```

The command is read-only and requires the Shopify `read_locations` scope. Use
the returned GraphQL location ID when adapting an inventory workflow.

## Synchronize Shopify inventory from tracezilla

First retrieve the Shopify location ID with `list-shopify-locations`. Then run a
bounded preview using the corresponding tracezilla warehouse location number:

```bash
docker compose run --rm php php bin/synchronize-inventory \
  --shopify-location=gid://shopify/Location/123 \
  --tracezilla-warehouse=2 \
  --limit=10
```

Writes require both explicit safety flags:

```bash
docker compose run --rm php php bin/synchronize-inventory \
  --shopify-location=gid://shopify/Location/123 \
  --tracezilla-warehouse=2 \
  --execute --confirm --limit=1
```

Review `TracezillaInventoryToShopifyQuantityMapper` before execution. Its unit
conversion rule is an example business mapping, not a universal default.

## Report collected Shopify orders

Build a read-only sales report from orders created during the last three days.
Lines are grouped by business date, currency, and SKU:

```bash
docker compose run --rm php php bin/report-collected-orders \
  --days=3 --timezone=Europe/Copenhagen --limit=10
```

Run the same command without Docker after installing Composer dependencies:

```bash
php bin/report-collected-orders \
  --days=3 --timezone=Europe/Copenhagen --limit=10
```

Add `--json` for structured output suitable for logs or another program. The
command never writes to Shopify or tracezilla. Cancelled orders, orders whose
line-item connection is truncated, and unusable lines are reported as skipped.

## Import individual Shopify orders

Preview at most ten recent Shopify orders as individual tracezilla sales
orders. The tracezilla customer name and warehouse location number are explicit
so the business relationship is visible whenever the command runs:

```bash
docker compose run --rm php php bin/import-individual-orders \
  --customer='Banana primary webshop' \
  --warehouse=2 \
  --days=3 \
  --limit=10
```

The command is a dry run by default. After reviewing the mapping and output,
one bounded sandbox write requires both safety flags:

```bash
docker compose run --rm php php bin/import-individual-orders \
  --customer='Banana primary webshop' \
  --warehouse=2 \
  --days=3 \
  --limit=1 \
  --execute --confirm
```

The example supports DKK, prefixes external references with `SHP`, rejects
partial orders, and uses a visible example partner/address mapping. Adapt these
rules to the customer before execution. Add `--json` for structured output.

## Design

The example deliberately separates responsibilities:

```text
GraphQL query -> Shopify client -> catalog service -> mapper --+
                                                              +-> CompareCatalogs
tracezilla API -> tracezilla client -> catalog service -> mapper+
```

- `Queries` contain Shopify GraphQL documents.
- API clients own authentication and HTTP transport.
- Catalog services own retrieval and pagination boundaries.
- Mappers convert vendor responses into the shared `CatalogItem` model.
- Workflow classes contain comparison or creation rules.
- Files in `bin/` assemble dependencies and render output.

The classes use ordinary PHP and Composer—no Laravel, Symfony, or other
application framework. They can be wrapped by a framework command, controller,
job, or scheduler without changing the workflow.

The optional
[`tracezilla-integration-workbench`](https://github.com/Happy-Bananas/tracezilla-integration-workbench)
is a separate Laravel application for interactive credential checks and safe
experiments.

## Configuration safety

- Never commit `.env`; it is ignored by Git.
- Use a development store and test tracezilla team first.
- Do not print tokens or client secrets in logs or error reports.
- Keep Shopify scopes minimal. Commands currently use `read_products`,
  `read_locations`, `read_inventory`, `write_inventory`, and `read_orders`
  according to the workflows being run.

Canonical setup and safety guidance lives in the
[Tracezilla Integrations documentation](https://happy-bananas.github.io/tracezilla-integrations-docs/).
