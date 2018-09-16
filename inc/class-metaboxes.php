<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Info metaboxes
 */
class WooMS_Metaboxes {
	
	
	/**
	 * WooMS_Metaboxes init.
	 */
	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'loaded' ) );
	}
	
	/**
	 *
	 */
	public static function loaded() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes_post_type' ) );
		add_action( 'product_cat_edit_form_fields', array( __CLASS__, 'add_data_category' ), 30 );
	}
	
	/**
	 * Add metaboxes
	 */
	public static function add_meta_boxes_post_type() {
		add_meta_box( 'metabox_order', 'МойСклад', array( __CLASS__, 'add_meta_box_data_order' ), 'shop_order', 'side', 'low' );
		add_meta_box( 'metabox_product', 'МойСклад', array( __CLASS__, 'add_meta_box_data_product' ), 'product', 'side', 'low' );
	}
	
	/**
	 * Meta box in order
	 */
	public static function add_meta_box_data_order() {
		$post      = get_post();
		$meta_data = get_post_meta( $post->ID, 'wooms_id', true );
		echo self::meta_box_data( $meta_data );
		
	}
	
	/**
	 *
	 * Data output in the metabox
	 *
	 * @param string $data
	 * @param string $type
	 *
	 * @return string
	 */
	public static function meta_box_data( $data = '', $type = 'order' ) {
		$meta_data = '';
		switch ( $type ) {
			case 'order':
				if ( $data ) {
					$meta_data = sprintf( '<div>ID заказа в МойСклад: <div><strong>%s</strong></div></div>', $data );
					$meta_data .= sprintf( '<p><a href="https://online.moysklad.ru/app/#customerorder/edit?id=%s">Посмотреть заказ в МойСклад</a></p>', $data );
				} else {
					$meta_data = 'Заказ не передан в МойСклад';
				}
				break;
			case 'product':
				$meta_data = sprintf( '<div>ID товара в МойСклад: <div><strong>%s</strong></div></div>', $data );
				$meta_data .= sprintf( '<p><a href="https://online.moysklad.ru/app/#good/edit?id=%s">Посмотреть товар в МойСклад</a></p>', $data );
				break;
		}
		
		return $meta_data;
	}
	
	/**
	 * Meta box in product
	 */
	public static function add_meta_box_data_product() {
		$post      = get_post();
		$meta_data = get_post_meta( $post->ID, 'wooms_id', true );
		echo self::meta_box_data( $meta_data, 'product' );
		
	}
	
	
	/**
	 * Meta box in category
	 *
	 * @param $term
	 */
	public static function add_data_category( $term ) {
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
				<a href="https://online.moysklad.ru/app/#good/edit?id=<?php echo $meta_data ?>">Посмотреть категории в МойСклад</a>
			</td>
		</tr>
	<?php }
}

WooMS_Metaboxes::init();