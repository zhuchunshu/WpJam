<?php
class WPJAM_Custom{
	use WPJAM_Setting_Trait;

	private function __construct(){
		$this->init('wpjam-custom', true);
	}

	public function on_admin_head(){
		remove_action('admin_bar_menu',	'wp_admin_bar_wp_menu', 10);
		
		add_action('admin_bar_menu',	[$this, 'on_admin_bar_menu']);

		echo $this->get_setting('admin_head');
	}

	public function on_admin_bar_menu($wp_admin_bar){
		$admin_logo	= $this->get_setting('admin_logo');
		$title 		= $admin_logo ? '<img src="'.wpjam_get_thumbnail($admin_logo, 40, 40).'" style="height:20px; padding:6px 0">' : '<span class="ab-icon"></span>';

		$wp_admin_bar->add_menu([
			'id'    => 'wp-logo',
			'title' => $title,
			'href'  => self_admin_url(),
			'meta'  => ['title'=>get_bloginfo('name')]
		]);
	}

	public function filter_admin_footer_text($text){
		return $this->get_setting('admin_footer') ?: $text;
	}

	public function on_login_head(){
		echo $this->get_setting('login_head'); 
	}

	public function on_login_footer(){
		echo $this->get_setting('login_footer'); 
	}

	public function filter_login_redirect($redirect_to, $request){
		return $request ?: ($this->get_setting('login_redirect') ?: $redirect_to);
	}

	public function on_wp_head(){
		echo $this->get_setting('head'); 
	}

	public function on_wp_footer(){
		echo $this->get_setting('footer');

		if(wpjam_basic_get_setting('optimized_by_wpjam')){
			echo '<p id="optimized_by_wpjam_basic">Optimized by <a href="https://blog.wpjam.com/project/wpjam-basic/">WPJAM Basic</a>。</p>';
		}
	}

	public function load_option_page(){
		$sections	= [
			'wpjam-custom'	=> ['title'=>'前台定制',	'fields'=>[
				'head'			=> ['title'=>'前台 Head 代码',		'type'=>'textarea',	'class'=>''],
				'footer'		=> ['title'=>'前台 Footer 代码',		'type'=>'textarea',	'class'=>''],
			]],
			'admin-custom'	=> ['title'=>'后台定制',	'fields'=>[
				'admin_logo'	=> ['title'=>'后台左上角 Logo',		'type'=>'img',	'item_type'=>'url',	'description'=>'建议大小：20x20。'],
				'admin_head'	=> ['title'=>'后台 Head 代码 ',		'type'=>'textarea',	'class'=>''],
				'admin_footer'	=> ['title'=>'后台 Footer 代码',		'type'=>'textarea',	'class'=>'']
			]],
			'login-custom'	=> ['title'=>'登录界面', 	'fields'=>[
				// 'login_logo'			=> ['title'=>'登录界面 Logo',		'type'=>'img',		'description'=>'建议大小：宽度不超过600px，高度不超过160px。'),
				'login_head'	=> ['title'=>'登录界面 Head 代码',	'type'=>'textarea',	'class'=>''],
				'login_footer'	=> ['title'=>'登录界面 Footer 代码',	'type'=>'textarea',	'class'=>''],
				'login_redirect'=> ['title'=>'登录之后跳转的页面',		'type'=>'text'],
			]]
		];

		wpjam_register_option('wpjam-custom', [
			'summary'		=> '对网站的前端或者后台的样式进行定制，详细介绍请点击：<a href="https://blog.wpjam.com/m/wpjam-basic-custom-setting/"  target="_blank">样式定制</a>。',
			'sections'		=> $sections,
			'site_default'	=> true
		]);
	}
}

add_action('wp_loaded', function(){
	$instance	= WPJAM_Custom::get_instance();

	if(is_admin()){
		wpjam_add_basic_sub_page('wpjam-custom', ['menu_title'=>'样式定制',	'function'=>'option',	'load_callback'=>[$instance, 'load_option_page'],	'order'=>20]);

		add_action('admin_head',		[$instance, 'on_admin_head']);
		add_filter('admin_footer_text',	[$instance, 'filter_admin_footer_text']);
	}elseif(is_login()){
		add_filter('login_headerurl',	'home_url');
		add_filter('login_headertext',	'get_bloginfo');

		add_action('login_head', 		[$instance, 'on_login_head']);
		add_action('login_footer',		[$instance, 'on_login_footer']);
		add_filter('login_redirect',	[$instance, 'filter_login_redirect'], 10, 2);
	}else{
		add_action('wp_head',	[$instance, 'on_wp_head'], 1);
		add_action('wp_footer', [$instance, 'on_wp_footer'], 99);
	}
});

