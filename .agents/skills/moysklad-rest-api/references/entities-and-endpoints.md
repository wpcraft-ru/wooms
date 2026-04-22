# MoySklad References: Entities and Endpoints

Use this map to quickly choose the right resource in Remap 1.2.

## Core docs

- General JSON API overview:
  - https://dev.moysklad.ru/doc/api/remap/1.2/#/general#1-mojsklad-json-api
- Full API docs index:
  - https://dev.moysklad.ru/doc/api/remap/1.2/
- Base endpoint:
  - https://api.moysklad.ru/api/remap/1.2/

## Product catalog and assortment

- Product:
  - Endpoint: `GET/POST https://api.moysklad.ru/api/remap/1.2/entity/product`
  - Docs: https://dev.moysklad.ru/doc/api/remap/1.2/#/dictionaries/product
- Assortment (unified view for product/service/variant/bundle):
  - Endpoint: `GET https://api.moysklad.ru/api/remap/1.2/entity/assortment`
  - Docs: https://dev.moysklad.ru/doc/api/remap/1.2/#/dictionaries/assortment
- Product folder (categories):
  - Endpoint: `GET/POST https://api.moysklad.ru/api/remap/1.2/entity/productfolder`
  - Docs: https://dev.moysklad.ru/doc/api/remap/1.2/#/dictionaries/productfolder
- Variant (modification):
  - Endpoint: `GET/POST https://api.moysklad.ru/api/remap/1.2/entity/variant`
  - Docs: https://dev.moysklad.ru/doc/api/remap/1.2/#/dictionaries/variant
- Service:
  - Endpoint: `GET/POST https://api.moysklad.ru/api/remap/1.2/entity/service`
  - Docs: https://dev.moysklad.ru/doc/api/remap/1.2/#/dictionaries/service
- Bundle (set):
  - Endpoint: `GET/POST https://api.moysklad.ru/api/remap/1.2/entity/bundle`
  - Docs: https://dev.moysklad.ru/doc/api/remap/1.2/#/dictionaries/bundle

## Orders and sales documents

- Customer order (Заказ покупателя):
  - Endpoint: `GET/POST https://api.moysklad.ru/api/remap/1.2/entity/customerorder`
  - Docs: https://dev.moysklad.ru/doc/api/remap/1.2/#/documents/customerorder
- Demand (Отгрузка):
  - Endpoint: `GET/POST https://api.moysklad.ru/api/remap/1.2/entity/demand`
  - Docs: https://dev.moysklad.ru/doc/api/remap/1.2/#/documents/demand
- Supply (Приемка):
  - Endpoint: `GET/POST https://api.moysklad.ru/api/remap/1.2/entity/supply`
  - Docs: https://dev.moysklad.ru/doc/api/remap/1.2/#/documents/supply
- Purchase order (Заказ поставщику):
  - Endpoint: `GET/POST https://api.moysklad.ru/api/remap/1.2/entity/purchaseorder`
  - Docs: https://dev.moysklad.ru/doc/api/remap/1.2/#/documents/purchaseorder
- Invoice out (Счет покупателю):
  - Endpoint: `GET/POST https://api.moysklad.ru/api/remap/1.2/entity/invoiceout`
  - Docs: https://dev.moysklad.ru/doc/api/remap/1.2/#/documents/invoiceout

## Related entities used by orders

- Counterparty:
  - Endpoint: `GET/POST https://api.moysklad.ru/api/remap/1.2/entity/counterparty`
  - Docs: https://dev.moysklad.ru/doc/api/remap/1.2/#/dictionaries/counterparty
- Organization (own legal entity):
  - Endpoint: `GET/POST https://api.moysklad.ru/api/remap/1.2/entity/organization`
  - Docs: https://dev.moysklad.ru/doc/api/remap/1.2/#/dictionaries/organization
- Store (warehouse):
  - Endpoint: `GET/POST https://api.moysklad.ru/api/remap/1.2/entity/store`
  - Docs: https://dev.moysklad.ru/doc/api/remap/1.2/#/dictionaries/store
- Project:
  - Endpoint: `GET/POST https://api.moysklad.ru/api/remap/1.2/entity/project`
  - Docs: https://dev.moysklad.ru/doc/api/remap/1.2/#/dictionaries/project

## Practical notes

- For stock sync, `entity/assortment` is often the best read model.
- For order sync from shop to ERP, create/update `entity/customerorder`.
- For shipment sync from ERP to shop, track `entity/demand` status/updates.
- Use `expand=positions` carefully; keep `limit` moderate.
- For strict links in filters, pass full `meta.href` URLs.
