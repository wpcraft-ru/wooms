<?php 
	function create_workflow_xml($uuid, $states) {
		$xml = '<workflow name="CustomerOrder">';
		if ($uuid) $xml .= '<uuid>'.$uuid.'</uuid>';
		$xml .= '<code>CustomerOrder</code>';
		foreach ($states as $key=>$value) {
			$xml .= '<state name="'.$key.'">';
			if ($value) $xml .= "<uuid>$value</uuid>";
			$xml .= '</state>';	
		}
		$xml .= '</workflow>';
		//print_r($xml);
		return $xml;
	}
	
	function create_good_folder_xml($name, $id, $parent='') {
		$xml = '<goodFolder archived="false" productCode="" name="'.$name.'" ';
		if ($parent) $xml .= 'parentUuid = "'.$parent.'" ';
		$xml .= '>';
		if (get_option('woosklad_category_'.$id.'_uuid')) $xml .= '<uuid>'.get_option('woosklad_category_'.$id.'_uuid').'</uuid>';
		$xml .= '<code>Категория '.$id.'</code>';
		$xml .= '</goodFolder>';
		//echo "<pre>"; print_r(simplexml_load_string($xml)); echo "</pre>";
		return $xml;
	}
	
	function get_parent_category($categories) {
		$parents = array();
		foreach ($categories as $cat) {
			if (!in_array($cat->parent, $parents)) $parents[] = $cat->parent;
		}
		$i = 0; 
		while (!in_array($categories[$i]->term_id, $parents)) return $categories[$i++]->term_id;
		return $categories[count($categories)-1]->term_id;
	}
	
	function create_good_xml($id) {
		$product = new WC_Product($id);
		$weight = get_post_meta($id, '_weight', true); $weight = $weight ? $weight : "0.0";
		
		$buyPrice = get_post_meta($id, '_max_variation_regular_price', true) ? get_post_meta($id, '_max_variation_regular_price', true) :
			get_post_meta($id, '_regular_price', true);
		$buyPrice = $buyPrice ? $buyPrice*100 : "0.0";
		
		$salePrice = get_post_meta($id, '_max_variation_price', true) ? get_post_meta($id, '_max_variation_price', true) : 
			get_post_meta($id, '_price', true);
		$salePrice = $salePrice ? $salePrice*100 : "0.0";
		
		$uuid = get_post_meta($id, '_woosklad_good_uuid', true);
		//echo $uuid."<br />";
		$categories = get_the_terms($id, 'product_cat');
		put_category_identity($categories);
		$parent = get_parent_category($categories);
		$parentUuid = get_option('woosklad_category_'.$parent.'_uuid') ? 
			'parentUuid="'.get_option('woosklad_category_'.$parent.'_uuid').'"' : '';
		
		$xml = '<good isSerialTrackable="false" weight="'.$weight.'" salePrice="'.$salePrice.'" 
			buyPrice="'.$buyPrice.'" name="'.$product->get_title().'" 
			productCode="'.get_post_meta($id, '_sku', true).'" '.$parentUuid.'>';
		$xml .= '<code>'.get_post_meta($id, '_sku', true).'</code>';
		if ($uuid) $xml .= '<uuid>'.$uuid.'</uuid>';
		$xml .= '</good>';
		return $xml;
	}
	
	function create_feature_xml($goodId, $varId) {
		$goodUuid = get_post_meta($goodId, '_woosklad_good_uuid', true);
		$xml = '<feature goodUuid="'.$goodUuid.'">';
		
		$uuid = get_post_meta($varId, '_woosklad_feature_uuid', true);
		if ($uuid) $xml .= '<uuid>'.$uuid.'</uuid>';
		
		$code = get_post_meta($varId, '_sku', true) ? get_post_meta($varId, '_sku', true) : get_post_meta($goodId, '_sku', true)."_".$varId;
		$xml .= '<code>'.$code.'</code>';
		
		$product = new WC_Product($goodId);
		$attributes = $product->get_attributes();
		foreach ($attributes as $key=>$value) {
			$attr = 'pa_'===substr($value['name'],0,3) ? substr($value['name'],3) : $value['name']; 
			//if (!get_option("woosklad_attribute_$attr")) put_attribute_identity();
			$attrUuid = get_option("woosklad_attribute_$attr");
			if ($attrUuid) {
				$attrSlug = get_post_meta($varId,'attribute_'.$key, true);
				$attrValue = get_term_by('slug', $attrSlug, $value['name']);
				//echo "attr ".$value['name']." uuid ".$attrUuid." val ".$attrValue->name."<br/>"; 
				
				$uuid = get_post_meta($varId, '_woosklad_attr_'.$attrUuid, true); //если обновляем
				
				$xml .= '<attribute metadataUuid="'.$attrUuid.'" valueString="'.$attrValue->name.'">';
				if ($uuid) $xml .= '<uuid>'.$uuid.'</uuid>';
				$xml .= '</attribute>';
			} else {
				update_option('woosklad_error', current_time('mysql').' Невозможно выгрузить вариации. Проверьте все ли атрибуты добавлены как характеристики на Мой Склад и синхрониируйте информацию.');
				exit;
			}
		}
		$xml .= '</feature>';
		return $xml;
	}
	
	function create_description_xml($order) {
		if ($order) {
			$description = 'ID в интернет-магазине: '.$order->id."\n";
			$description .= 'Дата заказа: '.$order->order_date."\n";
			$description .= 'Имя: '.$order->shipping_first_name.' '.$order->shipping_last_name."\n";
			$description .= 'Email: '.$order->billing_email."\n";
			$description .= 'Телефон: '.$order->billing_phone."\n";
			$description .= 'Детали оплаты: '.$order->billing_state.", ".$order->billing_postcode.", ".$order->billing_city.", ".$order->billing_address_1."\n";
			$description .= 'Детали доставки: '.$order->shipping_state.", ".$order->shipping_postcode.", ".$order->shipping_city.", ".$order->shipping_address_1."\n";
			$description .= 'Способ оплаты: '.$order->payment_method_title."\n";
			$description .= 'Тип доставки: ' . $order->get_shipping_method(). "\n";
			$description .= 'Заметка от клиента: '.$order->customer_note;
		}
		return $description;
	}
	
	function create_order_position_xml($item, $order_id, $status, $store, &$items_id) {
		$item_id = $item['variation_id'] ? $item['variation_id'] : $item['product_id'];
		$old_items_id = get_post_meta($order_id, '_woosklad_store_item_count', true);
                
		$old_count = 0;
                if (array_key_exists($item_id, $old_items_id))
			if (array_key_exists($store, $old_items_id[$item_id])) 
				$old_count = $old_items_id[$item_id][$store];
			//echo "old ".$old_count."<br />";	
			
			if ($old_items_id && ($status == 'cancelled' || $status == 'failed' || $status == 'completed' || $status == 'refunded')) {
				$items_id[$item_id][$store]=$old_count;
				$items_id[$item_id]['stock']=$old_items_id[$item_id]['stock'];
				$quantity = $old_count;
			}
			else {			
				$count_store = (int)get_post_meta($item_id, '_woosklad_stock_'.$store, true) + $old_count;
			//echo "count_store ".$count_store."<br />";	
				//$quantity = $count_store < $items_id[$item_id]['stock'] ? $count_store : $items_id[$item_id]['stock'];
                                $quantity = $items_id[$item_id]['stock'];
				$items_id[$item_id][$store] = $quantity;
				$items_id[$item_id]['stock'] -=  $quantity;
			}

                        //update_option('woosklad_error', current_time('mysql')." ".$old_count);

                        
		if ($quantity > 0) {
                        $xml = '<customerOrderPosition ';
			$xml .= 'discount="'.number_format((($item['line_subtotal']-$item['line_total'])/($item['line_subtotal']/100)),1,'.','').'" ';
			$xml .= 'quantity="'.number_format($quantity, 1, '.', '').'" ';
			if (!get_post_meta($item_id, '_woosklad_good_uuid', true)) {
				
				return 0;
			}
			if (get_post_meta($item_id, '_woosklad_good_uuid', true)) {
				$xml .= 'goodUuid="'.get_post_meta($item_id, '_woosklad_good_uuid', true).'" ';
				if (get_post_meta($item_id, '_woosklad_consignment_uuid', true)) {
					$xml .= 'consignmentUuid="'.get_post_meta($item_id, '_woosklad_consignment_uuid', true).'" ';
				}
			}
			$xml .= '>';
			
			if (get_post_meta($item_id, "_woosklad_order_".$order_id."_uuid_".$store)) {
				$xml .= '<uuid>'.get_post_meta($item_id, "_woosklad_order_".$order_id."_uuid_".$store,true).'</uuid>';
			}
			
			$xml .= '<basePrice sum="'.number_format((100*$item['line_subtotal']/$item['qty']),1,'.','').'" sumInCurrency="'.number_format((100*$item['line_subtotal']/$item['qty']),1,'.','').'" />';
			$xml .= '<price sum="'.number_format((100*$item['line_total']/$item['qty']),1,'.','').'" sumInCurrency="'.number_format((100*$item['line_total']/$item['qty']),1,'.','').'" />';
			
			if ($status != 'cancelled' && $status != 'failed' && $status != 'refunded' && $status != 'processing')
				$xml .= '<reserve>'.number_format($quantity,1,'.','').'</reserve>';
			$xml .= '</customerOrderPosition>';
			//echo "<pre>"; print_r(simplexml_load_string($xml)); echo "</pre>";
		}
		return $xml;
	}
	
	function create_order_xml($id, $storeUuid, &$items_id, $index) {
		$order = new WC_Order($id);
		//echo "<pre>"; print_r($order); echo "</pre>";
		$sourceUuid = get_option('woosklad_agent_uuid');
		if (!$sourceUuid) {
			$sourceUuid = get_option('woosklad_save_agent');
			$sourceUuid = $sourceUuid[0];
		}
		$targetUuid = get_option('woosklad_company_uuid');
		if (!$targetUuid) {
			$targetUuid = get_option('woosklad_save_company');
			$targetUuid = $targetUuid[0];
		}
		$stateUuid = get_option('woosklad_states_'.$order->get_status().'_uuid');

		$name = get_post_meta($order->id, '_woosklad_order_name', true);
		if (!$name) $name = "wc".$order->id."_".$index;
		$xml = '<?xml version="1.0" encoding="UTF-8"?>';
		$xml .= '<customerOrder vatIncluded="false" ';
		$xml .= 'applicable="true" payerVat="false" ';
		if ($stateUuid) $xml .= 'stateUuid="'.$stateUuid.'" ';
		if ($sourceUuid) $xml .= 'sourceAgentUuid="'.$sourceUuid.'" ';
		else {
			update_option('woosklad_error', current_time('mysql').' При выгрузке заказа произошла ошибка.
				Проверьте существование организаций и контрагентов в Мой Склад и синхронизируйте информацию.');
			return 0;
		}
		if ($targetUuid) $xml .= 'targetAgentUuid="'.$targetUuid.'" ';
		else {
			update_option('woosklad_error', current_time('mysql').' При выгрузке заказа произошла ошибка.
				Проверьте существование контрагентов и организаций в Мой Склад и синхронизируйте информацию.');
			return 0;
		}

		if ($storeUuid) $xml .= 'sourceStoreUuid="'.$storeUuid.'" ';
		if ($name) $xml .= 'name="'.$name.'"';
		$xml .= '>';

		if (get_post_meta($order->id, '_woosklad_order_uuid_'.$storeUuid,true))
			$xml .= '<uuid>'.get_post_meta($order->id, '_woosklad_order_uuid_'.$storeUuid,true).'</uuid>';

		$description = create_description_xml($order);
		if ($description) $xml .= "<description>$description</description>";
		$items = $order->get_items(); $item_ch = false;
		foreach ($items as $item) {
			$item_id = $item['variation_id'] ? $item['variation_id'] : $item['product_id'];
			if (!array_key_exists($item_id, $items_id)) $items_id[$item_id]['stock'] = (int)$item['qty'];
			$item_xml = create_order_position_xml($item, $order->id, $order->get_status(), $storeUuid, $items_id);
			//echo "<pre>"; print_r(simplexml_load_string($item_xml)); echo "</pre>";

			if ($item_xml) { $xml .= $item_xml; $item_ch = true; }
		}
		$xml .= '</customerOrder>';
		//echo "<pre>"; print_r(simplexml_load_string($xml)); echo "</pre>";

		if ($item_ch) return $xml; // WTF?
		return $xml;
	}
?>