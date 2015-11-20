<?php
	function get_sklad_list($type, $name) {
		$list = get_moysklad_information($type);
		if ($list) {
			$result = object_to_uuid_name($list);
			return $result;
		}
		else {
			update_option('woosklad_error','Невозможно загрузить список "'.$name.'". Возможно Вы указали неверные данные для доступа к МойСклад. 
			Пожалуйста, проверьте их правильность, и попробуйте снова.<br />');
			return -1;
		}
	}

	function save_sklad_list($type, $name, $option) {
		$list = get_sklad_list($type, $name);
		if ($list)
			update_option('woosklad_save_'.$option, $list);
		else {
			update_option('woosklad_error','Запрос списка "'.$name.'" вернул пустой результат. Проверьте наличие данных в системе "Мой Склад"<br />');
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
		echo "<pre>"; print_r($categories); echo "</pre>";
		foreach ($categories as $cat) {
			if (!get_option('woosklad_category_'.$cat->term_id.'_uuid')) {
				$parent = get_option('woosklad_category_'.$cat->parent.'_uuid');
				$xml = create_good_folder_xml($cat->name, $cat->term_id, $parent);
				$result = put_update_type('GoodFolder', $xml);
				if ($result->uuid) {
					update_option('woosklad_category_'.$cat->term_id.'_uuid',(string)$result->uuid);
				}
				echo "<pre>Result: "; print_r($result); echo "</pre>";
			}
		}
	}
	
	function get_goods_uuid() {
		$start = 0; $count = 750;
		do {
			$result = get_moysklad_information('Good','list',$start, $count);
			$start += $count;
			//echo "<pre>"; print_r($result); echo "</pre>";
			update_goods_uuid($result);
			
		} while (count($result)==$count);
	}
	
	function get_consignment_uuid($start = 0, $count = 400) {
		//do {
		$result = download_consignments($start, $count);
		update_consignment_uuid();
		$start += $count;
		//} while ($result == $count);
		update_option('woosklad_start_cons_download', $start);
		return $result;
	}
	
	function download_consignments($start, $count) {
		$time = time();
		$data = array();
		$result = get_moysklad_information('Consignment','list', $start, $count);
		//update_consignment_uuid($result);
		for ($i=0; $i<count($result); $i++) {
			
			$code = $result->consignment[$i]->feature->code ? (string)$result->consignment[$i]->feature->code : '';	
			$uuid = (string)$result->consignment[$i]->uuid;
			$goodUuid = (string)$result->consignment[$i]['goodUuid'];
			$data[] = array('code' => $code, 'uuid' => $uuid, 'good' => $goodUuid);
			//save_consignment_uuid($goodUuid, $uuid, $code);
		}
		update_option('woosklad_good_uuids', $data);
		//echo count($result)." ".(time() - $time)."<br />";
		return count($result);
	}

	function find_consignment($date, $uuid) {
		$date = substr(str_replace(array('-',':','T'), '', $date),0,14);
		$result = get_moysklad_information('Consignment', 'list?filter=updated%3E'.$date);
		for ($i=0; $i<count($result); $i++) {
			if ($result->consignment[$i]->uuid && $result->consignment[$i]['goodUuid']==$uuid) return (string)$result->consignment[$i]->uuid;
		}
		return 0;
	}
?>