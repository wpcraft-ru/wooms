<?php

namespace WooMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Import Product Categories from MoySklad
 */
class CategoriesFilter {

	public static $groups = [];
	/**
	 * WooMS_Import_Product_Categories constructor.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'add_settings' ), 50 );

		$groups = get_option( 'wooms_set_folders' );

		if ( $groups ) {
			self::$groups = explode( ',', $groups );
		}

		if ( empty( self::$groups ) ) {
			return;
		}

		add_filter( 'wooms_url_get_products_filters', array( __CLASS__, 'product_add_filter_by_folder' ), 10 );
		add_filter( 'wooms_url_get_bundle_filter', array( __CLASS__, 'product_add_filter_by_folder' ), 10 );
		add_filter( 'wooms_url_get_service_filter', array( __CLASS__, 'product_add_filter_by_folder' ), 10 );
		add_filter( 'wooms_productfolder', array( __CLASS__, 'filter_folders' ), 10 );
	}

	public static function filter_folders( $filter_folders ) {

		$new_folders = [];
		foreach ( $filter_folders as $folder ) {

			if(empty($folder['pathName'])){
				foreach(self::$groups as $group){
					if(str_starts_with($folder['name'], $group)) {
						$new_folders[] = $folder;
					}
				}
			} else {
				foreach(self::$groups as $group){
					if(str_starts_with($folder['pathName'], $group)){
						$new_folders[] = $folder;
					}
				}

			}
		}

		if ( $new_folders ) {
			return $new_folders;
		}

		return $filter_folders;
	}

	/**
	 * Добавляем фильтр по папке
	 * Если выбрана группа для синка
	 * Use $url_api = apply_filters('wooms_url_get_products', $url);
	 */
	public static function product_add_filter_by_folder( $filters ) {

		foreach ( self::$groups as $group ) {
			$filters[] = 'pathName~=' . trim( $group );
		}
		return $filters;
	}


	/**
	 * Settings UI
	 */
	public static function add_settings() {

		register_setting( 'mss-settings', 'wooms_set_folders' );
		add_settings_field(
			$name = 'wooms_set_folders',
			$title = 'Группы товаров для фильтрации',
			$render = function ($args) {
				printf( '<textarea name="%s" rows="5" cols="50">%s</textarea>', $args['key'], $args['value'] );
				printf( '<p><small>%s</small></p>',
					'Тут можно указать группы для фильтрации товаров через запятую. Например: "Мебель/Диваны,Пицца,Одежда/Обувь/Ботинки"'
				);
			},
			$setings = 'mss-settings',
			$group = 'wooms_product_cat',
			$arts = [
				'key' => 'wooms_set_folders',
				'value' => get_option( 'wooms_set_folders' ),
			]
		);

	}

}

CategoriesFilter::init();
