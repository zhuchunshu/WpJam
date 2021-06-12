<?php
abstract class WPJAM_Items{
	protected $args	= 0;
	protected $items	= [];

	abstract public function get_items();
	abstract public function update_items($items);

	public function delete_items(){
		return true;
	}

	public function __construct($args=[]){
		if(!isset($args['max_items'])){
			$args['max_items']	= $args['total'] ?? 0;	// 兼容
		}

		$this->args = wp_parse_args($args, [
			'primary_key'	=> 'id',
			'primary_title'	=> 'ID'
		]);

		$this->items	= $this->get_items();
	}

	public function __get($key){
		return $this->args[$key] ?? null;
	}

	public function get_primary_key(){
		return $this->primary_key;
	}

	public function get_results(){
		return $this->parse_items();
	}

	public function save(){
		return $this->update_items($this->items);
	}

	public function reset(){
		$result	= $this->delete_items();

		if($result && !is_wp_error($result)){
			$this->items	= $this->get_items();
		}

		return $result;
	}

	public function parse_items($items=null){
		$items	= $items ?? $this->items;

		if($items && is_array($items)){
			foreach($items as $id => &$item){
				$item[$this->primary_key]	= $id;
			}

			unset($item);
		}else{
			$items	= [];
		}

		return $items;
	}

	public function exists($value){
		return $this->items ? in_array($value, array_column($this->items, $this->unique_key)) : false;
	}

	public function get($id){
		return $this->items[$id] ?? false;
	}

	public function is_over_max_items(){
		if($this->max_items && count($this->items) >= $this->max_items){
			return new WP_Error('over_total', '最大允许数量：'.$this->max_items);
		}

		return false;
	}

	public function insert($item, $last=false){
		$result	= $this->is_over_max_items();

		if($result && is_wp_error($result)){
			return $result;
		}

		$item	= wpjam_array_filter($item, function($v){ return !is_null($v); });

		if(in_array($this->primary_key, ['option_key', 'id'])){
			if($this->unique_key){
				if(empty($item[$this->unique_key])){
					return new WP_Error('empty_'.$this->unique_key, $this->unique_title.'不能为空');
				}

				if($this->exists($item[$this->unique_key])){
					return new WP_Error('duplicate_'.$this->unique_key, $this->unique_title.'重复');
				}
			}

			if($this->items){
				$ids	= array_keys($this->items);
				$ids	= array_map(function($id){	return (int)(str_replace('option_key_', '', $id)); }, $ids);

				$id		= max($ids);
				$id		= $id+1;
			}else{
				$id		= 1;
			}

			if($this->primary_key == 'option_key'){
				$id		= 'option_key_'.$id;
			}

			$item[$this->primary_key]	= $id;
		}else{
			if(empty($item[$this->primary_key])){
				return new WP_Error('empty_'.$this->primary_key, $this->primary_title.'不能为空');
			}

			$id	= $item[$this->primary_key];

			if(isset($this->items[$id])){
				return new WP_Error('duplicate_'.$this->primary_key, $this->primary_title.'值重复');
			}
		}

		if($last){
			$this->items[$id]	= $item;
		}else{
			$this->items		= [$id=>$item]+$this->items;
		}

		$result	= $this->save();

		if(is_wp_error($result)){
			return $result;
		}

		return $id;
	}

	public function update($id, $item){
		if(!isset($this->items[$id])){
			return new WP_Error('invalid_'.$this->primary_key, $this->primary_title.'为「'.$id.'」的数据的不存在');
		}

		if(in_array($this->primary_key, ['option_key', 'id'])){
			if($this->unique_key && isset($item[$this->unique_key])){
				if(empty($item[$this->unique_key])){
					return new WP_Error('empty_'.$this->unique_key, $this->unique_title.'不能为空');
				}

				if($item[$this->unique_key] != $this->items[$id][$this->unique_key]){
					if($this->exists($item[$this->unique_key])){
						return new WP_Error('duplicate_'.$this->unique_key, $this->unique_title.'重复');
					}
				}
			}
		}

		$item[$this->primary_key] = $id;

		$item	= wp_parse_args($item, $this->items[$id]);
		$item	= wpjam_array_filter($item, function($v){ return !is_null($v); });

		$this->items[$id]	= $item;

		return $this->save();
	}

	public function delete($id){
		if(!isset($this->items[$id])){
			return new WP_Error('invalid_'.$this->primary_key, $this->primary_title.'为「'.$id.'」的数据的不存在');
		}

		$this->items	= wpjam_array_except($this->items, $id);

		return $this->save();
	}

	public function move($id, $data){
		$items	= $this->items;

		if(empty($items) || empty($items[$id])){
			return new WP_Error('key_not_exists', $id.'的值不存在');
		}

		$next	= $data['next'] ?? false;
		$prev	= $data['prev'] ?? false;

		if(!$next && !$prev){
			return new WP_Error('invalid_move', '无效移动位置');
		}

		$item	= wpjam_array_pull($items, $id);

		if($next){
			if(empty($items[$next])){
				return new WP_Error('key_not_exists', $next.'的值不存在');
			}

			$offset	= array_search($next, array_keys($items));

			if($offset){
				$items	= array_slice($items, 0, $offset, true) +  [$id => $item] + array_slice($items, $offset, null, true);
			}else{
				$items	= [$id => $item] + $items;
			}
		}else{
			if(empty($items[$prev])){
				return new WP_Error('key_not_exists', $prev.'的值不存在');
			}

			$offset	= array_search($prev, array_keys($items));
			$offset ++;

			if($offset){
				$items	= array_slice($items, 0, $offset, true) +  [$id => $item] + array_slice($items, $offset, null, true);
			}else{
				$items	= [$id => $item] + $items;
			}
		}

		$this->items	= $items;

		return $this->save();
	}

	public function query_items($limit, $offset){
		return ['items'=>$this->parse_items(), 'total'=>count($this->items)];
	}
}

class WPJAM_Option_Items extends WPJAM_Items{
	private $option_name;
	
	public function __construct($option_name, $args=[]){
		$this->option_name	= $option_name;

		if(!is_array($args)){
			$args	= ['primary_key' => $args];
		}else{
			$args	= wp_parse_args($args, ['primary_key'=>'option_key']);
		}

		parent::__construct($args);
	}

	public function get_items(){
		return get_option($this->option_name) ?: [];
	}

	public function update_items($items){
		if($items && in_array($this->get_primary_key(), ['option_key','id'])){
			foreach ($items as &$item){
				unset($item[$this->get_primary_key()]);
			}

			unset($item);
		}

		return update_option($this->option_name, $items);
	}

	public function delete_items(){
		return delete_option($this->option_name);
	}
}

class WPJAM_Meta_Items extends WPJAM_Items{
	private $meta_type;
	private $object_id;
	private $meta_key;

	public function __construct($meta_type, $object_id, $meta_key, $args=[]){
		$this->meta_type	= $meta_type;
		$this->object_id	= $object_id;
		$this->meta_key		= $meta_key;

		parent::__construct($args);
	}

	public function get_items(){
		return get_metadata($this->meta_type, $this->object_id, $this->meta_key, true) ?: [];
	}

	public function update_items($items){
		if($items && in_array($this->get_primary_key(), ['option_key','id'])){
			foreach($items as &$item){
				unset($item[$this->get_primary_key()]);
				unset($item[$this->meta_type.'_id']);
			}

			unset($item);
		}

		return update_metadata($this->meta_type, $this->object_id, $this->meta_key, $items);
	}

	public function delete_items(){
		return delete_metadata($this->meta_type, $this->object_id, $this->meta_key);
	}
}

class WPJAM_Content_Items extends WPJAM_Items{
	private $post_id;

	public function __construct($post_id, $args=[]){
		$this->post_id	= $post_id;

		parent::__construct($args);
	}

	public function get_items(){
		$_post	= get_post($this->post_id);

		return ($_post && $_post->post_content) ? maybe_unserialize($_post->post_content) : [];
	}

	public function update_items($items){
		if($items && in_array($this->get_primary_key(), ['option_key','id'])){
			foreach($items as &$item){
				unset($item[$this->get_primary_key()]);
				unset($item['post_id']);
			}

			unset($item);

			$content	= maybe_serialize($items);
		}else{
			$content	= '';
		}
		
		return WPJAM_Post::update($this->post_id, ['post_content'=>$content]);
	}

	public function delete_items(){
		return WPJAM_Post::update($this->post_id, ['post_content'=>'']);
	}
}

class_alias('WPJAM_Option_Items', 'WPJAM_Option');
class_alias('WPJAM_Items', 'WPJAM_Item');