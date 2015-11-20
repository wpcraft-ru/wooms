<?php 
	function get_moysklad_information($type, $uuid = 'list', $start=0, $count=1000) {
		$type .= "/".$uuid;
		
		$login = get_option('woosklad_login');
		$password = get_option('woosklad_password');
		
		$ch = curl_init("https://online.moysklad.ru/exchange/rest/ms/xml/$type?start=$start&count=$count");
		
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);		
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 0);
		curl_setopt($ch, CURLOPT_USERPWD, "$login:$password");
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, TRUE);
		
		$result = curl_exec($ch); 
		$info = curl_getinfo ($ch);
		//echo "<pre>"; print_r($info); echo "</pre>";
		curl_close($ch);
		if ($info['http_code'] == 200) {
			return simplexml_load_string($result);
		}
		else echo "<pre>"; print_r($result); echo "</pre>";
		return 0;
	}
	
	function get_stock(&$result, $uuid='') {
		$login = get_option('woosklad_login');
		$pass = get_option('woosklad_password');
		
		$data = array(	'stockMode' => 'NON_EMPTY', 
						'showConsignments' => 'true');
		if ($uuid) $data['storeUuid'] = $uuid;
		$ch = curl_init();
		
		curl_setopt($ch, CURLOPT_URL, add_query_arg($data, "https://online.moysklad.ru/exchange/rest/stock/json"));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 0);
		curl_setopt($ch, CURLOPT_USERPWD, "$login:$pass");
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, TRUE);
		
		$result = curl_exec($ch);
		$info = curl_getinfo ($ch);
		curl_close($ch);
		
		return $info['http_code'];
	}
	
	function put_update_type($type, $xml) {
		$login = get_option('woosklad_login');
		$pass = get_option('woosklad_password');

		$sock = fsockopen("ssl://online.moysklad.ru", 443, $errno, $errstr, 30);
 
		if (!$sock) die("$errstr ($errno)\n");
 
		fputs($sock, "PUT /exchange/rest/ms/xml/$type HTTP/1.1\r\n");
		fputs($sock, "Host: online.moysklad.ru\r\n");
		fputs($sock, "Authorization: Basic " . base64_encode("$login:$pass") . "\r\n");
		fputs($sock, "Content-Type: application/xml \r\n");
		fputs($sock, "Accept: */*\r\n");
		fputs($sock, "Content-Length: ".strlen($xml)."\r\n");
		fputs($sock, "Connection: close\r\n\r\n");
		fputs($sock, "$xml");
		
		while ($str = trim(fgets($sock, 4096)));
 
		$body = "";
 
		while (!feof($sock))
			$body.= fgets($sock, 4096);
 
		fclose($sock);
		sleep(1);
		if ($body)
			return simplexml_load_string($body);
		return 0;
	}
	
	function delete_type_uuid($type, $uuid) {
		$login = get_option('woosklad_login');
		$pass = get_option('woosklad_password');

		$sock = fsockopen("ssl://online.moysklad.ru", 443, $errno, $errstr, 30);
 
		if (!$sock) die("$errstr ($errno)\n");
 
		fputs($sock, "DELETE /exchange/rest/ms/xml/$type/$uuid HTTP/1.1\r\n");
		fputs($sock, "Host: online.moysklad.ru\r\n");
		fputs($sock, "Authorization: Basic " . base64_encode("$login:$pass") . "\r\n");
		fputs($sock, "Content-Type: application/xml \r\n");
		fputs($sock, "Accept: */*\r\n");
		fputs($sock, "Content-Length: ".strlen($xml)."\r\n");
		fputs($sock, "Connection: close\r\n\r\n");
		//fputs($sock, "$xml");
		
		while ($str = trim(fgets($sock, 4096)));
 
		$body = "";
 
		while (!feof($sock))
			$body.= fgets($sock, 4096);
 
		fclose($sock);
		sleep(1);
		update_option('woosklad_result_body', simplexml_load_string($body));
		if ($body)
			return simplexml_load_string($body);
		return 0;
	}
?>