<?php
class WPJAM_Grant{
	protected $items = [];
	private static $instance = null;

	public static function get_instance(){
		if(is_null(self::$instance)){
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct(){
		$items = get_option('wpjam_grant') ?: [];

		if($items && !wp_is_numeric_array($items)){
			$items	= [$items];

			update_option('wpjam_grant', $items);
		}

		$this->items	= $items;
	}

	public function save(){
		return update_option('wpjam_grant', array_values($this->items));
	}

	public function __call($method, $args){
		if(in_array($method, ['cache_get', 'cache_set', 'cache_add', 'cache_delete'])){
			$cg_obj		= WPJAM_Cache_Group::get_instance('wpjam_api_times');

			$appid		= $args[0];
			$today		= date('Y-m-d', current_time('timestamp'));
			$args[0]	= $args[0] ? $args[0].':'.$today : $today;

			return call_user_func_array([$cg_obj, $method], $args);
		}
	}

	public function get_items(){
		return $this->items;
	}

	public function add(){
		if(count($this->items) >= 3){
			return new WP_Error('appid_over_quota', '最多可以设置三个APPID');
		}

		$appid	= 'jam'.strtolower(wp_generate_password(15, false, false));
		$item	= $this->get_by_appid($appid);

		if($item && !is_wp_error($item)){
			return new WP_Error('appid_exists', 'AppId已存在');
		}

		$this->items[]	= compact('appid');
		$this->save();

		return $appid;
	}

	public function delete($appid){
		$item	= $this->get_by_appid($appid, $index);

		if(is_wp_error($item)){
			return $item;
		}

		unset($this->items[$index]);

		$this->save();
	}

	public function reset_secret($appid){
		$item	= $this->get_by_appid($appid, $index);

		if(is_wp_error($item)){
			return $item;
		}

		$secret	= strtolower(wp_generate_password(32, false, false));

		$item['secret']	= md5($secret);

		unset($item['token']);
		unset($item['time']);

		$this->items[$index]	= $item;
		$this->save();

		return $secret;
	}

	public function reset_token($appid, $secret){
		$item	= $this->get_by_appid($appid, $index);

		if(is_wp_error($item)){
			return $item;
		}

		if(empty($item['secret']) || $item['secret'] != md5($secret)){
			return new WP_Error('invalid_secret', '非法密钥');
		}

		$item['token']	= $token = wp_generate_password(64, false, false);
		$item['time']	= time();

		$this->items[$index]	= $item;
		$this->save();

		return $token;
	}

	public function get_by_appid($appid, &$index=0){
		if($appid && $this->items){
			foreach($this->items as $i=> $item){
				if($item['appid'] == $appid){
					$index	= $i;
					return $item;
				}
			}
		}

		return new WP_Error('invalid_appid', '无效的AppId');
	}

	public function get_by_token($token){
		foreach($this->items as $item){
			if(isset($item['token']) && $item['token'] == $token && (time()-$item['time'] < 7200)){
				return $item;
			}
		}

		return new WP_Error('invalid_access_token', '非法 Access Token');
	}

	public function render_item($appid, $secret=''){
		$secret	= $secret ? '<p class="secret" id="secret_'.$appid.'" style="display:block;">'.$secret.'</p>' : '<p class="secret" id="secret_'.$appid.'"></p>';

		$caches	= $this->cache_get($appid);
		$times	= $caches['token.grant'] ?? 0;

		return '
		<table class="form-table widefat striped" id="table_'.$appid.'">
			<tbody>
				<tr>
					<th>AppID</th>
					<td class="appid">'.$appid.'</td>
					<td>'.wpjam_get_page_button('delete_grant', ['data'=>compact('appid')]).'</td>
				</tr>
				<tr>
					<th>Secret</th>
					<td>出于安全考虑，Secret不再被明文保存，忘记密钥请点击重置：'.$secret.'</td>
					<td>'.wpjam_get_page_button('reset_secret', ['data'=>compact('appid')]).'</td>
				</tr>
				<tr>
					<th>用量</th>
					<td>鉴权接口已被调用了 <strong>'.$times.'</strong> 次，更多接口调用统计请点击'.wpjam_get_page_button('get_stats', ['data'=>compact('appid')]).'</td>
					<td>'.wpjam_get_page_button('clear_quota', ['data'=>compact('appid')]).'</td>
				</tr>
			</tbody>
		</table>
		';
	}

	public function render_create_item($count=0){
		return '
		<table class="form-table widefat striped" id="create_grant" style="'. ($count >=3 ? 'display: none;' : '').'">
			<tbody>
				<tr>
					<th>创建</th>
					<td>点击右侧创建按钮可创建 AppID/Secret，最多可创建三个：</td>
					<td>'.wpjam_get_page_button('create_grant').'</td>
				</tr>
			</tbody>
		</table>
		';
	}

	public static function get_fields($name){
		$instance	= self::get_instance();

		$appid	= wpjam_get_data_parameter('appid');
		$caches	= $instance->cache_get($appid) ?: [];
		$fields	= [];

		if($appid){
			$fields['appid']	= ['title'=>'APPID',	'type'=>'view', 'value'=>$appid];

			$caches['token.grant']	= $caches['token.grant'] ?? 0;
		}

		if($caches){
			foreach($caches as $json => $times){
				$fields[$json]	= ['title'=>$json,	'type'=>'view', 'value'=>$times];
			}
		}else{
			$fields['no']	= ['type'=>'view', 'value'=>'暂无数据'];
		}

		return $fields;
	}

	public static function ajax_clear_quota(){
		$appid		= wpjam_get_data_parameter('appid');
		$instance	= self::get_instance();
		$instance->cache_delete($appid);

		wpjam_send_json(['errmsg'=>'接口已清零']);
	}

	public static function ajax_reset_secret(){
		$appid		= wpjam_get_data_parameter('appid');
		$instance	= self::get_instance();
		$secret		= $instance->reset_secret($appid);

		if(is_wp_error($secret)){
			wpjam_send_json($secret);
		}else{
			wpjam_send_json(compact('appid', 'secret'));
		}
	}

	public static function ajax_create_grant(){
		$instance	= self::get_instance();
		$appid		= $instance->add();

		if(is_wp_error($appid)){
			wpjam_send_json($appid);
		}

		$secret	= $instance->reset_secret($appid);
		
		$table 	= $instance->render_item($appid, $secret);
		$rest	= 3 - count($instance->get_items());

		wpjam_send_json(compact('table', 'rest'));
	}

	public static function ajax_delete_grant(){
		$appid		= wpjam_get_data_parameter('appid');
		$instance	= self::get_instance();
		$result		= $instance->delete($appid);

		if(is_wp_error($result)){
			wpjam_send_json($result);
		}else{
			wpjam_send_json(compact('appid'));
		}
	}

	public static function load_plugin_page(){
		wpjam_register_page_action('reset_secret', [
			'button_text'	=> '重置',
			'class'			=> 'button',
			'direct'		=> true,
			'confirm'		=> true,
			'callback'		=> [self::class, 'ajax_reset_secret']
		]);

		wpjam_register_page_action('delete_grant', [
			'button_text'	=> '删除',
			'class'			=> 'button',
			'direct'		=> true,
			'confirm'		=> true,
			'callback'		=> [self::class, 'ajax_delete_grant']
		]);

		wpjam_register_page_action('create_grant', [
			'button_text'	=> '创建',
			'class'			=> 'button',
			'direct'		=> true,
			'confirm'		=> true,
			'callback'		=> [self::class, 'ajax_create_grant']
		]);

		wpjam_register_page_action('get_stats', [
			'button_text'	=> '用量',
			'submit_text'	=> '',
			'class'			=> '',
			'width'			=> 500,
			'fields'		=> [self::class, 'get_fields']
		]);

		wpjam_register_page_action('clear_quota', [
			'button_text'	=> '清零',
			'class'			=> 'button button-primary',
			'direct'		=> true,
			'confirm'		=> true,
			'callback'		=> [self::class, 'ajax_clear_quota']
		]);

		$doc	= '
		<p>access_token 是开放接口的全局<strong>接口调用凭据</strong>，第三方调用各接口时都需使用 access_token，开发者需要进行妥善保存。</p>
		<p>access_token 的有效期目前为2个小时，需定时刷新，重复获取将导致上次获取的 access_token 失效。</p>

		<h4>请求地址</h4>

		<p><code>'.home_url('/api/').'token/grant.json?appid=APPID&secret=APPSECRET</code></p>

		<h4>参数说明<h4>

		'.do_shortcode('[table th=1 class="form-table striped"]
		参数	
		是否必须
		说明

		appid
		是
		第三方用户凭证

		secret
		是
		第三方用户证密钥。
		[/table]').'
		
		<h4>返回说明</h4>

		<p><code>
			{"errcode":0,"access_token":"ACCESS_TOKEN","expires_in":7200}
		</code></p>';

		wpjam_register_page_action('access_token', [
			'button_text'	=> '接口文档',
			'submit_text'	=> '',
			'page_title'	=> '获取access_token',
			'class'			=> 'page-title-action button',
			'fields'		=> ['access_token'=>['type'=>'view', 'value'=>$doc]], 
		]);

		add_action('admin_head', function(){ ?>
		<style type="text/css">
		div.card {max-width:640px; width:640px;}
		
		div.card .form-table{margin: 20px 0; border: none;}
		div.card .form-table th{width: 60px; padding-left: 10px;}

		table.form-table code{display: block; padding: 5px 10px; font-size: smaller; }

		td.appid{font-weight: bold;}
		p.secret{display: none; background: #ffc; padding:4px 8px; font-weight: bold;}
		h3 span.page-actions{display: flex; align-content: center; justify-content: space-between; float:right;}
		</style>

		<script type="text/javascript">
		jQuery(function($){
			$('body').on('page_action_success', function(e, response){
				if(response.page_action == 'reset_secret'){
					$('p#secret_'+response.appid).show().html(response.secret);
				}else if(response.page_action == 'create_grant'){
					$('table#create_grant').before(response.table);
					if(response.rest == 0){
						$('table#create_grant').hide();
					}
				}else if(response.page_action == 'delete_grant'){
					$('table#table_'+response.appid).remove();
					
					$('table#create_grant').show();
				}
			});
		});
		</script>
		
		<?php });
	}

	public static function plugin_page(){
		$instance	= self::get_instance();

		echo '<div class="card">';

		echo '<h3>开发者 ID<span class="page-actions">'.wpjam_get_page_button('access_token').'</span></h3>';

		if($items = $instance->get_items()){
			foreach($items as $item){
				echo $instance->render_item($item['appid']);
			} 
		}

		echo $instance->render_create_item(count($items));
		
		echo '</div>';
	}

	public static function validate($json_obj){
		if(!isset($_GET['access_token']) && is_super_admin()){
			return;
		}

		$instance	= self::get_instance();

		if($json_obj->name == 'token.grant'){
			$appid	= wpjam_get_parameter('appid',	['required'=>true]);
		}else{
			if($json_obj->grant){
				$token	= wpjam_get_parameter('access_token', ['required'=>true]);
				$item 	= $instance->get_by_token($token);

				if(is_wp_error($item)){
					wpjam_send_json($item);
				}

				$appid	= $item['appid'];
			}else{
				$appid	= '';
			}
		}

		$caches	= $instance->cache_get($appid) ?: [];
		$times	= $caches[$json_obj->name] ?? 0;

		$caches[$json_obj->name]	= $times+1;

		$instance->cache_set($appid, $caches);

		if($json_obj->quota && $times > $json_obj->quota){
			wpjam_send_json(['errcode'=>'api_exceed_quota', 'errmsg'=>'API 调用次数超限']);
		}
	}

	public static function generate_token(){
		$instance	= self::get_instance();
		$appid		= wpjam_get_parameter('appid',	['required'=>true]);
		$secret		= wpjam_get_parameter('secret', ['required'=>true]);
		$token		= $instance->reset_token($appid, $secret);

		if(is_wp_error($token)){
			wpjam_send_json($token);
		}
		
		wpjam_send_json(['access_token'=>$token, 'expires_in'=>7200]);
	}
}

add_action('wp_loaded', function(){
	add_action('wpjam_json_response',	['WPJAM_Grant', 'validate']);

	wpjam_register_api('token.grant',		['quota'=>1000,	'callback'=>['WPJAM_Grant', 'generate_token']]);
	wpjam_register_api('token.validate',	['quota'=>10,	'grant'=>true]);

	if(is_admin()){
		add_action('wpjam_admin_init', function(){
			if($GLOBALS['plugin_page'] == 'wpjam-grant'){
				wpjam_add_basic_sub_page('wpjam-grant', [
					'menu_title'	=> '开发设置',
					'load_callback'	=> ['WPJAM_Grant', 'load_plugin_page'],
					'function'		=> ['WPJAM_Grant', 'plugin_page']
				]);
			}
		});
	}
});