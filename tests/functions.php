<?php

namespace WooMS\Tests;

function getProductsRows() {

	// $data = \WooMS\request( 'entity/product' );
	// $data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
	// ddcli($data);
	$json = file_get_contents( __DIR__ . "/data/products.json" );
	$data = json_decode( $json, true );
	return $data['rows'];
}

function get_variant() {
	$strJsonFileContents = file_get_contents( __DIR__ . "/data/variants.json" );
	$data = json_decode( $strJsonFileContents, true );
	return $data;
}

function get_productfolder() {
	$strJsonFileContents = file_get_contents( __DIR__ . "/data/productfolder.json" );
	$data = json_decode( $strJsonFileContents, true );
	return $data;
}

function get_assortment() {
	$strJsonFileContents = file_get_contents( __DIR__ . "/data/assortment.json" );
	$data = json_decode( $strJsonFileContents, true );
	return $data;
}
