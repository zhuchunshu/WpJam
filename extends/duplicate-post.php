<?php
/*
Name: 文章快速复制
URI: https://blog.wpjam.com/m/duplicate-post/
Description: 在后台文章列表添加一个快速复制按钮，复制一篇草稿用于快速新建。
Version: 1.0
*/
if(is_admin()){
	add_action('wpjam_builtin_page_load', function($screen_base){
		if($screen_base == 'edit'){
			wpjam_register_list_table_action('quick_duplicate', [
				'title'		=> '快速复制',
				'response'	=> 'add',
				'direct'	=> true,
				'callback'	=> ['WPJAM_Post', 'duplicate']
			]);
		}
	});
}