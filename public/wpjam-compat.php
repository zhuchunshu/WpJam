<?php
if(!function_exists('update_usermeta_cache')){
	function update_usermeta_cache($user_ids) {
		return update_meta_cache('user', $user_ids);
	}
}

if(!function_exists('get_userdata')){
	function get_userdata($user_id){
		$check	= apply_filters('wpjam_get_userdata', null, $user_id);

		if(null !== $check){
			return $check;
		}

		return get_user_by('id', $user_id);
	}
}

if(!function_exists('get_post_excerpt')){   
	//获取日志摘要
	function get_post_excerpt($post=null, $excerpt_length=240){
		_deprecated_function(__FUNCTION__, 'WPJAM Basic 4.2', 'wpjam_get_post_excerpt');
		return wpjam_get_post_excerpt($post, $excerpt_length);
	}
}

if(!function_exists('str_replace_deep')){
	function str_replace_deep($search, $replace, $value){
		return map_deep($value, function($value) use($search, $replace){
			return str_replace($search, $replace, $value);
		});
	}
}

if(!function_exists('user_can_for_blog')){
	function user_can_for_blog($user, $blog_id, $capability){
		$switched = is_multisite() ? switch_to_blog( $blog_id ) : false;

		if ( ! is_object( $user ) ) {
			$user = get_userdata( $user );
		}

		if ( ! $user || ! $user->exists() ) {
			return false;
		}

		if ( empty( $user ) ) {
			if ( $switched ) {
				restore_current_blog();
			}
			return false;
		}

		$args = array_slice( func_get_args(), 2 );
		$args = array_merge( array( $capability ), $args );

		$can = call_user_func_array( array( $user, 'has_cap' ), $args );

		if ( $switched ) {
			restore_current_blog();
		}

		return $can;
	}
}

if(!function_exists('get_metadata_by_value')){
	function get_metadata_by_value($meta_type, $meta_value, $meta_key=''){
		if($datas = wpjam_get_by_meta($meta_type, ['meta_key'=>$meta_key, 'meta_value'=>$meta_value])){
			return (object)current($datas);
		}

		return false;
	}
}

if(!function_exists('wp_cache_delete_multi')){
	function wp_cache_delete_multi($keys, $group = ''){
		foreach ($keys as $key) {
			wp_cache_delete($key, $group);
		}

		return true;
	}
}

if(!function_exists('wp_cache_get_multi')){
	function wp_cache_get_multi($keys, $group = ''){

		$datas = [];

		foreach ($keys as $key) {
			$datas[$key] = wp_cache_get($key, $group);
		}

		return $datas;
	}
}

if(!function_exists('wp_cache_get_with_cas')){
	function wp_cache_get_with_cas($key, $group = '', &$cas_token=null){
		return wp_cache_get($key, $group);
	}
}

if(!function_exists('wp_cache_cas')){
	function wp_cache_cas($cas_token, $key, $data, $group='', $expire=0){
		return wp_cache_set($key, $data, $group, $expire);
	}
}

if(!function_exists('get_post_type_support_value')){
	function get_post_type_support_value($post_type, $feature){
		$supports	= get_all_post_type_supports($post_type);

		if($supports && isset($supports[$feature])){
			if(is_array($supports[$feature]) && wp_is_numeric_array($supports[$feature]) && count($supports[$feature]) == 1){
				return current($supports[$feature]);
			}else{
				return $supports[$feature];
			}
		}else{
			return false;
		}
	}
}

if(!function_exists('array_key_first')){
	function array_key_first($arr) {
		foreach($arr as $key => $unused) {
			return $key;
		}

		return null;
	}
}

if(!function_exists('array_key_last')){
	function array_key_last($arr){
		if(!empty($arr)){
			return key(array_slice($arr, -1, 1, true));
		}

		return null;
	}
}

function wpjam_get_option_setting($name){
	$object = WPJAM_Option_Setting::get($name);

	return $object ? $object->to_array() : null;
}

function wpjam_get_ajax_button($args){
	return WPJAM_Page_Action::ajax_button($args);
}

function wpjam_get_ajax_form($args){
	return WPJAM_Page_Action::ajax_form($args);
}

function wpjam_ajax_button($args){
	echo wpjam_get_ajax_button($args);
}

function wpjam_ajax_form($args){
	echo wpjam_get_ajax_form($args);
}

function wpjam_get_post_options($post_type, $post_id=null){
	$pt_options		= [];
		
	foreach(WPJAM_Post_Option::get_registereds() as $meta_box => $object){
		if($object->is_available_for_post_type($post_type)){
			$pt_options[$meta_box] = $object->to_array();
		}
	}

	return $pt_options;
}

function wpjam_get_post_option_fields($post_type, $post_id=null){
	$pt_fields	= [];

	foreach(WPJAM_Post_Option::get_registereds() as $object){
		if($object->is_available_for_post_type($post_type)){
			if(empty($object->update_callback) && ($fields = $object->get_fields($post_type, $post_id))){
				$pt_fields	= array_merge($pt_fields, $fields);
			}
		}
	}

	return $pt_fields;
}

function wpjam_get_term_options($taxonomy, $term_id=null){
	$tax_fields	= [];

	foreach(WPJAM_Term_Option::get_registereds() as $object){
		if($object->is_available_for_taxonomy($taxonomy)){
			if($fields = $object->get_fields($term_id)){
				$tax_fields	= array_merge($tax_fields, $fields);
			}
		}
	}

	return $tax_fields;
}

function wpjam_get_post_fields($post_type, $post_id=null){
	return wpjam_get_post_option_fields($post_type, $post_id);
}

class WPJAM_PostType extends WPJAM_Post{}

add_filter('rewrite_rules_array', function($rules){
	if(has_filter('wpjam_rewrite_rules')){
		return array_merge(apply_filters('wpjam_rewrite_rules', []), $rules);
	}
	return $rules;
});

add_action('wpjam_builtin_page_load', function($screen_base, $current_screen){
	if($screen_base == 'post'){
		$post_type	= $current_screen->post_type;

		if(has_action('wpjam_post_page_file')){
			do_action('wpjam_post_page_file', $post_type);
		}

		if(has_filter('wpjam_post_options')){
			if($post_options = apply_filters('wpjam_post_options', [], $post_type)){
				foreach($post_options as $meta_box => $args){
					wpjam_register_post_option($meta_box, $args);
				}
			}
		}
	}elseif($screen_base == 'edit'){
		if(has_action('wpjam_post_list_page_file')){
			do_action('wpjam_post_list_page_file', $current_screen->post_type);
		}
	}elseif(in_array($screen_base, ['term', 'edit-tags'])){
		$taxonomy	= $current_screen->taxonomy;

		if(has_action('wpjam_term_list_page_file')){
			do_action('wpjam_term_list_page_file', $taxonomy);
		}

		if(has_filter('wpjam_term_options')){
			if($term_options = apply_filters('wpjam_term_options', [], $taxonomy)){
				foreach($term_options as $key => $args){
					wpjam_register_term_option($key, $args);
				}
			}
		}

		if(has_filter('wpjam_'.$taxonomy.'_term_options')){
			if($term_options = apply_filters('wpjam_'.$taxonomy.'_term_options', [])){
				foreach($term_options as $key => $args){
					wpjam_register_term_option($key, array_merge($args, ['taxonomy'=>$taxonomy]));
				}
			}
		}
	}
}, 10, 2);

function wpjam_form_field_tmpls($echo=true){}

function wpjam_urlencode_img_cn_name($img_url){
	return $img_url;
}

function wpjam_image_hwstring($size){
	$width	= (int)($size['width']);
	$height	= (int)($size['height']);
	return image_hwstring($width, $height);
}

function wpjam_api_set_response(&$response){
	_deprecated_function(__FUNCTION__, 'WPJAM Basic 4.3');
}

function wpjam_api_signon(){
	_deprecated_function(__FUNCTION__, 'WPJAM Basic 4.3');
}

function wpjam_get_taxonomy_levels($taxonomy){
	return get_taxonomy($taxonomy)->levels ?? 0;
}

function wpjam_get_post_type_setting($post_type){
	$settings	= get_option('wpjam_post_types') ?: [];
	return $settings[$post_type] ?? [];
}

function wpjam_get_api_setting($name){
	return wpjam_get_json_object($name);
}

function wpjam_is_json($json=''){
	_deprecated_function(__FUNCTION__, 'WPJAM Basic 4.2', 'wpjam_get_json');

	$wpjam_json = wpjam_get_json();

	if(empty($wpjam_json)){
		return false;
	}

	return $json ? $wpjam_json == $json : true;
}

function is_wpjam_json($json=''){
	_deprecated_function(__FUNCTION__, 'WPJAM Basic 4.2', 'wpjam_get_json');

	return wpjam_is_json($json);
}

function wpjam_is_module($module='', $action=''){
	return $GLOBALS['wpjam_route']->is_module($module, $action);
}

function is_module($module='', $action=''){
	return wpjam_is_module($module, $action);
}

function wpjam_has_path($path_type, $page_key){
	$path_obj	= WPJAM_Path::get($page_key);

	return is_null($path_obj) ? false : $path_obj->has($path_type);
}

function wpjam_get_paths_by_post_type($post_type, $path_type){
	return WPJAM_Path::get_by(compact('post_type', 'path_type'));
}

function wpjam_get_paths_by_taxonomy($taxonomy, $path_type){
	return WPJAM_Path::get_by(compact('taxonomy', 'path_type'));
}

function wpjam_generate_path($data){
	$page_key	= $data['page_key'] ?? '';
	$path_type	= $data['path_type'] ?? '';
	$path_type	= $path_type ?: 'weapp'; 	// 历史遗留问题，默认都是 weapp， 非常 ugly 
	return wpjam_get_path($path_type, $page_key, $data);
}

function wpjam_get_path_obj($page_key){
	return wpjam_get_path_object($page_key);
}

function wpjam_get_path_objs($path_type){
	return wpjam_get_paths($path_type);
}

function wpjam_render_path_item($item, $text, $platforms=[]){
	$platform	= wpjam_get_current_platform($platforms);
	$parsed		= wpjam_parse_path_item($item, $platform);

	return wpjam_get_path_item_link_tag($parsed, $text);
}

function wpjam_get_related_posts_query($number=5){
	$instance	= WPJAM_Post::get_instance();

	return is_wp_error($instance) ? null : $instance->get_related_query($number);
}

function wpjam_get_post_list($wp_query, $args=[]){
	$args['parse_for_json']	= false;

	return WPJAM_Post::get_list($wp_query, $args);
}

function wpjam_new_posts($args=[]){
	echo wpjam_get_new_posts($args);
}

function wpjam_top_viewd_posts($args=[]){
	echo wpjam_get_top_viewd_posts($args);
}

function wpjam_attachment_url_to_postid($url){
	$post_id = wp_cache_get($url, 'attachment_url_to_postid');

	if($post_id === false){
		global $wpdb;

		$upload_dir	= wp_get_upload_dir();
		$path		= str_replace(parse_url($upload_dir['baseurl'], PHP_URL_PATH).'/', '', parse_url($url, PHP_URL_PATH));

		$post_id	= $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value = %s", $path));

		wp_cache_set($url, $post_id, 'attachment_url_to_postid', DAY_IN_SECONDS);
	}

	return (int) apply_filters( 'attachment_url_to_postid', $post_id, $url );
}

// 获取远程图片
function wpjam_get_content_remote_image_url($img_url, $post_id=null){
	return $img_url;
}

function wpjam_image_remote_method($img_url=''){
	return '';
}

function wpjam_is_remote_image($img_url, $strict=true){
	if($strict){
		return !wpjam_is_cdn_url($img_url);	
	}else{
		return wpjam_is_external_image($img_url);
	}
}

function wpjam_get_content_width(){
	return (int)apply_filters('wpjam_content_image_width', wpjam_cdn_get_setting('width'));
}

function wpjam_cdn_replace_local_hosts($html, $to_cdn=true){
	return wpjam_cdn_host_replace($html, $to_cdn);
}

function wpjam_cdn_content($content){
	return WPJAM_CDN::content_images($content);
}

function wpjam_content_images($content, $max_width=0){
	return WPJAM_CDN::content_images($content, $max_width);
}

function wpjam_get_content_remote_img_url($img_url, $post_id=0){
	_deprecated_function(__FUNCTION__, 'WPJAM Basic 3.2', 'wpjam_get_content_remote_image_url');
	return wpjam_get_content_remote_image_url($img_url, $post_id);
}

function wpjam_get_post_first_image($post=null, $size='full'){
	_deprecated_function(__FUNCTION__, 'WPJAM Basic 3.2', 'wpjam_get_post_first_image_url');
	return wpjam_get_post_first_image_url($post=null, $size='full');
}

function wpjam_get_qqv_vid($id_or_url){
	return WPJAM_Utli::get_qqv_id($id_or_url);
}

function wpjam_get_qq_vid($id_or_url){
	return WPJAM_Utli::get_qqv_id($id_or_url);
}

function wpjam_sha1(...$args){
	return WPJAM_Crypt::sha1(...$args);
}

function wpjam_is_mobile() {
	_deprecated_function(__FUNCTION__, 'WPJAM Basic 3.2', 'wp_is_mobile');
	return wp_is_mobile();
}

function get_post_first_image($post_content=''){
	_deprecated_function(__FUNCTION__, 'WPJAM Basic 3.2', 'wpjam_get_post_first_image');
	return wpjam_get_post_first_image($post_content);
}

function wpjam_get_post_image_url($image_id, $size='full'){
	_deprecated_function(__FUNCTION__, 'WPJAM Basic 3.2', 'wp_get_attachment_image_url');

	if($thumb = wp_get_attachment_image_src($image_id, $size)){
		return $thumb[0];
	}
	return false;
}

function wpjam_has_post_thumbnail(){
	return wpjam_get_post_thumbnail_url() ? true : false;
}

function wpjam_post_thumbnail($size='thumbnail', $crop=1, $class='wp-post-image', $ratio=2){
	echo wpjam_get_post_thumbnail(null, $size, $crop, $class, $ratio);
}

function wpjam_get_post_thumbnail($post=null, $size='thumbnail', $crop=1, $class='wp-post-image', $ratio=2){
	$size	= wpjam_parse_size($size, $ratio);
	if($post_thumbnail_url = wpjam_get_post_thumbnail_url($post, $size, $crop)){
		$image_hwstring	= image_hwstring($size['width']/$ratio, $size['height']/$ratio);
		return '<img src="'.$post_thumbnail_url.'" alt="'.the_title_attribute(['echo'=>false]).'" class="'.$class.'"'.$image_hwstring.' />';
	}else{
		return '';
	}
}

function wpjam_get_post_thumbnail_src($post=null, $size='thumbnail', $crop=1, $ratio=1){
	_deprecated_function(__FUNCTION__, 'WPJAM Basic 3.2', 'wpjam_get_post_thumbnail_url');
	return wpjam_get_post_thumbnail_url($post, $size, $crop, $ratio);
}

function wpjam_get_post_thumbnail_uri($post=null, $size='full'){
	_deprecated_function(__FUNCTION__, 'WPJAM Basic 3.2', 'wpjam_get_post_thumbnail_url');
	return wpjam_get_post_thumbnail_url($post, $size);
}

function wpjam_get_default_thumbnail_src($size){
	_deprecated_function(__FUNCTION__, 'WPJAM Basic 3.2', 'wpjam_get_default_thumbnail_url');
	return wpjam_get_default_thumbnail_url($size);
}

function wpjam_get_default_thumbnail_uri(){
	_deprecated_function(__FUNCTION__, 'WPJAM Basic 3.2', 'wpjam_get_default_thumbnail_url');
	return wpjam_get_default_thumbnail_url('full');
}

function wpjam_has_term_thumbnail(){
	return wpjam_get_term_thumbnail_url()? true : false;
}

function wpjam_term_thumbnail($size='thumbnail', $crop=1, $class="wp-term-image", $ratio=2){
	echo wpjam_get_term_thumbnail(null, $size, $crop, $class);
}

function wpjam_get_term_thumbnail($term=null, $size='thumbnail', $crop=1, $class="wp-term-image", $ratio=2){
	$size	= wpjam_parse_size($size, $ratio);

	if($term_thumbnail_url = wpjam_get_term_thumbnail_url($term, $size, $crop)){
		$image_hwstring	= image_hwstring($size['width']/$ratio, $size['height']/$ratio);

		return  '<img src="'.$term_thumbnail_url.'" class="'.$class.'"'.$image_hwstring.' />';
	}else{
		return '';
	}
}

/* category thumbnail */
function wpjam_has_category_thumbnail(){
	return wpjam_has_term_thumbnail();
}

function wpjam_get_category_thumbnail_url($term=null, $size='full', $crop=1, $ratio=1){
	return wpjam_get_term_thumbnail_url($term, $size, $crop, $ratio);
}

function wpjam_get_category_thumbnail($term=null, $size='thumbnail', $crop=1, $class="wp-category-image", $ratio=2){
	return wpjam_get_term_thumbnail($term, $size, $crop, $class, $ratio);
}

function wpjam_category_thumbnail($size='thumbnail', $crop=1, $class="wp-category-image", $ratio=2){
	wpjam_term_thumbnail($size, $crop, $class, $ratio);
}

function wpjam_get_category_thumbnail_src($term=null, $size='thumbnail', $crop=1, $ratio=1){
	_deprecated_function(__FUNCTION__, 'WPJAM Basic 3.2', 'wpjam_get_term_thumbnail_url');
	return wpjam_get_term_thumbnail_url($term, $size, $crop, $ratio);
}

function wpjam_get_category_thumbnail_uri($term=null){
	_deprecated_function(__FUNCTION__, 'WPJAM Basic 3.2', 'wpjam_get_term_thumbnail_url');
	return wpjam_get_term_thumbnail_url($term, 'full');
}

/* tag thumbnail */
function wpjam_has_tag_thumbnail(){
	return wpjam_has_term_thumbnail();
}

function wpjam_get_tag_thumbnail_url($term=null, $size='full', $crop=1, $ratio=1){
	return wpjam_get_term_thumbnail_url($term, $size, $crop, $ratio);
}

function wpjam_get_tag_thumbnail($term=null, $size='thumbnail', $crop=1, $class="wp-tag-image", $ratio=2){
	return wpjam_get_term_thumbnail($term, $size, $crop, $class, $ratio);
}

function wpjam_tag_thumbnail($size='thumbnail', $crop=1, $class="wp-tag-image", $ratio=2){
	wpjam_term_thumbnail($size, $crop, $class, $ratio);
}

function wpjam_get_tag_thumbnail_src($term=null, $size='thumbnail', $crop=1, $ratio=1){
	_deprecated_function(__FUNCTION__, 'WPJAM Basic 3.2', 'wpjam_get_term_thumbnail_url');
	return wpjam_get_term_thumbnail_url($term, $size, $crop, $ratio);
}

function wpjam_get_tag_thumbnail_uri($term=null){
	_deprecated_function(__FUNCTION__, 'WPJAM Basic 3.2', 'wpjam_get_term_thumbnail_url');
	return wpjam_get_term_thumbnail_url($term, 'full');
}

function wpjam_get_term_thumbnail_src($term=null, $size='thumbnail', $crop=1, $ratio=1){
	_deprecated_function(__FUNCTION__, 'WPJAM Basic 3.2', 'wpjam_get_term_thumbnail_url');
	return wpjam_get_term_thumbnail_url($term, $size, $crop, $ratio);
}

function wpjam_get_term_thumbnail_uri($term=null){
	_deprecated_function(__FUNCTION__, 'WPJAM Basic 3.2', 'wpjam_get_term_thumbnail_url');
	return wpjam_get_term_thumbnail_url($term, 'full');
}

function wpjam_display_errors(){
	_deprecated_function(__FUNCTION__, 'WPJAM Basic 4.2');
}

// 逐步放弃
function wpjam_get_form_fields($admin_column = false){
	_deprecated_function(__FUNCTION__, 'WPJAM Basic 4.2');
	return [];
}

function wpjam_api_validate_quota($json='', $max_times=1000){
	$today	= date('Y-m-d', current_time('timestamp'));
	$times	= wp_cache_get($json.':'.$today, 'wpjam_api_times');
	$times	= $times ?: 0;

	if($times < $max_times){
		wp_cache_set($json.':'.$today, $times+1, 'wpjam_api_times', DAY_IN_SECONDS);
		return true;
	}else{
		wpjam_send_json(['errcode'=>'api_exceed_quota', 'errmsg'=>'API 调用次数超限']);
	}
}

function wpjam_api_validate_access_token(){
	$result	= WPJAM_Grant::get_instance()->validate_access_token();

	if(is_wp_error($result) && wpjam_is_json_request()){
		wpjam_send_json($result);
	}

	return $result;
}

add_filter('wpjam_html', function($html){
	if(has_filter('wpjam_html_replace')){
		$html	= apply_filters_deprecated('wpjam_html_replace', [$html], 'WPJAM Basic 3.4', 'wpjam_html');
	}

	return $html;
},9);

add_action('wpjam_api', function($json){
	if(has_action('wpjam_api_template_redirect')){
		do_action('wpjam_api_template_redirect', $json);
	}
});

class WPJAM_PlatformBit extends WPJAM_Bit{
	public function __construct($bit){
		$this->set_platform($bit);
	}

	public function set_platform($bit){
		$this->bit	= $bit;
	}

	public function get_platform(){
		return $this->bit;
	}
}

class WPJAM_OPENSSL_Crypt{
	private $key;
	private $method = 'aes-128-cbc';
	private $iv = '';
	private $options = OPENSSL_RAW_DATA;

	public function __construct($key, $args=[]){
		$this->key		= $key;
		$this->method	= $args['method'] ?? $this->method;
		$this->options	= $args['options'] ?? $this->options;
		$this->iv		= $args['iv'] ?? '';
	}

	public function encrypt($text){
		return openssl_encrypt($text, $this->method, $this->key, $this->options, $this->iv);
	}

	public function decrypt($encrypted_text){
		return openssl_decrypt($encrypted_text, $this->method, $this->key, $this->options, $this->iv);
	}
}

function wpjam_create_meta_table($meta_type, $table=''){
	if($meta_type = sanitize_key($meta_type)){
		global $wpdb;

		$table	= $table ?: $wpdb->prefix . $meta_type .'meta';
		$column	= $meta_type . '_id';

		if($wpdb->get_var("show tables like '{$table}'") != $table) {
			$wpdb->query("CREATE TABLE {$table} (
				meta_id bigint(20) unsigned NOT NULL auto_increment,
				{$column} bigint(20) unsigned NOT NULL default '0',
				meta_key varchar(255) default NULL,
				meta_value longtext,
				PRIMARY KEY  (meta_id),
				KEY {$column} ({$column}),
				KEY meta_key (meta_key(191))
			)");
		}
	}
}

// 直接在 handler 里面定义即可。
// 需要在使用的 CLASS 中设置 public static $meta_type
trait WPJAM_Meta_Trait{
	public static function get_meta_type_object(){
		return wpjam_get_meta_type_object(self::$meta_type);
	}

	public static function add_meta($id, $meta_key, $meta_value, $unique=false){
		return self::get_meta_type_object()->add_data($id, $meta_key, $meta_value, $unique);
	}

	public static function delete_meta($id, $meta_key, $meta_value=''){
		return self::get_meta_type_object()->delete_data($id, $meta_key, $meta_value);
	}

	public static function get_meta($id, $key = '', $single = false){
		return self::get_meta_type_object()->get_data($id, $key, $single);
	}

	public static function update_meta($id, $meta_key, $meta_value, $prev_value=''){
		return self::get_meta_type_object()->update_data($id, $meta_key, wp_slash($meta_value), $prev_value);
	}

	public static function delete_meta_by_key($meta_key){
		return self::get_meta_type_object()->delete_by_key($meta_key);
	}

	public static function update_meta_cache($object_ids){
		self::get_meta_type_object()->update_cache($object_ids);
	}

	public static function create_meta_table(){
		self::get_meta_type_object()->create_table();
	}

	public static function get_meta_table(){
		return self::get_meta_type_object()->get_table();
	}
}

function wpjam_stats_header($args=[]){
	global $wpjam_stats_labels;

	$wpjam_stats_labels	= [];

	WPJAM_Chart::init($args);
	WPJAM_Chart::form($args);

	// do_action('wpjam_stats_header');

	foreach(['start_date', 'start_timestamp', 'end_date', 'end_timestamp', 'date', 'timestamp', 'start_date_2', 'start_timestamp_2', 'end_date_2', 'end_timestamp_2', 'date_type', 'date_format', 'compare'] as $key){
		$wpjam_stats_labels['wpjam_'.$key]	= WPJAM_Chart::get_parameter($key);
	}

	$wpjam_stats_labels['compare_label']	= WPJAM_Chart::get_parameter('start_date').' '.WPJAM_Chart::get_parameter('end_date');
	$wpjam_stats_labels['compare_label_2']	= WPJAM_Chart::get_parameter('start_date_2').' '.WPJAM_Chart::get_parameter('end_date_2');
}

function wpjam_sub_summary($tabs){
	?>
	<h2 class="nav-tab-wrapper nav-tab-small">
	<?php foreach ($tabs as $key => $tab) { ?>
		<a class="nav-tab" href="javascript:;" id="tab-title-<?php echo $key;?>"><?php echo $tab['name'];?></a>   
	<?php }?>
	</h2>

	<?php foreach ($tabs as $key => $tab) { ?>
	<div id="tab-<?php echo $key;?>" class="div-tab" style="margin-top:1em;">
	<?php
	global $wpdb;

	$counts = $wpdb->get_results($tab['counts_sql']);
	$total  = $wpdb->get_var($tab['total_sql']);
	$labels = isset($tab['labels'])?$tab['labels']:'';
	$base   = isset($tab['link'])?$tab['link']:'';

	$new_counts = $new_types = array();
	foreach ($counts as $count) {
		$link   = $base?($base.'&'.$key.'='.$count->label):'';

		if(is_super_admin() && $tab['name'] == '手机型号'){
			$label  = ($labels && isset($labels[$count->label]))?$labels[$count->label]:'<span style="color:red;">'.$count->label.'</span>';
		}else{
			$label  = ($labels && isset($labels[$count->label]))?$labels[$count->label]:$count->label;
		}

		$new_counts[] = array(
			'label' => $label,
			'count' => $count->count,
			'link'  => $link
		);
	}

	wpjam_donut_chart($new_counts, array('total'=>$total,'show_line_num'=>1,'table_width'=>'420'));

	?>
	</div>
	<?php }
}

class WPJAM_PostContent extends WPJAM_Content_Items{
	public function __construct($args=[]){
		$post_id	= wpjam_get_data_parameter('post_id');
		parent::__construct($post_id, $args);
	}
}

class WPJAM_MetaItem extends WPJAM_Meta_Items{
	public function __construct($meta_type, $meta_key, $args=[]){
		$object_id	= wpjam_get_data_parameter($meta_type.'_id');
		parent::__construct($meta_type, $object_id, $meta_key, $args);
	}
}


trait WPJAM_Qrcode_Bind_Trait{
	public function verify_qrcode($scene, $code){
		if(empty($code)){
			return new WP_Error('invalid_code', '验证码不能为空');
		}

		$qrcode	= $this->get_qrcode($scene);

		if(is_wp_error($qrcode)){
			return $qrcode;
		}

		if(empty($qrcode['openid'])){
			return new WP_Error('invalid_code', '请先扫描二维码！');
		}

		if($code != $qrcode['code']){
			return new WP_Error('invalid_code', '验证码错误！');
		}

		$this->cache_delete($scene.'_scene');

		return $qrcode;
	}

	public function scan_qrcode($openid, $scene){
		$qrcode = $this->get_qrcode($scene);

		if(is_wp_error($qrcode)){
			return $qrcode;
		}

		if(!empty($qrcode['openid']) && $qrcode['openid'] != $openid){
			return new WP_Error('qrcode_scaned', '已有用户扫描该二维码！');
		}

		$this->cache_delete($qrcode['key'].'_qrcode');

		if(!empty($qrcode['id']) && !empty($qrcode['bind_callback']) && is_callable($qrcode['bind_callback'])){
			return call_user_func($qrcode['bind_callback'], $openid, $qrcode['id']);
		}else{
			$this->cache_set($scene.'_scene', array_merge($qrcode, ['openid'=>$openid]), 1200);

			return $qrcode['code'];
		}
	}

	public function get_qrcode($scene){
		if(empty($scene)){
			return new WP_Error('invalid_scene', '场景值不能为空');
		}

		$qrcode	= $this->cache_get($scene.'_scene');

		return $qrcode ?: new WP_Error('invalid_scene', '二维码无效或已过期，请刷新页面再来验证！');
	}
}