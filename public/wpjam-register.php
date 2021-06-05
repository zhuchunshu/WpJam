<?php
// 注册接口
function wpjam_register_json($name, $args=[]){
	if(WPJAM_JSON::get($name)){
		trigger_error('API 「'.$name.'」已经注册。');
	}

	$args	= apply_filters('wpjam_register_api_args', $args, $name);

	WPJAM_JSON::register($name, $args);
}

function wpjam_register_api($name, $args=[]){
	wpjam_register_json($name, $args);
}

function wpjam_get_json_object($name){
	return WPJAM_JSON::get($name);
}

function wpjam_get_api($name){
	return wpjam_get_json_object($name);
}

function wpjam_is_json_request(){
	return WPJAM_JSON::is_request();
}

function wpjam_get_json(){
	return wpjam_get_current_json();
}

function wpjam_get_current_json(){
	return WPJAM_JSON::get_current();
}


// 注册后台选项
function wpjam_register_option($name, $args=[]){
	$args	= apply_filters('wpjam_register_option_args', $args, $name);

	WPJAM_Option_Setting::register($name, $args);
}

function wpjam_unregister_option($name){
	WPJAM_Option_Setting::unregister($name);
}

function wpjam_get_option_setting($name){
	return WPJAM_Option_Setting::get_args($name);
}


// 添加后台菜单
function wpjam_add_menu_page($menu_slug, $args=[]){
	if(is_admin()){
		WPJAM_Menu_Page::add($menu_slug, $args);
	}
}

// 注册 Meta 类型
function wpjam_register_meta_type($name, $args=[]){
	WPJAM_Meta_Type::register($name, $args);
}

function wpjam_get_meta_type_object($name){
	return WPJAM_Meta_Type::get($name);
}


// 注册文章类型
function wpjam_register_post_type($name, $args=[]){
	$args	= wp_parse_args($args, [
		'public'			=> true,
		'show_ui'			=> true,
		'hierarchical'		=> false,
		'rewrite'			=> true,
		'permastruct'		=> false,
		'thumbnail_size'	=> '',
		'supports'			=> ['title'],
		'taxonomies'		=> [],
	]);

	if(empty($args['taxonomies'])){
		unset($args['taxonomies']);
	}

	if($args['hierarchical']){
		$args['supports'][]	= 'page-attributes';

		if($args['permastruct'] && (strpos($args['permastruct'], '%post_id%') || strpos($args['permastruct'], '%'.$name.'_id%'))){
			$args['permastruct']	= false;
		}
	}else{
		if($args['permastruct'] && (strpos($args['permastruct'], '%post_id%') || strpos($args['permastruct'], '%'.$name.'_id%'))){
			$args['permastruct']	= str_replace('%post_id%', '%'.$name.'_id%', $args['permastruct']);
			$args['query_var']		= $args['query_var'] ?? false;
		}
	}

	if($args['permastruct'] && empty($args['rewrite'])){
		$args['rewrite']	= true;
	}

	if($args['rewrite']){
		if(is_array($args['rewrite'])){
			$args['rewrite']	= wp_parse_args($args['rewrite'], ['with_front'=>false, 'feeds'=>false]);
		}else{
			$args['rewrite']	= ['with_front'=>false, 'feeds'=>false];
		}
	}

	WPJAM_Post_Type::register($name, $args);
}


// 注册文章选项
function wpjam_register_post_option($meta_box, $args=[]){
	if(WPJAM_Post_Option::get($meta_box)){
		trigger_error('Post Option 「'.$meta_box.'」已经注册。');
	}

	$args	= apply_filters('wpjam_register_post_option_args', $args, $meta_box);

	WPJAM_Post_Option::register($meta_box, $args);
}

function wpjam_unregister_post_option($meta_box){
	WPJAM_Post_Option::unregister($meta_box);
}


// 注册分类模式
function wpjam_register_taxonomy($name, $args=[]){
	if(empty($args['object_type'])){
		return;
	}

	if(isset($args['args'])){
		$args	= array_merge($args['args'], ['object_type'=>$args['object_type']]);
	}

	$args = wp_parse_args($args, [
		'permastruct'		=> null,
		'rewrite'			=> true,
		'show_ui'			=> true,
		'show_in_nav_menus'	=> false,
		'show_admin_column'	=> true,
		'hierarchical'		=> true,
	]);

	if($args['permastruct'] && empty($args['rewrite'])){
		$args['rewrite']	= true;
	}

	if($args['rewrite']){
		if(is_array($args['rewrite'])){
			$args['rewrite']	= wp_parse_args($args['rewrite'], ['with_front'=>false, 'feed'=>false, 'hierarchical'=>false]);
		}else{
			$args['rewrite']	= ['with_front'=>false, 'feed'=>false, 'hierarchical'=>false];
		}
	}

	WPJAM_Taxonomy::register($name, $args);
}

// 注册分类选项
function wpjam_register_term_option($key, $args=[]){
	if(WPJAM_Term_Option::get($key)){
		trigger_error('Term Option 「'.$key.'」已经注册。');
	}

	WPJAM_Term_Option::register($key, apply_filters('wpjam_register_term_option_args', $args, $key));
}

function wpjam_unregister_term_option($key){
	WPJAM_Term_Option::unregister($key);
}

// 注册绑定
function wpjam_register_bind($name, $appid, $args){
	WPJAM_Bind_Type::register($name, $appid, $args);
}

function wpjam_get_bind_object($name, $appid){
	return WPJAM_Bind_Type::get($name, $appid);
}

// 注册 AJAX
function wpjam_register_ajax($name, $args){
	WPJAM_AJAX::register($name, $args);
}

function wpjam_get_ajax_object($name){
	return WPJAM_AJAX::get($name);
}

function wpjam_ajax_enqueue_scripts(){
	WPJAM_AJAX::enqueue_scripts();
}


// 注册平台
function wpjam_register_platform($key, $args){
	WPJAM_Platform::register($key, $args);
}

function wpjam_is_platform($platform){
	return WPJAM_Platform::get($platform)->verify();
}

function wpjam_get_current_platform($platforms=[], $type='key'){
	return WPJAM_Platform::get_current($platforms, $type);
}


// 注册验证 txt
function wpjam_register_verify_txt($key, $args){
	WPJAM_Verify_TXT::register($key, $args);
}


// 注册路径
function wpjam_register_path($page_key, ...$args){
	if(count($args) == 2){
		WPJAM_Path::register($page_key, $args[0], $args[1]);
	}else{
		$args	= $args[0];
		$args	= wp_is_numeric_array($args) ? $args : [$args];

		foreach($args as $item){
			$path_type	= wpjam_array_pull($item, 'path_type');

			WPJAM_Path::register($page_key, $path_type, $item);
		}
	}
}

function wpjam_unregister_path($page_key, $path_type=''){
	if($path_type){
		if($path_obj = WPJAM_Path::get($page_key)){
			$path_obj->remove_type($path_type);
		}
	}else{
		WPJAM_Path::unregister($page_key);
	}
}

function wpjam_get_path_object($page_key){
	return WPJAM_Path::get($page_key);
}

function wpjam_get_paths($path_type){
	return WPJAM_Path::get_by(['path_type'=>$path_type]);
}

function wpjam_get_tabbar_options($path_type){
	return WPJAM_Path::get_tabbar_options($path_type);
}

function wpjam_get_path_fields($path_type, $for=''){
	return WPJAM_Path::get_path_fields($path_type, $for);
}

function wpjam_get_page_keys($path_type){
	return WPJAM_Path::get_page_keys($path_type);
}

function wpjam_get_path($path_type, $page_key, $args=[]){
	$path_obj	= wpjam_get_path_obj($page_key);

	return $path_obj ? $path_obj->get_path($path_type, $args) : '';
}

function wpjam_parse_path_item($item, $path_type, $parse_backup=true){
	$parsed	= WPJAM_Path::parse_item($item, $path_type);

	if(empty($parsed) && $parse_backup && !empty($item['page_key_backup'])){
		$parsed	= WPJAM_Path::parse_item($item, $path_type, true);
	}

	return $parsed ?: ['type'=>'none'];
}

function wpjam_validate_path_item($item, $path_types){
	return WPJAM_Path::validate_item($item, $path_types);
}

function wpjam_get_path_item_link_tag($parsed, $text){
	return WPJAM_Path::get_item_link_tag($parsed, $text);
}