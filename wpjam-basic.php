<?php
/*
Plugin Name: WPJAM BASIC
Plugin URI: https://blog.wpjam.com/project/wpjam-basic/
Description: WPJAM 常用的函数和接口，屏蔽所有 WordPress 不常用的功能。
Version: 5.7.5
Requires at least: 5.6
Tested up to: 5.6
Requires PHP: 7.2
Author: Denis
Author URI: http://blog.wpjam.com/
*/
if (version_compare(PHP_VERSION, '7.2.0') < 0) {
	include plugin_dir_path(__FILE__).'old/wpjam-basic.php';
}else{
	define('WPJAM_BASIC_PLUGIN_DIR', plugin_dir_path(__FILE__));
	define('WPJAM_BASIC_PLUGIN_URL', plugin_dir_url(__FILE__));
	define('WPJAM_BASIC_PLUGIN_FILE', __FILE__);

	include WPJAM_BASIC_PLUGIN_DIR.'includes/class-wpjam-model.php';	// Model 类
	include WPJAM_BASIC_PLUGIN_DIR.'includes/class-wpjam-db.php';		// DB 操作类
	include WPJAM_BASIC_PLUGIN_DIR.'includes/class-wpjam-items.php';	// ITEM 操作类
	include WPJAM_BASIC_PLUGIN_DIR.'includes/class-wpjam-util.php';		// 通用工具类
	include WPJAM_BASIC_PLUGIN_DIR.'includes/class-wpjam-field.php';	// 字段解析类
	include WPJAM_BASIC_PLUGIN_DIR.'includes/class-wpjam-misc.php';		// 杂项类和特征
	include WPJAM_BASIC_PLUGIN_DIR.'includes/class-wpjam-path.php';		// 路径平台类
	include WPJAM_BASIC_PLUGIN_DIR.'includes/class-wpjam-post.php';		// 文章处理类
	include WPJAM_BASIC_PLUGIN_DIR.'includes/class-wpjam-term.php';		// 分类处理类
	include WPJAM_BASIC_PLUGIN_DIR.'includes/class-wpjam-user.php';		// 用户处理类
	include WPJAM_BASIC_PLUGIN_DIR.'includes/class-wpjam-api.php';		// 接口路由类

	if(is_admin()){
		include WPJAM_BASIC_PLUGIN_DIR.'public/wpjam-admin.php';	// 后台入口
		include WPJAM_BASIC_PLUGIN_DIR.'public/wpjam-verify.php';
	}

	include WPJAM_BASIC_PLUGIN_DIR.'public/wpjam-functions.php';	// 常用函数
	include WPJAM_BASIC_PLUGIN_DIR.'public/wpjam-register.php';		// 注册方法
	include WPJAM_BASIC_PLUGIN_DIR.'public/wpjam-route.php';		// 路由接口

	do_action('wpjam_loaded');
}