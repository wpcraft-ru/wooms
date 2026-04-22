---
name: moysklad-rest-api
description: "Use for tasks that integrate with MoySklad JSON API (Remap 1.2): auth, endpoint selection, filters, expand/fields, and syncing products, assortment, and orders."
---

# MoySklad REST API (Remap 1.2)

## When to use

Use this skill when you need to:

- build or update integrations with MoySklad JSON API
- choose correct endpoints for products, assortment, and orders
- implement API requests with auth, filtering, sorting, expand, and fields
- map MoySklad entities to WooCommerce data flows
- debug rate limits, validation errors, or permissions issues

## Primary docs

- Main section (requested baseline):
  - https://dev.moysklad.ru/doc/api/remap/1.2/#/general#1-mojsklad-json-api
- API base URL:
  - https://api.moysklad.ru/api/remap/1.2/

## How to work

1. Start from general rules (auth, errors, filtering, sorting, expand, fields).
2. Pick endpoint by business object (see references file).
3. Use explicit `meta.href` links for relationships where possible.
4. Prefer incremental sync by update timestamps and stable IDs.
5. Respect request limits and retry headers when handling 429/5xx.

## Request basics

- Auth:
  - `Authorization: Basic <base64(login:password)>` or `Authorization: Bearer <token>`
- Core response shape often includes `meta` and `rows`.
- `filter`, `order`, `search`, `expand`, `fields`, `limit`, `offset` are key query params.
- Common max page size is up to 1000 for many list endpoints.

## Quick references

See:

- [references/entities-and-endpoints.md](references/entities-and-endpoints.md)
