<?php 
	/*function delete_uuids() {
		global $wpdb;
		$wpdb->query("
			DELETE FROM $wpdb->postmeta 
			WHERE meta_key = '_woosklad_good_uuid'
			OR meta_key = '_woosklad_consignment_uuid'
			");
	}*/

	/*function update_goods_uuid($result) {
		$shipment = get_option('woosklad_shipment_id');
		for ($i=0; $i<count($result); $i++) {
			$code = (string)$result->good[$i]->code;
			if ($code == $shipment) update_option('woosklad_shipment_uuid', (string)$result->good[$i]->uuid);
			else save_good_uuid((string)$result->good[$i]->uuid, $code);
		}
		echo count($result)."<br />";
	}*/

	/*function update_consignment_uuid() {
		$time = time(); 
		$result = get_option('woosklad_good_uuids');
		for ($i=0; $i<count($result); $i++) {
			$data = $result[$i];	
			//echo "<pre>"; print_r($data); echo "</pre>";
			save_consignment_uuid($data['good'], $data['uuid'], $data['code']);
		}
		//delete_option('woosklad_good_uuids');
		//echo count($result)." ".(time() - $time)."<br />";
	}*/
	
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
		echo $item_id."<br />"; 
		if ($item_id != -1 && $item_id) {
			$stock = $item->quantity - $item->inTransit;
			update_post_meta($item_id, '_woosklad_stock_'.$store, $stock);
			if (get_post_meta($item_id, '_woosklad_new_stock', true))
				//update_post_meta($item_id, '_stock', $stock + get_post_meta($item_id, '_stock',true));
				$product->increase_stock($stock);
			else //update_post_meta($item_id, '_stock', $stock);
				$product->set_stock($stock);
			update_post_meta($item_id, '_woosklad_new_stock', true);
			
			//
			echo "<pre>Result: "; print_r($product); echo "</pre>";
			if (get_post_meta($item_id, '_stock',true) > 0) {
				//update_post_meta($item_id, '_stock_status','instock');
				update_post_meta($product->post->post_parent, '_stock_status','instock');
				update_post_meta($product->post->post_parent, '_woosklad_new_stock',true);
			}
			//else update_post_meta($item_id, '_stock_status', 'outofstock');
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
		//echo "<pre>"; print_r ($result); echo "</pre>";
		
		$data = get_option('woosklad_count_on_store');
		$index = get_option('woosklad_index_store');
		
		//$cons_ex = get_option('woosklad_start_cons_download');
		for ($i = $start_pos; $i<$end; $i++) {
			//if ($i == $cons_ex) get_consignment_uuid($cons_ex);
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
			//echo "<pre>"; print_r($items); echo "</pre>";
		} while (count($items) == $count);
		
		foreach ($items as $id) get_variation($id);
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
					$cons_uuid = find_consignment((string)$result['updated'], (string)$result->uuid);
					//echo $cons_uuid;
					if  ($cons_uuid) update_post_meta($goods[$i],'_woosklad_consignment_uuid', $cons_uuid);
					get_variation($goods[$i]);
				}
				
			}
			if ((time() - $start_time)> 25) {
				update_goods($goods, ++$i);
				break;
			}
			update_option('woosklad_updated_good', $begin+$i+1);
		}
	}
	
	function get_variation($id) {
		global $wpdb;
		$count = 30; $start = 0;
		
		do {
			$items = $wpdb->get_col("
				SELECT ID FROM $wpdb->posts WHERE post_type='product_variation' 
				AND post_status='publish' AND post_parent=$id LIMIT $start, $count");
			$start += $count;
			update_variation($items, $id);
			//echo "<pre>"; print_r($items); echo "</pre>";
		} while (count($items) == $count);
	}
	
	function update_variation($vars, $goodId, $start_pos=0) {
		$start_time = time();
		$begin = get_option('woosklad_updated_good');
		for ($i=$start_pos; $i<count($vars); $i++) {
			$xml = create_feature_xml($goodId, $vars[$i]);
			//echo "<pre>"; print_r(simplexml_load_string($xml)); echo "</pre>";
			if ($xml) {
				$result = put_update_type('Feature', $xml);
				//echo "<pre>Result: "; print_r($result); echo "</pre>";
				if ($result->uuid) {
					update_post_meta($vars[$i], '_woosklad_feature_uuid', (string)$result->uuid);
					update_post_meta($vars[$i], '_woosklad_good_uuid', (string)$result['goodUuid']);
					update_post_meta($vars[$i], '_woosklad_new_stock', '');
					foreach($result->attribute as $attr) {
						update_post_meta($vars[$i], '_woosklad_attr_'.(string)$attr['metadataUuid'], (string)$attr->uuid);
					}
					$cons_uuid = find_consignment((string)$result['updated'], get_post_meta($goodId, '_woosklad_good_uuid', true));
					if  ($cons_uuid) update_post_meta($vars[$i],'_woosklad_consignment_uuid', $cons_uuid);
				}
			}
			if ((time() - $start_time)> 25) {
				update_variation($vars, $goodId, ++$i);
				break;
			}
		}
	}
	
	function restart_update_orders($orders, $start_pos) {
		update_orders($orders, $start_pos);
	}
	
	function update_orders($orders, $start_pos = 0) {
		$start_time = time(); 
		$begin = get_option('woosklad_updated_order');
		$stores = get_priority_stores();
		for ($i = $start_pos; $i<count($orders); $i++) {
			$order = new WC_Order($orders[$i]);
			$order_uuids = array();
			echo "<pre>"; print_r($stores); echo "</pre>";
			$satus = $order->get_status();
			$index = 1; $items_id = array();
			foreach ($stores as $key=>$value) {
				
				$order = create_order_xml($orders[$i], $value, $items_id, $index); 
				echo "<pre>"; print_r(simplexml_load_string($order)); echo "</pre>";
				echo "<pre>"; var_dump($items_id); echo "</pre>";
				if ($order) {
					$result = put_update_type('CustomerOrder', $order);
					echo "<pre> Result: "; print_r($result); echo "</pre>";
					if ($result->uuid) {
						update_post_meta($orders[$i], '_woosklad_order_uuid_'.$value, (string)$result->uuid);
						$order_uuids[] = (string)$result->uuid;
						update_post_meta($orders[$i], '_woosklad_order_name_'.$value, (string)$result['name']);
						update_items($result->customerOrderPosition, $orders[$i], $value, $items_id, 0);
					}
				}
				$index++; $lasts = false;
				foreach ($items_id as $key=>$value) {
					if ($value['stock'] > 0) $lasts = true;
				}
				if (!$lasts) break;
			}
			$old_order_uuids = get_post_meta($orders[$i], '_woosklad_order_uuids', true);
			if ($old_order_uuids)
				foreach($old_order_uuids as $old) {
					if (!in_array($old, $order_uuids)) delete_type_uuid('CustomerOrder', $old);
				}
			update_post_meta($orders[$i], '_woosklad_order_uuids', $order_uuids);
			update_post_meta($orders[$i], '_woosklad_store_item_count', $items_id);
			update_option('woosklad_updated_order', $begin+$i+1);
			if ((time()- $start_time)> 25) {
				restart_update_orders($orders, ++$i);
				break;
			}
		}
	}
	
	function restart_update_items($items, $order_id, $start_pos) {
		update_items($items, $order_id, $start_pos);
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
			if (array_key_exists($id, $old_items_id))
				if (array_key_exists($store, $old_items_id[$id])) 
					$old_count = $old_items_id[$id][$store];
				
			//$count_items = $items_id[$id][$store];
			
			$order = new WC_Order($order_id);
			if ($order->get_status() == 'cancelled' || $order->get_status() == 'failed') $items_id[$id][$store] = 0;
			//echo "status ".$order->get_status()."<br />";
			//echo "count ".$items_id[$id][$store]."<br />"; 
			
			if (array_key_exists($id, $items_id) && array_key_exists($store, $items_id[$id])) 
				update_post_meta($id, '_woosklad_stock_'.$store, (int)get_post_meta($id, '_woosklad_stock_'.$store, true)+$old_count-$items_id[$id][$store]);
			if ((time()- $start_time)> 25) {
				update_items($items, $order_id, $store, $items_id, ++$i);
				break;
			}
		}
	}
	
?>