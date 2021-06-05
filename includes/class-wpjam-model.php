<?php
abstract class WPJAM_Model{
	protected $data	= [];

	public function __construct(array $data=[]){
		$this->data	= $data;
	}

	public function __get($key){
		return $this->get_data($key);
	}

	public function __set($key, $value){
		$this->set_data($key, $value);
	}

	public function __isset($key){
		return isset($this->data[$key]);
	}

	public function __unset($key){
		unset($this->data[$key]);
	}

	public function get_data($key=''){
		if($key){
			return $this->data[$key] ?? null;
		}else{
			return $this->data;
		}
	}

	public function set_data($key, $value){
		if(self::get_primary_key() == $key){
			trigger_error('不能修改主键的值');
			wp_die('不能修改主键的值');
		}

		$this->data[$key]	= $value;

		return $this;
	}

	public function to_array(){
		return $this->data;
	}

	public function save($data=[]){
		if($data){
			$this->data = array_merge($this->data, $data);
		}

		$primary_key	= self::get_primary_key();

		$id	= $this->data[$primary_key] ?? null;

		if($id){
			$result	= static::update($id, $this->data);
		}else{
			$result	= $id = static::insert($this->data);
		}

		if(!is_wp_error($result)){
			$this->data	= static::get($id);
		}

		return $result;
	}

	public static function find($id){
		return static::get_instance($id);
	}

	public static function get_instance($id){
		if($id && ($data = static::get($id))){
			return new static($data);
		}else{
			return null;
		}
	}

	public static function get_handler(){
		return static::$handler;
	}

	public static function set_handler($handler){
		static::$handler	= $handler;
	}
	
	// get($id)
	// get_by($field, $value, $order='ASC')
	// get_by_ids($ids)
	// get_searchable_fields()
	// get_filterable_fields()
	// update_caches($values)
	// insert($data)
	// insert_multi($datas)
	// update($id, $data)
	// delete($id)
	// move($id, $data)
	// get_primary_key()
	// get_cache_key($key)
	// get_last_changed
	// get_cache_group
	// cache_get($key)
	// cache_set($key, $data, $cache_time=DAY_IN_SECONDS)
	// cache_add($key, $data, $cache_time=DAY_IN_SECONDS)
	// cache_delete($key)
	public static function __callStatic($method, $args){
		return self::call_handler_method($method, ...$args);
	}

	protected static function call_handler_method($method, ...$args){
		$method_map	= [
			'get_ids'	=> 'get_by_ids',
			'get_all'	=> 'get_results'
		];

		if(isset($method_map[$method])){
			$method	= $method_map[$method];
		}
				
		if(method_exists(static::get_handler(), $method)){
			if(in_array($method, ['cache_get', 'cache_set', 'cache_add', 'cache_delete'])){
				$args[0]	= static::get_handler()->get_cache_key($args[0]);
				$group		= static::get_handler()->get_cache_group();
				$cg_obj		= WPJAM_Cache_Group::get_instance($group);

				return call_user_func_array([$cg_obj, $method], $args);
			}else{
				return call_user_func_array([static::get_handler(), $method], $args);
			}
		}else{
			return new WP_Error('undefined_method', '「'.$method.'」方法未定义');
		}
	}

	public static function Query($args=[]){
		if($args){
			return new WPJAM_Query(static::get_handler(), $args);
		}else{
			return static::get_handler();
		}
	}

	public static function get_list_cache(){
		return new WPJAM_listCache(self::get_cache_group());
	}

	public static function get_one_by($field, $value, $order='ASC'){
		$items = static::get_by($field, $value, $order);
		return $items ? current($items) : [];
	}

	public static function delete_by($field, $value){
		return static::get_handler()->delete([$field=>$value]);
	}

	public static function delete_multi($ids){
		if(method_exists(static::get_handler(), 'delete_multi')){
			return static::get_handler()->delete_multi($ids);
		}elseif($ids){
			foreach($ids as $id){
				$result	= static::get_handler()->delete($id);

				if(is_wp_error($result)){
					return $result;
				}
			}

			return $result;
		}
	}

	public static function query_items($limit, $offset){
		return self::call_handler_method('query_items', $limit, $offset);
	}

	public static function list($limit, $offset){
		// _deprecated_function(__METHOD__, 'WPJAM Basic 3.7', 'WPJAM_Model::query_items');
		return self::call_handler_method('query_items', $limit, $offset);
	}

	public static function item_callback($item){
		return $item;
	}

	public static function parse_item($item){
		return $item;
	}

	public static function get_by_cache_keys($values){
		_deprecated_function(__METHOD__, 'WPJAM Basic 4.4', 'WPJAM_Model::update_caches');
		return static::update_caches($values);
	}
}

class WPJAM_Query{
	public $query;
	public $query_vars;
	public $request;
	public $datas;
	public $max_num_pages	= 0;
	public $found_rows 		= 0;
	public $next_cursor 	= 0;
	public $handler;

	public function __construct($handler, $query=''){
		$this->handler	= $handler;

		if(!empty($query)){
			$this->query($query);
		}
	}

	public function __call($name, $args){
		return $this->handler->$name(...$args);
	}

	public function query($query){
		$this->query		= $query;
		$this->query_vars	= wp_parse_args($query, [
			'number'	=> 50,
			'orderby'	=> $this->get_primary_key()
		]);

		$found_rows	= false;
		$orderby 	= $this->query_vars['orderby'];
		$cache_it	= $orderby != 'rand';
		$fields		= wpjam_array_pull($this->query_vars, 'fields');
		
		if($this->get_meta_type()){
			$meta_query	= new WP_Meta_Query();
			$meta_query->parse_query_vars($query);

			$this->set_meta_query($meta_query);

			$this->query_vars	= wpjam_array_except($this->query_vars, ['meta_key', 'meta_value', 'meta_value_num', 'meta_compare', 'meta_query']);
		}

		foreach ($this->query_vars as $key => $value){
			if(is_null($value)){
				continue;
			}

			if($key == 'number'){
				if($value != -1){
					$found_rows	= true;

					$this->limit($value);
				}
			}elseif($key == 'offset'){
				$found_rows	= true;

				$this->offset($value);
			}elseif($key == 'orderby'){
				$this->orderby($value);
			}elseif($key == 'order'){
				$this->order($value);
			}elseif($key == 'first'){
				$this->where_gt($orderby, $value);
			}elseif($key == 'cursor'){
				if($value > 0){
					$this->where_lt($orderby, $value);
				}
			}elseif($key == 'search'){
				$this->search($value);
			}elseif(strpos($key, '__in_set')){
				$this->find_in_set($value, str_replace('__in_set', '', $key));
			}elseif(strpos($key, '__in')){
				$this->where_in(str_replace('__in', '', $key), $value);
			}elseif(strpos($key, '__not_in')){
				$this->where_not_in(str_replace('__not_in', '', $key), $value);
			}else{
				$this->where($key, $value);
			}
		}

		if($found_rows){
			$this->found_rows(true);
		}

		$clauses	= apply_filters_ref_array('wpjam_clauses', [$this->get_clauses($fields), &$this]);
		$request	= apply_filters_ref_array('wpjam_request', [$this->get_sql_by_clauses($clauses), &$this]);

		$this->request	= $request;

		if($cache_it){
			$last_changed	= $this->get_last_changed();
			$cache_group	= $this->get_cache_group();
			$cache_prefix	= $this->get_cache_prefix();
			$key			= md5(maybe_serialize($this->query).$request);
			$cache_key		= 'wpjam_query:'.$key.':'.$last_changed;
			$cache_key		= $cache_prefix ? $cache_prefix.':'.$cache_key : $cache_key;

			$result			= wp_cache_get($cache_key, $cache_group);
		}else{
			$result			= false;
		}

		if($result === false){
			$datas	= $GLOBALS['wpdb']->get_results($request, ARRAY_A);
			$result	= ['datas'=>$this->filter_results($datas)];

			if($found_rows){
				$result['found_rows']	= $this->find_total();
			}

			if($cache_it){
				wp_cache_set($cache_key, $result, $cache_group, DAY_IN_SECONDS);
			}
		}

		$this->datas	= apply_filters_ref_array('wpjam_datas', [$result['datas'], &$this]);
		
		if($found_rows){
			$this->found_rows	= $result['found_rows'];

			if($this->found_rows && $this->query_vars['number'] && $this->query_vars['number'] != -1){
				$this->max_num_pages = ceil($this->found_rows / $this->query_vars['number']);

				if(!isset($this->query_vars['offset']) && $orderby == $this->get_primary_key()){
					if($this->found_rows > $this->query_vars['number']){
						$this->next_cursor	= (int)$this->datas[count($this->datas)-1][$orderby];
					}
				}
			}
		}

		return $this->datas;
	}
}