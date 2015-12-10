<?php



//Отправляем xml файл на сервер МойСклад с указанием типа сущности
//Возвращает ответ сервера
//Документация https://support.moysklad.ru/hc/ru/articles/203402923-%D0%9F%D1%80%D0%B8%D0%BC%D0%B5%D1%80-%D0%B7%D0%B0%D0%B3%D1%80%D1%83%D0%B7%D0%BA%D0%B8-%D0%B7%D0%B0%D0%BA%D0%B0%D0%B7%D0%B0-%D0%BF%D0%BE%D0%BA%D1%83%D0%BF%D0%B0%D1%82%D0%B5%D0%BB%D1%8F-%D1%87%D0%B5%D1%80%D0%B5%D0%B7-REST-API
function send_xml_to_moysklad($type, $xml_body){

  $body = $xml_body;

  $login = get_option('mss_login_s');
  $pass = get_option('mss_pass_s');

  $sock = fsockopen("ssl://online.moysklad.ru", 443, $errno, $errstr, 30);
  if (!$sock) die("$errstr ($errno)\n");


  fputs($sock, "PUT /exchange/rest/ms/xml/$type HTTP/1.1\r\n");
  fputs($sock, "Host: online.moysklad.ru\r\n");
  fputs($sock, "Authorization: Basic " . base64_encode("$login:$pass") . "\r\n");
  fputs($sock, "Content-Type: application/xml \r\n");
  fputs($sock, "Accept: */*\r\n");
  fputs($sock, "Content-Length: ".strlen($body)."\r\n");
  fputs($sock, "Connection: close\r\n\r\n");
  fputs($sock, "$body");

  while ($str = trim(fgets($sock, 4096)));

  $body = "";

  while (!feof($sock))
      $body.= fgets($sock, 4096);
  fclose($sock);
  if($body)
    return $body;
  return false;

}
