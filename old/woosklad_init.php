<?php 
	function save_stock($cron = false) {
		$stores = get_priority_stores(); $all = array();
		$data = array();
                foreach ($stores as $key=>$value) {
                        $info = get_stock($result, $value);
                        if ($info == 200) {
				$all = array_merge($all, json_decode($result)); 
				echo "<pre>"; print_r($all); echo "</pre>";
				$data[] = array('count'=>count($all), 'uuid'=>$value);
                                
                                //update_option('woosklad_error', current_time('mysql')." ".array_values($all));
			}
			else {
				update_option('woosklad_total_stock', 0);
				if ($info == 401) $data = array('result' => 'error', 'message' => 'Остатки не были загружены. Проверьте правильность подключения');
				else $data['message'] = 'Произошла непредвиденная ошибка. Код ошибки '.$info;
				
				if (!$cron) {
					echo json_encode($data);
					wp_die();
				}
				else {
					if ($info == 401) $message = current_time('mysql').' Во время загрузки остатков произошла ошибка. Проверьте правильность введенных логина и пароля.';
					else $message = current_time('mysql').'Во время загрузки остатков произошла непредвиденная ошибка. Код ошибки '.$info;
					update_option('woosklad_error', $message);
					exit;
				}
			}
		}
                
		update_option('woosklad_all_stock', $all);
		update_option('woosklad_count_on_store', $data);
		update_option('woosklad_index_store', 0);
		update_option('woosklad_total_stock', count($all));
		update_option('woosklad_updated_stock', 0);		
		$data = array('result'=>'OK');
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
		update_option('woosklad_all_stock', '');
		if (!$cron) { wp_die();}
	}
				
	function stock_progress() {
		if ( ! wp_verify_nonce($_POST['security'], 'stock-progress')) {
			die(json_encode(array('result' => 'error')));
			}
			if (get_option('woosklad_error')) {
				$data = array('result'=>'error', 'message'=>get_option('woosklad_error'));
				update_option('woosklad_error', '');
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
		update_option('woosklad_error','');
		echo json_encode(array('result'=>'OK'));
		wp_die();
	}
		
	function upload_orders($cron = false) {
		$start =  current_time('mysql');
		get_orders();
		if (!get_option('woosklad_error'))
		{
			update_option('woosklad_last_order_update', $start);
		}
		if (!$cron)
		{
			update_option('woosklad_error', '');
			wp_die();
		}
	}
		
	function order_progress() {
		if ( ! wp_verify_nonce( $_POST['security'], 'order-progress' ) ) {
			die(json_encode(array('result' => 'error')));
		}
		if (get_option('woosklad_error')) { $data = array('result'=>'error', 'message' => get_option('woosklad_error')); update_option('woosklad_error', '');}
		else $data = array('result'=>'OK', 'count'=>get_option('woosklad_updated_order'), 'total'=>get_option('woosklad_total_order'),'last_update'=>get_option('woosklad_last_order_update'));
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
		if (!get_option('woosklad_error'))
			update_option('woosklad_last_items_update', $start);
		if (!$cron) { update_option('woosklad_error', ''); wp_die();}
	}

	function goods_progress() {
		if (get_option('woosklad_error')) { $data = array('result'=>'error', 'message' => get_option('woosklad_error')); update_option('woosklad_error', '');}
		else $data = array('result'=>'OK', 'count'=>get_option('woosklad_updated_good'), 'total'=>get_option('woosklad_total_good'),	'last_update'=>get_option('woosklad_last_items_update'));
		echo json_encode($data);
		wp_die();
	}
	
	function first_synch() {
		$time = current_time('mysql');
		save_sklad_list('Warehouse', 'Склады', 'stores');
		if (!get_option('woosklad_error')) {
			sleep(1);
			save_sklad_list('Company', 'Контрагенты', 'agents');
			sleep(1);
			save_sklad_list('MyCompany', 'Компании', 'company');
			sleep(1);
			update_option('woosklad_sync_result', 'Выгрузка статусов');
			sleep(1);
			put_states_identity();
			update_option('woosklad_sync_result', 'Синхронизация атрибутов');
			put_attribute_identity();
			update_option('woosklad_sync_result', 'Синхронизация категорий');
			update_all_categories();
			update_option('woosklad_sync_result', 'Синхронизация завершена');
			update_option('woosklad_last_sync_update', $time);
		}
	}
		
	function start_synchronization() {
		update_option('woosklad_sync_result','Загрузка справочников');
		echo json_encode(array('result'=>'OK'));
		wp_die();
	}
		
	function sync_progress() {
		if (get_option('woosklad_error')) { $data = array('result'=>'error', 'message' => get_option('woosklad_error')); update_option('woosklad_error', '');}
		else $data = array('result'=>'OK', 'progress' => get_option('woosklad_sync_result'), 'last_update' => get_option('woosklad_last_sync_update'));
		echo json_encode($data);
		wp_die();
	}
	
	function reset_option() {
		update_option('woosklad_login','');
		update_option('woosklad_password','');
		update_user_meta( get_current_user_id(), '_woosklad_login', '');
		update_user_meta( get_current_user_id(), '_woosklad_password', '');
		update_option('woosklad_order_time','');
		update_option('woosklad_company_uuid','');
		update_option('woosklad_agent_uuid','');
		update_option('woosklad_stores','');
		update_option('woosklad_priority','');
	
		update_option('woosklad_last_order_update','');
		update_option('woosklad_stock_time','');
	
		update_option('woosklad_last_stock_update','');
		update_option('woosklad_error','');
		update_option('woosklad_good_time','');
		update_option('woosklad_last_sync_update','');
		update_option('woosklad_last_items_update','');
		$next = wp_next_scheduled('woosklad_stock_hook');
		wp_unschedule_event($next, 'woosklad_stock_hook');
		$next = wp_next_scheduled('woosklad_order_hook');
		wp_unschedule_event($next, 'woosklad_order_hook');
		$next = wp_next_scheduled('woosklad_good_hook');
		wp_unschedule_event($next, 'woosklad_good_hook');
		echo json_decode(array('result'=>'OK'));
		wp_die();
	}
	
	
?>