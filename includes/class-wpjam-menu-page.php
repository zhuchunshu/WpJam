<?php
class WPJAM_Menu_Page{
	protected static $menu_pages	= [];
	protected static $is_rendering	= true;
	protected static $page_setting	= null;
	protected static $page_object	= null;
	protected static $queried_menus	= [];

	public  static function add($menu_slug, $args=[]){
		if(is_numeric($menu_slug)){
			return;
		}

		if(!empty($args['parent'])){
			self::$menu_pages[$args['parent']]['subs'][$menu_slug]	= $args;
		}else{
			if(isset(self::$menu_pages[$menu_slug])){
				$current_subs	= self::$menu_pages[$menu_slug]['subs'] ?? [];
				$args['subs']	= $args['subs'] ?? [];
                $args['subs']	= array_merge($current_subs, $args['subs']);
            }

			self::$menu_pages[$menu_slug]	= $args;
		}
	}

	public  static function get_builtin_parents(){
		if(is_multisite() && is_network_admin()){
			$builtin_parents	= [
				'settings'	=> 'settings.php',
				'theme'		=> 'themes.php',
				'themes'	=> 'themes.php',
				'plugins'	=> 'plugins.php',
				'users'		=> 'users.php',
				'sites'		=> 'sites.php',
			];
		}else{
			$builtin_parents	= [
				'dashboard'	=> 'index.php',
				'management'=> 'tools.php',
				'options'	=> 'options-general.php',
				'theme'		=> 'themes.php',
				'themes'	=> 'themes.php',
				'plugins'	=> 'plugins.php',
				'posts'		=> 'edit.php',
				'media'		=> 'upload.php',
				'links'		=> 'link-manager.php',
				'pages'		=> 'edit.php?post_type=page',
				'comments'	=> 'edit-comments.php',
				'users'		=> current_user_can('edit_users') ? 'users.php' : 'profile.php',
			];

			foreach(get_post_types(['_builtin'=>false, 'show_ui'=>true]) as $_post_type) {
				$builtin_parents[$_post_type.'s'] = 'edit.php?post_type='.$_post_type;
			}
		}

		return $builtin_parents;
	}

	public  static function get_menu_pages(){
		$menu_filter	= (is_multisite() && is_network_admin()) ? 'wpjam_network_pages' : 'wpjam_pages';

		return apply_filters($menu_filter, self::$menu_pages);
	}

	public  static function init($plugin_page){
		$GLOBALS['plugin_page']	= $plugin_page;
		self::$is_rendering		= false;
		self::render();
	}

	public  static function is_available($menu_page, $default=false){
		$network	= $menu_page['network'] ?? $default;

		if(is_multisite() && is_network_admin()){
			return (bool)$network;
		}else{
			return $network !== 'only';
		}
	}

	public  static function render(){
		do_action('wpjam_admin_init');

		$builtin_parents	= self::get_builtin_parents();
		$menu_pages			= self::get_menu_pages();

		foreach($menu_pages as $menu_slug => $menu_page){
			if(is_numeric($menu_slug)){
				continue;
			}
		
			if(isset($builtin_parents[$menu_slug])){
				$parent_slug	= $builtin_parents[$menu_slug];
				$admin_page		= $parent_slug;
				$network_default	= false;
			}else{
				if(empty($menu_page['menu_title']) || !self::is_available($menu_page)){
					continue;
				}

				$menu_page		= self::parse_page($menu_slug, $menu_page);
				$parent_slug	= $menu_slug;
				$admin_page		= 'admin.php';
				$network_default	= true;
			}

			if(!empty($menu_page['subs'])){
				$menu_page['subs']	= wpjam_list_sort($menu_page['subs']);

				if($parent_slug	== $menu_slug){
					if(isset($menu_page['subs'][$menu_slug])){
						$menu_page['subs']	= array_merge([$menu_slug=>$menu_page['subs'][$menu_slug]], $menu_page['subs']);
					}else{
						$menu_page['subs']	= array_merge([$menu_slug=>$menu_page], $menu_page['subs']);
					}
				}

				foreach($menu_page['subs'] as $sub_menu_slug => $sub_menu_page){
					if(!self::is_available($sub_menu_page, $network_default)){
						continue;
					}

					$sub_menu_page	= self::parse_page($sub_menu_slug, $sub_menu_page, $parent_slug, $admin_page);

					if(!self::$is_rendering && $GLOBALS['plugin_page'] == $sub_menu_slug){
						break;
					}
				}
			}

			if(!self::$is_rendering && $GLOBALS['plugin_page'] == $menu_slug){
				break;
			}
		}

		if(self::$is_rendering && self::$queried_menus){
			add_filter('wpjam_html', [self::class, 'filter_html']);
		}
	}

	private static function parse_page($menu_slug, $menu_page, $parent_slug='', $admin_page='admin.php'){
		$menu_title	= $menu_page['menu_title'] ?? '';
		$page_title	= $menu_page['page_title'] = $menu_page['page_title'] ?? $menu_title;
		$capability	= $menu_page['capability'] ?? 'manage_options';

		$menu_page['admin_url']	= $admin_url = add_query_arg(['page'=>$menu_slug], $admin_page);

		if(!empty($menu_page['query_args'])){
			$query_data	= self::generate_query_data($menu_page['query_args']);

			if($null_queries = array_filter($query_data, 'is_null')){
				if($GLOBALS['plugin_page'] == $menu_slug){
					wp_die('「'.implode('」,「', array_keys($null_queries)).'」参数无法获取');
				}else{
					return $menu_page;
				}
			}

			$menu_page['query_data']	= $query_data;
			$menu_page['admin_url']		= $queried_url	= add_query_arg($query_data, $admin_url);

			if(self::$is_rendering){
				self::$queried_menus[$menu_slug]	= ['admin_url'=>esc_url($admin_url),	'queried_url'=>$queried_url];
			}
		}

		if(!empty($menu_page['map_meta_cap']) && is_callable($menu_page['map_meta_cap'])){
			wpjam_register_map_meta_cap($capability, $menu_page['map_meta_cap']);
		}

		if(self::$is_rendering){
			if($parent_slug){
				$page_hook	= add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, [self::class, 'admin_page']);
			}else{
				$icon		= $menu_page['icon'] ?? '';
				$position	= $menu_page['position'] ?? '';

				$page_hook	= add_menu_page($page_title, $menu_title, $capability, $menu_slug, [self::class, 'admin_page'], $icon, $position);
			}

			$menu_page['page_hook']	= $page_hook;
		}

		if($GLOBALS['plugin_page'] == $menu_slug && ($parent_slug || ($parent_slug == '' && empty($menu_page['subs'])))){
			$GLOBALS['current_admin_url']	= is_network_admin() ? network_admin_url($menu_page['admin_url']) : admin_url($menu_page['admin_url']);

			self::$page_setting	= $menu_page;
		}

		return $menu_page;
	}

	private static function generate_query_data($query_args){
		$query_data	= [];

		foreach($query_args as $query_arg){
			$query_data[$query_arg]	= wpjam_get_data_parameter($query_arg);
		}

		return $query_data;
	}

	public  static function filter_html($html){
		$search	= $replace = [];

		foreach(self::$queried_menus as $query_menu){
			$search[]	= "<a href='".$query_menu['admin_url']."'";
			$replace[]	= "<a href='".$query_menu['queried_url']."'";
		}

		return str_replace($search, $replace, $html);
	}

	public  static function is_tab(){
		return self::get_setting('function') == 'tab';
	}

	public  static function get_setting($key=''){
		if($key){
			return self::$page_setting[$key] ?? null;
		}else{
			return self::$page_setting;
		}
	}

	public  static function set_setting($key, $value){
		self::$page_setting[$key]	= $value;
	}

	public  static function get_page_setting($key='', $using_tab=false){
		if($key == 'query_data'){
			$value	= self::get_setting('query_data') ?: [];

			if(self::is_tab() && ($query_data = wpjam_get_current_tab_setting('query_data'))){
				$value	= array_merge($value, $query_data);
			}

			return $value ?: [];
		}elseif(in_array($key, ['list_table_name', 'option_name', 'dashboard_name', 'form_name', 'function'])){
			if(self::is_tab()){
				$value	= wpjam_get_current_tab_setting($key);
			}else{
				$value	= self::get_setting($key);
			}

			if($key == 'function'){
				return $value == 'list' ? 'list_table' : $value;
			}else{
				return $value ?: $GLOBALS['plugin_page'];
			}
		}else{
			return ($using_tab && self::is_tab()) ? wpjam_get_current_tab_setting($key) : self::get_setting($key);
		}
	}

	public  static function tab_load(){
		$tabs	= self::get_setting('tabs') ?: [];
		$tabs	= apply_filters(wpjam_get_filter_name($GLOBALS['plugin_page'], 'tabs'), $tabs);

		foreach($tabs as $tab_name => $tab_setting){
			wpjam_register_plugin_page_tab($tab_name, $tab_setting);
		}

		$tabs	= [];

		foreach(WPJAM_Plugin_Page_Tab::get_registereds() as $tab_name => $object){
			if(isset($object->plugin_page) && $object->plugin_page != $GLOBALS['plugin_page']){
				continue;
			}

			if(isset($object->map_meta_cap) && is_callable($object->map_meta_cap)){
				wpjam_register_map_meta_cap($object->capability, $object->map_meta_cap);
			}

			if(isset($object->capability) && !current_user_can($object->capability)){
				continue;
			}

			$tabs[$tab_name]	= $object;
		}

		if(empty($tabs)){
			return new WP_Error('empty_tabs', 'Tabs 未设置');
		}

		$tabs	= wpjam_list_sort($tabs);

		if(self::$is_rendering){
			$current_tab	= wpjam_get_parameter('tab', ['sanitize_callback'=>'sanitize_key', 'default'=>array_keys($tabs)[0]]);
		}else{
			$current_tab	= wpjam_get_parameter('current_tab', ['method'=>'POST', 'sanitize_callback'=>'sanitize_key']);
		}

		$GLOBALS['current_tab']			= $current_tab;
		$GLOBALS['current_admin_url']	= $GLOBALS['current_admin_url'].'&tab='.$current_tab;

		$parsed	= [];

		foreach($tabs as $name => $object){
			if(!empty($object->query_args)){
				$query_data	= self::generate_query_data($object->query_args);

				if($null_queries = array_filter($query_data, 'is_null')){
					if($current_tab == $name){
						wp_die('「'.implode('」,「', array_keys($null_queries)).'」参数无法获取');
					}else{
						continue;
					}
				}else{
					if($current_tab == $name){
						$GLOBALS['current_admin_url']	= add_query_arg($query_data, $GLOBALS['current_admin_url']);
					}
				}

				$object->query_data	= $query_data;
			}

			$parsed[$name]	= $object;
		}

		self::set_setting('tabs', $parsed);

		if(empty($current_tab) || empty($tabs[$current_tab])){
			return new WP_Error('invalid_tab', '无效的 Tab');
		}elseif(empty($tabs[$current_tab]->function)){
			return new WP_Error('empty_tab_function', 'Tab 未设置 function');
		}

		return self::load($tabs[$current_tab]->to_array(), true);
	}

	public  static function load($page_setting, $in_tab=false){
		$function		= $page_setting['function'] ?? null;
		$load_callback	= $page_setting['load_callback'] ?? '';

		if($in_tab){
			if($function == 'tab'){
				wp_die('tab 不能嵌套 tab');
			}

			$load_file	= $page_setting['tab_file'] ?? '';

			do_action('wpjam_plugin_page_load', $GLOBALS['plugin_page'], $GLOBALS['current_tab']);

			if($load_callback && is_callable($load_callback)){
				call_user_func($load_callback, $GLOBALS['current_tab']);
			}
		}else{
			$load_file	= $page_setting['page_file'] ?? '';

			do_action('wpjam_plugin_page_load', $GLOBALS['plugin_page'], '');

			if($load_callback && is_callable($load_callback)){
				call_user_func($load_callback, $GLOBALS['plugin_page']);
			}
		}

		if($load_file && file_exists($load_file)){
			include $load_file;
		}

		if(!empty($page_setting['chart'])){
			WPJAM_Chart::init($page_setting['chart']);
		}

		if($function == 'tab'){
			$result	= self::tab_load();

			if(is_wp_error($result)){
				if(wp_doing_ajax()){
					wpjam_send_json($result);
				}else{
					wpjam_admin_add_error($result);
				}
			}
		}else{
			$page_object	= self::get_page_object();

			if(is_wp_error($page_object)){
				wpjam_admin_add_error($page_object);
			}elseif($page_hook = self::get_page_setting('page_hook')){
				add_action('load-'.$page_hook, [$page_object, 'page_load']);
			}
		}
	}

	public  static function admin_page(){
		echo '<div class="wrap">';

		$page_setting	= self::get_page_setting();

		if(self::is_tab()){
			self::tab_page($page_setting);
		}else{
			self::plugin_page($page_setting);
		}

		echo '</div>';
	}

	public  static function tab_page($page_setting){
		$function	= wpjam_get_filter_name($GLOBALS['plugin_page'], 'page');	// 所有 Tab 页面都执行的函数
		$tabs		= self::get_setting('tabs') ?: [];
		$summary	= $page_setting['summary'] ?? '';
		$tab_count	= count($tabs);

		if($tab_count > 1){
			$page_setting['summary']	= $summary;

			self::page_title($page_setting);

			if(is_callable($function)){
				call_user_func($function);
			}

			echo '<nav class="nav-tab-wrapper wp-clearfix">';

			foreach($tabs as $tab_name => $tab_object){
				$tab_url	= $page_setting['admin_url'].'&tab='.$tab_name;

				if(!empty($tab_object->query_data)){
					$tab_url	= add_query_arg($tab_object->query_data, $tab_url);
				}
				
				$class		= 'nav-tab';
				
				if($GLOBALS['current_tab'] == $tab_name){
					$class	.= ' nav-tab-active';
				}

				$tab_title	= $tab_object->tab_title ?: $tab_object->title;

				echo '<a class="'.$class.'" href="'.$tab_url.'">'.$tab_title.'</a>';
			}

			echo '</nav>';
		}else{
			if(is_callable($function)){
				call_user_func($function);
			}
		}

		if($page_setting = wpjam_get_current_tab_setting()){
			if($tab_count == 1 && $summary && !isset($page_setting['summary'])){
				$page_setting['summary']	= $summary;
			}

			self::plugin_page($page_setting, true);
		}
	}

	public  static function plugin_page($page_setting, $in_tab=false){
		$page_object	= self::get_page_object();

		if(is_wp_error($page_object)){
			self::page_title($page_setting, $in_tab);
		}else{
			if($title = $page_object->title){
				$page_setting['page_title']	= $title;
			}

			if($summary = $page_object->summary){
				$page_setting['summary']	= $summary;
			}

			if(method_exists($page_object, 'get_subtitle')){
				$subtitle	= $page_object->get_subtitle();
			}else{
				$subtitle	= $page_object->subtitle;
			}

			if($subtitle){
				$page_setting['subtitle']	= $subtitle;
			}

			self::page_title($page_setting, $in_tab);

			$page_object->page($page_setting, $in_tab);
		}
	}

	public  static function page_title($page_setting, $in_tab=false){
		$page_title	= $page_setting['page_title'] ?? $page_setting['title'];

		if($page_title){
			$subtitle	= $page_setting['subtitle'] ?? '';

			if($in_tab && count(self::get_setting('tabs')) > 1){
				echo '<h2>'.$page_title.$subtitle.'</h2>';
			}else{
				echo '<h1 class="wp-heading-inline">'.$page_title.'</h1>';
				echo $subtitle;
				echo '<hr class="wp-header-end">';
			}
		}

		$current_tab	= $in_tab ? $GLOBALS['current_tab'] : '';
		$summary		= $page_setting['summary'] ?? '';
		$summary		= apply_filters('wpjam_plugin_page_summary', $summary, $GLOBALS['plugin_page'], $current_tab);

		echo $summary ? wpautop($summary) : '';
	}

	public  static function admin_enqueue_scripts(){
		$screen_base	= $GLOBALS['current_screen']->base;

		if($screen_base == 'customize'){
			return;
		}

		add_thickbox();

		if($screen_base == 'post'){
			$post = get_post();
			if(!$post && !empty($GLOBALS['post_ID'])){
				$post = $GLOBALS['post_ID'];
			}

			wp_enqueue_media(['post'=>$post]);
		}else{
			wp_enqueue_media();
		}

		$ver	= get_plugin_data(WPJAM_BASIC_PLUGIN_FILE)['Version'];

		wp_enqueue_style('wpjam-style',		WPJAM_BASIC_PLUGIN_URL.'static/style.css',	['wp-color-picker', 'editor-buttons'], $ver);

		wp_enqueue_script('wpjam-script',	WPJAM_BASIC_PLUGIN_URL.'static/script.js',	['jquery', 'thickbox', 'wp-backbone', 'jquery-ui-sortable', 'jquery-ui-tooltip', 'jquery-ui-tabs', 'jquery-ui-draggable', 'jquery-ui-slider', 'jquery-ui-autocomplete', 'wp-color-picker'], $ver);
		wp_enqueue_script('wpjam-form',		WPJAM_BASIC_PLUGIN_URL.'static/form.js',	['wpjam-script', 'mce-view'], $ver);

		$setting	= [
			'screen_base'	=> $screen_base,
			'screen_id'		=> $GLOBALS['current_screen']->id,
			'post_type'		=> $GLOBALS['current_screen']->post_type,
			'taxonomy'		=> $GLOBALS['current_screen']->taxonomy,
		];

		$params	= wpjam_array_except($_REQUEST, array_merge(wp_removable_query_args(),['page', 'tab', 'post_type', 'taxonomy', '_wp_http_referer', '_wpnonce']));
		$params	= array_filter($params, function($param){ return !empty($param) || is_numeric($param); });

		if($GLOBALS['plugin_page']){
			$setting['plugin_page']	= $GLOBALS['plugin_page'];
			$setting['current_tab']	= $GLOBALS['current_tab'] ?? null;

			$query_data		= self::get_page_setting('query_data');
			$page_object	= self::get_page_object();

			if(!is_wp_error($page_object)){
				if($query_args = $page_object->query_args){
					$_query_data	= self::generate_query_data($query_args);
					$query_data		= array_merge($query_data, $_query_data);

					$GLOBALS['current_admin_url']	= add_query_arg($_query_data, $GLOBALS['current_admin_url']);
				}

				if($left_key = $page_object->left_key){
					$setting['left_key']	= $left_key;
				}
			}

			if($query_data){
				$params	= wpjam_array_except($params, array_keys($query_data));

				$setting['query_data']	= array_map('sanitize_textarea_field', $query_data);
			}
			
			$setting['admin_url']	= $GLOBALS['current_admin_url'] ?? '';
		}

		$setting['params']	= $params ? array_map('sanitize_textarea_field', $params) : new stdClass();

		if(!empty($GLOBALS['wpjam_list_table'])){
			$setting['list_table']	= ['ajax'=>true, 'form_id'=>($GLOBALS['wpjam_list_table']->form_id ?: 'list_table_form')];

			if($sortable = $GLOBALS['wpjam_list_table']->sortable){
				$setting['list_table']['sortable']	= is_array($sortable) ? wpjam_array_except($sortable, 'action_args') : ['items'=>' >tr'];
			}

			$id	= wpjam_get_parameter('id', ['sanitize_callback'=>'sanitize_text_field']);

			if($id && !$GLOBALS['wpjam_list_table']->current_action()){
				$setting['list_table']['query_id']	= $id;
			}
		}

		wp_localize_script('wpjam-script', 'wpjam_page_setting', $setting);
	}

	public  static function get_page_object(){
		if(is_null(self::$page_object)){
			$page_types	= ['option', 'form', 'list_table', 'dashboard'];

			$function	= self::get_page_setting('function');

			if($function && is_string($function) && in_array($function, $page_types, true)){
				$name	= self::get_page_setting($function.'_name');
				
				self::$page_object	= call_user_func('wpjam_get_'.$function.'_object', $name);
			}else{
				$function	= $function ?: wpjam_get_filter_name($GLOBALS['plugin_page'], 'page');

				self::$page_object	= new WPJAM_Plugin_Page($function);
			}
		}

		return self::$page_object;
	}
}

class WPJAM_Plugin_Page{
	protected $function;

	public function __construct($function){
		$this->function	= $function;
	}

	public function __get($key){
		return null;
	}

	public function __call($method, $args){
		return null;
	}

	public function page_load(){
		if(!is_callable($this->function)){
			wpjam_admin_add_error($this->function.'无效或者不存在', 'error');
		}
	}

	public function page($page_setting, $in_tab=false){
		if(!empty($page_setting['chart'])){
			WPJAM_Chart::form();
		}
		
		if(is_callable($this->function)){
			call_user_func($this->function);
		}
	}
}

class WPJAM_Plugin_Page_Tab{
	use WPJAM_Register_Trait;

	public static function get_current_setting($key=''){
		$current_tab	= $GLOBALS['current_tab'] ?? '';

		if($current_tab && ($object = self::get($current_tab))){
			return $key ? $object->$key : $object->to_array();
		}

		return null;
	}
}

class WPJAM_Page_Action{
	use WPJAM_Register_Trait;

	public static function ajax_response(){
		$action_type	= wpjam_get_parameter('action_type',	['method'=>'POST', 'sanitize_callback'=>'sanitize_key']);
		$action			= wpjam_get_parameter('page_action',	['method'=>'POST']);

		if($action_type != 'form'){
			$nonce	= wpjam_get_parameter('_ajax_nonce',	['method'=>'POST']);

			if(!self::verify_nonce($nonce, $action)){
				wpjam_send_json(['errcode'=>'invalid_nonce',	'errmsg'=>'非法操作']);
			}
		}

		if($instance = self::get($action)){
			if($action_type == 'form'){
				$form	= $instance->get_form();

				if(is_wp_error($form)){
					wpjam_send_json($form);
				}

				$width		= $instance->width ?? 720;
				$page_title	= wpjam_get_parameter('page_title', ['method'=>'POST']);

				if(is_null($page_title)){
					foreach(['page_title', 'button_text', 'submit_text'] as $key){
						if(!empty($instance->$key)){
							$page_title	= $instance->$key;
							break;
						}
					}
				}

				$result	= ['form'=>$form, 'page_title'=>$page_title, 'width'=>$width];
			}else{
				$result	= $instance->callback($action_type);
			}
		}else{
			do_action_deprecated('wpjam_page_action', [$action, $action_type], 'WPJAM Basic 4.6');

			$ajax_response	= wpjam_get_filter_name($GLOBALS['plugin_page'], 'ajax_response');
			$ajax_response	= apply_filters_deprecated('wpjam_page_ajax_response', [$ajax_response, $GLOBALS['plugin_page'], $action, $action_type], 'WPJAM Basic 4.6');

			if(is_callable($ajax_response)){
				$result	= call_user_func($ajax_response, $action);
				$result	= (is_wp_error($result) || is_array($result)) ? $result : [];
			}else{
				$result	= new WP_Error('invalid_ajax_response', '无效的回调函数');
			}
		}

		wpjam_send_json($result);
	}

	public static function ajax_query(){
		$data_type	= wpjam_get_parameter('data_type',	['method'=>'POST']);
		$query_args	= wpjam_get_parameter('query_args',	['method'=>'POST']);

		if($data_type == 'post_type'){
			$query_args['posts_per_page']	= $query_args['posts_per_page'] ?? 10;
			$query_args['post_status']		= $query_args['post_status'] ?? 'publish';

			$query	= wpjam_query($query_args);
			$posts	= array_map(function($post){ return wpjam_get_post($post->ID); }, $query->posts);

			wpjam_send_json(['datas'=>$posts]);
		}elseif($data_type == 'taxonomy'){
			$query_args['number']		= $query_args['number'] ?? 10;
			$query_args['hide_empty']	= $query_args['hide_empty'] ?? 0;
			
			$terms	= wpjam_get_terms($query_args, -1);

			wpjam_send_json(['datas'=>$terms]);
		}elseif($data_type == 'model'){
			$model	= $query_args['model'];

			$query_args	= wpjam_array_except($query_args, ['model', 'label_key', 'id_key']);
			$query_args	= $query_args + ['number'=>10];
			
			$query	= $model::Query($query_args);

			wpjam_send_json(['datas'=>$query->datas]);
		}
	}

	public static function ajax_button($args){
		$args	= wp_parse_args($args, [
			'action'		=> '',
			'data'			=> [],
			'direct'		=> '',
			'confirm'		=> '',
			'button_text'	=> '保存',
			'page_title'	=> '',
			'tag'			=> 'a',
			'nonce'			=> '',
			'class'			=> 'button-primary large',
			'style'			=> ''
		]);

		if(empty($args['action'])){
			return '';
		}

		$title	= $args['page_title'] ?: $args['button_text'];
		$class	= $args['class'] ? (array)$args['class'] : [];
		$class	= array_merge($class, ['wpjam-button']);

		$attr	= self::generate_attr([
			'title'	=> $title,
			'class'	=> $class,
			'style'	=> $args['style'],
			'data'	=> array_merge([
				'action'	=> $args['action'],
				'nonce'		=> $args['nonce'] ?: self::create_nonce($args['action']),
				'data'		=> $args['data'] ? http_build_query($args['data']) : '',
				'title'		=> $title
			], wp_array_slice_assoc($args, ['direct', 'confirm']))
		]);

		if($args['tag'] == 'a'){
			$attr	= 'href="javascript:;" '.$attr;
		}
		
		return '<'.$args['tag'].' '.$attr.'>'.$args['button_text'].'</'.$args['tag'].'>';
	}

	public static function ajax_form($args){
		$args	= wp_parse_args($args, [
			'data_type'		=> 'form',
			'fields_type'	=> 'table',
			'fields'		=> [],
			'data'			=> [],
			'action'		=> '',
			'page_title'	=> '',
			'submit_text'	=> '',
			'nonce'			=> '',
			'form_id'		=> 'wpjam_form'
		]);

		$attr	= self::generate_attr([
			'method'	=> 'post',
			'action'	=> '#',
			'id'		=> $args['form_id'],
			'data'		=> [
				'title'		=> $args['page_title'] ?: $args['submit_text'],
				'action'	=> $args['action'],
				'nonce'		=> $args['nonce'] ?: ($args['action'] ? self::create_nonce($args['action']) : '')
			],
		]);

		$form_fields	= $args['fields'] ? wpjam_fields($args['fields'], array_merge($args, ['echo'=>false])) : '';
		$submit_button	= $args['submit_text'] ? get_submit_button($args['submit_text'], 'primary', 'page_submit') : '';

		return 	'<form '.$attr.'>'.$form_fields.$submit_button.'</form>';
	}

	public static function generate_attr($attr){
		$attr	= array_filter($attr);

		foreach($attr as $attr_key => &$attr_value){
			if($attr_key == 'data'){
				$attr_value	= array_filter($attr_value);

				foreach($attr_value as $k => &$v){
					$v	= 'data-'.$k.'="'.$v.'"';
				}

				$attr_value	= implode(' ', array_values($attr_value));

				unset($v);
			}else{
				if(is_array($attr_value)){
					$attr_value	= implode(' ', array_filter($attr_value));
				}

				$attr_value	= $attr_key.'='.'"'.esc_attr($attr_value).'"';
			}
		}

		return implode(' ', array_values($attr));
	}

	public static function get_nonce_action($key){
		if(isset($GLOBALS['plugin_page'])){
			return $GLOBALS['plugin_page'].'-'.$key;
		}else{
			return get_current_screen()->id.'-'.$key;
		}
	}

	protected static function create_nonce($key){
		return wp_create_nonce(self::get_nonce_action($key));
	}

	protected static function verify_nonce($nonce, $key){
		return wp_verify_nonce($nonce, self::get_nonce_action($key));
	}

	public function current_user_can($type=''){
		$capability	= $this->capability ?? ($type ? 'manage_options' : 'read');

		return current_user_can($capability, $this->name);
	}

	public function callback($action_type){
		if(!$this->current_user_can($action_type)){
			return new WP_Error('bad_authentication', '无权限');
		}

		if(!$this->callback || !is_callable($this->callback)){
			return new WP_Error('invalid_ajax_callback', '无效的回调函数');
		}

		if($this->validate){
			$defaults	= wpjam_get_parameter('defaults',	['method'=>'POST', 'sanitize_callback'=>'wp_parse_args', 'default'=>[]]);
			$data		= wpjam_get_parameter('data',		['method'=>'POST', 'sanitize_callback'=>'wp_parse_args', 'default'=>[]]);
			$data		= wpjam_array_merge($defaults, $data);

			if($fields	= $this->get_fields()){
				$data	= wpjam_validate_fields_value($fields, $data);

				if(is_wp_error($data)){
					return $data;
				}
			}

			$result	= call_user_func($this->callback, $data, $this->name);
		}else{
			$result	= call_user_func($this->callback, $this->name);
		}

		if(is_wp_error($result)){
			return $result;
		}elseif(!$result){
			return new WP_Error('error_ajax_callback', '回调函数返回错误');
		}

		$response	= ['type'=>$this->response];

		if(is_array($result)){
			$response	= array_merge($response, $result);
		}elseif($result !== true){
			if($this->response == 'redirect'){
				$response['url']	= $result;
			}else{
				$response['data']	= $result;
			}
		}

		return apply_filters('wpjam_ajax_response', $response);
	}

	public function get_button($args=[]){
		if(!$this->current_user_can()){
			return '';
		}

		$button	= $this->ajax_button(array_merge($this->args, $args, ['action'=>$this->name]));

		return empty($args['wrap']) ? $button : sprintf($args['wrap'], esc_attr($this->name), $button);
	}

	public function get_fields($args=[]){
		$args	= array_merge($this->args, $args);
		$fields	= $args['fields'] ?? [];

		if($fields && is_callable($fields)){
			$fields	= call_user_func($fields, $this->name);
		}

		return $fields;
	}

	public function get_data($args=[]){
		$args	= array_merge($this->args, $args);
		$data	= $args['data'] ?? [];

		if(!empty($args['data_callback']) && is_callable($args['data_callback'])){
			$_data	= call_user_func($args['data_callback'], $this->name, $args['fields']);

			if(is_wp_error($_data)){
				return $_data;
			}

			$data	= array_merge($data, $_data);
		}

		return $data;
	}

	public function get_form($args=[]){
		if(!$this->current_user_can()){
			return '';
		}

		$args	= array_merge($this->args, $args, ['action'=>$this->name]);

		$args['fields']	= $this->get_fields($args);

		if(is_wp_error($args['fields'])){
			return $args['fields'];
		}

		$args['data']	= $this->get_data($args);

		if(is_wp_error($args['data'])){
			return $args['data'];
		}

		return $this->ajax_form($args);
	}

	public function page_load(){}

	public function page($page_setting, $in_tab=false){
		$form	= $this->get_form();

		if(is_wp_error($form)){
			wp_die($form);
		}else{
			echo $form;
		}
	}
}

class WPJAM_Dashboard_Setting{
	use WPJAM_Register_Trait;

	public function page_load(){
		require_once ABSPATH . 'wp-admin/includes/dashboard.php';
		// wp_dashboard_setup();

		wp_enqueue_script('dashboard');

		if(wp_is_mobile()) {
			wp_enqueue_script('jquery-touch-punch');
		}
	}

	public function page($page_setting, $in_tab=false){
		if($this->welcome_panel && is_callable($this->welcome_panel)){
			echo '<div id="welcome-panel" class="welcome-panel">';
			call_user_func($this->welcome_panel, $this->name);
			echo '</div>';
		}

		if($this->widgets){
			foreach($this->widgets as $widget_id => $meta_box){
				wpjam_register_dashboard_widget($widget_id, $meta_box);
			}
		}

		foreach(wpjam_list_sort(WPJAM_Dashboard_Widget::get_registereds()) as $widget_id => $widget_object){
			if(!isset($widget_object->dashboard) || $widget_object->dashboard == $this->name){
				$title		= $widget_object->title;
				$callback	= $widget_object->callback ?? wpjam_get_filter_name($widget_id, 'dashboard_widget_callback');
				$context	= $widget_object->context ?? 'normal';	// 位置，normal 左侧, side 右侧
				$priority	= $widget_object->priority ?? 'core';
				$args		= $widget_object->args ?? [];

				add_meta_box($widget_id, $title, $callback, get_current_screen(), $context, $priority, $args);
			}
		}

		echo '<div id="dashboard-widgets-wrap">';
		wp_dashboard();
		echo '</div>';
	}
}

class WPJAM_Dashboard_Widget{
	use WPJAM_Register_Trait;
}