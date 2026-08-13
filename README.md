# tracezilla-shopify-php

Framework-neutral PHP templates for integrating Shopify with the tracezilla
API. The first example is a read-only catalog comparison using SKU code as the
shared identifier.

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
- `CompareCatalogs` contains only the comparison rule.
- `bin/compare-catalogs` assembles dependencies and renders output.

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
- Keep Shopify scopes minimal; this example only needs `read_products`.

Canonical setup and safety guidance lives in the
[Tracezilla Integrations documentation](https://happy-bananas.github.io/tracezilla-integrations-docs/).
