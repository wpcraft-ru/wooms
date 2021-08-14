<?php
/**
 * Индикатор синхронизации в список товаров в админке
 *
 * @package WooMS
 */

namespace WooMS;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class MetaColumn {

    /**
     * The Init
     */
    public static function init() {

        add_filter( 'manage_product_posts_columns', array( __CLASS__, 'column_heading' ), 10, 1 );

        add_action( 'manage_product_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
    }

    /**
     * Adds the column heading for Wooms indicator.
     *
     * @param array $columns Already existing columns.
     *
     * @return array Array containing the column heading.
     */
    public static function column_heading( $columns ) {

        $added_columns = array(
            'wooms-sync' => '<span class="dashicons-before dashicons-forms"><span><span class="screen-reader-text">Синхронизация с МойСклад</span>'
        );

        return array_merge( $columns, $added_columns );
    }
    
    /**
     * Displays the column content for the given column.
     *
     * @param string $column_name Column to display the content for.
     * @param int    $post_ID     Post to display the column content for.
     * 
     * @return void
     */
    public static function column_content( $column_name, $post_ID ) {

        if ( $column_name === 'wooms-sync' ) {

            $uuid = get_post_meta($post_ID, 'wooms_id', true);

            if (!$uuid) {
                echo '<span class="dashicons-before dashicons-no-alt wp-ui-text-notification"><span><span class="screen-reader-text">Товар не синхронизирован с МойСклад</span>';
            } else {
                echo '<span class="dashicons-before dashicons-yes"><span><span class="screen-reader-text">Товар не синхронизирован с МойСклад</span>';
            }
        }
    }

}

MetaColumn::init();
