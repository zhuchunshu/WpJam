<?php
class WPJAM_Route{
	private $module	= '';
	private $action	= '';

	public function __construct(){
		$GLOBALS['wp']->add_query_var('module');
		$GLOBALS['wp']->add_query_var('action');
		$GLOBALS['wp']->add_query_var('term_id');

		add_action('send_headers',	[$this, 'send_headers']);
	}

	public function send_headers($wp){
		$module	= $wp->query_vars['module'] ?? '';
		$action = $wp->query_vars['action'] ?? '';

		if($module){
			$this->module	= $module;
			$this->action	= $action;

			if($module_obj = wpjam_get_route_module($module)){
				$module_obj->callback($action);
			}

			remove_action('template_redirect', 'redirect_canonical');

			add_filter('template_include', [$this, 'filter_template']);
		}

		wpjam_parse_query_vars($wp);
	}

	public function filter_template(){
		$template	= $this->action ? $this->action.'.php' : 'index.php';
		$template	= apply_filters('wpjam_template', STYLESHEETPATH.'/template/'.$this->module.'/'.$template, $this->module, $this->action);

		if(!is_file($template)){
			wp_die('路由错误！');
		}

		return $template;
	}

	public function is_module($module='', $action=''){
		if($module && $action){
			return $module == $this->module && $action == $this->action;
		}elseif($module){
			return $module == $this->module;
		}elseif($this->module){
			return true;
		}else{
			return false;
		}
	}

	public static function parse_query_vars($wp){
		$tax_query	= $wp->query_vars['tax_query'] ?? [];
		$date_query	= $wp->query_vars['date_query'] ?? [];

		$taxonomies	= array_values(get_taxonomies(['_builtin'=>false]));

		foreach(array_merge($taxonomies, ['category', 'post_tag']) as $taxonomy){
			$query_key	= wpjam_get_taxonomy_query_key($taxonomy);

			if($term_id	= wpjam_array_pull($wp->query_vars, $query_key)){
				if($term_id == -1){
					$tax_query[]	= ['taxonomy'=>$taxonomy,	'field'=>'term_id',	'operator'=>'NOT EXISTS'];
				}else{
					if(in_array($taxonomy, ['category', 'post_tag'])){
						$wp->query_vars[$query_key]	= $term_id;
					}else{
						$tax_query[]	= ['taxonomy'=>$taxonomy,	'field'=>'term_id',	'terms'=>[$term_id]];
					}
				}
			}
		}

		if(!empty($wp->query_vars['taxonomy']) && empty($wp->query_vars['term'])){
			if($term_id	= wpjam_array_pull($wp->query_vars, 'term_id')){
				if(is_numeric($term_id)){
					$tax_query[]	= ['taxonomy'=>wpjam_array_pull($wp->query_vars, 'taxonomy'),	'field'=>'term_id',	'terms'=>[$term_id]];
				}else{
					$wp->set_query_var('term', $term_id);
				}
			}
		}

		if($cursor = wpjam_array_pull($wp->query_vars, 'cursor')){
			$date_query[]	= ['before' => get_date_from_gmt(date('Y-m-d H:i:s', $cursor))];
		}

		if($since = wpjam_array_pull($wp->query_vars, 'since')){
			$date_query[]	= ['after' => get_date_from_gmt(date('Y-m-d H:i:s', $since))];
		}

		if($tax_query){
			$wp->query_vars['tax_query']	= $tax_query;
		}

		if($date_query){
			$wp->query_vars['date_query']	= $date_query;
		}
	}
}

class WPJAM_Route_Module{
	use WPJAM_Register_Trait;

	public function callback($action){
		call_user_func($this->callback, $action, $this->name);
	}
}

function wpjam_register_route_module($name, $args){
	WPJAM_Route_Module::register($name, $args);
}

function wpjam_unregister_route_module($name){
	WPJAM_Route_Module::unregister($name);
}

function wpjam_get_route_module($name){
	return WPJAM_Route_Module::get($name);
}

function wpjam_parse_query_vars($wp){
	WPJAM_Route::parse_query_vars($wp);
}

add_filter('map_meta_cap',	['WPJAM_Map_Meta_Cap', 'filter'], 10, 4);

add_filter('register_taxonomy_args',		['WPJAM_Taxonomy', 'filter_register_args'], 10, 2);
add_filter('wpjam_data_type_field_value',	['WPJAM_Taxonomy', 'filter_data_type_field_value'], 1, 2);

add_filter('post_password_required', 		['WPJAM_Post_Type', 'filter_post_password_required'], 10, 2);
add_filter('wpjam_data_type_field_value',	['WPJAM_Post_Type', 'filter_data_type_field_value'], 1, 2);

add_action('registered_post_type',	['WPJAM_Post_Type', 'on_registered'], 1, 2);
add_action('registered_taxonomy',	['WPJAM_Taxonomy', 'on_registered'], 1, 3);

add_action('init',	function(){
	$GLOBALS['wpjam_route'] = new WPJAM_Route();

	add_rewrite_rule($GLOBALS['wp_rewrite']->root.'api/([^/]+)/(.*?)\.json?$',	'index.php?module=json&action=mag.$matches[1].$matches[2]', 'top');
	add_rewrite_rule($GLOBALS['wp_rewrite']->root.'api/([^/]+)\.json?$',		'index.php?module=json&action=$matches[1]', 'top');

	add_filter('root_rewrite_rules',	['WPJAM_Verify_TXT', 'filter_root_rewrite_rules']);

	wpjam_register_route_module('json', ['callback'=>['WPJAM_JSON', 'module']]);
	wpjam_register_route_module('txt',	['callback'=>['WPJAM_Verify_TXT', 'module']]);

	wpjam_register_platform('weapp',	['bit'=>1,	'order'=>4,		'title'=>'小程序',	'verify'=>'is_weapp']);
	wpjam_register_platform('weixin',	['bit'=>2,	'order'=>4,		'title'=>'微信网页',	'verify'=>'is_weixin']);
	wpjam_register_platform('mobile',	['bit'=>4,	'order'=>8,		'title'=>'移动网页',	'verify'=>'wp_is_mobile']);
	wpjam_register_platform('web',		['bit'=>8,	'order'=>10,	'title'=>'网页',		'verify'=>'__return_true']);
	wpjam_register_platform('template',	['bit'=>8,	'order'=>10,	'title'=>'网页',		'verify'=>'__return_true']);

	wpjam_register_lazyloader('user',		['filter'=>'wpjam_get_userdata',	'callback'=>'cache_users']);
	wpjam_register_lazyloader('post_meta',	['filter'=>'get_post_metadata',		'callback'=>'update_postmeta_cache']);
	wpjam_register_lazyloader('post_term',	['filter'=>'loop_start',			'callback'=>'update_object_term_cache',	'accepted_args'=>2]);

	foreach (WPJAM_Post_Type::get_registereds() as $name=>$post_type_object) {
		if(is_admin() && $post_type_object->show_ui){
			add_filter('post_type_labels_'.$name, ['WPJAM_Post_Type', 'filter_labels']);
		}

		register_post_type($name, $post_type_object->to_array());
	}

	foreach(WPJAM_Taxonomy::get_registereds() as $name => $taxonomy_object){
		if(is_admin() && $taxonomy_object->show_ui){
			add_filter('taxonomy_labels_'.$name,	['WPJAM_Taxonomy', 'filter_labels']);
		}

		register_taxonomy($name, $taxonomy_object->object_type, $taxonomy_object->to_array());
	}

	add_filter('posts_clauses',		['WPJAM_Post_Type', 'filter_clauses'], 1, 2);
	add_filter('post_type_link',	['WPJAM_Post_Type', 'filter_link'], 1, 2);
	add_filter('pre_term_link',		['WPJAM_Taxonomy', 'filter_link'], 1, 2);
});

add_action('wpjam_api', function($json){
	if(wpjam_get_api($json)){
		return;
	}

	if($json == 'media.upload'){
		return wpjam_register_api('media.upload',	['modules'=>['type'=>'media',	'args'=>['media'=>'media']]]);
	}elseif($json == 'post.list'){
		$modules	= [];
		$post_type	= wpjam_get_parameter('post_type');
		
		$modules[]	= [
			'type'	=> 'post_type',
			'args'	=> ['post_type'=>$post_type, 'action'=>'list', 'posts_per_page'=>10, 'output'=>'posts']
		];

		if($post_type && is_string($post_type) && get_post_type_object($post_type)){
			foreach(get_object_taxonomies($post_type, 'objects') as $taxonomy=>$tax_obj){
				if($tax_obj->hierarchical){
					$modules[]	= ['type'=>'taxonomy',	'args'=>['taxonomy'=>$taxonomy, 'hide_empty'=>0]];
				}
			}
		}

		return wpjam_register_api('post.list',	['modules'=>$modules]);
	}elseif($json == 'post.get'){
		return wpjam_register_api('post.get',	['modules'=>['type'=>'post_type',	'args'=>['action'=>'get', 'output'=>'post']]]);
	}
});

add_filter('determine_current_user', function($user_id){
	if(empty($user_id)){
		$wpjam_user	= wpjam_get_current_user();

		if($wpjam_user && !empty($wpjam_user['user_id'])){
			return $wpjam_user['user_id'];
		}
	}

	return $user_id;
});

add_filter('wp_get_current_commenter', function($commenter){
	if(empty($commenter['comment_author_email'])){
		$wpjam_user	= wpjam_get_current_user();

		if($wpjam_user && !empty($wpjam_user['user_email'])){
			$commenter['comment_author_email']	= $wpjam_user['user_email'];
			$commenter['comment_author']		= $wpjam_user['nickname'];
		}
	}

	return $commenter;
});

if(wpjam_is_json_request()){
	remove_filter('the_title', 'convert_chars');

	remove_action('init', 'wp_widgets_init', 1);
	remove_action('init', 'maybe_add_existing_user_to_blog');
	remove_action('init', 'check_theme_switched', 99);

	remove_action('plugins_loaded', '_wp_customize_include');

	remove_action('wp_loaded', '_custom_header_background_just_in_time');
}

// 加载各种扩展
include __DIR__.'/wpjam-basic.php';		// 基础设置
include __DIR__.'/wpjam-notice.php';	// 消息通知
include __DIR__.'/wpjam-cdn.php';		// CDN 处理
include __DIR__.'/wpjam-thumbnail.php';	// 缩略图处理
include __DIR__.'/wpjam-grant.php';		// 接口授权
include __DIR__.'/wpjam-crons.php';		// 定时作业
include __DIR__.'/wpjam-hooks.php';		// 基本优化
include __DIR__.'/wpjam-bind.php';		// 登录绑定
include __DIR__.'/wpjam-compat.php';	// 兼容代码

if(is_admin()){
	include __DIR__.'/wpjam-menus.php';
	include __DIR__.'/wpjam-builtins.php';
}