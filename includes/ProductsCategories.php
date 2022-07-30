<?php

namespace WooMS;

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

/**
 * Import Product Categories from MoySklad
 */
class ProductsCategories
{

  /**
   * WooMS_Import_Product_Categories constructor.
   */
  public static function init()
  {

    // add_action('init', function () {
    //   if (!isset($_GET['dd'])) {
    //     return;
    //   }

    //   dd(get_option());

    //   dd(0);
    // });

    add_filter('wooms_product_save', array(__CLASS__, 'product_save'), 10, 3);
    add_filter('wooms_product_save', array(__CLASS__, 'add_ancestors'), 15, 2);

    add_action('admin_init', array(__CLASS__, 'add_settings'), 50);
    add_action('product_cat_edit_form_fields', array(__CLASS__, 'display_data_category'), 30);
  }

  /**
   * add ancestors 
   * 
   * issue https://github.com/wpcraft-ru/wooms/issues/282
   */
  public static function add_ancestors($product, $data_api)
  {
    if (!get_option('wooms_categories_include_children')) {
      return $product;
    }

    if (empty($data_api['productFolder']['meta']['href'])) {
      return $product;
    }

    if (!$term_id = self::check_term_by_ms_uuid($data_api['productFolder']['meta']['href'])) {
      return $product;
    }

    // $product = wc_get_product($product);

    $term_ancestors = get_ancestors($term_id, 'product_cat', 'taxonomy');

    $term_ancestors[] = $term_id;
    $product->set_category_ids($term_ancestors);

    return $product;
  }

  /**
   * Загрузка данных категории для продукта
   */
  public static function product_save($product, $value, $data)
  {

    //Если опция отключена - пропускаем обработку
    if (self::is_disable()) {
      return $product;
    }

    if (empty($value['productFolder']['meta']['href'])) {
      return $product;
    }

    $product_id = $product->get_id();

    $url = $value['productFolder']['meta']['href'];

    if ($term_id = self::update_category($url)) {

      $result = $product->set_category_ids(array($term_id));

      if (is_wp_error($result)) {
        do_action('wooms_logger_error', __CLASS__, $result->get_error_code(), $result->get_error_message());
      } elseif ($result === false) {
        do_action('wooms_logger_error', __CLASS__, 'Не удалось выбрать категорию', $term_id);
      } else {
        do_action(
          'wooms_logger',
          __CLASS__,
          sprintf('Выбор категории продукта %s (id %s)', $product->get_name(), $product->get_id()),
          [
            '$url' => $url,
            '$term_id' => $term_id,
            'term_name' => get_term_by('id', $term_id, 'product_cat')->name,
            '$product_id' => $product_id,
            'select_cat_pid' => $product_id,
          ]
        );
      }
    }

    return $product;
  }

  /**
   * If isset term return term_id, else return false
   */
  public static function check_term_by_ms_uuid($id)
  {
    //if uuid as url - get uuid only
    $id = str_replace('https://online.moysklad.ru/api/remap/1.2/entity/productfolder/', '', $id);

    $terms = get_terms(array(
      'taxonomy'   => array('product_cat'),
      'hide_empty' => false,
      'meta_query' => array(
        array(
          'key'   => 'wooms_id',
          'value' => $id,
        ),
      ),
    ));

    if (is_wp_error($terms) or empty($terms)) {
      return false;
    } else {
      foreach ($terms as $term) {
        if (isset($term->term_id)) {
          return $term->term_id;
        } else {
          return false;
        }
      }
    }
  }


  /**
   * Create and update categories
   *
   * @param $url
   */
  public static function update_category($url)
  {
    /**
     * Если указана группа для синхронизации,
     * и совпадает с запрошенной, то вернуть false, чтобы прекратить рекурсию создания родителей
     */
    if ($url == get_option('woomss_include_categories_sync')) {
      return false;
    }

    $data = wooms_request($url);

    if ($term_id = self::check_term_by_ms_uuid($data['id'])) {

      do_action('wooms_update_category', $term_id);

      $args_update = array();
      $url_parent = '';

      if (isset($data['productFolder']['meta']['href'])) {
        $url_parent = $data['productFolder']['meta']['href'];
        if ($term_id_parent = self::update_category($url_parent)) {
          $args_update['parent'] = isset($term_id_parent) ? intval($term_id_parent) : 0;
        }
      }

      if (apply_filters('wooms_skip_update_select_category', true, $url_parent)) {
        $term = wp_update_term($term_id, 'product_cat', $args_update);
      }

      wp_update_term_count($term_id, $taxonomy = 'product_cat');

      update_term_meta($term_id, 'wooms_updated_category', $data['updated']);

      if (is_array($term) && !empty($term["term_id"])) {
        return $term["term_id"];
      } else {
        return false;
      }
    } else {

      $args = array();

      $term_new = array(
        'wooms_id' => $data['id'],
        'name'     => $data['name'],
        'archived' => $data['archived'],
      );

      if (isset($data['productFolder']['meta']['href'])) {
        $url_parent = $data['productFolder']['meta']['href'];
        if ($term_id_parent = self::update_category($url_parent)) {
          $args['parent'] = intval($term_id_parent);
        }
      }

      $url_parent = isset($data['productFolder']['meta']['href']) ? $data['productFolder']['meta']['href'] : '';
      $path_name  = isset($data['pathName']) ? $data['pathName'] : null;

      if (apply_filters('wooms_skip_categories', true, $url_parent, $path_name)) {

        $term = wp_insert_term($term_new['name'], $taxonomy = 'product_cat', $args);
        if (is_wp_error($term)) {

          if (isset($term->error_data['term_exists'])) {
            $msg = $term->get_error_message();
            $msg .= PHP_EOL . sprintf('Имя указанное при создании термина: %s', $term_new['name']);
            $msg .= PHP_EOL . sprintf('Существующий термин: %s', $term->error_data['term_exists']);
            $msg .= PHP_EOL . sprintf('Аргументы создания термина: %s', print_r($args, true));
            $msg .= PHP_EOL . sprintf('URL API: %s', $url);
          } else {
            $msg = $term->get_error_message();
            $msg .= PHP_EOL . print_r($args, true);
          }
          do_action('wooms_logger_error', __CLASS__, $term->get_error_code(), $msg);
        } else {
          do_action(
            'wooms_logger',
            __CLASS__,
            sprintf('Добавлен термин %s', $term_new['name']),
            sprintf('Результат обработки %s', PHP_EOL . print_r($term, true))
          );
        }
      }

      if (isset($term->errors["term_exists"])) {
        $term_id = intval($term->error_data['term_exists']);
        if (empty($term_id)) {
          return false;
        }
      } elseif (isset($term->term_id)) {
        $term_id = $term->term_id;
      } elseif (is_array($term) && !empty($term["term_id"])) {
        $term_id = $term["term_id"];
      } else {
        return false;
      }

      update_term_meta($term_id, 'wooms_id', $term_new['wooms_id']);

      update_term_meta($term_id, 'wooms_updated_category', $data['updated']);

      if ($session_id = get_option('wooms_session_id')) {
        update_term_meta($term_id, 'wooms_session_id', $session_id);
      }

      do_action('wooms_add_category', $term, $url_parent, $path_name);

      return $term_id;
    }
  }

  /**
   * Meta box in category
   *
   * @since 2.1.2
   *
   * @param $term
   */
  public static function display_data_category($term)
  {

    $meta_data         = get_term_meta($term->term_id, 'wooms_id', true);
    $meta_data_updated = get_term_meta($term->term_id, 'wooms_updated_category', true);

?>
    <tr class="form-field term-meta-text-wrap">
      <td colspan="2" style="padding: 0;">
        <h3 style="margin: 0;">МойСклад</h3>
      </td>
    </tr>
    <?php

    if ($meta_data) : ?>
      <tr class="form-field term-meta-text-wrap">
        <th scope="row">
          <label for="term-meta-text">ID категории в МойСклад</label>
        </th>
        <td>
          <strong><?php echo $meta_data ?></strong>
        </td>
      </tr>
      <tr class="form-field term-meta-text-wrap">
        <th scope="row">
          <label for="term-meta-text">Ссылка на категорию</label>
        </th>
        <td>
          <a href="https://online.moysklad.ru/app/#good/edit?id=<?php echo $meta_data ?>" target="_blank">Посмотреть категорию в МойСклад</a>
        </td>
      </tr>
    <?php else : ?>
      <tr class="form-field term-meta-text-wrap">
        <th scope="row">
          <label for="term-meta-text">ID категории в МойСклад</label>
        </th>
        <td>
          <strong>Категория еще не синхронизирована</strong>
        </td>
      </tr>
    <?php endif;

    if ($meta_data_updated) : ?>
      <tr class="form-field term-meta-text-wrap">
        <th scope="row">
          <label for="term-meta-text">Дата последнего обновления в МойСклад</label>
        </th>
        <td>
          <strong><?php echo $meta_data_updated; ?></strong>
        </td>
      </tr>
<?php
    endif;
  }

  /**
   * Settings UI
   */
  public static function add_settings()
  {

    add_settings_section('wooms_product_cat', 'Категории продуктов', null, 'mss-settings');

    self::add_setting_categories_sync_enabled();
    self::add_setting_include_children();
  }

  /**
   * add_setting_include_children
   * 
   * issue https://github.com/wpcraft-ru/wooms/issues/282
   */
  public static function add_setting_include_children()
  {
    $option_name = 'wooms_categories_include_children';

    register_setting('mss-settings', $option_name);
    add_settings_field(
      $id = $option_name,
      $title = 'Выбор всех категорий в дереве',
      $callback = function ($args) {
        printf('<input type="checkbox" name="%s" value="1" %s />', $args['key'], checked(1, $args['value'], false));
        printf('<p>%s</p>', 'Опция позволяет указывать категории у продукта с учетом всего дерева - от верхнего предка, до всех потомков');
        printf('<p>Подробнее: <a href="%s" target="_blank">https://github.com/wpcraft-ru/wooms/issues/282</a></p>', 'https://github.com/wpcraft-ru/wooms/issues/282');
      },
      $page = 'mss-settings',
      $section = 'wooms_product_cat',
      $args = [
        'key' => $option_name,
        'value' => get_option($option_name)
      ]
    );
  }

  /**
   * is_disable
   */
  public static function is_disable()
  {
    if (get_option('woomss_categories_sync_enabled')) {
      return true;
    }

    return false;
  }

  /**
   * add_setting_categories_sync_enabled
   */
  public static function add_setting_categories_sync_enabled()
  {

    /**
     * TODO заменить woomss_categories_sync_enabled на wooms_categories_sync_disable
     */
    $option_name = 'woomss_categories_sync_enabled';

    register_setting('mss-settings', $option_name);
    add_settings_field(
      $id = $option_name,
      $title = 'Отключить синхронизацию категорий',
      $callback = function ($args) {
        printf('<input type="checkbox" name="%s" value="1" %s />', $args['key'], checked(1, $args['value'], false));
        printf('<small>%s</small>', 'Если включить опцию, то при обновлении продуктов категории не будут учтываться в соответствии с группами МойСклад.');
      },
      $page = 'mss-settings',
      $section = 'wooms_product_cat',
      $args = [
        'key' => $option_name,
        'value' => get_option($option_name)
      ]
    );
  }
}

ProductsCategories::init();
