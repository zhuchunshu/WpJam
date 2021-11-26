<?php
if(!class_exists('WP_List_Table')){
	include ABSPATH.'wp-admin/includes/class-wp-list-table.php';
}

include WPJAM_BASIC_PLUGIN_DIR.'includes/class-wpjam-list-table.php';
include WPJAM_BASIC_PLUGIN_DIR.'includes/class-wpjam-builtin-list-table.php';
include WPJAM_BASIC_PLUGIN_DIR.'includes/class-wpjam-menu-page.php';
include WPJAM_BASIC_PLUGIN_DIR.'includes/class-wpjam-builtin-page.php';
include WPJAM_BASIC_PLUGIN_DIR.'includes/class-wpjam-chart.php';

function wpjam_admin_load($current_screen=null){
	if($page_setting = wpjam_get_plugin_page_setting()){
		WPJAM_Menu_Page::load($page_setting);
	}else{
		WPJAM_Builtin_Page::load();
	}
}

function wpjam_get_plugin_page_setting($key='', $using_tab=false){
	return WPJAM_Menu_Page::get_page_setting($key, $using_tab);
}

function wpjam_set_plugin_page_summary($summary, $append=true){
	$original	= $append ? (string)WPJAM_Menu_Page::get_setting('summary') : '';

	WPJAM_Menu_Page::set_setting('summary', $original.$summary);
}

function wpjam_set_builtin_page_summary($summary, $append=true){
	WPJAM_Builtin_Page::get_instance()->set_summary($summary, $append);
}

function wpjam_get_plugin_page_query_data(){
	return wpjam_get_plugin_page_setting('query_data') ?: [];
}

function wpjam_admin_tooltip($text, $tooltip){
	return '<div class="wpjam-tooltip">'.$text.'<div class="wpjam-tooltip-text">'.wpautop($tooltip).'</div></div>';
}

function wpjam_get_referer(){
	$referer	= wp_get_original_referer();
	$referer	= $referer ?: wp_get_referer();

	$removable_query_args	= array_merge(wp_removable_query_args(), ['_wp_http_referer', 'action', 'action2', '_wpnonce']);

	return remove_query_arg($removable_query_args, $referer);	
}

function wpjam_register_page_action($name, $args){
	if(WPJAM_Page_Action::get($name)){
		trigger_error('Page Action 「'.$name.'」已经注册。');
	}

	$args	= wp_parse_args($args, ['response'=>$name, 'direct'=>false, 'fields'=>[]]);
	$args	= apply_filters('wpjam_register_page_action_args', $args, $name);

	return WPJAM_Page_Action::register($name, $args);
}

function wpjam_unregister_page_action($name){
	WPJAM_Page_Action::unregister($name);
}

function wpjam_get_form_object($name){
	$object	= WPJAM_Page_Action::get($name);

	if(!$object){
		if(!wpjam_get_plugin_page_setting('callback', true)){
			return new WP_Error('page_action_unregistered', 'Page Action 「'.$name.'」 未注册');
		}

		$args	= wpjam_get_plugin_page_setting('', true);
		$object	= wpjam_register_page_action($name, $args);
	}

	return $object;
}

function wpjam_get_page_form($name, $args=[]){
	$instance	= WPJAM_Page_Action::get($name);
	return $instance ? $instance->get_form($args) : '';
}

function wpjam_get_page_button($name, $args=[]){
	$instance	= WPJAM_Page_Action::get($name);
	return $instance ? $instance->get_button($args) : '';
}

function wpjam_get_option_object($name){
	$object	= WPJAM_Option_Setting::get($name);

	if(!$object){
		if($model = wpjam_get_plugin_page_setting('model', true)){
			$args	= wpjam_get_plugin_page_setting('', true);
			$object	= call_user_func([$model, 'register_option'], $args);
		}else{
			if(wpjam_get_plugin_page_setting('sections', true) || wpjam_get_plugin_page_setting('fields', true)){
				$args	= wpjam_get_plugin_page_setting('', true);
			}else{
				$args	= apply_filters(wpjam_get_filter_name($name, 'setting'), []);

				if(!$args){
					return new WP_Error('option_setting_unregistered', 'Option「'.$name.'」 未注册');
				}
			}	

			$object	= wpjam_register_option($name, $args);
		}
	}

	if(wp_doing_ajax()){
		add_action('wp_ajax_wpjam-option-action',	[$object, 'ajax_response']);
	}else{
		add_action('admin_action_update', [$object, 'register_settings']);
	}

	return $object;
}

function wpjam_register_list_table($name, $args=[]){
	if(WPJAM_List_Table_Setting::get($name)){
		trigger_error('List Table 「'.$name.'」已经注册。');
	}

	return WPJAM_List_Table_Setting::register($name, $args);
}

function wpjam_get_list_table_object($name, $args=[]){
	$object	= WPJAM_List_Table_Setting::get($name);

	if(!$object){
		if(wpjam_get_plugin_page_setting('model', true)){
			$args	= wpjam_get_plugin_page_setting('', true);
		}else{
			$args	= apply_filters(wpjam_get_filter_name($name, 'list_table'), []);

			if(!$args){
				return new WP_Error('list_table_unregistered', 'List Table 未注册');
			}
		}

		$object	= wpjam_register_list_table($name, $args);
	}
	
	if(empty($object->model) || !class_exists($object->model)){
		return  new WP_Error('invalid_list_table_model', 'List Table 的 Model '.$object->model.'未定义或不存在');
	}

	$args	= wp_parse_args($object->to_array(), ['primary_key'=>'id', 'name'=>$name, 'singular'=>$name, 'plural'=>$name.'s']);

	if($object->layout == 'left' || $object->layout == '2'){
		$args['layout']	= 'left';

		return new WPJAM_Left_List_Table($args);
	}elseif($object->layout == 'calendar'){
		return new WPJAM_Calendar_List_Table($args);
	}else{
		return new WPJAM_List_Table($args);
	}
}

function wpjam_register_list_table_action($name, $args){
	if(WPJAM_List_Table_Action::get($name)){
		trigger_error('List Table Action 「'.$name.'」已经注册。');
	}

	$args	= apply_filters('wpjam_register_list_table_action_args', $args, $name);

	return WPJAM_List_Table_Action::register($name, $args);
}

function wpjam_unregister_list_table_action($name){
	WPJAM_List_Table_Action::unregister($name);
}

function wpjam_register_list_table_column($name, $field){
	if(WPJAM_List_Table_Column::get($name)){
		trigger_error('List Table Column 「'.$name.'」已经注册。');
	}

	$field	= wp_parse_args($field, ['type'=>'view', 'show_admin_column'=>true]);

	return WPJAM_List_Table_Column::register($name, $field);
}

function wpjam_unregister_list_table_column($name){
	WPJAM_List_Table_Column::unregister($name);
}

function wpjam_register_plugin_page_tab($name, $args){
	$name	= sanitize_key($name);

	return WPJAM_Plugin_Page_Tab::register($name, $args);
}

function wpjam_unregister_plugin_page_tab($name){
	WPJAM_Plugin_Page_Tab::unregister($name);
}

function wpjam_get_current_tab_setting($key=''){
	return WPJAM_Plugin_Page_Tab::get_current_setting($key);
}

function wpjam_get_list_table_filter_link($filters, $title, $class=''){
	return $GLOBALS['wpjam_list_table']->get_filter_link($filters, $title, $class);
}

function wpjam_get_list_table_row_action($action, $args=[]){
	return $GLOBALS['wpjam_list_table']->get_row_action($action, $args);
}

function wpjam_render_list_table_column_items($id, $items, $args=[]){
	return $GLOBALS['wpjam_list_table']->render_column_items($id, $items, $args);
}

function wpjam_call_list_table_model_method($method, ...$args){
	return $GLOBALS['wpjam_list_table']->call_model_method($method, ...$args);
}

function wpjam_register_dashboard($name, $args){
	return WPJAM_Dashboard_Setting::register($name, $args);
}

function wpjam_unregister_dashboard($name){
	WPJAM_Dashboard_Setting::unregister($name);
}

function wpjam_get_dashboard_object($name){
	$object = WPJAM_Dashboard_Setting::get($name);

	if(!$object){
		if(!wpjam_get_plugin_page_setting('widgets', true)){
			return new WP_Error('dashboard_unregistered', 'Dashboard 「'.$name.'」 未注册');
		}

		$args	= wpjam_get_plugin_page_setting('', true);
		$object	= wpjam_register_dashboard($name, $args);
	}

	return $object;
}

function wpjam_register_dashboard_widget($name, $args){
	return WPJAM_Dashboard_Widget::register($name, $args);
}

function wpjam_unregister_dashboard_widget($name){
	WPJAM_Dashboard_Widget::unregister($name);
}

function wpjam_get_admin_post_id(){
	return WPJAM_Post_Page::get_post_id();
}

function wpjam_line_chart($counts_array, $labels, $args=[], $type = 'Line'){
	WPJAM_Chart::line($counts_array, $labels, $args, $type);
}

function wpjam_bar_chart($counts_array, $labels, $args=[]){
	wpjam_line_chart($counts_array, $labels, $args, 'Bar');
}

function wpjam_donut_chart($counts, $args=[]){
	WPJAM_Chart::donut($counts, $args);
}

function wpjam_get_chart_parameter($key){
	return WPJAM_Chart::get_parameter($key);
}

if(wp_doing_ajax()){
	add_action('plugins_loaded', ['WPJAM_AJAX', 'set_screen_id'], 1);
}

add_action('wp_loaded', function(){	// 内部的 hook 使用 优先级 9，因为内嵌的 hook 优先级要低
	if($GLOBALS['pagenow'] == 'options.php'){
		// 为了实现多个页面使用通过 option 存储。这个可以放弃了，使用 AJAX + Redirect
		// 注册设置选项，选用的是：'admin_action_' . $_REQUEST['action'] hook，
		// 因为在这之前的 admin_init 检测 $plugin_page 的合法性
		add_action('admin_action_update', function(){
			add_action('current_screen',	'wpjam_admin_load', 9);

			$referer_origin	= parse_url(wpjam_get_referer());

			if(!empty($referer_origin['query'])){
				$referer_args	= wp_parse_args($referer_origin['query']);

				if(!empty($referer_args['page'])){
					WPJAM_Menu_Page::init($referer_args['page']);	// 实现多个页面使用同个 option 存储。

					set_current_screen($_POST['screen_id']);
				}
			}
		}, 9);
	}elseif(wp_doing_ajax()){
		add_action('admin_init', function(){
			add_action('current_screen',	'wpjam_admin_load', 9);

			if(isset($_POST['plugin_page'])){
				WPJAM_Menu_Page::init($_POST['plugin_page']);
			}

			if($screen_id = WPJAM_AJAX::get_screen_id()){
				if($screen_id == 'upload'){
					$GLOBALS['hook_suffix']	= $screen_id;

					set_current_screen();
				}else{
					set_current_screen($screen_id);
				}
			}
			
			add_action('wp_ajax_wpjam-page-action',	['WPJAM_Page_Action', 'ajax_response']);
			add_action('wp_ajax_wpjam-query', 		['WPJAM_Page_Action', 'ajax_query']);
		}, 9);
	}else{
		$admin_menu_action	= (is_multisite() && is_network_admin()) ? 'network_admin_menu' : 'admin_menu';	

		add_action($admin_menu_action,	['WPJAM_Menu_Page', 'render'], 9);
		add_action('current_screen',	'wpjam_admin_load', 9);

		add_action('admin_enqueue_scripts', ['WPJAM_Menu_Page',	'admin_enqueue_scripts'], 9);
		add_action('print_media_templates', ['WPJAM_Field',		'print_media_templates'], 9);

		add_filter('set-screen-option', function($status, $option, $value){
			return isset($_GET['page']) ? $value : $status;
		}, 9, 3);
	}
});

