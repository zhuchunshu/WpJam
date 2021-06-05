<?php
if(!class_exists('WP_List_Table')){
	include ABSPATH.'wp-admin/includes/class-wp-list-table.php';
}

include WPJAM_BASIC_PLUGIN_DIR.'includes/class-wpjam-page-action.php';
include WPJAM_BASIC_PLUGIN_DIR.'includes/class-wpjam-list-table.php';
include WPJAM_BASIC_PLUGIN_DIR.'includes/class-wpjam-builtin-list-table.php';
include WPJAM_BASIC_PLUGIN_DIR.'includes/class-wpjam-plugin-page.php';
include WPJAM_BASIC_PLUGIN_DIR.'includes/class-wpjam-builtin-page.php';
include WPJAM_BASIC_PLUGIN_DIR.'includes/class-wpjam-chart.php';

function wpjam_admin_load($current_screen=null){
	if($page_setting = wpjam_get_plugin_page_setting()){
		WPJAM_Menu_Page::load($page_setting);
	}else{
		WPJAM_Builtin_Page::load();
	}
}

function wpjam_get_plugin_page_setting($key=''){
	return WPJAM_Menu_Page::get_page_setting($key);
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

	WPJAM_Page_Action::register($name, apply_filters('wpjam_register_page_action_args', $args, $name));
}

function wpjam_unregister_page_action($name){
	WPJAM_Page_Action::unregister($name);
}

function wpjam_get_page_form($name, $args=[]){
	$instance	= WPJAM_Page_Action::get($name);
	return $instance ? $instance->get_form($args) : '';
}

function wpjam_get_page_button($name, $args=[]){
	$instance	= WPJAM_Page_Action::get($name);
	return $instance ? $instance->get_button($args) : '';
}

function wpjam_register_list_table($name, $args=[]){
	if(WPJAM_List_Table_Setting::get($name)){
		trigger_error('List Table 「'.$name.'」已经注册。');
	}

	$args	= apply_filters('wpjam_register_list_table_action_args', $args, $name);

	WPJAM_List_Table_Setting::register($name, $args);
}

function wpjam_unregister_list_table($name){
	WPJAM_List_Table_Setting::unregister($name);
}

function wpjam_register_list_table_action($name, $args){
	if(WPJAM_List_Table_Action::get($name)){
		trigger_error('List Table Action 「'.$name.'」已经注册。');
	}

	$args	= apply_filters('wpjam_register_list_table_action_args', $args, $name);

	WPJAM_List_Table_Action::register($name, $args);
}

function wpjam_unregister_list_table_action($name){
	WPJAM_List_Table_Action::unregister($name);
}

function wpjam_register_list_table_column($name, $field){
	if(WPJAM_List_Table_Column::get($name)){
		trigger_error('List Table Column 「'.$name.'」已经注册。');
	}

	$field	= wp_parse_args($field, ['type'=>'view', 'show_admin_column'=>true]);
	$field	= apply_filters('wpjam_register_list_table_column_args', $field, $name);

	WPJAM_List_Table_Column::register($name, $field);
}

function wpjam_unregister_list_table_column($name){
	WPJAM_List_Table_Column::unregister($name);
}

function wpjam_register_plugin_page_tab($name, $args){
	$name	= sanitize_key($name);

	WPJAM_Plugin_Page_Tab::register($name, $args);
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

function wpjam_register_dashboard($name, $args){
	WPJAM_Dashboard_Setting::register($name, $args);
}

function wpjam_unregister_dashboard($name){
	WPJAM_Dashboard_Setting::unregister($name);
}

function wpjam_register_dashboard_widget($name, $args){
	WPJAM_Dashboard_Widget::register($name, $args);
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

add_action('wp_loaded', function(){	// 内部的 hook 使用 优先级 9，因为内嵌的 hook 优先级要低
	if($GLOBALS['pagenow'] == 'options.php'){
		// 为了实现多个页面使用通过 option 存储。
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

			if(isset($_POST['screen_id'])){
				set_current_screen($_POST['screen_id']);
			}elseif(isset($_POST['screen'])){
				set_current_screen($_POST['screen']);	
			}else{
				$ajax_action	= $_REQUEST['action'] ?? '';

				if($ajax_action == 'fetch-list'){
					set_current_screen($_GET['list_args']['screen']['id']);
				}elseif($ajax_action == 'inline-save-tax'){
					set_current_screen('edit-'.sanitize_key($_POST['taxonomy']));
				}elseif($ajax_action == 'get-comments'){
					set_current_screen('edit-comments');
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

