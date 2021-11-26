<?php
/*
Name: 摘要快速编辑
URI: https://blog.wpjam.com/m/quick-excerpt/
Description: 后台文章列表的快速编辑支持编辑摘要。
Version: 1.0
*/
if(is_admin()){
	class WPJAM_Quick_Excerpt{
		public static function filter_wp_insert_post_data($data, $postarr){
			if(isset($_POST['the_excerpt'])){
				$data['post_excerpt']   = $_POST['the_excerpt'];
			}
				
			return $data;
		}

		public static function filter_add_inline_data($post){
			echo '<div class="post_excerpt">' . esc_textarea(trim($post->post_excerpt)) . '</div>';
		}

		public static function filter_html($html){
			$excerpt_inline_edit	= '
			<label>
				<span class="title">摘要</span>
				<span class="input-text-wrap"><textarea cols="22" rows="2" name="the_excerpt"></textarea></span>
			</label>
			';

			$html	= str_replace('<fieldset class="inline-edit-date">', $excerpt_inline_edit.'<fieldset class="inline-edit-date">', $html);

			return $html;
		}
	}

	add_action('wpjam_builtin_page_load', function($screen_base, $current_screen){
		if($screen_base == 'edit' && post_type_supports($current_screen->post_type, 'excerpt')){
			add_filter('wp_insert_post_data',	['WPJAM_Quick_Excerpt', 'filter_wp_insert_post_data'], 10, 2);
			add_filter('add_inline_data',		['WPJAM_Quick_Excerpt', 'filter_add_inline_data'], 10, 2);

			if(!wp_doing_ajax()){
				add_filter('wpjam_html',		['WPJAM_Quick_Excerpt', 'filter_html']);
			}
		}
	}, 10, 2);
}