<?php
trait WPJAM_Register_Trait{
	protected $name;
	protected $args;

	public function __construct($name, $args=[]){
		$this->name	= $name;
		$this->args	= $args;
	}

	public function filter_args(){
		return $this->args;
	}

	public function __get($key){
		if($key == 'name'){
			return $this->name;
		}else{
			$args	= $this->filter_args();
			return $args[$key] ?? null;
		}
	}

	public function __set($key, $value){
		if($key != 'name'){
			$this->args	= $this->filter_args();
			$this->args[$key]	= $value;
		}
	}

	public function __isset($key){
		$args	= $this->filter_args();
		return isset($args[$key]);
	}

	public function __unset($key){
		$this->args	= $this->filter_args();
		unset($this->args[$key]);
	}

	public function to_array(){
		return $this->filter_args();
	}

	protected static $_registereds	= [];

	public static function register($name, $args){
		$instance	= new static($name, $args);

		return self::register_instance($name, $instance);
	}

	protected static function register_instance($name, $instance){
		if(empty($name)){
			trigger_error(self::class.'的注册 name 为空');
			return null;
		}elseif(is_numeric($name)){
			trigger_error(self::class.'的注册 name「'.$name.'」'.'为纯数字');
			return null;
		}elseif(!is_string($name)){
			trigger_error(self::class.'的注册 name「'.var_export($name, true).'」不为字符串');
			return null;
		}

		self::$_registereds[$name]	= $instance;

		return $instance;
	}

	public static function unregister($name){
		unset(self::$_registereds[$name]);
	}

	public static function get_by($args=[], $output='objects', $operator='and'){
		return self::get_registereds($args, $output, $operator);
	}

	public static function get_registereds($args=[], $output='objects', $operator='and'){
		$registereds	= $args ? wp_filter_object_list(self::$_registereds, $args, $operator, false) : self::$_registereds;

		if($output == 'names'){
			return array_keys($registereds);
		}elseif(in_array($output, ['args', 'settings'])){
			return array_map(function($registered){ return $registered->to_array(); }, $registereds);
		}else{
			return $registereds;
		}
	}

	public static function get($name){
		return self::$_registereds[$name] ?? null;
	}

	public static function exists($name){
		return isset(self::$_registereds[$name]);
	}
}

trait WPJAM_Type_Trait{
	use WPJAM_Register_Trait;
}

class WPJAM_Meta_Type{
	use WPJAM_Register_Trait;

	private $lazyloader	= null;

	public function __call($method, $args){
		if(in_array($method, ['get_meta', 'add_meta', 'update_meta', 'delete_meta', 'lazyload_meta'])){
			$method	= str_replace('_meta', '_data', $method);
		}elseif(in_array($method, ['delete_meta_by_key', 'update_meta_cache', 'create_meta_table', 'get_meta_table'])){
			$method	= str_replace('_meta', '', $method);
		}

		return call_user_func([$this, $method], ...$args);
	}

	public function lazyload_data($ids){
		if(is_null($this->lazyloader)){
			$this->lazyloader	= wpjam_register_lazyloader($this->name.'_meta', [
				'filter'	=> 'get_'.$this->name.'_metadata', 
				'callback'	=> [$this, 'update_cache']
			]);
		}

		$this->lazyloader->queue_objects($ids);
	}

	public function get_data($id, $meta_key='', $single=false){
		return get_metadata($this->name, $id, $meta_key, $single);
	}

	public function add_data($id, $meta_key, $meta_value, $unique=false){
		return add_metadata($this->name, $id, $meta_key, wp_slash($meta_value), $unique);
	}

	public function update_data($id, $meta_key, $meta_value, $prev_value=''){
		if($meta_value){
			return update_metadata($this->name, $id, $meta_key, wp_slash($meta_value), $prev_value);
		}else{
			return delete_metadata($this->name, $id, $meta_key, $prev_value);
		}
	}

	public function delete_data($id, $meta_key, $meta_value=''){
		return delete_metadata($this->name, $id, $meta_key, $meta_value);
	}

	public function delete_by_key($meta_key){
		return delete_metadata($this->name, null, $meta_key, '', true);
	}

	public function update_cache($object_ids){
		if($object_ids){
			update_meta_cache($this->name, $object_ids);
		}
	}

	public function get_table(){
		return $this->table ?: $GLOBALS['wpdb']->prefix.sanitize_key($this->name).'meta';
	}

	public function create_table(){
		$table	= $this->get_table();

		if($GLOBALS['wpdb']->get_var("show tables like '{$table}'") != $table){
			$column	= sanitize_key($this->name).'_id';

			$GLOBALS['wpdb']->query("CREATE TABLE {$table} (
				meta_id bigint(20) unsigned NOT NULL auto_increment,
				{$column} bigint(20) unsigned NOT NULL default '0',
				meta_key varchar(255) default NULL,
				meta_value longtext,
				PRIMARY KEY  (meta_id),
				KEY {$column} ({$column}),
				KEY meta_key (meta_key(191))
			)");
		}
	}
}

class WPJAM_Meta{
	public static function get_data($meta_type, $object_id, ...$args){
		if(!$object_id){
			return null;
		}

		if(is_array($args[0])){
			$data	= [];

			foreach($args[0] as $meta_key => $default){
				if(is_numeric($meta_key)){
					$meta_key	= $default;
					$default	= null;
				}

				$data[$meta_key]	= self::get_data($meta_type, $object_id, $meta_key, $default);
			}

			return $data;
		}else{
			$meta_key	= $args[0];
			$default	= $args[1] ?? null;

			if(metadata_exists($meta_type, $object_id, $meta_key)){
				return get_metadata($meta_type, $object_id, $meta_key, true);
			}

			return $default;
		}
	}

	public static function update_data($meta_type, $object_id, ...$args){
		if(is_array($args[0])){
			$data		= $args[0];
			$meta_keys	= (isset($args[1]) && is_array($args[1])) ? $args[1] : array_keys($data);

			foreach($meta_keys as $meta_key => $default){
				if(is_numeric($meta_key)){
					$meta_key	= $default;
					$default	= null;
				}

				$meta_value	= $data[$meta_key] ?? false;

				self::update_data($meta_type, $object_id, $meta_key, $meta_value, $default);
			}

			return true;
		}else{
			$meta_key	= $args[0];
			$meta_value	= $args[1];
			$default	= $args[2] ?? null;

			if(!is_null($meta_value) 
				&& $meta_value !== ''
				&& ((is_null($default) && $meta_value)
					|| (!is_null($default) && $meta_value != $default)
				)
			){
				return update_metadata($meta_type, $object_id, $meta_key, wp_slash($meta_value));
			}else{
				return delete_metadata($meta_type, $object_id, $meta_key);
			}
		}	
	}

	public static function get_by($meta_type, ...$args){
		if(empty($args)){
			return [];
		}

		$meta_key	= $meta_value	= null;

		if(is_array($args[0])){
			$args	= $args[0];

			if(isset($args['meta_key'])){
				$meta_key	= $args['meta_key'];
			}elseif(isset($args['key'])){
				$meta_key	= $args['key'];
			}

			if(isset($args['meta_value'])){
				$meta_value	= $args['meta_value'];
			}elseif(isset($args['value'])){
				$meta_value	= $args['value'];
			}
		}else{
			$meta_key	= $args[0];

			if(isset($args[1])){
				$meta_value	= $args[1];
			}
		}

		global $wpdb;

		$where	= [];

		if($meta_key){
			$where[]	= $wpdb->prepare('meta_key=%s', $meta_key);
		}

		if(!is_null($meta_value)){
			$where[]	= $wpdb->prepare('meta_value=%s', maybe_serialize($meta_value));
		}

		if($where){
			$table	= _get_meta_table($meta_type);
			$where	= implode(' AND ', $where);

			if($data = $wpdb->get_results("SELECT * FROM {$table} WHERE {$where}", ARRAY_A)){
				foreach($data as &$item){
					$item['meta_value']	= maybe_unserialize($item['meta_value']);
				}

				return $data;
			}
		}

		return [];
	}
}

class WPJAM_Lazyloader{
	use WPJAM_Register_Trait;

	private $pending_objects	= [];

	public function callback($check){
		if($this->pending_objects){
			if($this->accepted_args && $this->accepted_args > 1){
				foreach($this->pending_objects as $object){
					call_user_func($this->callback, $object['ids'], ...$object['args']);
				}
			}else{
				call_user_func($this->callback, $this->pending_objects);
			}

			$this->pending_objects	= [];
		}
	
		remove_filter($this->filter, [$this, 'callback']);

		return $check;
	}

	public function queue_objects($object_ids, ...$args){
		if($this->accepted_args && $this->accepted_args > 1){
			if((count($args)+1) >= $this->accepted_args){
				$key	= wpjam_json_encode($args);

				if(isset($this->pending_objects[$key])){
					$this->pending_objects[$key]['ids']	= array_merge($this->pending_objects[$key]['ids'], $object_ids);
					$this->pending_objects[$key]['ids']	= array_unique($this->pending_objects[$key]['ids']);
				}else{
					$this->pending_objects[$key]	= ['ids'=>$object_ids, 'args'=>$args];
				}
			}
		}else{
			$this->pending_objects	= array_merge($this->pending_objects, $object_ids);
			$this->pending_objects	= array_unique($this->pending_objects);
		}

		add_filter($this->filter, [$this, 'callback']);
	}
}

class WPJAM_Cache_Group{
	private $group;

	public function __construct($group){
		$this->group	= $group;
	}

	public function cache_get($key){
		return wp_cache_get($key, $this->group);
	}

	public function cache_add($key, $value, $time=DAY_IN_SECONDS){
		return wp_cache_add($key, $value, $this->group, $time);
	}

	public function cache_set($key, $value, $time=DAY_IN_SECONDS){
		return wp_cache_set($key, $value, $this->group, $time);
	}

	public function cache_delete($key){
		return wp_cache_delete($key, $this->group);
	}

	private static $instances	= [];

	public static function get_instance($group){
		if(!isset(self::$instances[$group])){
			self::$instances[$group]	= new self($group);
		}

		return self::$instances[$group];
	}
}

class WPJAM_AJAX{
	use WPJAM_Register_Trait;

	public function create_nonce($args=[]){
		$nonce_action	= $this->name;

		if($this->nonce_keys){
			foreach($this->nonce_keys as $key){
				if(!empty($args[$key])){
					$nonce_action	.= ':'.$args[$key];
				}
			}
		}

		return wp_create_nonce($nonce_action);
	}

	public function verify_nonce($nonce){
		$nonce_action	= $this->name;

		if($this->nonce_keys){
			foreach($this->nonce_keys as $key){
				if($value = wpjam_get_data_parameter($key)){
					$nonce_action	.= ':'.$value;
				}
			}
		}

		return wp_verify_nonce($nonce, $nonce_action);
	}

	public function callback(){
		if(!$this->callback || !is_callable($this->callback)){
			wp_die('0', 400);
		}
		
		$nonce	= wpjam_get_parameter('_ajax_nonce', ['method'=>'POST']);

		if(!$this->verify_nonce($nonce)){
			wpjam_send_json(['errcode'=>'invalid_nonce', 'errmsg'=>'非法操作']);
		}

		$result	= call_user_func($this->callback);

		wpjam_send_json($result);
	}

	public function get_data_attr($data=[], $return=''){
		$attr	= [
			'action'	=> $this->name,
			'nonce'	=> $this->create_nonce($data)
		];

		if($data){
			$attr['data']	= http_build_query($data);
		}

		if($return == ''){
			foreach($attr as $key => &$value){
				$value	= 'data-'.$key.'="'.esc_attr($value).'"';
			}

			return implode(' ', $attr);
		}

		return $attr;
	}

	public  static function get_screen_id(){
		return self::$screen_id;
	}

	public static function set_screen_id(){
		if(isset($_POST['screen_id'])){
			$screen_id	= $_POST['screen_id'];
		}elseif(isset($_POST['screen'])){
			$screen_id	= $_POST['screen'];	
		}else{
			$ajax_action	= $_REQUEST['action'] ?? '';

			if($ajax_action == 'fetch-list'){
				$screen_id	= $_GET['list_args']['screen']['id'];
			}elseif($ajax_action == 'inline-save-tax'){
				$screen_id	= 'edit-'.sanitize_key($_POST['taxonomy']);
			}elseif($ajax_action == 'get-comments'){
				$screen_id	= 'edit-comments';
			}else{
				$screen_id	= false;
			}
		}

		if($screen_id){
			if('-network' === substr($screen_id, -8)){
				if(!defined('WP_NETWORK_ADMIN')){
					define('WP_NETWORK_ADMIN', true);
				}
			}elseif('-user' === substr($screen_id, -5)){
				if(!defined('WP_USER_ADMIN')){
					define('WP_USER_ADMIN', true);
				}
			}
		}

		self::$screen_id	= $screen_id;
	}

	public static $screen_id		= null;
	public static $enqueue_scripts	= null;

	public static function enqueue_scripts(){
		if(isset(self::$enqueue_scripts)){
			return;
		}

		self::$enqueue_scripts	= true;

		$scripts	= '
if(typeof ajaxurl == "undefined"){
	var ajaxurl	= "'.admin_url('admin-ajax.php').'";
}

jQuery(function($){
	if(window.location.protocol == "https:"){
		ajaxurl	= ajaxurl.replace("http://", "https://");
	}

	$.fn.extend({
		wpjam_submit: function(callback){
			let _this	= $(this);
			
			$.post(ajaxurl, {
				action:			$(this).data(\'action\'),
				_ajax_nonce:	$(this).data(\'nonce\'),
				data:			$(this).serialize()
			},function(data, status){
				callback.call(_this, data);
			});
		},
		wpjam_action: function(callback){
			let _this	= $(this);
			
			$.post(ajaxurl, {
				action:			$(this).data(\'action\'),
				_ajax_nonce:	$(this).data(\'nonce\'),
				data:			$(this).data(\'data\')
			},function(data, status){
				callback.call(_this, data);
			});
		}
	});
});
		';

		if(did_action('wpjam_static') && !is_login()){
			wpjam_register_static('wpjam-script',	['title'=>'AJAX 基础脚本', 'type'=>'script',	'source'=>'value',	'value'=>$scripts]);
		}else{
			wp_enqueue_script('jquery');
			wp_add_inline_script('jquery', $scripts);
		}
	}
}

class WPJAM_Map_Meta_Cap{
	use WPJAM_Register_Trait;

	public static function filter($caps, $cap, $user_id, $args){
		if(in_array('do_not_allow', $caps) || empty($user_id)){
			return $caps;
		}

		if($object = self::get($cap)){
			return call_user_func($object->callback, $user_id, $args, $cap);
		}

		return $caps;
	}
}

class WPJAM_Verify_TXT{
	use WPJAM_Register_Trait;

	public function get_data($key=''){
		$data	= wpjam_get_setting('wpjam_verify_txts', $this->name) ?: [];

		return $key ? ($data[$key] ?? '') : $data;
	}

	public function set_data($data){
		return wpjam_update_setting('wpjam_verify_txts', $this->name, $data) || true;
	}

	public function get_fields(){
		$data	= $this->get_data();

		return [
			'name'	=>['title'=>'文件名称',	'type'=>'text',	'required', 'value'=>$data['name'] ?? '',	'class'=>'all-options'],
			'value'	=>['title'=>'文件内容',	'type'=>'text',	'required', 'value'=>$data['value'] ?? '']
		];
	}

	public static function __callStatic($method, $args){
		$name	= $args[0];

		if($object = self::get($name)){
			if(in_array($method, ['get_name', 'get_value'])){
				return $object->get_data(str_replace('get_', '', $method));
			}elseif($method == 'set' || $method == 'set_value'){
				return $object->set_data(['name'=>$args[1], 'value'=>$args[2]]);
			}
		}	
	}

	public static function filter_root_rewrite_rules($root_rewrite){
		if(empty($GLOBALS['wp_rewrite']->root)){
			$home_path	= parse_url(home_url());

			if(empty($home_path['path']) || '/' == $home_path['path']){
				$root_rewrite	= array_merge(['([^/]+)\.txt?$'=>'index.php?module=txt&action=$matches[1]'], $root_rewrite);
			}
		}
		
		return $root_rewrite;
	}

	public static function module($action){
		if($values = wpjam_get_option('wpjam_verify_txts')){
			$name	= str_replace('.txt', '', $action).'.txt';
			
			foreach($values as $key => $value) {
				if($value['name'] == $name){
					header('Content-Type: text/plain');
					echo $value['value'];

					exit;
				}
			}
		}

		wp_die('错误');
	}
}

class WPJAM_Theme_Upgrader{
	use WPJAM_Register_Trait;

	public function filter_site_transient($transient){
		if($this->upgrader_url){
			$theme	= $this->name;
	
			if(empty($transient->checked[$theme])){
				return $transient;
			}
			
			$remote	= get_transient('wpjam_theme_upgrade_'.$theme);

			if(false == $remote){
				$remote = wpjam_remote_request($this->upgrader_url);
		 
				if(!is_wp_error($remote)){
					set_transient('wpjam_theme_upgrade_'.$theme, $remote, HOUR_IN_SECONDS*12);
				}
			}

			if($remote && !is_wp_error($remote)){
				if(version_compare($transient->checked[$theme], $remote['new_version'], '<')){
					$transient->response[$theme]	= $remote;
				}
			}
		}
		
		return $transient;
	}
}

class_alias('WPJAM_Verify_TXT', 'WPJAM_VerifyTXT');