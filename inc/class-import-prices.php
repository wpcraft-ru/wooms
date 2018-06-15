<?php

/**
 * Select specific price is setup
 */
class WooMS_Import_Prices {
	/**
	 * WooMS_Import_Prices constructor.
	 */
	public function __construct() {
		add_filter( 'wooms_product_price', array( $this, 'chg_price' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'settings' ), $priority = 100, $accepted_args = 1 );
	}
	
	/**
	 * Update prices for product
	 * Check specifiec price and replace if isset price
	 */
	public function chg_price( $price, $data ) {
		$price_name = get_option( 'wooms_price_id' );
		
		if ( empty( $price_name ) || 'Распродажа' === $price_name ) {
			return $price;
		} else {
			$price_value = $this->get_value_for_price_type( $data );
			if ( empty( $price_value ) ) {
				return $price;
			} else {
				return $price_value;
			}
		}
	}
	
	/**
	 * Get specific price
	 * Return 0 or value price
	 */
	public function get_value_for_price_type( $data ) {
		$price_name = get_option( 'wooms_price_id' );
		if ( empty( $price_name ) ) {
			return 0;
		}
		
		$price_value = 0;
		foreach ( $data["salePrices"] as $price ) {
			
			if ( $price["priceType"] == $price_name ) {
				$price_value = $price["value"];
			}
		}
		
		if ( empty( $price_value ) ) {
			return 0;
		} else {
			return $price_value;
		}
	}
	
	/**
	 * Add settings
	 */
	public function settings() {
		register_setting( 'mss-settings', 'wooms_price_id' );
		add_settings_field( $id = 'wooms_price_id', $title = 'Тип Цены', $callback = [
			$this,
			'display_field_wooms_price_id',
		], $page = 'mss-settings', $section = 'woomss_section_other' );
	}
	
	public function display_field_wooms_price_id() {
		$id = 'wooms_price_id';
		printf( '<input type="text" name="%s" value="%s" />', $id, sanitize_text_field( get_option( $id ) ) );
		echo '<p><small>Укажите наименование цены, если нужно выбрать специальный тип цен. Система будет проверять такой тип цены и если он указан то будет подставлять его вместо базового.</small></p>';
		echo '<p><small>Для добавления распродажных цен укажите в поле слово <strong>Распродажа</strong>. <strong>Внимание! В МойСклад тип расродажных цен должен называться точно также - Распродажа</strong></small></p>';
	}
}

new WooMS_Import_Prices;