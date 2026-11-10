# Changelog

What changed in each release of this template, and what a project created
from an older release has to change to get it. A project's `TEMPLATE_VERSION`
says which release it started from; see
[Update Your Integration](https://happy-bananas.github.io/tracezilla-integrations-docs/update.html).

Changes in the shared code (tracezilla client, login, run lock, seed) are in
the [bifrost-core changelog](https://github.com/Happy-Bananas/bifrost-core/blob/main/CHANGELOG.md)
and reach a project with `composer update happy-bananas/bifrost-core`.

## 0.2.0 (2026-11-10)

- The integration logs in to tracezilla by itself with the API user in `.env`.
  `tracezilla:login` is removed.

What a project changes: add `TRACEZILLA_USERNAME` and `TRACEZILLA_PASSWORD`
(the API user from tracezilla's Resource Owner Password Credentials) to
`.env`, delete `bin/tracezilla-login` and remove `tracezilla:login` from
`bin/bifrost-connect`.

## 0.1.0 (2026-11-10)

First release.

- `connection:check` (also `./check-connection`) and `tracezilla:login`.
- Catalog: `catalog:compare`, `catalog:report-shopify-decisions` and
  `catalog:create-tracezilla-skus`.
- `shopify:locations` and `inventory:sync`.
- Orders: `orders:report-collected` and `orders:import-individual`, with
  tracezilla's own error message and retries of failed orders however old
  they are (`failures:list`, `failures:retry`, `failures:dismiss`).
- `deployment:check`, scenarios (`scenario:create`, `scenario:run`), and an
  Ansible playbook in `deployment/ansible/`.
- The optional `tracezilla:seed`.
- Requires bifrost-core `^0.6`.
