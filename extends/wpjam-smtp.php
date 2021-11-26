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

	public static function on_phpmailer_init($phpmailer){
		$instance	= WPJAM_SMTP::get_instance();

		$phpmailer->isSMTP(); 

		// $phpmailer->SMTPDebug	= 1;

		$phpmailer->SMTPAuth	= true;
		$phpmailer->SMTPSecure	= $instance->get_setting('ssl', 'ssl');
		$phpmailer->Host		= $instance->get_setting('host'); 
		$phpmailer->Port		= $instance->get_setting('port', '465');
		$phpmailer->Username	= $instance->get_setting('user');
		$phpmailer->Password	= $instance->get_setting('pass');

		$phpmailer->setFrom($instance->get_setting('user'), $instance->get_setting('mail_from_name'), false);

		if($smtp_reply_to_mail	= $instance->get_setting('reply_to_mail')){
			$name	= $instance->get_setting('mail_from_name') ?: '';
			$phpmailer->AddReplyTo($smtp_reply_to_mail, $name);
		}
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

	public static function load_plugin_page(){
		wpjam_set_plugin_page_summary('SMTP 邮件服务扩展让你可以使用第三方邮箱的 SMTP 服务来发邮件，详细介绍请点击：<a href="https://blog.wpjam.com/m/wpjam-smtp/" target="_blank">SMTP 邮件服务扩展</a>，点击这里查看：<a target="_blank" href="http://blog.wpjam.com/m/gmail-qmail-163mail-imap-smtp-pop3/" target="_blank">常用邮箱的 SMTP 设置</a>。');

		wpjam_register_plugin_page_tab('smtp', [
			'title'		=> '发信设置',
			'function'	=> 'option',
			'fields'	=> [
				'smtp_setting'		=> ['title'=>'SMTP 设置',	'type'=>'fieldset','fields'=>[
					'host'	=> ['title'=>'地址',		'type'=>'text',		'class'=>'all-options',	'value'=>'smtp.qq.com'],
					'ssl'	=> ['title'=>'发送协议',	'type'=>'text',		'class'=>'',			'value'=>'ssl'],
					'port'	=> ['title'=>'SSL端口',	'type'=>'number',	'class'=>'',			'value'=>'465'],
					'user'	=> ['title'=>'邮箱账号',	'type'=>'email',	'class'=>'all-options'],
					'pass'	=> ['title'=>'邮箱密码',	'type'=>'password',	'class'=>'all-options'],
				]],
				'mail_from_name'	=> ['title'=>'发送者姓名',	'type'=>'text',	'class'=>''],
				'reply_to_mail'		=> ['title'=>'回复地址',		'type'=>'email','class'=>'all-options',	'description'=>'不填则用户回复使用SMTP设置中的邮箱账号']
			]
		]);

		wpjam_register_plugin_page_tab('send', [
			'title'			=> '发送测试',	
			'function'		=> 'form',		
			'submit_text'	=> '发送',
			'callback'		=> [self::class, 'ajax_send'],
			'fields'		=> [
				'to'		=> ['title'=>'收件人',	'type'=>'email',	'required'],
				'subject'	=> ['title'=>'主题',		'type'=>'text',		'required'],
				'message'	=> ['title'=>'内容',		'type'=>'textarea',	'class'=>'',	'rows'=>8,	'required'],
			]
		]);
	}
}

add_action('phpmailer_init',	['WPJAM_SMTP', 'on_phpmailer_init']);

if(is_admin()){
	wpjam_add_basic_sub_page('wpjam-smtp', [
		'menu_title'	=> '发信设置',
		'page_title'	=> 'SMTP邮件服务',
		'network'		=> false,
		'function'		=> 'tab',
		'load_callback'	=> ['WPJAM_SMTP', 'load_plugin_page']
	]);
}