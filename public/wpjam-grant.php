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

	public function get_items(){
		return $this->items;
	}

	public function validate_access_token(){
		if(!isset($_GET['access_token']) && is_super_admin()){
			return true;
		}

		$token	= wpjam_get_parameter('access_token', ['required'=>true]);

		if($this->items){
			foreach($this->items as $item){
				if(isset($item['token']) && $item['token'] == $token && (time()-$item['time'] < 7200)){
					return true;
				}
			}
		}

		return new WP_Error('invalid_access_token', '非法 Access Token');
	}

	public function generate_access_token(){
		$appid	= wpjam_get_parameter('appid',	['required'=>true]);
		$secret	= wpjam_get_parameter('secret', ['required'=>true]);

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

	public function add(){
		$appid	= 'jam'.strtolower(wp_generate_password(15, false, false));

		if($this->items){
			if(count($this->items) >= 3){
				return new WP_Error('appid_over_quota', '最多可以设置三个APPID');
			}

			$item	= $this->get_by_appid($appid);

			if($item && !is_wp_error($item)){
				return new WP_Error('appid_exists', 'AppId已存在');
			}
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

	public function render_item($appid, $secret=''){
		$secret	= $secret ? '<p class="secret" id="secret_'.$appid.'" style="display:block;">'.$secret.'</p>' : '<p class="secret" id="secret_'.$appid.'"></p>';

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
					<td>点击右侧按钮创建 AppID/Secret，最多可创建三个：</td>
					<td>'.wpjam_get_page_button('create_grant').'</td>
				</tr>
			</tbody>
		</table>
		';
	}

	public function get_api_doc(){
		return '
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
	}

	public function plugin_page(){
		echo '<div class="card">';

		echo '<h3>开发者 ID '.wpjam_get_page_button('access_token').'</h3>';

		if($items = $this->get_items()){
			foreach($items as $item){
				echo $this->render_item($item['appid']);
			} 
		}

		echo $this->render_create_item(count($items));
		
		echo '</div>';
	}

	public function ajax_reset_secret(){
		$appid	= wpjam_get_data_parameter('appid');
		$secret	= $this->reset_secret($appid);

		if(is_wp_error($secret)){
			wpjam_send_json($secret);
		}else{
			wpjam_send_json(compact('appid', 'secret'));
		}
	}

	public function ajax_delete_grant(){
		$appid	= wpjam_get_data_parameter('appid');
		$result	= $this->delete($appid);

		if(is_wp_error($result)){
			wpjam_send_json($result);
		}else{
			wpjam_send_json(compact('appid'));
		}
	}

	public function ajax_create_grant(){
		$appid	= $this->add();

		if(is_wp_error($appid)){
			wpjam_send_json($appid);
		}

		$secret	= $this->reset_secret($appid);
		
		$table 	= $this->render_item($appid, $secret);
		$rest	= 3 - count($this->get_items());

		wpjam_send_json(compact('table', 'rest'));
	}

	public function ajax_access_token(){
		$appid	= $this->add();

		if(is_wp_error($appid)){
			wpjam_send_json($appid);
		}

		$secret	= $this->reset_secret($appid);
		$table 	= $this->render_item($appid, $secret);
		$grants	= $this->get_items();

		$rest	= 3 - count($grants);

		wpjam_send_json(compact('table', 'rest'));
	}

	public function admin_head(){
		?>
		<style type="text/css">
		div.card {max-width:640px; width:640px;}
		
		div.card .form-table{margin: 20px 0; border: none;}
		div.card .form-table th{width: 60px; padding-left: 10px;}

		table.form-table code{display: block; padding: 5px 10px; font-size: smaller; }

		td.appid{font-weight: bold;}
		p.secret{display: none; background: #ffc; padding:4px 8px; font-weight: bold;}
		a.wpjam-button.button{float: right;}
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
		
		<?php
	}

	public function load_plugin_page(){
		wpjam_register_page_action('reset_secret', [
			'button_text'	=> '重置',
			'class'			=> 'button',
			'direct'		=> true,
			'confirm'		=> true,
			'callback'		=> [$this, 'ajax_reset_secret']
		]);

		wpjam_register_page_action('delete_grant', [
			'button_text'	=> '删除',
			'class'			=> 'button',
			'direct'		=> true,
			'confirm'		=> true,
			'callback'		=> [$this, 'ajax_delete_grant']
		]);

		wpjam_register_page_action('create_grant', [
			'button_text'	=> '创建',
			'class'			=> 'button',
			'direct'		=> true,
			'confirm'		=> true,
			'callback'		=> [$this, 'ajax_create_grant']
		]);

		wpjam_register_page_action('access_token', [
			'button_text'	=> '接口文档',
			'submit_text'	=> '',
			'page_title'	=> '获取access_token',
			'class'			=> 'page-title-action button',
			'fields'		=> ['access_token'=>['title'=>'', 'type'=>'view', 'value'=>$this->get_api_doc()]], 
			'callback'		=> [$this, 'ajax_access_token']
		]);

		add_action('admin_head', [$this, 'admin_head']);
	}
}

add_action('wp_loaded', function(){
	$instance	= WPJAM_Grant::get_instance();

	add_filter('wpjam_json_token', [$instance, 'generate_access_token']);
	add_filter('wpjam_json_grant', [$instance, 'validate_access_token']);

	wpjam_register_api('token.grant',		['token'=>true, 'quota'=>1000]);
	wpjam_register_api('token.validate',	['grant'=>true, 'quota'=>10]);

	// if(is_admin()){
	// 	wpjam_add_basic_sub_page('wpjam-grant', [
	// 		'menu_title'	=> '开发设置',
	// 		'load_callback'	=> [$instance, 'load_plugin_page'],
	// 		'function'		=> [$instance, 'plugin_page']
	// 	]);
	// }
});