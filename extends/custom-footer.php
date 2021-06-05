<?php
/*
Name: 文章页代码
URI: https://blog.wpjam.com/m/custom-post/
Description: 在文章编辑页面可以单独设置每篇文章 head 和 Footer 代码。
Version: 1.0
*/
add_action('wp_footer', function (){
	if(is_singular()){
		echo get_post_meta(get_the_ID(), 'custom_footer', true);
	}
});

add_action('wp_head', function (){
	if(is_singular()){
		echo get_post_meta(get_the_ID(), 'custom_head', true);
	}
});

if(is_admin()){
	add_action('wpjam_builtin_page_load', function($screen_base, $current_screen){
		if($screen_base == 'post' && $current_screen->post_type != 'attachment' && is_post_type_viewable($current_screen->post_type)){
			wpjam_register_post_option('wpjam_custom_head_box', [
				'title'		=> '文章头部代码',
				'summary'	=> '自定义文章代码可以让你在当前文章插入独有的 JS，CSS，iFrame 等类型的代码，让你可以对具体一篇文章设置不同样式和功能，展示不同的内容。',
				'fields'	=> ['custom_head'=>['title'=>'',	'type'=>'textarea']]
			]);

			wpjam_register_post_option('wpjam_custom_footer_box',	[
				'title'		=> '文章底部代码',
				'fields'	=> ['custom_footer'=>['title'=>'',	'type'=>'textarea']]
			]);
		}
	}, 10, 2);
}