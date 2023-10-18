<?php

namespace WooMS\Tests\RestApi;

use function Testeroid\{test, transaction_query, ddcli};
use function WooMS\{request, set_config};

test('check woooms request new api', function(){

	$data = request('https://api.moysklad.ru/api/remap/1.2/entity/product');

	if(isset($data['context'])){
		return true;
	}

	return false;

});

test('check woooms request new api', function(){

	$data = request('entity/product');

	if(isset($data['context'])){
		return true;
	}

	return false;

});
