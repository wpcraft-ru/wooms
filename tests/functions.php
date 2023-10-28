<?php

namespace WooMS\Tests;

function getProductsRows(){
  $strJsonFileContents = file_get_contents(__DIR__ . "/data/products.json");
  $data = json_decode($strJsonFileContents, true);
  return $data['rows'];
}

function get_variant(){
  $strJsonFileContents = file_get_contents(__DIR__ . "/data/variants.json");
  $data = json_decode($strJsonFileContents, true);
  return $data;
}

function get_productfolder(){
  $strJsonFileContents = file_get_contents(__DIR__ . "/data/productfolder.json");
  $data = json_decode($strJsonFileContents, true);
  return $data;
}
