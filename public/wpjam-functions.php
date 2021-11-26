<?php
// 获取参数，
function wpjam_get_parameter($parameter, $args=[]){
	return WPJAM_API::get_parameter($parameter, $args);
}

function wpjam_get_data_parameter($parameter, $args=[]){
	return WPJAM_API::get_data_parameter($parameter, $args);
}

function wpjam_send_json($response=[], $status_code=null){
	WPJAM_API::send_json($response, $status_code);
}

function wpjam_json_encode($data, $options=JSON_UNESCAPED_UNICODE, $depth=512){
	return WPJAM_API::json_encode($data, $options, $depth);
}

function wpjam_json_decode($json, $assoc=true, $depth=512, $options=0){
	return WPJAM_API::json_decode($json, $assoc, $depth, $options);
}

function wpjam_remote_request($url, $args=[], $err_args=[]){
	return WPJAM_API::http_request($url, $args, $err_args);
}

function wpjam_get_filter_name($name='', $type=''){
	return WPJAM_API::get_filter_name($name, $type);
}


function wpjam_get_current_user($required=false){
	$current_user	= apply_filters('wpjam_current_user', null);

	if($required){
		if(is_null($current_user)){
			return new WP_Error('bad_authentication', '无权限');
		}
	}else{
		if(is_wp_error($current_user)){
			return null;
		}
	}

	return $current_user;
}

function wpjam_get_current_commenter(){
	if(get_option('comment_registration')){
		return new WP_Error('logged_in_required', '只支持登录用户操作');
	}

	$commenter	= wp_get_current_commenter();

	if(empty($commenter['comment_author_email'])){
		return new WP_Error('bad_authentication', '无权限');
	}

	return $commenter;
}


// 获取设置
function wpjam_get_setting($option_name, $setting_name, $blog_id=0){
	return WPJAM_Setting::get_setting($option_name, $setting_name, $blog_id);
}

// 更新设置
function wpjam_update_setting($option_name, $setting_name, $setting_value, $blog_id=0){
	return WPJAM_Setting::update_setting($option_name, $setting_name, $setting_value, $blog_id);
}

function wpjam_delete_setting($option_name, $setting_name, $blog_id=0){
	return WPJAM_Setting::delete_setting($option_name, $setting_name, $blog_id);
}

// 获取选项
function wpjam_get_option($name, $blog_id=0){
	return WPJAM_Setting::get_option($name, $blog_id);
}

function wpjam_update_option($name, $value, $blog_id=0){
	return WPJAM_Setting::update_option($name, $value, $blog_id);
}

function wpjam_get_site_option($name){
	return WPJAM_Setting::get_site_option($name);
}

function wpjam_update_site_option($name, $value){
	return WPJAM_Setting::update_site_option($name, $value);
}


function wpjam_get_by_meta($meta_type, ...$args){
	return WPJAM_Meta::get_by($meta_type, ...$args);
}

function wpjam_get_metadata($meta_type, $object_id, ...$args){
	return WPJAM_Meta::get_data($meta_type, $object_id, ...$args);
}

function wpjam_update_metadata($meta_type, $object_id, ...$args){
	return WPJAM_Meta::update_data($meta_type, $object_id, ...$args);
}

// WP_Query 缓存
function wpjam_query($args=[]){
	$args['no_found_rows']			= $args['no_found_rows'] ?? true;
	$args['ignore_sticky_posts']	= $args['ignore_sticky_posts'] ?? true;

	$args['cache_it']	= true;

	return new WP_Query($args);
}

function wpjam_parse_query($query, $args=[], $parse_for_json=true){
	if($parse_for_json){
		return WPJAM_Post::parse_query($query, $args);
	}else{
		return wpjam_render_query($query, $args);
	}
}

function wpjam_render_query($query, $args=[]){
	return WPJAM_Post::render_query($query, $args);
}

function wpjam_validate_post($post_id, $post_type=''){
	return WPJAM_Post::validate($post_id, $post_type);
}

function wpjam_get_posts($post_ids, $args=[]){
	$posts = WPJAM_Post::get_by_ids($post_ids, $args);

	return $posts ? array_values($posts) : [];
}

function wpjam_get_post_id_field($post_type='post', $args=[]){
	return WPJAM_Post::get_id_field($post_type, $args);
}

function wpjam_get_post($post, $args=[]){
	$object	= WPJAM_Post::get_instance($post);

	return is_wp_error($object) ? [] : $object->parse_for_json($args);
}

function wpjam_get_post_views($post=null, $addon=true){
	$object	= WPJAM_Post::get_instance($post);

	return is_wp_error($object) ? 0 : $object->get_views($addon);
}

function wpjam_update_post_views($post=null){
	$object	= WPJAM_Post::get_instance($post);

	return is_wp_error($object) ? null : $object->view();
}

function wpjam_get_post_excerpt($post=null, $excerpt_length=240){
	$object	= WPJAM_Post::get_instance($post);

	return is_wp_error($object) ? '' : $object->get_excerpt($excerpt_length);
}

function wpjam_get_post_content($post=null, $raw=false){
	$object	= WPJAM_Post::get_instance($post);

	return is_wp_error($object) ? '' : $object->get_content($raw);
}

function wpjam_get_post_thumbnail_url($post=null, $size='full', $crop=1){
	$object	= WPJAM_Post::get_instance($post);

	return is_wp_error($object) ? '' : $object->get_thumbnail_url($size, $crop);
}

function wpjam_get_post_first_image_url($post=null, $size='full'){
	$object	= WPJAM_Post::get_instance($post);

	return is_wp_error($object) ? '' : $object->get_first_image_url($size);
}

function wpjam_get_post_term_taxonomy_ids($post=null, $taxonomies=[]){
	$object	= WPJAM_Post::get_instance($post);

	return is_wp_error($object) ? [] : $object->get_term_taxonomy_ids($taxonomies);
}

function wpjam_related_posts($args=[]){
	echo wpjam_get_related_posts(null, $args, false);
}

function wpjam_get_related_posts($post=null, $args=[], $parse_for_json=false){
	$object	= WPJAM_Post::get_instance($post);

	if(is_wp_error($object)){
		return [];
	}

	$number	= wpjam_array_pull($args, 'number') ?: 5;
	$query	= $object->get_related_query($number);

	return wpjam_parse_query($query, $args, $parse_for_json);
}

function wpjam_get_new_posts($args=[], $parse_for_json=false){
	$query	= wpjam_query([
		'post_status'		=> 'publish',
		'posts_per_page'	=> wpjam_array_pull($args, 'number') ?: 5, 
		'post_type'			=> wpjam_array_pull($args, 'post_type') ?: 'post', 
		'orderby'			=> wpjam_array_pull($args, 'orderby') ?: 'date', 
	]);

	return wpjam_parse_query($query, $args, $parse_for_json);
}

function wpjam_get_top_viewd_posts($args=[], $parse_for_json=false){
	$days	= wpjam_array_pull($args, 'days');
	$query	= wpjam_query([
		'posts_per_page'	=> wpjam_array_pull($args, 'number') ?: 5,
		'post_type'			=> wpjam_array_pull($args, 'post_type') ?: 'post', 
		'post_status'		=> 'publish',
		'orderby'			=> 'meta_value_num', 
		'meta_key'			=> 'views', 
		'date_query'		=> $days ? [[
			'column'	=> wpjam_array_pull($args, 'column') ?: 'post_date_gmt',
			'after'		=> date('Y-m-d', current_time('timestamp')-DAY_IN_SECONDS*$days)
		]] : [] 
	]);

	return wpjam_parse_query($query, $args, $parse_for_json);
}

function wpjam_get_permastruct($name){
	return $GLOBALS['wp_rewrite']->extra_permastructs[$name]['struct'] ?? '';
}


function wpjam_get_taxonomy_query_key($taxonomy){
	if($taxonomy == 'category'){
		return 'cat';
	}elseif($taxonomy == 'post_tag'){
		return 'tag_id';
	}else{
		return $taxonomy.'_id';
	}
}

function wpjam_get_terms(...$args){
	if($args[0] && wp_is_numeric_array($args[0])){
		$term_ids	= $args[0];
		$_args		= $args[1] ?? [];
		$terms		= WPJAM_Term::update_caches($term_ids, $_args);

		return $terms ? array_values($terms) : [];
	}else{
		$max_depth	= $args[1] ?? -1;
		$_args		= $args[0];

		return WPJAM_Term::get_terms($_args, $max_depth);
	}
}

function wpjam_flatten_terms($terms){
	return WPJAM_Term::flatten($terms);
}

function wpjam_get_term_id_field($taxonomy='category', $args=[]){
	return WPJAM_Term::get_id_field($taxonomy, $args);
}

function wpjam_get_related_object_ids($term_taxonomy_ids, $number, $page=1){
	return WPJAM_Term::get_related_object_ids($term_taxonomy_ids, $number, $page);
}

function wpjam_get_term($term, $taxonomy=''){
	$term	= get_term($term, $taxonomy);
	$object	= is_wp_error($term) ? $term : WPJAM_Term::get_instance($term);

	if(is_wp_error($object)){
		return $object;
	}

	if($taxonomy && $taxonomy != 'any' && $taxonomy != $object->taxonomy){
		return new WP_Error('invalid_taxonomy', '无效的分类模式');
	}

	return $object->parse_for_json();
}

function wpjam_get_term_thumbnail_url($term=null, $size='full', $crop=1){
	$object	= WPJAM_Term::get_instance($term);

	return is_wp_error($object) ? '' : $object->get_thumbnail_url($size, $crop);
}



function wpjam_get_current_page_url(){
	return WPJAM_Util::get_current_page_url();
}

function wpjam_human_time_diff($from, $to=0) {
	return WPJAM_Util::human_time_diff($from, $to);
}

function wpjam_parse_show_if($show_if){
	return WPJAM_Util::parse_show_if($show_if);
}

function wpjam_show_if($item, $show_if){
	return WPJAM_Util::show_if($item, $show_if);
}

function wpjam_compare($value, $operator, $compare_value){
	return WPJAM_Util::compare($value, $operator, $compare_value);
}

function wpjam_parse_shortcode_attr($str, $tagnames=null){
	return 	WPJAM_Util::parse_shortcode_attr($str,  $tagnames);
}

function wpjam_zh_urlencode($url){
	return WPJAM_Util::zh_urlencode($url);
}

function wpjam_get_video_mp4($id_or_url){
	return WPJAM_Util::get_video_mp4($id_or_url);
}

function wpjam_get_qqv_mp4($vid){
	return WPJAM_Util::get_qqv_mp4($vid);
}

function wpjam_get_qqv_id($id_or_url){
	return WPJAM_Util::get_qqv_id($id_or_url);
}

function wpjam_download_image($image_url, $name='', $media=false, $post_id=0){
	return WPJAM_Util::download_image($image_url, $name, $media, $post_id);
}

function wpjam_fetch_external_images(&$img_urls, $media=true, $post_id=0){
	return WPJAM_Util::fetch_external_images($img_urls, $media, $post_id);
}

function wpjam_is_image($img_url){
	return WPJAM_Util::is_image($img_url);
}

function wpjam_is_external_image($img_url, $scene=''){
	return WPJAM_Util::is_external_image($img_url, $scene);
}

function wpjam_unserialize(&$serialized){
	return WPJAM_Util::unserialize($serialized);
}

// 去掉非 utf8mb4 字符
function wpjam_strip_invalid_text($str, $charset='utf8mb4'){
	return WPJAM_Util::strip_invalid_text($str, $charset);
}

// 去掉 4字节 字符
function wpjam_strip_4_byte_chars($chars){
	return WPJAM_Util::strip_4_byte_chars($chars);
}

// 去掉控制字符
function wpjam_strip_control_characters($text){
	return WPJAM_Util::strip_control_characters($text);
}

//获取纯文本
function wpjam_get_plain_text($text){
	return WPJAM_Util::get_plain_text($text);
}

//获取第一段
function wpjam_get_first_p($text){
	return WPJAM_Util::get_first_p($text);
}

//中文截取方式
function wpjam_mb_strimwidth($text, $start=0, $width=40, $trimmarker='...', $encoding='utf-8'){
	return WPJAM_Util::mb_strimwidth($text, $start, $width, $trimmarker, $encoding);
}

// 检查非法字符
function wpjam_blacklist_check($text, $name='内容'){
	return WPJAM_Util::blacklist_check($text, $name);
}

function wpjam_hex2rgba($color, $opacity=null){
	return WPJAM_Util::hex2rgba($color, $opacity);
}

function wpjam_unicode_decode($str){
	return WPJAM_Util::unicode_decode($str);
}

function wpjam_get_ipdata($ip=''){
	return WPJAM_Var::parse_ip($ip);
}

function wpjam_parse_ip($ip=''){
	return WPJAM_Var::parse_ip($ip);
}

function wpjam_get_ip(){
	return WPJAM_Var::get_ip();
}

function wpjam_get_user_agent(){
	return $_SERVER['HTTP_USER_AGENT'] ?? '';
}

function wpjam_get_ua(){
	return wpjam_get_user_agent();
}

function wpjam_parse_user_agent($user_agent='',$referer=''){
	return WPJAM_Var::parse_user_agent($user_agent, $referer);
}

function wpjam_is_webp_supported(){
	return $GLOBALS['is_chrome'] || is_android() || (is_ios() && wpjam_get_os_version() >= 14);
}

function wpjam_get_device(){
	return WPJAM_Var::get_instance()->device;
}

function is_ipad(){
	return wpjam_get_device() == 'iPad';
}

function is_iphone(){
	return wpjam_get_device() == 'iPone';
}

function wpjam_get_os(){
	return WPJAM_Var::get_instance()->os;
}

function wpjam_get_os_version(){
	return WPJAM_Var::get_instance()->os_version;
}

function is_ios(){
	return wpjam_get_os() == 'iOS';
}

function is_mac(){
	return is_macintosh();
}

function is_macintosh(){
	return wpjam_get_os() == 'Macintosh';
}

function is_android(){
	return wpjam_get_os() == 'Android';
}

function wpjam_get_browser(){
	return WPJAM_Var::get_instance()->browser;
}

function wpjam_get_browser_version(){
	return WPJAM_Var::get_instance()->browser_version;
}

function wpjam_get_app(){
	return WPJAM_Var::get_instance()->app;
}

function wpjam_get_app_version(){
	return WPJAM_Var::get_instance()->app_version;
}

// 判断当前用户操作是否在微信内置浏览器中
function is_weixin(){ 
	if(isset($_GET['weixin_appid'])){
		return true;
	}

	return wpjam_get_app() == 'weixin';
}

// 判断当前用户操作是否在微信小程序中
function is_weapp(){ 
	if(isset($_GET['appid'])){
		return true;
	}

	return wpjam_get_app() == 'weapp';
}

// 判断当前用户操作是否在头条小程序中
function is_bytedance(){ 
	if(isset($_GET['bytedance_appid'])){
		return true;
	}

	return wpjam_get_app() == 'bytedance';
}


function wpjam_generate_random_string($length){
	return WPJAM_Crypt::generate_random_string($length);
}

function wpjam_fields($fieds, $args=[]){
	return WPJAM_Field::fields_callback($fieds, $args);
}

function wpjam_validate_fields_value($fields, $values=null){
	return WPJAM_Field::fields_validate($fields, $values);
}

function wpjam_validate_field_value($field, $value){
	$object	= new WPJAM_Field($field);
	return $object->validate($value);
}

function wpjam_get_field_value($field, $args=[]){
	$object	= new WPJAM_Field($field);
	return $object->parse_value($args);
}

function wpjam_get_fieldset_type($field, $default='single'){
	return WPJAM_Field::get_fieldset_type($field, $default);
}

function wpjam_get_field_html($field){
	return WPJAM_Field::render($field);
}

function wpjam_render_field($field){
	return WPJAM_Field::render($field);
}

function wpjam_get_form_post($fields, $nonce_action='', $capability='manage_options'){
	return WPJAM_Field::form_validate($fields, $nonce_action, $capability);
}

function wpjam_form($fields, $form_url, $nonce_action='', $submit_text=''){
	WPJAM_Field::form_callback($fields, $form_url, $nonce_action, $submit_text);
}

if(!function_exists('is_login')){
	function is_login(){
		global $pagenow;
		return $pagenow == 'wp-login.php';
	}
}

function wpjam_doing_debug(){
	if(isset($_GET['debug'])){
		return $_GET['debug'] ? sanitize_key($_GET['debug']) : true;
	}else{
		return false;
	}
}

// 打印
function wpjam_print_r($value){
	$capability	= is_multisite() ? 'manage_site' : 'manage_options';

	if(current_user_can($capability)){
		echo '<pre>';
		print_r($value);
		echo '</pre>'."\n";
	}
}

function wpjam_var_dump($value){
	$capability	= is_multisite() ? 'manage_site' : 'manage_options';
	if(current_user_can($capability)){
		echo '<pre>';
		var_dump($value);
		echo '</pre>'."\n";
	}
}

function wpjam_pagenavi($total=0, $echo=true){
	$args = [
		'prev_text'	=> '&laquo;',
		'next_text'	=> '&raquo;'
	];

	if(!empty($total)){
		$args['total']	= $total;
	}

	if($echo){
		echo '<div class="pagenavi">'.paginate_links($args).'</div>'; 
	}else{
		return '<div class="pagenavi">'.paginate_links($args).'</div>'; 
	}
}

// 判断一个数组是关联数组，还是顺序数组
function wpjam_is_assoc_array(array $arr){
	if ([] === $arr) return false;
	return array_keys($arr) !== range(0, count($arr) - 1);
}

function wpjam_array_push(&$array, $data=null, $key=false){
	WPJAM_Array::push($array, $data, $key);
}

function wpjam_array_first($array, $callback=null){
	return WPJAM_Array::first($array, $callback);
}

function wpjam_array_pull(&$array, $key, $default=null){
	return WPJAM_Array::pull($array, $key, $default);
}

function wpjam_array_get($array, $key, $default=null){
	return WPJAM_Array::get($array, $key, $default);
}

function wpjam_array_except($array, $keys){
	return WPJAM_Array::except($array, $keys);
}

function wpjam_array_filter($array, $callback, $mode=0){
	return WPJAM_Array::filter($array, $callback, $mode);
}

function wpjam_array_merge($arr1, $arr2){
	return WPJAM_Array::merge($arr1, $arr2);
}

function wpjam_sort_items($items, $order_key='order', $order='DESC'){
	return wpjam_list_sort($items, $order_key, $order);
}

function wpjam_list_sort($list, $order_key='order', $order='DESC', $preserve_keys=true){
	return WPJAM_List_Util::sort($list, $order_key, $order);
}

function wpjam_list_filter($list, $args=[], $operator='AND'){	// 增强 wp_list_filter ，支持 in_array 判断
	return WPJAM_List_Util::filter($list, $args, $operator);
}

function wpjam_localize_script($handle, $object_name, $l10n ){
	wp_localize_script($handle, $object_name, ['l10n_print_after' => $object_name.' = ' . wpjam_json_encode($l10n)]);
}

function wpjam_is_mobile_number($number){
	return preg_match('/^0{0,1}(1[3,5,8][0-9]|14[5,7]|166|17[0,1,3,6,7,8]|19[8,9])[0-9]{8}$/', $number);
}

function wpjam_set_cookie($key, $value, $expire=DAY_IN_SECONDS){
	$expire	= $expire < time() ? $expire+time() : $expire;

	setcookie($key, $value, $expire, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);

	if(COOKIEPATH != SITECOOKIEPATH){
		setcookie($key, $value, $expire, SITECOOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
	}
}

function wpjam_clear_cookie($key){
	setcookie($key, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
	setcookie($key, ' ', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN);
}
