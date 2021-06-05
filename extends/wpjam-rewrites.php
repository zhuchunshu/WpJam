<?php
/*
Name: Rewrite 优化
URI: https://blog.wpjam.com/m/wpjam-rewrite/
Description: 清理无用的 Rewrite 规则和新增自定义 Rewrite 规则。
Version: 1.0
*/
class WPJAM_Rewrite{
	use WPJAM_Setting_Trait;

	private function __construct(){
		$this->init('wpjam-basic', true);
	}

	public function remove_rules(&$rules){
		$unuse_rewrite_keys = ['comment-page', 'feed=', 'attachment'];

		foreach ($unuse_rewrite_keys as $i=>$unuse_rewrite_key) {
			if($this->get_setting('remove_'.$unuse_rewrite_key.'_rewrite') == false){
				unset($unuse_rewrite_keys[$i]);
			}
		}

		if(wpjam_basic_get_setting('disable_post_embed')){
			$unuse_rewrite_keys[]	= '&embed=true';
		}

		if(wpjam_basic_get_setting('disable_trackbacks')){
			$unuse_rewrite_keys[]	= '&tb=1';
		}

		if($unuse_rewrite_keys){
			foreach ($rules as $key => $rule) {
				if($rule == 'index.php?&feed=$matches[1]'){
					continue;
				}

				foreach ($unuse_rewrite_keys as $unuse_rewrite_key) {
					if(strpos($key, $unuse_rewrite_key) !== false || strpos($rule, $unuse_rewrite_key) !== false){
						unset($rules[$key]);
					}
				}
			}
		}
	}

	public function filter_attachment_link($link, $post_id){
		return wp_get_attachment_url($post_id);
	}

	public function on_generate_rewrite_rules($wp_rewrite){
		$this->remove_rules($wp_rewrite->rules); 
		$this->remove_rules($wp_rewrite->extra_rules_top);

		if($rewrites	= $this->get_setting('rewrites')){
			$wp_rewrite->rules = array_merge(wp_list_pluck($rewrites, 'query', 'regex'), $wp_rewrite->rules);
		}
	}

	public function load_plugin_page(){
		if(!is_multisite() || !is_network_admin()){
			wpjam_register_plugin_page_tab('rules',	['title'=>'Rewrites 规则',	'function'=>'list']);	
		}

		wpjam_register_plugin_page_tab('optimize',	['title'=>'Rewrites 优化',	'function'=>'option',	'option_name'=>'wpjam-basic']);

		wpjam_register_list_table('wpjam-rewrites', [
			'plural'	=> 'rewrites',
			'singular' 	=> 'rewrite',
			'model'		=> 'WPJAM_Rewrites_Admin',
			'fixed'		=> false,
			'per_page'	=> 300
		]);

		wpjam_register_option('wpjam-basic', [
			'summary'	=>'如果你的网站没有使用以下页面，可以移除相关功能的的 Rewrites 规则以提高网站效率！',
			'fields'	=> [
				'remove_date_rewrite'			=> ['title'=>'',	'type'=>'checkbox',	'description'=>'移除日期 Rewrite 规则'],
				'remove_comment_rewrite'		=> ['title'=>'',	'type'=>'checkbox',	'description'=>'移除留言 Rewrite 规则'],
				'remove_comment-page_rewrite'	=> ['title'=>'',	'type'=>'checkbox',	'description'=>'移除留言分页 Rewrite 规则'],
				'remove_feed=_rewrite'			=> ['title'=>'',	'type'=>'checkbox',	'description'=>'移除分类 Feed Rewrite 规则'],
				'remove_attachment_rewrite'		=> ['title'=>'',	'type'=>'checkbox',	'description'=>'移除附件 Rewrite 规则']
			],
			'update_callback'	=> function($option, $value){
				update_option($option, $value);
				update_option('rewrite_rules', '');
			},
			'site_default'		=> true
		]);
	}
}

class WPJAM_Rewrites_Admin{
	public static function get_all(){
		return get_option('rewrite_rules') ?: [];
	}

	public static function get_rewrites(){
		return wpjam_basic_get_setting('rewrites') ?: [];
	}

	public static function update_rewrites($rewrites){
		wpjam_basic_update_setting('rewrites', $rewrites);
		flush_rewrite_rules();
		return true;
	}

	public static function get($id){
		$rewrites	= self::get_all();
		$regex_arr	= array_keys($rewrites);

		$regex		= $regex_arr[($id-1)] ?? '';

		if($regex){
			$query	= $rewrites[$regex];
			return compact('id', 'regex', 'query');
		}else{
			return [];
		}
	}

	public static function prepare($data, $id=''){
		$regex	= $data['regex'] ?? '';
		$query	= $data['query'] ?? '';

		if(empty($regex) || empty($query)){
			return new WP_error('empty_regex', 'Rewrite 规则不能为空');
		}

		$rewrites	= self::get_all();

		if($id){
			$current	= self::get($id);

			if(empty($current)){
				return new WP_error('invalid_regex', '该 Rewrite 规则不存在');
			}elseif($current['regex'] != $regex && isset($rewrites[$regex])){
				return new WP_error('invalid_regex', '该 Rewrite 规则已使用');
			}
		}else{
			if(isset($rewrites[$regex])){
				return new WP_error('duplicate_regex', '该 Rewrite 规则已存在');
			}
		}

		return $data;
	}

	public static function insert($data){
		$data	= self::prepare($data);
		if(is_wp_error($data)){
			return $data;
		}

		$rewrites	= self::get_rewrites();
		$rewrites	= array_merge([$data], $rewrites);

		return self::update_rewrites($rewrites);
	}

	public static function update($id, $data){
		$data	= self::prepare($data, $id);
		if(is_wp_error($data)){
			return $data;
		}

		$current	= self::get($id);
		$rewrites	= self::get_rewrites();
		foreach ($rewrites as $i => $rewrite){
			if($rewrite['regex'] == $current['regex']){
				$rewrites[$i]	= $data;
				break;
			}
		}

		return self::update_rewrites($rewrites);
	}

	public static function delete($id){
		$current	= self::get($id);
		if(empty($current)){
			return new WP_error('invalid_regex', '该 Rewrite 规则不存在');
		}

		$rewrites	= self::get_rewrites();
		foreach ($rewrites as $i => $rewrite){
			if($rewrite['regex'] == $current['regex']){
				unset($rewrites[$i]);
				break;
			}
		}

		return self::update_rewrites($rewrites);
	}

	public static function query_items($limit, $offset){
		$rewrites	= self::get_all();
		$items		= [];

		$id			= 0;

		foreach ($rewrites as $regex => $query) {
			$id++;
			$items[]	= compact('id', 'regex', 'query');
		}

		return ['items'=>$items, 'total'=>count($items)];
	}

	public static function item_callback($item){
		$rewrites	= self::get_rewrites();

		$rewrites	= $rewrites ? wp_list_pluck($rewrites, 'query', 'regex') : [];
		if(!$rewrites || !isset($rewrites[$item['regex']])){
			unset($item['row_actions']);
			$item['regex']	= wpautop($item['regex']);
		}

		$item['query']	= wpautop($item['query']);

		return $item;
	}

	public static function get_actions(){
		return [
			'add'		=> ['title'=>'新建',	'response'=>'list'],
			'edit'		=> ['title'=>'编辑'],
			'delete'	=> ['title'=>'删除',	'direct'=>true,	'response'=>'list']
		];
	}

	public static function get_fields($action_key='', $id=0){
		return [
			'regex'		=> ['title'=>'正则',		'type'=>'text',	'show_admin_column'=>true],
			'query'		=> ['title'=>'查询',		'type'=>'text',	'show_admin_column'=>true]
		];
	}
}

add_action('init', function(){
	$instance	= WPJAM_Rewrite::get_instance();

	if($instance->get_setting('remove_date_rewrite')){
		add_filter('date_rewrite_rules', '__return_empty_array');

		remove_rewrite_tag('%year%');
		remove_rewrite_tag('%monthnum%');
		remove_rewrite_tag('%day%');
		remove_rewrite_tag('%hour%');
		remove_rewrite_tag('%minute%');
		remove_rewrite_tag('%second%');
	}

	if($instance->get_setting('remove_attachment_rewrite')){
		add_filter('attachment_link',	[$instance, 'filter_attachment_link'], 10, 2);
	}

	if($instance->get_setting('remove_comment_rewrite')){
		add_filter('comments_rewrite_rules', '__return_empty_array');
	}

	add_action('generate_rewrite_rules',	[$instance, 'on_generate_rewrite_rules']);

	if(is_admin()){
		wpjam_add_basic_sub_page('wpjam-rewrites', [
			'menu_title'	=> 'Rewrites',
			'function'		=> 'tab',
			'load_callback'	=> [$instance, 'load_plugin_page'],
			'summary'		=> 'Rewrites 扩展让可以优化现有 Rewrites 规则和添加额外的 Rewrite 规则，详细介绍请点击：<a href="https://blog.wpjam.com/m/wpjam-rewrite/" target="_blank">Rewrites 扩展</a>。'
		]);
	}
});