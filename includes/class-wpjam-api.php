<?php
class WPJAM_JSON{
	use WPJAM_Register_Trait;

	public function response(){
		do_action('wpjam_json_response', $this);

		$current_user	= wpjam_get_current_user($this->auth);

		if(is_wp_error($current_user)){
			wpjam_send_json($current_user);
		}

		$response	= [
			'errcode'		=> 0,
			'current_user'	=> $current_user
		];

		if($_SERVER['REQUEST_METHOD'] != 'POST'){
			$response['page_title']		= (string)$this->page_title;
			$response['share_title']	= (string)$this->share_title ;
			$response['share_image']	= (string)$this->share_image;
		}

		if($this->modules){
			if(!wp_is_numeric_array($this->modules)){
				$this->modules	= [$this->modules];
			}

			foreach($this->modules as $module){
				if(empty($module['args'])){
					continue;
				}

				$module_args	= is_array($module['args']) ? $module['args'] : wpjam_parse_shortcode_attr(stripslashes_deep($module['args']), 'module');
				$module_type	= $module['type'] ?? '';

				if($module_type == 'post_type'){
					$result	= $this->parse_post_type_module($module_args);
				}elseif($module_type == 'taxonomy'){
					$result	= $this->parse_taxonomy_module($module_args);
				}elseif($module_type == 'setting'){
					$result	= $this->parse_setting_module($module_args);
				}elseif($module_type == 'media'){
					$result	= $this->parse_media_module($module_args);
				}else{
					$result	= $module_args;
				}

				$response	= $this->merge_result($result, $response);
			}
		}elseif($this->callback || $this->template){
			if($this->callback && is_callable($this->callback)){
				$result	= call_user_func($this->callback, $this->args, $this->name);
			}elseif($this->template && is_file($this->template)){
				$result	= include $this->template;
			}else{
				$result	= null;
			}

			$response	= $this->merge_result($result, $response);
		}else{
			$response	= $this->merge_result($this->args, $response);
		}

		$response	= apply_filters('wpjam_json', $response, $this->args, $this->name);

		if($_SERVER['REQUEST_METHOD'] != 'POST'){
			if(empty($response['page_title'])){
				$response['page_title']		= html_entity_decode(wp_get_document_title());
			}

			if(empty($response['share_title'])){
				$response['share_title']	= $response['page_title'];
			}

			if(!empty($response['share_image'])){
				$response['share_image']	= wpjam_get_thumbnail($response['share_image'], '500x400');
			}
		}

		wpjam_send_json($response);
	}

	protected static function merge_result($result, $response){
		if(is_wp_error($result)){
			wpjam_send_json($result);
		}elseif(is_array($result)){
			$except	= [];

			foreach(['page_title', 'share_title', 'share_image'] as $key){
				if(!empty($response[$key]) && isset($result[$key])){
					$except[]	= $key;
				}
			}

			if($except){
				$result	= wpjam_array_except($result, $except);
			}

			$response	= array_merge($response, $result);
		}

		return $response;
	}

	protected static $current_json	= '';

	public static function is_request(){
		if(get_option('permalink_structure')){
			if(preg_match("/\/api\/(.*)\.json/", $_SERVER['REQUEST_URI'])){ 
				return true;
			}
		}else{
			if(isset($_GET['module']) && $_GET['module'] == 'json'){
				return true;
			}
		}

		return false;
	}

	public static function module($action){
		if(!wpjam_doing_debug()){ 
			self::send_origin_headers();

			if(wp_is_jsonp_request()){
				@header('Content-Type: application/javascript; charset='.get_option('blog_charset'));
			}else{
				@header('Content-Type: application/json; charset='.get_option('blog_charset'));
			}
		}

		if(strpos($action, 'mag.') !== 0){
			return;
		}

		self::$current_json	= $json	= str_replace(['mag.','/'], ['','.'], $action);

		do_action('wpjam_api', $json);

		if($json_obj = self::get($json)){
			$json_obj->response();
		}else{
			wpjam_send_json(['errcode'=>'api_not_defined',	'errmsg'=>'接口未定义！']);
		}
	}

	protected static function send_origin_headers(){
		header('X-Content-Type-Options: nosniff');

		if($origin	= get_http_origin()){
			// Requests from file:// and data: URLs send "Origin: null"
			if('null' !== $origin){
				$origin	= esc_url_raw($origin);
			}

			@header('Access-Control-Allow-Origin: ' . $origin);
			@header('Access-Control-Allow-Methods: GET, POST');
			@header('Access-Control-Allow-Credentials: true');
			@header('Access-Control-Allow-Headers: Authorization, Content-Type');
			@header('Vary: Origin');

			if('OPTIONS' === $_SERVER['REQUEST_METHOD']){
				exit;
			}
		}

		if('OPTIONS' === $_SERVER['REQUEST_METHOD']){
			status_header(403);
			exit;
		}
	}

	public static function get_current(){
		return self::$current_json;
	}

	public static function parse_post_type_module($module_args){
		$module_action	= $module_args['action'] ?? '';

		if(empty($module_action)){
			wpjam_send_json(['errcode'=>'empty_action',	'errmsg'=>'没有设置 action']);
		}

		global $wp, $wpjam_query_vars;	// 两个 post 模块的时候干扰。。。。

		if(empty($wpjam_query_vars)){
			$wpjam_query_vars	= $wp->query_vars; 
		}else{
			$wp->query_vars		= $wpjam_query_vars;
		}

		if($module_action == 'list'){
			return self::parse_post_list_module($module_args);
		}elseif($module_action == 'get'){
			return self::parse_post_get_module($module_args);
		}elseif($module_action == 'upload'){
			return self::parse_media_upload_module($module_args);
		}
	}

	/* 规则：
	** 1. 分成主的查询和子查询（$query_args['sub']=1）
	** 2. 主查询支持 $_GET 参数 和 $_GET 参数 mapping
	** 3. 子查询（sub）只支持 $query_args 参数
	** 4. 主查询返回 next_cursor 和 total_pages，current_page，子查询（sub）没有
	** 5. $_GET 参数只适用于 post.list 
	** 6. term.list 只能用 $_GET 参数 mapping 来传递参数
	*/
	public static function parse_post_list_module($query_args){
		global $wp, $wp_query;

		$is_main_query	= empty($query_args['sub']);

		if(!$is_main_query){	// 子查询不支持 $_GET 参数
			$wp->query_vars	= [];
		}

		// 缓存处理
		$wp->set_query_var('cache_results', true);

		if($query_args){
			foreach ($query_args as $query_key => $query_var) {
				$wp->set_query_var($query_key, $query_var);
			}
		}

		$post_type	= $wp->query_vars['post_type'] ?? '';

		if(!empty($query_args['output'])){
			$output	= $query_args['output'];
		}elseif($post_type && !is_array($post_type)){
			$output	= $post_type.'s';
		}else{
			$output	= 'posts';
		}

		if($is_main_query){
			if($posts_per_page = (int)wpjam_get_parameter('posts_per_page')){
				if($posts_per_page	> 20){
					$posts_per_page	= 20;
				}

				$wp->set_query_var('posts_per_page', $posts_per_page);
			}

			if($offset = (int)wpjam_get_parameter('offset')){
				$wp->set_query_var('offset', $offset);
			}

			$orderby	= $wp->query_vars['orderby'] ?? 'date';
			$paged		= $wp->query_vars['paged'] ?? null;
			$use_cursor	= (empty($paged) && is_null(wpjam_get_parameter('s')) && !is_array($orderby) && in_array($orderby, ['date', 'post_date']));

			if($use_cursor){
				if($cursor = (int)wpjam_get_parameter('cursor')){
					$wp->set_query_var('cursor', $cursor);
					$wp->set_query_var('ignore_sticky_posts', true);
				}

				if($since = (int)wpjam_get_parameter('since')){
					$wp->set_query_var('since', $since);
					$wp->set_query_var('ignore_sticky_posts', true);
				}
			}

			// taxonomy 参数处理，同时支持 $_GET 和 $query_args 参数
			$taxonomies	= $post_type ? get_object_taxonomies($post_type) : get_taxonomies(['public'=>true]);

			if($taxonomies){
				if($category = wpjam_array_pull($taxonomies, 'category')){
					foreach(['category_id', 'cat_id'] as $cat_key){
						if($term_id	= (int)wpjam_get_parameter($cat_key)){
							$wp->set_query_var('cat', $term_id);
							break;
						}
					}
				}

				$taxonomies	= array_diff($taxonomies, ['post_format']);

				foreach($taxonomies as $taxonomy){
					$query_key	= wpjam_get_taxonomy_query_key($taxonomy);

					if($term_id	= (int)wpjam_get_parameter($query_key)){
						$wp->set_query_var($query_key, $term_id);
					}
				}

				if($term_id	= (int)wpjam_get_parameter('term_id')){
					if($taxonomy = wpjam_get_parameter('taxonomy')){
						$wp->set_query_var('term_id', $term_id);
						$wp->set_query_var('taxonomy', $taxonomy);
					}
				}
			}
		}

		wpjam_parse_query_vars($wp);

		$wp->query_posts();

		$_posts = [];

		while($wp_query->have_posts()){
			$wp_query->the_post();

			$_posts[]	= wpjam_get_post(get_the_ID(), $query_args);
		}

		$posts_json = [];

		if($is_main_query){
			if(is_category() || is_tag() || is_tax()){
				if($current_term = get_queried_object()){
					$taxonomy		= $current_term->taxonomy;
					$current_term	= wpjam_get_term($current_term, $taxonomy);

					$posts_json['current_taxonomy']		= $taxonomy;
					$posts_json['current_'.$taxonomy]	= $current_term;
				}else{
					$posts_json['current_taxonomy']		= null;
				}
			}elseif(is_author()){
				if($author = $wp_query->get('author')){
					$posts_json['current_author']	= WPJAM_User::get_instance($author)->parse_for_json();
				}else{
					$posts_json['current_author']	= null;
				}
			}

			$posts_json['total']		= (int)$wp_query->found_posts;
			$posts_json['total_pages']	= (int)$wp_query->max_num_pages;
			$posts_json['current_page']	= (int)($wp_query->get('paged') ?: 1);

			if($use_cursor){
				$posts_json['next_cursor']	= ($_posts && $wp_query->max_num_pages>1) ? end($_posts)['timestamp'] : 0;
			}

			$posts_json['page_title']	= $posts_json['share_title'] = html_entity_decode(wp_get_document_title());
		}

		$wp_query->set('output', $output);

		$posts_json[$output]	= $_posts;

		return apply_filters('wpjam_posts_json', $posts_json, $wp_query, $query_args);
	}

	public static function parse_post_get_module($query_args){
		global $wp, $wp_query;

		$post_id	= $query_args['id'] ?? (int)wpjam_get_parameter('id');
		$post_type	= $query_args['post_type'] ?? wpjam_get_parameter('post_type',	['default'=>'any']);

		if($post_type != 'any'){
			$pt_obj	= get_post_type_object($post_type);

			if(!$pt_obj){
				wpjam_send_json(['errcode'=>'post_type_not_exists',	'errmsg'=>'post_type 未定义']);
			}
		}

		if(empty($post_id)){
			if($post_type == 'any'){
				wpjam_send_json(['errcode'=>'empty_post_id',	'errmsg'=>'文章ID不能为空']);
			}

			$orderby	= wpjam_get_parameter('orderby');

			if($orderby == 'rand'){
				$wp->set_query_var('orderby', 'rand');
			}else{
				$name_key	= $pt_obj->hierarchical ? 'pagename' : 'name';

				$wp->set_query_var($name_key,	wpjam_get_parameter($name_key,	['required'=>true]));
			}
		}else{
			$wp->set_query_var('p', $post_id);
		}

		$wp->set_query_var('post_type', $post_type);
		$wp->set_query_var('posts_per_page', 1);
		$wp->set_query_var('cache_results', true);

		$wp->query_posts();

		if($wp_query->have_posts()){
			$post_id	= $wp_query->post->ID;
		}else{
			if($post_name = get_query_var('name')){
				if($post_id = apply_filters('old_slug_redirect_post_id', null)){
					$post_type	= 'any';

					$wp->set_query_var('post_type', $post_type);
					$wp->set_query_var('posts_per_page', 1);
					$wp->set_query_var('p', $post_id);
					$wp->set_query_var('name', '');
					$wp->set_query_var('pagename', '');

					$wp->query_posts();
				}else{
					wpjam_send_json(['errcode'=>'empty_query',	'errmsg'=>'查询结果为空']);
				}
			}else{
				wpjam_send_json(['errcode'=>'empty_query',	'errmsg'=>'查询结果为空']);
			}
		}

		$_post	= wpjam_get_post($post_id, $query_args);

		$post_json	= [];

		$post_json['page_title']	= html_entity_decode(wp_get_document_title());

		if($share_title = wpjam_array_pull($_post, 'share_title')){
			$post_json['share_title']	= $share_title;
		}else{
			$post_json['share_title']	= $post_json['page_title'];
		}

		if($share_image = wpjam_array_pull($_post, 'share_image')){
			$post_json['share_image']	= $share_image;
		}

		$output	= $query_args['output'] ?? '';
		$output	= $output ?: $_post['post_type'];

		$post_json[$output]	= $_post;

		return $post_json;
	}

	public static function parse_taxonomy_module($module_args){
		$taxonomy	= $module_args['taxonomy'] ?? '';
		$tax_obj	= $taxonomy ? get_taxonomy($taxonomy) : null;

		if(empty($tax_obj)){
			wpjam_send_json(['errcode'=>'invalid_taxonomy',	'errmsg'=>'无效的自定义分类']);
		}

		$args	= $module_args;

		if($mapping = wpjam_array_pull($args, 'mapping')){
			$mapping	= wp_parse_args($mapping);

			if($mapping && is_array($mapping)){
				foreach ($mapping as $key => $get) {
					if($value = wpjam_get_parameter($get)){
						$args[$key]	= $value;
					}
				}
			}
		}

		$number		= (int)wpjam_array_pull($args, 'number');
		$output		= wpjam_array_pull($args, 'output') ?: $taxonomy.'s';
		$max_depth	= wpjam_array_pull($args, 'max_depth') ?: ($tax_obj->levels ?? -1);

		$terms_json	= [];

		if($terms = wpjam_get_terms($args, $max_depth)){
			if($number){
				$paged	= $args['paged'] ?? 1;
				$offset	= $number * ($paged-1);

				$terms_json['current_page']	= (int)$paged;
				$terms_json['total_pages']	= ceil(count($terms)/$number);
				$terms = array_slice($terms, $offset, $number);
			}

			$terms_json[$output]	= array_values($terms);
		}else{
			$terms_json[$output]	= [];
		}

		$terms_json['page_title']	= $tax_obj->label;

		return $terms_json;
	}

	public static function parse_media_upload_module($module_args){
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$media_id	= $module_args['media'] ?? 'media';
		$output		= $module_args['output'] ?? 'url';

		if (!isset($_FILES[$media_id])) {
			wpjam_send_json(['errcode'=>'empty_media',	'errmsg'=>'媒体流不能为空！']);
		}

		$post_id		= (int)wpjam_get_parameter('post_id',	['method'=>'POST', 'default'=>0]);
		$attachment_id	= media_handle_upload($media_id, $post_id);

		if(is_wp_error($attachment_id)){
			wpjam_send_json($attachment_id);
		}

		return [$output=>wp_get_attachment_url($attachment_id)];
	}

	public static function parse_media_module($module_args){
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$media_id	= $module_args['media'] ?? 'media';
		$output		= $module_args['output'] ?? 'url';

		if(!isset($_FILES[$media_id])){
			wpjam_send_json(['errcode'=>'empty_media',	'errmsg'=>'媒体流不能为空！']);
		}

		$upload_file	= wp_handle_upload($_FILES[$media_id], ['test_form'=>false]);

		if(isset($upload_file['error'])){
			wpjam_send_json(['errcode'=>'upload_error',	'errmsg'=>$upload_file['error']]);
		}

		return [$output=>$upload_file['url']];
	}

	public static function parse_setting_module($module_args){
		if(empty($module_args['option_name'])){
			return new WP_Error('empty_option_name', 'option_name 不能为空');
		}

		$option_name	= $module_args['option_name'] ?? '';
		$setting_name	= $module_args['setting_name'] ?? ($module_args['setting'] ?? '');
		$output			= $module_args['output'] ?? '';

		if($setting_name){
			$output	= $output ?: $setting_name; 
			$value	= wpjam_get_setting($option_name, $setting_name);
		}else{
			$output	= $output ?: $option_name;
			$value	= wpjam_get_option($option_name);
		}

		$value	= apply_filters('wpjam_setting_json', $value, $option_name, $setting_name);

		if(is_wp_error($value)){
			return $value;
		}

		return [$output=>$value];
	}
}

class WPJAM_API{
	public static function get_filter_name($name='', $type=''){
		$filter	= str_replace('-', '_', $name);
		$filter	= str_replace('wpjam_', '', $filter);

		return 'wpjam_'.$filter.'_'.$type;
	}

	public static function method_allow($method, $send=true){
		if($_SERVER['REQUEST_METHOD'] != $method){
			$wp_error = new WP_Error('method_not_allow', '接口不支持 '.$_SERVER['REQUEST_METHOD'].' 方法，请使用 '.$method.' 方法！');
			if($send){
				self::send_json($wp_error);
			}else{
				return $wp_error;
			}
		}else{
			return true;
		}
	}

	private static function get_post_input(){
		static $post_input;
		if(!isset($post_input)){
			$post_input	= file_get_contents('php://input');
			// trigger_error(var_export($post_input,true));
			if(is_string($post_input)){
				$post_input	= @self::json_decode($post_input);
			}
		}

		return $post_input;
	}

	public static function get_parameter($parameter, $args=[]){
		$value		= null;
		$method		= !empty($args['method']) ? strtoupper($args['method']) : 'GET';

		if($method == 'GET'){
			if(isset($_GET[$parameter])){
				$value = wp_unslash($_GET[$parameter]);
			}
		}elseif($method == 'POST'){
			if(empty($_POST)){
				$post_input	= self::get_post_input();

				if(is_array($post_input) && isset($post_input[$parameter])){
					$value = $post_input[$parameter];
				}
			}else{
				if(isset($_POST[$parameter])){
					$value = wp_unslash($_POST[$parameter]);
				}
			}
		}else{
			if(!isset($_GET[$parameter]) && empty($_POST)){
				$post_input	= self::get_post_input();

				if(is_array($post_input) && isset($post_input[$parameter])){
					$value = $post_input[$parameter];
				}
			}else{
				if(isset($_REQUEST[$parameter])){
					$value = wp_unslash($_REQUEST[$parameter]);
				}
			}
		}

		if(is_null($value) && isset($args['default'])){
			return $args['default'];
		}

		$validate_callback	= $args['validate_callback'] ?? '';

		$send	= $args['send'] ?? true;

		if($validate_callback && is_callable($validate_callback)){
			$result	= call_user_func($validate_callback, $value);

			if($result === false){
				$wp_error = new WP_Error('invalid_parameter', '非法参数：'.$parameter);

				if($send){
					self::send_json($wp_error);
				}else{
					return $wp_error;
				}
			}elseif(is_wp_error($result)){
				if($send){
					self::send_json($result);
				}else{
					return $result;
				}
			}
		}else{
			if(!empty($args['required']) && is_null($value)){
				$wp_error = new WP_Error('missing_parameter', '缺少参数：'.$parameter);

				if($send){
					self::send_json($wp_error);
				}else{
					return $wp_error;
				}
			}

			$length	= $args['length'] ?? 0;
			$length	= (int)$length;

			if($length && (mb_strlen($value) < $length)){
				$wp_error = new WP_Error('short_parameter', $parameter.' 参数长度不能少于 '.$length);

				if($send){
					self::send_json($wp_error);
				}else{
					return $wp_error;
				}
			}
		}

		$sanitize_callback	= $args['sanitize_callback'] ?? '';

		if($sanitize_callback && is_callable($sanitize_callback)){
			$value	= call_user_func($sanitize_callback, $value);
		}else{
			if(!empty($args['type']) && $args['type'] == 'int' && $value){
				$value	= (int)$value;
			}
		}

		return $value;
	}

	public static function get_data_parameter($parameter, $args=[]){
		$value		= null;

		if(isset($_GET[$parameter])){
			$value	= wp_unslash($_GET[$parameter]);
		}elseif(isset($_REQUEST['data'])){
			$data		= wp_parse_args(wp_unslash($_REQUEST['data']));
			$defaults	= !empty($_REQUEST['defaults']) ? wp_parse_args(wp_unslash($_REQUEST['defaults'])) : [];
			$data		= wpjam_array_merge($defaults, $data);

			if(isset($data[$parameter])){
				$value	= $data[$parameter];
			}
		}

		if(is_null($value) && isset($args['default'])){
			return $args['default'];
		}

		$sanitize_callback	= $args['sanitize_callback'] ?? '';

		if(is_callable($sanitize_callback)){
			$value	= call_user_func($sanitize_callback, $value);
		}

		return $value;
	}

	public static function json_encode( $data, $options=JSON_UNESCAPED_UNICODE, $depth = 512){
		return wp_json_encode($data, $options, $depth);
	}

	public static function send_json($response=[], $status_code=null){
		if(is_wp_error($response)){
			$errdata	= $response->get_error_data();
			$response	= ['errcode'=>$response->get_error_code(), 'errmsg'=>$response->get_error_message()];

			if($errdata){
				$errdata	= is_array($errdata) ? $errdata : ['errdata'=>$errdata];
				$response 	= $response + $errdata;
			}
		}else{
			$response	= array_merge(['errcode'=>0], $response);
		}

		$result	= self::json_encode($response);

		if(!headers_sent() && !wpjam_doing_debug()){
			if(!is_null($status_code)){
				status_header($status_code);
			}

			if(wp_is_jsonp_request()){
				@header('Content-Type: application/javascript; charset=' . get_option('blog_charset'));

				$jsonp_callback	= $_GET['_jsonp'];

				$result	= '/**/' . $jsonp_callback . '(' . $result . ')';

			}else{
				@header('Content-Type: application/json; charset=' . get_option('blog_charset'));
			}
		}

		echo $result;

		exit;
	}

	public static function json_decode($json, $assoc=true, $depth=512, $options=0){
		$json	= wpjam_strip_control_characters($json);

		if(empty($json)){
			return new WP_Error('empty_json', 'JSON 内容不能为空！');
		}

		$result	= json_decode($json, $assoc, $depth, $options);

		if(is_null($result)){
			$result	= json_decode(stripslashes($json), $assoc, $depth, $options);

			if(is_null($result)){
				if(wpjam_doing_debug()){
					print_r(json_last_error());
					print_r(json_last_error_msg());
				}
				trigger_error('json_decode_error '. json_last_error_msg()."\n".var_export($json,true));
				return new WP_Error('json_decode_error', json_last_error_msg());
			}
		}

		return $result;
	}

	public static function http_request($url, $args=[], $err_args=[]){
		$args = wp_parse_args($args, [
			'timeout'		=> 5,
			'body'			=> [],
			'headers'		=> [],
			'sslverify'		=> false,
			'blocking'		=> true,	// 如果不需要立刻知道结果，可以设置为 false
			'stream'		=> false,	// 如果是保存远程的文件，这里需要设置为 true
			'filename'		=> null,	// 设置保存下来文件的路径和名字
			// 'headers'	=> ['Accept-Encoding'=>'gzip;'],	//使用压缩传输数据
			// 'headers'	=> ['Accept-Encoding'=>''],
			// 'compress'	=> false,
			'decompress'	=> true,
		]);

		if(wpjam_doing_debug()){
			print_r($url);
			print_r($args);
		}

		if(isset($args['json_encode_required'])){
			$json_encode_required	= wpjam_array_pull($args, 'json_encode_required');
		}elseif(isset($args['need_json_encode'])){
			$json_encode_required	= wpjam_array_pull($args, 'need_json_encode');
		}else{
			$json_encode_required	= false;
		}

		if(isset($args['json_decode_required'])){
			$json_decode_required	= wpjam_array_pull($args, 'json_decode_required');
		}elseif(isset($args['need_json_decode'])){
			$json_decode_required	= wpjam_array_pull($args, 'need_json_decode');
		}else{
			$json_decode_required	= true;
		}

		$method	= wpjam_array_pull($args, 'method');
		$method	= $method ? strtoupper($method) : ($args['body'] ? 'POST' : 'GET');

		if($method == 'GET'){
			$response = wp_remote_get($url, $args);
		}elseif($method == 'POST'){
			if($json_encode_required){
				if(is_array($args['body'])){
					$args['body']	= self::json_encode($args['body']);
				}

				if(empty($args['headers']['Content-Type'])){
					$args['headers']['Content-Type']	= 'application/json';
				}
			}

			$response	= wp_remote_post($url, $args);
		}elseif($method == 'FILE'){	// 上传文件
			$args['method']				= $args['body'] ? 'POST' : 'GET';
			$args['sslcertificates']	= $args['sslcertificates'] ?? ABSPATH.WPINC.'/certificates/ca-bundle.crt';
			$args['user-agent']			= $args['user-agent'] ?? 'WordPress';

			$wp_http_curl	= new WP_Http_Curl();
			$response		= $wp_http_curl->request($url, $args);
		}elseif($method == 'HEAD'){
			if($json_encode_required && is_array($args['body'])){
				$args['body']	= self::json_encode($args['body']);
			}

			$response = wp_remote_head($url, $args);
		}else{
			if($json_encode_required && is_array($args['body'])){
				$args['body']	= self::json_encode($args['body']);
			}

			$response = wp_remote_request($url, $args);
		}

		if(is_wp_error($response)){
			trigger_error($url."\n".$response->get_error_code().' : '.$response->get_error_message()."\n".var_export($args['body'],true));
			return $response;
		}

		if(!empty($response['response']['code']) && $response['response']['code'] != 200){
			return new WP_Error($response['response']['code'], '远程服务器错误：'.$response['response']['code'].' - '.$response['response']['message']);
		}

		if(!$args['blocking']){
			return true;
		}

		$headers	= $response['headers'];
		$response	= $response['body'];

		if(isset($headers['content-type'])){
			$content_type	= is_array($headers['content-type']) ? implode(' ', $headers['content-type']) : $headers['content-type'];
		}else{
			$content_type	= '';
		}

		if($json_decode_required || ($content_type && strpos($content_type, '/json'))){
			if($args['stream']){
				$response	= file_get_contents($args['filename']);
			}

			if(empty($response)){
				trigger_error(var_export($response, true).var_export($headers, true));
			}else{
				$response	= self::json_decode($response);

				if(is_wp_error($response)){
					return $response;
				}
			}
		}

		$err_args	= wp_parse_args($err_args,  [
			'errcode'	=>'errcode',
			'errmsg'	=>'errmsg',
			'detail'	=>'detail',
			'success'	=>'0',
		]);

		if(isset($response[$err_args['errcode']]) && $response[$err_args['errcode']] != $err_args['success']){
			$errcode	= wpjam_array_pull($response, $err_args['errcode']);
			$errmsg		= wpjam_array_pull($response, $err_args['errmsg']);
			$detail		= wpjam_array_pull($response, $err_args['detail']);
			$detail		= is_null($detail) ? array_filter($response) : $detail;

			if(apply_filters('wpjam_http_response_error_debug', true, $errcode, $errmsg, $detail)){
				trigger_error($url."\n".$errcode.' : '.$errmsg."\n".($detail ? var_export($detail,true)."\n" : '').var_export($args['body'],true));
			}

			return new WP_Error($errcode, $errmsg, $detail);
		}

		if(wpjam_doing_debug()){
			echo $url;
			print_r($response);
		}

		return $response;
	}

	public static function get_apis(){
		return WPJAM_JSON::get_by();
	}
}