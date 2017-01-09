<?php

/**
 * Import variants from MoySklad
 */
class woomss_tool_import_variaints {

  public $url;
  public $slug = 'woomss_tool_import_variaints';
  public $btn_text = 'Импортировать вариации';



  function __construct(){

    $this->url = $_SERVER['REQUEST_URI'];

    add_action( 'woomss_tool_actions_btns', [$this, 'woomss_tool_actions_btns_callback'], $priority = 20, $accepted_args = 1 );

    add_action( 'woomss_tool_actions', [$this, 'woomss_tool_actions_cb'], $priority = 10, $accepted_args = 1 );

  }


  function woomss_tool_actions_cb(){

    if( empty($_GET['a']) or $_GET['a'] != $this->slug)
      return;

    echo '<hr/>';


    $url_get = 'https://online.moysklad.ru/api/remap/1.1/entity/variant/';

    $username = get_option( 'woomss_login' );
    $password = get_option( 'woomss_pass' );

    $args = array(
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password )
        )
      );


    $response = wp_remote_get( $url_get, $args );
    $body = $response['body'];

    $data = json_decode( $body, true );
    $data = $data['rows'];

    foreach ($data as $key => $value) {

      printf('<h2>%s</h2>', $value['name']);
      printf('<p>id: %s</p>', $value['id']);
      printf('<pre>%s</pre>', print_r($value, true));

    }


    echo '<p>end job</p>';
  }


  function woomss_tool_actions_btns_callback(){
      printf(
        '<section><a href="%s" class="button">%s</a></section>',
        add_query_arg('a', $this->slug, $this->url),
        $this->btn_text
      );
  }


} new woomss_tool_import_variaints;
