<?php
class WPJAM_Platform{
	use WPJAM_Register_Trait;

	public function verify(){
		return call_user_func($this->verify);
	}

	public static function get_sorted(){
		return wpjam_sort_items(self::get_registereds(), 'order', 'ASC');
	}

	public static function get_options($type='bit'){
		$objects	= self::get_sorted();

		if($type == 'key'){
			return wp_list_pluck($objects, 'title');
		}elseif($type == 'bit'){
			$objects	= array_filter($objects, function($object){
				return !empty($object->bit);
			});

			return wp_list_pluck($objects, 'title', 'bit');
		}else{
			return wp_list_pluck($objects, 'bit');
		}
	}

	public static function get_current($platforms=[], $type='bit'){
		foreach(self::get_sorted() as $name=>$object){
			if($object->verify()){
				$return	= $type == 'bit' ? $object->bit : $name;

				if($platforms){
					if(in_array($return, $platforms)){
						return $return;
					}
				}else{
					return $return;
				}
			}	
		}

		return '';
	}
}

class WPJAM_Path{
	use WPJAM_Register_Trait;

	private $fields	= [];
	private $types	= [];

	public function __call($func, $args) {
		if(strpos($func, 'get_') === 0){
			$key	= str_replace('get_', '', $func);

			if(in_array($key, ['tabbar', 'page', 'callback'])){
				return $this->types[$args[0]][$key] ?? false;
			}elseif($key == 'type'){
				return $this->types[$args[0]] ?? [];
			}else{
				return $this->$key;
			}
		}elseif(strpos($func, 'set_') === 0){
			$key	= str_replace('set_', '', $func);

			if(in_array($key, ['tabbar', 'page', 'callback', 'path'])){
				return $this->types[$args[0]][$key]	= $args[1];
			}else{
				return $this->$key = $args[0];
			}
		}
	}

	public function add_type($type, $args){
		$this->types[$type]	= [];

		if(isset($args['path'])){
			$this->set_path($type, $args['path']);

			if($args['path']){
				if(strrpos($args['path'], '?')){
					$path_parts	= explode('?', $args['path']);
					$this->set_page($type, $path_parts[0]);
				}else{
					$this->set_page($type, $args['path']);
				}
			}
		}

		if(!empty($args['callback'])){
			$this->set_callback($type, $args['callback']);
		}elseif($this->page_type && method_exists($this, 'get_'.$this->page_type.'_path')){
			$this->set_callback($type, [$this, 'get_'.$this->page_type.'_path']);
		}

		if(!empty($args['fields'])){
			$this->set_fields($type, $args['fields']);
		}elseif($this->page_type && method_exists($this, 'get_'.$this->page_type.'_fields')){
			$this->set_fields($type, [$this, 'get_'.$this->page_type.'_fields']);
		}

		$tabbar	= $args['tabbar'] ?? false;
		$this->set_tabbar($type, $tabbar);
	}

	public function remove_type($type){
		unset($this->types[$type]);
	}

	public function get_fields(){
		$fields	= $this->fields;

		if($fields && is_callable($fields)){
			$fields	= call_user_func($fields, $this->name);
		}

		return $fields;
	}

	public function set_fields($type, $fields=[]){
		if(is_callable($fields)){
			$this->fields	= $fields;
		}elseif(!is_callable($this->fields)){
			$this->fields	= array_merge($this->fields, $fields);
		}
	}

	public function get_path($type, $args=[]){
		$callback	= $this->get_callback($type);

		if($callback && is_callable($callback)){
			if(empty($args['path'])){
				$args['path']	= $this->types[$type]['path'] ?? '';
			}
			
			return call_user_func($callback, array_merge($args, ['path_type'=>$type]));
		}else{
			if(isset($this->types[$type]['path'])){
				return $this->types[$type]['path'];
			}else{
				if(isset($args['backup'])){
					return new WP_Error('invalid_page_key_backup', '备用页面无效');
				}else{
					return new WP_Error('invalid_page_key', '页面无效');
				}
			}
		}
	}

	public function get_raw_path($type){
		return $this->types[$type]['path'] ?? '';
	}

	private function get_post_type_path($args){
		$post_id	= (int)($args[$this->post_type.'_id'] ?? 0);

		if(empty($post_id)){
			$pt_object	= get_post_type_object($this->post_type);
			return new WP_Error('empty_'.$this->post_type.'_id', $pt_object->label.'ID不能为空并且必须为数字');
		}

		if($args['path_type'] == 'template'){
			return get_permalink($post_id);
		}else{
			if(strpos($args['path'], '%post_id%')){
				return str_replace('%post_id%', $post_id, $args['path']);
			}else{
				return $args['path'];
			}
		}
	}

	private function get_taxonomy_path($args){
		$query_key	= wpjam_get_taxonomy_query_key($this->taxonomy);

		if(empty($args[$query_key])){
			$tax_object	= get_taxonomy($this->taxonomy);
			return new WP_Error('empty_'.$query_key, $tax_object->label.'ID不能为空并且必须为数字');
		}

		$term_id	= (int)$args[$query_key];

		if($args['path_type'] == 'template'){
			return get_term_link($term_id, $this->taxonomy);
		}else{
			if(strpos($args['path'], '%term_id%')){
				return str_replace('%term_id%', $term_id, $args['path']);
			}else{
				return $args['path'];
			}
		}
	}

	private function get_author_path($args){
		$author	= (int)($args['author'] ?? 0);

		if(empty($author)){
			return new WP_Error('empty_author', '作者ID不能为空并且必须为数字。');
		}

		if($args['path_type'] == 'template'){
			return get_author_posts_url($author);
		}else{
			if(strpos($args['path'], '%author%')){
				return str_replace('%author%', $author, $args['path']);
			}else{
				return $args['path'];
			}
		}
	}

	private function get_post_type_fields(){
		if(get_post_type_object($this->post_type)){
			return [$this->post_type.'_id'	=> wpjam_get_post_id_field($this->post_type, ['required'=>true])];
		}else{
			return [];
		}
	}

	private function get_taxonomy_fields(){
		if($tax_obj = get_taxonomy($this->taxonomy)){
			$query_key	= wpjam_get_taxonomy_query_key($this->taxonomy);

			return [$query_key	=> wpjam_get_term_id_field($this->taxonomy, ['required'=>true])];
		}else{
			return [];
		}
	}

	private function get_author_fields(){
		return ['author'	=> ['title'=>'',	'type'=>'select',	'options'=>wp_list_pluck(get_users(['who'=>'authors']), 'display_name', 'ID')]];
	}

	public function has($types, $operator='AND', $strict=false){
		$types	= (array)$types;

		foreach($types as $type){
			$has	= isset($this->types[$type]['path']) || isset($this->types[$type]['callback']);

			if($strict && $has && isset($this->types[$type]['path']) && $this->types[$type]['path'] === false){
				$has	= false;
			}

			if($operator == 'AND'){
				if(!$has){
					return false;
				}
			}elseif($operator == 'OR'){
				if($has){
					return true;
				}
			}
		}

		if($operator == 'AND'){
			return true;
		}elseif($operator == 'OR'){
			return false;
		}
	}

	private static $path_types	= [];

	public static function parse_item($item, $path_type, $backup=false){
		if($backup){
			$page_key	= $item['page_key_backup'] ?: 'none';
		}else{
			$page_key	= $item['page_key'] ?? '';
		}

		$parsed	= [];

		if($page_key == 'none'){
			if(!empty($item['video'])){
				$parsed['type']		= 'video';
				$parsed['video']	= $item['video'];
				$parsed['vid']		= wpjam_get_qqv_id($item['video']);
			}else{
				$parsed['type']		= 'none';
			}
		}elseif($page_key == 'external'){
			if($path_type == 'web'){
				$parsed['type']		= 'external';
				$parsed['url']		= $item['url'];
			}
		}elseif($page_key == 'web_view'){
			if($path_type == 'web'){
				$parsed['type']		= 'external';
				$parsed['url']		= $item['src'];
			}else{
				$parsed['type']		= 'web_view';
				$parsed['src']		= $item['src'];
			}
		}elseif($page_key){
			if($path_obj = self::get($page_key)){
				if($backup){
					$backup_item	= ['backup'=>true];

					if($path_fields = $path_obj->get_fields()){
						foreach($path_fields as $field_key => $path_field){
							$backup_item[$field_key]	= $item[$field_key.'_backup'] ?? '';
						}
					}

					$path	= $path_obj->get_path($path_type, $backup_item);
				}else{
					$path	= $path_obj->get_path($path_type, $item);
				}

				if(!is_wp_error($path)){
					if(is_array($path)){
						$parsed	= $path;
					}else{
						$parsed['type']		= '';
						$parsed['page_key']	= $page_key;
						$parsed['path']		= $path;
					}
				}
			}
		}

		return $parsed;
	}

	public static function validate_item($item, $path_types){
		$page_key	= $item['page_key'];

		if($page_key == 'none'){
			return true;
		}elseif($page_key == 'web_view'){
			$path_types	= array_diff($path_types, ['web']);
		}

		if($path_obj = self::get($page_key)){
			$backup_check	= false;

			foreach ($path_types as $path_type) {
				$path	= $path_obj->get_path($path_type, $item);

				if(is_wp_error($path)){
					if(count($path_types) <= 1 || $path->get_error_code() != 'invalid_page_key'){
						return $path;
					}else{
						$backup_check	= true;
						break;
					}
				}
			}
		}else{
			if(count($path_types) <= 1){
				return new WP_Error('invalid_page_key', '页面无效');
			}

			$backup_check	= true;
		}

		if($backup_check){
			$page_key	= $item['page_key_backup'] ?: 'none';

			if($page_key == 'none'){
				return true;
			}

			if($path_obj = self::get($page_key)){
				$backup		= ['backup'=>true];

				if($path_obj && ($path_fields = $path_obj->get_fields())){
					foreach($path_fields as $field_key => $path_field){
						$backup[$field_key]	= $item[$field_key.'_backup'] ?? '';
					}
				}

				foreach ($path_types as $path_type) {
					$path	= $path_obj->get_path($path_type, $backup);

					if(is_wp_error($path)){
						return $path;
					}
				}
			}else{
				return new WP_Error('invalid_page_key_backup', '备用页面无效');
			}
		}

		return true;
	}

	public static function get_item_link_tag($parsed, $text){
		if($parsed['type'] == 'none'){
			return $text;
		}elseif($parsed['type'] == 'external'){
			return '<a href_type="web_view" href="'.$parsed['url'].'">'.$text.'</a>';
		}elseif($parsed['type'] == 'web_view'){
			return '<a href_type="web_view" href="'.$parsed['src'].'">'.$text.'</a>';
		}elseif($parsed['type'] == 'mini_program'){
			return '<a href_type="mini_program" href="'.$parsed['path'].'" appid="'.$parsed['appid'].'">'.$text.'</a>';
		}elseif($parsed['type'] == 'contact'){
			return '<a href_type="contact" href="" tips="'.$parsed['tips'].'">'.$text.'</a>';
		}elseif($parsed['type'] == ''){
			return '<a href_type="path" page_key="'.$parsed['page_key'].'" href="'.$parsed['path'].'">'.$text.'</a>';
		}
	}

	public static function get_tabbar_options($path_type){
		$options	= [];
	
		foreach (self::get_registereds() as $page_key => $path_obj){
			if($tabbar	= $path_obj->get_tabbar($path_type)){
				$options[$page_key]	= is_array($tabbar) ? $tabbar['text'] : $path_obj->title;
			}
		}

		return $options;
	}

	public static function get_path_fields($path_types, $for=''){
		if(empty($path_types)){
			return [];
		}

		$path_types	= (array) $path_types;

		$backup_fields_required	= count($path_types) > 1 && $for != 'qrcode';

		if($backup_fields_required){
			$backup_fields	= ['page_key_backup'=>['title'=>'',	'type'=>'select',	'options'=>['none'=>'只展示不跳转'],	'description'=>'&emsp;跳转页面不生效时将启用备用页面']];
			$backup_show_if_keys	= [];
		}

		$page_key_fields	= ['page_key'	=> ['title'=>'',	'type'=>'select',	'options'=>[]]];
		
		$strict	= ($for == 'qrcode');

		foreach(self::get_registereds() as $page_key => $path_obj){
			if(!$path_obj->has($path_types, 'OR', $strict)){
				continue;
			}

			$page_key_fields['page_key']['options'][$page_key]	= $path_obj->title;

			if($path_fields = $path_obj->get_fields()){
				foreach($path_fields as $field_key => $path_field){
					if(isset($page_key_fields[$field_key])){
						$page_key_fields[$field_key]['show_if']['value'][]	= $page_key;
					}else{
						$path_field['title']	= '';
						$path_field['show_if']	= ['key'=>'page_key','compare'=>'IN','value'=>[$page_key]];

						$page_key_fields[$field_key]	= $path_field;
					}
				}
			}

			if($backup_fields_required){
				if($path_obj->has($path_types, 'AND')){
					if($page_key == 'module_page' && $path_fields){
						$backup_fields['page_key_backup']['options'][$page_key]	= $path_obj->title;

						foreach($path_fields as $field_key => $path_field){
							$path_field['show_if']	= ['key'=>'page_key_backup','value'=>$page_key];
							$backup_fields[$field_key.'_backup']	= $path_field;
						}
					}elseif(empty($path_fields)){
						$backup_fields['page_key_backup']['options'][$page_key]	= $path_obj->title;
					}
				}else{
					if($page_key == 'web_view'){
						if(!$path_obj->has(array_diff($path_types, ['web']), 'AND')){
							$backup_show_if_keys[]	= $page_key;
						}
					}else{
						$backup_show_if_keys[]	= $page_key;
					}
				}
			}
		}

		if($for == 'qrcode'){
			return ['page_key_set'	=> ['title'=>'页面',	'type'=>'fieldset',	'fields'=>$page_key_fields]];
		}else{
			$page_key_fields['page_key']['options']['none']	= '只展示不跳转';

			$fields	= ['page_key_set'	=> ['title'=>'页面',	'type'=>'fieldset',	'fields'=>$page_key_fields]];

			if($backup_fields_required){
				$show_if	= ['key'=>'page_key','compare'=>'IN','value'=>$backup_show_if_keys];

				$fields['page_key_backup_set']	= ['title'=>'备用',	'type'=>'fieldset',	'fields'=>$backup_fields, 'show_if'=>$show_if];
			}

			return $fields;
		}
	}

	public static function get_page_keys($path_type){
		$pages	= [];

		foreach(self::get_registereds() as $page_key => $path_obj){
			if($page = $path_obj->get_page($path_type)){
				$pages[]	= compact('page_key', 'page');
			}
		}

		return $pages;
	}

	public static function register($page_key, $path_type, $args){
		$args['page_type']	= $args['page_type'] ?? '';

		if($args['page_type'] == 'post_type'){
			$args['post_type']	= $args['post_type'] ?? $page_key;
		}elseif($args['page_type'] == 'taxonomy'){
			$args['taxonomy']	= $args['taxonomy'] ?? $page_key;
		}

		$path_obj	= self::get($page_key);

		if(is_null($path_obj)){
			$path_obj	= new WPJAM_Path($page_key, $args);

			self::register_instance($page_key, $path_obj);
		}else{
			if($path_obj->get_type($path_type)){
				trigger_error('Path 「'.$page_key.'」的「'.$path_type.'」已经注册。');
			}
		}

		if(!in_array($path_type, self::$path_types)){
			self::$path_types[]	= $path_type;
		}

		$path_obj->add_type($path_type, $args);
	}

	public static function get_by($args=[]){
		$path_type	= $args['path_type'] ?? '';
		$args		= wp_array_slice_assoc($args, ['page_type', 'post_type', 'taxonomy']);
		$path_objs	= wp_filter_object_list(self::get_registereds(), $args);

		if($path_type){
			$path_objs	= array_filter($path_objs, function($path_obj) use($path_type){
				return $path_obj->has($path_type);
			});
		}

		return $path_objs;
	}

	public static function create($page_key, $args=[]){
		wpjam_register_path($page_key, $args);
	}
}