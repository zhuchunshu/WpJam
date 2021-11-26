<?php
class WPJAM_User{
	private $user_id;

	private function __construct($user_id){
		$this->user_id	= (int)$user_id;
	}

	public function __get($name){
		if(in_array($name, ['id', 'user_id'])){
			return $this->user_id;
		}elseif($name == 'user'){
			return get_userdata($this->user_id);
		}elseif($name == 'avatarurl'){
			return get_user_meta($this->user_id, 'avatarurl', true);
		}else{
			return $this->user->$name;
		}
	}

	public function update_avatarurl($avatarurl){
		if($this->avatarurl != $avatarurl){
			update_user_meta($this->user_id, 'avatarurl', $avatarurl);
		}

		return true;
	}

	public function update_nickname($nickname){
		if($this->nickname != $nickname){
			self::update($this->user_id, ['nickname'=>$nickname, 'display_name'=>$nickname]);
		}

		return true;
	}

	public function add_role($role, $blog_id=0){
		$switched	= (is_multisite() && $blog_id) ? switch_to_blog($blog_id) : false;	// 不同博客的用户角色不同
		$wp_error	= null;

		if($this->user->roles){
			if(!in_array($role, $this->user->roles)){
				$wp_error	= new WP_Error('role_added', '你已有权限，如果需要更改权限，请联系管理员直接修改。');
			}
		}else{
			$this->user->add_role($role);
		}

		if($switched){
			restore_current_blog();
		}

		return $wp_error ?? $this->user;
	}

	public function get_openid($name, $appid=''){
		$bind_key	= $this->get_bind_key($name, $appid);

		return get_user_meta($this->user_id, $bind_key, true);
	}

	public function update_openid($name, $appid, $openid){
		$bind_key	= $this->get_bind_key($name, $appid);

		return (bool)update_user_meta($this->user_id, $bind_key, $openid);
	}

	public function delete_openid($name, $appid=''){
		$bind_key	= $this->get_bind_key($name, $appid);

		return (bool)delete_user_meta($this->user_id, $bind_key);
	}

	public function bind($name, $appid, $openid){
		$bind_id	= self::get_by_openid($name, $appid, $openid);

		if(is_wp_error($bind_id)){
			return $bind_id;
		}

		if($bind_id && $bind_id != $this->user_id && get_userdata($bind_id)){
			return new WP_Error('already_binded', '已绑定其他账号。');
		}

		$current_openid	= $this->get_openid($name, $appid);

		if($current_openid && $current_openid != $openid){
			return new WP_Error('already_binded', '该账号已经绑定了其他用户，请先取消绑定再处理！');
		}

		$bind_obj	= wpjam_get_bind_object($name, $appid);

		$bind_obj->update_bind($openid, 'user_id', $this->user_id);

		if($avatarurl = $bind_obj->get_avatarurl($openid)){
			$this->update_avatarurl($avatarurl);
		}

		if(!$this->nickname && ($nickname = $bind_obj->get_nickname($openid))){
			$this->update_nickname($nickname);
		}

		if($current_openid != $openid){
			return $this->update_openid($name, $appid, $openid);
		}else{
			return true;
		}
	}

	public function unbind($name, $appid=''){
		$bind_obj	= wpjam_get_bind_object($name, $appid);

		if(!$bind_obj){
			return false;
		}

		if($openid = $this->get_openid($name, $appid)){
			$this->delete_openid($name, $appid);
		}else{
			$openid	= $bind_obj->get_openid_by('user_id', $this->user_id);
		}

		if($openid){
			$bind_obj->update_bind($openid, 'user_id', 0);
		}

		return $openid;
	}

	public function login(){
		wp_set_auth_cookie($this->user_id, true, is_ssl());
		wp_set_current_user($this->user_id);
		do_action('wp_login', $this->user_login, $this->user);
	}

	public function parse_for_json(){
		$user_json	= [];

		$user_json['id']		= $this->user_id;
		$user_json['nickname']	= $this->nickname;
		$user_json['name']		= $user_json['display_name'] = $this->display_name;
		$user_json['avatar']	= get_avatar_url($this->user);

		return apply_filters('wpjam_user_json', $user_json, $this->user_id);
	}

	private static $instances	= [];

	public static function get_instance($user_id){
		$user	= $user_id ? self::get_user($user_id) : null;

		if(!$user || !($user instanceof WP_User)){
			return new WP_Error('user_not_exists', '用户不存在');
		}

		$user_id	= $user->ID;

		if(!isset($instances[$user_id])){
			$instances[$user_id]	= new self($user_id);
		}

		return $instances[$user_id];
	}

	public static function get_user($user){
		if($user && is_numeric($user)){	// 不存在情况下的缓存优化
			$user_id	= $user;
			$found		= false;
			$cache		= wp_cache_get($user_id, 'users', false, $found);

			if($found){
				return $cache ? get_userdata($user_id) : $cache;
			}else{
				$user	= get_userdata($user_id);

				if(!$user){	// 防止重复 SQL 查询。
					wp_cache_add($user_id, false, 'users', 10);
				}
			}
		}

		return $user;
	}

	public static function get($id){
		$user	= get_userdata($id);
		return $user ? $user->to_array() : [];
	}

	public static function insert($data){
		return wp_insert_user(wp_slash($data));
	}

	public static function update($user_id, $data){
		$data['ID'] = $user_id;

		return wp_update_user(wp_slash($data));
	}

	public static function create($args){
		$args	= wp_parse_args($args, [
			'user_pass'		=> wp_generate_password(12, false),
			'user_login'	=> '',
			'user_email'	=> '',
			'nickname'		=> '',
			'avatarurl'		=> '',
			'role'			=> '',
			'blog_id'		=> 0
		]);

		$users_can_register		= $args['users_can_register'] ?? get_option('users_can_register');

		if(!$users_can_register){
			return new WP_Error('register_disabled', '系统不开放注册，请联系管理员！');
		}

		$args['user_login']	= preg_replace('/\s+/', '', sanitize_user($args['user_login'], true));

		if(empty($args['user_login'])){
			return new WP_Error('empty_user_login', '用户名不能为空。');
		}

		if(empty($args['user_email'])){
			return new WP_Error('empty_user_email', '用户邮箱不能为空。');
		}

		$register_lock	= wp_cache_get($args['user_login'].'_register_lock', 'users');

		if($register_lock !== false){
			return new WP_Error('user_register_locked', '该用户名正在注册中，请稍后再试！');
		}

		$result	= wp_cache_add($args['user_login'].'_register_lock', 1, 'users', 15);

		if($result === false){
			return new WP_Error('user_register_locked', '该用户名正在注册中1，请稍后再试！');
		}

		$userdata	= wp_array_slice_assoc($args, ['user_login', 'user_pass', 'user_email']);

		if(!empty($args['nickname'])){
			$userdata['nickname']	= $userdata['display_name']	= $args['nickname'];
		}

		$switched	= (is_multisite() && $args['blog_id']) ? switch_to_blog($args['blog_id']) : false;

		$userdata['role']	= $args['role'] ?: get_option('default_role');

		$user_id	= self::insert($userdata);

		if($switched){
			restore_current_blog();
		}

		return $user_id;
	}

	public static function get_bind_key($name, $appid=''){
		return $appid ? $name.'_'.$appid : $name;
	}

	public static function get_by_openid($name, $appid, $openid){
		$bind_obj	= wpjam_get_bind_object($name, $appid);

		if(!$bind_obj){
			return new WP_Error('bind_not_exists', '不支持该登录方式');
		}

		if(!$bind_obj->get_user($openid)){
			return new WP_Error('invalid_openid', '无效的 Openid');
		}

		$user_id	= $bind_obj->get_bind($openid, 'user_id', true);

		if(is_wp_error($user_id) || ($user_id && get_userdata($user_id))){
			return $user_id;
		}

		$bind_key	= self::get_bind_key($name, $appid);

		if($users = get_users(['meta_key'=>$bind_key, 'meta_value'=>$openid])){
			return current($users)->ID;
		}

		return username_exists($openid);
	}

	public static function signup($name, $appid, $openid, $args){
		$user_id	= self::get_by_openid($name, $appid, $openid);

		if(is_wp_error($user_id)){
			return $user_id;
		}

		if(!$user_id){
			$bind_obj	= wpjam_get_bind_object($name, $appid);
			$is_create	= true;

			$args['user_login']	= $openid;
			$args['user_email']	= $bind_obj->get_email($openid);
			$args['nickname']	= $bind_obj->get_nickname($openid);

			$user_id	= self::create($args);

			if(is_wp_error($user_id)){
				return $user_id;
			}
		}else{
			$is_create	= false;
		}

		$wpjam_user	= self::get_instance($user_id);

		if(!$is_create && !empty($args['role'])){
			$blog_id	= $args['blog_id'] ?? 0;
			$result		= $wpjam_user->add_role($args['role'], $blog_id);

			if(is_wp_error($result)){
				return $result;
			}
		}

		$wpjam_user->bind($name, $appid, $openid);
		$wpjam_user->login();

		$user = $wpjam_user->user;

		do_action('wpjam_user_signuped', $user, $args);

		return $user;
	}

	public static function get_meta($user_id, ...$args){
		return WPJAM_Meta::get_data('user', $user_id, ...$args);
	}

	public static function update_meta($user_id, ...$args){
		return WPJAM_Meta::update_data('user', $user_id, ...$args);
	}

	public static function update_metas($user_id, $data, $meta_keys=[]){
		return WPJAM_Meta::update_data('user', $user_id, $data, $meta_keys);
	}

	public static function value_callback($meta_key, $user_id){
		return self::get_meta($user_id, $meta_key);
	}
}