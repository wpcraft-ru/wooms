<?php

namespace WooMS\Products;

/**
 * Product Import Walker
 * do_action('wooms_product_import_row', $value, $key, $data);
 */
class Walker {

  /**
   * The Init
   */
	public static function init()
  {

    //Main Walker
    add_action( 'wooms_cron_walker', array( __CLASS__, 'walker_cron_starter' ) );
    add_action( 'init', array( __CLASS__, 'cron_init' ) );
    add_filter( 'cron_schedules', array( __CLASS__, 'add_schedule' ) );

    //Product data
    add_action( 'wooms_product_import_row', array( __CLASS__, 'load_product' ), 10, 3 );

		//UI and actions manually
		add_action( 'woomss_tool_actions_btns', array( __CLASS__, 'display_wrapper' ) );
		add_action( 'woomss_tool_actions_wooms_products_start_import', array( __CLASS__, 'start_manually' ) );
		add_action( 'woomss_tool_actions_wooms_products_stop_import', array( __CLASS__, 'stop_manually' ) );

		//Notices
		add_action( 'wooms_products_display_state', array( __CLASS__, 'display_state' ) );

    //Other
    add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes_post_type' ) );

	}

  /**
   * Load data and set product type simple
   *
   * @param $value
   * @param $key
   * @param $data
   */
  public static function load_product( $value, $key, $data )
  {
    if ( ! empty( $value['archived'] ) ) {
      return;
    }

    /**
     * Если отключена опция связи по UUID и пустой артикул, то пропуск записи
     */
    if ( empty(get_option('wooms_use_uuid')) and empty($value['article']) ) {
      return;
    }

    if ( ! empty( $value['article'] ) ) {
      $product_id = wc_get_product_id_by_sku( $value['article'] );
    } else {
      $product_id = null;
    }

    if ( intval( $product_id ) ) {
      self::update_product( $product_id, $value );
    } elseif ( $product_id = self::get_product_id_by_uuid( $value['id'] ) ) {
      self::update_product( $product_id, $value );
    } else {
      $product_id = self::add_product( $value );
      if ( $product_id ) {
        self::update_product( $product_id, $value );
      } else {
        return;
      }
    }

    /**
     * От этого хука надо будет отказаться в пользу wooms_product_save
     */
    do_action( 'wooms_product_update', $product_id, $value, $data );

    $product = wc_get_product($product_id);

    /**
     * Хук позволяет работать с методами WC_Product
     * Сохраняет в БД все изменения за 1 раз
     * Снижает нагрузку на БД
     */
    $product = apply_filters('wooms_product_save', $product, $value, $data);
    $product_id = $product->save();
    do_action('wooms_logger', 'product_save', $product_id, 'main walker');

  }

  /**
   * Update product from source data
   *
   * @param $product_id var id product
   * @param $data_of_source var data of source from MoySklad
   *
   */
  public static function update_product( $product_id, $data_of_source )
  {
    wp_set_object_terms( $product_id, 'simple', 'product_type', false );

    $product = wc_get_product( $product_id );

    //save data of source
    $now = date( "Y-m-d H:i:s" );
    update_post_meta( $product_id, 'wooms_data_of_source', print_r( $data_of_source, true ) );

    //Set session id for product
    if ( $session_id = get_option( 'wooms_session_id' ) ) {
      update_post_meta( $product_id, 'wooms_session_id', $session_id );
    }

    //the time stamp for database cleanup by cron
    update_post_meta( $product_id, 'wooms_updated_timestamp', $now );

    update_post_meta( $product_id, 'wooms_id', $data_of_source['id'] );

    update_post_meta( $product_id, 'wooms_updated', $data_of_source['updated']);

    //update title
    if ( isset( $data_of_source['name'] ) and $data_of_source['name'] != $product->get_title() ) {
      if ( ! empty( get_option( 'wooms_replace_title' ) ) ) {
        $product->set_name( $data_of_source['name'] );
      }
    }

    $product_description = isset($data_of_source['description']) ? $data_of_source['description'] : '';
    //update description
    if ( apply_filters( 'wooms_added_description', true, $product_description) ) {

      if ( $product_description && ! empty( get_option( 'wooms_replace_description' ) ) ) {

        $product->set_description( $product_description );

      } else {

        if ( empty( $product->get_description() ) ) {

          $product->set_description( $product_description);
        }
      }
    }
    //Price Retail 'salePrices'
    if ( isset( $data_of_source['salePrices'][0]['value'] ) ) {
      $price_source = floatval( $data_of_source['salePrices'][0]['value'] );
      $price        = apply_filters( 'wooms_product_price', $price_source, $data_of_source );

      $price = $price / 100;

      $product->set_price( $price );
      $product->set_regular_price( $price );

      if ( 0 == $product->get_price()){
        $product->set_catalog_visibility('hidden' );
      } else {
        $product->set_catalog_visibility('visible');
      }
    }

    $product->set_stock_status( 'instock' );
    $product->set_manage_stock( 'no' );


    $product->set_status( 'publish' );
    $product->save();

  }

  /**
   * Add metaboxes
   */
  public static function add_meta_boxes_post_type()
  {
    add_meta_box( 'metabox_product', 'МойСклад', array( __CLASS__, 'display_metabox_for_product' ), 'product', 'side', 'low' );
  }

  /**
   * Meta box in product
   */
  public static function display_metabox_for_product() {
    $post = get_post();
    $box_data = '';
    $data_id   = get_post_meta( $post->ID, 'wooms_id', true );
    $data_meta = get_post_meta( $post->ID, 'wooms_meta', true );
    $data_updated = get_post_meta( $post->ID, 'wooms_updated', true );
    if ( $data_id ) {
      $box_data = sprintf( '<div>ID товара в МойСклад: <div><strong>%s</strong></div></div>', $data_id );
    } else {
      $box_data = '<p>Товар еще не синхронизирован с МойСклад.</p> <p>Ссылка на товар отсутствует</p>';
    }

    if ( $data_meta ) {
      $box_data .= sprintf( '<p><a href="%s" target="_blank">Посмотреть товар в МойСклад</a></p>', $data_meta['uuidHref'] );
    }

    if ( $data_updated ) {
      $box_data .= sprintf( '<div>Дата последнего обновления товара в МойСклад: <strong>%s</strong></div>', $data_updated );
    }

    echo $box_data;
  }

  /**
   * Product Check
   *
   * @param $uuid
   *
   * @return bool
   */
  public static function get_product_id_by_uuid( $uuid ) {

    $posts = get_posts( 'post_type=product&meta_key=wooms_id&meta_value=' . $uuid );

    if ( empty( $posts[0]->ID ) ) {
      return false;
    } else {
      return $posts[0]->ID;
    }
  }


  /**
   * Add product from source data
   *
   * @param $data_source
   *
   * @return bool|int|WP_Error
   */
  public static function add_product( $data_source ) {

    // $product = new WC_Product_Simple();
    $post_data = array(
      'post_type'   => 'product',
      'post_title'  => wp_filter_post_kses( $data_source['name'] ),
      'post_status' => 'draft',
    );

    if ( ! apply_filters( 'wooms_add_product', true, $data_source ) ) {
      return false;
    }

    // Вставляем запись в базу данных
    $post_id = wp_insert_post( $post_data );

    // $product = wc_get_product($post_id);

    if ( empty( $post_id ) ) {
      return false;
    }

    update_post_meta( $post_id, $meta_key = 'wooms_id', $meta_value = $data_source['id'] );

    update_post_meta( $post_id, 'wooms_meta', $data_source['meta']);

    update_post_meta( $post_id, 'wooms_updated', $data_source['updated']);

    if ( isset( $data_source['article'] ) ) {
      update_post_meta( $post_id, $meta_key = '_sku', $meta_value = $data_source['article'] );
    }

    return $post_id;
  }

	/**
	 * Cron shedule setup for 1 minute interval
	 */
	public static function add_schedule( $schedules ) {
		$schedules['wooms_cron_walker_shedule'] = array(
			'interval' => apply_filters('wooms_cron_interval', 60),
			'display'  => 'WooMS Cron Walker 60 sec',
		);

		return $schedules;
	}

	/**
	 * Cron task restart
	 */
	public static function cron_init() {
		if ( ! wp_next_scheduled( 'wooms_cron_walker' ) ) {
			wp_schedule_event( time(), 'wooms_cron_walker_shedule', 'wooms_cron_walker' );
		}
	}

	/**
	 * Starter walker by cron if option enabled
	 */
	public static function walker_cron_starter() {

		if ( self::can_cron_start() ) {
			self::walker();
		}
	}

	/**
	 * Can cron start? true or false
	 */
	public static function can_cron_start() {

		//Если стоит отметка о ручном запуске - крон может стартовать
		if ( ! empty( get_transient( 'wooms_manual_sync' ) ) ) {
			return true;
		}

		//Если работа по расписанию отключена - не запускаем
		if ( empty( get_option( 'woomss_walker_cron_enabled' ) ) ) {
			return false;
		}
		if ( $end_stamp = get_transient( 'wooms_end_timestamp' ) ) {

			$interval_hours = get_option( 'woomss_walker_cron_timer' );
			$interval_hours = (int) $interval_hours;
			if ( empty( $interval_hours ) ) {
				return false;
			}
			$now       = new \DateTime();
			$end_stamp = new \DateTime( $end_stamp );
			$end_stamp = $now->diff( $end_stamp );
			$diff_hours = $end_stamp->format( '%h' );
			if ( $diff_hours > $interval_hours ) {
				return true;
			} else {
				return false;
			}
		} else {
			return true;
		}
	}

	/**
	 * Walker for data from MoySklad
	 */
	public static function walker() {
		//Check stop tag and break the walker
		if ( self::check_stop_manual() ) {
			return;
		}

		$count = apply_filters( 'wooms_iteration_size', 20 );
		if ( ! $offset = get_transient( 'wooms_offset' ) ) {
			$offset = 0;
			set_transient( 'wooms_offset', $offset );
			update_option( 'wooms_session_id', date( "YmdHis" ), 'no' ); //set id session sync
			delete_transient( 'wooms_count_stat' );
		}

		$ms_api_args = array(
			'offset' => $offset,
			'limit'  => $count,
      'scope'  => 'product',
		);

    $url = add_query_arg( $ms_api_args, 'https://online.moysklad.ru/api/remap/1.1/entity/assortment' );

    $url_api = apply_filters('wooms_url_get_products', $url);


		try {

			delete_transient( 'wooms_end_timestamp' );
			set_transient( 'wooms_start_timestamp', time() );
			$data = wooms_request( $url_api );
      do_action('wooms_logger',
        'walker_request_url',
        sprintf('Отправлен запрос %s', $url_api),
        print_r($data, true)
      );

      // do_action( 'cl', array( 'tag2', $data ) );

			//Check for errors and send message to UI
			if ( isset( $data['errors'] ) ) {
				$error_code = $data['errors'][0]["code"];
				if ( $error_code == 1056 ) {
					$msg = sprintf( 'Ошибка проверки имени и пароля. Код %s, исправьте в <a href="%s">настройках</a>', $error_code, admin_url( 'admin.php?page=mss-settings' ) );
					throw new Exception( $msg );
				} else {
					throw new Exception( $error_code . ': ' . $data['errors'][0]["error"] );
				}
			}

			do_action( 'wooms_walker_start' );
			//If no rows, that send 'end' and stop walker
			if ( empty( $data['rows'] ) ) {
				self::walker_finish();

				do_action( 'wooms_walker_finish' );

				return true;
			}

			$i = 0;

			foreach ( $data['rows'] as $key => $value ) {
				do_action( 'wooms_product_import_row', $value, $key, $data );
				$i ++;
			}

			if ( $count_saved = get_transient( 'wooms_count_stat' ) ) {
				set_transient( 'wooms_count_stat', $i + $count_saved );
			} else {
				set_transient( 'wooms_count_stat', $i );
			}

			set_transient( 'wooms_offset', $offset + $i );

			return;
		} catch ( Exception $e ) {
			delete_transient( 'wooms_start_timestamp' );
			set_transient( 'wooms_error_background', $e->getMessage() );
		}
	}

	/**
	 * Check and stop walker manual
	 */
	public static function check_stop_manual() {
		if ( get_transient( 'wooms_walker_stop' ) ) {
			delete_transient( 'wooms_start_timestamp' );
			delete_transient( 'wooms_offset' );
			delete_transient( 'wooms_walker_stop' );

			return true;
		}

		return false;
	}

	/**
	 * Finish walker
	 */
	public static function walker_finish() {
		delete_transient( 'wooms_start_timestamp' );
		delete_transient( 'wooms_offset' );
		delete_transient( 'wooms_manual_sync' );
		//Отключаем обработчик или ставим на паузу
		if ( empty( get_option( 'woomss_walker_cron_enabled' ) ) ) {
			$timer = 0;
		} else {
			$timer = 60 * 60 * intval( get_option( 'woomss_walker_cron_timer', 24 ) );
		}

		set_transient( 'wooms_end_timestamp', date( "Y-m-d H:i:s" ), $timer );

		return true;
	}

	/**
	 * Start manually actions
	 */
	public static function start_manually() {
		delete_transient( 'wooms_start_timestamp' );
		delete_transient( 'wooms_error_background' );
		delete_transient( 'wooms_offset' );
		delete_transient( 'wooms_end_timestamp' );
		delete_transient( 'wooms_walker_stop' );
		set_transient( 'wooms_manual_sync', 1 );
		self::walker();
		wp_redirect( admin_url( 'admin.php?page=moysklad' ) );
	}

	/**
	 * Stop manually actions
	 */
	public static function stop_manually() {
		set_transient( 'wooms_walker_stop', 1, 60 * 60 );
		delete_transient( 'wooms_start_timestamp' );
		delete_transient( 'wooms_offset' );
		delete_transient( 'wooms_end_timestamp' );
		delete_transient( 'wooms_manual_sync' );
		wp_redirect( admin_url( 'admin.php?page=moysklad' ) );
	}

  /**
   * Description
   */
  public static function display_state(){
    $state = '<strong>Выполняется пакетная обработка данных в фоне очередями раз в минуту.</strong>';
    $time_string = get_transient( 'wooms_start_timestamp' );
		$diff_sec    = time() - $time_string;
		$time_string = date( 'Y-m-d H:i:s', $time_string );

    $errors = get_transient( 'wooms_error_background' );
    if(empty($errors)){
      $errors = 'не обнаружено';
    }

    $session = get_option( 'wooms_session_id' );
    if(empty($session)){
      $session = 'отсутствует';
    }

    $timestamp_end_last_session = get_transient( 'wooms_end_timestamp' );
    if(empty($timestamp_end_last_session)){
      $timestamp_end_last_session = 'отметка времени будет проставлена после завершения текущей сессии синхронизации';
    } else {
      $state = 'Синхронизация завершена успешно и находится в ожидании старта';
    }

    ?>
    <div class="wrap">
      <div id="message" class="notice notice-warning">
        <p>Статус: <?= $state ?></p>
        <p>Сессия (номер/дата): <?= $session ?></p>
        <p>Последняя успешная синхронихация (отметка времени): <?= $timestamp_end_last_session ?></p>
        <p>Ошибки: <?= $errors ?></p>
        <p>Отметка времени о последней итерации: <?php echo $time_string ?></p>
        <p>Количество обработанных записей: <?php echo get_transient( 'wooms_count_stat' ); ?></p>
        <p>Секунд прошло: <?= $diff_sec ?>.<br/> Следующая серия данных должна отправиться примерно через
          минуту. Можно обновить страницу для проверки результатов работы.</p>
      </div>
    </div>
    <?php
  }

	public static function notice_walker() {
		do_action( 'wooms_before_notice_walker' );
		$screen = get_current_screen();

		if ( $screen->base != 'toplevel_page_moysklad' ) {
			return;
		}

		if ( empty( get_transient( 'wooms_start_timestamp' ) ) ) {
			return;
		}

		$time_string = get_transient( 'wooms_start_timestamp' );
		$diff_sec    = time() - $time_string;
		$time_string = date( 'Y-m-d H:i:s', $time_string );
		do_action( 'wooms_notice_walker' );
		?>
		<div class="wrap">
			<div id="message" class="notice notice-warning">
				<p><strong>Выполняется пакетная обработка данных в фоне очередями раз в минуту.</strong></p>
				<p>Отметка времени о последней итерации: <?php echo $time_string ?></p>
				<p>Количество обработанных записей: <?php echo get_transient( 'wooms_count_stat' ); ?></p>
				<p>Секунд прошло: <?php echo $diff_sec ?>.<br/> Следующая серия данных должна отправиться примерно через
					минуту. Можно обновить страницу для проверки результатов работы.</p>
			</div>
		</div>
		<?php
	}

	/**
	 * User interface for manually actions
	 */
	public static function display_wrapper() {
		echo '<h2>Товары</h2>';

    do_action('wooms_products_display_state');

		if ( empty( get_transient( 'wooms_start_timestamp' ) ) ) {
			echo "<p>Нажмите на кнопку ниже, чтобы запустить синхронизацию данных о продуктах вручную</p>";
			printf( '<a href="%s" class="button button-primary">Выполнить</a>', add_query_arg( 'a', 'wooms_products_start_import', admin_url( 'admin.php?page=moysklad' ) ) );
		} else {
			printf( '<a href="%s" class="button button-secondary">Остановить</a>', add_query_arg( 'a', 'wooms_products_stop_import', admin_url( 'admin.php?page=moysklad' ) ) );
		}
	}
}

Walker::init();
