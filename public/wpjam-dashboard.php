<?php
add_action('wpjam_builtin_page_load', function($screen_base){
	if(!in_array($screen_base, ['dashboard', 'dashboard-network', 'dashboard-user'])){
		return;
	}

	add_action('wp_dashboard_setup',			'wpjam_dashboard_setup', 1);
	add_action('wp_network_dashboard_setup',	'wpjam_dashboard_setup', 1);
	add_action('wp_user_dashboard_setup',		'wpjam_dashboard_setup', 1);


	// add_action('activity_box_end', function(){
	// 	echo '<span class="dashicons dashicons-megaphone"></span> Sweet主题升级到 1.5。';
	// });

	// add_filter('dashboard_glance_items', function($elements){
	// 	$elements[]	= '<a><span class="dashicons dashicons-megaphone"></span> Sweet主题升级到 1.5。</a>';
	// 	return $elements;
	// });

	add_filter('dashboard_recent_posts_query_args', function($query_args){
		$query_args['post_type']	= 'any';
		$query_args['cache_it']		= true;
		// $query_args['posts_per_page']	= 10;

		return $query_args;
	});

	add_filter('dashboard_recent_drafts_query_args', function($query_args){
		$query_args['post_type']	= 'any';

		return $query_args;
	});
});

// Dashboard Widget
// Dashboard Widget
function wpjam_dashboard_setup(){
	remove_meta_box('dashboard_primary', get_current_screen(), 'side');

	add_action('pre_get_comments', function($query){
		$query->query_vars['type']	= 'comment';
	});
		
	if(is_multisite()){
		if(!is_user_member_of_blog()){
			remove_meta_box('dashboard_quick_press', get_current_screen(), 'side');
		}
	}
	
	$dashboard_widgets	= [];

	$dashboard_widgets['wpjam_update']	= [
		'title'		=> 'WordPress资讯及技巧',
		'context'	=> 'side',	// 位置，normal 左侧, side 右侧
	];

	if($dashboard_widgets	= apply_filters('wpjam_dashboard_widgets', $dashboard_widgets)){
		foreach ($dashboard_widgets as $widget_id => $meta_box){
			$title		= $meta_box['title'];
			$callback	= $meta_box['callback'] ?? wpjam_get_filter_name($widget_id, 'dashboard_widget_callback');
			$context	= $meta_box['context'] ?? 'normal';	// 位置，normal 左侧, side 右侧
			$args		= $meta_box['args'] ?? [];

			add_meta_box($widget_id, $title, $callback, get_current_screen(), $context, 'core', $args);
		}
	}
}

function wpjam_update_dashboard_widget_callback(){
	?>
	<style type="text/css">
		#dashboard_wpjam .inside{margin:0; padding:0;}
		a.jam-post {border-bottom:1px solid #eee; margin: 0 !important; padding:6px 0; display: block; text-decoration: none; }
		a.jam-post:last-child{border-bottom: 0;}
		a.jam-post p{display: table-row; }
		a.jam-post img{display: table-cell; width:40px; height: 40px; margin:4px 12px; }
		a.jam-post span{display: table-cell; height: 40px; vertical-align: middle;}
	</style>
	<div class="rss-widget">
	<?php

	$jam_posts = get_transient('dashboard_jam_posts');

	if($jam_posts === false){

		$response	= wpjam_remote_request('http://jam.wpweixin.com/api/post/list.json', ['timeout'=>1]);

		if(is_wp_error($response)){
			$jam_posts	= [];
		}else{
			$jam_posts	= $response['posts'];
		}

		set_transient('dashboard_jam_posts', $jam_posts, 12 * HOUR_IN_SECONDS );
	}
	if($jam_posts){
		$i = 0;
		foreach ($jam_posts as $jam_post){
			if($i == 5) break;
			echo '<a class="jam-post" target="_blank" href="http://blog.wpjam.com'.$jam_post['post_url'].'"><p>'.'<img src="'.str_replace('imageView2/1/w/200/h/200/', 'imageView2/1/w/100/h/100/', $jam_post['thumbnail']).'" /><span>'.$jam_post['title'].'</span></p></a>';
			$i++;
		}
	}	
	?>
	</div>

	<!-- <p class="community-events-footer">
		<a href="https://blog.wpjam.com/" target="_blank">WordPress果酱 <span aria-hidden="true" class="dashicons dashicons-external"></span></a>
	</p> -->

	<?php
}