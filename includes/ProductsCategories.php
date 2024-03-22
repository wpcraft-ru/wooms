<?php

namespace WooMS;

use Error;

defined( 'ABSPATH' ) || exit;

/**
 * Import Product Categories from MoySklad
 */
class ProductsCategories {

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'add_settings' ), 50 );
		add_action( 'product_cat_edit_form_fields', array( __CLASS__, 'display_data_category' ), 30 );

		if ( self::is_disable() ) {
			return;
		}

		add_action( 'wooms_main_walker_started', array( __CLASS__, 'reset' ) );

		add_filter( 'wooms_product_update', [ __CLASS__, 'update' ], 10, 3 );
		add_filter( 'wooms_product_update', [ __CLASS__, 'add_ancestors' ], 15, 3 );
		add_filter( 'wooms_main_walker_finish', [ __CLASS__, 'recount' ], 15, 3 );

	}



	public static function update( $product, $row, $data ) {
		if ( self::is_disable() ) {
			return $product;
		}

		$term_id = self::check_term_by_ms_uuid( $row['productFolder']['meta']['href'] );

		if ( empty( $term_id ) ) {

			do_action( 'wooms_logger_error', __NAMESPACE__, 'ProductsCategories - update - empty($term_id)', $row['id'] );
		}

		$product->set_category_ids( array( $term_id ) );

		return $product;
	}



	/**
	 * add ancestors
	 *
	 * issue https://github.com/wpcraft-ru/wooms/issues/282
	 */
	public static function add_ancestors( \WC_Product $product, $row, $data ) {
		if ( ! get_option( 'wooms_categories_include_children' ) ) {
			return $product;
		}

		if ( empty( $row['productFolder']['meta']['href'] ) ) {
			return $product;
		}

		if ( ! $term_id = self::check_term_by_ms_uuid( $row['productFolder']['meta']['href'] ) ) {
			return $product;
		}

		$term_ancestors = get_ancestors( $term_id, 'product_cat', 'taxonomy' );

		$term_ancestors[] = $term_id;
		$product->set_category_ids( $term_ancestors );


		return $product;
	}



	public static function product_categories_update( $productfolder ) {

		if ( empty( $productfolder['rows'] ) ) {
			throw new Error( 'No categories for products', 500 );
		}

		$rows = apply_filters('wooms_productfolder', $productfolder['rows']);

		$ids = [];
		foreach ( $rows as $row ) {
			$ids[] = self::product_category_update( $row, $rows );
		}

		if ( empty( $ids ) ) {
			throw new Error( 'product_category_update = empty $list[]' );
		}

		//delete categories not about current iteration
		$categories = get_categories( [
			'taxonomy' => 'product_cat',
			'hide_empty' => false,
		] );
		if($categories){
			foreach($categories as $term){
				if(in_array($term->term_id, $ids)){
					continue;
				}
				wp_delete_term($term->term_id, 'product_cat');
			}
		}

		return $ids;

	}

	public static function product_category_update( $row, $rows ) {

		if ( empty( $row['id'] ) ) {
			throw new Error( 'product_category_update = no $row[id]' );
		}

		$term_id = self::check_term_by_ms_uuid( $row['id'] );

		if ( $term_id ) {

			update_term_meta( $term_id, 'wooms_updated_category', $row['updated'] );

			$args = [];

			if ( isset( $row['productFolder']['meta']['href'] ) ) {
				$url_parent = $row['productFolder']['meta']['href'];
				foreach ( $rows as $parent_row ) {
					if ( $parent_row['meta']['href'] == $url_parent ) {
						$term_id_parent = self::product_category_update( $parent_row, $rows );
						$args['parent'] = $term_id_parent;
						break;
					}
				}
			} else {
				$args['parent'] = 0;
			}

			wp_update_term( $term_id, $taxonomy = 'product_cat', $args );

			return $term_id;
		} else {

			$term_new = array(
				'wooms_id' => $row['id'],
				'name' => $row['name'],
				'archived' => $row['archived'],
			);

			if ( isset( $row['productFolder']['meta']['href'] ) ) {
				$url_parent = $row['productFolder']['meta']['href'];

				foreach ( $rows as $parent_row ) {
					if ( $parent_row['meta']['href'] == $url_parent ) {
						$term_id_parent = self::product_category_update( $parent_row, $rows );
						$term_new['parent'] = $term_id_parent;
						break;
					}
				}
			}

			// https://github.com/wpcraft-ru/wooms/issues/524#issuecomment-1860552168
			$term    = wp_insert_term( $row['name'], 'product_cat', $term_new );
			$term_id = ! is_wp_error( $term ) ? $term['term_id'] : null;

			update_term_meta( $term_id, 'wooms_id', $row['id'] );

			update_term_meta( $term_id, 'wooms_updated_category', $row['updated'] );

			if ( $session_id = get_option( 'wooms_session_id' ) ) {
				update_term_meta( $term_id, 'wooms_session_id', $session_id );
			}

			do_action( 'wooms_add_category', $term_id, $row, $rows );

			return $term_id;
		}

	}


	public static function prepare( $data, $row ) {

		return $data;

	}


	public static function reset() {
		$productfolder = request( 'entity/productfolder' );

		self::product_categories_update( $productfolder );

		do_action('wooms_product_categories_update', $productfolder);

	}


	/**
	 * If isset term return term_id, else return false
	 */
	public static function check_term_by_ms_uuid( $id ) {

		//if uuid as url - get uuid only
		$id = str_replace( 'https://online.moysklad.ru/api/remap/1.2/entity/productfolder/', '', $id );
		$id = str_replace( 'https://api.moysklad.ru/api/remap/1.2/entity/productfolder/', '', $id );


		$terms = get_terms( array(
			'taxonomy' => array( 'product_cat' ),
			'hide_empty' => false,
			'meta_query' => array(
				array(
					'key' => 'wooms_id',
					'value' => $id,
				),
			),
		) );

		if ( empty( $terms[0] ) ) {
			return null;
		}
		return $terms[0]->term_id;
	}


	/**
	 * Meta box in category
	 *
	 * @since 2.1.2
	 *
	 * @param $term
	 */
	public static function display_data_category( $term ) {

		$meta_data = get_term_meta( $term->term_id, 'wooms_id', true );
		$meta_data_updated = get_term_meta( $term->term_id, 'wooms_updated_category', true );

		?>
		<tr class="form-field term-meta-text-wrap">
			<td colspan="2" style="padding: 0;">
				<h3 style="margin: 0;">МойСклад</h3>
			</td>
		</tr>
		<?php

		if ( $meta_data ) : ?>
			<tr class="form-field term-meta-text-wrap">
				<th scope="row">
					<label for="term-meta-text">ID категории в МойСклад</label>
				</th>
				<td>
					<strong>
						<?php echo $meta_data ?>
					</strong>
				</td>
			</tr>
			<tr class="form-field term-meta-text-wrap">
				<th scope="row">
					<label for="term-meta-text">Ссылка на категорию</label>
				</th>
				<td>
					<a href="https://online.moysklad.ru/app/#good/edit?id=<?php echo $meta_data ?>" target="_blank">Посмотреть
						категорию в МойСклад</a>
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

		if ( $meta_data_updated ) : ?>
			<tr class="form-field term-meta-text-wrap">
				<th scope="row">
					<label for="term-meta-text">Дата последнего обновления в МойСклад</label>
				</th>
				<td>
					<strong>
						<?php echo $meta_data_updated; ?>
					</strong>
				</td>
			</tr>
			<?php
		endif;
	}

	/**
	 * Settings UI
	 */
	public static function add_settings() {

		add_settings_section( 'wooms_product_cat', 'Категории продуктов', __return_empty_string(), 'mss-settings' );

		self::add_setting_categories_sync_enabled();
		self::add_setting_include_children();
	}

	/**
	 * add_setting_include_children
	 *
	 * issue https://github.com/wpcraft-ru/wooms/issues/282
	 */
	public static function add_setting_include_children() {
		$option_name = 'wooms_categories_include_children';

		register_setting( 'mss-settings', $option_name );
		add_settings_field(
			$id = $option_name,
			$title = 'Выбор всех категорий в дереве',
			$callback = function ($args) {
				printf( '<input type="checkbox" name="%s" value="1" %s />', $args['key'], checked( 1, $args['value'], false ) );
				printf( '<p>%s</p>', 'Опция позволяет указывать категории у продукта с учетом всего дерева - от верхнего предка, до всех потомков' );
				printf( '<p>Подробнее: <a href="%s" target="_blank">https://github.com/wpcraft-ru/wooms/issues/282</a></p>', 'https://github.com/wpcraft-ru/wooms/issues/282' );
			},
			$page = 'mss-settings',
			$section = 'wooms_product_cat',
			$args = [
				'key' => $option_name,
				'value' => get_option( $option_name )
			]
		);
	}

	/**
	 * is_disable
	 */
	public static function is_disable() {
		if ( get_option( 'woomss_categories_sync_enabled' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * add_setting_categories_sync_enabled
	 */
	public static function add_setting_categories_sync_enabled() {

		/**
		 * TODO заменить woomss_categories_sync_enabled на wooms_categories_sync_disable
		 */
		$option_name = 'woomss_categories_sync_enabled';

		register_setting( 'mss-settings', $option_name );
		add_settings_field(
			$id = $option_name,
			$title = 'Отключить синхронизацию категорий',
			$callback = function ($args) {
				printf( '<input type="checkbox" name="%s" value="1" %s />', $args['key'], checked( 1, $args['value'], false ) );
				printf( '<small>%s</small>', 'Если включить опцию, то при обновлении продуктов категории не будут учтываться в соответствии с группами МойСклад.' );
			},
			$page = 'mss-settings',
			$section = 'wooms_product_cat',
			$args = [
				'key' => $option_name,
				'value' => get_option( $option_name )
			]
		);
	}


	public static function recount( ) {
		wc_recount_all_terms();
	}

}

ProductsCategories::init();
