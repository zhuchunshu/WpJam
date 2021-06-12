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
	return WPJAM_User::get_current_user($required);
}

function wpjam_get_current_commenter(){
	return WPJAM_User::get_current_commenter();
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
function wpjam_get_option($option_name, $blog_id=0, $site_default=false){
	return WPJAM_Setting::get_option($option_name, $blog_id, $site_default);
}

function wpjam_update_option($option_name, $option_value, $blog_id=0){
	return WPJAM_Setting::update_option($option_name, $option_value, $blog_id);
}


// WP_Query 缓存
function wpjam_query($args=[]){
	$args['no_found_rows']			= $args['no_found_rows'] ?? true;
	$args['ignore_sticky_posts']	= $args['ignore_sticky_posts'] ?? true;

	$args['cache_it']	= true;

	return new WP_Query($args);
}

function wpjam_parse_query($wp_query, $args=[]){
	return WPJAM_Post::parse_query($wp_query, $args);
}

function wpjam_render_query($wp_query, $args=[]){
	return WPJAM_Post::render_query($wp_query, $args);
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
	$instance	= WPJAM_Post::get_instance($post);

	return is_wp_error($instance) ? [] : $instance->parse_for_json($args);
}

function wpjam_get_post_views($post=null, $addon=true){
	$instance	= WPJAM_Post::get_instance($post);

	return is_wp_error($instance) ? 0 : $instance->get_views($addon);
}

function wpjam_update_post_views($post=null){
	$instance	= WPJAM_Post::get_instance($post);

	return is_wp_error($instance) ? null : $instance->view();
}

function wpjam_get_post_excerpt($post=null, $excerpt_length=240){
	$instance	= WPJAM_Post::get_instance($post);

	return is_wp_error($instance) ? '' : $instance->get_excerpt($excerpt_length);
}

function wpjam_get_post_content($post=null, $raw=false){
	$instance	= WPJAM_Post::get_instance($post);

	return is_wp_error($instance) ? '' : $instance->get_content($raw);
}

function wpjam_get_post_thumbnail_url($post=null, $size='full', $crop=1){
	$instance	= WPJAM_Post::get_instance($post);

	return is_wp_error($instance) ? '' : $instance->get_thumbnail_url($size, $crop);
}

function wpjam_get_post_first_image_url($post=null, $size='full'){
	$instance	= WPJAM_Post::get_instance($post);

	return is_wp_error($instance) ? '' : $instance->get_first_image_url($size);
}

function wpjam_related_posts($args=[]){
	echo wpjam_get_related_posts(null, $args, false);
}

function wpjam_get_related_posts($post=null, $args=[], $parse_for_json=false){
	$instance	= WPJAM_Post::get_instance($post);

	if(is_wp_error($instance)){
		return [];
	}

	$post_type	= $args['post_type'] ?? null;
	$number		= $args['number'] ?? 5;

	$wp_query	= $instance->get_related_query($number, $post_type);

	if($parse_for_json){
		return wpjam_parse_query($wp_query, $args);
	}else{
		return wpjam_render_query($wp_query, $args);
	}
}

function wpjam_get_new_posts($args=[], $parse_for_json=false){
	$wp_query	= wpjam_query([
		'post_status'	=> 'publish',
		'posts_per_page'=> $args['number'] ?? 5, 
		'post_type'		=> $args['post_type'] ?? 'post', 
		'orderby'		=> $args['orderby'] ?? 'date', 
	]);

	if($parse_for_json){
		return wpjam_parse_query($wp_query, $args);
	}else{
		return wpjam_render_query($wp_query, $args);
	}
}

function wpjam_get_top_viewd_posts($args=[], $parse_for_json=false){
	$date_query	= isset($args['days']) ? [[
		'column'	=> $args['column'] ?? 'post_date_gmt',
		'after'		=> $args['days'].' days ago',
	]] : [];

	$wp_query	= wpjam_query([
		'post_status'	=> 'publish',
		'posts_per_page'=> $args['number'] ?? 5, 
		'post_type'		=> $args['post_type'] ?? ['post'], 
		'orderby'		=> 'meta_value_num', 
		'meta_key'		=> 'views', 
		'date_query'	=> $date_query 
	]);

	if($parse_for_json){
		return wpjam_parse_query($wp_query, $args);
	}else{
		return wpjam_render_query($wp_query, $args);
	}
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

function wpjam_get_term($term, $taxonomy){
	$instance	= WPJAM_Term::get_instance($term);

	if(is_wp_error($instance)){
		return $instance;
	}

	if($taxonomy && $taxonomy != 'any' && $taxonomy != $instance->taxonomy){
		return new WP_Error('invalid_taxonomy', '无效的分类模式');
	}

	return $instance->parse_for_json();
}

function wpjam_get_term_thumbnail_url($term=null, $size='full', $crop=1){
	$instance	= WPJAM_Term::get_instance($term);

	return is_wp_error($instance) ? '' : $instance->get_thumbnail_url($size, $crop);
}


// 获取当前页面 url
function wpjam_get_current_page_url(){
	return WPJAM_Util::get_current_page_url();
}

function wpjam_human_time_diff($from, $to=0) {
	return WPJAM_Util::human_time_diff($from, $to);
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

function wpjam_download_image($image_url, $name='', $media=false){
	return WPJAM_Util::download_image($image_url, $name, $media);
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
	return WPJAM_Field::validate($field, $value);
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

function wpjam_array_pull(&$array, $key){
	return WPJAM_Array::pull($array, $key);
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
