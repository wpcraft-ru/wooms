<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Import Product Categories from MoySklad
 */
class WooMS_Import_Product_Categories {
	
	/**
	 * WooMS_Import_Product_Categories constructor.
	 */
	public function __construct() {
		/**
		 * Use hook: do_action('wooms_product_update', $product_id, $value, $data);
		 */
		add_action( 'wooms_product_update', array( $this, 'load_data' ), 100, 3 );
		add_action( 'admin_init', array( $this, 'settings_init' ), 103 );
		//add_action( 'wooms_walker_finish', array( $this, 'update_parent_category' ), 10);
		add_action( 'product_cat_edit_form_fields', array( $this, 'add_data_category' ), 30 );
	}
	
	/**
	 * Receiving data and distributing products by category
	 *
	 * @param $product_id
	 * @param $value
	 * @param $data
	 */
	public function load_data( $product_id, $value, $data ) {
		//Если опция отключена - пропускаем обработку
		if ( get_option( 'woomss_categories_sync_enabled' ) ) {
			return;
		}
		
		if ( empty( $value['productFolder']['meta']['href'] ) ) {
			return;
		}
		
		$url = $value['productFolder']['meta']['href'];
		
		if ( $term_id = $this->update_category( $url ) ) {
			
			wp_set_object_terms( $product_id, $term_id, $taxonomy = 'product_cat' );
		}
		
	}
	
	/**
	 * Create and update categories
	 *
	 * @param $url
	 *
	 * @return bool|int|mixed
	 */
	public function update_category( $url ) {
		$data = wooms_request( $url );
		//do_action("logger_u7", [ $data, $url] );
		if ( $term_id = $this->check_term_by_ms_id( $data['id'] ) ) {
			
			do_action( 'wooms_update_category', $term_id );
			
			return $term_id;

		} else {
		
		$args = array();
		
		$term_new = array(
			'wooms_id' => $data['id'],
			'name'     => $data['name'],
			'archived' => $data['archived'],
		);
		
		if ( isset( $data['productFolder']['meta']['href'] ) ) {
			$url_parent = $data['productFolder']['meta']['href'];
			if ( $term_id_parent = $this->update_category( $url_parent ) ) {
				$args['parent'] = intval( $term_id_parent );
			}
		}
		
		$url_parent = isset( $data['productFolder']['meta']['href'] ) ? $data['productFolder']['meta']['href'] : '';
		$path_name  = isset( $data['pathName'] ) ? $data['pathName'] : null;

			if ( apply_filters( 'wooms_skip_categories', true, $url_parent, $path_name ) ) {
				$term = wp_insert_term( $term_new['name'], $taxonomy = 'product_cat', $args );
			}


		
		//wp_suspend_cache_addition( $was_suspended );
		
		if ( isset( $term->errors["term_exists"] ) ) {
			$term_id = intval( $term->error_data['term_exists'] );
			if ( empty( $term_id ) ) {
				return false;
			}
		} elseif ( isset( $term->term_id ) ) {
			$term_id = $term->term_id;
		} elseif ( is_array( $term ) && ! empty( $term["term_id"] ) ) {
			$term_id = $term["term_id"];
		} else {
			return false;
		}
		
		update_term_meta( $term_id, 'wooms_id', $term_new['wooms_id'] );
		
		if ( $session_id = get_option( 'wooms_session_id' ) ) {
			update_term_meta( $term_id, 'wooms_session_id', $session_id );
		}
		
		do_action( 'wooms_add_category', $term, $url_parent, $path_name );
		
		return $term_id;
		}
		
	}
	
	/**
	 * If isset term return term_id, else return false
	 */
	public function check_term_by_ms_id( $id ) {
		
		$terms = get_terms( array(
			'taxonomy'   => array( 'product_cat' ),
			'meta_query' => array(
				array(
					'key'   => 'wooms_id',
					'value' => $id,
				),
			),
		) );
		
		if ( empty( $terms ) ) {
			return false;
		} else {
			return $terms[0]->term_id;
		}
	}
	
	/**
	 * Creating a parent category, if it is not
	 */
	public function update_parent_category() {
		
		$terms_sub = get_terms( array(
			'taxonomy'   => array( 'product_cat' ),
			'meta_query' => array(
				array(
					'key'     => 'wooms_slug_parent',
					'compare' => 'EXISTS',
				),
			),
		) );
		
		if ( false == $terms_sub ) {
			return;
		}
		
		$term_parent_args = array();
		
		foreach ( $terms_sub as $term_sub ) {
			$term_parent_args['name']     = get_term_meta( $term_sub->term_id, 'wooms_name_parent', true );
			$term_parent_args['slug']     = get_term_meta( $term_sub->term_id, 'wooms_slug_parent', true );
			$term_parent_args['wooms_id'] = get_term_meta( $term_sub->term_id, 'wooms_wooms_id_parent', true );
		}
		
		$was_suspended = wp_suspend_cache_addition();
		wp_suspend_cache_addition( true );
		
		$term_add = wp_insert_term( $term_parent_args['name'], $taxonomy = 'product_cat', array(
			'slug'   => $term_parent_args['slug'],
			'parent' => 0,
		) );
		
		wp_suspend_cache_addition( $was_suspended );
		
		if ( isset( $term_add->errors["term_exists"] ) ) {
			$term_id = intval( $term_add->error_data['term_exists'] );
			if ( empty( $term_id ) ) {
				return;
			}
		} elseif ( isset( $term_add->term_id ) ) {
			$term_id = $term_add->term_id;
		} elseif ( isset( $term_add["term_id"] ) ) {
			$term_id = $term_add["term_id"];
		} else {
			return;
		}
		
		update_term_meta( $term_id, 'wooms_id', $term_parent_args['wooms_id'] );
		
		if ( $session_id = get_option( 'wooms_session_id' ) ) {
			update_term_meta( $term_id, 'wooms_session_id', $session_id );
		}
		
		foreach ( $terms_sub as $term_sub ) {
			$term_upd = wp_update_term( $term_sub->term_id, $taxonomy = 'product_cat', array(
				'parent' => $term_id,
			) );
			delete_term_meta( $term_sub->term_id, 'wooms_name_parent' );
			delete_term_meta( $term_sub->term_id, 'wooms_slug_parent' );
			delete_term_meta( $term_sub->term_id, 'wooms_wooms_id_parent' );
		}
		
		wp_update_term_count( $term_id, $taxonomy = 'product_cat' );
		
	}
	
	/**
	 * Meta box in category
	 *
	 * @param $term
	 */
	public function add_data_category( $term ) {
		$meta_data = get_term_meta( $term->term_id, 'wooms_id', true );
		if ( ! $meta_data ) {
			$meta_data = '';
		}
		
		?>
		
		<tr class="form-field term-meta-text-wrap">
			<td colspan="2" style="padding: 0;">
				<h3 style="margin: 0;">МойСклад</h3>
			</td>
		</tr>
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
				<a href="https://online.moysklad.ru/app/#good/edit?id=<?php echo $meta_data ?>" target="_blank">Посмотреть категории в МойСклад</a>
			</td>
		</tr>
	<?php }
	
	/**
	 * Settings UI
	 */
	public function settings_init() {
		
		register_setting( 'mss-settings', 'woomss_categories_sync_enabled' );
		add_settings_field( $id = 'woomss_categories_sync_enabled', $title = 'Отключить синхронизацию категорий', $callback = [
			$this,
			'display_option_categories_sync_enabled',
		], $page = 'mss-settings', $section = 'woomss_section_other' );
		
	}
	
	//Display field
	public function display_option_categories_sync_enabled() {
		$option = 'woomss_categories_sync_enabled';
		printf( '<input type="checkbox" name="%s" value="1" %s />', $option, checked( 1, get_option( $option ), false ) );
		?>
		<small>Если включить опцию, то при обновлении продуктов категории не будут учтываться в соответствии с группами МойСклад.</small>
		<?php
	}
}

new WooMS_Import_Product_Categories;
