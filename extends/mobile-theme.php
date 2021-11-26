<?php
/*
Name: 移动主题
URI: https://blog.wpjam.com/m/mobile-theme/
Description: 给当前站点设置移动设备设置上使用单独的主题。
Version: 1.0
*/
class WPJAM_Mobile_Theme{
	use WPJAM_Setting_Trait;

	private function __construct(){
		$this->init('wpjam-basic', true);
	}

	public function filter_stylesheet($stylesheet){
		return $this->get_setting('mobile_stylesheet');
	}

	public function filter_template($template){
		$mobile_stylesheet	= $this->get_setting('mobile_template');
		$mobile_theme		= wp_get_theme($mobile_stylesheet);

		return $mobile_theme->get_template();
	}

	public static function load_option_page(){
		$current_theme	= wp_get_theme();
		$theme_options	= [];
		
		$theme_options[$current_theme->get_stylesheet()]	= $current_theme->get('Name');

		foreach(wp_get_themes() as $theme){
			$theme_options[$theme->get_stylesheet()]	= $theme->get('Name');
		}

		wpjam_register_option('wpjam-basic', [
			'fields'		=> ['mobile_stylesheet'=>['title'=>'选择移动主题',	'type'=>'select',	'options'=>$theme_options]],
			'summary'		=> '使用手机和平板访问网站的用户将看到以下选择的主题界面，而桌面用户依然看到 <strong>'.$current_theme->get('Name').'</strong> 主题界面。',
			'site_default'	=> true
		]);
	}
}

add_action('plugins_loaded', function(){
	if(wp_is_mobile()){
		if(wpjam_basic_get_setting('mobile_stylesheet')){
			$instance	= WPJAM_Mobile_Theme::get_instance();

			add_filter('stylesheet',	[$instance, 'filter_stylesheet']);
			add_filter('template',		[$instance, 'filter_template']);
		}
	}

	if(is_admin()){
		wpjam_add_menu_page('mobile-theme', [
			'menu_title'	=> '移动主题',
			'parent'		=> 'themes',
			'function'		=> 'option',
			'option_name'	=> 'wpjam-basic',
			'load_callback'	=> ['WPJAM_Mobile_Theme', 'load_option_page']
		]);
	}
}, 0);
