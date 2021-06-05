<?php
/*
Name: 相关文章
URI: https://blog.wpjam.com/m/wpjam-related-posts/
Description: 根据文章的标签和分类自动生成相关文章，并显示在文章末尾。
Version: 1.0
*/
class WPJAM_Related_Posts{
	use WPJAM_Setting_Trait;

	private function __construct(){
		$this->init('wpjam-related-posts');
	}

	public function get_args($ratio = 1){
		$args	= $this->get_settings() ?: [];

		if(!empty($args['thumb'])){
			$args['size']	= wp_array_slice_assoc($args, ['width', 'height']);
			$args['size']	= wpjam_parse_size($args['size'], $ratio);
		}

		if(!empty($args['post_types'])){
			$args['post_type']	= $args['post_types'];
		}

		return $args;
	}

	public function shortcode($atts, $content=''){
		$atts	= shortcode_atts(['tag'=>''], $atts);
		$tags	= $atts['tag'] ? explode(",", $atts['tag']) : '';

		if(empty($tags)){
			return '';
		}

		$related_query	= wpjam_query([
			'post_type'		=> 'any', 
			'no_found_rows'	=> true,
			'post_status'	=> 'publish', 
			'post__not_in'	=> [get_the_ID()],
			'tax_query'		=> [
				[
					'taxonomy'	=> 'post_tag',
					'terms'		=> $tags,
					'operator'	=> 'AND',
					'field'		=> 'name'
				]
			]
		]);

		return  wpjam_render_query($related_query, ['thumb'=>false, 'class'=>'related-posts']);
	}

	public function has($for_json=true){
		if(is_singular() && get_the_ID() == get_queried_object_id()){
			if(!$for_json && (wpjam_is_json_request() || doing_filter('get_the_excerpt') || !$this->get_setting('auto'))){
				return false;
			}

			$post_types	= $this->get_setting('post_types');

			if(empty($post_types) || (in_array(get_post_type(), $post_types))){
				return true;
			}
		}

		return false;
	}

	public function filter_the_content($content){
		if($this->has(false)){
			$content	.= wpjam_get_related_posts(get_the_ID(), $this->get_args());
		}

		return $content;
	}

	public function filter_post_json($post_json){
		if($this->has()){
			$post_json['related']	= wpjam_get_related_posts(get_the_ID(), $this->get_args(2), $parse_for_json=true);
		}

		return $post_json;
	}

	public function load_option_page(){
		$post_type_options	= wp_list_pluck(get_post_types(['show_ui'=>true,'public'=>true], 'objects'), 'label', 'name');

		unset($post_type_options['attachment']);

		$fields	= [
			'title'			=> ['title'=>'标题',		'type'=>'text',		'value'=>'相关文章',	'class'=>'all-options',	'description'=>'相关文章列表标题。'],
			'number'		=> ['title'=>'数量',		'type'=>'number',	'value'=>5,			'class'=>'all-options',	'description'=>'默认为5。'],
			'post_types'	=> ['title'=>'文章类型',	'type'=>'checkbox',	'options'=>$post_type_options,	'description'=>'相关文章列表包含哪些文章类型的文章，默认则为当前文章的类型。'],
			'_excerpt'		=> ['title'=>'摘要',		'type'=>'checkbox',	'name'=>'excerpt',	'description'=>'显示文章摘要。'],
			'thumb_set'		=> ['title'=>'缩略图',	'type'=>'fieldset',	'fields'=>[
				'thumb'		=> ['title'=>'',	'type'=>'checkbox',	'value'=>1,		'description'=>'显示缩略图。<br />如勾选之后无缩略图显示，请到「<a href="'.admin_url('admin.php?page=wpjam-thumbnail').'">缩略图设置</a>」勾选「无需修改主题，程序自动使用 WPJAM 的缩略图设置」。'],
				'width'		=> ['title'=>'宽度',	'type'=>'number',	'value'=>100,	'class'=>'small-text',	'show_if'=>['key'=>'thumb', 'value'=>1],	'description'=>'px'],
				'height'	=> ['title'=>'高度',	'type'=>'number',	'value'=>100,	'class'=>'small-text',	'show_if'=>['key'=>'thumb', 'value'=>1],	'description'=>'px']
			]],
			'style'			=> ['title'=>'样式',		'type'=>'fieldset',	'fields'=>[
				'div_id'	=> ['title'=>'',	'type'=>'text',	'value'=>'related_posts',	'class'=>'all-options',	'description'=>'外层 div id，不填则外层不添加 div。'],
				'class'		=> ['title'=>'',	'type'=>'text',	'value'=>'',				'class'=>'all-options',	'description'=>'相关文章列表 ul 的 class。'],
			]],
			'auto'			=> ['title'=>'自动',		'type'=>'checkbox',	'value'=>1,	'description'=>'自动附加到文章末尾。'],
		];

		$summary	= '相关文章扩展会在文章详情页生成一个相关文章的列表，详细介绍请点击：<a href="https://blog.wpjam.com/m/wpjam-related-posts/">相关文章扩展</a>。';

		wpjam_register_option('wpjam-related-posts', compact('fields', 'summary'));
	}
}

add_action('wp_loaded', function(){
	$instance	= WPJAM_Related_Posts::get_instance();

	add_shortcode('related', [$instance, 'shortcode']);

	if(is_admin()){
		if(!is_multisite() || !is_network_admin()){
			wpjam_register_plugin_page_tab('related-posts', [
				'title'			=> '相关文章',	
				'function'		=> 'option',	
				'option_name'	=> 'wpjam-related-posts',
				'plugin_page'	=> 'wpjam-posts',
				'load_callback'	=> [$instance, 'load_option_page']
			]);
		}
	}else{
		add_filter('the_content',		[$instance, 'filter_the_content'], 11);
		add_filter('wpjam_post_json',	[$instance, 'filter_post_json'], 10, 2);
	}
});