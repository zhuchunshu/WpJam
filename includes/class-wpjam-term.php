<?php
class WPJAM_Term{
	private $term_id;
	private $thumbnail_url	= null;

	private function __construct($term_id){
		$this->term_id	= (int)$term_id;
	}

	public function __get($name){
		if(in_array($name, ['id', 'term_id'])){
			return $this->term_id;
		}elseif($name == 'term'){
			return get_term($this->term_id);
		}else{
			return $this->term->$name;
		}
	}

	public function get_thumbnail_url($size='full', $crop=1){
		if(is_null($this->thumbnail_url)){
			$this->thumbnail_url	= apply_filters('wpjam_term_thumbnail_url', '', get_term($this->term_id));
		}

		return $this->thumbnail_url ? wpjam_get_thumbnail($this->thumbnail_url, $size, $crop) : '';
	}

	public function get_children($children_terms=null, $max_depth=-1, $depth=0){
		$children	= [];

		if(is_null($children_terms)){
			// 以后实现
		}

		if($children_terms && isset($children_terms[$this->term_id]) && ($max_depth == 0 || $max_depth > $depth+1)){
			foreach($children_terms[$this->term_id] as $child){
				$children[]	= self::get_instance($child)->parse_for_json($children_terms, $max_depth, $depth+1);
			}
		}

		return $children;
	}

	public function parse_for_json($children_terms=null, $max_depth=-1, $depth=0){
		$term_json	= [];

		$term_json['id']		= $this->term_id;
		$term_json['taxonomy']	= $this->taxonomy;
		$term_json['name']		= html_entity_decode($this->name);

		if(get_queried_object_id() == $this->term_id){
			$term_json['page_title']	= $term_json['name'];
			$term_json['share_title']	= $term_json['name'];
		}

		$tax_obj	= get_taxonomy($this->taxonomy);

		if($tax_obj->public || $tax_obj->publicly_queryable || $tax_obj->query_var){
			$term_json['slug']		= $this->slug;
		}

		$term_json['count']			= (int)$this->count;
		$term_json['description']	= $this->description;
		$term_json['parent']		= $this->parent;

		if($max_depth != -1){
			$term_json['children']	= $this->get_children($children_terms, $max_depth, $depth);
		}

		return apply_filters('wpjam_term_json', $term_json, $this->term_id);
	}

	private static $instances	= [];
	
	public static function get_instance($term=null){
		$term	= $term ?: get_queried_object();
		$term	= self::get_term($term);

		if(!($term instanceof WP_Term)){
			return new WP_Error('term_not_exists', '分类不存在');
		}

		if(!taxonomy_exists($term->taxonomy)){
			return new WP_Error('taxonomy_not_exists', '自定义分类不存在');
		}

		$term_id	= $term->term_id;

		if(!isset($instances[$term_id])){
			$instances[$term_id]	= new self($term_id);
		}

		return $instances[$term_id];
	}

	/**
	* $max_depth = -1 means flatly display every element.
	* $max_depth = 0 means display all levels.
	* $max_depth > 0 specifies the number of display levels.
	*
	*/
	public static function get_terms($args, $max_depth=-1){
		$raw_args	= $args;
		$parent		= wpjam_array_pull($args, 'parent');

		$args['update_term_meta_cache']	= false;

		$terms	= get_terms($args) ?: [];

		if(is_wp_error($terms) || empty($terms)){
			return $terms;
		}

		$lazyloader	= wp_metadata_lazyloader();
		$lazyloader->queue_objects('term', wp_list_pluck($terms, 'term_id'));

		if($max_depth == -1){
			foreach ($terms as &$term) {
				$term	= self::get_instance($term)->parse_for_json();
			}
		}else{
			$top_level_terms	= [];
			$children_terms		= [];

			foreach($terms as $term){
				if($parent){
					if($term->term_id == $parent){
						$top_level_terms[] = $term;
					}elseif($term->parent && $max_depth != 1){
						$children_terms[$term->parent][] = $term;
					}
				}else{
					if(empty($term->parent)){
						$top_level_terms[] = $term;
					}elseif($max_depth != 1){
						$children_terms[$term->parent][] = $term;
					}
				}
			}

			$terms	= $top_level_terms;

			foreach($terms as &$term){
				$term	= self::get_instance($term)->parse_for_json($children_terms, $max_depth, 0);
			}
		}

		return apply_filters('wpjam_terms', $terms, $raw_args, $max_depth);
	}

	public static function flatten($terms, $depth=0){
		$terms_flat	= [];

		foreach($terms as $term){
			$term['name']	= str_repeat('&nbsp;', $depth*3).$term['name'];
			$terms_flat[]	= $term;

			if(!empty($term['children'])){
				$terms_flat	= array_merge($terms_flat, self::flatten($term['children'], $depth+1));
			}
		}

		return $terms_flat;
	}

	public static function get($term){
		return self::get_term($term, '', ARRAY_A);
	}

	public static function insert($data){
		$taxonomy	= $data['taxonomy'] ?? '';

		if(empty($taxonomy)){
			return new WP_Error('empty_taxonomy', '分类模式不能为空');
		}

		$name			= $data['name']			?? '';
		$parent			= $data['parent']		?? 0;
		$slug			= $data['slug']			?? '';
		$description	= $data['description']	?? '';

		if(term_exists($name, $taxonomy)){
			return new WP_Error('term_exists', '相同名称的'.get_taxonomy($taxonomy)->label.'已存在。');
		}

		$term	= wp_insert_term(wp_slash($name), $taxonomy, wp_slash(compact('parent','slug','description')));

		if(is_wp_error($term)){
			return $term;
		}

		$term_id	= $term['term_id'];

		$meta_input	= $data['meta_input']	?? [];

		if($meta_input){
			foreach($meta_input as $meta_key => $meta_value) {
				update_term_meta($term_id, $meta_key, $meta_value);
			}
		}

		return $term_id;
	}

	public static function update($term_id, $data){
		$taxonomy		= $data['taxonomy']	?? '';

		if(empty($taxonomy)){
			return new WP_Error('empty_taxonomy', '分类模式不能为空');
		}

		$term	= self::get_term($term_id, $taxonomy);

		if(is_wp_error($term)){
			return $term;
		}

		if(isset($data['name'])){
			$exist	= term_exists($data['name'], $taxonomy);

			if($exist){
				$exist_term_id	= $exist['term_id'];

				if($exist_term_id != $term_id){
					return new WP_Error('term_name_duplicate', '相同名称的'.get_taxonomy($taxonomy)->label.'已存在。');
				}
			}
		}

		$term_args = [];

		$term_keys = ['name', 'parent', 'slug', 'description'];

		foreach($term_keys as $key) {
			$value = $data[$key] ?? null;
			if (is_null($value)) {
				continue;
			}

			$term_args[$key] = $value;
		}

		if(!empty($term_args)){
			$term =	wp_update_term($term_id, $taxonomy, wp_slash($term_args));
			if(is_wp_error($term)){
				return $term;
			}
		}

		$meta_input		= $data['meta_input']	?? [];

		if($meta_input){
			foreach($meta_input as $meta_key => $meta_value) {
				update_term_meta($term['term_id'], $meta_key, $meta_value);
			}
		}

		return true;
	}

	public static function delete($term_id){
		$term	= get_term($term_id);

		if(is_wp_error($term) || empty($term)){
			return $term;
		}

		return wp_delete_term($term_id, $term->taxonomy);
	}

	public static function update_meta($term_id, $meta_key, $meta_value){
		if($meta_value){
			return update_term_meta($term_id, $meta_key, wp_slash($meta_value));
		}else{
			return delete_term_meta($term_id, $meta_key);
		}
	}

	public static function value_callback($meta_key, $term_id){
		if($term_id && metadata_exists('term', $term_id, $meta_key)){
			return get_term_meta($term_id, $meta_key, true);
		}

		return null;
	}

	public static function get_by_ids($term_ids){
		return self::update_caches($term_ids);
	}

	public static function update_caches($term_ids, $args=[]){
		if($term_ids){
			$term_ids 	= array_filter($term_ids);
			$term_ids 	= array_unique($term_ids);
		}

		if(empty($term_ids)) {
			return [];
		}

		$update_meta_cache	= $args['update_meta_cache'] ?? true;

		_prime_term_caches($term_ids, $update_meta_cache);

		if(function_exists('wp_cache_get_multiple')){
			$cache_values	= wp_cache_get_multiple($term_ids, 'terms');

			foreach ($term_ids as $term_id) {
				if(empty($cache_values[$term_id])){
					wp_cache_add($term_id, false, 'terms', 10);	// 防止大量 SQL 查询。
				}
			}

			return $cache_values;
		}else{
			$cache_values	= [];

			foreach ($term_ids as $term_id) {
				$cache	= wp_cache_get($term_id, 'terms');

				if($cache !== false){
					$cache_values[$term_id]	= $cache;
				}
			}

			return $cache_values;
		}
	}

	public static function get_term($term, $taxonomy='', $output=OBJECT, $filter='raw'){
		if($term && is_numeric($term)){	// 不存在情况下的缓存优化
			$found	= false;
			$cache	= wp_cache_get($term, 'terms', false, $found);

			if($found){
				if(is_wp_error($cache)){
					return $cache;
				}elseif(!$cache){
					return null;
				}
			}else{
				$_term	= WP_Term::get_instance($term, $taxonomy);

				if(is_wp_error($_term)){
					return $_term;
				}elseif(!$_term){	// 防止重复 SQL 查询。
					wp_cache_add($term, false, 'terms', 10);
					return null;
				}
			}
		}

		return get_term($term, $taxonomy, $output, $filter);
	}

	public static function validate($term_id, $taxonomy=''){
		$instance	= self::get_instance($term_id);

		if(is_wp_error($instance)){
			return $instance;
		}

		if($taxonomy && $taxonomy != 'any' && $taxonomy != $instance->taxonomy){
			return new WP_Error('invalid_taxonomy', '无效的分类模式');
		}

		return $instance->term;
	}

	public static function get_id_field($taxonomy, $args=[]){
		if($tax_obj	= get_taxonomy($taxonomy)){
			$args	= wp_parse_args($args, [
				'title'			=> $tax_obj->label, 
				'name'			=> '', 
				'required'		=> false,
				'option_all'	=> false
			]);

			if($tax_obj->hierarchical){
				$levels		= $tax_obj->levels ?? 0;
				$terms		= self::get_terms(['taxonomy'=>$taxonomy, 'hide_empty'=>0], $levels);
				$terms		= self::flatten($terms);
				$options	= $terms ? wp_list_pluck($terms, 'name', 'id') : [];

				if($args['option_all'] !== false){
					$option_all	= $args['option_all'] === true ? '所有'.$tax_obj->label :  $args['option_all'];
					$options	= [''=>$option_all]+$options;
				}

				$field	= [
					'title'			=> $args['title'],
					'type'			=> 'select',
					'options'		=> $options
				];
			}else{
				$field = [
					'title'			=> $args['title'],
					'type'			=> 'text',
					'class'			=> 'all-options',
					'data_type'		=> 'taxonomy',
					'taxonomy'		=> $taxonomy,
					'placeholder'	=> '请输入'.$tax_obj->label.'ID或者输入关键字筛选'
				];
			}

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

class WPJAM_Taxonomy{
	use WPJAM_Register_Trait;

	public static function filter_register_args($args, $name){
		$args = wp_parse_args($args, [
			'supports'		=> ['slug', 'description', 'parent'],
			'permastruct'	=> null,
			'levels'		=> null,
			'sortable'		=> null,
			'filterable'	=> null,
		]);

		if($args['permastruct']){
			if(strpos($args['permastruct'], '%term_id%') || strpos($args['permastruct'], '%'.$name.'_id%')){
				$args['permastruct']	= str_replace('%term_id%', '%'.$name.'_id%', $args['permastruct']);
				$args['supports']		= array_diff($args['supports'], ['slug']);
				$args['query_var']		= $args['query_var'] ?? false;
			}
		}

		return $args;
	}

	public static function on_registered($name, $object_type, $args){
		if(!empty($args['permastruct'])){
			if(strpos($args['permastruct'], '%'.$name.'_id%')){
				$GLOBALS['wp_rewrite']->extra_permastructs[$name]['struct']	= $args['permastruct'];

				add_rewrite_tag('%'.$name.'_id%', '([^/]+)', 'taxonomy='.$name.'&term_id=');
				remove_rewrite_tag('%'.$name.'%');
			}elseif(strpos($args['permastruct'], '%'.$args['rewrite']['slug'].'%')){
				$GLOBALS['wp_rewrite']->extra_permastructs[$name]['struct']	= $args['permastruct'];
			}
		}

		$registered_callback	= $args['registered_callback'] ?? '';

		if($registered_callback && is_callable($registered_callback)){
			call_user_func($registered_callback, $name, $object_type, $args);
		}
	}

	public static function filter_labels($labels){
		$taxonomy	= str_replace('taxonomy_labels_', '', current_filter());
		$args		= self::get($taxonomy)->to_array();
		$_labels	= $args['labels'] ?? [];

		$labels		= (array)$labels;
		$name		= $labels['name'];

		if(empty($args['hierarchical'])){
			$search		= ['标签', 'Tag', 'tag'];
			$replace	= [$name, ucfirst($name), $name];
		}else{
			$search		= ['目录', '分类', 'categories', 'Categories', 'Category'];
			$replace	= ['', $name, $name, $name.'s', ucfirst($name).'s', ucfirst($name)];
		}

		foreach ($labels as $key => &$label) {
			if($label && empty($_labels[$key]) && $label != $name){
				$label	= str_replace($search, $replace, $label);
			}
		}

		return $labels;
	}

	public static function filter_link($term_link, $term){
		if(array_search('%'.$term->taxonomy.'_id%', $GLOBALS['wp_rewrite']->rewritecode, true)){
			$term_link	= str_replace('%'.$term->taxonomy.'_id%', $term->term_id, $term_link);
		}

		return $term_link;
	}
}

class WPJAM_Term_Option{
	use WPJAM_Register_Trait;

	public function is_available_for_taxonomy($taxonomy){
		return is_callable($this->args) || empty($this->taxonomy) || in_array($taxonomy, $this->taxonomy);
	}

	public function get_fields($term_id=null){
		if(is_callable($this->args)){
			return call_user_func($this->args, $term_id, $this->name);
		}else{
			return [$this->name => $this->args];
		}
	}

	public static function register($name, $args){
		if(!is_callable($args)){
			if(!empty($args['taxonomy'])){
				$args['taxonomy']	= (array)$args['taxonomy'];
			}elseif(!empty($args['taxonomies']) && is_array($args['taxonomies'])){
				$args['taxonomy']	= $args['taxonomies'];
			}
		}

		self::register_instance($name, new self($name, $args));
	}
}