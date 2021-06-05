<?php
class WPJAM_Hook{
	public static function init(){
		if(	//阻止非法访问
			// strlen($_SERVER['REQUEST_URI']) > 255 ||
			strpos($_SERVER['REQUEST_URI'], "eval(") ||
			strpos($_SERVER['REQUEST_URI'], "base64") ||
			strpos($_SERVER['REQUEST_URI'], "/**/")
		){
			@header("HTTP/1.1 414 Request-URI Too Long");
			@header("Status: 414 Request-URI Too Long");
			@header("Connection: Close");
			exit;
		}

		if(wpjam_basic_get_setting('disable_trackbacks')){
			$GLOBALS['wp']->remove_query_var('tb');
		}

		if(wpjam_basic_get_setting('disable_post_embed')){ 
			$GLOBALS['wp']->remove_query_var('embed');
		}

		// 去掉URL中category
		if(wpjam_basic_get_setting('no_category_base') && !$GLOBALS['wp_rewrite']->use_verbose_page_rules){
			add_filter('request',		['WPJAM_Hook', 'filter_request']);
			add_filter('pre_term_link',	['WPJAM_Hook', 'filter_pre_term_link'], 1, 2);
		}

		// if(wpjam_basic_get_setting('disable_feed')){
		// 	$wp->remove_query_var('feed');
		// 	$wp->remove_query_var('withcomments');
		// 	$wp->remove_query_var('withoutcomments');
		// }
		
		wp_embed_unregister_handler('tudou');
		wp_embed_unregister_handler('youku');
		wp_embed_unregister_handler('56com');
	}

	public static function on_loaded(){
		ob_start(['WPJAM_Hook', 'html_replace']);
	}

	public static function html_replace($html){
		// Google字体加速
		if(wpjam_basic_get_setting('google_fonts')){
			$google_font_searchs	= [
				'googleapis_fonts'			=> '//fonts.googleapis.com', 
				'googleapis_ajax'			=> '//ajax.googleapis.com',
				'googleusercontent_themes'	=> '//themes.googleusercontent.com',
				'gstatic_fonts'				=> '//fonts.gstatic.com',
			];

			$search	= $replace = [];

			if(wpjam_basic_get_setting('google_fonts') == 'custom'){
				foreach ($google_font_searchs as $google_font_key => $google_font_search) {
					if(wpjam_basic_get_setting($google_font_key)){
						$search[]	= $google_font_search;
						$replace[]	= str_replace(['http://','https://'], '//', $google_font_search);
					}
				}
			}elseif(wpjam_basic_get_setting('google_fonts') == 'ustc'){
				$search		= array_values($google_font_searchs);
				$replace	= [
					'//fonts.lug.ustc.edu.cn',
					'//ajax.lug.ustc.edu.cn',
					'//google-themes.lug.ustc.edu.cn',
					'//fonts-gstatic.lug.ustc.edu.cn',
				];
			}

			$html	= $search ? str_replace($search, $replace, $html) : $html;
		}

		return apply_filters('wpjam_html', $html);
	}

	public static function feed_disabled() {
		wp_die('Feed已经关闭, 请访问<a href="'.get_bloginfo('url').'">网站首页</a>！');
	}

	public static function on_admin_page_access_denied(){
		if((is_multisite() && is_user_member_of_blog(get_current_user_id(), get_current_blog_id())) || !is_multisite()){
			wp_die(__( 'Sorry, you are not allowed to access this page.' ).'<a href="'.admin_url().'">返回首页</a>', 403);
		}
	}

	public static function timestamp_file_name($file){
		return array_merge($file, ['name'=> time().'-'.$file['name']]);
	}

	public static function filter_admin_title($admin_title){
		return str_replace(' &#8212; WordPress', '', $admin_title);
	}

	public static function filter_update_attachment_metadata($data){
		if(isset($data['thumb'])){
			$data['thumb'] = basename($data['thumb']);
		}

		return $data;
	}

	public static function filter_register_post_type_args($args, $post_type){
		if(!empty($args['supports']) && is_array($args['supports'])){
			if(wpjam_basic_get_setting('disable_trackbacks')){	// 屏蔽 Trackback
				$args['supports']	= array_diff($args['supports'], ['trackbacks']);

				remove_post_type_support($post_type, 'trackbacks');	// create_initial_post_types 会执行两次
			}

			if(wpjam_basic_get_setting('disable_revision')){	//禁用日志修订功能
				$args['supports']	= array_diff($args['supports'], ['revisions']);

				remove_post_type_support($post_type, 'revisions');
			}
		}

		return $args;
	}

	public static function filter_pre_term_link($term_link, $term){
		$no_base_taxonomy	= wpjam_basic_get_setting('no_category_base_for') ?: 'category';
			
		if($term->taxonomy == $no_base_taxonomy){
			return "%$no_base_taxonomy%";
		}

		return $term_link;
	}

	public static function filter_request($query_vars) {
		if(!isset($query_vars['module']) && !isset($_GET['page_id']) && !isset($_GET['pagename']) && !empty($query_vars['pagename'])){
			$pagename	= strtolower($query_vars['pagename']);
			$pagename	= wp_basename($pagename);
			
			$taxonomy	= wpjam_basic_get_setting('no_category_base_for') ?: 'category';
			$terms		= get_categories(['taxonomy'=>$taxonomy,'hide_empty'=>false]);
			$terms		= wp_list_pluck($terms, 'slug');

			if(in_array($pagename, $terms)){
				unset($query_vars['pagename']);
				if($taxonomy == 'category'){
					$query_vars['category_name']	= $pagename;
				}else{
					$query_vars['taxonomy']	= $taxonomy;
					$query_vars['term']		= $pagename;
				}
			}
		}

		return $query_vars;
	}

	public static function on_pre_get_posts($wp_query){
		if(!empty($wp_query->query_vars['s'])){
			$wp_query->query_vars['s']	= wpjam_strip_invalid_text($wp_query->query_vars['s']);	// 去掉搜索中非法字符串
		}
	}

	public static function filter_post_password_required($required, $post){
		if(!$required){
			return $required;
		}

		$hash	= wpjam_get_parameter('post_password', ['method'=>'REQUEST']);

		if(empty($hash) || 0 !== strpos($hash, '$P$B')){
			return true;
		}

		require_once ABSPATH . WPINC . '/class-phpass.php';

		$hasher	= new PasswordHash(8, true);

		return !$hasher->CheckPassword($post->post_password, $hash);
	}

	public static function on_template_redirect(){
		if(wpjam_basic_get_setting('no_category_base')){
			$taxonomy	= wpjam_basic_get_setting('no_category_base_for') ?: 'category';

			if(strpos($_SERVER['REQUEST_URI'], '/'.$taxonomy.'/') === false){
				return;
			}

			if((is_category() && $taxonomy == 'category') || is_tax($taxonomy)){
				wp_redirect(site_url(str_replace('/'.$taxonomy, '', $_SERVER['REQUEST_URI'])), 301);
				exit;
			}
		}

		//搜索关键词为空时直接重定向到首页
		//当搜索结果只有一篇时直接重定向到文章
		if(wpjam_basic_get_setting('search_optimization')){
			if(is_search() && get_query_var('module') == '') {
				global $wp_query;

				if(empty($wp_query->query['s'])){
					wp_redirect(home_url());
				}else{
					$paged	= get_query_var('paged');
					if ($wp_query->post_count == 1 && empty($paged)) {
						wp_redirect(get_permalink($wp_query->posts['0']->ID));
					}
				}
			}
		}			
	}

	public static function filter_old_slug_redirect_post_id($post_id){
		if($post_id){
			return $post_id;
		}

		global $wpdb;

		$post_ids	= $wpdb->get_col($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wp_old_slug' AND meta_value = %s", get_query_var('name')));

		if(empty($post_ids)){
			return null;
		}

		$posts	= array_filter(WPJAM_Post::get_by_ids($post_ids), function($post){
			return $post->post_status == 'publish';
		});

		if(empty($posts)){
			return null;
		}

		$post_id	= current($posts)->ID;
		$post_type	= get_query_var('post_type');

		if(count($posts) > 1 && $post_type && !is_null($post_type) && $post_type != 'any'){ // 指定 post_type 则获取首先获取 post_type 相同的
			$filtered_posts	= array_filter($posts, function($post) use($post_type){
				return $post->post_type == $post_type;
			});

			if($filtered_posts){
				$post_id	= current($filtered_posts)->ID;
			}
		}

		return $post_id;
	}

	private static $locale = null;

	public static function filter_locale($locale){
		if(is_null(self::$locale)){
			self::$locale	= $locale;	
		}

		if(in_array('get_language_attributes', wp_list_pluck(debug_backtrace(), 'function'))){
			return self::$locale;
		}else{
			return 'en_US';
		}
	}

	public static function filter_pre_get_avatar_data($args, $id_or_email){
		$email_hash	= '';
		$user		= $email = false;
		
		if(is_object($id_or_email) && isset($id_or_email->comment_ID)){
			$id_or_email	= get_comment($id_or_email);
		}

		if(is_numeric($id_or_email)){
			$user	= get_user_by('id', absint($id_or_email));
		}elseif($id_or_email instanceof WP_User){	// User Object
			$user	= $id_or_email;
		}elseif($id_or_email instanceof WP_Post){	// Post Object
			$user	= get_user_by('id', (int)$id_or_email->post_author);
		}elseif($id_or_email instanceof WP_Comment){	// Comment Object
			$avatar = get_comment_meta($id_or_email->comment_ID, 'avatarurl', true);

			if($avatar){
				$args['url']	= wpjam_get_thumbnail($avatar, [$args['width'],$args['height']]);
				$args['found_avatar']	= true;

				return $args;
			}

			if(!empty($id_or_email->user_id)){
				$user	= get_user_by('id', (int)$id_or_email->user_id);
			}elseif(!empty($id_or_email->comment_author_email)){
				$email	= $id_or_email->comment_author_email;
			}
		}elseif(is_string($id_or_email)){
			if(strpos($id_or_email, '@md5.gravatar.com')){
				list($email_hash)	= explode('@', $id_or_email);
			} else {
				$email	= $id_or_email;
			}
		}

		if($user){
			$avatar = get_user_meta($user->ID, 'avatarurl', true);

			if($avatar){
				$args['url']	= wpjam_get_thumbnail(set_url_scheme($avatar), [$args['width'],$args['height']]);
				$args['found_avatar']	= true;

				return $args;
			}else{
				$args	= apply_filters('wpjam_default_avatar_data', $args, $user->ID);

				if($args['found_avatar']){
					return $args;
				}else{
					$email = $user->user_email;
				}
			}
		}

		if(!$email_hash && $email){
			$email_hash = md5(strtolower(trim($email)));
		}

		if($email_hash){
			$args['found_avatar']	= true;

			$gravatar_url	= 'http://cn.gravatar.com/avatar/';

			// Gravatar加速
			if($gravatar_setting = wpjam_basic_get_setting('gravatar')){
				if($gravatar_setting == 'custom'){
					if($gravatar_custom	= wpjam_basic_get_setting('gravatar_custom')){
						$gravatar_url	= $gravatar_custom;
					}
				}elseif($gravatar_setting == 'v2ex'){
					$gravatar_url	= 'http://cdn.v2ex.com/gravatar/';
				}
			}

			$url	= $gravatar_url.$email_hash;
			$url_args	= array_filter([
				's'	=> $args['size'],
				'd'	=> $args['default'],
				'f'	=> $args['force_default'] ? 'y' : false,
				'r'	=> $args['rating'],
			]);

			$url	= add_query_arg(rawurlencode_deep($url_args), set_url_scheme($url, $args['scheme']));

			$args['url']	= apply_filters('get_avatar_url', $url, $id_or_email, $args);
		}

		return $args;
	}

	public static function filter_get_the_excerpt($text='', $post=null){
		if(empty($text)){
			remove_filter('the_excerpt', 'wp_filter_content_tags');

			$length	= wpjam_basic_get_setting('excerpt_length') ?: 200;	
			$text	= wpjam_get_post_excerpt($post, $length);
		}

		return $text;
	}
}

add_action('init',				['WPJAM_Hook', 'init']);
add_action('wp_loaded',			['WPJAM_HOOK', 'on_loaded']);
add_action('pre_get_posts',		['WPJAM_HOOK', 'on_pre_get_posts'], 1);
add_action('template_redirect',	['WPJAM_Hook', 'on_template_redirect']);

add_filter('register_post_type_args', 		['WPJAM_HOOK', 'filter_register_post_type_args'], 10, 2);
add_filter('post_password_required', 		['WPJAM_HOOK', 'filter_post_password_required'], 10, 2);
add_filter('pre_get_avatar_data', 			['WPJAM_HOOK', 'filter_pre_get_avatar_data'], 10, 2);
add_filter('wp_update_attachment_metadata', ['WPJAM_Hook', 'filter_update_attachment_metadata']);	// 修正任意文件删除漏洞

// 优化文章摘要
if($excerpt_optimization = wpjam_basic_get_setting('excerpt_optimization')){ 
	remove_filter('get_the_excerpt', 'wp_trim_excerpt');

	if($excerpt_optimization != 2){
		add_filter('get_the_excerpt', ['WPJAM_Hook', 'filter_get_the_excerpt'], 10, 2);
	}
}

//前台不加载语言包
if(wpjam_basic_get_setting('locale') && !is_admin()){
	add_filter('locale',	['WPJAM_Hook', 'filter_locale']);
}

// 解决日志改变 post type 之后跳转错误的问题，
// WP 原始解决函数 'wp_old_slug_redirect' 和 'redirect_canonical'
if(wpjam_basic_get_setting('404_optimization')){ 
	add_filter('old_slug_redirect_post_id',	['WPJAM_Hook', 'filter_old_slug_redirect_post_id']);
}

//移除 WP_Head 无关紧要的代码
if(wpjam_basic_get_setting('remove_head_links')){
	remove_action( 'wp_head', 'wp_generator');					//删除 head 中的 WP 版本号
	foreach (['rss2_head', 'commentsrss2_head', 'rss_head', 'rdf_header', 'atom_head', 'comments_atom_head', 'opml_head', 'app_head'] as $action) {
		remove_action( $action, 'the_generator' );
	}

	remove_action( 'wp_head', 'rsd_link' );						//删除 head 中的 RSD LINK
	remove_action( 'wp_head', 'wlwmanifest_link' );				//删除 head 中的 Windows Live Writer 的适配器？ 

	remove_action( 'wp_head', 'feed_links_extra', 3 );		  	//删除 head 中的 Feed 相关的link
	//remove_action( 'wp_head', 'feed_links', 2 );	

	remove_action( 'wp_head', 'index_rel_link' );				//删除 head 中首页，上级，开始，相连的日志链接
	remove_action( 'wp_head', 'parent_post_rel_link', 10); 
	remove_action( 'wp_head', 'start_post_rel_link', 10); 
	remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10);

	remove_action( 'wp_head', 'wp_shortlink_wp_head', 10, 0 );	//删除 head 中的 shortlink
	remove_action( 'wp_head', 'rest_output_link_wp_head', 10);	//删除头部输出 WP RSET API 地址

	remove_action( 'template_redirect',	'wp_shortlink_header', 11);		//禁止短链接 Header 标签。	
	remove_action( 'template_redirect',	'rest_output_link_header', 11);	//禁止输出 Header Link 标签。
}

//让用户自己决定是否书写正确的 WordPress
if(wpjam_basic_get_setting('remove_capital_P_dangit')){
	remove_filter( 'the_content', 'capital_P_dangit', 11 );
	remove_filter( 'the_title', 'capital_P_dangit', 11 );
	remove_filter( 'wp_title', 'capital_P_dangit', 11 );
	remove_filter( 'comment_text', 'capital_P_dangit', 31 );
}

// 屏蔽字符转码
if(wpjam_basic_get_setting('disable_texturize')){
	add_filter('run_wptexturize', '__return_false');
}

//移除 admin bar
if(wpjam_basic_get_setting('remove_admin_bar')){
	add_filter('show_admin_bar', '__return_false');
}

//禁用 XML-RPC 接口
if(wpjam_basic_get_setting('disable_xml_rpc')){
	if(wpjam_basic_get_setting('disable_block_editor')){
		add_filter( 'xmlrpc_enabled', '__return_false' );
		remove_action( 'xmlrpc_rsd_apis', 'rest_output_rsd' );
	}
}

// 屏蔽古腾堡编辑器
if(wpjam_basic_get_setting('disable_block_editor')){
	remove_action('wp_enqueue_scripts', 'wp_common_block_scripts_and_styles');
	remove_action('admin_enqueue_scripts', 'wp_common_block_scripts_and_styles');
	remove_filter('the_content', 'do_blocks', 9);
}

// 屏蔽站点管理员邮箱验证功能
if(wpjam_basic_get_setting('disable_admin_email_check')){
	add_filter('admin_email_check_interval', '__return_false');
}

// 屏蔽 Emoji
if(wpjam_basic_get_setting('disable_emoji')){  
	remove_action('admin_print_scripts','print_emoji_detection_script');
	remove_action('admin_print_styles',	'print_emoji_styles');

	remove_action('wp_head',			'print_emoji_detection_script',	7);
	remove_action('wp_print_styles',	'print_emoji_styles');

	remove_action('embed_head',			'print_emoji_detection_script');

	remove_filter('the_content_feed',	'wp_staticize_emoji');
	remove_filter('comment_text_rss',	'wp_staticize_emoji');
	remove_filter('wp_mail',			'wp_staticize_emoji_for_email');

	add_filter('emoji_svg_url',		'__return_false');
	add_filter('tiny_mce_plugins',	function($plugins){ 
		return array_diff($plugins, ['wpemoji']); 
	});
}

//禁用文章修订功能
if(wpjam_basic_get_setting('disable_revision')){
	if(!defined('WP_POST_REVISIONS')){
		define('WP_POST_REVISIONS', false);
	}
	
	remove_action('pre_post_update', 'wp_save_post_revision');
}

// 屏蔽Trackbacks
if(wpjam_basic_get_setting('disable_trackbacks')){
	if(wpjam_basic_get_setting('disable_xml_rpc')){
		//彻底关闭 pingback
		add_filter('xmlrpc_methods',function($methods){
			return array_merge($methods, [
				'pingback.ping'						=> '__return_false',
				'pingback.extensions.getPingbacks'	=> '__return_false'
			]);
		});
	}

	//禁用 pingbacks, enclosures, trackbacks 
	remove_action( 'do_pings', 'do_all_pings', 10 );

	//去掉 _encloseme 和 do_ping 操作。
	remove_action( 'publish_post','_publish_post_hook',5 );
}

//禁用 Auto OEmbed
if(wpjam_basic_get_setting('disable_autoembed')){ 
	remove_filter('the_content',			[$GLOBALS['wp_embed'], 'run_shortcode'], 8);
	remove_filter('widget_text_content',	[$GLOBALS['wp_embed'], 'run_shortcode'], 8);

	remove_filter('the_content',			[$GLOBALS['wp_embed'], 'autoembed'], 8);
	remove_filter('widget_text_content',	[$GLOBALS['wp_embed'], 'autoembed'], 8);

	remove_action('edit_form_advanced',		[$GLOBALS['wp_embed'], 'maybe_run_ajax_cache']);
	remove_action('edit_page_form',			[$GLOBALS['wp_embed'], 'maybe_run_ajax_cache']);
}

// 屏蔽文章Embed
if(wpjam_basic_get_setting('disable_post_embed')){  
	
	remove_action( 'rest_api_init', 'wp_oembed_register_route' );
	remove_filter( 'rest_pre_serve_request', '_oembed_rest_pre_serve_request', 10, 4 );

	add_filter( 'embed_oembed_discover', '__return_false' );

	remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );
	remove_filter( 'oembed_response_data',   'get_oembed_response_data_rich',  10, 4 );

	remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
	remove_action( 'wp_head', 'wp_oembed_add_host_js' );

	add_filter('tiny_mce_plugins', function ($plugins){
		return array_diff( $plugins, ['wpembed'] );
	});
}

// 屏蔽站点Feed
if(wpjam_basic_get_setting('disable_feed')){
	add_action('do_feed',		['WPJAM_Hook', 'feed_disabled'], 1);
	add_action('do_feed_rdf',	['WPJAM_Hook', 'feed_disabled'], 1);
	add_action('do_feed_rss',	['WPJAM_Hook', 'feed_disabled'], 1);
	add_action('do_feed_rss2',	['WPJAM_Hook', 'feed_disabled'], 1);
	add_action('do_feed_atom',	['WPJAM_Hook', 'feed_disabled'], 1);
}

// 屏蔽自动更新
if(wpjam_basic_get_setting('disable_auto_update')){  
	add_filter('automatic_updater_disabled', '__return_true');
	remove_action('init', 'wp_schedule_update_checks');
}

// 禁止使用 admin 用户名尝试登录
if(wpjam_basic_get_setting('no_admin')){
	add_filter( 'wp_authenticate',  function ($user){
		if($user == 'admin') exit;
	});

	add_filter('sanitize_user', function ($username, $raw_username, $strict){
		if($raw_username == 'admin' || $username == 'admin'){
			exit;
		}
		return $username;
	}, 10, 3);
}

if(wpjam_basic_get_setting('x-frame-options')){
	add_action('send_headers', function ($wp){
		header('X-Frame-Options: '.wpjam_basic_get_setting('x-frame-options'));
	});
}

// 防止重名造成大量的 SQL 请求
if(wpjam_basic_get_setting('timestamp_file_name')){
	add_filter('wp_handle_sideload_prefilter',	['WPJAM_Hook', 'timestamp_file_name']);
	add_filter('wp_handle_upload_prefilter',	['WPJAM_Hook', 'timestamp_file_name']);
}

// 屏蔽后台隐私
if(wpjam_basic_get_setting('disable_privacy')){
	remove_action( 'user_request_action_confirmed', '_wp_privacy_account_request_confirmed' );
	remove_action( 'user_request_action_confirmed', '_wp_privacy_send_request_confirmation_notification', 12 ); // After request marked as completed.
	remove_action( 'wp_privacy_personal_data_exporters', 'wp_register_comment_personal_data_exporter' );
	remove_action( 'wp_privacy_personal_data_exporters', 'wp_register_media_personal_data_exporter' );
	remove_action( 'wp_privacy_personal_data_exporters', 'wp_register_user_personal_data_exporter', 1 );
	remove_action( 'wp_privacy_personal_data_erasers', 'wp_register_comment_personal_data_eraser' );
	remove_action( 'init', 'wp_schedule_delete_old_privacy_export_files' );
	remove_action( 'wp_privacy_delete_old_export_files', 'wp_privacy_delete_old_export_files' );

	add_filter('option_wp_page_for_privacy_policy', '__return_zero');
}

if(is_admin()){
	add_action('admin_page_access_denied',	['WPJAM_Hook', 'on_admin_page_access_denied']);

	add_filter('admin_title', ['WPJAM_Hook','filter_admin_title']);

	remove_action('admin_init', 'zh_cn_l10n_legacy_option_cleanup');
	remove_action('admin_init', 'zh_cn_l10n_settings_init');

	// add_filter('is_protected_meta', function($protected, $meta_key){
	// 	return $protected ?: in_array($meta_key, ['views', 'favs']);
	// }, 10, 2);

	// add_filter('removable_query_args', function($removable_query_args){
	// 	return array_merge($removable_query_args, ['added', 'duplicated', 'unapproved',	'unpublished', 'published', 'geted', 'created', 'synced']);
	// });

	if(wpjam_basic_get_setting('disable_auto_update')){
		remove_action('admin_init', '_maybe_update_core');
		remove_action('admin_init', '_maybe_update_plugins');
		remove_action('admin_init', '_maybe_update_themes');
	}

	if(wpjam_basic_get_setting('remove_help_tabs')){  
		add_action('in_admin_header', function(){
			$GLOBALS['current_screen']->remove_help_tabs();
		});
	}

	if(wpjam_basic_get_setting('remove_screen_options')){  
		add_filter('screen_options_show_screen', '__return_false');
		add_filter('hidden_columns', '__return_empty_array');
	}

	if(wpjam_basic_get_setting('disable_privacy')){
		add_action('admin_menu', function(){
			remove_submenu_page('options-general.php', 'options-privacy.php');
			remove_submenu_page('tools.php', 'export-personal-data.php');
			remove_submenu_page('tools.php', 'erase-personal-data.php');
		},11);

		add_action('admin_init', function(){
			remove_action('admin_init', ['WP_Privacy_Policy_Content', 'text_change_check'], 100);
			remove_action('edit_form_after_title', ['WP_Privacy_Policy_Content', 'notice']);
			remove_action('admin_init', ['WP_Privacy_Policy_Content', 'add_suggested_content'], 1);
			remove_action('post_updated', ['WP_Privacy_Policy_Content', '_policy_page_updated']);
			remove_filter('list_pages', '_wp_privacy_settings_filter_draft_page_titles', 10, 2);
		},1);
	}
}