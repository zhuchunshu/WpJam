<?php
class WPJAM_Builtin_Page{
	protected static $instance	= null;

	public static function get_instance(){
		$screen_base	= $GLOBALS['current_screen']->base;

		if(is_null(self::$instance)){
			if(in_array($screen_base, ['term', 'edit-tags'])){
				$taxonomy	= $GLOBALS['current_screen']->taxonomy ?? '';

				self::$instance	= new WPJAM_Term_Page($taxonomy);
			}elseif(in_array($screen_base, ['edit', 'upload', 'post'])){
				$post_type	= $GLOBALS['current_screen']->base == 'upload' ? 'attachment' : ($GLOBALS['current_screen']->post_type ?? '');

				self::$instance	= new WPJAM_Post_Page($post_type);
			}elseif($screen_base == 'users'){
				self::$instance	= new WPJAM_User_Page();
			}else{
				self::$instance	= new self();
			}
		}

		return self::$instance;
	}

	public static function load(){
		do_action('wpjam_builtin_page_load', $GLOBALS['current_screen']->base, $GLOBALS['current_screen']);

		if($instance = self::get_instance()){
			$instance->page_load();
		}

		if(!wp_doing_ajax() && $instance->get_summary()){
			add_filter('wpjam_html', [$instance, 'page_summary']);
		}
	}

	protected $summary	= '';

	public function page_load(){}

	public function get_summary(){
		return apply_filters('wpjam_builtin_page_summary', $this->summary, $GLOBALS['current_screen']);
	}

	public function set_summary($summary, $append=true){
		if($append){
			$this->summary	.= $summary;
		}else{
			$this->summary	= $summary;
		}
	}

	public function page_summary($html){
		return str_replace('<hr class="wp-header-end">', '<hr class="wp-header-end">'.wpautop($this->get_summary()), $html);
	}
}

class WPJAM_Post_Page extends WPJAM_Builtin_Page{
	private $post_type;

	public static function get_post_id(){
		if(isset($_GET['post'])){
			$post_id	= (int)$_GET['post'];
		}elseif(isset($_POST['post_ID'])){
			$post_id	= (int)$_POST['post_ID'];
		}else{
			$post_id	= 0;
		}

		return $post_id;
	}

	protected function __construct($post_type){
		$this->post_type	= $post_type;
	}

	public function page_load(){
		$screen_base	= $GLOBALS['current_screen']->base;
		if($screen_base == 'post'){
			$edit_form_hook	= $this->post_type == 'page' ? 'edit_page_form' : 'edit_form_advanced';

			add_action($edit_form_hook,			[$this, 'on_edit_form'], 99);
			add_action('add_meta_boxes',		[$this, 'on_add_meta_boxes'], 10, 2);
			add_action('wp_after_insert_post',	[$this, 'on_after_insert_post'], 999, 2);

			add_filter('post_updated_messages',		[$this, 'filter_updated_messages']);
			add_filter('admin_post_thumbnail_html',	[$this, 'filter_admin_thumbnail_html'], 10, 2);
			add_filter('redirect_post_location',	[$this, 'filter_redirect_location'], 10, 2);

			add_filter('post_edit_category_parent_dropdown_args',	[$this, 'filter_edit_category_parent_dropdown_args']);

			if($taxonomies	= get_object_taxonomies($this->post_type, 'objects')){
				$style	= [];

				foreach($taxonomies as $taxonomy => $tax_obj){
					if(isset($tax_obj->levels) && $tax_obj->levels == 1){
						$style[]	= '#new'.$taxonomy.'_parent{ display:none; }';
					}
				}

				if($style){
					wp_add_inline_style('list-tables', "\n".implode("\n", $style));
				}
			}
		}elseif(in_array($screen_base, ['edit', 'upload'])){
			foreach(WPJAM_Post_Option::get_registereds() as $name => $object){
				if($object->is_available_for_post_type($this->post_type) && $object->list_table && $object->title){
					wpjam_register_list_table_action('set_'.$name, wp_parse_args($object->to_array(), [
						'page_title'	=> '设置'.$object->title,
						'submit_text'	=> '设置'
					]));
				}
			}

			if($screen_base == 'upload'){
				$mode	= get_user_option('media_library_mode', get_current_user_id()) ?: 'grid';

				if(isset($_GET['mode']) && in_array($_GET['mode'], ['grid', 'list'], true)){
					$mode	= $_GET['mode'];
				}

				if($mode == 'grid'){
					return;
				}
			}

			$GLOBALS['wpjam_list_table']	= new WPJAM_Posts_List_Table();
		}
	}

	public function filter_updated_messages($messages){
		if(!in_array($this->post_type, ['page', 'post', 'attachment'])){
			$key	= get_post_type_object($this->post_type)->hierarchical ? 'page' : 'post';

			if(isset($messages[$key])){
				$messages[$key]	= array_map([$this, 'updated_message_replace'], $messages[$key]);
			}
		}

		return $messages;
	}

	public function updated_message_replace($message){
		$label_name	= get_post_type_object($this->post_type)->labels->name;
		return str_replace(['文章', '页面'], [$label_name, $label_name], $message);
	}

	public function filter_admin_thumbnail_html($content, $post_id){
		if($post_id){
			$thumbnail_size	= get_post_type_object($this->post_type)->thumbnail_size ?? '';
			$content		.= $thumbnail_size ? wpautop('尺寸：'.$thumbnail_size) : '';
		}

		return $content;
	}

	public function filter_redirect_location($location, $post_id){
		$referer	= wp_get_referer();
		$fragment	= parse_url($referer, PHP_URL_FRAGMENT);

		if(empty($fragment)){
			return $location;
		}

		if(parse_url($location, PHP_URL_FRAGMENT)){
			return $location;
		}

		return $location.'#'.$fragment;
	}

	public function filter_edit_category_parent_dropdown_args($args){
		$levels	= get_taxonomy($args['taxonomy'])->levels ?? 0;

		if($levels == 1){
			$args['parent']	= -1;
		}elseif($levels > 1){
			$args['depth']	= $levels - 1;
		}

		return $args;
	}

	public function on_after_insert_post($post_id, $post){
		// 非 POST 提交不处理
		// 自动草稿不处理
		// 自动保存不处理
		// 预览不处理
		if(
			$_SERVER['REQUEST_METHOD'] != 'POST'  ||
			$post->post_status == 'auto-draft' || 
			(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || 
			(!empty($_POST['wp-preview']) && $_POST['wp-preview'] == 'dopreview')
		){
			return;
		}

		$post_data	= [];

		foreach(WPJAM_Post_Option::get_registereds() as $name => $object){
			if(!$object->is_available_for_post_type($this->post_type) || $object->list_table === 'only'){
				continue;
			}

			$fields	= $object->get_fields($post_id);

			if(empty($fields)){
				continue;
			}

			$data	= wpjam_validate_fields_value($fields);

			if(is_wp_error($data)){
				wp_die($data);
			}elseif(empty($data)){
				continue;
			}

			$update_callback	= $object->update_callback;

			if($update_callback){
				if(is_callable($update_callback)){
					$result	= call_user_func($update_callback, $post_id, $data, $fields);

					if(is_wp_error($result)){
						wp_die($result);
					}elseif($result === false){
						wp_die('未知错误');
					}
				}
			}else{
				$post_data	= array_merge($post_data, $data);
			}
		}

		$custom	= get_post_custom($post_id);

		foreach($post_data as $key => $value){
			if(empty($value)){
				if(isset($custom[$key])){
					delete_post_meta($post_id, $key);
				}
			}else{
				if(empty($custom[$key]) || maybe_unserialize($custom[$key][0]) != $value){
					WPJAM_Post::update_meta($post_id, $key, $value);
				}
			}
		}
	}

	public function on_add_meta_boxes($post_type, $post){
		$context	= (!function_exists('use_block_editor_for_post_type') || !use_block_editor_for_post_type($post_type)) ? 'wpjam' : 'normal';

		// 输出日志自定义字段表单
		foreach(WPJAM_Post_Option::get_registereds() as $name => $object){
			if($object->is_available_for_post_type($post_type) && $object->list_table !== 'only' && $object->title){
				$callback	= $object->callback ?? [$this, 'meta_box_cb'];
				$context	= $object->context ?? $context;
				$priority	= $object->priority ?? 'default';

				add_meta_box($name, $object->title, $callback, $post_type, $context, $priority);
			}
		}
	}

	public function meta_box_cb($post, $meta_box){
		$object	= WPJAM_Post_Option::get($meta_box['id']);

		if($object->summary){
			echo wpautop($object->summary);
		}

		$args	= [];

		$args['fields_type']	= $object->context == 'side' ? 'list' : 'table';
		$args['is_add']			= $GLOBALS['current_screen']->action == 'add';

		if(!$args['is_add']){
			$args['id']	= $post->ID;

			if($object->data){
				$args['data']	= $object->data;
			}else{
				if($object->value_callback && is_callable($object->value_callback)){
					$args['value_callback']	= $object->value_callback; 
				}else{
					$args['value_callback']	= ['WPJAM_Post', 'value_callback'];
				}
			}
		}

		wpjam_fields($object->get_fields($post->ID), $args);
	}

	public function on_edit_form($post){
		// 下面代码 copy 自 do_meta_boxes
		$context	= 'wpjam';
		$page		= $GLOBALS['current_screen']->id;
		$meta_boxes	= $GLOBALS['wp_meta_boxes'][$page][$context] ?? [];

		if(empty($meta_boxes)) {
			return;
		}

		$nav_tab_title	= '';
		$meta_box_count	= 0;

		foreach(['high', 'core', 'default', 'low'] as $priority){
			if(empty($meta_boxes[$priority])){
				continue;
			}

			foreach ((array)$meta_boxes[$priority] as $meta_box) {
				if(empty($meta_box['id']) || empty($meta_box['title'])){
					continue;
				}

				$meta_box_count++;
				$meta_box_title	= $meta_box['title'];
				$nav_tab_title	.= '<li><a class="nav-tab" href="#tab_'.$meta_box['id'].'">'.$meta_box_title.'</a></li>';
			}
		}

		if(empty($nav_tab_title)){
			return;
		}

		echo '<div id="'.htmlspecialchars($context).'-sortables">';
		echo '<div id="'.$context.'" class="postbox tabs">' . "\n";

		if($meta_box_count == 1){
			echo '<div class="postbox-header">';
			echo '<h2 class="hndle">'.$meta_box_title.'</h2>';
			echo '</div>';
		}else{
			echo '<h2 class="nav-tab-wrapper"><ul>'.$nav_tab_title.'</ul></h2>';
		}

		echo '<div class="inside">';

		foreach (['high', 'core', 'default', 'low'] as $priority) {
			if (!isset($meta_boxes[$priority])){
				continue;
			}

			foreach ((array) $meta_boxes[$priority] as $meta_box) {
				if(empty($meta_box['id']) || empty($meta_box['title'])){
					continue;
				}

				echo '<div id="tab_'.$meta_box['id'].'">';
				call_user_func($meta_box['callback'], $post, $meta_box);
				echo "</div>\n";
			}
		}

		echo "</div>\n";

		echo "</div>\n";
		echo "</div>";
	}
}

class WPJAM_Term_Page extends WPJAM_Builtin_Page{
	private $taxonomy;

	protected function __construct($taxonomy){
		$this->taxonomy	= $taxonomy;
	}

	public function page_load(){
		add_filter('term_updated_messages',			[$this, 'filter_updated_messages']);
		add_filter('taxonomy_parent_dropdown_args',	[$this, 'filter_parent_dropdown_args'], 10, 3);

		if($GLOBALS['current_screen']->base == 'term'){
			add_action($this->taxonomy.'_edit_form_fields',	[$this, 'on_edit_form_fields']);
		}elseif($GLOBALS['current_screen']->base == 'edit-tags'){
			add_action('edited_term',	[$this, 'on_edited_term'], 10, 3);

			if($term_options = WPJAM_Term_Option::get_registereds()){
				foreach($term_options as $name => $object){
					if($object->is_available_for_taxonomy($this->taxonomy) && $object->list_table){
						wpjam_register_list_table_action('set_'.$name, wp_parse_args($object->to_array(), [
							'page_title'	=> '设置'.$object->title,
							'submit_text'	=> '设置',
							'fields'		=> [$object, 'get_fields']
						]));
					}
				}

				if(wp_doing_ajax()){
					if($_POST['action'] == 'add-tag'){
						add_filter('pre_insert_term',	[$this, 'filter_pre_insert_term'], 10, 2);
						add_action('created_term', 		[$this, 'on_created_term'], 10, 3);
					}
				}else{
					add_action($this->taxonomy.'_add_form_fields', 	[$this, 'on_add_form_fields']);
				}
			}

			$GLOBALS['wpjam_list_table']	= new WPJAM_Terms_List_Table();
		}

		$supports	= get_taxonomy($this->taxonomy)->supports;

		if(get_taxonomy($this->taxonomy)->levels == 1){
			$supports	= array_diff($supports, ['parent']);
		}

		$style		= [
			'.fixed th.column-slug{ width:16%; }',
			'.fixed th.column-description{width:22%;}',
			'td.column-name img.wp-term-image{float:left; margin:0px 10px 10px 0;}',
			'.form-field.term-parent-wrap p{display: none;}',
			'.form-field span.description{color:#666;}'
		];

		foreach (['slug', 'description', 'parent'] as $key) { 
			if(!in_array($key, $supports)){
				$style[]	= '.form-field.term-'.$key.'-wrap{display: none;}'."\n";
			}
		}

		wp_add_inline_style('list-tables', "\n".implode("\n", $style));
	}

	public function filter_updated_messages($messages){
		if(!in_array($this->taxonomy, ['post_tag', 'category'])){
			$messages[$this->taxonomy]	= array_map([$this, 'updated_message_replace'], $messages['_item']);
		}

		return $messages;
	}

	public function updated_message_replace($message){
		$label_name	= get_taxonomy($this->taxonomy)->labels->name;
		return str_replace(['项目', 'Item'], [$label_name, ucfirst($label_name)], $message);
	}

	public function filter_parent_dropdown_args($args, $taxonomy, $action_type){
		if(($levels = get_taxonomy($this->taxonomy)->levels) && $levels > 1){
			$args['depth']	= $levels - 1;

			if($action_type == 'edit'){
				$term_id		= $args['exclude_tree'];
				$term_levels	= count(get_ancestors($term_id, $taxonomy, 'taxonomy'));
				$child_levels	= $term_levels;

				$children	= get_term_children($term_id, $taxonomy);
				if($children){
					$child_levels = 0;

					foreach($children as $child){
						$new_child_levels	= count(get_ancestors($child, $taxonomy, 'taxonomy'));
						if($child_levels	< $new_child_levels){
							$child_levels	= $new_child_levels;
						}
					}
				}

				$redueced	= $child_levels - $term_levels;

				if($redueced < $args['depth']){
					$args['depth']	-= $redueced;
				}else{
					$args['parent']	= -1;
				}
			}
		}

		return $args;
	}

	public function get_fields($action='add', $term_id=0){
		$tax_fields	= [];

		foreach(WPJAM_Term_Option::get_registereds() as $object){
			if($object->is_available_for_taxonomy($this->taxonomy) && $object->list_table !== 'only'){
				foreach($object->get_fields($term_id) as $key => $field){
					if(empty($field['action']) || $field['action'] == $action){
						if(empty($field['value_callback']) && $object->value_callback && is_callable($object->value_callback)){
							$field['value_callback']	= $object->value_callback;
						}

						$tax_fields[$key]	= wpjam_array_except($field, 'action');
					}
				}
			}
		}

		return $tax_fields;
	}

	public function on_add_form_fields($taxonomy){
		if($fields = $this->get_fields('add')){
			wpjam_fields($fields, [
				'fields_type'	=> 'div',
				'wrap_class'	=> 'form-field',
				'is_add'		=> true
			]);
		}
	}

	public function on_edit_form_fields($term){
		if($fields = $this->get_fields('edit', $term->term_id)){
			wpjam_fields($fields, [
				'fields_type'	=> 'tr',
				'wrap_class'	=> 'form-field',
				'id'			=> $term->term_id,
				'value_callback'=> ['WPJAM_Term', 'value_callback']
			]);
		}
	}

	public function filter_pre_insert_term($term, $taxonomy){
		if($fields = $this->get_fields('add')){
			$data	= wpjam_validate_fields_value($fields);

			if(is_wp_error($data)){
				return $data;
			}
		}

		return $term;
	}

	public function on_created_term($term_id, $tt_id, $taxonomy){
		if($fields = $this->get_fields('add')){
			if($data = wpjam_validate_fields_value($fields)){
				foreach ($data as $key => $value) {
					if($value){
						WPJAM_Term::update_meta($term_id, $key, $value);
					}
				}
			}
		}
	}

 	public function on_edited_term($term_id, $tt_id, $taxonomy){
 		if($fields = $this->get_fields('edit', $term_id)){
			$data	= wpjam_validate_fields_value($fields);

			if(is_wp_error($data)){
				wp_die($data);
			}

			if($data){
				foreach ($data as $key => $value) {
					if($value){
						WPJAM_Term::update_meta($term_id, $key, $value);
					}else{
						if(metadata_exists('term', $term_id, $key)){
							delete_term_meta($term_id, $key);
						}
					}
				}
			}
		}
	}
}

class WPJAM_User_Page extends WPJAM_Builtin_Page{
	protected function __construct(){}

	public function page_load(){
		$GLOBALS['wpjam_list_table']	= new WPJAM_Users_List_Table();
	}
}