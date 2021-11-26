<?php
class WPJAM_Bind{
	protected $name;
	protected $appid;
	protected $args		= [];

	public function __construct($name, $appid, $args=[]){
		$this->name		= $name;
		$this->appid	= $appid;
		$this->args		= $args;
	}

	public function __get($key){
		return $this->args[$key] ?? null;
	}

	public function __set($key, $value){
		$this->args[$key]	= $value;
	}

	public function __isset($key){
		return isset($this->args[$key]);
	}

	public function __unset($key){
		unset($this->args[$key]);
	}

	public function to_array(){
		return $this->args;
	}

	public function get_bind($openid, $bind_field, $unionid=false){
		if($user = $this->get_user($openid)){
			return $user[$bind_field] ?? null;
		}

		return null;
	}

	public function update_bind($openid, $bind_field, $bind_value){
		$user	= $this->get_user($openid);

		if($user && $user[$bind_field] != $bind_value){
			return $this->update_user($openid, [$bind_field=>$bind_value]);
		}

		return true;
	}

	public function get_appid(){
		return $this->appid;
	}

	public function get_email($openid){
		$domain	= $this->domain ?: $this->appid.'.'.$this->name;
		return $openid.'@'.$domain;
	}

	public function get_avatarurl($openid){
		return $this->get_value($openid, 'avatarurl');
	}

	public function get_nickname($openid){
		return $this->get_value($openid, 'nickname');
	}

	public function get_unionid($openid){
		return $this->get_value($openid, 'unionid');
	}

	public function get_value($openid, $field){
		if($user = $this->get_user($openid)){
			return $user[$field] ?? '';
		}

		return '';
	}

	public function get_openid_by($key, $value){
		return null;
	}

	public function get_user($openid){
		return [];
	}

	public function update_user($openid, $user){
		return true;
	}
}

abstract class WPJAM_Qrcode_Bind extends WPJAM_Bind{
	public function verify_qrcode($scene, $code){
		if(empty($code)){
			return new WP_Error('invalid_code', '验证码不能为空');
		}

		$qrcode	= $this->get_qrcode($scene);

		if(is_wp_error($qrcode)){
			return $qrcode;
		}

		if(empty($qrcode['openid'])){
			return new WP_Error('invalid_code', '请先扫描二维码！');
		}

		if($code != $qrcode['code']){
			return new WP_Error('invalid_code', '验证码错误！');
		}

		$this->cache_delete($scene.'_scene');

		return $qrcode;
	}

	public function scan_qrcode($openid, $scene){
		$qrcode = $this->get_qrcode($scene);

		if(is_wp_error($qrcode)){
			return $qrcode;
		}

		if(!empty($qrcode['openid']) && $qrcode['openid'] != $openid){
			return new WP_Error('qrcode_scaned', '已有用户扫描该二维码！');
		}

		$this->cache_delete($qrcode['key'].'_qrcode');

		if(!empty($qrcode['id']) && !empty($qrcode['bind_callback']) && is_callable($qrcode['bind_callback'])){
			return call_user_func($qrcode['bind_callback'], $openid, $qrcode['id']);
		}else{
			$this->cache_set($scene.'_scene', array_merge($qrcode, ['openid'=>$openid]), 1200);

			return $qrcode['code'];
		}
	}

	public function get_qrcode($scene){
		if(empty($scene)){
			return new WP_Error('invalid_scene', '场景值不能为空');
		}

		$qrcode	= $this->cache_get($scene.'_scene');

		return $qrcode ?: new WP_Error('invalid_scene', '二维码无效或已过期，请刷新页面再来验证！');
	}

	abstract public function create_qrcode($key, $args=[]);
}

class WPJAM_Bind_Type{
	use WPJAM_Register_Trait;
}

class WPJAM_User_Signup{
	protected $name		= '';
	protected $appid	= '';

	public function __construct($name, $appid){
		$this->name		= $name;
		$this->appid	= $appid;
	}

	public function __call($method, $args){
		return call_user_func_array([wpjam_get_bind_object($this->name, $this->appid), $method], $args);
	}

	public function get_name(){
		return $this->name;
	}

	public function get_openid($user_id){
		$wpjam_user	= WPJAM_User::get_instance($user_id);

		if($openid = $wpjam_user->get_openid($this->name, $this->appid)){
			return $openid;
		}

		return $this->get_openid_by('user_id', $user_id);
	}

	public function signup($openid, $args){
		$args	= apply_filters('wpjam_user_signup_args', $args, $this->name, $this->appid, $openid);

		if(is_wp_error($args)){
			return $args;
		}

		return WPJAM_User::signup($this->name, $this->appid, $openid, $args);
	}

	public function bind($openid, $user_id=null){
		$user_id	= $user_id ?? get_current_user_id();

		if(!$user_id || !get_userdata($user_id)){
			return false;
		}

		$wpjam_user	= WPJAM_User::get_instance($user_id);

		return $wpjam_user->bind($this->name, $this->appid, $openid);
	}

	public function unbind($user_id=null){
		$user_id	= $user_id ?? get_current_user_id();
		
		if(!$user_id || !get_userdata($user_id)){
			return false;
		}

		$wpjam_user	= WPJAM_User::get_instance($user_id);
		$wpjam_user->unbind($this->name, $this->appid);
		
		return true;
	}

	public function get_bind_openid_fields(){}

	public function bind_openid_callback(){}

	public function register_bind_user_action(){
		wpjam_register_list_table_action('bind_user', [
			'title'			=> '绑定用户',
			'capability'	=> is_multisite() ? 'manage_sites' : 'manage_options',
			'callback'		=> [$this, 'bind_user_callback'],
			'fields'		=> [
				'nickname'	=> ['title'=>'用户',		'type'=>'view'],
				'user_id'	=> ['title'=>'用户ID',	'type'=>'text',	'class'=>'all-options',	'description'=>'请输入 WordPress 的用户']
			]
		]);
	}

	public function bind_user_callback($openid, $data){
		$user_id	= $data['user_id'] ?? 0;

		if($user_id){
			if(get_userdata($user_id)){
				return $this->bind($openid, $user_id);
			}else{
				return new WP_Error('invalid_user_id', '无效的用户 ID，请确认之后再试！');
			}
		}else{
			$prev_id	= $this->get_bind($openid, 'user_id');

			if($prev_id && get_userdata($prev_id)){
				return $this->unbind($prev_id, $openid);
			}
		}
	}
}

class WPJAM_User_Qrcode_Signup extends WPJAM_User_Signup{
	public function __construct($name, $appid){
		parent::__construct($name, $appid);

		wpjam_register_ajax($name.'-qrcode-signup', [
			'nopriv'	=> true, 
			'callback'	=> [$this, 'ajax_qrcode_signup']
		]);
	}

	public function qrcode_signup($scene, $code, $args=[]){
		if($user = apply_filters('wpjam_qrcode_signup', null, $scene, $code)){
			return $user;
		}

		$qrcode	= $this->verify_qrcode($scene, $code);

		if(is_wp_error($qrcode)){
			if($qrcode->get_error_message() == 'invalid_code'){
				do_action('wpjam_qrcode_signup_failed', $scene);
			}

			return $qrcode;
		}

		return $this->signup($qrcode['openid'], $args);
	}

	public function login_action($args=[]){}

	public function get_login_data($key=''){
		$key	= $key ?: wp_generate_password(32, false, false);
		$qrcode	= $this->create_qrcode($key);

		if(is_wp_error($qrcode)){
			return $qrcode;
		}

		$attr	= wpjam_get_ajax_data_attr($this->name.'-qrcode-signup', [], 'array');

		$fields	= '<p><label for="code">微信扫码，一键登录<br /><img src="'.$qrcode['qrcode_url'].'" width="272" /></label></p>'."\n";
		$fields	.= '<p><label for="code">验证码<br />'.wpjam_render_field(['key'=>'code', 'type'=>'number', 'class'=>'input',	'required', 'size'=>20]).'</label></p>'."\n";
		$fields	.= wpjam_render_field(['key'=>'scene', 		'type'=>'hidden',	'value'=>$qrcode['scene']])."\n";

		return $attr+['fields'=>$fields];
	}

	public function get_bind_openid_fields(){
		$user_id	= get_current_user_id();
		$openid		= $this->get_openid($user_id);

		if($openid){
			$view	= '<img src="'.str_replace('/132', '/0', $this->get_avatarurl($openid)).'" width="160" />'."\n\n绑定的微信账号是：".$this->get_nickname($openid);

			return [
				'view'		=> ['type'=>'view',		'value'=>wpautop($view)],
				'bind_type'	=> ['type'=>'hidden',	'value'=>'unbind']
			];
		}else{
			$qrcode	= $this->create_qrcode(md5('bind_'.$user_id), ['id'=>$user_id, 'bind_callback'=>[$this, 'bind']]);

			if(is_wp_error($qrcode)){
				return $qrcode;
			}

			return [
				'scene'		=> ['type'=>'hidden',	'value'=>$qrcode['scene']],
				'bind_type'	=> ['type'=>'hidden',	'value'=>'bind'],
				'view'		=> ['type'=>'view',		'value'=>'<p>使用微信扫描下面的二维码之后，刷新即可，绑定账号之后就可以直接微信扫码登录了。</p>'],
				'qrcode'	=> ['type'=>'view',		'value'=>'<img src="'.$qrcode['qrcode_url'].'" style="max-width:215px;" />'],
				// 'code'	=> ['title'=>'验证码',	'type'=>'number',	'description'=>'验证码10分钟内有效！']
			];
		}
	}

	public function bind_openid_callback(){
		$user_id	= get_current_user_id();
		$openid		= $this->get_openid($user_id);
		$bind_type 	= wpjam_get_data_parameter('bind_type');
	
		if($bind_type == 'bind'){
			if(!$openid){
				return new WP_Error('scan_fail', '请先扫描，再点击刷新。');
			}

			return true;
		}elseif($bind_type == 'unbind'){
			return $this->unbind($user_id, $openid);
		}
	}

	public function ajax_qrcode_signup(){
		$scene	= wpjam_get_data_parameter('scene');
		$code	= wpjam_get_data_parameter('code');	
		$result	= $this->qrcode_signup($scene, $code);

		return is_wp_error($result) ? $result : [];
	}
}

class WPJAM_User_Signup_Type{
	use WPJAM_Type_Trait;

	public function __call($method, $args){
		if(method_exists($this->model, $method)){
			return call_user_func([$this->model, $method], ...$args);
		}
	}
}

class WPJAM_Login_From{
	public static $login_action = null;

	public static function set_action($action){
		if($st_obj = wpjam_get_user_signup_object($action)){
			self::$login_action	= $action;
		}
	}

	public static function on_login_form_login(){
		if(empty($_COOKIE[TEST_COOKIE])){
			$_COOKIE[TEST_COOKIE]	= 'WP Cookie check';
		}

		$login_actions	= wpjam_get_user_signups(['login'=>true]);

		if(is_null(self::$login_action)){
			$action	= $_REQUEST['action'] ?? '';

			if(!$action && $_SERVER['REQUEST_METHOD'] == 'POST'){
				$action == 'login';
			}

			if($action != 'login'){
				if(!$action || !isset($login_actions[$action])){
					$action	= current(array_keys($login_actions));
				}
			
				self::$login_action	= $action;
			}
		}

		if(self::$login_action){
			$st_obj	= wpjam_get_user_signup_object(self::$login_action);

			if($st_obj->login_action && is_callable($st_obj->login_action)){
				call_user_func($st_obj->login_action, $st_obj->to_array());
			}
		}

		$wp_scripts = wp_scripts();

		$wp_scripts->add_data('jquery', 'group', 1);
		$wp_scripts->add_data('jquery-core', 'group', 1);
		$wp_scripts->add_data('jquery-migrate', 'group', 1);

		wpjam_ajax_enqueue_scripts();

		wp_add_inline_style('login', join("\n", [
			'p.login-actions{line-height:2; float:left; clear:left; margin-top:10px;}',
			'p.login-actions a{text-decoration: none; display:block;}'
		]));

		add_action('login_form',	[self::class, 'on_login_form']);
		add_action('login_footer',	[self::class, 'on_login_footer'], 999);
	}

	public static function on_login_form(){
		echo '<p class="login-actions">';

		foreach(wpjam_get_user_signups(['login'=>true]) as $login_action => $st_obj){
			$data_attr	= wpjam_get_ajax_data_attr('fetch-login-data', ['login_action'=>$login_action, 'login_key'=>wp_generate_password(32, false, false)]);
			echo '<a class="login-action '.$login_action.'" href="javascript:;" data-login_action="'.$login_action.'" '.$data_attr.'>'.$st_obj->login_title.'</a>';

			add_action('login_footer',	[$st_obj, 'login_script'], 1000);
		}

		echo '<a class="login-action login" href="javascript:;" data-login_action="login">使用账号和密码登录</a>';
		echo '</p>';
	}

	public static function on_login_footer(){
		?>
<script type="text/javascript">
jQuery(function($){
	$('body').on('submit', '#loginform', function(e){
		if($(this).data('action')){
			e.preventDefault();
			
			$('div#login_error').remove();
			$(this).removeClass('shake');

			$(this).wpjam_submit(function(data){
				if(data.errcode){
					$('h1').after('<div id="login_error">'+data.errmsg+'</div>');
					$(this).addClass('shake');
				}else{
					if($('body').hasClass('interim-login')){
						$('body').addClass('interim-login-success');
						$(window.parent.document).find('.wp-auth-check-close').click();
					}else{
						window.location.href	= $.trim($('input[name="redirect_to"]').val());
					}
				}
			});
		}
	});

	$('body').on('click', '.login-action', function(e){
		e.preventDefault();

		$('#loginform').hide();

		if($(this).data('login_action') == 'login'){
			$('p#nav').show();

			$('div.login-fields').html(login_fields);
			$('#loginform').data('action', '').data('nonce', '').slideDown(300);

			$('a.login-action').show();
			$(this).hide();
		}else{
			$('p#nav').hide();

			$(this).wpjam_action(function(data){
				if(data.errcode != 0){
					alert(data.errmsg);
				}else{
					$('div.login-fields').html(data.fields);
					$('#loginform').data('action', data.action).data('nonce', data.nonce).slideDown(300);

					$('a.login-action').show();
					$(this).hide();
				}
			});
		}

		window.history.replaceState(null, null, login_url+'action='+$(this).data('login_action'));

		return true;
	});

	$('p.login-actions').insertBefore('p.submit');
	
	$('<div class="login-fields">').prependTo('#loginform');

	$('input#user_login').parent().appendTo('div.login-fields');
	$('div.user-pass-wrap, p.forgetmenot').appendTo('div.login-fields');

	let login_fields	= $('div.login-fields').html();
	let login_action	= '<?php echo self::$login_action ?: 'login'; ?>';
	let login_url		= '<?php echo remove_query_arg(['action'], wpjam_get_current_page_url()); ?>';

	login_url	+= login_url.indexOf('?') >= 0 ? '&' : '?';
	
	$('body a.login-action.'+login_action).click();
});
</script>
		<?php
	}

	public static function ajax_callback(){
		$login_action	= wpjam_get_data_parameter('login_action');
		$login_key		= wpjam_get_data_parameter('login_key');

		if($object = wpjam_get_user_signup_object($login_action)){
			return $object->get_login_data($login_key);
		}

		return new WP_Error('invalid_login_action', '无效的登录方式');
	}
}

// 注册绑定
function wpjam_register_bind($name, $appid, $model){
	if($instance = wpjam_get_bind_type_object($name, $appid)){
		return $instance;
	}

	return WPJAM_Bind_Type::register($name.':'.$appid, [
		'name'		=> $name, 
		'appid'		=> $appid,
		'object'	=> is_object($model) ? $model:  new $model($appid)
	]);
}

function wpjam_get_bind_type_object($name, $appid){
	return WPJAM_Bind_Type::get($name.':'.$appid);
}

function wpjam_get_bind_object($name, $appid){
	$instance	= wpjam_get_bind_type_object($name, $appid);

	return $instance ? $instance->object : null;
}

// 注册登录方式
function wpjam_register_user_signup($name, $args){
	WPJAM_User_Signup_Type::register($name, $args);
}

function wpjam_get_user_signups($args=[], $output='objects', $operator='and'){
	return WPJAM_User_Signup_Type::get_registereds($args, $output, $operator);
}

function wpjam_get_user_signup_object($name){
	return WPJAM_User_Signup_Type::get($name);
}

add_action('wp_loaded', function(){
	wpjam_register_bind('phone', '', new WPJAM_Bind('phone', '', ['domain'=>'@phone.sms']));

	wpjam_register_ajax('fetch-login-data',	[
		'nopriv'		=> true, 
		'callback'		=> ['WPJAM_Login_From', 'ajax_callback'],
		'nonce_keys'	=> ['login_action']
	]);

	if(is_login() && wpjam_get_user_signups(['login'=>true])){
		add_action('login_form_login',	['WPJAM_Login_From', 'on_login_form_login']);
	}
});