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
			return new WP_Error('already_binded', '请先取消绑定，再绑定！');
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

	public static function update_meta($user_id, $meta_key, $meta_value){
		if($meta_value){
			return update_user_meta($user_id, $meta_key, wp_slash($meta_value));
		}else{
			return delete_user_meta($user_id, $meta_key);
		}
	}

	public static function update_metas($user_id, $data){
		foreach($data as $meta_key => $meta_value){
			self::update_meta($user_id, $meta_key, $meta_value);
		}

		return true;
	}

	public static function value_callback($meta_key, $user_id){
		if($user_id && metadata_exists('user', $user_id, $meta_key)){
			return get_user_meta($user_id, $meta_key, true);
		}

		return null;
	}

	public static function get_current_user($required=false){
		$current_user	= apply_filters('wpjam_current_user', null);

		if($required){
			if(is_null($current_user)){
				return new WP_Error('bad_authentication', '无权限');
			}
		}else{
			if(is_wp_error($current_user)){
				return null;
			}
		}

		return $current_user;
	}

	public static function get_current_commenter(){
		if(get_option('comment_registration')){
			return new WP_Error('logged_in_required', '只支持登录用户操作');
		}

		$commenter	= wp_get_current_commenter();

		if(empty($commenter['comment_author_email'])){
			return new WP_Error('bad_authentication', '无权限');
		}

		return $commenter;
	}

	public static function filter_current_user($user_id){
		if(empty($user_id)){
			$wpjam_user	= self::get_current_user();

			if($wpjam_user && !empty($wpjam_user['user_id'])){
				return $wpjam_user['user_id'];
			}
		}

		return $user_id;
	}

	public static function filter_current_commenter($commenter){
		if(empty($commenter['comment_author_email'])){
			$wpjam_user	= self::get_current_user();

			if($wpjam_user && !empty($wpjam_user['user_email'])){
				$commenter['comment_author_email']	= $wpjam_user['user_email'];
				$commenter['comment_author']		= $wpjam_user['nickname'];
			}
		}

		return $commenter;
	}
}

class WPJAM_Bind_Type{
	public static $types	= [];

	public static function register($name, $appid, $model){
		if(!isset(self::$types[$name])){
			self::$types[$name]	= [];
		}

		self::$types[$name][$appid]	= new $model($appid);
	}

	public static function get_by($name=''){
		if($name){
			return self::$types[$name] ?? [];
		}else{
			return self::$types;
		}
	}

	public static function get($name, $appid){
		return self::$types[$name][$appid] ?? null;
	}
}

abstract class WPJAM_Bind{
	protected $name;
	protected $appid;

	public function __construct($name, $appid){
		$this->name		= $name;
		$this->appid	= $appid;
	}

	public function get_bind($openid, $bind_field, $unionid=false){
		$user	= $this->get_user($openid);

		if(is_wp_error($user)){
			return $user;
		}elseif(empty($user)){
			return new WP_Error('invalid_openid', '无效的 Openid');
		}

		return $user[$bind_field] ?? null;
	}

	public function update_bind($openid, $bind_field, $bind_value){
		$user	= $this->get_user($openid);

		if($user[$bind_field] != $bind_value){
			return $this->update_user($openid, [$bind_field=>$bind_value]);
		}

		return true;
	}

	public function get_avatarurl($openid){
		$user	= $this->get_user($openid);
		return $user['avatarurl'] ?? '';
	}

	public function get_nickname($openid){
		$user	= $this->get_user($openid);
		return $user['nickname'] ?? '';
	}

	public function get_unionid($openid){
		$user	= $this->get_user($openid);
		return $user['unionid'] ?? '';
	}

	public function get_email($openid){
		return $openid.'@'.$this->appid.'.'.$this->name;
	}

	abstract public function get_user($openid);
	abstract public function update_user($openid, $user);
}

class WPJAM_Phone_Bind extends WPJAM_Bind{
	public function __construct(){
		parent::__construct('phone', '');
	}

	public function get_email($phone){
		return $phone.'@phone.sms';
	}

	public function get_bind($phone, $bind_field, $unionid=false){
		return null;
	}

	public function update_bind($phone, $bind_field, $bind_value){
		return true;
	}

	public function get_openid_by($key, $value){
		return '';
	}

	public function get_avatarurl($phone){
		return '';
	}

	public function get_nickname($phone){
		return '';
	}

	public function get_user($openid){
		return [];
	}

	public function update_user($openid, $user){
		return true;
	}
}

trait WPJAM_Qrcode_Bind_Trait{
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
}