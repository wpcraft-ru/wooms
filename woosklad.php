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
require_once('woosklad-synchronization.php');

add_action('admin_menu', 'woosklad_menu', 12);

function woosklad_menu() {
	$page = add_menu_page('Настройки интеграции Woocommerce и Мой Склад', 'Мой Склад', 'manage_options', __FILE__, 'woosklad_page'); 
	add_action('admin_print_scripts-'.$page, 'woosklad_scripts');
}

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
add_action('wp_ajax_woosklad-sync-progress','sync_progress');


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
	if (!get_option('woosklad_shipment_uuid')) add_option('woosklad_shipment_uuid','','','no');

	register_setting('woosklad', 'woosklad_stock_time');
	
	if (!get_option('woosklad_last_stock_update')) add_option('woosklad_last_stock_update','','','no');
	if (!get_option('woosklad_updated_stock')) add_option('woosklad_updated_stock','','','no');
	if (!get_option('woosklad_all_stock')) add_option('woosklad_all_stock');
	if (!get_option('woosklad_total_stock')) add_option('woosklad_total_stock','','','no');
	
	register_setting('woosklad', 'woosklad_good_time');
	
	if (!get_option('woosklad_error')) add_option('woosklad_error','','','no');
}


function save_stock($cron = false) {
	$stores = get_priority_stores(); $all = array();
	$data = array();
	foreach ($stores as $key=>$value) {
		$info = get_stock($result, $value);
		if ($info == 200) {
		$all = array_merge($all, json_decode($result)); 
		//echo "<pre>"; print_r($all); echo "</pre>";
		$data[] = array('count'=>count($all), 'uuid'=>$value);
		}
		else {
			update_option('woosklad_total_stock', 0);
			$data = array('result'=>'error', 'message'=>'Остатки не были загружены. Проверьте правильность подключения');
			if (!$cron) {
				echo json_encode($data);
				wp_die();
			}
			else exit;
		}
	}
	update_option('woosklad_all_stock', $all);
	update_option('woosklad_count_on_store', $data);
	update_option('woosklad_index_store', 0);
	update_option('woosklad_total_stock', count($all));
	update_option('woosklad_updated_stock', 0);		$data = array('result'=>'OK');
	if (!$cron) {
		echo json_encode($data);
		wp_die();
	}
}
	
function download_stock($cron = false) {
	$start = current_time('mysql');
	$total = get_option('woosklad_total_stock');
	update_option('woosklad_start_cons_download', 0);
	for ($i=0; $i<$total; $i+=75) {
		update_stock($i);
	}
	update_option('woosklad_last_stock_update', $start);
	update_empty_stock();
	if (!$cron) { wp_die();}
}
			
function stock_progress() {
	if ( ! wp_verify_nonce($_POST['security'], 'stock-progress')) {
		die(json_encode(array('result' => 'error')));
		}
		if (get_option('woosklad_error')) {
			$data = array('result'=>'error', 'message'=>get_option('woosklad_error'));
			}
			else {
				$data = array('result'=>'OK', 'count'=>get_option('woosklad_updated_stock'), 'total'=>get_option('woosklad_total_stock'),'last_update'=>get_option('woosklad_last_stock_update'));
		}
	echo json_encode($data);
	wp_die();
}
	
function start_orders() {
	update_option('woosklad_total_order', get_orders_count());
	update_option('woosklad_updated_order',0);
	echo json_encode(array('result'=>'OK'));
	wp_die();
}
	
function upload_orders($cron = false) {
	$start =  current_time('mysql');
	get_orders(); 
	update_option('woosklad_last_order_update', $start);
	if (!$cron) { wp_die();}
}
	
	function order_progress() {
		if ( ! wp_verify_nonce( $_POST['security'], 'order-progress' ) ) {
			die(json_encode(array('result' => 'error')));
			}
			$data = array('result'=>'OK', 'count'=>get_option('woosklad_updated_order'), 'total'=>get_option('woosklad_total_order'),'last_update'=>get_option('woosklad_last_order_update'));
			echo json_encode($data);
	wp_die();
	}
	
	function start_goods() {
		update_option('woosklad_total_good', get_items_count());
		update_option('woosklad_updated_good', 0);
		echo json_encode(array('result'=>'OK'));
		wp_die();
	}
	
	function upload_goods($cron = false) {
		$start =  current_time('mysql');
		get_items(); 
		update_option('woosklad_last_items_update', $start);
		if (!$cron) { wp_die();}
	}
	
	function goods_progress() {
		$data = array('result'=>'OK', 
		'count'=>get_option('woosklad_updated_good'), 
		'total'=>get_option('woosklad_total_good'),
		'last_update'=>get_option('woosklad_last_items_update'));
		echo json_encode($data);
		wp_die();
	}
	
	add_filter( 'cron_schedules', 'cron_stock_schedules' );
	add_filter( 'cron_schedules', 'cron_order_schedules' );
	add_filter( 'cron_schedules', 'cron_good_schedules' );
	function cron_stock_schedules($schedules) {
		if (get_option('woosklad_stock_time')) {
			$schedules['stock'] = array(
				'interval' =>  get_option('woosklad_old_stock_time'),
				'display' => __( 'Загрузка остатков' )
				);
		}
		return $schedules;
	}
	
	function cron_order_schedules($schedules) {
		if (get_option('woosklad_order_time')) {
			$schedules['order'] = array(
				'interval' => get_option('woosklad_old_order_time'),
				'display' => __( 'Выгрузка заказов' )
			);
		}
		return $schedules;
	}
			
	function cron_good_schedules($schedules) {
		if (get_option('woosklad_good_time')) {
			$schedules['good'] = array(
				'interval' => get_option('woosklad_old_good_time'),
				'display' => __( 'Выгрузка товаров' )
			);
		}
		return $schedules;
	}
			
	function woosklad_update_stock() { 
		save_stock(true); download_stock(true);
	}
	
	function woosklad_update_order() { 
		update_option('woosklad_total_order', get_orders_count());
		update_option('woosklad_updated_order',0);
		upload_orders(true);
	}
	
	function woosklad_update_good() { 
		update_option('woosklad_total_good', get_items_count());
		update_option('woosklad_updated_good', 0);
		upload_goods(true);
	}
	
	add_action('woosklad_stock_hook', 'woosklad_update_stock');
	add_action('woosklad_order_hook', 'woosklad_update_order');
	add_action('woosklad_good_hook', 'woosklad_update_good');
	
	add_action('woosklad_consignment_hook', 'first_synch');
	function first_synch() {
		$time = current_time('mysql');
		save_sklad_list('Warehouse', 'Склады', 'stores');
		save_sklad_list('Company', 'Контрагенты', 'agents');
		save_sklad_list('MyCompany', 'Компании', 'company');
		update_option('woosklad_sync_result', 'Выгрузка статусов');
		sleep(1);
		put_states_identity();
		update_option('woosklad_sync_result', 'Синхронизация атрибутов');
		put_attribute_identity();
		
		update_option('woosklad_sync_result', 'Синхронизация завершена');
		update_option('woosklad_last_sync_update', $time);
	}
		
	function start_synchronization() {
		update_option('woosklad_sync_result','Загрузка справочников');
		first_synch();
		echo json_encode(array('result'=>'OK'));
		wp_die();
	}
		
	function sync_progress() {
		echo json_encode(array('result'=>'OK', 'progress' => get_option('woosklad_sync_result'), 'last_update' => get_option('woosklad_last_sync_update')));
		wp_die();
	}
		
function woosklad_page() { 
	$stores = get_option('woosklad_save_stores'); 
	$agents = get_option('woosklad_save_agents'); 
	$comps = get_option('woosklad_save_company');
	$prior = get_option('woosklad_priority');
	$select_store = get_option('woosklad_stores');
	
	$schedules = wp_get_schedules();
	if (!get_option('woosklad_stock_time') || get_option('woosklad_stock_time') == 0) {
		$next = wp_next_scheduled('woosklad_stock_hook');
		if ($next) wp_unschedule_event($next, 'woosklad_stock_hook');
	}
	else {
		$time = get_option('woosklad_stock_time');
		if ($time<5) update_option('woosklad_stock_time',5);
		$time = get_option('woosklad_stock_time')*60;
		if ($schedules['stock']['interval'] != $time) {
			update_option('woosklad_old_stock_time',$time);
			apply_filters( 'cron_schedules', 'cron_stock_schedules' );
			$next = wp_next_scheduled('woosklad_stock_hook');
			if ($next) wp_unschedule_event($next, 'woosklad_stock_hook');
			wp_schedule_event(time()+$time, 'stock', 'woosklad_stock_hook');
		}	
	}
	if (!get_option('woosklad_order_time')){
		$next =  wp_next_scheduled('woosklad_order_hook');
		if ($next) wp_unschedule_event($next, 'woosklad_order_hook');
	}
	else {
		$time = get_option('woosklad_order_time'); 
		if ($time<5) update_option('woosklad_order_time',5);
		$time = get_option('woosklad_order_time')*60;
		if ($schedules['order']['interval'] != $time) {
			update_option('woosklad_old_order_time',$time);
			apply_filters( 'cron_schedules', 'cron_order_schedules' );
			$next =  wp_next_scheduled('woosklad_order_hook');
			if ($next) wp_unschedule_event($next, 'woosklad_order_hook');
			wp_schedule_event(time()+$time, 'order', 'woosklad_order_hook');
		}
	}
	if (!get_option('woosklad_good_time')){
		$next =  wp_next_scheduled('woosklad_good_hook');
		if ($next) wp_unschedule_event($next, 'woosklad_good_hook');
	}
	else {
		$time = get_option('woosklad_good_time'); 
		if ($time<5) update_option('woosklad_good_time',5);
		$time = get_option('woosklad_good_time')*60;
		if ($schedules['good']['interval'] != $time) {
			update_option('woosklad_old_good_time',$time);
			apply_filters( 'cron_schedules', 'cron_good_schedules' );
			$next =  wp_next_scheduled('woosklad_good_hook');
			if ($next) wp_unschedule_event($next, 'woosklad_good_hook');
			wp_schedule_event(time()+$time, 'good', 'woosklad_good_hook');
		}
	}
	
	
	if (get_option('woosklad_error')) {
?>
		<div id="error_message" class='error'><p><?php echo get_option('woosklad_error'); ?></p></div>
<?php 	update_option('woosklad_error','');
	}	
	else { ?>
		<div id="error_message" class='error hidden'></div>
<?php } ?>
		<div class="wrap">
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
						<td><input name="woosklad_login" type="text" value="<?php echo get_option( 'woosklad_login' ) ?>" /></td>
					</tr>
					<tr>
						<th><label for="woosklad_password">Пароль</label></th>
						<td><input name="woosklad_password" type="password" value="<?php echo get_option( 'woosklad_password' ) ?>" /></td>
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
<?php 									$agent_uuid = get_option('woosklad_agent_uuid');
									
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
								if ($stores)
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
		</div>
<?php 
//update_goods(array(92));
//update_orders(array(320));

//get_items();
//$p = get_priority_stores();
//echo "<pre>"; print_r($p); echo "</pre>";
//save_stock();
//update_variation(array(200),92);
//update_goods(array(305));
//get_variation();
//put_attribute_identity();
//wc_ms_attribute_identity();
//upload_orders();
//$result = put_update_type('CustomerOrder', $order);
//update_states_identity();
//download_stock();
//update_stock(0);
//
//consignment_uuid();
//get_consignment_uuid(0);
//get_goods_uuid();
//update_consignments();
//update_consignment_uuid();
//update_option('woosklad_good_uuids', "Consignment");
//get_moysklad_information("Consignment", "list", 0,5);
//get_orders();
}
?>