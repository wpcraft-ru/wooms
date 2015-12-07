<?php
/*
Plugin Name: WooSklad
Plugin URI:
Description: This plugin integrates WooCommerce and MoySklad. This plugin can update balances in woocommerce from moysklad and update orders in moysklad from woocommerce.
Author: Systemo
Version: 1.0
Author URI: http://systemo.biz/
*/

require_once('woosklad_moysklad.php');
require_once('woosklad_mapping.php');
require_once('woosklad_xml_create.php');
require_once('woosklad_update.php');
require_once('woosklad_synchronization.php');
require_once('woosklad_init.php');

///МЕНЮ///
add_action('admin_menu', 'woosklad_menu', 12);
function woosklad_menu() {
	$page = add_menu_page('Настройки интеграции Woocommerce и Мой Склад', 'Мой Склад', 'edit_others_posts', 'woosklad_menu', 'woosklad_page');
	add_action('admin_print_scripts-'.$page, 'woosklad_scripts');
}
///ДОСТУП///
add_filter( 'option_page_capability_woosklad_menu', 'my_page_capability' );
add_filter( 'option_page_capability_woosklad', 'my_page_capability' );
function my_page_capability( $capability ) {
	return 'edit_others_posts';
}
///СКРИПТЫ///
function woosklad_scripts() {
	wp_enqueue_script('bootstrap', plugins_url( '/src/js/bootstrap.js', __FILE__ ), array( 'jquery' ));
	wp_enqueue_script( 'woosklad-settings', plugins_url( '/src/js/woosklad-settings.js', __FILE__ ), array( 'jquery', 'bootstrap') );
	wp_enqueue_style( 'bootstrap', plugins_url( '/src/css/bootstrap.css', __FILE__ ) );
	wp_enqueue_style( 'woosklad-style', plugins_url( '/src/css/woosklad-style.css', __FILE__ ) );
	wp_localize_script( 'woosklad-settings', 'woosklad', array(
            'order_progress'          => wp_create_nonce( 'order-progress' ),
            'stock_progress' => wp_create_nonce( 'stock-progress' )
        ) );
}

add_action('wp_ajax_woosklad-update-stock', 'download_stock');
add_action('wp_ajax_woosklad-save-stock', 'save_stock');
add_action('wp_ajax_woosklad-stock-progress', 'stock_progress');

add_action('wp_ajax_woosklad-start-orders', 'start_orders');
add_action('wp_ajax_woosklad-update-orders', 'upload_orders');
add_action('wp_ajax_woosklad-order-progress', 'order_progress');

add_action('wp_ajax_woosklad-start-goods', 'start_goods');
add_action('wp_ajax_woosklad-update-goods', 'upload_goods');
add_action('wp_ajax_woosklad-goods-progress', 'goods_progress');

add_action('wp_ajax_woosklad-synchronization', 'start_synchronization');
add_action('wp_ajax_woosklad-first-sync', 'first_synch');
add_action('wp_ajax_woosklad-sync-progress','sync_progress');

add_action('wp_ajax_woosklad-reset-opt', 'reset_option');

///НАСТРОЙКИ///
add_action('admin_init', 'create_options');
function create_options() {
	register_setting('woosklad', 'woosklad_login');
	register_setting('woosklad', 'woosklad_password');

	register_setting('woosklad', 'woosklad_order_time');
	register_setting('woosklad', 'woosklad_company_uuid');
	register_setting('woosklad', 'woosklad_agent_uuid');
	register_setting('woosklad', 'woosklad_stores');
	register_setting('woosklad', 'woosklad_priority');

	if (!get_option('woosklad_last_order_update')) add_option('woosklad_last_order_update','','','no');
	if (!get_option('woosklad_updated_order')) add_option('woosklad_updated_order','','','no');
	if (!get_option('woosklad_total_order')) add_option('woosklad_total_order','','','no');
	//if (!get_option('woosklad_shipment_uuid')) add_option('woosklad_shipment_uuid','','','no');

	register_setting('woosklad', 'woosklad_stock_time');

	if (!get_option('woosklad_last_stock_update')) add_option('woosklad_last_stock_update','','','no');
	if (!get_option('woosklad_updated_stock')) add_option('woosklad_updated_stock','','','no');
	if (!get_option('woosklad_all_stock')) add_option('woosklad_all_stock');
	if (!get_option('woosklad_total_stock')) add_option('woosklad_total_stock','','','no');

	register_setting('woosklad', 'woosklad_good_time');

	if (!get_option('woosklad_error')) add_option('woosklad_error','','','no');
}

///КРОН///
	add_filter( 'cron_schedules', 'cron_stock_schedules' );
	function cron_stock_schedules($schedules) {
		if (get_option('woosklad_stock_time')) {
			$schedules['stock'] = array(
				'interval' => get_option('woosklad_old_stock_time'),
				'display' => __( 'Загрузка остатков' )
				);
		}
		return $schedules;
	}

	add_filter( 'cron_schedules', 'cron_order_schedules' );
	function cron_order_schedules($schedules) {
		if (get_option('woosklad_order_time')) {
			$schedules['order'] = array(
				'interval' => get_option('woosklad_old_order_time'),
				'display' => __( 'Выгрузка заказов' )
			);
		}
		return $schedules;
	}

	add_filter( 'cron_schedules', 'cron_good_schedules' );
	function cron_good_schedules($schedules) {
		if (get_option('woosklad_good_time')) {
			$schedules['good'] = array(
				'interval' => get_option('woosklad_old_good_time'),
				'display' => __( 'Выгрузка товаров' )
			);
		}
		return $schedules;
	}

	add_action('woosklad_stock_hook', 'woosklad_update_stock');
	function woosklad_update_stock() {
		save_stock(true); download_stock(true);
	}

	add_action('woosklad_order_hook', 'woosklad_update_order');
	function woosklad_update_order() {
		update_option('woosklad_total_order', get_orders_count());
		update_option('woosklad_updated_order', 0);
		upload_orders(true);
	}

	add_action('woosklad_good_hook', 'woosklad_update_good');
	function woosklad_update_good() {
		update_option('woosklad_total_good', get_items_count());
		update_option('woosklad_updated_good', 0);
		upload_goods(true);
	}

///УДАЛЕНИЕ///
	add_action( 'before_delete_post', 'save_variation_uuid', 0, 1 );
	add_action( 'deleted_post', 'delete_variation', 10, 1 );
	function save_variation_uuid($postid) {
		$type = get_post_type($postid);
		if ($type == 'product_variation') {
			$uuid = get_post_meta($postid, '_woosklad_feature_uuid', true);
			update_option('woosklad_deleted_type', $uuid);
		}
	}

	function delete_variation($postid) {
		$type = get_post_type($postid);
		if ($type == 'product_variation') {
			$uuid = get_option('woosklad_deleted_type');
			$result = delete_type_uuid('Feature', $uuid);
			//update_option('woosklad_delete_result', json_decode($result));
			if ($result) echo "<pre>"; print_r ($result); echo "</pre>";
		}

	}
///СОХРАНЕНИЕ///
 add_action('save_post', 'save_to_sklad', 30, 3);
 function save_to_sklad($post_id, $post)
 {
	 if ($post->post_status == 'publish' && $post->post_type == 'product') update_goods(array($post_id));
	 if ($post->post_type == 'shop_order' && $post->post_type != 'trash' && $post->post_type != 'auto-draft'
			&& $post->post_type != 'draft' && $post->post_type != 'future' && $post->post_type != 'pending')
			update_orders(array($post_id));
 }
///СТРАНИЦА///
function woosklad_page() {

	/*if (isset($_REQUEST) && $_REQUEST['settings-updated']) {
		update_user_meta( get_current_user_id(), '_woosklad_login', get_option('woosklad_login'));
		update_user_meta( get_current_user_id(), '_woosklad_password', get_option('woosklad_password') );
	}*/
	//echo "<pre> Result: "; print_r($_REQUEST); echo "</pre>";
	$stores = get_option('woosklad_save_stores');
	$agents = get_option('woosklad_save_agents');
	$comps = get_option('woosklad_save_company');
	$prior = get_option('woosklad_priority');
	$select_store = get_option('woosklad_stores');

	$schedules = wp_get_schedules();

	// cron синхронизация остатков
	if (!get_option('woosklad_stock_time') OR null)
	{
		$next = wp_next_scheduled('woosklad_stock_hook');
		if ($next) wp_unschedule_event($next, 'woosklad_stock_hook');
	}
	else
	{
		$get_stock_time = get_option('woosklad_stock_time');
		if ($get_stock_time <= 5) {
			update_option('woosklad_stock_time', 5);
			$time = 5*60;
		} else {
			$time = get_option('woosklad_stock_time')*60;
		}
		//if ($schedules['stock']['interval'] != $time) {
			update_option('woosklad_old_stock_time',$time);
			//apply_filters( 'cron_schedules', 'cron_stock_schedules' );
			$next = wp_next_scheduled('woosklad_stock_hook');
			if ($next) wp_unschedule_event($next, 'woosklad_stock_hook');
			wp_schedule_event(time()+$time, 'stock', 'woosklad_stock_hook');
		//}
	}

	// cron синхронизация заказов
	if (!get_option('woosklad_order_time') OR null)
	{
		$next = wp_next_scheduled('woosklad_order_hook');
		if ($next) wp_unschedule_event($next, 'woosklad_order_hook');
	}
	else
	{
		$get_order_time = get_option('woosklad_order_time');
		if ($get_order_time <= 5) {
			update_option('woosklad_order_time', 5);
			$time = 5*60;
		} else {
			$time = get_option('woosklad_order_time')*60;
		}
		//if ($schedules['order']['interval'] != $time) {
			update_option('woosklad_old_order_time', $time);
			//apply_filters( 'cron_schedules', 'cron_order_schedules' );
			$next = wp_next_scheduled('woosklad_order_hook');
			if ($next) wp_unschedule_event($next, 'woosklad_order_hook');
			wp_schedule_event( time()+$time, 'order', 'woosklad_order_hook');
		//}
	}

	// cron синхронизация товаров
	if (!get_option('woosklad_good_time') OR null)
	{
		$next = wp_next_scheduled('woosklad_good_hook');
		if ($next) wp_unschedule_event($next, 'woosklad_good_hook');
	}
	else
	{
		$get_good_time = get_option('woosklad_good_time');
		if ($get_good_time <= 5) {
			update_option('woosklad_good_time', 5);
			$time = 5*60;
		} else {
			$time = get_option('woosklad_good_time')*60;
		}
		//if ($schedules['good']['interval'] != $time) {
			update_option('woosklad_old_good_time', $time);
			//apply_filters( 'cron_schedules', 'cron_good_schedules' );
			$next =  wp_next_scheduled('woosklad_good_hook');
			if ($next) wp_unschedule_event($next, 'woosklad_good_hook');
			wp_schedule_event( time()+$time, 'good', 'woosklad_good_hook');
		//}
	}


	if (get_option('woosklad_error')) {
?>
		<div id="error_message" class='error'><p><?php echo get_option('woosklad_error'); ?></p></div>
<?php 	update_option('woosklad_error','');
	}
	else { ?>
		<div id="error_message" class='error hidden'></div>
<?php } ?>
		<div class="wrap"><?php //echo "<pre>"; print_r( _get_cron_array() ); echo "</pre>"; //TODO удалить?>
			<h2><?php echo get_admin_page_title() ?></h2>
			<div id="attent"> Важно! Для корректной загрузки остатков и выгрузки заказов необходимо производить
				синхронизацию информации при первом подключении к аккаунту "Мой Склад", а также при изменении в "Мой Склад" списка контрагентов,
				складов и организаций. Для корректной выгрузки вариаций товара необходимо в "Мой Склад" создать тестовый товар с модификацией,
				включающей все характеристики (атрибуты) товара, и выполнить синхронизацию.
				Запустить синхронизацию можно внизу страницы.</div>
			<form  action="options.php" method="POST">
				<h3>Авторизация в "Мой Склад"</h3>
				<?php settings_fields( 'woosklad' ); ?>

				<table class="form-table" id="auth">
					<tr>
						<th><label for="woosklad_login">Логин</label></th>
						<td><input name="woosklad_login" type="text" value="<?php echo get_option('woosklad_login') /*get_user_meta( get_current_user_id(), '_woosklad_login',true )*/ ?>" /></td>
					</tr>
					<tr>
						<th><label for="woosklad_password">Пароль</label></th>
						<td><input name="woosklad_password" type="password" value="<?php echo get_option('woosklad_password')/*get_user_meta( get_current_user_id(), '_woosklad_password', true )*/ ?>" /></td>
					</tr>
				</table>
				<table class="form-table" id="settings">
					<tr>
						<td>
							<h3>Настройка загрузки данных</h3>
							<table class="form-table" id="orders">
								<tr>
									<th>
										<label for="woosklad_stock_time">Интервал загрузки остатков в минутах</label>
										<img src="<?php echo plugins_url('/src/img/help.png', __FILE__); ?>" data-toggle="tooltip"
											title="Интервал между автоматическими загрузками данных. Чтобы отменить автозагрузку, оставьте это поле пустым." height="16px" width="16px"/>
									</th>
									<td><input name="woosklad_stock_time" type="text" value="<?php echo get_option( 'woosklad_stock_time' )?>" /></td>
								</tr>
								<tr>
									<th>
										<label for="woosklad_good_time">Интервал выгрузки товаров в минутах</label>
										<img src="<?php echo plugins_url('/src/img/help.png', __FILE__); ?>" data-toggle="tooltip"
											title="Интервал между автоматическими загрузками данных. Чтобы отменить автозагрузку, оставьте это поле пустым." height="16px" width="16px"/>
									</th>
									<td><input name="woosklad_good_time" type="text" value="<?php echo get_option( 'woosklad_good_time' )?>" /></td>
								</tr>
								<tr>
									<th>
										<label for="woosklad_order_time">Интервал выгрузки заказов в минутах</label>
										<img src="<?php echo plugins_url('/src/img/help.png', __FILE__); ?>" data-toggle="tooltip"
											title="Интервал между автоматическими выгрузками данных. Чтобы отменить автовыгрузку, оставьте это поле пустым." height="16px" width="16px"/>
									</th>
									<td><input name="woosklad_order_time" type="text" value="<?php echo get_option( 'woosklad_order_time' )?>" /></td>
								</tr>
								<tr>
									<th>
										<label for="woosklad_agent_uuid">Контрагент</label>
										<img src="<?php echo plugins_url('/src/img/help.png', __FILE__); ?>" data-toggle="tooltip"
											title="Контрагент, подставляемый в заказ покупателя." height="16px" width="16px"/>
									</th>
									<td><select name="woosklad_agent_uuid">
<?php
 									$agent_uuid = get_option('woosklad_agent_uuid');

										foreach ($agents as $key=>$value) {
											$selected = $agent_uuid==$key ? 'selected' : '';
?>

											<option value="<?php echo $key?>" <?php echo $selected ?>><?php echo $value?></option>
<?php 									} ?>
										</select>
									</td>
								</tr>
								<tr>
									<th>
										<label for="woosklad_company_uuid">Организация</label>
										<img src="<?php echo plugins_url('/src/img/help.png', __FILE__); ?>" data-toggle="tooltip"
										title="Организация, от имени которой будут создаваться заказы." height="16px" width="16px"/>
									</th>
									<td><select name="woosklad_company_uuid">
<?php 									$company_uuid = get_option('woosklad_company_uuid');

										foreach ($comps as $key=>$value) {
											$selected = $company_uuid==$key ? 'selected' : '';
?>
											<option value="<?php echo $key?>" <?php echo $selected ?>><?php echo $value?></option>
<?php 									}
?>
										</select>
									</td>
								</tr>
							</table>
						</td>
						<td></td>
						<td>
							<h3>Настройка складов
								<img src="<?php echo plugins_url('/src/img/help.png', __FILE__); ?>" data-toggle="tooltip"
								title="Выбор складов, с которых будет произведена выгрузка остатков, установка приоритетов." height="16px" width="16px"/>
							</h3>
							<table class="form-table" id="stores">
<?php
								$i = 0;
								if ($stores && $stores!=1)
								foreach ($stores as $key=>$value) {
									if ($select_store)
										$checked = in_array($key, $select_store) ? 'checked' : '';
?>
									<tr>
										<td><input type="checkbox" name="woosklad_stores[]" value="<?php echo $key ?>" <?php echo $checked ?>></td>
										<td><label><?php echo $value?></label></td>
										<td><input type="text" name="woosklad_priority[]" value="<?php echo $prior[$i] ?>"></td>
									</tr>
<?php
									$i++;
								}
?>
							</table>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<table class="form-table" id="download">
				<tr>
					<td>
						<h3>Выгрузка заказов в МойСклад</h3>
						<p>Процесс выгрузки может занять длительное время</p>
						<p class="submit">
							<input type="submit" name="submit" id="update_orders" class="button button-primary" value="Выгрузить заказы">
						</p>
						<div class="progress hidden">
							<div id="progress-order" class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%">
								0%
							</div>
						</div>
						<div class="">Последняя выгрузка произошла в <span id="last_time_order"><?php echo get_option('woosklad_last_order_update'); ?></span></div>
					</td>
					<td>
						<h3>Загрузить остатки из МойСклад</h3>
						<p>Процесс загрузки может занять длительное время</p>
						<p class="submit">
							<input type="submit" name="submit" id="update_stock" class="button button-primary" value="Загрузить остатки">
						</p>
						<div class="progress hidden">
							<div id="progress-stock" class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%;">
								0%
							</div>
						</div>
						<div class="">Последняя загрузка произошла в <span id="last_time_stock"><?php echo get_option('woosklad_last_stock_update'); ?></span></div>

					</td>
				</tr>


				<tr>
					<td>
						<h3>Выгрузка товаров в МойСклад</h3>
						<p>Процесс выгрузки может занять длительное время</p>
						<p class="submit">
							<input type="submit" name="submit" id="update_goods" class="button button-primary" value="Выгрузить товары">
						</p>
						<div class="progress hidden">
							<div id="progress-good" class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%">
								0%
							</div>
						</div>
						<div class="">Последняя выгрузка произошла в <span id="last_time_good"><?php echo get_option('woosklad_last_items_update'); ?></span></div>
					</td>
					<td>
						<h3>Синхронизация информации</h3>
						<p>Загрузка информации о контрагентах, складах, компаниях, <br />выгрузка статусов интернет-магазина.<br />
							Синхронизация может занять продолжительное время!</p>
						<p class="submit">
							<input type="submit" name="submit" id="sync_info" class="button button-primary" value="Синхронизировать">
						</p>
						<div class="progress hidden">
							<div id="progress-sync" class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width:100%;">></div>					</div>
						<div class="">Последняя синхронизация произошла в <span id="last_time_sync"><?php echo get_option('woosklad_last_sync_update'); ?></span></div>
					</td>
				</tr>
			</table>
			<table class="form-table" id="download">
				<tr>
					<td>
						<h3>Сброс настроек</h3>
						<p>Сбрасывает настройки плагина и историю синхронизаций</p>
						<p class="submit">
							<input type="submit" name="submit" id="reset_option" class="button button-primary" value="Сбросить настройки">
						</p>
					</td>
				</tr>
			</table>
		</div>
<?php
}
?>
