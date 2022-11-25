<?php
/**
 * Class SampleTest
 *
 * @package Unit_Test_Plugin
 */

/**
 * Sample test case.
 */
class TDD extends WP_UnitTestCase {

	/**
	 * Product Loaded by JSON and get ID
	 */
	public function test_Products_Walker_ProductLoaded() {
    $json = $this->get_json_product_yandex_station();
    $product_id = \WooMS\Products\load_product($json);
		$this->assertIsInt( $product_id );
	}

  public function test_CanStart() {
    $can_start = wooms_can_start();
		$this->assertTrue( $can_start );
	}

  /**
   * get JSON
   */
  public function get_json_product_yandex_station(){
    ob_start();
    ?>
{
    "meta": {
        "href": "https:\/\/online.moysklad.ru\/api\/remap\/1.2\/entity\/product\/ffbeca32-1424-11ea-0a80-054600159b23",
        "metadataHref": "https:\/\/online.moysklad.ru\/api\/remap\/1.2\/entity\/product\/metadata",
        "type": "product",
        "mediaType": "application\/json",
        "uuidHref": "https:\/\/online.moysklad.ru\/app\/#good\/edit?id=ffbec29f-1424-11ea-0a80-054600159b21"
    },
    "id": "ffbeca32-1424-11ea-0a80-054600159b23",
    "accountId": "1f2036af-9192-11e7-7a69-97110001d249",
    "owner": {
        "meta": {
            "href": "https:\/\/online.moysklad.ru\/api\/remap\/1.2\/entity\/employee\/1f2ec2de-9192-11e7-7a69-97110016a92b",
            "metadataHref": "https:\/\/online.moysklad.ru\/api\/remap\/1.2\/entity\/employee\/metadata",
            "type": "employee",
            "mediaType": "application\/json",
            "uuidHref": "https:\/\/online.moysklad.ru\/app\/#employee\/edit?id=1f2ec2de-9192-11e7-7a69-97110016a92b"
        }
    },
    "shared": true,
    "group": {
        "meta": {
            "href": "https:\/\/online.moysklad.ru\/api\/remap\/1.2\/entity\/group\/1f206231-9192-11e7-7a69-97110001d24a",
            "metadataHref": "https:\/\/online.moysklad.ru\/api\/remap\/1.2\/entity\/group\/metadata",
            "type": "group",
            "mediaType": "application\/json"
        }
    },
    "updated": "2021-06-02 19:48:46.392",
    "name": "Яндекс.Станция",
    "description": "Маленькая и умная станция",
    "code": "00045",
    "externalCode": "Wvwt5aOmhkCzW5ST7rQSR3",
    "archived": false,
    "pathName": "Электроника",
    "productFolder": {
        "meta": {
            "href": "https:\/\/online.moysklad.ru\/api\/remap\/1.2\/entity\/productfolder\/b3f6975e-9192-11e7-7a34-5acf002db9a8",
            "metadataHref": "https:\/\/online.moysklad.ru\/api\/remap\/1.2\/entity\/productfolder\/metadata",
            "type": "productfolder",
            "mediaType": "application\/json",
            "uuidHref": "https:\/\/online.moysklad.ru\/app\/#good\/edit?id=b3f6975e-9192-11e7-7a34-5acf002db9a8"
        }
    },
    "useParentVat": true,
    "uom": {
        "meta": {
            "href": "https:\/\/online.moysklad.ru\/api\/remap\/1.2\/entity\/uom\/19f1edc0-fc42-4001-94cb-c9ec9c62ec10",
            "metadataHref": "https:\/\/online.moysklad.ru\/api\/remap\/1.2\/entity\/uom\/metadata",
            "type": "uom",
            "mediaType": "application\/json"
        }
    },
    "images": {
        "meta": {
            "href": "https:\/\/online.moysklad.ru\/api\/remap\/1.2\/entity\/product\/ffbeca32-1424-11ea-0a80-054600159b23\/images",
            "type": "image",
            "mediaType": "application\/json",
            "size": 4,
            "limit": 1000,
            "offset": 0
        }
    },
    "minPrice": {
        "value": 0,
        "currency": {
            "meta": {
                "href": "https:\/\/online.moysklad.ru\/api\/remap\/1.2\/entity\/currency\/1f3ac651-9192-11e7-7a69-97110016a959",
                "metadataHref": "https:\/\/online.moysklad.ru\/api\/remap\/1.2\/entity\/currency\/metadata",
                "type": "currency",
                "mediaType": "application\/json",
                "uuidHref": "https:\/\/online.moysklad.ru\/app\/#currency\/edit?id=1f3ac651-9192-11e7-7a69-97110016a959"
            }
        }
    },
    "salePrices": [
        {
            "value": 1100000,
            "currency": {
                "meta": {
                    "href": "https:\/\/online.moysklad.ru\/api\/remap\/1.2\/entity\/currency\/1f3ac651-9192-11e7-7a69-97110016a959",
                    "metadataHref": "https:\/\/online.moysklad.ru\/api\/remap\/1.2\/entity\/currency\/metadata",
                    "type": "currency",
                    "mediaType": "application\/json",
                    "uuidHref": "https:\/\/online.moysklad.ru\/app\/#currency\/edit?id=1f3ac651-9192-11e7-7a69-97110016a959"
                }
            },
            "priceType": {
                "meta": {
                    "href": "https:\/\/online.moysklad.ru\/api\/remap\/1.2\/context\/companysettings\/pricetype\/1f3c1593-9192-11e7-7a69-97110016a95a",
                    "type": "pricetype",
                    "mediaType": "application\/json"
                },
                "id": "1f3c1593-9192-11e7-7a69-97110016a95a",
                "name": "Цена продажи",
                "externalCode": "cbcf493b-55bc-11d9-848a-00112f43529a"
            }
        },
        {
            "value": 0,
            "currency": {
                "meta": {
                    "href": "https:\/\/online.moysklad.ru\/api\/remap\/1.2\/entity\/currency\/1f3ac651-9192-11e7-7a69-97110016a959",
                    "metadataHref": "https:\/\/online.moysklad.ru\/api\/remap\/1.2\/entity\/currency\/metadata",
                    "type": "currency",
                    "mediaType": "application\/json",
                    "uuidHref": "https:\/\/online.moysklad.ru\/app\/#currency\/edit?id=1f3ac651-9192-11e7-7a69-97110016a959"
                }
            },
            "priceType": {
                "meta": {
                    "href": "https:\/\/online.moysklad.ru\/api\/remap\/1.2\/context\/companysettings\/pricetype\/e9e8d47f-2451-11e8-9ff4-34e80019ace1",
                    "type": "pricetype",
                    "mediaType": "application\/json"
                },
                "id": "e9e8d47f-2451-11e8-9ff4-34e80019ace1",
                "name": "Опт",
                "externalCode": "a59e79d7-1826-4f1a-a04c-c4e60fc2e07e"
            }
        },
        {
            "value": 0,
            "currency": {
                "meta": {
                    "href": "https:\/\/online.moysklad.ru\/api\/remap\/1.2\/entity\/currency\/1f3ac651-9192-11e7-7a69-97110016a959",
                    "metadataHref": "https:\/\/online.moysklad.ru\/api\/remap\/1.2\/entity\/currency\/metadata",
                    "type": "currency",
                    "mediaType": "application\/json",
                    "uuidHref": "https:\/\/online.moysklad.ru\/app\/#currency\/edit?id=1f3ac651-9192-11e7-7a69-97110016a959"
                }
            },
            "priceType": {
                "meta": {
                    "href": "https:\/\/online.moysklad.ru\/api\/remap\/1.2\/context\/companysettings\/pricetype\/bcf7a583-6ef3-11e8-9109-f8fc00314515",
                    "type": "pricetype",
                    "mediaType": "application\/json"
                },
                "id": "bcf7a583-6ef3-11e8-9109-f8fc00314515",
                "name": "Распродажа",
                "externalCode": "db0eabb7-21c2-42b7-b70f-432505bb4d97"
            }
        }
    ],
    "buyPrice": {
        "value": 800000,
        "currency": {
            "meta": {
                "href": "https:\/\/online.moysklad.ru\/api\/remap\/1.2\/entity\/currency\/1f3ac651-9192-11e7-7a69-97110016a959",
                "metadataHref": "https:\/\/online.moysklad.ru\/api\/remap\/1.2\/entity\/currency\/metadata",
                "type": "currency",
                "mediaType": "application\/json",
                "uuidHref": "https:\/\/online.moysklad.ru\/app\/#currency\/edit?id=1f3ac651-9192-11e7-7a69-97110016a959"
            }
        }
    },
    "barcodes": [
        {
            "ean13": "2000000004266"
        }
    ],
    "paymentItemType": "GOOD",
    "discountProhibited": false,
    "article": "ya-st",
    "weight": 0,
    "volume": 0,
    "variantsCount": 1,
    "isSerialTrackable": false,
    "trackingType": "NOT_TRACKED",
    "files": {
        "meta": {
            "href": "https:\/\/online.moysklad.ru\/api\/remap\/1.2\/entity\/product\/ffbeca32-1424-11ea-0a80-054600159b23\/files",
            "type": "files",
            "mediaType": "application\/json",
            "size": 0,
            "limit": 1000,
            "offset": 0
        }
    }
}
    <?php
    $data = ob_get_clean();
    $data = json_decode($data, true);
    return $data;
  }
}
