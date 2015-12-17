<?php

/**
 * Экспорт продуктов из сайта в МойСклад
 */
class MSSProductsCatImport
{
  function __construct()
  {
    add_action('add_section_mss_tool', array($this, 'add_section_mss_tool_callback'));

    if(is_admin()){
      add_action('wp_ajax_mss_product_cat_import', array($this,'mss_product_cat_import_ajax_callback'));
      add_action('wp_ajax_nopriv_mss_product_cat_import', array($this,'mss_product_cat_import_ajax_callback'));
    }
  }

  //Запуск обработки экспорта товаров
  function mss_product_cat_import_ajax_callback(){

    //Подготовка данных для запроса
    $login = get_option('mss_login_s');
    $pass = get_option('mss_pass_s');

    $url = 'https://online.moysklad.ru/exchange/rest/ms/xml/GoodFolder/list';
    $args = array(
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode( $login . ':' . $pass )
        )
    );

    //Запрос и получение XML-ответа
    $data_remote = wp_remote_get( $url, $args );
    $body = wp_remote_retrieve_body($data_remote);
    $xml  = new SimpleXMLElement($body); //преобразование ответа в XML-объект


    $counts = array('i' => 0, 'a' => 0, 'b' => 0,  'c' => 0); //счетчики для контроля результатов


    //Цикл по всем записям, без учета данных о родителе (родительские отношения обновлять будем вторым циклом)
    foreach($xml->goodFolder as $goodFolder) {

      $counts['i']++; //общий счетчик обработанных записей
      //Проверка родительской категории
      //if((string)$goodFolder['parentUuid']) continue; //если есть родительская категория, то пропуск

      //Проверка наличия термина с таким uuid
      $uuid = (string)$goodFolder->uuid; //код категории в МойСклад
      $check_term_id = get_term_id_by_uuid_mss($uuid);
      if($check_term_id) continue; //Если термин нашелся, то создавать ничего не надо и просто пропускаем


      //Проверка сходства по имени
      $name = (string)$goodFolder['name']; //имя продукта
      $check_term_by_name = get_terms($taxonomies = 'product_cat', array('name__like' => $name));
      if($check_term_by_name) {
        update_uuid_product_category_mss($check_term_by_name[0]->term_id, $uuid); //присваиваем значение uuid к термину таксономии
        $counts['a']++;
        continue;
      }


      $productCode = (string)$goodFolder['productCode']; //артикул продукта
      $updated = (string)$goodFolder['updated']; //дата обновления
      $data[] = $name;

      //Если это новый термин, то добавляем
      $new_term = wp_insert_term( $term = $name, $taxonomy = 'product_cat' );
      update_uuid_product_category_mss($new_term['term_id'], $uuid); //присваиваем значение uuid к термину таксономии
      $counts['b']++;
    }


    //Цикл для обновления данных о родителе
    foreach($xml->goodFolder as $goodFolder) {

      //Проверка родительской категории
      if(! $goodFolder['parentUuid']) continue; //если нет родительской категории, то пропуск

      $parentUuid = (string)$goodFolder['parentUuid']; //сохраняем uuid родителя

      //берем uuid из данных МойСклад и получаем по нему данные соответствующего термина в WP
      $uuid = (string)$goodFolder->uuid; //код категории в МойСклад
      $check_term_id = get_term_id_by_uuid_mss($uuid);
      if($check_term_id) $term_id = $check_term_id;
      $term = get_term( $term_id, $taxonomy = 'product_cat');

      $term_parent_id_from_mss = get_term_id_by_uuid_mss($parentUuid); //получаем term_id родителя по uuid родителя
      $term_parent_id_from_wp = $term->parent; //получаем term_id текущего родителя
      if($term_parent_id_from_mss != $term_parent_id_from_wp) {
        wp_update_term(
          $term_id,
          'product_cat',
          array(
            'parent' => $term_parent_id_from_mss
          )
        );
        $counts['c']++; //проверка обновленных родителей

      }
    }

    //подготовка и отправка данных о результатах для клиента
    $data = 'обработано записей = ' . $counts['i'];
    $data .= ", обновлено терминов = " . $counts['a'];
    $data .= ", создано терминов = " . $counts['b'];
    $data .= ', обновлено родителей: ' . $counts['c'];
    wp_send_json_success($data);

  }



  //Интерфейс пользователя для запуска выгрузки продуктов
  function add_section_mss_tool_callback(){
    ?>
    <section id="mss-product-cat-import">
      <header>
        <hr>
        <h2>Импорт категорий товаров из МойСклад на сайт</h2>
      </header>
      <div class="instruction">
        <p>Эта обработка импортирует группы МойСклад в категории продуктов WooCommerce.</p>
        <p>Если нет то создает новую категорию продуктов.</p>
        <p>Записывает в мету термина uuid_product_category_mss идентификатор из группы МойСклад (uuid).</p>
        <p>Пытается связать группу по имени и если находит то связывает их через запись uuid.</p>
      </div>
      <button class="button button-small">Импортировать</button>

      <div class="status-wrapper hide-if-js">
        <strong>Статус импорта категорий: </strong>
        <ul>
          <li>Результат первой итерации: <span class="first-result">отсутствует</span></li>
        </ul>
      </div>

      <script type="text/javascript">
          jQuery(document).ready(function($) {
            $('#mss-product-cat-import button').click(function () {

              //при нажатии кнопки показываем блок со статусными данными
              $('#mss-product-cat-import .status-wrapper').show();

              var data = {
                action: 'mss_product_cat_import',
              };
              $.getJSON(ajaxurl, data, function(response){
                //по результату запроса подменяем текст в статусе на сонове вернувшихся данных
                $('#mss-product-cat-import .first-result').text('успех = ' + response.success + ' (' + response.data + ')');

              });
            });
          });
      </script>

    </section>
    <?php
  }
} $TheMSSProductsCatImport = new MSSProductsCatImport;
