<?php
	function get_sklad_list($type, $name) {
		$list = get_moysklad_information($type);
		if ($list) {
			$result = object_to_uuid_name($list);
			return $result;
		}
		else {
			update_option('woosklad_error',current_time('mysql').' Невозможно загрузить список "'.$name.'". Возможно Вы указали неверные данные для доступа к МойСклад. 
			Пожалуйста, проверьте их правильность, и попробуйте снова.');
			return -1;
		}
	}

	function save_sklad_list($type, $name, $option) {
		$list = get_sklad_list($type, $name);
		if ($list)
			update_option('woosklad_save_'.$option, $list);
		else {
			update_option('woosklad_error',current_time('mysql').' Запрос списка "'.$name.'" вернул пустой результат. Проверьте наличие данных в системе "Мой Склад".');
		}
	}

	function put_states_identity() {
		$workflows = get_moysklad_information('Workflow');
		if ($workflows) {
			foreach ($workflows as $workflow) {
				if ($workflow['name']== 'CustomerOrder') {
					$customerOrder = $workflow;
					break;
				}
			}
			$states = wc_ms_states_identity($customerOrder->state);
			$xml = create_workflow_xml($customerOrder->uuid, $states);
		}
		else {
			$states = wc_ms_states_identity('');
			$xml = create_workflow_xml('', $states);
		}
		$result = put_update_type('Workflow', $xml);
		//echo "<pre>Result: "; print_r($result); echo "</pre>";
		if ($result->state) {
			wc_ms_states_identity($result->state);
		}
	}
	
	function put_attribute_identity() {
		$metadata = get_moysklad_information('EmbeddedEntityMetadata');
		if ($metadata) {
			foreach ($metadata as $meta) {
				if ($meta->code== 'GoodFolder') {
					$attribute = $meta;
					break;
				}
			}
			$attr = wc_ms_attribute_identity($attribute->attributeMetadata);
		}
	}
	
	function parent_sort($a,$b) {
		if ($a->parent == $b->parent) return 0;
		return ($a->parent < $b->parent) ? -1 : 1;
	}
	
	function put_category_identity($categories) {
		usort($categories, "parent_sort");
		//echo "<pre>"; print_r($categories); echo "</pre>";
		foreach ($categories as $cat) {
			//if (!get_option('woosklad_category_'.$cat->term_id.'_uuid')) {
				$parent = get_option('woosklad_category_'.$cat->parent.'_uuid');
				$xml = create_good_folder_xml($cat->name, $cat->term_id, $parent);
				$result = put_update_type('GoodFolder', $xml);
				if ($result->uuid) {
					update_option('woosklad_category_'.$cat->term_id.'_uuid',(string)$result->uuid);
				}
			//	echo "<pre>Result: "; print_r($result); echo "</pre>";
			//}
		}
	}
	
	function find_consignment($date, $uuid) {
		global $wpdb;
		//echo "date: ".$date;
		$date = substr(str_replace(array('-',':','T'), '', $date),0,14);
		$good_id = $wpdb->get_var($wpdb->prepare("
			SELECT post_id FROM $wpdb->postmeta join $wpdb->posts on post_id=ID
			WHERE meta_key = '_woosklad_good_uuid' AND meta_value='%s' AND post_type='product' ", $uuid));
		$result = get_moysklad_information('Consignment', 'list?filter=updated%3E'.$date);
		//echo "<pre>Result: "; print_r($result); echo "</pre>";

		for ($i=0; $i<count($result); $i++) {
			//echo ((string)$result->consignment[$i]->uuid)." ".((string)$result->consignment[$i]['goodUuid'])."<br />";
			$good_uuid = (string)$result->consignment[$i]['goodUuid'];
			if ($result->consignment[$i]->uuid && $good_uuid == $uuid) {
				//echo $good_id."<br />";
			 	if (!$result->consignment[$i]->feature->code && $good_id) { update_post_meta($good_id, '_woosklad_consignment_uuid', (string)$result->consignment[$i]->uuid);} 
				if ($result->consignment[$i]->feature->code) {
					$id = $wpdb->get_var($wpdb->prepare("
						SELECT post_id FROM $wpdb->postmeta 
						WHERE meta_key = '_woosklad_feature_uuid' AND meta_value='%s'", (string)$result->consignment[$i]->feature->uuid));
					//echo "<pre>Result: "; print_r($result->consignment[$i]->feature->attribute); echo "</pre>";
					if ($id) {
						update_post_meta($id, '_woosklad_consignment_uuid', (string)$result->consignment[$i]->uuid);
						foreach ($result->consignment[$i]->feature->attribute as $attr){
								update_post_meta($id, '_woosklad_attr_'.(string)$attr['metadataUuid'], (string)$attr->uuid);
						}
					}
				}
			}
		}
	}
?>