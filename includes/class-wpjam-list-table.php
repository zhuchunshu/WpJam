<?php
class WPJAM_List_Table extends WP_List_Table{
	protected $model = '';

	public function __construct($args=[]){
		$args	= wp_parse_args($args, [
			'title'			=> '',
			'plural'		=> '',
			'singular'		=> '',
			'model'			=> '',
			'capability'	=> 'manage_options',
			'data_type'		=> 'form',
			'ajax'			=> true,
			'screen'		=> get_current_screen(),
			'per_page'		=> 50
		]);

		$this->model	= $args['model'];
		$primary_key	= $this->call_model_method('get_primary_key');

		if($primary_key && !is_wp_error($primary_key)){
			$args['primary_key']	= $primary_key;
		}

		$actions	= $this->call_model_method('get_actions');

		if(is_array($actions)){
			$args['actions']	= $actions;
		}elseif(!isset($args['actions'])){
			$args['actions']	= [
				'add'		=> ['title'=>'新建',	'dismiss'=>true],
				'edit'		=> ['title'=>'编辑'],
				'delete'	=> ['title'=>'删除',	'direct'=>true, 'confirm'=>true,	'bulk'=>true],
			];
		}

		$args	= $this->parse_args($args);

		if(is_array($args['per_page'])){
			add_screen_option('per_page', $args['per_page']);
		}

		if(!empty($args['style'])){
			add_action('admin_enqueue_scripts', function(){
				wp_add_inline_style('list-tables', $this->_args['style']);
			});
		}

		if(method_exists($this->model, 'admin_head')){
			add_action('admin_head',	[$this->model, 'admin_head']);
		}

		if($args['columns']){
			$column_keys	= array_keys($args['columns']);

			if(empty($args['primary_column']) || !in_array($args['primary_column'], $column_keys)){
				$args['primary_column']	= $column_keys[0];
			}
		}

		if(!empty($args['bulk_actions'])){
			$args['columns'] = array_merge(['cb'=>'checkbox'], $args['columns']);

			if(!wp_doing_ajax()){
				add_filter('wpjam_html',	[$this, 'set_bulk_action_data_attr']);
			}else{
				if($_POST['action'] == 'wpjam-list-table-action' && $_POST['list_action_type'] == 'list'){
					add_filter('wpjam_ajax_response',	[$this, 'set_bulk_action_data_attr']);
				}
			}
		}

		parent::__construct($args);
	}

	public function __get($name){
		return parent::__get($name) ?? ($this->_args[$name] ?? null);
	}

	public function __isset($name){
		return parent::__isset($name) ?? isset($this->_args[$name]);
	}

	public function parse_args($args){
		$this->_args	= $args;

		$args['actions']	= $args['actions'] ?? [];

		foreach($args['actions'] as $key => $action){
			wpjam_register_list_table_action($key, $action);
		}

		if(!empty($args['sortable'])){
			wpjam_register_list_table_action('move',	['page_title'=>'拖动',		'direct'=>true,	'title'=>'<span class="dashicons dashicons-move"></span>']);
			wpjam_register_list_table_action('up',		['page_title'=>'向上移动',	'direct'=>true,	'title'=>'<span class="dashicons dashicons-arrow-up-alt"></span>']);
			wpjam_register_list_table_action('down',	['page_title'=>'向下移动',	'direct'=>true,	'title'=>'<span class="dashicons dashicons-arrow-down-alt"></span>']);
		}

		$args['actions']	= $args['bulk_actions'] = $args['overall_actions'] = $args['row_actions'] = $next_actions = [];

		$actions	= WPJAM_List_Table_Action::get_by([], 'settings');
		$actions	= array_filter($actions, [$this, 'is_available']);

		foreach(wpjam_list_sort($actions) as $key => $action){
			$action['key']	= $key;

			if(!isset($action['page_title'])){
				$action['page_title']	= wp_strip_all_tags($action['title'].$this->title);
			}

			if(!isset($action['capability'])){
				$action['capability']	= $this->capability;
			}

			if(!empty($action['overall'])){
				$action['response']	= 'list';

				if($this->current_user_can($action)){
					$args['overall_actions'][]	= $key;
				}
			}else{
				if(!isset($action['response'])){
					$action['response']	= $key;
				}

				if(!empty($action['bulk'])){
					if($this->current_user_can($action)){
						$args['bulk_actions'][$key]	= $action['title'];
					}
				}

				if(!empty($action['next']) && $action['response'] == 'form'){
					$next_actions[]	= $action['next'];
				}

				if($key != 'add' && (!isset($action['row_action']) || $action['row_action'])){
					$args['row_actions'][]	= $key;
				}
			}

			$args['actions'][$key]	= $action;
		}

		$args['row_actions']	= array_diff($args['row_actions'], $next_actions);

		foreach($this->get_fields() as $key => $field){
			if($this->data_type != 'form' || !empty($field['show_admin_column'])){
				wpjam_register_list_table_column($key, $field);
			}

			if($field['type'] == 'fieldset' && (empty($field['fieldset_type']) || $field['fieldset_type'] == 'single')){
				foreach($field['fields'] as $sub_key => $sub_field){
					if($this->data_type != 'form' || !empty($sub_field['show_admin_column'])){
						wpjam_register_list_table_column($sub_key, $sub_field);
					}
				}
			}
		}

		$column_fields	= WPJAM_List_Table_Column::get_by([], 'settings');
		$column_fields	= array_filter($column_fields, [$this, 'is_available']);

		$args['column_fields']		= wpjam_list_sort($column_fields);
		$args['columns']			= $args['columns'] ?? [];
		$args['sortable_columns']	= $args['sortable_columns'] ?? [];

		foreach($args['column_fields'] as $key => $field){
			$args['columns'][$key]	= $field['column_title'] ?? $field['title'];

			if(!empty($field['sortable_column'])){
				$args['sortable_columns'][$key] = [$key, true];
			}
		}

		return $args;
	}

	public function is_available($args){
		if(isset($args['plugin_page'])){
			if(empty($GLOBALS['plugin_page']) || !in_array($GLOBALS['plugin_page'], (array)$args['plugin_page'])){
				return false;
			}elseif(isset($args['current_tab'])){
				if(empty($GLOBALS['current_tab']) || !in_array($GLOBALS['current_tab'], (array)$args['current_tab'])){
					return false;
				}
			}
		}else{
			if(isset($args['screen_base'])){
				if(!in_array(get_current_screen()->base, (array)$args['screen_base'])){
					return false;
				}
			}

			if(isset($args['screen_id'])){
				if(!in_array(get_current_screen()->id, (array)$args['screen_id'])){
					return false;
				}
			}
		}

		return true;
	}

	public function call_model_method($method, ...$args){
		$result		= null;
		$methods	= [
			'get_actions',
			'get_subtitle',
			'subtitle', 
			'get_views', 
			'views',
			'get_fields',
			'extra_tablenav', 
			'query_items',
			'list',
			'before_single_row',
			'item_callback',
			'render_item',
			'after_single_row',
			'admin_footer'
		];

		$compat_methods	= [
			'render_item'	=> 'item_callback',
			'get_subtitle'	=> 'subtitle',
			'get_views'		=> 'views',
		];

		$reflection	= new ReflectionClass($this->model);

		if(isset($compat_methods[$method])){
			if(!method_exists($this->model, $method) && method_exists($this->model, $compat_methods[$method])){
				$method	= $compat_methods[$method];
			}
		}elseif($method == 'query_items'){
			$model_methods	= $reflection->getMethods();
			$model_methods	= wp_list_pluck($model_methods, 'class', 'name');

			if(isset($model_methods['query_items']) && $model_methods['query_items'] == $this->model){
				$method	= 'query_items';
			}elseif(isset($model_methods['list']) && $model_methods['list'] == $this->model){
				$method	= 'list';
			}elseif(isset($model_methods['query_items']) && $model_methods['query_items'] != 'WPJAM_Model'){
				$method	= 'query_items';
			}elseif(isset($model_methods['list']) && $model_methods['list'] != 'WPJAM_Model'){
				$method	= 'list';
			}
		}

		if(method_exists($this->model, $method)){
			$static	= $reflection->getMethod($method)->isStatic();

			if(!$static){
				$exists	= method_exists($this->model, 'get_instance');
			}else{
				$exists	= true;
			}
		}elseif(method_exists($this->model, '__callStatic') && !in_array($method, $methods)){
			$exists	= true;
			$static	= true;
		}else{
			$exists	= false;
		}

		if($exists){
			if($static){
				$result	= call_user_func([$this->model, $method], ...$args);
			}else{
				$_id	= wpjam_array_pull($args, 0);
				$obj	= $this->model::get_instance($_id);

				if(is_null($obj)){
					return new WP_Error('model_object_not_found','数据不存在');
				}elseif(is_wp_error($obj)){
					return $obj;
				}

				$result	= call_user_func([$obj, $method], ...$args);
			}
		}else{
			if(in_array($method, $methods)){
				$result	= null;
			}else{
				$result	= new WP_Error('undefined_method', '「'.$method.'」方法未定义');
			}
		}

		if(is_null($result) || is_wp_error($result)){
			if(in_array($method, ['subtitle', 'get_subtitle'])){
				return '';
			}elseif(in_array($method, ['get_views', 'views', 'get_filterable_fields', 'get_searchable_fields'])){
				return [];
			}elseif(in_array($method, ['get', 'render_item', 'item_callback'])){	// 使用第一个
				return $args[0];
			}elseif(in_array($method, ['extra_tablenav', 'before_single_row', 'after_single_row', 'admin_footer'])){
				return;
			}
		}

		return $result;
	}

	public function get_subtitle(){
		$subtitle	= $this->call_model_method('get_subtitle') ?: '';

		if($search_term = wpjam_get_data_parameter('s')){
			$subtitle 	.= ' “'.esc_html($search_term).'”的搜索结果';
		}

		$subtitle	= $subtitle ? '<span class="subtitle">'.$subtitle.'</span>' : '';

		if(isset($this->actions['add'])){
			if($this->layout != 'calendar' || ($this->layout == 'calendar' && !empty($this->actions['add']['calendar']))){
				$subtitle	= $this->get_row_action('add', ['class'=>'page-title-action', 'dashicon'=>false]).$subtitle;
			}
		}

		return $subtitle;
	}

	public function get_action($key){
		return is_array($key) ? $key : ($this->actions[$key] ?? []);
	}

	protected function get_submit_text($action, $id){
		if(isset($action['submit_text'])){
			$submit_text	= $action['submit_text'];

			if($submit_text && is_callable($submit_text)){
				$submit_text	= call_user_func($submit_text, $id, $action['key']);
			}

			return $submit_text;
		}else{
			return wp_strip_all_tags($action['title']) ?: $action['page_title'];
		}
	}

	protected function create_nonce($key, $id=''){
		return wp_create_nonce($this->get_nonce_action($key, $id));
	}

	protected function verify_nonce($nonce, $key, $id=''){
		return wp_verify_nonce($nonce, $this->get_nonce_action($key, $id));
	}

	protected function get_nonce_action($key, $id=0){
		return $id ? $key.'-'.$this->singular.'-'.$id : $key.'-'.$this->singular;
	}

	protected function get_row_actions($id){
		$row_actions	= [];

		foreach($this->row_actions as $key){
			if($row_action = $this->get_row_action($key, ['id'=>$id])){
				$row_actions[$key] = $row_action;
			}
		}

		return $row_actions;
	}

	public function get_row_action($action, $args=[]){
		$action	= $this->get_action($action);
		$args	= wp_parse_args($args, ['id'=>0, 'data'=>[], 'class'=>'', 'style'=>'', 'title'=>'']);

		if(!$action || !$this->current_user_can($action, $args['id'])){
			return '';
		}

		if($this->layout == 'calendar' && empty($action['calendar'])){
			return '';
		}

		$attr	= 'title="'.esc_attr($action['page_title']).'"';

		$tag	= $args['tag'] ?? 'a';

		if(!empty($action['redirect'])){
			$class		= 'list-table-redirect';
			$tag		= 'a';
			$data_attr	= '';
			$href		= str_replace('%id%', $args['id'], $action['redirect']);
		}elseif(!empty($action['filter'])){
			$class		= 'list-table-filter';
			$item		= (array)$this->call_model_method('get', $args['id']);
				
			$action['data']	= $action['data'] ?? [];
			$action['data']	= array_merge($action['data'], wp_array_slice_assoc($item, wp_parse_list($action['filter'])));

			$data		= wp_parse_args($args['data'], $action['data']);
			$data_attr	= $data ? 'data-filter=\''.$this->parse_data_filter($data).'\'' : '';
		}else{
			$class		= $action['key'] == 'move' ? 'list-table-move-action' : 'list-table-action';
			$data_attr	= $this->generate_data_attr($action, $args);
		}

		if($tag == 'a'){
			$href	= $href ?? 'javascript:;';
			$attr	.= 'href="'.$href.'" ';
		}

		if($args['class']){
			$class	.= ' '.$args['class'];
		}

		$attr	.= ' class="'.$class.'" ';

		if($args['style']){
			$attr	.= ' style="'.esc_attr($args['style']).'" ';
		}

		$attr	.= ' '.$data_attr;

		if($args['title']){
			$title	= $args['title'];
		}else{
			if($this->layout == 'calendar' && (!isset($args['dashicon']) || $args['dashicon'])){
				$title	= '<span class="dashicons dashicons-'.esc_attr($action['dashicon']).'"></span>';
			}else{
				$title	= $action['title'];
			}
		}

		return '<'.$tag.' '.$attr.'>'.$title.'</'.$tag.'>';
	}

	private function current_user_can($action='', $id=0){
		if($action){
			if($action = $this->get_action($action)){
				$action_key	= $action['key'];
				$capability	= $action['capability'];
			}else{
				return false;
			}
		}else{
			$action_key	= '';
			$capability	= $this->capability;
		}

		return ($capability == 'read' || current_user_can($capability, $id, $action_key));
	}

	private function generate_data_attr($action, $args=[]){
		$args	= wp_parse_args($args, ['type'=>'button', 'id'=>0, 'data'=>[], 'bulk'=>false, 'ids'=>[]]);
		$attr	= 'data-action="'.$action['key'].'"';

		$data_attrs	= [];

		if($args['bulk']){
			$data_attrs['bulk']		= $args['bulk'];
			$data_attrs['ids']		= $args['ids'] ? http_build_query($args['ids']) : '';
			$data_attrs['nonce']	= $this->create_nonce('bulk_'.$action['key']);
		}else{
			$data_attrs['id']		= $args['id'];
			$data_attrs['nonce']	= $this->create_nonce($action['key'], $args['id']);
		}

		if($args['type'] == 'button'){
			foreach(['direct', 'confirm', 'width'] as $action_attr){
				if(isset($action[$action_attr])){
					$data_attrs[$action_attr]	= $action[$action_attr];
				}
			}

			if(!isset($data_attrs['width']) && isset($action['tb_width'])){
				$data_attrs['width']	= $action['tb_width'];
			}
		}else{
			if(isset($action['next'])){
				$data_attrs['next']	= $action['next'];
			}
		}

		$defaults	= $action['data'] ?? [];

		if($data = wp_parse_args($args['data'], $defaults)){
			$data_attrs['data']	= http_build_query($data);
		}

		foreach($data_attrs as $data_key => $data_value){
			if($data_value || $data_value === 0){
				$attr	.= ' data-'.$data_key.'="'.$data_value.'"';
			}
		}

		return $attr;
	}

	private function parse_data_filter($filters){
		$data_filters	= [];

		foreach($filters as $name => $value){
			$data_filters[]	= ['name'=>$name, 'value'=>$value];
		}

		return wpjam_json_encode($data_filters);
	}

	public function get_filter_link($filters, $title, $class=''){
		$title_attr	= esc_attr(wp_strip_all_tags($title, true));

		return '<a href="javascript:;" title="'.$title_attr.'" class="list-table-filter '.$class.'" data-filter=\''.$this->parse_data_filter($filters).'\'>'.$title.'</a>';
	}

	public function single_row($raw_item){
		if(!is_array($raw_item) || is_object($raw_item)){
			$raw_item	= $this->call_model_method('get', $raw_item);
		}

		if(empty($raw_item)){
			echo '';
			return;
		}

		$raw_item	= (array)$raw_item;

		$this->call_model_method('before_single_row', $raw_item);

		$attr	= '';
		$class	= '';

		if($this->primary_key){
			$id	= $raw_item[$this->primary_key];
			$id	= str_replace('.', '-', $id);

			$attr	.= ' data-id="'.$id.'"';
			$attr	.= ' id="'.$this->singular.'-'.$id.'"';
			$class	.= 'tr-'.$id;
		}

		$item	= $this->render_item($raw_item);

		if(isset($item['style'])){
			$attr	.= ' style="'.$item['style'].'"';
		}

		if(isset($item['class'])){
			$class	.= ' '.$item['class'];
		}

		$attr	.= ' class="'.$class.'"';

		echo '<tr '.$attr.'>';

		$this->single_row_columns($item);

		echo '</tr>';

		$this->call_model_method('after_single_row', $item, $raw_item);
	}

	protected function render_item($raw_item){
		$item		= (array)$raw_item;
		$item_id	= $item[$this->primary_key];

		if($this->primary_key){
			$item['row_actions']	= $this->get_row_actions($item_id);
		}

		if($this->primary_key == 'id'){
			$row_actions['id']	= 'ID：'.$item_id;	// 显示 id
		}

		return $this->call_model_method('render_item', $item);
	}

	public function column_default($item, $column_name){
		$column_value	= $item[$column_name] ?? null;

		if($this->primary_key){
			$column_value	= $this->column_callback($column_value, $column_name, $item[$this->primary_key]);
		}

		return $column_value ?? '';
	}

	public function column_cb($item){
		if($this->primary_key){
			$item_id	= $item[$this->primary_key];

			if($this->current_user_can('', $item_id)){
				$name	= isset($item['name']) ? strip_tags($item['name']) : $item_id;

				return '<label class="screen-reader-text" for="cb-select-'.esc_attr($item_id).'">选择'.$name.'</label>'.'<input class="list-table-cb" type="checkbox" name="ids[]" value="'.esc_attr($item_id).'" id="cb-select-'.esc_attr($item_id). '" />';
			}
		}
		
		return '<span class="dashicons dashicons-minus"></span>';
	}

	protected function is_filterable_column($column_name){
		$filterable_fields	= $this->call_model_method('get_filterable_fields');

		return $filterable_fields && in_array($column_name, $filterable_fields);
	}

	protected function get_column_field($column_name){
		return $this->column_fields[$column_name] ?? [];
	}

	protected function column_callback($column_value, $column_name, $id){
		$field	= $this->get_column_field($column_name);

		if(empty($field)){
			return null;
		}

		if(is_null($column_value) && isset($field['default'])){
			$column_value	= $field['default'];
		}

		if(!empty($field['column_callback'])){
			return call_user_func($field['column_callback'], $id, $column_name, $column_value);
		}

		$options	= $field['options'] ?? [];
		$filterable	= $this->is_filterable_column($column_name);

		if($options){
			if($field['type'] == 'checkbox' && is_array($column_value)){
				$option_values	= [];

				foreach($column_value as $_column_value){
					$option_value	= $options[$_column_value] ?? $_column_value;

					if(is_array($option_value)){
						$option_value	= $option_value['title'] ?? '';
					}

					if($filterable){
						$option_value	= $this->get_filter_link([$column_name=>$_column_value], $option_value);
					}

					$option_values[]	= $option_value;
				}

				return implode(',', $option_values);
			}else{
				$option_value	= $options[$column_value] ?? $column_value;

				if(is_array($option_value)){
					$option_value	= $option_value['title'] ?? '';
				}

				if($filterable){
					$option_value =	$this->get_filter_link([$column_name=>$column_value], $option_value);
				}

				return $option_value;
			}
		}else{
			if($filterable){
				$column_value	= $this->get_filter_link([$column_name=>$column_value], $column_value);
			}

			return $column_value;
		}
	}

	public function list_table(){
		$this->views();

		echo '<form action="#" id="list_table_form" class="list-table-form" method="POST">';

		if($this->is_searchable()){
			$this->search_box('搜索', 'wpjam');
			echo '<br class="clear" />';
		}

		$this->display(); 

		echo '</form>';
	}

	public function list_page(){
		$class	= 'list-table';

		if($this->layout){
			$class	.= ' layout-'.$this->layout;
		}

		echo '<div class="'.$class.'">';

		$this->list_table();

		echo '</div>';

		return true;
	}

	public function ajax_response(){
		$action_type	= wpjam_get_parameter('list_action_type', ['method'=>'POST', 'sanitize_callback'=>'sanitize_key']);

		if($action_type == 'list'){
			if($_POST['data']){
				foreach(wp_parse_args($_POST['data']) as $key => $value){
					$_REQUEST[$key]	= $value;
				}
			}

			$result	= $this->prepare_items();

			if(is_wp_error($result)){
				wpjam_send_json($result);
			}

			ob_start();
			$this->list_table();
			$data	= ob_get_clean();
			$this->send_json(['errcode'=>0, 'errmsg'=>'', 'data'=>$data, 'type'=>'list']);
		}

		$list_action	= wpjam_get_parameter('list_action', ['method'=>'POST']);

		if(!$list_action) {
			wpjam_send_json(['errcode'=>'invalid_action', 'errmsg'=>'非法操作']);
		}

		$action	= $this->get_action($list_action);

		if(!$action) {
			wpjam_send_json(['errcode'=>'invalid_action', 'errmsg'=>'非法操作']);
		}

		$nonce		= wpjam_get_parameter('_ajax_nonce',['method'=>'POST', 'default'=>'']);
		$id			= wpjam_get_parameter('id',			['method'=>'POST', 'default'=>'']);
		$ids		= wpjam_get_parameter('ids',		['method'=>'POST', 'sanitize_callback'=>'wp_parse_args', 'default'=>[]]);
		$bulk		= wpjam_get_parameter('bulk',		['method'=>'POST', 'sanitize_callback'=>'boolval']);
		$defaults	= wpjam_get_parameter('defaults',	['method'=>'POST', 'sanitize_callback'=>'wp_parse_args', 'default'=>[]]);
		$data		= wpjam_get_parameter('data',		['method'=>'POST', 'sanitize_callback'=>'wp_parse_args', 'default'=>[]]);
		$data		= wpjam_array_merge($defaults, $data);

		if($bulk){
			if($action_type != 'form'){
				if(!$this->verify_nonce($nonce, 'bulk_'.$list_action)){
					wpjam_send_json(['errcode'=>'invalid_nonce', 'errmsg'=>'非法操作']);
				}
			}

			foreach($ids as $_id){
				if(!$this->current_user_can($action, $_id)){
					wpjam_send_json(['errcode'=>'bad_authentication', 'errmsg'=>'无权限']);
				}
			}
		}else{
			if($action_type != 'form'){
				if(!$this->verify_nonce($nonce, $list_action, $id)){
					wpjam_send_json(['errcode'=>'invalid_nonce',	'errmsg'=>'非法操作']);
				}
			}

			if(!$this->current_user_can($action, $id)){
				wpjam_send_json(['errcode'=>'bad_authentication', 'errmsg'=>'无权限']);
			}
		}
		
		$response	= ['errmsg'=>'', 'page_title'=>$action['page_title'], 'type'=>$action['response'], 'bulk'=>$bulk, 'ids'=>$ids, 'id'=>$id];
		$form_args	= ['action_type'=>$action_type, 'response_type'=>$action['response'], 'bulk'=>$bulk, 'ids'=>$ids, 'id'=>$id];

		if($action_type == 'form'){
			$form_args['data']	= $data;
			$ajax_form			= $this->ajax_form($list_action, $form_args);

			if(is_wp_error($ajax_form)){
				wpjam_send_json($ajax_form);
			}

			$this->send_json(array_merge($response, ['form'=>$ajax_form]));
		}elseif($action_type == 'direct'){
			if($bulk){
				$result	= $this->list_action($list_action, $ids); 
			}else{
				if(in_array($list_action, ['move', 'up', 'down'])){
					$result	= $this->list_action('move', $id, $data);
				}else{
					$result	= $this->list_action($list_action, $id);

					if($list_action == 'duplicate'){
						$id = $result;
					}
				}
			}
		}elseif($action_type == 'submit'){
			if($action['response'] != 'form'){
				$form_args['data']	= $defaults;

				$id_or_ids	= $bulk ? $ids : $id;

				if($fields	= $this->get_fields($list_action, $id_or_ids, ['include_prev'=>true])){
					if(is_wp_error($fields)){
						wpjam_send_json($fields);
					}

					$data	= wpjam_validate_fields_value($fields, $data);

					if(is_wp_error($data)){
						wpjam_send_json($data);
					}
				}

				$result	= $this->list_action($list_action, $id_or_ids, $data); 
			}else{
				$form_args['data']	= $data;

				$result	= null;
			}
		}

		if($result && is_wp_error($result)){
			wpjam_send_json($result);
		}

		$data	= '';

		if($action['response'] == 'append'){
			$response['data']	= $result;
			$this->send_json($response);
		}elseif($action['response'] == 'list'){
			$result	= $this->prepare_items();

			if(is_wp_error($result)){
				wpjam_send_json($result);
			}

			ob_start();
			$this->list_table();
			$data	= ob_get_clean();
		}elseif($action['response'] == 'redirect'){
			if(is_string($result)){
				$response['url']	= $result;
			}

			$this->send_json($response);
		}elseif(in_array($action['response'], ['delete', 'move', 'up', 'down', 'form'])){
			if($this->layout == 'calendar'){
				$data	= $this->render_dates($result);
			}
		}elseif(in_array($action['response'], ['add', 'duplicate'])){
			if($this->layout == 'calendar'){
				$data	= $this->render_dates($result);
			}else{
				if(is_array($result)){
					if(isset($result[$this->primary_key])){
						$id	= $result[$this->primary_key];
					}else{
						if(is_array(current($result)) && isset(current($result)[$this->primary_key])){
							$id	= current($result)[$this->primary_key];
						}else{
							wpjam_send_json(['errcode'=>'invalid_id', '无效的ID']);
						}
					}
				}else{
					$id		= $result;
				}

				if($id){
					$response['id']	= $form_args['id'] = $id;
					ob_start();
					$this->single_row($id);
					$data	= ob_get_clean();
				}
			}

			$result	= true;
		}else{
			$update_row	= $action['update_row'] ?? true;

			if($bulk){
				$items	= $this->call_model_method('get_by_ids', $ids);

				$data	= [];
				if($update_row){
					foreach($items as $id => $item){
						ob_start();
						$this->single_row($item);
						$data[$id]	= ob_get_clean();
					}
				}
			}else{
				if($update_row){
					if($this->layout == 'calendar'){
						$data	= $this->render_dates($result);
					}else{
						ob_start();
						$this->single_row($id);
						$data	= ob_get_clean();
					}
				}
			}
		}

		$response['layout']	= $this->layout;
		$response['data']	= $data;

		if($action['response'] != 'form'){
			if($result && is_array($result) && !empty($result['errmsg']) && $result['errmsg'] != 'ok'){ // 有些第三方接口返回 errmsg ： ok
				$response['errmsg'] = $result['errmsg'];
			}else{
				$response['errmsg'] = $this->get_submit_text($action, $id).'成功';
			}
		}

		if($action_type == 'submit'){
			if(!in_array($action['response'], ['delete','list'])){
				$form_required	= true;

				if(!empty($action['next'])){
					$response['next']		= $action['next'];
					$response['page_title']	= $this->get_action($action['next'])['page_title'];

					if($action['response'] == 'form'){
						$response['errmsg']		= '';
					}
				}elseif(!empty($action['dismiss'])){
					$response['dismiss']	= true;
					$form_required			= false;
				}

				if($form_required){
					$ajax_form	= $this->ajax_form($list_action, $form_args);

					if(is_wp_error($ajax_form)){
						wpjam_send_json($ajax_form);
					}

					$response['form']	= $ajax_form;
				}
			}

			if(in_array($action['response'], ['add', 'duplicate'])){
				if(!empty($action['last'])){
					$response['last']	= true;
				}
			}
		}

		$this->send_json($response);
	}

	public function send_json($response){
		wpjam_send_json(apply_filters('wpjam_ajax_response', $response));
	}

	public function list_action($list_action='', $id=0, $data=null){
		$action	= $this->get_action($list_action);

		if(isset($action['callback']) && is_callable($action['callback'])){
			return call_user_func($action['callback'], $id, $data, $list_action);
		}else{
			if($this->data_type == 'form'){
				$result	= $this->call_model_list_action($list_action, $id, $data);
			}else{
				$result	= null;
			}

			return is_null($result) ? new WP_Error('empty_list_action', '没有定义该操作') : $result;
		}
	}

	protected function call_model_list_action($list_action, $id, $data){
		if(is_array($id)){
			if(method_exists($this->model, 'bulk_'.$list_action)){
				$result	= $this->call_model_method('bulk_'.$list_action, $id, $data);
			}else{
				foreach($id as $_id){
					$result	= $this->call_model_method($list_action, $_id, $data);

					if(is_wp_error($result)){
						return $result;
					}
				}
			}
		}else{
			$action		= $this->get_action($list_action);
			$response	= $action['response'];

			if($list_action == 'add'){
				$list_action	= 'insert';
			}elseif($list_action == 'edit'){
				$list_action	= 'update';
			}elseif($list_action == 'duplicate'){
				if(!is_null($data)){
					$list_action	= 'insert';
				}
			}

			if(!empty($action['overall'])){
				$result	= $this->call_model_method($list_action, $data);
			}elseif($list_action == 'insert' || $response == 'add'){
				$last	= $action['last'] ?? false;
				$result	= $this->call_model_method($list_action, $data, $last);
			}else{
				$result	= $this->call_model_method($list_action, $id, $data);
			}
		}

		return is_null($result) ? true : $result;;
	}

	public function ajax_form($list_action, $args=[]){
		$action	= $this->get_action($list_action);

		if($args['action_type'] == 'submit' && !empty($action['next'])){
			if($action['response'] == 'form'){
				$prev_action	= $action;	
			}

			$list_action	= $action['next'];
			$action			= $this->get_action($list_action);
		}

		$fields_args	= [];

		$data	= [];
		$id		= 0;
		$bulk	= $args['bulk'];

		if($bulk){
			$ids	= $args['ids'];
			$fields	= $this->get_fields($list_action, $ids);

			if(is_wp_error($fields)){
				return $fields;
			}
		}else{
			$id		= $args['id'];
			$fields	= $this->get_fields($list_action, $id);

			if(is_wp_error($fields)){
				return $fields;
			}

			if($id && ($args['action_type'] != 'submit' || $args['response_type'] != 'form')){
				if(!empty($action['data_callback']) && is_callable($action['data_callback'])){
					$data	= call_user_func($action['data_callback'], $id, $action['key'], $fields);
				}else{
					$data	= $this->call_model_method('get', $id);

					if(empty($data)){
						return new WP_Error('invalid_id', '无效的ID');
					}
				}

				if(is_wp_error($data)){
					return $data;
				}
			}

			if(isset($action['value_callback']) && is_callable($action['value_callback'])){
				$fields_args['value_callback']	= $action['value_callback'];
			}elseif($this->value_callback){
				$fields_args['value_callback']	= $this->value_callback;
			}
		}

		$form_fields	= wpjam_fields($fields, array_merge($fields_args, ['id'=>$id, 'data'=>wp_parse_args($data, $args['data']), 'echo'=>false]));

		$submit_button	= '';

		if(isset($prev_action) || !empty($action['prev'])){
			$prev_action	= $prev_action ?? $this->get_action($action['prev']);

			if($prev_action){
				$submit_button	.= '<input type="button" class="list-table-action button large" '.$this->generate_data_attr($prev_action, $args).' value="返回">&emsp;';
			}
		}

		$submit_text	= (!empty($action['next']) && $action['response'] == 'form') ? '下一步' : $this->get_submit_text($action, $id);
		$submit_button	.= $submit_text ? '<input type="submit" name="list-table-submit" id="list-table-submit" class="button-primary large"  value="'.$submit_text.'"> <span class="spinner"></span>' : '';

		$submit_button	= $submit_button ? '<p class="submit">'.$submit_button.'</p>' : '';
		$data_attrs		= $this->generate_data_attr($action, array_merge($args, ['type'=>'form']));

		return '<div class="list-table-action-notice notice inline is-dismissible hidden"></div>'.'<form method="post" id="list_table_action_form" action="#" '.$data_attrs.'>'.$form_fields.$submit_button.'</form>'.'<div class="card response" style="display:none;"></div>'; 

		return $output;
	}

	public function get_fields($key='', $id=0, $args=[]){
		$fields	= [];

		if($key){
			$action	= $this->get_action($key);

			if($action && !empty($action['direct'])){
				return[];
			}

			if(isset($action['fields'])){
				if(is_callable($action['fields'])){
					$fields	= call_user_func($action['fields'], $id, $key);

					if(is_wp_error($fields)){
						return $fields;
					}
				}elseif(is_array($action['fields'])){
					$fields	= $action['fields'];
				}
			}

			$fields	= $this->filter_fields($fields, $key, $id);

			if(is_wp_error($fields)){
				return $fields;
			}

			if(!empty($args['include_prev'])){
				if(!empty($action['prev'])){
					$prev	= $action['prev'];
					$args['prev_including']	= true;
					$prev_fields	= $this->get_fields($prev, $id, $args);

					if(is_wp_error($prev_fields)){
						return $prev_fields;
					}

					$fields		= array_merge($fields, $prev_fields);
				}
			}

			if(empty($args['prev_including'])){
				$primary_key	= $this->primary_key;

				if($primary_key && isset($fields[$primary_key]) && !in_array($key, ['add', 'duplicate'])){
					$fields[$primary_key]['type']	= 'view';
				}
			}
		}else{
			$fields	= $this->filter_fields($fields, $key, $id);
		}

		return $fields;
	}

	protected function filter_fields($fields, $key, $id){
		if(empty($fields)){
			$fields	= $this->call_model_method('get_fields', $key, $id) ?: [];

			if(is_wp_error($fields)){
				return $fields;
			}
		}

		return apply_filters_deprecated(wpjam_get_filter_name($this->singular, 'fields'), [$fields, $key, $id], 'WPJAM Basic 5.1');
	}

	protected function get_bulk_actions(){
		return $this->bulk_actions;
	}

	public function set_bulk_action_data_attr($html){
		return preg_replace_callback('/<select name="action.*?>(.*?)<\/select>/is', function($matches){
			return preg_replace_callback('/<option value="(.*?)".*?<\/option>/is', function($sub_matches){
				$key	= $sub_matches[1];

				if($key && $key != -1 && ($action = $this->get_action($key))){
					return str_replace('<option value="'.$key.'"', '<option value="'.$key.'" '.$this->generate_data_attr($action, ['bulk'=>true]).' ', $sub_matches[0]);
				}else{
					return $sub_matches[0];
				}
			}, $matches[0]);
		}, $html);
	}

	protected function get_table_classes() {
		$classes = parent::get_table_classes();

		return $this->fixed ? $classes : array_diff($classes, ['fixed']);
	}

	public function get_singular(){
		return $this->singular;
	}

	protected function get_primary_column_name(){
		return $this->primary_column;
	}

	protected function handle_row_actions($item, $column_name, $primary){
		return ($primary === $column_name && !empty($item['row_actions'])) ? $this->row_actions($item['row_actions'], false) : '';
	}

	public function row_actions($actions, $always_visible=true){
		return parent::row_actions($actions, $always_visible);
	}

	public function get_per_page(){
		if($this->per_page && is_numeric($this->per_page)){
			return $this->per_page;
		}

		if($option	= get_current_screen()->get_option('per_page', 'option')){
			$default	= get_current_screen()->get_option('per_page', 'default')?:50;
			return $this->get_items_per_page($option, $default);
		}

		return 50;
	}

	public function prepare_items(){
		$per_page	= $this->get_per_page();
		$offset		= ($this->get_pagenum()-1) * $per_page;
		$result		= $this->call_model_method('query_items', $per_page, $offset);

		if(is_wp_error($result)){
			return $result;
		}

		$this->items	= $result['items'] ?? [];
		$total_items	= $result['total'] ?? count($this->items);

		if($total_items){
			$this->set_pagination_args(['total_items'=>$total_items,	'per_page'=>$per_page]);
		}

		return true;
	}

	public function get_columns(){
		return $this->columns;
	}

	public function get_sortable_columns(){
		return $this->sortable_columns;
	}

	public function get_views(){
		return $this->call_model_method('get_views') ?: [];
	}

	public function extra_tablenav($which='top'){
		$this->call_model_method('extra_tablenav', $which);

		do_action(wpjam_get_filter_name($this->plural, 'extra_tablenav'), $which);

		if($which == 'top'){
			$this->overall_actions();
		}
	}

	public function overall_actions(){
		foreach($this->overall_actions as $action){
			echo '<div class="alignleft actions overallactions">'.$this->get_row_action($action, ['class'=>'button-primary button']).'</div>';
		}
	}

	public function print_column_headers($with_id=true) {
		foreach(['orderby', 'order'] as $key){
			if(isset($_REQUEST[$key])){
				$_GET[$key] = wpjam_get_data_parameter($key);
			}
		}

		parent::print_column_headers($with_id);
	}

	public function is_searchable(){
		if(empty($_REQUEST['s']) && (!$this->has_items() || $this->_pagination_args['total_pages'] <= 1)){
			return false;
		}

		return $this->search ?? $this->call_model_method('get_searchable_fields');
	}

	public function current_action(){
		return wpjam_get_parameter('modal_action', ['method'=>'REQUEST', 'default'=>parent::current_action()]);
	}

	public function _js_vars() {
		$current_action	= $this->current_action();
		$action			= $current_action ? $this->get_action($current_action) : null;

		if($action  && empty($action['direct'])){
			$data	= wpjam_get_parameter('data', ['sanitize_callback'=>function($value){
				return $value ? array_map('sanitize_textarea_field', wp_parse_args(urldecode($value))) : [];
			}]);

			$action_args	= ['list_action_type'=>'form', 'list_action'=>$current_action, 'data'=>($data ? http_build_query($data) : null)];

			if(isset($action['width'])){
				$action_args['width']	= $action['width'];
			}elseif(isset($action['tb_width'])){
				$action_args['width']	= $action['tb_width'];
			}else{
				$action_args['width']	= 720;
			}

			if($current_action !='add'){
				if($id = wpjam_get_parameter('id', ['sanitize_callback'=>'sanitize_text_field'])){
					$action_args['id']	= $id;
				}
			}
		}else{
			$action_args	= false;
		}

		$sortable	= $this->sortable ? ($this->sortable === true ? ['items'=>' >tr'] : $this->sortable) : false;
		$args		= ['current_action'=>$action_args, 'sortable'=>$sortable];

		printf("<script type='text/javascript'>wpjam_list_args = %s;</script>\n", wpjam_json_encode($args));

		$this->call_model_method('admin_footer');
	}
}

class WPJAM_Left_List_Table extends WPJAM_List_Table{
	protected $_left_pagination_args = [];

	public function col_left(){
		$result	= $this->call_model_method('col_left');;

		if($result && !is_wp_error($result) && is_array($result)){
			$this->set_left_pagination_args($result);
		}

		echo '<div class="tablenav bottom">';

		if($left_keys = array_filter($this->left_keys)){ 
			echo '<input type="hidden" id="wpjam_left_keys" name="wpjam_left_keys" value=\''. wpjam_json_encode($left_keys).'\' />';
		}

		$this->left_pagination();

		echo '</div>';
	}

	public function set_left_pagination_args($args){
		$args = wp_parse_args($args, [
			'total_items'	=> 0,
			'total_pages'	=> 0,
			'per_page'		=> 0,
		]);

		if (!$args['total_pages'] && $args['per_page'] > 0) {
			$args['total_pages']	= ceil($args['total_items']/$args['per_page']);
		}

		$this->_left_pagination_args = $args;
	}

	public function left_pagination(){
		if(empty($this->_left_pagination_args)){
			return;
		}

		$total_items	= $this->_left_pagination_args['total_items'];

		if(empty($total_items)){
			return;
		}

		$total_pages	= $this->_left_pagination_args['total_pages'];
		$current		= wpjam_get_data_parameter('left_paged') ?: 1;

		$disable_prev	= false;
		$disable_next	= false;

		if ( 1 == $current ) {
			$disable_prev	= true;
		}

		if ( $total_pages == $current ) {
			$disable_next	= true;
		}

		$page_links	= [];

		if ( $disable_prev ) {
			$page_links[]	= '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>';
		} else {
			$page_links[]	= sprintf(
				"<a class='prev-page button' href='javascript:;' data-left_paged='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
				max( 1, $current - 1 ),
				__( 'Previous page' ),
				'&lsaquo;'
			);
		}

		$html_current_page	= sprintf("<span class='current-page'>%s</span>", $current);
		$html_total_pages	= sprintf( "<span class='total-pages'>%s</span>", number_format_i18n( $total_pages ) );
		$page_links[]		= "<span class='tablenav-paging-text'>".$html_current_page.'/'.$html_total_pages.'</span>';

		if($disable_next){
			$page_links[]	= '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>';
		} else {
			$page_links[]	= sprintf("<a class='next-page button' href='javascript:;' data-left_paged='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
				min( $total_pages, $current + 1 ),
				__( 'Next page' ),
				'&rsaquo;'
			);
		}

		if($total_pages > 2){
			$page_links[]	= sprintf(
				"&emsp;<input class='current-page' id='left-current-page' type='text' name='paged' value='%s' size='%d' aria-describedby='table-paging' /><span class='tablenav-paging-text'><span class='button left-pagination' style='line-height:2; font-size: inherit;'>跳转</span></span>",
				$current,
				strlen( $total_pages )
			);
		}

		$output		= "\n<span class='pagination-links'>".join("\n", $page_links).'</span>';
		$page_class = $total_pages < 2 ? ' one-page' : '';

		echo "<div class='tablenav-pages{$page_class}'>$output</div>";
	}

	public function list_page(){
		echo '<div id="col-container" class="wp-clearfix">';

		echo '<div id="col-left">';
		echo '<div class="col-wrap left">';

		$this->col_left();

		echo '</div>';
		echo '</div>';

		echo '<div id="col-right">';
		echo '<div class="list-table col-wrap">';

		$this->list_table();

		echo '</div>';
		echo '</div>';

		echo '</div>';

		return true;
	}

	public function ajax_response(){
		$action_type	= wpjam_get_parameter('list_action_type', ['method'=>'POST', 'sanitize_callback'=>'sanitize_key']);

		if($action_type == 'left'){
			$result	= $this->prepare_items();

			if(is_wp_error($result)){
				wpjam_send_json($result);
			}

			ob_start();
			$this->list_table();
			$data	= ob_get_clean();

			ob_start();
			$this->col_left();
			$left	= ob_get_clean();

			$this->send_json(['errcode'=>0, 'errmsg'=>'', 'data'=>$data, 'left'=>$left, 'type'=>'left']);
		}

		parent::ajax_response();
	}
}

class WPJAM_Calendar_List_Table extends WPJAM_List_Table{
	private $year;
	private $month;

	public function prepare_items(){
		$this->year		= (int)wpjam_get_data_parameter('year') ?: current_time('Y');
		$this->month	= (int)wpjam_get_data_parameter('month') ?: current_time('m');

		if($this->month > 12){
			$this->month	= 12;
		}elseif($this->month < 1){
			$this->month	= 1;
		}

		if($this->year > 2200){
			$this->year	= 2200;
		}elseif($this->year < 1970){
			$this->year	= 1970;
		}

		$items	= $this->call_model_method('query_dates', $this->year, zeroise($this->month, 2));

		if(is_wp_error($items)){
			return $items;
		}

		$this->items	= $items;

		return true;
	}

	public function render_date($raw_item, $date){
		$day	= explode('-', $date)[2];
		$class	= 'day';

		if($date == current_time('Y-m-d')){
			$class	.= ' today';
		}

		$add_action		= $this->actions['add'] ?? [];
		$row_actions	= []; 

		if($raw_item){
			if(wp_is_numeric_array($raw_item)){
				if(isset($this->actions['add'])){
					$row_actions	= ['add'=>$this->get_row_action('add', ['data'=>['date'=>$date]])];
				}
			}else{
				$row_actions	= $this->get_row_actions($raw_item[$this->primary_key]);
			}
		}else{
			if(isset($this->actions['add'])){
				$row_actions	= ['add'=>$this->get_row_action('add', ['data'=>['date'=>$date]])];
			}
		}

		$out	= [];

		foreach($row_actions as $action => $link){
			$out[] = "<span class='$action'>$link</span>";
		}

		$out	= implode(' ', $out);
		$out	= '<div class="row-actions alignright">'.$out.'</div>';

		$item	= '';
		$item	.= '<div class="date-meta">';
		$item	.= '<span class="'.$class.'">'.$day.'</span>'."\n";
		$item	.= $out;
		$item	.= '</div>'."\n";
		$item	.= '<div class="date-content">'."\n";
		$item	.= $this->call_model_method('render_date', $raw_item, $date)."\n";
		$item	.= '</div>'."\n";

		return $item;
	}

	public function render_dates($result){
		$data	= [];

		foreach($result as $date => $item){
			$data[$date]	= $this->render_date($item, $date);
		}

		return $data;
	}

	public function display(){
		global $wp_locale;

		$year		= $this->year;
		$month		= zeroise($this->month, 2);
		$month_ts	= mktime(0, 0, 0, $this->month, 1, $this->year);	// 每月开始的时间戳
		$week_start	= (int)get_option('start_of_week');
		$week_pad	= calendar_week_mod(date('w', $month_ts) - $week_start);

		$this->display_tablenav('top');

		$weekdays	= '';

		for($wd_count = 0; $wd_count <= 6; $wd_count++){
			$weekday	= ($wd_count + $week_start) % 7;
			$class		= in_array($weekday, [0, 6]) ? 'weekend' : 'weekday';
			$weekday	= $wp_locale->get_weekday($weekday);
			
			$weekdays	.= '<th scope="col" class="'.$class.'" title="'.$weekday.'">'.$wp_locale->get_weekday_abbrev($weekday).'</th>'."\n";
		}

		echo '<table id="wpjam_calendar" class="widefat fixed" cellpadding="10" cellspacing="0">'."\n";

		// echo '<caption>'.sprintf(__('%1$s %2$d'), $wp_locale->get_month($this->month), $this->year).'</caption>'."\n";
		
		echo '<thead>'."\n";
		echo '<tr>'."\n".$weekdays.'</tr>'."\n";
		echo '</thead>'."\n";

		echo '<tfoot>'."\n";
		echo '<tr>'."\n".$weekdays.'</tr>'."\n";
		echo '</tfoot>'."\n";

		echo '<tbody>'."\n";
		echo '<tr>'."\n";

		if(0 != $week_pad){
			echo '<td colspan="'.(int)$week_pad.'" class="pad">&nbsp;</td>';
		}

		$new_row	= false;
		$days		= date('t', $month_ts);

		for($day=1; $day<=$days; ++$day){
			if($new_row){
				echo '</tr>'."\n";
				echo '<tr>'."\n";

				$new_row	= false;
			}

			$date	= $year.'-'.$month.'-'.zeroise($day, 2);
			$item	= $this->items[$date] ?? [];

			$class	= in_array($week_pad+$week_start, [0, 6, 7]) ? 'weekend' : 'weekday';

			echo '<td id="date_'.$date.'"" class="'.$class.'">'."\n";

			echo $this->render_date($item, $date);

			echo '</td>'."\n";

			$week_pad++;

			if($week_pad%7 == 0){
				$new_row 	= true;
				$week_pad	= 0;
			}
		}

		if($week_pad > 1){
			echo '<td class="pad" colspan="'.(int)(7-$week_pad).'">&nbsp;</td>'."\n";
		}

		echo '</tr>'."\n";
		echo '</tbody>'."\n";
		echo '</table>'."\n";

		$this->display_tablenav('bottom');
	}

	public function extra_tablenav($which='top'){
		global $wp_locale;

		if($which == 'top'){
			echo '<span style="font-size:x-large; padding:30px 0; text-align: center;">'.sprintf(__('%1$s %2$d'), $wp_locale->get_month($this->month), $this->year).'</span>';
		}

		parent::extra_tablenav($which);
	}

	public function pagination($which){
		global $wp_locale;

		if($this->month == 1){
			$prev_year	= $this->year - 1;
			$prev_month	= 12;
		}else{
			$prev_year	= $this->year;
			$prev_month	= $this->month - 1;
		}

		if($this->month == 12){
			$next_year	= $this->year + 1;
			$next_month	= 1;
		}else{
			$next_year	= $this->year;
			$next_month	= $this->month + 1;
		}

		$prev_text	= '<span class="screen-reader-text">'.sprintf(__('%1$s %2$d'), $wp_locale->get_month($prev_month), $prev_year).'</span><span aria-hidden="true">&lsaquo;</span>';
		$prev_text	= $this->get_filter_link(['year'=>$prev_year,'month'=>$prev_month], $prev_text, 'prev-month button');
		$next_text	= '<span class="screen-reader-text">'.sprintf(__('%1$s %2$d'), $wp_locale->get_month($next_month), $next_year).'</span><span aria-hidden="true">&rsaquo;</span>';
		$next_text	= $this->get_filter_link(['year'=>$next_year,'month'=>$next_month], $next_text, 'next-month button');

		$this_text	= $this->get_filter_link(['year'=>current_time('Y'),'month'=>current_time('m')], '&emsp;今日&emsp;', 'current-month button');

		echo '<div class="tablenav-pages">'."\n";
		echo '<span class="pagination-links">'."\n";
		echo $prev_text." \n".$this_text." \n".$next_text."\n";
		echo '</span">'."\n";
		echo '</div>'."\n";
	}

	public function get_views(){
		return [];
	}

	public function get_bulk_actions(){
		return [];
	}

	public function is_searchable(){
		return false;
	}
}

class WPJAM_List_Table_Setting{
	use WPJAM_Register_Trait;
}

class WPJAM_List_Table_Action{
	use WPJAM_Register_Trait;
}

class WPJAM_List_Table_Column{
	use WPJAM_Register_Trait;
}