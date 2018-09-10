<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Import Product Categories from MoySklad
 */
class WooMS_Metaboxes {
	
	/**
	 * WooMS_Import_Product_Categories constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes_post_type' ) );
		add_action( 'product_cat_edit_form_fields', array( $this, 'add_data_category' ) , 30);
		add_action( 'save_post', array( $this, 'save_meta_boxes_order' ), 1, 2 );
	}
	
	public function add_meta_boxes_post_type() {
		add_meta_box( 'metabox_order', 'МойСклад', array( $this, 'add_meta_box_data_order' ), 'shop_order', 'side', 'low' );
		add_meta_box( 'metabox_product', 'МойСклад', array( $this, 'add_meta_box_data_product' ), 'product', 'side', 'low' );
	}

	
	public function meta_box_data( $data = '', $type = 'order' ) {
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
			case 'category':
				$meta_data = sprintf( '<div>ID категории в МойСклад: <div><strong>%s</strong></div></div>', $data );
				$meta_data .= sprintf( '<p><a href="https://online.moysklad.ru/app/#good/edit?id=%s">Посмотреть категории в МойСклад</a></p>', $data );
				break;
		}
		
		return $meta_data;
	}
	
	function add_meta_box_data_order() {
		global $post;
		$meta_data = get_post_meta( $post->ID, 'wooms_id', true );
		echo $this->meta_box_data( $meta_data );
		
	}
	function add_meta_box_data_product() {
		global $post;
		$meta_data = get_post_meta( $post->ID, 'wooms_id', true );
		echo $this->meta_box_data( $meta_data, 'product');
		
	}
	
	function add_meta_box_data_category() {
		global $post;
		$meta_data_order = get_term_meta( $post->ID, 'wooms_id', true );
		echo $this->meta_box_data( $meta_data_order , 'category');
		
	}

	function add_data_category( $term ) {
		$meta_data  = get_term_meta( $term->term_id , 'wooms_id', true );
		if (!$meta_data){
			$meta_data = '';
		}
		//echo $this->meta_box_data( $meta_data , 'category');
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

new WooMS_Metaboxes();