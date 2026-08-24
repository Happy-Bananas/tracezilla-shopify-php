# tracezilla Integration

Headless, framework-neutral PHP application for integrating commerce platforms
with the tracezilla API. Shopify is the first adapter; WooCommerce support is
planned. Consultants customize the business rules in PHP and run workflows
from the console, cron, or another scheduler.

Clone the repository before following the commands below:

```bash
git clone https://github.com/Happy-Bananas/tracezilla-shopify-php.git tracezilla-integration-php
cd tracezilla-integration-php
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

## Start the development environment with Docker

Requirements: Docker with the Compose plugin and API credentials for a test
Shopify store and tracezilla team.

```bash
cp .env.example .env
```

Fill in `.env`, then start the headless integration:

```bash
docker compose up --build
```

Docker installs missing PHP dependencies automatically. Wait until the terminal
displays `TRACEZILLA INTEGRATION IS READY`, then leave it running and open a
second terminal for commands.

List the available commands:

```bash
docker compose exec integration php bin/tracezilla-integration help
```

## Create your first business scenario

Generate the smallest runnable consultant feature:

```bash
docker compose exec integration php bin/tracezilla-integration scenario:create confirm-credentials --platform=shopify
```

The generator creates four files under
`custom/Scenarios/Shopify/ConfirmCredentials/`: a Shopify GraphQL query, a read-only
tracezilla request, PHP business rules, and a unit test. The initial “hello
world” scenario reads the Shopify shop name and one tracezilla SKU page to
confirm that both credentials work. It does not write to either service.

Run its test, then execute the credential check:

```bash
docker compose exec integration composer test
docker compose exec integration php bin/tracezilla-integration scenario:run confirm-credentials --platform=shopify
```

Copy and rename generated scenarios for customer features. Keep HTTP,
authentication, locking, retries, and history in the framework; customize the
generated query, request, rules, and tests.

Compare the catalogs and show up to ten rows from each result category:

```bash
docker compose exec integration php bin/tracezilla-integration catalog:compare
```

Change the display limit or request machine-readable output:

```bash
docker compose exec integration php bin/tracezilla-integration catalog:compare --limit=25
docker compose exec integration php bin/tracezilla-integration catalog:compare --json
```

The comparison always uses the complete catalogs. The display limit keeps
terminal output manageable and does not change the summary counts. JSON output
contains the complete result arrays.

## Report tracezilla SKUs needing a Shopify decision

List tracezilla SKUs that have no Shopify variant with the same SKU code:

```bash
docker compose exec integration php bin/tracezilla-integration catalog:report-shopify-decisions --limit=10
```

Add `--json` for bounded, structured output. This command is read-only: it does
not claim to create Shopify products. For every reported SKU, a consultant must
decide whether to create a new product, add a variant to an existing product,
map or change the SKU, or intentionally exclude it.

A future creation workflow must also define the product/variant relationship,
title, handle, options, vendor, product type, status and publication channels;
pricing, tax and shipping behavior; inventory tracking; images and descriptive
content. Those choices cannot be inferred safely from the current catalog APIs.

## Run the tests

```bash
docker compose exec integration composer test
```

Tests use in-memory inputs and do not contact Shopify or tracezilla.

## Create missing tracezilla SKUs

Preview at most ten Shopify variants without writing anything:

```bash
docker compose exec integration php bin/tracezilla-integration catalog:create-tracezilla-skus
```

The result reports records that would be created, already exist, are duplicate,
have no Shopify SKU, or failed. Change the processing limit with `--limit=25`
or add `--json` for structured output.

Execution requires two explicit flags:

```bash
docker compose exec integration php bin/tracezilla-integration catalog:create-tracezilla-skus --execute --confirm --limit=1
```

Review `ShopifyVariantToTracezillaSkuMapper` before execution. Its `pcs`,
`colli`, weight, and conversion values are example business assumptions that
must be adapted to the customer.

## List Shopify locations

List every location visible to the configured Shopify app:

```bash
docker compose exec integration php bin/tracezilla-integration shopify:locations
```

Return the complete structured result as JSON:

```bash
docker compose exec integration php bin/tracezilla-integration shopify:locations --json
```

The command is read-only and requires the Shopify `read_locations` scope. Use
the returned GraphQL location ID when adapting an inventory workflow.

## Synchronize Shopify inventory from tracezilla

First retrieve the Shopify location ID with `list-shopify-locations`. Then run a
bounded preview using the corresponding tracezilla warehouse location number:

```bash
docker compose exec integration php bin/tracezilla-integration inventory:sync \
  --shopify-location=gid://shopify/Location/123 \
  --tracezilla-warehouse=2 \
  --limit=10
```

Writes require both explicit safety flags:

```bash
docker compose exec integration php bin/tracezilla-integration inventory:sync \
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
docker compose exec integration php bin/tracezilla-integration orders:report-collected \
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
docker compose exec integration php bin/tracezilla-integration orders:import-individual \
  --customer='Banana primary webshop' \
  --warehouse=2 \
  --days=3 \
  --limit=10
```

The command is a dry run by default. After reviewing the mapping and output,
one bounded sandbox write requires both safety flags:

```bash
docker compose exec integration php bin/tracezilla-integration orders:import-individual \
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

The integration is intentionally headless. It does not require Laravel, a web
interface, or a database.

## Deployment safety and failed work

Before scheduling commands, verify the private writable runtime directory and
atomic global lock:

```bash
docker compose exec integration php bin/tracezilla-integration deployment:check
```

Workflow commands acquire one global non-blocking lock automatically. The
individual-order workflow stores failed writes as atomic JSON task files under
`var/retry/`, respects retry backoff during later reconciliations, moves
persistent or business failures to `attention`, and writes sanitized lifecycle
events to monthly `var/history/*.ndjson` files.

Inspect and manage failures with:

```bash
docker compose exec integration php bin/tracezilla-integration failures:list
docker compose exec integration php bin/tracezilla-integration failures:retry --task=<task-id>
docker compose exec integration php bin/tracezilla-integration failures:dismiss --task=<task-id> --reason='Approved exclusion'
```

Set `TRACEZILLA_RUNTIME_DIR` when the default `var/` directory is not suitable.
The runtime directory must be private, persistent, and writable by the same
user that runs cron.

## Configuration safety

- Never commit `.env`; it is ignored by Git.
- Use a development store and test tracezilla team first.
- Do not print tokens or client secrets in logs or error reports.
- Keep Shopify scopes minimal. Commands currently use `read_products`,
  `read_locations`, `read_inventory`, `write_inventory`, and `read_orders`
  according to the workflows being run.

Canonical setup and safety guidance lives in the
[Tracezilla Integrations documentation](https://happy-bananas.github.io/tracezilla-integrations-docs/).
