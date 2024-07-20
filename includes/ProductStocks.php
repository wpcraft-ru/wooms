<?php

namespace WooMS;

use WC_Product;
use function WooMS\request;

use function WooMS\get_config as get_config;
use function WooMS\get_config_name as get_config_name;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly

/**
 * Synchronization the stock of goods from MoySklad
 */
class ProductStocks {

	/**
	 * Используется для создания хука, расписания и как мета ключ очереди задач в мета полях продуктов
	 */
	static public $walker_hook_name = 'wooms_assortment_sync';

	/**
	 * Save state in DB
	 *
	 * @var string
	 */
	public static $state_transient_key = 'wooms_assortmen_state';

	public static function init() {

		/**
		 * snippet for fast debugging
		 */
		// add_action( 'init', function () {
		// 	if ( ! isset ( $_GET['test_ProductStocks'] ) ) {
		// 		return;
		// 	}

		// 	$product = wc_get_product( 11300 );
		// 	$url = 'https://api.moysklad.ru/api/remap/1.2/entity/assortment';
		// 	// $url = add_query_arg('filter', 'code=100001023', $url);
		// 	$url = add_query_arg('filter', 'id=f44042f0-c027-11ee-0a80-ыавыа', $url);

		// 	$data = request( $url );

		// 	$meta = get_post_meta( 11300, 'wooms_id', true );
		// 	echo '<pre>';
		// 	var_dump( $meta );
		// 	var_dump( $url );
		// 	var_dump( $data );
		// 	exit;
		// } );

		add_filter( 'wooms_stock_product_save', [ __CLASS__, 'update_manage_stock' ], 10, 2 );

		add_action( 'wooms_assortment_sync', [ __CLASS__, 'batch_handler' ] );

		add_filter( 'wooms_product_update', array( __CLASS__, 'update_product' ), 30, 2 );
		add_filter( 'wooms_variation_save', array( __CLASS__, 'update_variation' ), 30, 2 );

		add_filter( 'wooms_assortment_sync_filters', array( __CLASS__, 'assortment_add_filter_by_warehouse_id' ), 10 );
		add_filter( 'wooms_stock_log_data', array( __CLASS__, 'add_warehouse_name_to_log_data' ), 10 );

		add_action( 'wooms_variations_batch_end', [ __CLASS__, 'restart_after_batch' ] );
		add_action( 'wooms_products_batch_end', [ __CLASS__, 'restart_after_batch' ] );

		add_action( 'admin_init', [ __CLASS__, 'add_settings' ], 30 );
		add_action( 'wooms_tools_sections', array( __CLASS__, 'display_state' ), 17 );

		// add_filter( 'wooms_stock_type', array( __CLASS__, 'select_type_stock' ) );

	}


	public static function batch_handler( $state = [] ) {
		if ( empty( $state ) ) {
			$state = [
				'count' => 0
			];
		}

		$args = array(
			'post_type' => [ 'product', 'product_variation' ],
			'numberposts' => 20,
			'meta_query' => array(
				array(
					'key' => self::$walker_hook_name,
					'compare' => 'EXISTS',
				),
			),
			'no_found_rows' => true,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
			'cache_results' => false,
		);

		$products = get_posts( $args );

		if ( empty( $products ) ) {
			return false;
		}

		$filters_by_id = [];
		foreach ( $products as $product ) {
			$filters_by_id[] = 'id=' . get_post_meta( $product->ID, 'wooms_id', true );
			delete_post_meta( $product->ID, self::$walker_hook_name );
		}

		$filters = [
			implode( ';', $filters_by_id )
		];

		$url = 'https://api.moysklad.ru/api/remap/1.2/entity/assortment';

		$filters = apply_filters( 'wooms_assortment_sync_filters', $filters );

		$filters = implode( ';', $filters );

		$url = add_query_arg( 'filter', $filters, $url );

		do_action(
			'wooms_logger',
			__CLASS__,
			sprintf( 'Запрос на остатки %s', $url )
		);

		$data = request( $url );

		// var_dump($data); exit;

		if ( empty( $data['rows'] ) ) {
			return false;
		}

		$ids = self::process_rows( $data['rows'] );
		if ( $ids ) {
			$state['last_ids'] = $ids;
		}

		$state['count'] += count( $data['rows'] );

		return as_schedule_single_action( time(), self::$walker_hook_name, [ $state ], 'WooMS' );

	}

	public static function process_rows( $rows ) {

		$ids = [];
		foreach ( $rows as $row ) {

			if ( ! $product_id = Helper::get_product_id_by_uuid( $row['id'] ) ) {
				Helper::log_error( 'Не нашли продукт по uuid', __CLASS__, $row );
				continue;
			}

			if ( ! $product = wc_get_product( $product_id ) ) {
				Helper::log_error( 'Не нашли продукт по $product_id', __CLASS__, $row );
				continue;
			}

			$product = self::update_stock( $product, $row );

			$product->update_meta_data( 'wooms_assortment_data', self::get_stock_data_log( $row, $product_id ) );


			/**
			 * manage stock save
			 *
			 * issue https://github.com/wpcraft-ru/wooms/issues/287
			 */
			$product = apply_filters( 'wooms_stock_product_save', $product, $row );


			$ids[] = $product->save();
		}

		return $ids;

	}


	public static function update_stock( WC_Product $product, $data_api ): WC_Product {

		//если продукт вариативный, то его наличие определяется его вариациями и это отдельный поток хуков
		if ( $product->get_type() === 'variable' ) {
			return $product;
		}

		/**
		 * Поле по которому берем остаток?
		 * quantity = это доступные остатки за вычетом резервов
		 * stock = это все остатки без уета резерва
		 */
		if(get_config('stock_and_reserve')){
			$stock = (int) $data_api['quantity'] ?? 0;
		} else {
			$stock = (int) $data_api['stock'] ?? 0;
		}

		if ( $stock > 0 ) {
			$product->set_stock_quantity( $stock );
			$product->set_stock_status( 'instock' );
		} else {
			$product->set_stock_quantity( 0 );
			$product->set_stock_status( 'outofstock' );
		}

		$log_data = [
			'stock' => $data_api['stock'],
			'quantity' => $data_api['quantity'],
			'type' => $product->get_type(),
		];

		if ( $product->get_type() === 'variation' ) {
			$log_data['product_parent'] = $product->get_parent_id();
		}

		Helper::log( sprintf(
			'Остатки для продукта "%s" (ИД %s) = %s', $product->get_name(), $product->get_id(), $product->get_stock_quantity() ),
			__CLASS__,
			$log_data
		);

		return $product;
	}


	/**
	 * Если у сайта включена опция управление остатками - установить остатки для товара
	 *
	 * @todo вероятно опция типа wooms_warehouse_count - более не нужна
	 */
	public static function update_manage_stock( WC_Product $product, $data_api ): WC_Product {

		if ( ! get_option( 'woocommerce_manage_stock' ) ) {
			return $product;
		}

		if ( ! $product->get_manage_stock() ) {
			if($product->get_type() === 'variable'){
				$product->set_manage_stock( false );
				Helper::log( sprintf(
					'Выключили управление запасами для продукта: %s (ИД %s)', $product->get_name(), $product->get_id() ),
					__CLASS__
				);
			} else {
				$product->set_manage_stock( true );
				Helper::log( sprintf(
					'Включили управление запасами для продукта: %s (ИД %s)', $product->get_name(), $product->get_id() ),
					__CLASS__
				);

			}
		}

		//для вариативных товаров доступность определяется наличием вариаций
		if ( $product->get_type() === 'variation' ) {

			$parent_id = $product->get_parent_id();
			$parent_product = wc_get_product( $parent_id );
			if ( empty( $parent_product ) ) {
				Helper::log_error( "Не нашли родительский продукт: {$parent_id}, вариация: {$product->get_id()}",
					__CLASS__
				);
				return $product;
			}
			if ( $parent_product->get_manage_stock() ) {

				Helper::log( sprintf(
					'У основного продукта отключили управление остатками: %s (ИД %s)', $parent_product->get_name(), $parent_id ),
					__CLASS__
				);
				$parent_product->set_manage_stock( false );

				$parent_product->save();
			}

		}

		/**
		 * это похоже надо выпилить
		 *
		 * потому что это не относится к синку МС и должно управляться как то иначе
		 *
		 * если это тут оставлять, то эта отметка должна быть на стороне МС
		 */
		// if ( get_option( 'wooms_stock_empty_backorder' ) ) {
		// $product->set_backorders( 'notify' );
		// } else {
		// $product->set_backorders( 'no' );
		// }


		return $product;

	}

	/**
	 * get_stock_data_log
	 * for save log data to product meta
	 */
	public static function get_stock_data_log( $row = [], $product_id = 0 ) {
		$data = [
			"stock" => $row['stock'],
			"reserve" => $row['reserve'],
			"inTransit" => $row['inTransit'],
			"quantity" => $row['quantity'],
		];

		$data = apply_filters( 'wooms_stock_log_data', $data, $product_id, $row );

		$data = json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

		return $data;
	}


	public static function restart_after_batch() {
		if ( ! self::is_enable() ) {
			return;
		}

		if ( as_has_scheduled_action( self::$walker_hook_name ) ) {
			return;
		}

		as_schedule_single_action( time(), self::$walker_hook_name, [], 'WooMS' );
	}


	/**
	 * get state data
	 */
	public static function get_state( $key = '' ) {
		if ( ! $state = get_transient( self::$state_transient_key ) ) {
			$state = [];
			set_transient( self::$state_transient_key, $state );
		}

		if ( empty( $key ) ) {
			return $state;
		}

		if ( empty( $state[ $key ] ) ) {
			return null;
		}

		return $state[ $key ];
	}


	public static function set_state( $key, $value ) {

		if ( ! $state = get_transient( self::$state_transient_key ) ) {
			$state = [];
		}

		if ( is_array( $state ) ) {
			$state[ $key ] = $value;
		} else {
			$state = [];
			$state[ $key ] = $value;
		}

		set_transient( self::$state_transient_key, $state );
	}



	/**
	 * Get product variant ID
	 *
	 * @param $uuid
	 */
	public static function get_product_id_by_uuid( $uuid ) {
		if ( strpos( $uuid, 'http' ) !== false ) {
			$uuid = str_replace( 'https://online.moysklad.ru/api/remap/1.1/entity/product/', '', $uuid );
			$uuid = str_replace( 'https://online.moysklad.ru/api/remap/1.2/entity/product/', '', $uuid );
			$uuid = str_replace( 'https://api.moysklad.ru/api/remap/1.1/entity/product/', '', $uuid );
			$uuid = str_replace( 'https://api.moysklad.ru/api/remap/1.2/entity/product/', '', $uuid );
		}

		$args = array(
			'post_type' => [ 'product', 'product_variation' ],
			'numberposts' => 1,
			'meta_query' => array(
				array(
					'key' => 'wooms_id',
					'value' => $uuid,
				),
			),
			'no_found_rows' => true,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
			'cache_results' => false,
		);

		$posts = get_posts( $args );
		if ( empty( $posts[0]->ID ) ) {
			return false;
		}

		return $posts[0]->ID;
	}

	/**
	 * add_warehouse_name_to_log_data
	 */
	public static function add_warehouse_name_to_log_data( $data_log = [] ) {
		if ( ! $warehouse_id = get_option( 'woomss_warehouse_id' ) ) {
			return $data_log;
		}

		if ( ! $wh_name = get_transient( 'wooms_warehouse_name' ) ) {
			$url = sprintf( 'entity/store/%s', $warehouse_id );
			$data = request( $url );
			if ( isset( $data["name"] ) ) {
				$wh_name = $data["name"];
				set_transient( 'wooms_warehouse_name', $wh_name, HOUR_IN_SECONDS );
			}
		}

		$data_log['name_wh'] = $wh_name;

		return $data_log;
	}

	/**
	 * add_filter_by_warehouse_id
	 */
	public static function assortment_add_filter_by_warehouse_id( $filter ) {
		if ( ! $warehouse_id = get_option( 'woomss_warehouse_id' ) ) {
			return $filter;
		}

		$filter[] = 'stockStore=' . \WooMS\get_api_url( sprintf( 'entity/store/%s', $warehouse_id ) );

		return $filter;
	}


	/**
	 * Update stock for variation
	 */
	public static function update_variation( \WC_Product_Variation $variation, $data_api ) {
		if ( self::is_enable() ) {
			$variation->update_meta_data( self::$walker_hook_name, 1 );
		} else {
			$variation->set_catalog_visibility( 'visible' );
			$variation->set_stock_status( 'instock' );
			$variation->set_manage_stock( false );
			$variation->set_status( 'publish' );
		}

		return $variation;
	}

	/**
	 * Update product
	 */
	public static function update_product( $product, $data_api ) {
		if ( self::is_enable() ) {
			$product->update_meta_data( self::$walker_hook_name, 1 );

		} else {
			$product->set_catalog_visibility( 'visible' );
			$product->set_stock_status( 'instock' );
			$product->set_manage_stock( false );
			$product->set_status( 'publish' );
		}

		return $product;
	}

	/**
	 * Settings UI
	 */
	public static function add_settings() {

		add_settings_section(
			'woomss_section_warehouses',
			'Склад и остатки',
			$callback = array( __CLASS__, 'display_woomss_section_warehouses' ),
			'mss-settings'
		);

		register_setting( 'mss-settings', 'woomss_stock_sync_enabled' );
		add_settings_field(
			$id = 'woomss_stock_sync_enabled',
			$title = 'Включить работу с остатками',
			$callback = array( __CLASS__, 'woomss_stock_sync_enabled_display' ),
			$page = 'mss-settings',
			$section = 'woomss_section_warehouses'
		);

		add_settings_field(
			$id = 'stock_and_reserve',
			$title = 'Учитывать остатки с резервом',
			$callback = function ($args) {
				printf( '<input type="checkbox" name="%s" value="1" %s />', $args['name'], $args['value'] );

			},
			$page = 'mss-settings',
			$section,
			$args = [
				'name' => get_config_name( 'stock_and_reserve' ),
				'value' => checked( 1, get_config( 'stock_and_reserve' ), false ),
			]
		);

		// register_setting( 'mss-settings', 'wooms_warehouse_count' );
		// add_settings_field(
		// 	$id = 'wooms_warehouse_count',
		// 	$title = 'Управление запасами на уровне товаров',
		// 	$callback = array( __CLASS__, 'display_wooms_warehouse_count' ),
		// 	$page = 'mss-settings',
		// 	$section = 'woomss_section_warehouses'
		// );

		// register_setting( 'mss-settings', 'wooms_stock_empty_backorder' );
		// add_settings_field(
		// 	$id = 'wooms_stock_empty_backorder',
		// 	$title = 'Разрешать предзаказ при 0 остатке',
		// 	$callback = array( __CLASS__, 'display_wooms_stock_empty_backorder' ),
		// 	$page = 'mss-settings',
		// 	$section = 'woomss_section_warehouses'
		// );

		self::add_setting_warehouse_id();
	}


	/**
	 * Display field: select warehouse
	 */
	public static function add_setting_warehouse_id() {
		$option = 'woomss_warehouse_id';
		register_setting( 'mss-settings', $option );
		add_settings_field(
			$id = $option,
			$title = 'Учитывать остатки по складу',
			$callback = function ($args) {

				$url = 'entity/store';
				$data = request( $url );
				if ( empty ( $data['rows'] ) ) {
					echo 'Система не смогла получить список складов из МойСклад';
					return;
				}
				$selected_wh = $args['value']; ?>

			<select class="wooms_select_warehouse" name="woomss_warehouse_id">
				<option value="">По всем складам</option>
				<?php
					foreach ( $data['rows'] as $row ) :
						printf( '<option value="%s" %s>%s</option>', $row['id'], selected( $row['id'], $selected_wh, false ), $row['name'] );
					endforeach;
					?>
			</select>
			<?php
			},
			$page = 'mss-settings',
			$section = 'woomss_section_warehouses',
			$args = [
				'key' => $option,
				'value' => get_option( $option ),
			]
		);
	}

	/**
	 *
	 */
	public static function display_woomss_section_warehouses() {
		?>
		<p>Данные опции позволяют настроить обмен данным по остаткам между складом и сайтом.</p>
		<ol>
			<li>Функционал обязательно нужно проверять на тестовом сайте. Он еще проходит обкатку. В случае проблем
				сообщайте в техподдержку
			</li>
			<li>После изменения этих опций, следует обязательно <a href="<?php echo admin_url( 'admin.php?page=moysklad' ) ?>"
					target="_blank">запускать обмен данными
					вручную</a>, чтобы статусы наличия продуктов обновились
			</li>
			<li>Перед включением опций, нужно настроить магазина на работу с <a
					href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=products&section=inventory' ) ?>"
					target="_blank">Запасами</a></li>
		</ol>
		<?php
	}


	/**
	 * Display field
	 */
	public static function woomss_stock_sync_enabled_display() {
		$option = 'woomss_stock_sync_enabled';
		printf( '<input type="checkbox" name="%s" value="1" %s />', $option, checked( 1, get_option( $option ), false ) );
		echo '<p>При включении опции товары будут помечаться как в наличии или отсутствующие в зависимиости от числа остатков на складе</p>';
	}

	/**
	 * Display field
	 */
	public static function display_wooms_stock_empty_backorder() {
		$option = 'wooms_stock_empty_backorder';
		printf( '<input type="checkbox" name="%s" value="1" %s />', $option, checked( 1, get_option( $option ), false ) );
		echo '<p><small>Если включить опцию то система будет разрешать предзаказ при 0 остатках</small></p>';
	}


	/**
	 * Display field
	 */
	public static function display_wooms_warehouse_count() {
		$option = 'wooms_warehouse_count';
		printf( '<input type="checkbox" name="%s" value="1" %s />', $option, checked( 1, get_option( $option ), false ) );
		printf( '<p><strong>Перед включением опции, убедитесь что верно настроено управление запасами в WooCommerce (на <a href="%s" target="_blank">странице настроек</a>).</strong></p>', admin_url( 'admin.php?page=wc-settings&tab=products&section=inventory' ) );
		echo "<p><small>Если включена, то будет показан остаток в количестве единиц продукта на складе. Если снять галочку - только наличие.</small></p>";
	}

	/**
	 * is_enable
	 */
	public static function is_enable() {
		if ( get_option( 'woomss_stock_sync_enabled' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * display_state
	 */
	public static function display_state() {

		if ( ! self::is_enable() ) {
			return;
		}

		$strings = [];

		if ( as_next_scheduled_action( self::$walker_hook_name ) ) {
			$strings[] = sprintf( '<strong>Статус:</strong> %s', 'Выполняется очередями в фоне' );
		} else {
			$strings[] = sprintf( '<strong>Статус:</strong> %s', 'в ожидании задач' );
		}


		$strings[] = sprintf( 'Последняя успешная синхронизация: %s', Helper::get_timestamp_last_job_by_hook( self::$walker_hook_name ) ) ?? 'Нет данных';

		$strings[] = sprintf( 'Очередь задач: <a href="%s">открыть</a>', admin_url( 'admin.php?page=wc-status&tab=action-scheduler&s=wooms_assortment_sync&orderby=schedule&order=desc' ) );

		$strings[] = sprintf( 'Журнал обработки: <a href="%s">открыть</a>', admin_url( 'admin.php?page=wc-status&tab=logs&source=WooMS-ProductStocks' ) );

		?>
		<h2>Остатки</h2>
		<div class="wrap">

			<?php
			foreach ( $strings as $string ) {
				printf( '<p>%s</p>', $string );
			}
			?>

		</div>

		<?php

	}
}

ProductStocks::init();
