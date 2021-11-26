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

				if(($platforms && in_array($return, $platforms)) 
					|| empty($platforms))
				{
					return $return;
				}
			}	
		}

		return '';
	}
}

class WPJAM_Path{
	use WPJAM_Register_Trait;

	private $types	= [];

	public function get_type($type){
		return $this->types[$type] ?? [];
	}

	public function add_type($type, $item){
		$page_type	= $item['page_type'] ?? '';

		if($page_type 
			&& in_array($page_type, ['post_type', 'taxonomy'])
			&& empty($item[$page_type])
		){
			$item[$page_type]	= $this->name;
		}

		$this->types[$type]	= $item;

		$this->args	= $this->args+$item;

		if(!in_array($type, self::$platforms)){
			self::$platforms[]	= $type;
		}
	}

	public function remove_type($type){
		unset($this->types[$type]);
	}

	public function get_tabbar($type){
		if($item = $this->get_type($type)){
			return $item['tabbar'] ?? '';
		}
	}

	public function get_fields(){
		$fields	= [];

		foreach($this->types as $type => $item){
			$item_fields	= $item['fields'] ?? [];

			if(!$item_fields && !empty($item['page_type'])){
				if(method_exists($this, 'get_'.$item['page_type'].'_fields')){
					$item_fields	= [$this, 'get_'.$item['page_type'].'_fields'];
				}
			}

			if($item_fields){
				if(is_callable($item_fields)){
					$item_fields	= call_user_func($item_fields, $this->name);
				}

				if(is_array($item_fields)){
					$fields	= array_merge($fields, $item_fields);
				}
			}
		}

		return $fields;
	}

	public function get_path($type, $args=[]){
		if($item = $this->get_type($type)){
			$callback	= $item['callback'] ?? '';

			if(!$callback && !empty($item['page_type'])){
				if(method_exists($this, 'get_'.$item['page_type'].'_path')){
					$callback	= [$this, 'get_'.$item['page_type'].'_path'];
				}
			}

			if($callback && is_callable($callback)){
				$args['path_type']	= $type;

				if(empty($args['path'])){
					$args['path']	= $item['path'] ?? '';
				}
				
				return call_user_func($callback, $args, $this->name) ?: '';
			}else{
				if(isset($item['path'])){
					return $item['path'] ?: '';
				}

				if(isset($args['backup'])){
					return new WP_Error('invalid_backup_page_key', '无效的备用页面');
				}else{
					return new WP_Error('invalid_page_key', '无效的页面');
				}
			}
		}else{
			return new WP_Error('invalid_page_key', '无效的页面');
		}
	}

	public function get_raw_path($type){
		if($item = $this->get_type($type)){
			return $item['path'] ?? '';
		}

		return '';
	}

	private function get_post_type_path($args){
		$post_type	= $this->post_type ?: $this->name;
		$post_id	= (int)wpjam_array_pull($args, $post_type.'_id');

		if(empty($post_id)){
			$label	= get_post_type_object($post_type)->label;
			return new WP_Error('empty_'.$post_type.'_id', $label.'ID不能为空并且必须为数字');
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
		$taxonomy	= $this->taxonomy ?: $this->name;
		$query_key	= wpjam_get_taxonomy_query_key($taxonomy);
		$term_id	= (int)wpjam_array_pull($args, $query_key);

		if(empty($term_id)){
			$label	= get_taxonomy($taxonomy)->label;
			return new WP_Error('empty_'.$query_key, $label.'ID不能为空并且必须为数字');
		}

		if($args['path_type'] == 'template'){
			return get_term_link($term_id, $taxonomy);
		}else{
			if(strpos($args['path'], '%term_id%')){
				return str_replace('%term_id%', $term_id, $args['path']);
			}else{
				return $args['path'];
			}
		}
	}

	private function get_author_path($args){
		$aurhor	= (int)wpjam_array_pull($args, 'author');

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
		$post_type	= $this->post_type ?: $this->name;

		if(get_post_type_object($post_type)){
			return [$post_type.'_id'	=> wpjam_get_post_id_field($post_type, ['required'=>true])];
		}else{
			return [];
		}
	}

	private function get_taxonomy_fields(){
		$taxonomy	= $this->taxonomy ?: $this->name;

		if(get_taxonomy($taxonomy)){
			$query_key	= wpjam_get_taxonomy_query_key($taxonomy);

			return [$query_key	=> wpjam_get_term_id_field($taxonomy, ['required'=>true])];
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
			if($item = $this->get_type($type)){
				$has	= isset($item['path']) || isset($item['callback']);

				if($strict && $has && isset($item['path']) && $item['path'] === false){
					$has	= false;
				}
			}else{
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

	private static $platforms	= [];

	public static function get_platforms(){
		return self::$platforms;
	}

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
			if(in_array($path_type, ['web', 'template'])){
				$parsed['type']		= 'external';
				$parsed['url']		= $item['url'];
			}
		}elseif($page_key == 'web_view'){
			if(in_array($path_type, ['web', 'template'])){
				$parsed['type']		= 'external';
				$parsed['url']		= $item['src'];
			}else{
				$parsed['type']		= 'web_view';
				$parsed['src']		= $item['src'];
			}
		}

		if(empty($parsed) && $page_key && ($path_obj = self::get($page_key))){
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

		return $parsed;
	}

	public static function validate_item($item, $path_types){
		$page_key	= $item['page_key'];

		if($page_key == 'none'){
			return true;
		}elseif($page_key == 'web_view'){
			$path_types	= array_diff($path_types, ['web','template']);
		}

		if($path_obj = self::get($page_key)){
			$backup_check	= false;

			foreach($path_types as $path_type){
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
	
		foreach(self::get_registereds() as $page_key => $path_obj){
			if($tabbar = $path_obj->get_tabbar($path_type)){
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
					if(isset($path_field['show_if'])){
						$page_key_fields[$field_key]	= $path_field;
					}else{
						if(isset($page_key_fields[$field_key])){
							$page_key_fields[$field_key]['show_if']['value'][]	= $page_key;
						}else{
							$path_field['title']	= '';
							$path_field['show_if']	= ['key'=>'page_key','compare'=>'IN','value'=>[$page_key]];

							$page_key_fields[$field_key]	= $path_field;
						}
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
						if(!$path_obj->has(array_diff($path_types, ['web','template']), 'AND')){
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
			if($path = $path_obj->get_raw_path($path_type)){
				$pos	= strrpos($path, '?');
				$page	= $pos ? substr($path, 0, $pos) : $path;

				$pages[]	= compact('page_key', 'page');
			}
		}

		return $pages;
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