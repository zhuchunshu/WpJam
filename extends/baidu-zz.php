<?php
/*
Name: 百度站长
URI: https://blog.wpjam.com/m/baidu-zz/
Description: 支持主动，被动，自动以及批量方式提交链接到百度站长。
Version: 1.0
*/
class WPJAM_Baidu_ZZ{
	use WPJAM_Setting_Trait;

	private function __construct(){
		$this->init('baidu-zz');
	}

	public function notify($urls, $args=[]){
		$query_args	= [];

		$query_args['site']		= $this->get_setting('site');
		$query_args['token']	= $this->get_setting('token');

		if(empty($query_args['site']) || empty($query_args['token'])){
			return;
		}

		$update	= $args['update'] ?? false;
		$type	= $args['type'] ?? '';

		if(empty($type) && $this->get_setting('mip')){
			$type	= 'mip';
		}

		if($type){
			$query_args['type']	= $type;
		}

		if($update){
			$baidu_zz_api_url	= add_query_arg($query_args, 'http://data.zz.baidu.com/update');
		}else{
			$baidu_zz_api_url	= add_query_arg($query_args, 'http://data.zz.baidu.com/urls');
		}

		return wp_remote_post($baidu_zz_api_url, array(
			'headers'	=> ['Accept-Encoding'=>'','Content-Type'=>'text/plain'],
			'sslverify'	=> false,
			'blocking'	=> false,
			'body'		=> $urls
		));
	}

	public function notify_post_urls($post_id){
		$urls	= '';

		if(is_array($post_id)){
			$post_ids	= $post_id;

			foreach ($post_ids as $post_id) {
				if(get_post($post_id)->post_status == 'publish'){
					if(wp_cache_get($post_id, 'wpjam_baidu_zz_notified') === false){
						wp_cache_set($post_id, true, 'wpjam_baidu_zz_notified', HOUR_IN_SECONDS);
						$urls	.= apply_filters('baiduz_zz_post_link', get_permalink($post_id))."\n";
					}
				}
			}
		}else{
			if(get_post($post_id)->post_status == 'publish'){
				if(wp_cache_get($post_id, 'wpjam_baidu_zz_notified') === false){
					wp_cache_set($post_id, true, 'wpjam_baidu_zz_notified', HOUR_IN_SECONDS);
					$urls	.= apply_filters('baiduz_zz_post_link', get_permalink($post_id))."\n";
				}else{
					return new WP_Error('has_submited', '一小时内已经提交过了');
				}
			}else{
				return new WP_Error('invalid_post_status', '未发布的文章不能同步到百度站长');
			}
		}

		if($urls){
			$this->notify($urls);
		}else{
			return new WP_Error('empty_urls', '没有需要提交的链接');
		}

		return true;
	}

	public static function ajax_submit(){
		$instance	= self::get_instance();
		$offset		= (int)wpjam_get_data_parameter('offset',	['default'=>0]);
		$type		= wpjam_get_data_parameter('type',		['default'=>'post']);

		// $types	= apply_filters('wpjam_baidu_zz_batch_submit_types', ['post']);

		// if($type){
		// 	$index	= array_search($type, $types);
		// 	$types	= array_slice($types, $index, -1);
		// }

		// foreach ($types as $type) {
			if($type=='post'){
				$_query	= new WP_Query([
					'post_type'			=>'any',
					'post_status'		=>'publish',
					'posts_per_page'	=>100,
					'offset'			=>$offset
				]);

				if($_query->have_posts()){
					$count	= count($_query->posts);
					$number	= $offset+$count;

					$urls	= '';

					while($_query->have_posts()){
						$_query->the_post();

						if(wp_cache_get(get_the_ID(), 'wpjam_baidu_zz_notified') === false){
							wp_cache_set(get_the_ID(), true, 'wpjam_baidu_zz_notified', HOUR_IN_SECONDS);
							$urls	.= apply_filters('baiduz_zz_post_link', get_permalink())."\n";
						}
					}

					$instance->notify($urls);

					$args	= http_build_query(['type'=>$type, 'offset'=>$number]);

					return ['done'=>0, 'errmsg'=>'批量提交中，请勿关闭浏览器，已提交了'.$number.'个页面。',	'args'=>$args];
				}else{
					return true;
				}
			}else{
				// do_action('wpjam_baidu_zz_batch_submit', $type, $offset);
				// wpjam_send_json();
			}
		// }
	}

	public function on_after_insert_post($post_id, $post, $update, $post_before){
		if((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || $post->post_status != 'publish' || !current_user_can('edit_post', $post_id)){
			return;
		}

		if($update){
			$baidu_zz_daily	= wpjam_get_parameter('baidu_zz_daily',	['method'=>'POST']);

			if($baidu_zz_daily || wp_cache_get($post_id, 'wpjam_baidu_zz_notified') === false){
				wp_cache_set($post_id, true, 'wpjam_baidu_zz_notified', HOUR_IN_SECONDS);

				$post_link	= apply_filters('baiduz_zz_post_link', get_permalink($post_id), $post_id);

				$args	= [];

				if($baidu_zz_daily){
					$args['type']	= 'daily';
				}

				$this->notify($post_link, $args);
			}
		}else{
			$post_link	= apply_filters('baiduz_zz_post_link', get_permalink($post_id), $post_id);

			$args	= [];

			if(wpjam_get_parameter('baidu_zz_daily',	['method'=>'POST'])){
				$args['type']	= 'daily';
			}

			$this->notify($post_link, $args);
		}
	}

	public function on_publish_future_post($post_id){
		$urls	= apply_filters('baiduz_zz_post_link', get_permalink($post_id))."\n";

		wp_cache_set($post_id, true, 'wpjam_baidu_zz_notified', HOUR_IN_SECONDS);

		$this->notify($urls);
	}

	public function on_enqueue_scripts(){
		if(is_404() || is_preview()){
			return;
		}elseif(is_singular() && get_post_status() != 'publish'){
			return;
		}

		if(is_ssl()){
			wp_enqueue_script('baidu_zz_push', 'https://zz.bdstatic.com/linksubmit/push.js', '', '', true);
		}else{
			wp_enqueue_script('baidu_zz_push', 'http://push.zhanzhang.baidu.com/push.js', '', '', true);
		}
	}

	public function on_post_submitbox_misc_actions(){ ?>
		<div class="misc-pub-section" id="baidu_zz_section">
			<input type="checkbox" name="baidu_zz_daily" id="baidu_zz" value="1">
			<label for="baidu_zz_daily">提交给百度站长快速收录</label>
		</div>
	<?php }

	public function on_builtin_page_load($screen_base, $current_screen){
		if($screen_base == 'edit'){
			if(is_post_type_viewable($current_screen->post_type)){
				wpjam_register_list_table_action('notify_baidu_zz', [
					'title'			=> '提交到百度',
					'post_status'	=> ['publish'],
					'callback'		=> [$this, 'notify_post_urls'],
					'bulk'			=> true,
					'direct'		=> true
				]);
			}
		}elseif($screen_base == 'post'){
			if(is_post_type_viewable($current_screen->post_type)){
				add_action('wp_after_insert_post',			[$this, 'on_after_insert_post'], 10, 4);
				add_action('post_submitbox_misc_actions',	[$this, 'on_post_submitbox_misc_actions'],11);
				
				wp_add_inline_style('list-tables', '#post-body #baidu_zz_section:before {content: "\f103"; color:#82878c; font: normal 20px/1 dashicons; speak: none; display: inline-block; margin-left: -1px; padding-right: 3px; vertical-align: top; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }');
			}
		}
	}

	public static function load_plugin_page(){
		wpjam_set_plugin_page_summary('百度站长扩展实现提交链接到百度站长，让博客的文章能够更快被百度收录，详细介绍请点击：<a href="https://blog.wpjam.com/m/baidu-zz/" target="_blank">百度站长</a>。');

		wpjam_register_plugin_page_tab('baidu-zz', [
			'title'			=> '百度站长',	
			'function'		=> 'option',
			'option_name'	=> 'baidu-zz',
			'fields'		=> [
				'site'	=> ['title'=>'站点 (site)',	'type'=>'text',	'class'=>'all-options'],
				'token'	=> ['title'=>'密钥 (token)',	'type'=>'password'],
				'mip'	=> ['title'=>'MIP',			'type'=>'checkbox', 'description'=>'博客已支持MIP'],
				'no_js'	=> ['title'=>'不加载推送JS',	'type'=>'checkbox', 'description'=>'插件已支持主动推送，不加载百度推送JS'],
			]
		]);

		wpjam_register_plugin_page_tab('batch', [
			'title'			=> '批量提交',
			'function'		=> 'form',
			'submit_text'	=> '批量提交',
			'callback'		=> ['WPJAM_Baidu_ZZ', 'ajax_submit'],
			'summary'		=> '使用百度站长更新内容接口批量将博客中的所有内容都提交给百度搜索资源平台。'
		]);
	}
}

function wpjam_notify_baidu_zz($urls, $args=[]){
	return WPJAM_Baidu_ZZ::get_instance()->notify($urls, $args);
}

add_action('wp_loaded', function(){
	$instance	= WPJAM_Baidu_ZZ::get_instance();

	add_action('publish_future_post',	[$instance, 'on_publish_future_post'], 11);

	if(!$instance->get_setting('no_js')){
		add_action('wp_enqueue_scripts',	[$instance, 'on_enqueue_scripts']);
	}

	if(is_admin()){
		wpjam_add_basic_sub_page('baidu-zz',	[
			'menu_title'	=> '百度站长',
			'network'		=> false,
			'function'		=> 'tab',	
			'load_callback'	=> ['WPJAM_Baidu_ZZ', 'load_plugin_page']
		]);
		
		add_action('wpjam_builtin_page_load',	[$instance, 'on_builtin_page_load'], 10, 2);
	}
});

	