<?php 
class WPJAM_Builtin_List_Table extends WPJAM_List_Table{
	public function filter_bulk_actions($bulk_actions=[]){
		return array_merge($bulk_actions, $this->bulk_actions);
	}

	public function filter_columns($columns){
		if($this->get_columns()){	// 在最后一个之前插入
			$column_names	= array_keys($columns);
			wpjam_array_push($columns, $this->get_columns(), end($column_names)); 
		}

		return $columns;
	}

	public function filter_sortable_columns($sortable_columns){
		return array_merge($sortable_columns, $this->get_sortable_columns());
	}

	public function filter_html($html){
		if(!wp_doing_ajax() && $this->bulk_actions){
			$html	= $this->set_bulk_action_data_attr($html);
		}

		return $this->single_row_replace($html);
	}

	public function get_custom_column_value($name, $id){
		if($object = WPJAM_List_Table_Column::get($name)){
			$column_value	= call_user_func([$this->model, 'value_callback'], $name, $id);

			return $object->callback($id, $name, $column_value);
		}
	}

	public function get_single_row($id){		
		return apply_filters('wpjam_single_row', parent::get_single_row($id), $id);
	}

	public function get_list_table(){
		return $this->single_row_replace(parent::get_list_table());
	}

	public function single_row_replace($html){
		return preg_replace_callback('/<tr id="'.$this->singular.'-(\d+)".*?>.*?<\/tr>/is', function($matches){
			return apply_filters('wpjam_single_row', $matches[0], $matches[1]);
		}, $html);
	}

	public function wp_list_table(){
		$screen	= get_current_screen();

		if(!isset($GLOBALS['wp_list_table'])){
			if($screen->base == 'upload'){
				$GLOBALS['wp_list_table']	= _get_list_table('WP_Media_List_Table', ['screen'=>$screen]);
			}elseif($screen->base == 'edit'){
				$GLOBALS['wp_list_table']	= _get_list_table('WP_Posts_List_Table', ['screen'=>$screen]);
			}elseif($screen->base == 'edit-tags'){
				$GLOBALS['wp_list_table']	= _get_list_table('WP_Terms_List_Table', ['screen'=>$screen]);
			}
		}

		return $GLOBALS['wp_list_table'];
	}

	public function call_model_list_action($id, $data, $list_action){
		if(is_array($id)){
			$return	= [];

			foreach($id as $_id){
				$result	= call_user_func([$this->model, 'update_meta'], $_id, $data);

				if(is_wp_error($result)){
					return $result;
				}elseif(is_array($result)){
					$return	= wpjam_array_merge($return, $result);
				}

				if($return){
					return $return;
				}
			}
		}else{
			$result	= call_user_func([$this->model, 'update_meta'], $id, $data);
		}

		return $result;
	}

	public function prepare_items(){
		$data	= wpjam_get_parameter('data',	['method'=>'POST', 'sanitize_callback'=>'wp_parse_args', 'default'=>[]]);

		foreach($data as $key=>$value){
			$_GET[$key]	= $_POST[$key]	= $value;
		}

		$this->wp_list_table()->prepare_items();
	}
}

class WPJAM_Posts_List_Table extends WPJAM_Builtin_List_Table{
	private $post_type	= '';

	public function __construct($args=[]){
		$screen		= get_current_screen();
		$post_type	= $screen->post_type;
		$pt_obj		= get_post_type_object($post_type);

		if(isset($args['actions']['add']) && empty($args['actions']['add']['capability'])){
			$args['actions']['add']['capability']	= $pt_obj->cap->create_posts;
		}

		$this->post_type	= $post_type;
		$this->model		= 'WPJAM_Post';

		if(wp_doing_ajax()){
			add_action('wp_ajax_wpjam-list-table-action',	[$this, 'ajax_response']);
		}else{
			add_action('admin_footer',	[$this, '_js_vars']);
		}

		add_action('pre_get_posts',	[$this, 'pre_get_posts']);

		add_filter('bulk_actions-'.$screen->id,	[$this, 'filter_bulk_actions']);

		if(!wp_doing_ajax() || (wp_doing_ajax() && $_POST['action']=='inline-save')){
			add_filter('wpjam_html',	[$this, 'filter_html']);
		}

		if($post_type == 'attachment'){
			add_filter('media_row_actions',	[$this, 'filter_row_actions'],1,2);

			add_filter('manage_media_columns',			[$this, 'filter_columns']);
			add_filter('manage_media_custom_column',	[$this, 'filter_custom_column'], 10, 2);
		}else{
			$row_actions_filter	= is_post_type_hierarchical($post_type) ? 'page_row_actions' : 'post_row_actions';

			add_filter($row_actions_filter,	[$this, 'filter_row_actions'], 1, 2);
			add_filter('map_meta_cap',		[$this, 'filter_map_meta_cap'], 10, 4);

			add_filter('manage_'.$post_type.'_posts_columns',		[$this, 'filter_columns']);
			add_action('manage_'.$post_type.'_posts_custom_column',	[$this, 'filter_custom_column'], 10, 2);
		}

		add_filter('manage_'.$screen->id.'_sortable_columns',	[$this, 'filter_sortable_columns']);

		// 一定要最后执行
		$this->_args	= $this->parse_args(array_merge($args, [
			'title'			=> $pt_obj->label,
			'singular'		=> 'post',
			'capability'	=> 'edit_post',
			'data_type'		=> 'post_meta',
			'form_id'		=> 'posts-filter'
		]));
	}

	public function filter_map_meta_cap($caps, $cap, $user_id, $args){
		if($cap == 'edit_post' && empty($args[0])){
			$pt_obj	= get_post_type_object($this->post_type);

			return $pt_obj->map_meta_cap ? [$pt_obj->cap->edit_posts] : [$pt_obj->cap->$cap];
		}

		return $caps;
	}

	public function prepare_items(){
		$_GET['post_type']	= $this->post_type;

		parent::prepare_items();
	}

	public function list_table(){
		$wp_list_table	= $this->wp_list_table();

		if($this->post_type == 'attachment'){
			echo '<form id="posts-filter" method="get">';

			$wp_list_table->views();	
		}else{
			$wp_list_table->views();

			$status	= wpjam_get_data_parameter('post_status', ['default'=>'all']);

			echo '<form id="posts-filter" method="get">';

			echo wpjam_get_field_html(['key'=>'post_status', 'type'=>'hidden', 'class'=>'post_status_page', 'value'=>$status]);

			if($show_sticky	= wpjam_get_data_parameter('show_sticky')){
				echo wpjam_get_field_html(['key'=>'show_sticky', 'type'=>'hidden', 'value'=>1]);
			}

			$wp_list_table->search_box(get_post_type_object($this->post_type)->labels->search_items, 'post');
		}

		$wp_list_table->display(); 

		echo '</form>';
	}

	protected function filter_fields($fields, $key, $id){
		if($key && $id && !is_array($id)){
			$fields	= array_merge(['title'=>['title'=>$this->title.'标题', 'type'=>'view', 'value'=>get_post($id)->post_title]], $fields);
		}

		return $fields;
	}

	public function single_row($raw_item){
		global $post, $authordata;

		$post		= is_numeric($raw_item) ? get_post($raw_item) : $raw_item;
		$authordata = get_userdata($post->post_author);

		if($post->post_type == 'attachment'){
			$post_owner = (get_current_user_id() == $post->post_author) ? 'self' : 'other';

			echo '<tr id="post-'.$post->ID.'" class="'.trim(' author-' . $post_owner . ' status-' . $post->post_status).'">';

			$this->wp_list_table()->single_row_columns($post);

			echo '</tr>';
		}else{
			$this->wp_list_table()->single_row($post);
		}
	}

	public function filter_bulk_actions($bulk_actions=[]){
		$split	= array_search((isset($bulk_actions['trash']) ? 'trash' : 'untrash'), array_keys($bulk_actions), true);

		return array_merge(array_slice($bulk_actions, 0, $split), $this->bulk_actions, array_slice($bulk_actions, $split));
	}

	public function filter_row_actions($row_actions, $post){
		foreach($this->get_row_actions($post->ID) as $key => $row_action){
			$action	= WPJAM_List_Table_Action::get($key);
			$status	= get_post_status($post);

			if($status == 'trash'){
				if($action->post_status && in_array($status, (array)$action->post_status)){
					$row_actions[$key]	= $row_action;
				}
			}else{
				if(is_null($action->post_status) || in_array($status, (array)$action->post_status)){
					$row_actions[$key]	= $row_action;
				}
			}
		}

		foreach(['trash', 'view'] as $key){
			if($row_action = wpjam_array_pull($row_actions, $key)){
				$row_actions[$key]	= $row_action;
			}
		}

		return array_merge($row_actions, ['post_id'=>'ID: '.$post->ID]);
	}

	public function filter_custom_column($name, $post_id){
		echo parent::get_custom_column_value($name, $post_id) ?? '';
	}

	public function filter_html($html){
		if(!wp_doing_ajax()){
			if($add_action = WPJAM_List_Table_Action::get('add')){
				$html	= preg_replace('/<a href=".*?" class="page-title-action">.*?<\/a>/i', $add_action->get_row_action(['class'=>'page-title-action']), $html);
			}
		}

		return parent::filter_html($html);
	}

	public function pre_get_posts($wp_query){
		if($sortable_columns = $this->get_sortable_columns()){
			$orderby	= $wp_query->get('orderby');

			if($orderby && is_string($orderby) && isset($sortable_columns[$orderby])){
				if($object = WPJAM_List_Table_Column::get($orderby)){
					$orderby_type	= $object->sortable_column ?? 'meta_value';

					if(in_array($orderby_type, ['meta_value_num', 'meta_value'])){
						$wp_query->set('meta_key', $orderby);
						$wp_query->set('orderby', $orderby_type);
					}else{
						$wp_query->set('orderby', $orderby);
					}
				}
			}
		}
	}
}

class WPJAM_Terms_List_Table extends WPJAM_Builtin_List_Table{
	private $taxonomy	= '';
	private $post_type	= '';

	public function __construct($args=[]){
		$screen		= get_current_screen();
		$taxonomy	= $screen->taxonomy;
		$tax_obj	= get_taxonomy($taxonomy);

		$this->taxonomy		= $taxonomy;
		$this->post_type	= $screen->post_type;
		$this->model		= 'WPJAM_Term';

		if($tax_obj->hierarchical){
			if($tax_obj->sortable){
				$args['sortable']	= [
					'items'			=> $this->get_sorteable_items(),
					'action_args'	=> ['row_action'=>false, 'callback'=>['WPJAM_Term', 'move']]
				];
			}

			if(isset($tax_obj->levels)){
				wpjam_register_list_table_action('children', ['title'=>'下一级']);
			}
		}

		if(wp_doing_ajax()){
			add_action('wp_ajax_wpjam-list-table-action', [$this, 'ajax_response']);
		}else{
			add_action('admin_footer',	[$this, '_js_vars']);
		}
		
		if(!wp_doing_ajax() || (wp_doing_ajax() && in_array($_POST['action'], ['inline-save-tax', 'add-tag']))){
			add_filter('wpjam_html',	[$this, 'filter_html']);
		}

		add_action('parse_term_query',	[$this, 'parse_term_query'], 0);

		add_filter('bulk_actions-'.$screen->id,	[$this, 'filter_bulk_actions']);
		add_filter($taxonomy.'_row_actions',	[$this, 'filter_row_actions'], 1, 2);

		add_filter('manage_'.$screen->id.'_columns',			[$this, 'filter_columns']);
		add_filter('manage_'.$taxonomy.'_custom_column',		[$this, 'filter_custom_column'], 10, 3);
		add_filter('manage_'.$screen->id.'_sortable_columns',	[$this, 'filter_sortable_columns']);

		$this->_args	= $this->parse_args(array_merge($args, [
			'title'			=> $tax_obj->label,
			'capability'	=> $tax_obj->cap->edit_terms,
			'singular'		=> 'tag',
			'data_type'		=> 'term_meta',
			'form_id'		=> 'posts-filter'
		]));
	}

	public function list_table(){
		$wp_list_table	= $this->wp_list_table();

		$tax_obj	= get_taxonomy($this->taxonomy);

		if($tax_obj->hierarchical && $tax_obj->sortable){
			$sortable_items	= 'data-sortable_items="'.$this->get_sorteable_items().'"';
		}else{
			$sortable_items	= '';
		}

		echo '<form id="posts-filter" '.$sortable_items.' method="get">';

		echo wpjam_get_field_html(['key'=>'taxonomy', 'type'=>'hidden', 'value'=>$this->taxonomy]);
		echo wpjam_get_field_html(['key'=>'post_type', 'type'=>'hidden', 'value'=>$this->post_type]);

		$wp_list_table->display(); 

		echo '</form>';
	}

	public function get_list_table(){
		return $this->append_parent_button(parent::get_list_table());
	}

	public function filter_html($html){
		$html	= $this->append_parent_button($html);

		return parent::filter_html($html);
	}

	protected function filter_fields($fields, $key, $id){
		if($key && $id && !is_array($id)){
			$fields	= array_merge(['title'=>['title'=>$this->title, 'type'=>'view', 'value'=>get_term($id)->name]], $fields);
		}

		return $fields;
	}

	public function get_sorteable_items(){
		$parent	= $this->get_parent();
		$level	= $parent ? ($this->get_level($parent)+1) : 0;

		return 'tr.level-'.$level;
	}

	public function get_parent(){
		$tax_obj	= get_taxonomy($this->taxonomy);

		if(!$tax_obj->hierarchical){
			return null;
		}

		$parent	= wpjam_get_data_parameter('parent');

		if(is_null($parent)){
			if($tax_obj->levels == 1){
				return 0;
			}

			return null;
		}else{
			return (int)$parent;
		}
	}

	public function get_level($term_id){
		$term	= get_term($term_id);

		return ($term && $term->parent) ? count(get_ancestors($term->term_id, $term->taxonomy, 'taxonomy')) : 0;
	}

	public function get_edit_tags_link($args=[]){
		$args	= wp_parse_args($args, ['taxonomy'=>$this->taxonomy, 'post_type'=>$this->post_type]);

		return admin_url(add_query_arg($args, 'edit-tags.php'));
	}

	public function append_parent_button($html){
		$tax_obj	= get_taxonomy($this->taxonomy);

		if($tax_obj->hierarchical && isset($tax_obj->levels) && $tax_obj->levels != 1){
			$parent	= $this->get_parent();

			if(is_null($parent)){
				$link	= $this->get_edit_tags_link(['parent'=>0]);
				$text	= '只显示第一级';
			}elseif($parent > 0){
				$link	= $this->get_edit_tags_link(['parent'=>0]);
				$text	= '返回第一级';
			}else{
				$link	= $this->get_edit_tags_link();
				$text	= '显示所有';
			}

			$button	= '<a href="'.$link.'" class="button button-primary list-table-href">'.$text.'</a>';
			$html	= preg_replace('/(<input type="submit" id="doaction" .*?>)/i', '$1 '.$button, $html);
		}

		return $html;
	}

	public function single_row($raw_item){
		$term	= is_numeric($raw_item) ? get_term($raw_item) : $raw_item;
		$level	= $this->get_level($term);

		$this->wp_list_table()->single_row($term, $level);
	}

	public function filter_row_actions($row_actions, $term){
		if(!in_array('slug', get_taxonomy($term->taxonomy)->supports)){
			unset($row_actions['inline hide-if-no-js']);
		}

		$row_actions	= array_merge($row_actions, $this->get_row_actions($term->term_id));

		if(isset($row_actions['children'])){
			$parent	= $this->get_parent();

			if((empty($parent) || $parent != $term->term_id) && get_term_children($term->term_id, $term->taxonomy)){
				$row_actions['children']	= '<a href="'.$this->get_edit_tags_link(['parent'=>$term->term_id]).'">下一级</a>';
			}else{
				unset($row_actions['children']);
			}
		}

		foreach(['delete', 'view'] as $key){
			if($row_action = wpjam_array_pull($row_actions, $key)){
				$row_actions[$key]	= $row_action;
			}
		}

		return array_merge($row_actions, ['term_id'=>'ID：'.$term->term_id]);
	}

	public function filter_columns($columns){
		$columns	= parent::filter_columns($columns);
		$tax_obj	= get_taxonomy($this->taxonomy);
		
		foreach(['slug', 'description'] as $key){
			if(!in_array($key, $tax_obj->supports)){
				unset($columns[$key]);
			}
		}

		return $columns;
	}

	public function filter_custom_column($value, $name, $id){
		return $this->get_custom_column_value($name, $id) ?? $value;
	}

	public function parse_term_query($term_query){
		if(!in_array('WP_Terms_List_Table', array_column(debug_backtrace(), 'class'))){
			return;
		}

		$term_query->query_vars['list_table_query']	= true;

		if($sortable_columns = $this->get_sortable_columns()){
			$orderby	= $term_query->query_vars['orderby'];

			if($orderby && isset($sortable_columns[$orderby])){
				if($object = WPJAM_List_Table_Column::get($orderby)){
					$orderby_type	= $object->sortable_column ?? 'meta_value';

					if(in_array($orderby_type, ['meta_value_num', 'meta_value'])){
						$term_query->query_vars['meta_key']	= $orderby;
						$term_query->query_vars['orderby']	= $orderby_type;
					}else{
						$term_query->query_vars['orderby']	= $orderby;
					}
				}
			}
		}

		$tax_obj	= get_taxonomy($this->taxonomy);

		if($tax_obj->hierarchical){
			$parent	= $this->get_parent();
			
			if($parent){
				$hierarchy	= _get_term_hierarchy($this->taxonomy);
				$term_ids	= $hierarchy[$parent] ?? [];
				$term_ids[]	= $parent;

				if($ancestors = get_ancestors($parent, $this->taxonomy)){
					$term_ids	= array_merge($term_ids, $ancestors);
				}

				$term_query->query_vars['include']	= $term_ids;
				// $term_query->query_vars['pad_counts']	= true;
			}elseif($parent === 0){
				$term_query->query_vars['parent']	= $parent;
			}
		}
	}
}

class WPJAM_Users_List_Table extends WPJAM_Builtin_List_Table{
	public function __construct($args=[]){
		$this->model	= 'WPJAM_User';

		if(wp_doing_ajax()){
			add_action('wp_ajax_wpjam-list-table-action', [$this, 'ajax_response']);
		}else{
			add_action('admin_footer',	[$this, '_js_vars']);
			add_filter('wpjam_html',	[$this, 'filter_html']);
		}

		add_filter('user_row_actions',	[$this, 'filter_row_actions'], 1, 2);

		add_filter('manage_users_columns',			[$this, 'filter_columns']);
		add_filter('manage_users_custom_column',	[$this, 'filter_custom_column'], 10, 3);
		add_filter('manage_users_sortable_columns',	[$this, 'filter_sortable_columns']);

		$this->_args	= $this->parse_args(array_merge($args, [
			'title'			=> '用户',
			'singular'		=> 'user',
			'capability'	=> 'edit_user',
			'data_type'		=> 'user_meta',
		]));
	}

	protected function filter_fields($fields, $key, $id){
		if($key && $id && !is_array($id)){
			$fields	= array_merge(['name'=>['title'=>'用户', 'type'=>'view', 'value'=>get_userdata($id)->display_name]], $fields);
		}

		return $fields;
	}

	public function single_row($raw_item){
		$wp_list_table = _get_list_table('WP_Users_List_Table', ['screen'=>get_current_screen()]);

		echo $wp_list_table->single_row($raw_item);
	}

	public function filter_row_actions($row_actions, $user){
		foreach($this->get_row_actions($user->ID) as $key => $row_action){
			$action	= WPJAM_List_Table_Action::get($key);

			if(is_null($action->roles) || array_intersect($user->roles, (array)$action->roles)){
				$row_actions[$key]	= $row_action;
			}
		}

		foreach(['delete', 'remove', 'view'] as $key){
			if($row_action = wpjam_array_pull($row_actions, $key)){
				$row_actions[$key]	= $row_action;
			}
		}

		return array_merge($row_actions, ['user_id'=>'ID: '.$user->ID]);
	}

	public function filter_custom_column($value, $name, $id){
		return $this->get_custom_column_value($name, $id) ?? $value;
	}
}