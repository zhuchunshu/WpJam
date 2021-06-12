<?php
class WPJAM_Post{
	private $post_id;
	private $excerpt		= null;
	private $content_images	= null;
	private $thumbnail_url	= null;
	private $views			= null;
	private $viewd			= false;

	private static $instances	= [];

	private function __construct($post_id){
		$this->post_id	= (int)$post_id;
	}

	public function __get($name){
		if(in_array($name, ['id', 'post_id'])){
			return $this->post_id;
		}elseif($name == 'post'){
			return get_post($this->post_id);
		}else{
			return $this->post->$name;
		}
	}

	private function supports($feature){
		return post_type_supports($this->post_type, $feature); 
	}

	private function is_singular(){
		return is_singular($this->post_type);
	}

	private function get_taxonomies(){
		return get_object_taxonomies($this->post_type);
	}

	public function get_excerpt($excerpt_length=0, $excerpt_more=''){
		if($excerpt = $this->post_excerpt){
			return wp_strip_all_tags($excerpt, true);
		}

		if(is_null($this->excerpt)){
			$excerpt	= $this->get_content(true);
			$excerpt	= strip_shortcodes($excerpt);
			$excerpt	= excerpt_remove_blocks($excerpt);

			remove_filter('the_content', 'wp_filter_content_tags');

			$excerpt	= apply_filters('the_content', $excerpt);
			$excerpt	= str_replace(']]>', ']]&gt;', $excerpt);

			add_filter('the_content', 'wp_filter_content_tags');

			$this->excerpt	= wp_strip_all_tags($excerpt, true);
		}

		$excerpt_length	= $excerpt_length ?: apply_filters('excerpt_length', 200);
		$excerpt_more	= $excerpt_more ?: apply_filters('excerpt_more', ' '.'&hellip;');

		return mb_strimwidth($this->excerpt, 0, $excerpt_length, $excerpt_more, 'utf-8');
	}

	public function get_content($raw=false){
		$content	= get_the_content('', false, $this->post);

		if(!$raw){
			$content	= apply_filters('the_content', $content);
			$content	= str_replace(']]>', ']]&gt;', $content);
		}

		return $content;
	}

	public function get_thumbnail_url($size='thumbnail', $crop=1){
		if(is_null($this->thumbnail_url)){
			if($this->supports('thumbnail') && get_post_thumbnail_id($this->post_id)){
				$this->thumbnail_url	= wp_get_attachment_image_url(get_post_thumbnail_id($this->post_id), 'full');
			}else{
				$this->thumbnail_url	= apply_filters('wpjam_post_thumbnail_url', '', $this->post);
			}
		}

		if($this->thumbnail_url && empty($size)){
			$pt_obj	= get_post_type_object($this->post_type);
			$size	= !empty($pt_obj->thumbnail_size) ? $pt_obj->thumbnail_size : 'thumbnail';
		}

		return $this->thumbnail_url ? wpjam_get_thumbnail($this->thumbnail_url, $size, $crop) : '';
	}

	public function get_first_image_url($size='full'){
		if($content	= $this->post_content){
			preg_match_all('/class=[\'"].*?wp-image-([\d]*)[\'"]/i', $content, $matches);

			if($matches && isset($matches[1]) && isset($matches[1][0])){
				return wp_get_attachment_image_url($matches[1][0], $size);
			}

			preg_match_all('/<img.*?src=[\'"](.*?)[\'"].*?>/i', $content, $matches);

			if($matches && isset($matches[1]) && isset($matches[1][0])){	  
				return wpjam_get_thumbnail($matches[1][0], $size);
			}
		}

		return '';
	}

	public function get_author($size=200){
		$author_id	= $this->post_author;
		$wpjam_user	= WPJAM_User::get_instance($author_id);

		return ($wpjam_user && !is_wp_error($wpjam_user)) ? $wpjam_user->parse_for_json() : null;
	}

	public function get_views($addon=false){
		if(is_null($this->views)){
			$this->views	= (int)get_post_meta($this->post_id, 'views', true);
		}

		$addon	= $addon ? (int)apply_filters('wpjam_post_views_addon', 0, $this->post_id) : 0;

		return $this->views + $addon;
	}

	public function get_related_query($number=5, $post_type=null){
		$term_taxonomy_ids = [];

		if($taxonomies = $this->get_taxonomies()){
			foreach ($taxonomies as $taxonomy) {
				if($terms = get_the_terms($this->post_id, $taxonomy)){
					$term_taxonomy_ids = array_merge($term_taxonomy_ids, array_column($terms, 'term_taxonomy_id'));
				}
			}

			$term_taxonomy_ids	= array_unique(array_filter($term_taxonomy_ids));
		}

		return new WP_Query([
			'cache_it'				=> true,
			'no_found_rows'			=> true,
			'ignore_sticky_posts'	=> true,
			'cache_results'			=> true,
			'related_query'			=> true,
			'post_status'			=> 'publish',
			'post_type'				=> $post_type ?: $this->post_type,
			'posts_per_page'		=> $number ?: 5,
			'post__not_in'			=> [$this->post_id],
			'term_taxonomy_ids'		=> $term_taxonomy_ids
		]);
	}

	public function view(){
		if($this->viewd){	// 确保只加一次
			return true;
		}

		$this->viewd	= true;
		$this->views	= $this->get_views() + 1;

		return update_post_meta($this->post_id, 'views', $this->views);
	}

	public function parse_for_json($args=[]){
		$args	= wp_parse_args($args, [
			'list_query'		=> false,
			'content_required'	=> false,
			'raw_content'		=> false,
			'sticky_posts'		=> []
		]);

		$GLOBALS['post']	= $this->post;

		setup_postdata($this->post);

		$post_json	= [];

		$post_json['id']		= $this->post_id;
		$post_json['post_type']	= $this->post_type;
		$post_json['status']	= $this->post_status;

		if($args['sticky_posts'] && is_array($args['sticky_posts']) && in_array($this->post_id, $args['sticky_posts'])){
			$post_json['is_sticky']	= true;
		}

		if($this->post_password){
			$post_json['password_protected']	= true;
			$post_json['password_required']		= post_password_required($this->post);
		}

		$post_json['timestamp']			= (int)strtotime(get_gmt_from_date($this->post_date));
		$post_json['time']				= wpjam_human_time_diff($post_json['timestamp']);
		$post_json['date']				= wp_date('Y-m-d', $post_json['timestamp']);
		$post_json['modified_timestamp']= (int)strtotime($this->post_modified_gmt);
		$post_json['modified_time']		= wpjam_human_time_diff($post_json['modified_timestamp']);
		$post_json['modified_date']		= wp_date('Y-m-d', $post_json['modified_timestamp']);

		if(is_post_type_viewable($this->post_type)){
			$post_json['name']		= urldecode($this->post_name);
			$post_json['post_url']	= str_replace(home_url(), '', get_permalink($this->post_id));
		}

		$post_json['title']		= '';
		if($this->supports('title')){
			$post_json['title']		= html_entity_decode(get_the_title($this->post));

			if($this->is_singular() && !$args['list_query']){
				$post_json['page_title']	= $post_json['title'];
				$post_json['share_title']	= $post_json['title'];
			}
		}

		if(isset($args['thumbnail_size'])){
			$thumbnail_size	= $args['thumbnail_size'];
		}elseif(isset($args['size'])){
			$thumbnail_size	= $args['size'];
		}else{
			$thumbnail_size	= '';
		}

		$post_json['thumbnail']		= $this->get_thumbnail_url($thumbnail_size);

		if($this->supports('author')){
			$post_json['author']	= $this->get_author();
		}

		if($this->supports('excerpt')){
			$post_json['excerpt']	= html_entity_decode(get_the_excerpt($this->post));
		}

		if($this->supports('page-attributes')){
			$post_json['menu_order']	= (int)$this->menu_order;
		}

		if($this->supports('post-formats')){
			$post_json['format']	= get_post_format($this->post) ?: '';
		}

		$post_json['views']	= $this->get_views();

		if($args['list_query']){
			return $post_json;
		}

		if($taxonomies = $this->get_taxonomies()){
			foreach ($taxonomies as $taxonomy) {
				if($taxonomy != 'post_format'){
					if($terms	= get_the_terms($this->post_id, $taxonomy)){
						array_walk($terms, function(&$term) use ($taxonomy){ $term 	= wpjam_get_term($term, $taxonomy);});
						$post_json[$taxonomy]	= $terms;
					}else{
						$post_json[$taxonomy]	= [];
					}
				}
			}
		}

		if($this->is_singular() || $args['content_required']){
			if($this->supports('editor')){
				if($args['raw_content']){
					$post_json['raw_content']	= $this->get_content(true);
				}

				$post_json['content']	= $this->get_content();

				global $page, $numpages, $multipage;

				$post_json['multipage']	= (bool)$multipage;

				if($multipage){
					$post_json['numpages']	= $numpages;
					$post_json['page']		= $page;
				}
			}

			if($this->is_singular()){
				$this->view();
			}
		}

		return apply_filters('wpjam_post_json', $post_json, $this->post_id, $args);
	}

	public static function get_instance($post=null){
		$post	= self::get_post($post);

		if(!($post instanceof WP_Post)){
			return new WP_Error('post_not_exists', '文章不存在');
		}

		if(!post_type_exists($post->post_type)){
			return new WP_Error('post_type_not_exists', '文章类型不存在');
		}

		$post_id	= $post->ID;

		if(!isset($instances[$post_id])){
			$instances[$post_id]	= new self($post_id);
		}

		return $instances[$post_id];
	}

	public static function parse_query($wp_query, $args=[]){
		$posts_json	= [];

		if($wp_query->have_posts()){
			$filter	= $args['filter'] ?? 'wpjam_related_post_json';
			$args	= array_merge($args, ['list_query'=>true]);

			while($wp_query->have_posts()){
				$wp_query->the_post();

				$post_id		= get_the_ID();
				$post_json		= self::get_instance($post_id)->parse_for_json($args);
				$posts_json[]	= apply_filters($filter, $post_json, $post_id, $args);
			}
		}

		wp_reset_postdata();

		return $posts_json;
	}

	public static function render_query($wp_query, $args=[]){
		$output = '';

		if($wp_query->have_posts()){
			if(!empty($args['item_callback']) && is_callable($args['item_callback'])){
				$item_callback	= $args['item_callback'];
			}else{
				$item_callback	= ['WPJAM_Post', 'list_item_callback'];
			}

			while($wp_query->have_posts()){
				$wp_query->the_post();

				$output .= call_user_func($item_callback, get_the_ID(), $args);
			}
		}

		wp_reset_postdata();

		$wrap_callback	= $args['wrap_callback'] ?? ['WPJAM_Post', 'list_wrap_callback'];

		if(!empty($wrap_callback) && is_callable($wrap_callback)){
			$output	= call_user_func($wrap_callback, $output, $args); 
		}

		return $output;
	}

	public static function list_item_callback($post_id, $args){
		$args	= wp_parse_args($args, [
			'thumb'			=> true,
			'excerpt'		=> false,
			'crop'			=> true,
			'size'			=> 'thumbnail',
			'thumb_class'	=> 'wp-post-image',
		]);

		$li = get_the_title();

		if($args['thumb'] || $args['excerpt']){
			$li = '<h4>'.$li.'</h4>';

			if($args['thumb']){
				$li = get_the_post_thumbnail(null, $args['size'], ['class'=>$args['thumb_class']])."\n".$li;
			}

			if($args['excerpt']){
				$li .= "\n".wpautop(get_the_excerpt());
			}
		}

		if(!is_singular() || (is_singular() && get_queried_object_id() != $post_id)){
			$li = '<a href="'.get_permalink().'" title="'.the_title_attribute(['echo'=>false]).'">'.$li.'</a>';
		}

		return '<li>'.$li.'</li>'."\n";
	}

	public static function list_wrap_callback($output, $args){
		$args	= wp_parse_args($args, [
			'title'		=> '',
			'div_id'	=> '',
			'class'		=> '',
			'thumb'		=> true,
		]);

		if($args['thumb']){
			$args['class']	= $args['class'].' has-thumb';
		}

		$class	= $args['class'] ? ' class="'.$args['class'].'"' : '';
		$output = '<ul'.$class.'>'."\n".$output.'</ul>'."\n";

		if($args['title']){
			$output	= '<h3>'.$args['title'].'</h3>'."\n".$output;
		}

		if($args['div_id']){
			$output	= '<div id="'.$args['div_id'].'">'."\n".$output.'</div>'."\n";
		}

		return $output;
	}

	public static function get($post){
		return self::get_post($post, ARRAY_A);
	}

	public static function insert($data, $fire_after_hooks=true){
		$data['post_status']	= $data['post_status']	?? 'publish';
		$data['post_author']	= $data['post_author']	?? get_current_user_id();
		$data['post_date']		= $data['post_date']	?? get_date_from_gmt(date('Y-m-d H:i:s', time()));

		return wp_insert_post(wp_slash($data), true, $fire_after_hooks);
	}

	public static function update($post_id, $data, $fire_after_hooks=true){
		$data['ID'] = $post_id;

		return wp_update_post(wp_slash($data), true, $fire_after_hooks);
	}

	public static function delete($post_id, $force_delete=true){
		if(!get_post($post_id)){
			return new WP_Error('post_not_exists', '文章不存在');
		}

		$result		= wp_delete_post($post_id, $force_delete);

		return $result ? true : new WP_Error('delete_failed', '删除失败');
	}

	public static function update_meta($post_id, $meta_key, $meta_value){
		if($meta_value){
			return update_post_meta($post_id, $meta_key, wp_slash($meta_value));
		}else{
			return delete_post_meta($post_id, $meta_key);
		}
	}

	public static function update_metas($post_id, $data){
		foreach($data as $meta_key => $meta_value){
			self::update_meta($post_id, $meta_key, $meta_value);
		}

		return true;
	}

	public static function value_callback($meta_key, $post_id){
		if($post_id && metadata_exists('post', $post_id, $meta_key)){
			return get_post_meta($post_id, $meta_key, true);
		}

		return null;
	}

	public static function duplicate($post_id){
		$post_arr	= get_post($post_id, ARRAY_A);
		$post_arr	= wpjam_array_except($post_arr, ['ID', 'post_date_gmt', 'post_modified_gmt', 'post_name']);

		$post_arr['post_status']	= 'draft';
		$post_arr['post_author']	= get_current_user_id();
		$post_arr['post_date_gmt']	= $post_arr['post_modified_gmt']	= date('Y-m-d H:i:s', time());
		$post_arr['post_date']		= $post_arr['post_modified']		= get_date_from_gmt($post_arr['post_date_gmt']);

		$tax_input	= [];

		foreach(get_object_taxonomies($post_arr['post_type']) as $taxonomy){
			$tax_input[$taxonomy]	= wp_get_object_terms($post_id, $taxonomy, ['fields' => 'ids']);
		}

		$post_arr['tax_input']	= $tax_input;

		$new_post_id	= wp_insert_post(wp_slash($post_arr), true);

		if(is_wp_error($new_post_id)){
			return $new_post_id;
		}

		$meta_keys	= get_post_custom_keys($post_id);

		foreach ($meta_keys as $meta_key) {
			if(in_array($meta_key, ['views', 'likes', 'favs']) || is_protected_meta($meta_key, 'post')){
				continue;
			}

			$meta_values	= get_post_meta($post_id, $meta_key);
			foreach ($meta_values as $meta_value){
				add_post_meta($new_post_id, $meta_key, $meta_value, false);
			}
		}

		return $new_post_id;
	}

	public static function get_by_ids($post_ids, $args=[]){
		return self::update_caches($post_ids, $args);
	}

	public static function update_caches($post_ids, $args=[]){
		if($post_ids){
			$post_ids 	= array_filter($post_ids);
			$post_ids 	= array_unique($post_ids);
		}

		if(empty($post_ids)) {
			return [];
		}

		$update_term_cache	= $args['update_post_term_cache'] ?? true;
		$update_meta_cache	= $args['update_post_meta_cache'] ?? true;

		_prime_post_caches($post_ids, $update_term_cache, $update_meta_cache);

		if(function_exists('wp_cache_get_multiple')){
			$cache_values	= wp_cache_get_multiple($post_ids, 'posts');

			foreach ($post_ids as $post_id) {
				if(empty($cache_values[$post_id])){
					wp_cache_add($post_id, false, 'posts', 10);	// 防止大量 SQL 查询。
				}
			}

			return $cache_values;
		}else{
			$cache_values	= [];

			foreach ($post_ids as $post_id) {
				$cache	= wp_cache_get($post_id, 'posts');

				if($cache !== false){
					$cache_values[$post_id]	= $cache;
				}
			}

			return $cache_values;
		}
	}

	public static function get_post($post, $output=OBJECT, $filter='raw'){
		if($post && is_numeric($post)){	// 不存在情况下的缓存优化
			$found	= false;
			$cache	= wp_cache_get($post, 'posts', false, $found);

			if($found){
				if(is_wp_error($cache)){
					return $cache;
				}elseif(!$cache){
					return null;
				}
			}else{
				$_post	= WP_Post::get_instance($post);

				if(!$_post){	// 防止重复 SQL 查询。
					wp_cache_add($post, false, 'posts', 10);
					return null;
				}
			}
		}

		return get_post($post, $output, $filter);
	}

	public static function validate($post_id, $post_type=''){
		$instance	= self::get_instance($post_id);

		if(is_wp_error($instance)){
			return $instance;
		}

		if($post_type && $post_type != 'any' && $post_type != $instance->post_type){
			return new WP_Error('invalid_post_type', '无效的文章类型');
		}

		return self::get_post($post_id);
	}

	public static function update_views($post=null){
		_deprecated_function(__METHOD__, 'WPJAM Basic 5.0', 'wpjam_update_post_views');

		return wpjam_update_post_views($post);
	}

	public static function get_related($post=null, $args=[]){
		_deprecated_function(__METHOD__, 'WPJAM Basic 5.0', 'wpjam_get_related_posts');

		return wpjam_get_related_posts($post, $args);
	}

	public static function get_list($wp_query, $args=[]){
		// _deprecated_function(__METHOD__, 'WPJAM Basic 5.0');
		$parse_for_json	= $args['parse_for_json'] ?? true;

		if($parse_for_json){
			return self::parse_query($wp_query, $args);
		}else{
			return self::render_query($wp_query, $args);
		}
	}

	public static function get_id_field($post_type, $args=[]){
		if($pt_obj = get_post_type_object($post_type)){
			$args	= wp_parse_args($args, [
				'title'		=> $pt_obj->label, 
				'name'		=> '',
				'required'	=> false, 
			]);

			$field	= [
				'title'			=> $args['title'],
				'type'			=> 'text',	
				'class'			=> 'all-options',	
				'data_type'		=> 'post_type',	
				'post_type'		=> $post_type,
				'placeholder'	=> '请输入'.$pt_obj->label.'ID或者输入关键字筛选'
			];

			if($args['name']){
				$field['name']	= $args['name'];
			}

			if($args['required']){
				$field['required']	= 'required';
			}

			return $field;
		}

		return [];
	}
}

class WPJAM_Post_Type{
	use WPJAM_Register_Trait;

	public static function on_registered($name, $pt_obj){
		if(!empty($pt_obj->permastruct)){
			$permastruct	= $pt_obj->permastruct;

			if(strpos($permastruct, '%'.$name.'_id%')){
				$GLOBALS['wp_rewrite']->extra_permastructs[$name]['struct']	= $permastruct;

				add_rewrite_tag('%'.$name.'_id%', '([0-9]+)', 'post_type='.$name.'&p=');

				remove_rewrite_tag('%'.$name.'%');
			}elseif(strpos($permastruct, '%postname%')){
				$GLOBALS['wp_rewrite']->extra_permastructs[$name]['struct'] = $permastruct;
			}
		}

		$registered_callback	= $pt_obj->registered_callback ?? '';

		if($registered_callback && is_callable($registered_callback)){
			call_user_func($registered_callback, $name, $pt_obj);
		}
	}

	public static function filter_labels($labels){
		$name		= str_replace('post_type_labels_', '', current_filter());
		$args		= self::get($name)->to_array();
		$_labels	= $args['labels'] ?? [];

		$labels		= (array)$labels;
		$name		= $labels['name'];

		$search		= empty($args['hierarchical']) ? ['文章', 'post', 'Post', '撰写新', '写'] : ['页面', 'page', 'Page', '撰写新', '写'];
		$replace	= [$name, $name, ucfirst($name), '新建', '新建'];

		foreach ($labels as $key => &$label) {
			if($label && empty($_labels[$key])){
				if($key == 'all_items'){
					$label	= '所有'.$name;
				}elseif($label != $name){
					$label	= str_replace($search, $replace, $label);
				}
			}
		}

		return $labels;
	}

	public static function filter_link($post_link, $post){
		$name	= $post->post_type;

		if(array_search('%'.$name.'_id%', $GLOBALS['wp_rewrite']->rewritecode, true)){
			$post_link	= str_replace('%'.$name.'_id%', $post->ID, $post_link);
		}

		if(strpos($post_link, '%') !== false && ($taxonomies = get_object_taxonomies($name, 'objects'))){
			foreach ($taxonomies as $taxonomy=>$taxonomy_object) {
				if($taxonomy_rewrite = $taxonomy_object->rewrite){

					if(strpos($post_link, '%'.$taxonomy_rewrite['slug'].'%') === false){
						continue;
					}

					if($terms = get_the_terms($post->ID, $taxonomy)){
						$post_link	= str_replace('%'.$taxonomy_rewrite['slug'].'%', current($terms)->slug, $post_link);
					}else{
						$post_link	= str_replace('%'.$taxonomy_rewrite['slug'].'%', $taxonomy, $post_link);
					}
				}
			}
		}

		return $post_link;
	}

	public static function filter_clauses($clauses, $wp_query){
		global $wpdb;

		if($wp_query->get('related_query')){
			if($term_taxonomy_ids	= $wp_query->get('term_taxonomy_ids')){
				$clauses['fields']	.= ", count(tr.object_id) as cnt";
				$clauses['join']	.= "INNER JOIN {$wpdb->term_relationships} AS tr ON {$wpdb->posts}.ID = tr.object_id";
				$clauses['where']	.= " AND tr.term_taxonomy_id IN (".implode(",",$term_taxonomy_ids).")";
				$clauses['groupby']	.= " tr.object_id";
				$clauses['orderby']	= " cnt DESC, {$wpdb->posts}.post_date_gmt DESC";
			}
		}else{
			$orderby	= $wp_query->get('orderby');
			$order		= $wp_query->get('order') ?: 'DESC';

			if(in_array($orderby, ['views', 'favs', 'likes'])){
				$clauses['fields']	.= ", (COALESCE(jam_pm.meta_value, 0)+0) as {$orderby}";
				$clauses['join']	.= "LEFT JOIN {$wpdb->postmeta} jam_pm ON {$wpdb->posts}.ID = jam_pm.post_id AND jam_pm.meta_key = '{$orderby}' ";
				$clauses['orderby']	= "{$orderby} {$order}, " . $clauses['orderby'];
			}elseif($orderby == '' || $orderby == 'date' || $orderby == 'post_date'){
				$clauses['orderby']	.= ", {$wpdb->posts}.ID {$order}";
			}
		}

		return $clauses;
	}
}

class WPJAM_Post_Option{
	use WPJAM_Register_Trait;

	public function is_available_for_post_type($post_type){
		return is_null($this->post_type) || in_array($post_type, (array)$this->post_type);
	}

	public function get_fields($post_id=null){
		if(is_callable($this->fields)){
			return call_user_func($this->fields, $post_id, $this->name);
		}else{
			return $this->fields;
		}
	}

	public static function register($name, $args){
		$args	= wp_parse_args($args, ['fields'=>[],	'list_table'=>0]);

		if(!empty($args['post_type'])){
			$args['post_type']	= (array)$args['post_type'];
		}elseif(!empty($args['post_types']) && is_array($args['post_types'])){
			$args['post_type']	= $args['post_types'];
		}

		self::register_instance($name, new self($name, $args));
	}
}