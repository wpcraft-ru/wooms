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

## Product additional fields (`attributes`)

- In MoySklad UI these are called "additional fields" (`dop. polya`).
- In Remap 1.2 product payload they are stored in the `attributes` key of each product.
- Attribute metadata endpoint:
  - `GET /entity/product/metadata/attributes`
- To reliably read values for product additional fields, request products with:
  - `expand=attributes`
- Filtering by a specific additional field uses full metadata attribute URL in `filter`, for example:
  - `filter=https://api.moysklad.ru/api/remap/1.2/entity/product/metadata/attributes/{ATTRIBUTE_ID}=true`

Observed in repository fixtures (`tests/data/fixtures-v2/**/*.json`):

- `customentity`
- `long`
- `double`
- `string`
- `text`

Per-type JSON examples:

```json
{
  "type": "customentity",
  "name": "Ценовая категория",
  "value": {
    "meta": {
      "href": "https://api.moysklad.ru/api/remap/1.2/entity/customentity/d25d413b-8aba-11ee-0a80-0997004245ca/8e4b8460-8ac4-11ee-0a80-04b40045437b",
      "type": "customentity",
      "mediaType": "application/json"
    },
    "name": "Недорогие"
  }
}
```

```json
{
  "type": "long",
  "name": "Емкость аккумулятора (mAh)",
  "value": 3000
}
```

```json
{
  "type": "double",
  "name": "Объем (мл)",
  "value": 25
}
```

```json
{
  "type": "string",
  "name": "Размер (мм)",
  "value": "121.5 х 42.5"
}
```

```json
{
  "type": "text",
  "name": "Комплектация",
  "value": "Устройство Aegis Boost 3 ..."
}
```

Example product JSON fragment (`attributes`) from fixtures:

```json
{
  "attributes": [
    {
      "meta": {
        "href": "https://api.moysklad.ru/api/remap/1.2/entity/product/metadata/attributes/e92a0a6b-8aba-11ee-0a80-0b2d00424daf",
        "type": "attributemetadata",
        "mediaType": "application/json"
      },
      "id": "e92a0a6b-8aba-11ee-0a80-0b2d00424daf",
      "name": "Ценовая категория",
      "type": "customentity",
      "value": {
        "meta": {
          "href": "https://api.moysklad.ru/api/remap/1.2/entity/customentity/d25d413b-8aba-11ee-0a80-0997004245ca/8e4b8460-8ac4-11ee-0a80-04b40045437b",
          "metadataHref": "https://api.moysklad.ru/api/remap/1.2/context/companysettings/metadata/customEntities/d25d413b-8aba-11ee-0a80-0997004245ca",
          "type": "customentity",
          "mediaType": "application/json",
          "uuidHref": "https://online.moysklad.ru/app/#custom_d25d413b-8aba-11ee-0a80-0997004245ca/edit?id=8e4b8460-8ac4-11ee-0a80-04b40045437b"
        },
        "name": "Недорогие"
      }
    },
    {
      "meta": {
        "href": "https://api.moysklad.ru/api/remap/1.2/entity/product/metadata/attributes/e92a0c00-8aba-11ee-0a80-0b2d00424db0",
        "type": "attributemetadata",
        "mediaType": "application/json"
      },
      "id": "e92a0c00-8aba-11ee-0a80-0b2d00424db0",
      "name": "Тип вкуса",
      "type": "customentity",
      "value": {
        "meta": {
          "href": "https://api.moysklad.ru/api/remap/1.2/entity/customentity/e3a7270c-8aba-11ee-0a80-0fc600431a26/51cd05e0-8ac5-11ee-0a80-107500454b26",
          "metadataHref": "https://api.moysklad.ru/api/remap/1.2/context/companysettings/metadata/customEntities/e3a7270c-8aba-11ee-0a80-0fc600431a26",
          "type": "customentity",
          "mediaType": "application/json",
          "uuidHref": "https://online.moysklad.ru/app/#custom_e3a7270c-8aba-11ee-0a80-0fc600431a26/edit?id=51cd05e0-8ac5-11ee-0a80-107500454b26"
        },
        "name": "Фрукты и ягоды"
      }
    },
    {
      "meta": {
        "href": "https://api.moysklad.ru/api/remap/1.2/entity/product/metadata/attributes/788a2b85-8ac4-11ee-0a80-15e40045bfb5",
        "type": "attributemetadata",
        "mediaType": "application/json"
      },
      "id": "788a2b85-8ac4-11ee-0a80-15e40045bfb5",
      "name": "С холодком",
      "type": "customentity",
      "value": {
        "meta": {
          "href": "https://api.moysklad.ru/api/remap/1.2/entity/customentity/425f36ff-8abb-11ee-0a80-0b2d00425fbd/bd0116c1-8ac5-11ee-0a80-0fc600457420",
          "metadataHref": "https://api.moysklad.ru/api/remap/1.2/context/companysettings/metadata/customEntities/425f36ff-8abb-11ee-0a80-0b2d00425fbd",
          "type": "customentity",
          "mediaType": "application/json",
          "uuidHref": "https://online.moysklad.ru/app/#custom_425f36ff-8abb-11ee-0a80-0b2d00425fbd/edit?id=bd0116c1-8ac5-11ee-0a80-0fc600457420"
        },
        "name": "Да"
      }
    }
  ]
}
```

## Quick references

See:

- [references/entities-and-endpoints.md](references/entities-and-endpoints.md)
