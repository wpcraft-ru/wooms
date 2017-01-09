<?php
/**
 *
 */
class woomss_import {

    public $url;
    public $section_title;
    public $section_exerpt;
    public $slug;
    public $slug_action;
    public $btn_text;

    function __construct(){

      $this->section_title = __('Section header');
      $this->section_exerpt = __('Excerpt for section and simple instruction');
      $this->slug = 'woomss_import';
      $this->slug_action = 'woomss_import_do';
      $this->btn_text = __('Do it!');


      $this->url = $_SERVER['REQUEST_URI'];
      add_action( 'woomss_tool_actions_btns', [$this, 'woomss_tool_actions_btns_callback'], $priority = 20, $accepted_args = 1 );
      add_action( 'woomss_tool_actions', [$this, 'woomss_tool_actions_cb'], $priority = 10, $accepted_args = 1 );
    }

    function load_data(){

      //example code
      echo '<p>start job</p>';
      echo '<p>do job ...</p>';
      echo '<p>end job</p>';

    }

    public function woomss_tool_actions_cb(){

      if( empty($_GET['a']) or $_GET['a'] != $this->slug)
        return;

      echo '<hr/>';
      $this->load_data();

    }

    function woomss_tool_actions_btns_callback(){
      ?>
        <form method="GET" action="<?php echo $this->url ?>">
          <?php
            printf('<h2>%s</h2>', $this->section_title);
            printf('<p>%s</p>', $this->section_exerpt);
            printf('<input type="hidden" name="a" value="%s" />', $this->slug);
            printf('<input type="hidden" name="page" value="%s" />', 'moysklad');
            printf('<input type="submit" class="button" value="%s" >', $this->btn_text);
          ?>
          <hr/>
        </form>
      <?php
    }



    function get_data_by_url($url = ''){

      if(empty($url))
        return false;

      $args = array(
          'headers' => array(
              'Authorization' => 'Basic ' . base64_encode( get_option( 'woomss_login' ) . ':' . get_option( 'woomss_pass' ) )
          )
        );

      $response = wp_remote_get( $url, $args );
      $body = $response['body'];

      return json_decode( $body, true );

    }
}
