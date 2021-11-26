<?php
class WPJAM_Verify{
	public static function on_admin_notices(){
		if($messages = self::get_messages()){
			$unread_count	= $messages['unread_count'] ?? 0;

			if($unread_count){
				echo '<div class="updated"><p>你发布的帖子有<strong>'.$unread_count.'</strong>条回复了，请<a href="'.admin_url('admin.php?page=wpjam-basic-topics&tab=message').'">点击查看</a>！</p></div>';
			}
		}
	}

	public static function on_admin_init(){
		$menu_filter	= (is_multisite() && is_network_admin()) ? 'wpjam_network_pages' : 'wpjam_pages';

		if(get_transient('wpjam_basic_verify')){
			add_filter($menu_filter, [self::class, 'filter_menu_pages']);
		}else{
			$weixin_user	= self::get_weixin_user();

			if(empty($weixin_user) || empty($weixin_user['subscribe'])){
				$verified	= false;
			}elseif(time() - $weixin_user['last_update'] < DAY_IN_SECONDS) {
				$verified	= true;
			}else{
				$verified	= self::verify($weixin_user['openid']);
			}

			if($verified){
				wpjam_add_menu_page('wpjam-basic-topics', [
					'parent'		=> 'wpjam-basic',
					'order'			=> 2,
					'menu_title'	=> '讨论组',
					'function'		=> 'tab',
					'page_file'		=> __DIR__.'/wpjam-topics.php'
				]);
			}else{
				add_filter($menu_filter, [self::class, 'filter_menu_pages']);

				wpjam_add_menu_page('wpjam-verify', [
					'parent'		=> 'wpjam-basic',
					'order'			=> 3,
					'menu_title'	=> '扩展管理',
					'page_title'	=> '验证 WPJAM',
					'function'		=> 'form',
					'form_name'		=> 'verify_wpjam',
					'load_callback'	=> [self::class, 'page_action']
				]);
			}
		}
	}

	public static function filter_menu_pages($menu_pages){
		$subs	= $menu_pages['wpjam-basic']['subs'];

		if(isset($subs['wpjam-verify'])){
			// $menu_pages	= wp_array_slice_assoc($menu_pages, ['wpjam-basic']);

			$menu_pages['wpjam-basic']['subs']	= wp_array_slice_assoc($subs, ['wpjam-basic', 'wpjam-verify']);
		}else{
			$menu_pages['wpjam-basic']['subs']	= wpjam_array_except($subs, ['wpjam-about']);
		}

		return $menu_pages;
	}

	public static function verify($openid=''){
		return true;

		$user_id	= get_current_user_id();
		$response	= wp_cache_get('wpjam_weixin_user_'.$openid, 'counts');

		if($response === false){
			$response	= wpjam_remote_request('http://jam.wpweixin.com/api/topic/user/get.json?openid='.$openid);

			wp_cache_set('wpjam_weixin_user_'.$openid, $response, 'counts');
		}

		if(is_wp_error($response)){
			$failed_times	= get_user_meta($user_id, 'wpjam_weixin_user_failed_times') ?: 0;
			$failed_times ++;

			if($failed_times >= 3){	// 重复三次
				delete_user_meta($user_id, 'wpjam_weixin_user_failed_times');
				delete_user_meta($user_id, 'wpjam_weixin_user');
			}else{
				update_user_meta($user_id, 'wpjam_weixin_user_failed_times', $failed_times);
			}

			return false;
		}

		$weixin_user	= $response['user'];

		if(empty($weixin_user) || !$weixin_user['subscribe']){
			delete_user_meta($user_id, 'wpjam_weixin_user');
			delete_user_meta($user_id, 'wpjam_weixin_user_failed_times');
			return false;
		}

		$weixin_user['last_update']	= time();

		update_user_meta($user_id, 'wpjam_weixin_user', $weixin_user);
		delete_user_meta($user_id, 'wpjam_weixin_user_failed_times');

		return true;
	}

	public static function verify_domain($id=0){
		return get_transient('wpjam_basic_verify');
	}

	public static function get_weixin_user(){
		return get_user_meta(get_current_user_id(), 'wpjam_weixin_user', true);
	}

	public static function get_openid(){
		$weixin_user	= self::get_weixin_user();

		if($weixin_user && isset($weixin_user['openid'])){
			return $weixin_user['openid'];
		}else{
			return '';
		}
	}

	public static function get_qrcode($key=''){
		$key	= $key?:md5(home_url().'_'.get_current_user_id());

		return wpjam_remote_request('http://jam.wpweixin.com/api/weixin/qrcode/create.json?key='.$key);
	}

	public static function bind_user($data){
		$response	= wpjam_remote_request('http://jam.wpweixin.com/api/weixin/qrcode/verify.json', [
			'method'	=>'POST',
			'body'		=> $data
		]);

		if(is_wp_error($response)){
			return $response;
		}

		$weixin_user =	$response['user'];

		$weixin_user['last_update']	= time();

		update_user_meta(get_current_user_id(), 'wpjam_weixin_user', $weixin_user);

		return $weixin_user;
	}

	public static function get_messages(){
		$messages	= [];

		if(self::get_openid()){
			$user_id	= get_current_user_id();
			$messages	= get_transient('wpjam_topic_messages_'.get_current_user_id());

			if($messages === false){
				$messages = wpjam_remote_request('http://jam.wpweixin.com/api/topic/messages.json',[
					'method'	=> 'POST',
					'headers'	=> ['openid'=>self::get_openid()]
				]);

				if(is_wp_error($messages)){
					$messages = array('unread_count'=>0, 'messages'=>array());
				}
				
				set_transient('wpjam_topic_messages_'.get_current_user_id(), $messages, 900);
			}
		}

		return $messages;
	}

	public static function read_messages(){
		$result	= $messages	= self::get_messages();

		if($messages['unread_count']){

			wpjam_remote_request('http://jam.wpweixin.com/api/topic/messages/read.json',[
				'headers'	=> ['openid'=>self::get_openid()]
			]);

			$messages['unread_count'] = 0;
			
			foreach ($messages['messages'] as $key => &$message) {
				$message['status'] = 1;
			}

			unset($message);

			set_transient('wpjam_topic_messages_'.get_current_user_id(), $messages, 900);
		}

		return $result;
	}

	public static function ajax_verify(){
		$data	= wpjam_get_parameter('data',	['method'=>'POST', 'sanitize_callback'=>'wp_parse_args']);
		$result = self::bind_user($data);

		if(is_wp_error($result)){
			return $result;
		}

		$page	= current_user_can('manage_options') ? 'wpjam-extends' : 'wpjam-basic-topics';

		return ['url'=>admin_url('admin.php?page='.$page)];
	}

	public static function page_action(){
		$response	= self::get_qrcode();

		if(is_wp_error($response)){
			wp_die($response);
		}else{
			$summary	= '
			<p><strong>通过验证才能使用 WPJAM Basic 的扩展功能。 </strong></p>
			<p>1. 使用微信扫描下面的二维码获取验证码。<br />
			2. 将获取验证码输入提交即可！<br />
			3. 如果验证不通过，请使用 Chrome 浏览器验证，并在验证之前清理浏览器缓存。</p>
			';

			$qrcode = 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket='.$response['ticket'];

			wpjam_register_page_action('verify_wpjam', [
				'submit_text'	=> '验证',
				'callback'		=> ['WPJAM_Verify', 'ajax_verify'],
				'response'		=> 'redirect',
				'fields'		=> [
					'summary'	=> ['title'=>'',		'type'=>'view',		'value'=>$summary],
					'qrcode'	=> ['title'=>'二维码',	'type'=>'view',		'value'=>'<img src="'.$qrcode.'" style="max-width:250px;" />'],
					'code'		=> ['title'=>'验证码',	'type'=>'number',	'class'=>'all-options',	'description'=>'验证码10分钟内有效！'],
					'scene'		=> ['title'=>'scene',	'type'=>'hidden',	'value'=>$response['scene']]
				]
			]);

			wp_add_inline_style('list-tables', "\n".'.form-table th{width: 100px;}');
		}
	}
}

add_action('wpjam_loaded',	function(){
	add_action('wpjam_admin_init',	['WPJAM_Verify', 'on_admin_init']);
	add_action('admin_notices', 	['WPJAM_Verify', 'on_admin_notices']);
});