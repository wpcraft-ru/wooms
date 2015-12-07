<?php 
	function update_empty_stock() {
		global $wpdb;
		$wpdb->query("UPDATE $wpdb->postmeta SET meta_value=0 WHERE meta_key='_stock' 
			AND post_id in (SELECT * FROM ( SELECT post_id FROM $wpdb->postmeta where meta_key='_woosklad_new_stock' and meta_value='') m)");
		$wpdb->query("UPDATE $wpdb->postmeta SET meta_value='outofstock' WHERE meta_key='_stock_status' 
			AND post_id in (SELECT * FROM ( SELECT post_id FROM $wpdb->postmeta where meta_key='_woosklad_new_stock' and meta_value='') m)");
		$wpdb->query("UPDATE $wpdb->postmeta SET meta_value='' WHERE meta_key='_woosklad_new_stock'");
	}
	
	function update_one_stock_item($item, $store) {
		//$code = (!empty($item->productCode)) ? $item->productCode : $item->goodRef->code;
		$item_id = get_id_by_uuid($item->consignmentUuid); $product = new WC_Product($item_id);
		if ($item_id != -1 && $item_id) {
			$stock = $item->quantity - $item->inTransit;
			update_post_meta($item_id, '_woosklad_stock_'.$store, $stock);
			if (get_post_meta($item_id, '_woosklad_new_stock', true))
				$product->increase_stock($stock);
			else $product->set_stock($stock);
			update_post_meta($item_id, '_woosklad_new_stock', true);
			
			if (get_post_meta($item_id, '_stock',true) > 0) {
				update_post_meta($product->post->post_parent, '_stock_status','instock');
				update_post_meta($product->post->post_parent, '_woosklad_new_stock',true);
			}
		}
	}
	
	function update_stock ($start_pos) {
		$count = 75;
		global $wpdb;
		$total = get_option('woosklad_total_stock');
		
		if (($start_pos + $count) >= $total) {
			$end = $total; $suffix = "}}"; $search = "}}";
		}
		else {
			$end = $start_pos + $count;
			$suffix = "}"; $search = "i:$end";
		}
		$prefix = "a:".($end - $start_pos);
		
		$result = $wpdb->get_var($wpdb->prepare("
			SELECT MID(option_value,
			POSITION('i:%d' IN option_value),
			POSITION('%s' IN option_value) - POSITION('i:%d' IN option_value))
			FROM $wpdb->options
			WHERE option_name='woosklad_all_stock'
			",$start_pos, $search, $start_pos));
		$result = unserialize("$prefix:{".$result.$suffix);
		
		$data = get_option('woosklad_count_on_store');
		$index = get_option('woosklad_index_store');
		
		for ($i = $start_pos; $i<$end; $i++) {
			if ($i<$data[$index]['count']) $store = $data[$index]['uuid'];
			else $store = $data[++$index]['uuid'];
			update_one_stock_item($result[$i], $store);
			update_option('woosklad_updated_stock', $i+1);
		}
	}
	
	function get_orders_count() {
		global $wpdb;
		$time = get_option('woosklad_last_order_update');
		$query = $time ? " AND post_modified > '$time'":
			" AND post_status<>'wc-cancelled' AND post_status<>'wc-completed' 
			AND post_status<>'wc-failed'";
		$count = $wpdb->get_var("
				SELECT COUNT(*) FROM $wpdb->posts WHERE post_type='shop_order' 
				AND post_status<>'trash' AND post_status<>'auto-draft'".$query);
		return $count;
	}
	
	function get_orders() {
		global $wpdb; 
		$count = 30; $start = 0;
		$time = get_option('woosklad_last_order_update');

		$query = $time ? " AND post_modified > '$time'" :
			" AND post_status<>'wc-cancelled' AND post_status<>'wc-completed' 
			AND post_status<>'wc-failed'";

		do {
			$orders = $wpdb->get_col("
				SELECT ID FROM $wpdb->posts WHERE post_type='shop_order' 
				AND post_status<>'trash' AND post_status<>'auto-draft'".$query
				." LIMIT $start, $count");
			$start += $count;
			update_orders($orders);
		} while (count($orders) == $count);
	}
	
	function get_items_count() {
		global $wpdb;
		$time = get_option('woosklad_last_items_update');
		$query = $time ? " AND post_modified > '$time'": "";

		$count = $wpdb->get_var("
				SELECT COUNT(*) FROM $wpdb->posts WHERE post_type='product'
				AND post_status='publish'".$query);
		return $count;
	}
	
	function get_items() {
		global $wpdb;
		$count = 30; $start = 0;
		$time = get_option('woosklad_last_items_update');
		$query = $time ? " AND post_modified > '$time'" : "";
		do {
			$items = $wpdb->get_col("
				SELECT ID FROM $wpdb->posts WHERE post_type='product' 
				AND post_status='publish'".$query
				." LIMIT $start, $count");
			$start += $count;
			update_goods($items);
		} while (count($items) == $count);
	}

	function update_goods($goods, $start_pos=0) {
		$start_time = time();
		$begin = get_option('woosklad_updated_good');
		for ($i=$start_pos; $i<count($goods); $i++) {
			$good = create_good_xml($goods[$i]);
			//echo "<pre>"; print_r(simplexml_load_string($good)); echo "</pre>";
			if($good) {
				$result = put_update_type('Good', $good);
				//echo "<pre>Result: "; print_r($result); echo "</pre>";
				if ($result->uuid) {
					update_post_meta($goods[$i], '_woosklad_good_uuid', (string)$result->uuid);
					update_post_meta($goods[$i], '_woosklad_new_stock', '');
					//$cons_uuid = find_consignment((string)$result['updated'], (string)$result->uuid);
					//echo (string)$result['updated']."<br />";
					//if  ($cons_uuid) update_post_meta($goods[$i],'_woosklad_consignment_uuid', $cons_uuid);
					get_variation($goods[$i],(string)$result['updated']);
				}
				else {
					update_option('woosklad_error', current_time('mysql').' Невозможно выгрузить товары. Проверьте правильность введенных логина и пароля');
					exit;
				}
			}
			update_option('woosklad_updated_good', $i+1+$begin);
			if ((time() - $start_time)> 25) {
				update_goods($goods, ++$i);
				break;
			}
			
		}
	}
	
	function get_variation($id, $updated) {
		global $wpdb;
		$count = 20; $start = 0;
		$all_items = array();//echo "date: ".$updated."<br />";
		$time = get_option('woosklad_last_items_update');
		$xml = '<collection total="4">';
		do {
			$items = $wpdb->get_col("
				SELECT ID FROM $wpdb->posts WHERE post_type='product_variation' 
				AND post_status='publish' AND post_parent=$id LIMIT $start, $count");
			$start += $count;
			$all_items = array_merge($all_items, $items);
			$xml .= update_variation($items, $id);
			//echo "<pre>"; print_r($items); echo "</pre>";
		} while (count($items) == $count);
		$xml .= '</collection>';
		//echo "<pre>"; print_r(simplexml_load_string($xml)); echo "</pre>";
		if (count($all_items)) {
			$result = put_update_type('Feature/list/update', $xml);
			//echo "<pre>Result: "; print_r($result); echo "</pre>";
			if  ($result->id) {
				set_uuid_feature($id, $all_items, $result->id, $updated);
			}
			
		}
	}
	
	function update_variation($vars, $goodId, $start_pos=0) {
		$start_time = time(); $xml = '';
		for ($i=$start_pos; $i<count($vars); $i++) {
			$xml .= create_feature_xml($goodId, $vars[$i]);
		}
		return $xml;
	}
	
	function set_uuid_feature($id, $items, $ids, $updated) {
		
		for ($i=0; $i<count($items); $i++){
			update_post_meta($items[$i], '_woosklad_feature_uuid', (string)$ids[$i]);
			update_post_meta($items[$i], '_woosklad_good_uuid', get_post_meta($id, '_woosklad_good_uuid', true));
			update_post_meta($items[$i], '_woosklad_new_stock', '');
		}
		find_consignment($updated, get_post_meta($id, '_woosklad_good_uuid', true));
	}
	
	function update_orders($orders, $start_pos = 0) {
		$start_time = time(); 
		$begin = get_option('woosklad_updated_order');
		$stores = get_priority_stores();
		for ($i = $start_pos; $i<count($orders); $i++) {
			$order = new WC_Order($orders[$i]);
			$order_uuids = array();
			//echo "<pre>"; print_r($stores); echo "</pre>";
			$status = $order->get_status();
			$index = 1; $items_id = array();
			if ($stores) {
				foreach ($stores as $key=>$value) { //TODO криво работает с двумя складами
					$can_update = true;
					if ($status == 'completed' || $status == 'cancelled' || $status == 'failed' || $status == 'refunded') {
						$can_update = get_post_meta($orders[$i], '_woosklad_order_uuid_'.$value, true) ? true : false;
						$can_lasts = false;
					}
					if ($can_update) {
						$order = create_order_xml($orders[$i], $value, $items_id, $index);
						//echo "<pre>"; print_r(simplexml_load_string($orderx)); echo "</pre>";
						//echo "<pre>"; var_dump($items_id); echo "</pre>";

						//update_option('woosklad_error', current_time('mysql')." ".$order);

						if ($order) {
							$result = put_update_type('CustomerOrder', $order);

							if ($result->uuid) {
								update_post_meta($orders[$i], '_woosklad_order_uuid_'.$value, (string)$result->uuid);
								$order_uuids[] = (string)$result->uuid;
								update_post_meta($orders[$i], '_woosklad_order_name_'.$value, (string)$result['name']);
								update_items($result->customerOrderPosition, $orders[$i], $value, $items_id, 0);
							}
							else {
								update_option('woosklad_error', current_time('mysql')." Невозможно выгрузить заказы. Проверьте правильность введенных логина и пароля.");
								exit;
							}
						} else exit;

						$index++;
						if ($can_lasts) {
							$lasts = false;
							foreach ($items_id as $keyi=>$valuei) {
								if ($valuei['stock'] > 0) $lasts = true;
							}
							if (!$lasts) break;
						}
					}
				}
				
				$old_order_uuids = get_post_meta($orders[$i], '_woosklad_order_uuids', true);
				if ($old_order_uuids)
				foreach($old_order_uuids as $old) {
					if (!in_array($old, $order_uuids)) delete_type_uuid('CustomerOrder', $old);
				}
				update_post_meta($orders[$i], '_woosklad_order_uuids', $order_uuids);
				update_post_meta($orders[$i], '_woosklad_store_item_count', $items_id);
				update_post_meta($orders[$i], '_woosklad_old_status', $status);
				update_option('woosklad_updated_order', $begin+$i+1);
			}
			else update_option('woosklad_error', current_time('mysql').' Заказы не были загружены, так как не выбраны склады для списания остатков.');
			if ((time()- $start_time)> 25) {
				update_orders($orders, ++$i);
				break;
			}
		}

	}
	
	function update_items($items, $order_id, $store, &$items_id, $start_pos) {
		$start_time = time();
		global $wpdb;
		for ($i = $start_pos; $i<count($items); $i++) {
			$id = $wpdb->get_var($wpdb->prepare("
				SELECT good.post_id FROM $wpdb->postmeta good 
				JOIN $wpdb->postmeta cons 
				ON good.post_id=cons.post_id 
				WHERE good.meta_key = '_woosklad_good_uuid' AND good.meta_value = '%s'
				AND cons.meta_key = '_woosklad_consignment_uuid' AND cons.meta_value = '%s'", 
				(string)$items[$i]['goodUuid'], (string)$items[$i]['consignmentUuid']));
			if ($id) update_post_meta($id,'_woosklad_order_'.$order_id.'_good_uuid_'.$store, (string)$items[$i]->uuid);
			
			$old_items_id = get_post_meta($order_id, '_woosklad_store_item_count', true);
			$old_count = 0;
			if ($old_items_id && array_key_exists($id, $old_items_id))
				if (array_key_exists($store, $old_items_id[$id])) 
					$old_count = $old_items_id[$id][$store];
				
			//$count_items = $items_id[$id][$store];
			
			$order = new WC_Order($order_id); $add_count = $items_id[$id][$store];
			if ($order->get_status() == 'cancelled' || $order->get_status() == 'failed' || $order->get_status() == 'refunded') {
				$add_count = 0;
			}
			$old_status = get_post_meta($order_id, '_woosklad_old_status', true);
			if ($old_status == 'cancelled' || $old_status == 'failed' || $old_status == 'refunded') {
				$old_count = 0;
			}
			//echo "status ".$order->get_status()."<br />";
			//echo "count ".$items_id[$id][$store]."<br />"; 
			$prod = new WC_Product($id);
			if (array_key_exists($id, $items_id) && array_key_exists($store, $items_id[$id])) {
				update_post_meta($id, '_woosklad_stock_'.$store, (int)get_post_meta($id, '_woosklad_stock_'.$store, true)+$old_count-$add_count);
				$prod->increase_stock($old_count); $prod->reduce_stock($add_count);
				//WC_Product_Variable::sync_stock_status(92);
			}
			if ((time()- $start_time)> 25) {
				update_items($items, $order_id, $store, $items_id, ++$i);
				break;
			}
		}
	}
	
	function update_all_categories() {
		$categories = get_categories(array('hide_empty'=>0, 'taxonomy'=>'product_cat'));
		put_category_identity($categories);
		//echo "<pre> Result: "; print_r($categories); echo "</pre>";
	}
?>