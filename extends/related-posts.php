<?php
/*
Name: 相关文章
URI: https://blog.wpjam.com/m/wpjam-related-posts/
Description: 根据文章的标签和分类自动生成相关文章，并显示在文章末尾。
Version: 1.0
*/
class WPJAM_Related_Posts{
	public static function shortcode($atts, $content=''){
		$atts	= shortcode_atts(['tag'=>''], $atts);
		$tags	= $atts['tag'] ? explode(",", $atts['tag']) : '';

		return $tags ? wpjam_render_query(wpjam_query([
			'post_type'		=> 'any', 
			'no_found_rows'	=> true,
			'post_status'	=> 'publish', 
			'post__not_in'	=> [get_the_ID()],
			'tax_query'		=> [[
				'taxonomy'	=> 'post_tag',
				'terms'		=> $tags,
				'operator'	=> 'AND',
				'field'		=> 'name'
			]]
		]), ['thumb'=>false, 'class'=>'related-posts']) : '';
	}

	public static function get_args($ratio = 1){
		if(!is_singular() || get_the_ID() != get_queried_object_id()){
			return false;
		}

		$args	= get_option('wpjam-related-posts') ?: [];
		$auto	= wpjam_array_pull($args, 'auto');

		if(doing_filter('the_content')){
			if(wpjam_is_json_request() || doing_filter('get_the_excerpt') || !$auto){
				return false;
			}
		}

		$post_types	= wpjam_array_pull($args, 'post_types');
		$post_types	= $post_types ?: array_values(get_post_types(['show_ui'=>true, 'hierarchical'=>false, 'public'=>true], 'names'));

		if(!in_array(get_post_type(), $post_types)){
			return false;
		}
				
		if(!empty($args['thumb'])){
			$args['size']	= wp_array_slice_assoc($args, ['width', 'height']);
			$args['size']	= wpjam_parse_size($args['size'], $ratio);
		}

		return $args;
	}

	public static function filter_the_content($content){
		if($args = self::get_args()){
			$content	.= wpjam_get_related_posts(get_the_ID(), $args);
		}

		return $content;
	}

	public static function filter_post_json($post_json){
		if($args = self::get_args(2)){
			$post_json['related']	= wpjam_get_related_posts(get_the_ID(), $args, $parse_for_json=true);
		}

		return $post_json;
	}

	public static function load_option_page(){
		$options	= array_column(get_post_types(['show_ui'=>true, 'hierarchical'=>false, 'public'=>true], 'objects'), 'label', 'name');
		$options	= wpjam_array_except($options, 'attachment');
		$show_if	= ['key'=>'thumb', 'value'=>1];
		$fields		= [
			'post_types'=> ['title'=>'文章类型',	'type'=>'checkbox',	'options'=>$options,'description'=>'哪些文章类型显示相关文章。'],
			'title'		=> ['title'=>'列表标题',	'type'=>'text',		'value'=>'相关文章',	'class'=>'all-options',	'description'=>'相关文章列表标题。'],
			'number'	=> ['title'=>'列表数量',	'type'=>'number',	'value'=>5,			'class'=>'all-options',	'description'=>'默认为5。'],
			'thumb_set'	=> ['title'=>'列表内容',	'type'=>'fieldset',	'fields'=>[
				'_excerpt'	=> ['type'=>'checkbox',	'name'=>'excerpt',	'description'=>'显示文章摘要。'],
				'thumb'		=> ['type'=>'checkbox',	'description'=>'显示缩略图。','group'=>'size',	'value'=>1],
				'size'		=> ['type'=>'view',		'show_if'=>$show_if,	'group'=>'size',	'value'=>'尺寸：'],
				'width'		=> ['type'=>'number',	'show_if'=>$show_if,	'group'=>'size',	'value'=>100,	'class'=>'small-text'],
				'x'			=> ['type'=>'view',		'show_if'=>$show_if,	'group'=>'size',	'value'=>'<span class="dashicons dashicons-no-alt"></span>'],
				'height'	=> ['type'=>'number',	'show_if'=>$show_if,	'group'=>'size',	'value'=>100,	'class'=>'small-text'],
				'_view'		=> ['type'=>'view',		'show_if'=>$show_if,	'value'=>'如勾选之后缩略图不显示，请到「<a href="'.admin_url('admin.php?page=wpjam-thumbnail').'">缩略图设置</a>」勾选「无需修改主题，程序自动使用 WPJAM 的缩略图设置」。']
			]],
			'style'		=> ['title'=>'列表样式',	'type'=>'fieldset',	'fields'=>[
				'div_id'	=> ['type'=>'text',	'value'=>'related_posts',	'class'=>'all-options',	'description'=>'外层 div id，不填则外层不添加 div。'],
				'class'		=> ['type'=>'text',	'value'=>'',				'class'=>'all-options',	'description'=>'相关文章列表 ul 的 class。'],
			]],
			'auto'		=> ['title'=>'自动附加',	'type'=>'checkbox',	'value'=>1,	'description'=>'自动附加到文章末尾。'],
		];

		$summary	= '相关文章扩展会在文章详情页生成一个相关文章的列表，详细介绍请点击：<a href="https://blog.wpjam.com/m/wpjam-related-posts/">相关文章扩展</a>。';

		wpjam_register_option('wpjam-related-posts', compact('fields', 'summary'));
	}

	public static function init(){
		add_shortcode('related', [self::class, 'shortcode']);

		if(is_admin()){
			if(!is_multisite() || !is_network_admin()){
				wpjam_register_plugin_page_tab('related-posts', [
					'title'			=> '相关文章',
					'function'		=> 'option',
					'option_name'	=> 'wpjam-related-posts',
					'plugin_page'	=> 'wpjam-posts',
					'order'			=> 19,
					'load_callback'	=> [self::class, 'load_option_page']
				]);
			}
		}else{
			add_filter('the_content',		[self::class, 'filter_the_content'], 11);
			add_filter('wpjam_post_json',	[self::class, 'filter_post_json'], 10, 2);
		}
	}
}

add_action('init', ['WPJAM_Related_Posts', 'init']);