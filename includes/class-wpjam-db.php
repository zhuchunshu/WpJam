<?php
class WPJAM_DB{
	private $table;
	private $args;
	private $query_vars;
	private $where		= [];
	private $meta_query	= false;

	public function __construct($table, array $args = []){
		$args = wp_parse_args($args, [
			'primary_key'		=> 'id',
			'meta_type'			=> '',
			'cache'				=> true,
			'cache_key'			=> '',
			'cache_prefix'		=> '',
			'cache_group'		=> $table,
			'cache_time'		=> DAY_IN_SECONDS,
			'field_types'		=> [],
			'searchable_fields'	=> [],
			'filterable_fields'	=> [],
			'lazyload_callback'	=> null
		]);

		if($args['cache']){
			$args['cache_key']	= $args['cache_key'] ?: $args['primary_key'];
		}

		$this->table	= $table;
		$this->args		= $args;

		$this->clear();
	}

	public function __get($key){
		return $this->args[$key] ?? null;
	}

	public function clear(){
		$this->query_vars	= [
			'limit'			=> 0,
			'offset'		=> 0,
			'orderby'		=> $this->primary_key,
			'order'			=> 'DESC',
			'groupby'		=> '',
			'having'		=> '',
			'search_term'	=> null
		];

		$this->where	= [];
	}

	private function get_query_var($var, $default=null){
		return $this->query_vars[$var] ??	$default;
	}

	private function set_query_var($var, $value){
		$this->query_vars[$var]	= $value;
		return $this;
	}

	public function get_table(){
		return $this->table;
	}

	public function get_primary_key(){
		return $this->primary_key;
	}

	public function get_meta_type(){
		return $this->meta_type;
	}

	public function get_searchable_fields(){
		return $this->searchable_fields;
	}

	public function get_filterable_fields(){
		return $this->filterable_fields;
	}

	public function get_cache_group(){
		return $this->cache_group;
	}

	public function get_cache_prefix(){
		return $this->cache_prefix;
	}

	public function get_last_changed(){
		return wp_cache_get_last_changed($this->cache_group);
	}

	public function set_last_changed(){
		wp_cache_set('last_changed', microtime(), $this->cache_group);
	}

	public function get_cache_key($key){
		if($this->cache_key != $this->primary_key){
			$key	= $this->cache_key.':'.$key;
		}

		return $this->get_primary_cache_key($key);
	}

	public function get_primary_cache_key($id){
		return $this->cache_prefix ? $this->cache_prefix.':'.$id : $id;
	}

	public function set_meta_query($meta_query){
		$this->meta_query	= $meta_query;
	}

	public function cache_get($key){
		if($this->cache){
			if(!is_scalar($key)){
				trigger_error(var_export($key, true));
				return false;
			}

			return wp_cache_get($this->get_cache_key($key), $this->cache_group);
		}else{
			return false;
		}
	}

	public function cache_get_by_primary_key($id){
		if($this->cache){
			if(!is_scalar($id)){
				trigger_error(var_export($id, true));
				return false;
			}

			return wp_cache_get($this->get_primary_cache_key($id), $this->cache_group);
		}else{
			return false;
		}
	}

	public function cache_set($key, $data, $cache_time=0){
		if($this->cache){
			$cache_time	= $cache_time ?: $this->cache_time;
			wp_cache_set($this->get_cache_key($key), $data, $this->cache_group, $cache_time);
		}
	}

	public function cache_add($key, $data, $cache_time=0){
		if($this->cache){
			$cache_time	= $cache_time ?: $this->cache_time;
			wp_cache_add($this->get_cache_key($key), $data, $this->cache_group, $cache_time);
		}
	}

	public function cache_set_by_primary_key($id, $data, $cache_time=0){
		if($this->cache){
			$cache_time	= $cache_time ?: $this->cache_time;
			wp_cache_set($this->get_primary_cache_key($id), $data, $this->cache_group, $cache_time);
		}
	}

	public function cache_delete($key){
		if($this->cache){
			wp_cache_delete($this->get_cache_key($key), $this->cache_group);
		}
	}

	public function cache_delete_by_primary_key($id){
		if($this->cache){
			wp_cache_delete($this->get_primary_cache_key($id), $this->cache_group);
		}
	}

	public function cache_delete_multi($keys){
		if($this->cache){
			foreach($keys as $key){
				$this->cache_delete($key);
			}
		}
	}

	public function cache_delete_multi_by_primary_key($ids){
		if($this->cache){
			foreach($ids as $id){
				$this->cache_delete_by_primary_key($id);
			}
		}
	}

	public function cache_delete_by_conditions($conditions){
		if($this->cache){
			if(empty($conditions)){
				return;
			}

			if(is_array($conditions)){
				$conditions	= array_filter($conditions, function($condition){
					return $condition;
				});

				if(empty($conditions)){
					return;
				}

				$conditions		= ' WHERE ' . implode(' OR ', $conditions);
			}

			if($this->cache_key != $this->primary_key){
				if($results = $GLOBALS['wpdb']->get_results("SELECT {$this->primary_key}, {$this->cache_key} FROM `{$this->table}` {$conditions}", ARRAY_A)){
					// $primary_key	= $this->primary_key;
					// $cache_key		= $this->cache_key;

					foreach($results as $result){
						$this->cache_delete_by_primary_key($result[$this->primary_key]);
						$this->cache_delete($result[$this->cache_key]);
					}
					// $this->cache_delete_multi_by_primary_key(array_column($results, $this->primary_key));
					// $this->cache_delete_multi(array_column($results, $this->cache_key));
				}
			}else{
				if($ids = $GLOBALS['wpdb']->get_col("SELECT {$this->primary_key} FROM `{$this->table}` {$conditions}")){
					// $this->cache_delete_multi_by_primary_key($ids, $this->cache_group);
					foreach($ids as $id){
						$this->cache_delete_by_primary_key($id);
					}
				}
			}
		}
	}

	public function find_one_by($field, $value){
		$field_type	= $this->process_field_formats($field);

		return $GLOBALS['wpdb']->get_row($GLOBALS['wpdb']->prepare("SELECT * FROM `{$this->table}` WHERE `{$field}` = {$field_type}", $value), ARRAY_A);
	}

	public function find_by($field, $value, $order='ASC'){
		$field_type	= $this->process_field_formats($field);

		return $GLOBALS['wpdb']->get_results($GLOBALS['wpdb']->prepare("SELECT * FROM `{$this->table}` WHERE `{$field}` = {$field_type} ORDER BY `{$this->primary_key}` {$order}", $value), ARRAY_A);
	}

	public function find_one($id){
		$result = $this->cache_get_by_primary_key($id);
		if($result === false){
			$result = $this->find_one_by($this->primary_key, $id);
			if($result){
				$this->cache_set_by_primary_key($id, $result);
			}else{
				$this->cache_set_by_primary_key($id, $result, MINUTE_IN_SECONDS);
			}
		}

		return $result;
	}

	public function get($id){
		return $this->find_one($id);
	}

	public function get_by($field, $value, $order='ASC'){
		if($field == $this->cache_key){
			$result = $this->cache_get($value);

			if($result === false){
				$result = $this->find_by($field, $value, $order);
				if($result){
					$this->cache_set($value, $result);
				}else{
					$this->cache_set($value, $result, MINUTE_IN_SECONDS);
				}
			}

			return $result;
		}else{
			return $this->find_by($field, $value, $order);
		}
	}

	public function get_values_by($ids, $field){
		$result = $GLOBALS['wpdb']->get_results($this->where_in($field, $ids)->get_sql(), ARRAY_A);

		if($result){
			if($field == $this->primary_key){
				return array_combine(array_column($result, $this->primary_key), $result);
			}else{
				$return = [];
				foreach($ids as $id){
					$return[$id]	= array_values(wp_list_filter($result, [$field => $id]));
				}
				return $return;
			}
		}else{
			return [];
		}
	}

	public function update_caches($ids, $primary=false){
		if(!$this->cache){
			return [];
		}

		if($ids && is_array($ids)){
			$ids = array_filter($ids);
			$ids = array_unique($ids);
		}else{
			return [];
		}

		if(function_exists('wp_cache_get_multiple')){
			$cache_ids	= [];

			foreach($ids as $id){
				if($primary){
					$cache_key	= $this->get_primary_cache_key($id);
				}else{
					$cache_key	= $this->get_cache_key($id);
				}

				$cache_ids[$cache_key]	= $id;
			}

			$cache_keys	= array_keys($cache_ids);
			$caches		= wp_cache_get_multiple($cache_keys, $this->cache_group);

			$non_cached_ids	= [];
			$cache_values	= [];

			foreach($caches as $cache_key => $cache_value){
				$id	= $cache_ids[$cache_key];
				if($cache_value === false){
					$non_cached_ids[]	= $id;
				}else{
					$cache_values[$id]	= $cache_value;
				}
			}

			unset($cache_keys);
			unset($cache_ids);

			if(empty($non_cached_ids)){
				return $cache_values;
			}
		}else{
			$non_cached_ids = $cache_values = [];

			foreach($ids as $id){
				if($primary){
					$data	= $this->cache_get_by_primary_key($id);
				}else{
					$data	= $this->cache_get($id);
				}

				if(false === $data){
					$non_cached_ids[]	= $id;
				}else{
					$cache_values[$id]	= $data;
				}
			}

			if(empty($non_cached_ids)){
				return $cache_values;
			}
		}

		if($primary){
			$datas	= self::get_values_by($non_cached_ids, $this->primary_key);
		}else{
			$datas	= self::get_values_by($non_cached_ids, $this->cache_key);
		}

		foreach($non_cached_ids as $id){
			$cache_value	= $datas[$id] ?? [];
			$cache_time		= $cache_value ? $this->cache_time : MINUTE_IN_SECONDS;

			$cache_values[$id]	= $cache_value;

			if($primary){
				$this->cache_set_by_primary_key($id, $cache_value, $cache_time);
			}else{
				$this->cache_set($id, $cache_value, $cache_time);
			}
		}

		unset($non_cached_ids);

		return $cache_values;
	}

	public function get_ids($ids){
		return self::update_caches($ids, $primary=true);
	}

	public function get_by_ids($ids){
		return self::update_caches($ids, $primary=true);
	}

	protected function parse_orderby($orderby){
		global $wpdb;

		if($orderby == 'rand'){
			return 'RAND()';
		}elseif(preg_match('/RAND\(([0-9]+)\)/i', $orderby, $matches)){
			return sprintf('RAND(%s)', (int)$matches[1]);
		}elseif(strpos($orderby, '__in')){
			return '';	// 应该在 WPJAM_Query 里面处理
			// $field	= str_replace('__in', '', $orderby);
		}

		if($this->meta_type && $this->meta_query){
			$primary_meta_key	= '';
			$primary_meta_query	= false;
			$meta_clauses		= $this->meta_query->get_clauses();

			if(!empty($meta_clauses)){
				$primary_meta_query	= reset($meta_clauses);

				if(!empty($primary_meta_query['key'])){
					$primary_meta_key	= $primary_meta_query['key'];
				}

				if($orderby == $primary_meta_key || $orderby == 'meta_value'){
					if(!empty($primary_meta_query['type'])){
						return "CAST({$primary_meta_query['alias']}.meta_value AS {$primary_meta_query['cast']})";
					}else{
						return "{$primary_meta_query['alias']}.meta_value";
					}
				}elseif($orderby == 'meta_value_num'){
					return "{$primary_meta_query['alias']}.meta_value+0";
				}elseif(array_key_exists($orderby, $meta_clauses)){
					$meta_clause	= $meta_clauses[$orderby];
					return "CAST({$meta_clause['alias']}.meta_value AS {$meta_clause['cast']})";
				}
			}
		}
		
		if($orderby == 'meta_value_num' || $orderby == 'meta_value'){
			return '';
		}

		return '`'.sanitize_key($orderby).'`';
	}

	protected function parse_order($order){
		if(!is_string($order) || empty($order)){
			return 'DESC';
		}

		return 'ASC' === strtoupper($order) ? 'ASC' : 'DESC';
	}

	public function get_clauses($fields=[]){
		$join		= '';
		$where		= '';
		$distinct	= '';

		$groupby	= $this->get_query_var('groupby');

		if($this->meta_type && $this->meta_query){
			$clauses	= $this->meta_query->get_sql($this->meta_type, $this->table, $this->primary_key, $this);
			$join		.= $clauses['join'];
			$where		.= $clauses['where'];

			if(!empty($this->meta_query->queries)){
				$groupby	= $groupby ?: $this->table.'.'.$this->primary_key;
				$fields		= $fields ?: $this->table.'.*';
			}
		}

		if($fields){
			if(is_array($fields)){
				$fields	= '`'.implode( '`, `', $fields ). '`';
				$fields	= esc_sql($fields); 
			}
		}else{
			$fields = '*';
		}

		if($groupby){
			if(strstr($groupby, ',') !== false || strstr($groupby, '(') !== false || strstr($groupby, '.') !== false){
				$groupby	= ' GROUP BY ' . $groupby;
			}else{
				$groupby	= ' GROUP BY `' . $groupby . '`';
			}
		}else{
			$groupby	= '';
		}

		if($having = $this->get_query_var('having')){
			$having	= ' HAVING ' . $having;
		}else{
			$having	= '';
		}

		if($orderby = $this->get_query_var('orderby')){
			if(is_array($orderby)){
				$orderby_array	= [];

				foreach($orderby as $_orderby => $order){
					$_orderby	= addslashes_gpc(urldecode($_orderby));

					if($parsed = $this->parse_orderby($_orderby)){
						$orderby_array[]	=  $parsed . ' ' . $this->parse_order($order);
					}
				}

				$orderby	= $orderby_array ? ' ORDER BY '.implode(', ', $orderby_array) : '';
			}elseif(strstr($orderby, '(') !== false && strstr($orderby, ')') !== false){
				$orderby	= ' ORDER BY ' . $orderby;
			}elseif(strstr($orderby, ',') !== false ){
				$orderby	= ' ORDER BY ' . $orderby;
			}else{
				$orderby	= addslashes_gpc(urldecode($orderby));

				if($parsed = $this->parse_orderby($orderby)){
					$order		= $orderby == 'RAND()' ? '' : $this->get_query_var('order');
					$orderby	= ' ORDER BY ' . $parsed . ' ' . $order;
				}else{
					$orderby	= '';
				}
			}
		}else{
			$orderby	= '';
		}

		$limits	= '';

		if($this->get_query_var('limit') > 0){
			$limits .= ' LIMIT ' . $this->get_query_var('limit');
		}

		if($this->get_query_var('offset') > 0){
			$limits .= ' OFFSET ' . $this->get_query_var('offset');
		}

		if(!empty($limits) && $this->get_query_var('found_rows')){
			$found_rows	= 'SQL_CALC_FOUND_ROWS';
		}else{
			$found_rows	= '';
		}

		$where	= $this->get_conditions().$where;

		return compact('where', 'groupby', 'join', 'orderby', 'distinct', 'having', 'fields', 'limits', 'found_rows');
	}

	public function get_sql_by_clauses($clauses){
		$distinct	= $clauses['distinct'];
		$fields		= $clauses['fields'];
		$join		= $clauses['join'];
		$where		= $clauses['where'];
		$groupby	= $clauses['groupby'];
		$having		= $clauses['having'];
		$orderby	= $clauses['orderby'];
		$limits		= $clauses['limits'];
		$found_rows	= $clauses['found_rows'];

		return "SELECT $found_rows $distinct $fields FROM `{$this->table}` $join $where $groupby $having $orderby $limits";
	}

	public function get_sql($fields=[]){
		$clauses	= $this->get_clauses($fields);

		return $this->get_sql_by_clauses($clauses);
	}

	public function filter_results($results){
		if($results){
			$ids	= [];

			foreach($results as $result){
				if(!empty($result[$this->primary_key])){
					$id		= $result[$this->primary_key];
					$ids[]	= $id;

					$this->cache_set_by_primary_key($id, $result);
				}
			}

			if($ids){
				if($this->lazyload_callback){
					call_user_func($this->lazyload_callback, $ids, $results);
				}

				if($this->meta_type && ($mt_obj	= wpjam_get_meta_type_object($this->meta_type))){
					call_user_func([$mt_obj, 'lazyload'], $ids, $results);
				}
			}
		}

		return $results;
	}

	public function get_results($fields=[]){
		$clauses	= $this->get_clauses($fields);
		$sql		= $this->get_sql_by_clauses($clauses);
		$results	= $GLOBALS['wpdb']->get_results($sql, ARRAY_A);

		if($clauses['fields'] == '*' || $clauses['fields'] == $this->table.'.*'){
			$this->filter_results($results);
		}

		return $results;
	}

	public function get_col($field=''){
		$sql	= $this->get_sql($field);

		return $GLOBALS['wpdb']->get_col($sql);
	}

	public function get_var($field=''){
		$sql	= $this->get_sql($field);

		return $GLOBALS['wpdb']->get_var($sql);
	}

	public function get_row($fields=[]){
		$sql	= $this->get_sql($fields);

		return $GLOBALS['wpdb']->get_row($sql, ARRAY_A);
	}

	public function find($fields=[], $func='get_results'){
		return $this->$func($fields);
	}

	public function find_total($groupby=false){
		return $GLOBALS['wpdb']->get_var("SELECT FOUND_ROWS();");
	}

	public function get_request(){
		return $GLOBALS['wpdb']->last_query;
	}

	public function last_query(){
		return $GLOBALS['wpdb']->last_query;
	}

	public function insert_multi($datas){	// 使用该方法，自增的情况可能无法无法删除缓存，请注意
		$this->set_last_changed();

		if(empty($datas)){
			return new WP_Error('empty_datas', '数据为空');
		}

		$data		= current($datas);

		$formats	= $this->process_field_formats($data);
		$values		= [];
		$fields		= '`'.implode('`, `', array_keys($data)).'`';
		$updates	= implode(', ', array_map(function($field){ return "`$field` = VALUES(`$field`)"; }, array_keys($data)));

		$cache_keys		= [];
		$primary_keys	= [];

		foreach($datas as $data){
			if($data){
				foreach($data as $k => $v){
					if(is_array($v)){
						trigger_error($k.'的值是数组：'.var_export($data,true));
						continue;
					}
				}

				$values[]	= $GLOBALS['wpdb']->prepare('('.implode(', ', $formats).')', array_values($data));

				if(!empty($data[$this->primary_key])){
					$this->cache_delete_by_primary_key($data[$this->primary_key]);

					$primary_keys[]	= $data[$this->primary_key];
				}

				if($this->cache_key != $this->primary_key && !empty($data[$this->cache_key])){
					$this->cache_delete($data[$this->cache_key]);

					$cache_keys[]	= $data[$this->cache_key];
				}
			}
		}

		// if($primary_keys){
		// 	$this->cache_delete_multi_by_primary_key($primary_keys);
		// }

		// if($cache_keys){
		// 	$this->cache_delete_multi($cache_keys);
		// }

		if($this->cache_key != $this->primary_key){
			$conditions	= [];

			if($primary_keys){
				$conditions[]	= $this->where_in($this->primary_key, $primary_keys)->get_conditions(false);
			}

			if($cache_keys){
				$conditions[]	= $this->where_in($this->cache_key, $cache_keys)->get_conditions(false);
			}

			$this->cache_delete_by_conditions($conditions);
		}

		$values	= implode(',', $values);
		$sql	=  "INSERT INTO `$this->table` ({$fields}) VALUES {$values} ON DUPLICATE KEY UPDATE {$updates}";

		if(wpjam_doing_debug()){
			echo $sql;
		}

		$result	= $GLOBALS['wpdb']->query($sql);

		if(false === $result){
			return new WP_Error('insert_error', $GLOBALS['wpdb']->last_error);
		}else{
			return $result;
		}
	}

	public function insert($data){
		global $wpdb;

		$this->set_last_changed();

		if(!empty($data[$this->primary_key])){
			$this->cache_delete_by_primary_key($data[$this->primary_key]);
		}

		if($this->primary_key != $this->cache_key){
			$conditions = [];

			if(!empty($data[$this->primary_key])){
				$conditions[] = $this->where($this->primary_key, $data[$this->primary_key])->get_conditions(false);
			}

			if(!empty($data[$this->cache_key])){
				$this->cache_delete($data[$this->cache_key]);

				$conditions[] = $this->where($this->cache_key, $data[$this->cache_key])->get_conditions(false);
			}

			$this->cache_delete_by_conditions($conditions);
		}

		if(!empty($data[$this->primary_key])){
			$data 		= array_filter($data, function($v){ return !is_null($v); });

			$formats	= $this->process_field_formats($data);
			$fields		= implode(', ', array_keys($data));
			$values		= $wpdb->prepare(implode(', ',$formats), array_values($data));
			$updates	= implode(', ', array_map(function($field){ return "`$field` = VALUES(`$field`)"; }, array_keys($data)));

			$wpdb->check_current_query = false;

			if(false === $wpdb->query("INSERT INTO `$this->table` ({$fields}) VALUES ({$values}) ON DUPLICATE KEY UPDATE {$updates}")){
				return new WP_Error('insert_error', $wpdb->last_error);
			}else{
				return $data[$this->primary_key];
			}

		}else{
			$formats	= $this->process_field_formats($data);
			$result 	= $wpdb->insert($this->table, $data, $formats);

			if($result === false){
				return new WP_Error('insert_error', $wpdb->last_error);
			}else{
				$this->cache_delete_by_primary_key($wpdb->insert_id);
				return $wpdb->insert_id;
			}
		}
	}

	/*
	用法：
	update($data, $where);
	update($id, $data);
	update($data); // $where各种 参数通过 where() 方法事先传递
	*/
	public function update(){
		global $wpdb;

		$this->set_last_changed();

		$args_num = func_num_args();
		$args = func_get_args();

		if($args_num == 2){
			if(is_array($args[0])){
				$data	= $args[0];
				$where 	= $args[1];

				$conditions = [];

				$conditions[] = '('.$this->where_all($where)->get_conditions(false).')';

				if(!empty($data[$this->primary_key])){
					$this->cache_delete_by_primary_key($data[$this->primary_key]);

					$conditions[] = $this->where($this->primary_key, $data[$this->primary_key])->get_conditions(false);
				}

				if($this->primary_key != $this->cache_key){
					if(!empty($data[$this->cache_key])){
						$this->cache_delete($data[$this->cache_key]);

						$conditions[] = $this->where($this->cache_key, $data[$this->cache_key])->get_conditions(false);
					}
				}

				$this->cache_delete_by_conditions($conditions);
			}else{
				$id		= $args[0];
				$data	= $args[1];
				$where	= [$this->primary_key=>$id];

				$conditions = [];

				$this->cache_delete_by_primary_key($id);

				$conditions[] = $this->where($this->primary_key, $id)->get_conditions(false);

				if(!empty($data[$this->primary_key])){
					$this->cache_delete_by_primary_key($data[$this->primary_key]);

					$conditions[] = $this->where($this->primary_key, $data[$this->primary_key])->get_conditions(false);
				}

				if($this->primary_key != $this->cache_key){
					if(!empty($data[$this->cache_key])){
						$this->cache_delete($data[$this->cache_key]);

						$conditions[] = $this->where($this->cache_key, $data[$this->cache_key])->get_conditions(false);
					}

					$this->cache_delete_by_conditions($conditions);
				}
			}

			$format			= $this->process_field_formats($data);
			$where_format	= $this->process_field_formats($where);

			$result			= $wpdb->update($this->table, $data, $where, $format, $where_format);

			if($result === false){
				return new WP_Error('update_error', $wpdb->last_error);
			}else{
				return $result;
			}
		}
		// 如果为空，则需要事先通过各种 where 方法传递进去
		elseif($args_num == 1){
			$data	= $args[0];

			$conditions		= []; 
			$conditions[]	= $_condition	= $this->get_conditions(false);

			if(!empty($data[$this->primary_key])){
				$this->cache_delete_by_primary_key($data[$this->primary_key]);

				$conditions[] = $this->where($this->primary_key, $data[$this->primary_key])->get_conditions(false);
			}

			if($this->primary_key != $this->cache_key){
				if(!empty($data[$this->cache_key])){
					$this->cache_delete($data[$this->cache_key]);

					$conditions[] = $this->where($this->cache_key, $data[$this->cache_key])->get_conditions(false);
				}
			}

			$this->cache_delete_by_conditions($conditions);

			$fields = $values = [];
			foreach( $data as $field => $value ){
				if( is_null( $value ) ){
					$fields[] = "`$field` = NULL";
					continue;
				}

				$fields[] = "`$field` = " . $this->process_field_formats($field);
				$values[] = $value;
			}

			$fields = implode( ', ', $fields );

			if($_condition){
				$sql	= $wpdb->prepare("UPDATE `{$this->table}` SET {$fields} WHERE {$_condition}", $values);
			}else{
				$sql	= $wpdb->prepare("UPDATE `{$this->table}` SET {$fields}", $values);
			}

			if(wpjam_doing_debug()){
				echo $sql;
			}

			return $wpdb->query($sql);

			// return new WP_Error('update_error', 'WHERE 为空！');
		}
	}

	/*
	用法：
	delete($where);
	delete($id);
	delete(); // $where 参数通过各种 where() 方法事先传递
	*/
	public function delete($where = ''){
		global $wpdb;

		$this->set_last_changed();

		if($where){
			// 如果传递进来字符串或者数字，认为根据主键删除
			if(!is_array($where)){
				$id		= $where; 
				$where	= [$this->primary_key=>$id];

				$this->cache_delete_by_primary_key($id);

				if($this->cache_key != $this->primary_key){
					$this->cache_delete_by_conditions($this->where($this->primary_key, $id)->get_conditions());
				}
			}
			// 传递数组，采用 wpdb 默认方式
			else{
				$this->cache_delete_by_conditions($this->where_all($where)->get_conditions());
			}

			$where_format	= $this->process_field_formats($where);
			$result			= $wpdb->delete($this->table, $where, $where_format);

			if($result === false){
				return new WP_Error('delele_error', $wpdb->last_error);
			}else{
				return $result;
			}
		}
		// 如果为空，则 $where 参数通过各种 where() 方法事先传递
		else{
			if($conditions = $this->get_conditions()){
				$this->cache_delete_by_conditions($conditions);

				$sql = "DELETE FROM `{$this->table}` {$conditions}";

				if(wpjam_doing_debug()){
					echo $sql;
				}

				$result = $wpdb->query($sql);

				if(false === $result ){
					return new WP_Error('delele_error', $wpdb->last_error);
				}else{
					return $result ;
				}
			}else{
				return new WP_Error('delele_error', 'WHERE 为空！');
			}
		}
	}

	public function delete_multi($ids){
		global $wpdb;

		$this->set_last_changed();

		if(empty($ids)){
			return new WP_Error('empty_datas', '数据为空');
		}

		foreach($ids as $id){
			$this->cache_delete_by_primary_key($id);
		}

		if($this->primary_key != $this->cache_key){
			$this->cache_delete_by_conditions($this->where_in($this->primary_key, $ids)->get_conditions());
		}

		$values = [];

		foreach($ids as $id){
			$values[] = $wpdb->prepare($this->process_field_formats($this->primary_key), $id);
		}

		$where = 'WHERE `' . $this->primary_key . '` IN ('.implode(',', $values).') ';

		$sql = "DELETE FROM `{$this->table}` {$where}";

		if(wpjam_doing_debug()){
			echo $sql;
		}

		$result = $wpdb->query($sql);

		if(false === $result ){
			return new WP_Error('delele_error', $wpdb->last_error);
		}else{
			return $result ;
		}
	}

	public function parse_list($list){
		if(!is_array($list)){
			$list	= preg_split('/[\s,]+/', $list);
		}

		return array_values(array_unique($list));
	}

	private function parse_where(){
		global $wpdb;

		$where = [];

		if($this->searchable_fields && ($search_term = $this->get_query_var('search_term'))){
			$search_where = [];

			foreach($this->searchable_fields as $field){
				$like = '%' . $wpdb->esc_like( $search_term ) . '%';
				$search_where[]	= $wpdb->prepare( '`' . $field . '` LIKE  %s', $like );
			}

			$search_where = implode(' OR ', $search_where);

			$where[] = ' (' . $search_where . ')';
		}

		foreach($this->where as $q){
			if(isset($q['column'])){
				if(strstr($q['column'], '(') !== false){
					$q_column	= ' '.$q['column'].' ';
				}else{
					$q_column	= ' `' . $q['column']. '` ';
				}
			}

			// where
			if($q['type'] == 'where'){
				$where[] = $wpdb->prepare($q_column . '= ' . $this->process_field_formats($q['column']), $q['value']);
			}
			// where_not
			elseif($q['type'] == 'not'){
				$where[] = $wpdb->prepare($q_column . '!= ' . $this->process_field_formats($q['column']), $q['value']);
			}
			// where_like
			elseif($q['type'] == 'like'){
				$where[] = $wpdb->prepare($q_column . 'LIKE ' . $this->process_field_formats($q['column']), $q['value']);
			}
			// where_not_like
			elseif($q['type'] == 'not_like'){
				$where[] = $wpdb->prepare($q_column . 'NOT LIKE ' . $this->process_field_formats($q['column']), $q['value']);
			}
			// where_lt
			elseif($q['type'] == 'lt'){
				$where[] = $wpdb->prepare($q_column . '< ' . $this->process_field_formats($q['column']), $q['value']);
			}
			// where_lte
			elseif($q['type'] == 'lte'){
				$where[] = $wpdb->prepare($q_column . '<= ' . $this->process_field_formats($q['column']), $q['value']);
			}
			// where_gt
			elseif($q['type'] == 'gt'){
				$where[] = $wpdb->prepare($q_column . '> ' . $this->process_field_formats($q['column']), $q['value']);
			}
			// where_gte
			elseif($q['type'] == 'gte'){
				$where[] = $wpdb->prepare($q_column . '>= ' . $this->process_field_formats($q['column']), $q['value']);
			}
			// where_in
			elseif($q['type'] == 'in'){
				$values = [];

				foreach(self::parse_list($q['value']) as $value){
					$values[] = $wpdb->prepare($this->process_field_formats($q['column']), $value);
				}

				if(count($values) == 1){
					$where[] = $q_column . '= ' . $values[0];
				}else{
					$where[] = $q_column . 'IN ('.implode(',', $values).') ';
				}
			}
			// where_not_in
			elseif($q['type'] == 'not_in'){
				$values = [];

				foreach(self::parse_list($q['value']) as $value){
					$values[] = $wpdb->prepare($this->process_field_formats($q['column']), $value);
				}

				if(count($values) == 1){
					$where[] = $q_column . '!= ' . $values[0];
				}else{
					$where[] = $q_column . 'NOT IN ('.implode(',', $values).') ';
				}
			}
			// where_any
			elseif($q['type'] == 'any'){
				$wehre_any = [];
				foreach($q['where'] as $column => $value){
					$wehre_any[]	= $wpdb->prepare( '`' . $column . '` =  '.$this->process_field_formats($column), $value );
				}

				$wehre_any = implode(' OR ', $wehre_any);

				$where[] = ' ('. $wehre_any . ')';
			}
			// where_all
			elseif($q['type'] == 'all'){
				$wehre_all = [];
				foreach($q['where'] as $column => $value){
					$wehre_all[]	= $wpdb->prepare( '`' . $column . '` =  '.$this->process_field_formats($column), $value );
				}

				$wehre_all = implode(' AND ', $wehre_all);

				$where[] = ' ('. $wehre_all . ')';
			}
			// where_fragment
			elseif($q['type'] == 'fragment'){
				$where[] = ' ('. $q['fragment'] . ')';
			}
			// find_in_set
			elseif($q['type'] == 'find_in_set'){
				$where[] = ' FIND_IN_SET ('. $q['item'] . ', '.$q['list'].')';
			}
		}

		return $where;
	}

	private function get_conditions($return='with_where', $clear=true){
		$where	= $this->parse_where();

		if($clear){
			$this->clear();	
		}

		if($return === 'array'){
			return $where;
		}

		if(!empty($where)){
			$conditions	= $return ? '  WHERE ' : ' ';
			return $conditions.implode(' AND ', $where);
		}else{
			return '';
		}
	}

	public function get_wheres(){	// 以后放弃，目前统计在用
		return $this->get_conditions(false);
	}

	private function process_field_formats($data){
		if(is_array($data)){
			$format	= [];

			foreach($data as $field => $value){
				$format[]	= $this->field_types[$field] ?? '%s';
			}
		}else{
			$format	= $this->field_types[$data] ?? '%s';
		}

		return $format;
	}

	public function found_rows($found_rows=true){
		return $this->set_query_var('found_rows', (bool)$found_rows);
	}

	public function limit($limit){
		return $this->set_query_var('limit', (int)$limit);
	}

	public function offset($offset){
		return $this->set_query_var('offset', (int)$offset);
	}

	public function order_by($orderby=''){
		return $this->orderby($orderby);
	}

	public function orderby($orderby=''){
		return !is_null($orderby) ? $this->set_query_var('orderby', $orderby) : $this;
	}

	public function group_by($group_by=''){
		return $this->groupby($group_by);
	}

	public function groupby($groupby=''){
		return $groupby ? $this->set_query_var('groupby', $groupby) : $this;
	}

	public function having($having=''){
		return $having ? $this->set_query_var('having', $having) : $this;
	}

	public function order($order='DESC'){
		$order	= (strtoupper($order) == 'ASC') ? 'ASC':'DESC';

		return $this->set_query_var('order', $order);
	}

	public function search($search_term=''){
		return $search_term ? $this->set_query_var('search_term', $search_term) : $this;
	}

	public function where($column, $value){
		if($value !== null){
			$this->where[] = ['type' => 'where', 'column' => $column, 'value' => $value];
		}
		return $this;
	}

	public function where_not($column, $value){
		if($value !== null){
			$this->where[] = ['type' => 'not', 'column' => $column, 'value' => $value];
		}
		return $this;
	}

	public function where_like($column, $value){
		if($value !== null){
			$this->where[] = ['type' => 'like', 'column' => $column, 'value' => $value];
		}
		return $this;
	}

	public function where_not_like($column, $value){
		if($value !== null){
			$this->where[] = ['type' => 'not_like', 'column' => $column, 'value' => $value];
		}
		return $this;
	}

	public function where_lt($column, $value){
		if($value !== null){
			$this->where[] = ['type' => 'lt', 'column' => $column, 'value' => $value];
		}
		return $this;
	}

	public function where_lte($column, $value){
		if($value !== null){
			$this->where[] = ['type' => 'lte', 'column' => $column, 'value' => $value];
		}
		return $this;
	}

	public function where_gt($column, $value){
		if($value !== null){
			$this->where[] = ['type' => 'gt', 'column' => $column, 'value' => $value];
		}
		return $this;
	}

	public function where_gte($column, $value){
		if($value !== null){
			$this->where[] = ['type' => 'gte', 'column' => $column, 'value' => $value];
		}
		return $this;
	}

	public function where_in($column, $in){
		if($in !== null){
			if($in){
				$this->where[] = ['type' => 'in', 'column' => $column, 'value' => $in];
			}else{
				$this->where($column, '');
			}
		}
		return $this;
	}

	public function where_not_in($column, $not_in){
		if($not_in !== null){
			if($not_in){
				$this->where[] = ['type' => 'not_in', 'column' => $column, 'value' => $not_in];
			}else{
				$this->where_not($column, '');
			}
		}
		return $this;
	}

	public function where_any(array $where){
		if($where){
			$this->where[] = ['type' => 'any', 'where' => $where];
		}
		return $this;
	}

	public function where_all(array $where){
		if($where){
			$this->where[] = ['type' => 'all', 'where' => $where];
		}
		return $this;
	}

	public function where_fragment($where){
		if($where){
			$this->where[] = ['type' => 'fragment', 'fragment' => $where];
		}
		return $this;
	}

	public function find_in_set($item, $list){
		$this->where[] = ['type' => 'find_in_set', 'item' => $item, 'list' => $list];
		return $this;
	}

	public function query_items($limit, $offset){ 
		$this->limit($limit); 
		$this->offset($offset);
		$this->found_rows();

		if(isset($_REQUEST['orderby']) && $this->get_query_var('orderby') == $this->primary_key){	// 没设置过，才设置
			$this->orderby($_REQUEST['orderby']);
		}

		if(isset($_REQUEST['order'])){
			$this->order($_REQUEST['order']);
		}

		if($this->searchable_fields && is_null($this->get_query_var('search_term')) && isset($_REQUEST['s'])){
			$this->search($_REQUEST['s']);
		}

		foreach($this->filterable_fields as $filter_key){
			if(isset($_REQUEST[$filter_key])){
				$this->where($filter_key, $_REQUEST[$filter_key]);
			}
		}

		return ['items'=>$this->get_results(), 'total'=>$this->find_total($this->get_query_var('groupby'))];
	}
}

class WPJAM_DBTransaction{
	public static function wpdb(){
		global $wpdb;
		return $wpdb;
	}

	public static function beginTransaction(){
		return self::wpdb()->query("START TRANSACTION;");
	}

	public static function queryException(){
		$error = self::wpdb()->last_error;
		if(!empty($error)){
			throw new Exception($error);
		}
	}

	public static function commit(){
		self::queryException();
		return self::wpdb()->query("COMMIT;");
	}

	public static function rollBack(){
		return self::wpdb()->query("ROLLBACK;");
	}
}