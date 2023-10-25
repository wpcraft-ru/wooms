<?php

namespace WooMS\Tests\Categories;

use Error;
use function Testeroid\{test, transaction_query, ddcli};
use function WooMS\Tests\{getProductsRows, getVariantsRows};
use function WooMS\Products\{get_product_id_by_uuid, process_rows};


transaction_query( 'start' );



/**
 * to tests
 */
test( 'save categories and product', function () {

	$args = array(
		"hide_empty" => 0,
		"taxonomy" => "product_cat",
		"orderby" => "name",
		"order" => "ASC"
	);
	$types = get_categories( $args );

	foreach ( $types as $type ) {
		wp_delete_category( $type->ID );
	}

	$data = \WooMS\Tests\get_productfolder();

	$list = \WooMS\ProductsCategories::product_categories_update( $data );

	if ( empty( $list ) ) {
		throw new Error( 'тут нужны категории' );
	}

	$row = function () {

		$json = '{
			"meta": {
				"href": "https:\/\/api.moysklad.ru\/api\/remap\/1.2\/entity\/product\/4dc138a7-d532-11e7-7a69-8f55000890d1",
				"metadataHref": "https:\/\/api.moysklad.ru\/api\/remap\/1.2\/entity\/product\/metadata",
				"type": "product",
				"mediaType": "application\/json",
				"uuidHref": "https:\/\/online.moysklad.ru\/app\/#good\/edit?id=4dc12d87-d532-11e7-7a69-8f55000890cf"
			},
			"id": "4dc138a7-d532-11e7-7a69-8f55000890d1",
			"accountId": "1f2036af-9192-11e7-7a69-97110001d249",
			"owner": {
				"meta": {
					"href": "https:\/\/api.moysklad.ru\/api\/remap\/1.2\/entity\/employee\/1f2ec2de-9192-11e7-7a69-97110016a92b",
					"metadataHref": "https:\/\/api.moysklad.ru\/api\/remap\/1.2\/entity\/employee\/metadata",
					"type": "employee",
					"mediaType": "application\/json",
					"uuidHref": "https:\/\/online.moysklad.ru\/app\/#employee\/edit?id=1f2ec2de-9192-11e7-7a69-97110016a92b"
				}
			},
			"shared": true,
			"group": {
				"meta": {
					"href": "https:\/\/api.moysklad.ru\/api\/remap\/1.2\/entity\/group\/1f206231-9192-11e7-7a69-97110001d24a",
					"metadataHref": "https:\/\/api.moysklad.ru\/api\/remap\/1.2\/entity\/group\/metadata",
					"type": "group",
					"mediaType": "application\/json"
				}
			},
			"updated": "2019-02-24 17:06:19.224",
			"name": "Ботинки мужские RINGO",
			"code": "00026",
			"externalCode": "Lt-6EDVBjlPAtc5Hr7LV72",
			"archived": false,
			"pathName": "Одежда\/Обувь\/Ботинки",
			"productFolder": {
				"meta": {
					"href": "https:\/\/api.moysklad.ru\/api\/remap\/1.2\/entity\/productfolder\/380c02c8-d532-11e7-7a6c-d2a90010eb13",
					"metadataHref": "https:\/\/api.moysklad.ru\/api\/remap\/1.2\/entity\/productfolder\/metadata",
					"type": "productfolder",
					"mediaType": "application\/json",
					"uuidHref": "https:\/\/online.moysklad.ru\/app\/#good\/edit?id=380c02c8-d532-11e7-7a6c-d2a90010eb13"
				}
			},
			"useParentVat": true,
			"uom": {
				"meta": {
					"href": "https:\/\/api.moysklad.ru\/api\/remap\/1.2\/entity\/uom\/19f1edc0-fc42-4001-94cb-c9ec9c62ec10",
					"metadataHref": "https:\/\/api.moysklad.ru\/api\/remap\/1.2\/entity\/uom\/metadata",
					"type": "uom",
					"mediaType": "application\/json"
				}
			},
			"images": {
				"meta": {
					"href": "https:\/\/api.moysklad.ru\/api\/remap\/1.2\/entity\/product\/4dc138a7-d532-11e7-7a69-8f55000890d1\/images",
					"type": "image",
					"mediaType": "application\/json",
					"size": 1,
					"limit": 1000,
					"offset": 0
				}
			},
			"minPrice": {
				"value": 0,
				"currency": {
					"meta": {
						"href": "https:\/\/api.moysklad.ru\/api\/remap\/1.2\/entity\/currency\/1f3ac651-9192-11e7-7a69-97110016a959",
						"metadataHref": "https:\/\/api.moysklad.ru\/api\/remap\/1.2\/entity\/currency\/metadata",
						"type": "currency",
						"mediaType": "application\/json",
						"uuidHref": "https:\/\/online.moysklad.ru\/app\/#currency\/edit?id=1f3ac651-9192-11e7-7a69-97110016a959"
					}
				}
			},
			"salePrices": [
				{
					"value": 670000,
					"currency": {
						"meta": {
							"href": "https:\/\/api.moysklad.ru\/api\/remap\/1.2\/entity\/currency\/1f3ac651-9192-11e7-7a69-97110016a959",
							"metadataHref": "https:\/\/api.moysklad.ru\/api\/remap\/1.2\/entity\/currency\/metadata",
							"type": "currency",
							"mediaType": "application\/json",
							"uuidHref": "https:\/\/online.moysklad.ru\/app\/#currency\/edit?id=1f3ac651-9192-11e7-7a69-97110016a959"
						}
					},
					"priceType": {
						"meta": {
							"href": "https:\/\/api.moysklad.ru\/api\/remap\/1.2\/context\/companysettings\/pricetype\/1f3c1593-9192-11e7-7a69-97110016a95a",
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
							"href": "https:\/\/api.moysklad.ru\/api\/remap\/1.2\/entity\/currency\/1f3ac651-9192-11e7-7a69-97110016a959",
							"metadataHref": "https:\/\/api.moysklad.ru\/api\/remap\/1.2\/entity\/currency\/metadata",
							"type": "currency",
							"mediaType": "application\/json",
							"uuidHref": "https:\/\/online.moysklad.ru\/app\/#currency\/edit?id=1f3ac651-9192-11e7-7a69-97110016a959"
						}
					},
					"priceType": {
						"meta": {
							"href": "https:\/\/api.moysklad.ru\/api\/remap\/1.2\/context\/companysettings\/pricetype\/e9e8d47f-2451-11e8-9ff4-34e80019ace1",
							"type": "pricetype",
							"mediaType": "application\/json"
						},
						"id": "e9e8d47f-2451-11e8-9ff4-34e80019ace1",
						"name": "Опт",
						"externalCode": "a59e79d7-1826-4f1a-a04c-c4e60fc2e07e"
					}
				},
				{
					"value": 300000,
					"currency": {
						"meta": {
							"href": "https:\/\/api.moysklad.ru\/api\/remap\/1.2\/entity\/currency\/1f3ac651-9192-11e7-7a69-97110016a959",
							"metadataHref": "https:\/\/api.moysklad.ru\/api\/remap\/1.2\/entity\/currency\/metadata",
							"type": "currency",
							"mediaType": "application\/json",
							"uuidHref": "https:\/\/online.moysklad.ru\/app\/#currency\/edit?id=1f3ac651-9192-11e7-7a69-97110016a959"
						}
					},
					"priceType": {
						"meta": {
							"href": "https:\/\/api.moysklad.ru\/api\/remap\/1.2\/context\/companysettings\/pricetype\/bcf7a583-6ef3-11e8-9109-f8fc00314515",
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
				"value": 0,
				"currency": {
					"meta": {
						"href": "https:\/\/api.moysklad.ru\/api\/remap\/1.2\/entity\/currency\/1f3ac651-9192-11e7-7a69-97110016a959",
						"metadataHref": "https:\/\/api.moysklad.ru\/api\/remap\/1.2\/entity\/currency\/metadata",
						"type": "currency",
						"mediaType": "application\/json",
						"uuidHref": "https:\/\/online.moysklad.ru\/app\/#currency\/edit?id=1f3ac651-9192-11e7-7a69-97110016a959"
					}
				}
			},
			"barcodes": [
				{
					"ean13": "2000000000282"
				}
			],
			"paymentItemType": "GOOD",
			"discountProhibited": false,
			"country": {
				"meta": {
					"href": "https:\/\/api.moysklad.ru\/api\/remap\/1.2\/entity\/country\/9df7c2c3-7782-4c5c-a8ed-1102af611608",
					"metadataHref": "https:\/\/api.moysklad.ru\/api\/remap\/1.2\/entity\/country\/metadata",
					"type": "country",
					"mediaType": "application\/json",
					"uuidHref": "https:\/\/online.moysklad.ru\/app\/#country\/edit?id=9df7c2c3-7782-4c5c-a8ed-1102af611608"
				}
			},
			"article": "98765",
			"weight": 333,
			"volume": 55,
			"variantsCount": 5,
			"isSerialTrackable": false,
			"trackingType": "NOT_TRACKED",
			"files": {
				"meta": {
					"href": "https:\/\/api.moysklad.ru\/api\/remap\/1.2\/entity\/product\/4dc138a7-d532-11e7-7a69-8f55000890d1\/files",
					"type": "files",
					"mediaType": "application\/json",
					"size": 0,
					"limit": 1000,
					"offset": 0
				}
			}
		}';

		return json_decode( $json, true );

	};

	$product = \WooMS\Products\product_update( $row() );

	$ids = $product->get_category_ids();

	if ( empty( $ids ) ) {
		throw new Error( 'Категорий нет' );
	}

	return true;

} );
/**
 * to tests
 */
test( 'save categories', function () {

	$args = array(
		"hide_empty" => 0,
		"taxonomy" => "product_cat",
		"orderby" => "name",
		"order" => "ASC"
	);
	$types = get_categories( $args );

	foreach ( $types as $type ) {
		wp_delete_category( $type->ID );
	}


	$data = \WooMS\Tests\get_productfolder();

	$list = \WooMS\ProductsCategories::product_categories_update( $data );

	if ( empty( $list ) ) {
		throw new Error( 'тут нужны категории' );
	}

	return true;

} );

transaction_query('rollback');
