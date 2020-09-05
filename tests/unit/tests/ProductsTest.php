<?php

namespace WooMS\Tests;

use Mockery;

use function Brain\Monkey\Functions\expect;

class ProductsTest extends TestCase
{



    function get_data()
    {
        $json = '{
            "meta": {
              "href": "https://online.moysklad.ru/api/remap/1.2/entity/product/070ffbb6-ada6-11ea-0a80-02ee0026116c",
              "metadataHref": "https://online.moysklad.ru/api/remap/1.2/entity/product/metadata",
              "type": "product",
              "mediaType": "application/json",
              "uuidHref": "https://online.moysklad.ru/app/#good/edit?id=070fed16-ada6-11ea-0a80-02ee0026116a"
            },
            "id": "070ffbb6-ada6-11ea-0a80-02ee0026116c",
            "accountId": "1f2036af-9192-11e7-7a69-97110001d249",
            "owner": {
              "meta": {
                "href": "https://online.moysklad.ru/api/remap/1.2/entity/employee/1f2ec2de-9192-11e7-7a69-97110016a92b",
                "metadataHref": "https://online.moysklad.ru/api/remap/1.2/entity/employee/metadata",
                "type": "employee",
                "mediaType": "application/json",
                "uuidHref": "https://online.moysklad.ru/app/#employee/edit?id=1f2ec2de-9192-11e7-7a69-97110016a92b"
              }
            },
            "shared": true,
            "group": {
              "meta": {
                "href": "https://online.moysklad.ru/api/remap/1.2/entity/group/1f206231-9192-11e7-7a69-97110001d24a",
                "metadataHref": "https://online.moysklad.ru/api/remap/1.2/entity/group/metadata",
                "type": "group",
                "mediaType": "application/json"
              }
            },
            "updated": "2020-06-13 21:45:18.276",
            "name": "Audi SQ8",
            "code": "00052",
            "externalCode": "ib8xrPPggb6BtO4ReQgqz3",
            "archived": false,
            "pathName": "Авто",
            "productFolder": {
              "meta": {
                "href": "https://online.moysklad.ru/api/remap/1.2/entity/productfolder/1bbc413b-ada5-11ea-0a80-030c00217297",
                "metadataHref": "https://online.moysklad.ru/api/remap/1.2/entity/productfolder/metadata",
                "type": "productfolder",
                "mediaType": "application/json",
                "uuidHref": "https://online.moysklad.ru/app/#good/edit?id=1bbc413b-ada5-11ea-0a80-030c00217297"
              }
            },
            "effectiveVat": 20,
            "uom": {
              "meta": {
                "href": "https://online.moysklad.ru/api/remap/1.2/entity/uom/19f1edc0-fc42-4001-94cb-c9ec9c62ec10",
                "metadataHref": "https://online.moysklad.ru/api/remap/1.2/entity/uom/metadata",
                "type": "uom",
                "mediaType": "application/json"
              }
            },
            "images": {
              "meta": {
                "href": "https://online.moysklad.ru/api/remap/1.2/entity/product/070ffbb6-ada6-11ea-0a80-02ee0026116c/images",
                "type": "image",
                "mediaType": "application/json",
                "size": 1,
                "limit": 1000,
                "offset": 0
              }
            },
            "minPrice": {
              "value": 0.0,
              "currency": {
                "meta": {
                  "href": "https://online.moysklad.ru/api/remap/1.2/entity/currency/1f3ac651-9192-11e7-7a69-97110016a959",
                  "metadataHref": "https://online.moysklad.ru/api/remap/1.2/entity/currency/metadata",
                  "type": "currency",
                  "mediaType": "application/json",
                  "uuidHref": "https://online.moysklad.ru/app/#currency/edit?id=1f3ac651-9192-11e7-7a69-97110016a959"
                }
              }
            },
            "salePrices": [
              {
                "value": 710000000,
                "currency": {
                  "meta": {
                    "href": "https://online.moysklad.ru/api/remap/1.2/entity/currency/1f3ac651-9192-11e7-7a69-97110016a959",
                    "metadataHref": "https://online.moysklad.ru/api/remap/1.2/entity/currency/metadata",
                    "type": "currency",
                    "mediaType": "application/json",
                    "uuidHref": "https://online.moysklad.ru/app/#currency/edit?id=1f3ac651-9192-11e7-7a69-97110016a959"
                  }
                },
                "priceType": {
                  "meta": {
                    "href": "https://online.moysklad.ru/api/remap/1.2/context/companysettings/pricetype/1f3c1593-9192-11e7-7a69-97110016a95a",
                    "type": "pricetype",
                    "mediaType": "application/json"
                  },
                  "id": "1f3c1593-9192-11e7-7a69-97110016a95a",
                  "name": "Цена продажи",
                  "externalCode": "cbcf493b-55bc-11d9-848a-00112f43529a"
                }
              },
              {
                "value": 0.0,
                "currency": {
                  "meta": {
                    "href": "https://online.moysklad.ru/api/remap/1.2/entity/currency/1f3ac651-9192-11e7-7a69-97110016a959",
                    "metadataHref": "https://online.moysklad.ru/api/remap/1.2/entity/currency/metadata",
                    "type": "currency",
                    "mediaType": "application/json",
                    "uuidHref": "https://online.moysklad.ru/app/#currency/edit?id=1f3ac651-9192-11e7-7a69-97110016a959"
                  }
                },
                "priceType": {
                  "meta": {
                    "href": "https://online.moysklad.ru/api/remap/1.2/context/companysettings/pricetype/e9e8d47f-2451-11e8-9ff4-34e80019ace1",
                    "type": "pricetype",
                    "mediaType": "application/json"
                  },
                  "id": "e9e8d47f-2451-11e8-9ff4-34e80019ace1",
                  "name": "Опт",
                  "externalCode": "a59e79d7-1826-4f1a-a04c-c4e60fc2e07e"
                }
              },
              {
                "value": 0.0,
                "currency": {
                  "meta": {
                    "href": "https://online.moysklad.ru/api/remap/1.2/entity/currency/1f3ac651-9192-11e7-7a69-97110016a959",
                    "metadataHref": "https://online.moysklad.ru/api/remap/1.2/entity/currency/metadata",
                    "type": "currency",
                    "mediaType": "application/json",
                    "uuidHref": "https://online.moysklad.ru/app/#currency/edit?id=1f3ac651-9192-11e7-7a69-97110016a959"
                  }
                },
                "priceType": {
                  "meta": {
                    "href": "https://online.moysklad.ru/api/remap/1.2/context/companysettings/pricetype/bcf7a583-6ef3-11e8-9109-f8fc00314515",
                    "type": "pricetype",
                    "mediaType": "application/json"
                  },
                  "id": "bcf7a583-6ef3-11e8-9109-f8fc00314515",
                  "name": "Распродажа",
                  "externalCode": "db0eabb7-21c2-42b7-b70f-432505bb4d97"
                }
              }
            ],
            "buyPrice": {
              "value": 510000000,
              "currency": {
                "meta": {
                  "href": "https://online.moysklad.ru/api/remap/1.2/entity/currency/1f3ac651-9192-11e7-7a69-97110016a959",
                  "metadataHref": "https://online.moysklad.ru/api/remap/1.2/entity/currency/metadata",
                  "type": "currency",
                  "mediaType": "application/json",
                  "uuidHref": "https://online.moysklad.ru/app/#currency/edit?id=1f3ac651-9192-11e7-7a69-97110016a959"
                }
              }
            },
            "barcodes": [
              {
                "ean13": "2000000004372"
              }
            ],
            "paymentItemType": "GOOD",
            "discountProhibited": false,
            "weight": 0.0,
            "volume": 0.0,
            "variantsCount": 0,
            "isSerialTrackable": false,
            "trackingType": "NOT_TRACKED",
            "files": {
              "meta": {
                "href": "https://online.moysklad.ru/api/remap/1.2/entity/product/070ffbb6-ada6-11ea-0a80-02ee0026116c/files",
                "type": "files",
                "mediaType": "application/json",
                "size": 0,
                "limit": 1000,
                "offset": 0
              }
            },
            "stock": 0.0,
            "reserve": 4.0,
            "inTransit": 0.0,
            "quantity": -4.0
          }
          ';




        $data = json_decode($json, true);

        return $data;
    }

    // public function testProductsPrices()
    // {

    //     // $data_api = [
    //     //     "salePrices" => [
    //     //         'price' => 1234567,
    //     //     ]
    //     // ];

    //     $data_api = $this->get_data();

    //     var_dump($data_api); exit;

    //     $product_id = 123;

    //     $product = Mockery::mock("WC_Product_Simple");
    //     $product->shouldReceive('get_id')->withNoArgs()->once()->andReturn($product_id);
    //     $product->shouldReceive('set_regular_price')->with(710000000)->once()->andReturn($product_id);


    //     expect('wc_get_product')
    //         ->with($product_id)
    //         ->once()
    //         ->andReturn($product);

    //     expect('get_option')
    //         ->with("wooms_price_id")
    //         ->once()
    //         ->andReturn("Цена продажи");


    //     $product = \WooMS\ProductsPrices::product_chg_price($product, $data_api);

    //     $price = $product->get_price();

    //     $this->assertSame($price, "12345,67");
    // }
}
