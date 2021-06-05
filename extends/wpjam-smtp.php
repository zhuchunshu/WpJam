<?php
/*
Name: SMTP 发信
URI: https://blog.wpjam.com/m/wpjam-smtp/
Description: 简单配置就能让 WordPress 使用 SMTP 发送邮件。
Version: 1.0
*/
class WPJAM_SMTP{
	use WPJAM_Setting_Trait;

	private function __construct(){
		$this->init('wpjam-smtp');
	}

	public function on_phpmailer_init($phpmailer){
		$phpmailer->isSMTP(); 

		// $phpmailer->SMTPDebug	= 1;

		$phpmailer->SMTPAuth	= true;
		$phpmailer->SMTPSecure	= $this->get_setting('ssl');
		$phpmailer->Host		= $this->get_setting('host'); 
		$phpmailer->Port		= $this->get_setting('port');
		$phpmailer->Username	= $this->get_setting('user');
		$phpmailer->Password	= $this->get_setting('pass');

		if($smtp_reply_to_mail	= $this->get_setting('reply_to_mail')){
			$name	= $this->get_setting('mail_from_name') ?: '';
			$phpmailer->AddReplyTo($smtp_reply_to_mail, $name);
		}
	}

	public function filter_wp_mail_from(){
		return $this->get_setting('user');
	}

	public function filter_wp_mail_from_name($name){
		return $this->get_setting('mail_from_name') ?: $name;
	}

	public static function ajax_send(){
		$to			= wpjam_get_data_parameter('to');
		$subject	= wpjam_get_data_parameter('subject');
		$message	= wpjam_get_data_parameter('message');

		if(wp_mail($to, $subject, $message)){
			wpjam_send_json();
		}
	}

	public static function on_wp_mail_failed($mail_failed){
		wpjam_send_json($mail_failed);
	}

	public static function load_option_page(){
		wpjam_register_option('wpjam-smtp', ['fields'=>[
			'smtp_setting'		=> ['title'=>'SMTP 设置',	'type'=>'fieldset','fields'=>[
				'host'	=> ['title'=>'地址',		'type'=>'text',		'class'=>'all-options',	'value'=>'smtp.qq.com'],
				'ssl'	=> ['title'=>'发送协议',	'type'=>'text',		'class'=>'',			'value'=>'ssl'],
				'port'	=> ['title'=>'SSL端口',	'type'=>'number',	'class'=>'',			'value'=>'465'],
				'user'	=> ['title'=>'邮箱账号',	'type'=>'email',	'class'=>'all-options'],
				'pass'	=> ['title'=>'邮箱密码',	'type'=>'password',	'class'=>'all-options'],
			]],
			'mail_from_name'	=> ['title'=>'发送者姓名',	'type'=>'text',	'class'=>''],
			'reply_to_mail'		=> ['title'=>'回复地址',		'type'=>'email','class'=>'all-options',	'description'=>'不填则用户回复使用SMTP设置中的邮箱账号']
		]]);
	}

	public static function load_form_page(){
		wpjam_register_page_action('send_test_mail', [
			'submit_text'	=> '发送',
			'callback'		=> ['WPJAM_SMTP', 'ajax_send'],
			'fields'		=> [
				'to'		=> ['title'=>'收件人',	'type'=>'email',	'required'],
				'subject'	=> ['title'=>'主题',		'type'=>'text',		'required'],
				'message'	=> ['title'=>'内容',		'type'=>'textarea',	'class'=>'',	'rows'=>8,	'required'],
			]
		]);

		add_action('wp_mail_failed', ['WPJAM_SMTP', 'on_wp_mail_failed']);
	}
}

add_action('wp_loaded', function(){
	$instance	= WPJAM_SMTP::get_instance();

	add_action('phpmailer_init',	[$instance, 'on_phpmailer_init']);
	add_filter('wp_mail_from',		[$instance, 'filter_wp_mail_from']);
	add_filter('wp_mail_from_name',	[$instance, 'filter_wp_mail_from_name']);

	if(is_admin() && (!is_multisite() || !is_network_admin())){
		wpjam_add_basic_sub_page('wpjam-smtp', [
			'menu_title'	=> '发信设置',
			'page_title'	=> 'SMTP邮件服务',
			'function'		=> 'tab',
			'tabs'			=> [
				'smtp'	=> ['title'=>'发信设置',	'function'=>'option',	'load_callback'	=>['WPJAM_SMTP', 'load_option_page']],
				'send'	=> ['title'=>'发送测试',	'function'=>'form',		'form_name'=>'send_test_mail',	'load_callback'	=>['WPJAM_SMTP', 'load_form_page']],
			],
			'summary'		=> 'SMTP 邮件服务扩展让你可以使用第三方邮箱的 SMTP 服务来发邮件，详细介绍请点击：<a href="https://blog.wpjam.com/m/wpjam-smtp/" target="_blank">SMTP 邮件服务扩展</a>，点击这里查看：<a target="_blank" href="http://blog.wpjam.com/m/gmail-qmail-163mail-imap-smtp-pop3/" target="_blank">常用邮箱的 SMTP 设置</a>。'
		]);
	}
});
	