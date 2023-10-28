<?php

namespace WooMS\Tests\ProductVariable;

use WooMS\ProductVariable, Error;
use function Testeroid\{test, transaction_query, ddcli};

use function WooMS\{request, set_config};

use function WooMS\Products\{get_product_id_by_uuid, process_rows, walker};
use function WooMS\Tests\{getProductsRows, get_variant};

require_once __DIR__ . '/../functions.php';




test('variation - one - Ботинки мужские RINGO (41)', function(){

	$row = function(){
		$json = '{
			"meta": {
				"href": "https:\/\/api.moysklad.ru\/api\/remap\/1.2\/entity\/variant\/1db2577d-d5fe-11e7-7a31-d0fd00115e39",
				"metadataHref": "https:\/\/api.moysklad.ru\/api\/remap\/1.2\/entity\/variant\/metadata",
				"type": "variant",
				"mediaType": "application\/json",
				"uuidHref": "https:\/\/online.moysklad.ru\/app\/#feature\/edit?id=1db252e3-d5fe-11e7-7a31-d0fd00115e37"
			},
			"id": "1db2577d-d5fe-11e7-7a31-d0fd00115e39",
			"accountId": "1f2036af-9192-11e7-7a69-97110001d249",
			"updated": "2019-02-24 17:06:19.221",
			"name": "Ботинки мужские RINGO (41)",
			"code": "00002",
			"externalCode": "fxksEGSthpknzzjbDgqJo1",
			"archived": false,
			"characteristics": [
				{
					"meta": {
						"href": "https:\/\/api.moysklad.ru\/api\/remap\/1.2\/entity\/variant\/metadata\/characteristics\/1dad9735-d5fe-11e7-7a31-d0fd00115e36",
						"type": "attributemetadata",
						"mediaType": "application\/json"
					},
					"id": "1dad9735-d5fe-11e7-7a31-d0fd00115e36",
					"name": "Размер",
					"value": "41"
				}
			],
			"images": {
				"meta": {
					"href": "https:\/\/api.moysklad.ru\/api\/remap\/1.2\/entity\/variant\/1db2577d-d5fe-11e7-7a31-d0fd00115e39\/images",
					"type": "image",
					"mediaType": "application\/json",
					"size": 0,
					"limit": 1000,
					"offset": 0
				}
			},
			"salePrices": [
				{
					"value": 500000,
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
			"barcodes": [
				{
					"ean13": "2000000000374"
				}
			],
			"discountProhibited": false,
			"product": {
				"meta": {
					"href": "https:\/\/api.moysklad.ru\/api\/remap\/1.2\/entity\/product\/4dc138a7-d532-11e7-7a69-8f55000890d1",
					"metadataHref": "https:\/\/api.moysklad.ru\/api\/remap\/1.2\/entity\/product\/metadata",
					"type": "product",
					"mediaType": "application\/json",
					"uuidHref": "https:\/\/online.moysklad.ru\/app\/#good\/edit?id=4dc12d87-d532-11e7-7a69-8f55000890cf"
				}
			}
		}
		';

		return json_decode( $json, true );


	};

	$result = ProductVariable::update_variation($row());
	if(empty($result[0])){
		throw new Error( 'не вернулся продукт' );
	}
	if(empty($result[1])){
		throw new Error( 'не вернулась вариация' );
	}

	return true;
});


test('variations - base test', function(){
	transaction_query('start');

	$data = \WooMS\Tests\get_variant();

	$count = ProductVariable::process_rows($data['rows']);

	transaction_query('rollback');

	if($count){
		return true;
	}

	return false;

});

