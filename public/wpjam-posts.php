<?php
class WPJAM_Posts_Admin{
	private $post_type;
	
	public function __construct($post_type){
		$this->post_type	= $post_type;
	}

	public function get_views($post_id){
		$post_views	= wpjam_get_post_views($post_id, false) ?: 0;
		$pt_obj		= get_post_type_object($this->post_type);

		if(current_user_can($pt_obj->cap->edit_others_posts)){
			$post_views	= wpjam_get_list_table_row_action('update_views',	['id'=>$post_id,	'title'=>$post_views,]);
		}

		return $post_views;
	}

	public function update_views($post_id, $data){
		return isset($data['views']) ? update_post_meta($post_id, 'views', $data['views']) : true;
	}

	public function set_thumbnail($post_id, $data){
		return WPJAM_Post::update_meta($post_id, '_thumbnail_id', $data['_thumbnail_id']);
	}

	public function add_users_dropdown($post_type){
		wp_dropdown_users([
			'name'						=> 'author',
			'who'						=> 'authors',
			'hide_if_only_one_author'	=> true,
			'show_option_all'			=> $post_type == 'attachment' ? '所有上传者' : '所有作者',
			'selected'					=> (int)wpjam_get_parameter('author', ['method'=>'REQUEST'])
		]);
	}

	public function add_orders_dropdown($post_type){
		$wp_list_table		= $GLOBALS['wp_list_table'] ?: _get_list_table('WP_Posts_List_Table', ['screen'=>$post_type]);
		$orderby_options	= [
			''			=> '排序',
			'date'		=> '日期', 
			'modified'	=> '修改时间',
			'ID'		=> get_post_type_object($post_type)->labels->name.'ID',
			'title'		=> '标题', 
		];

		if(post_type_supports($post_type, 'comments')){
			$orderby_options['comment_count']	= '评论';
		}

		if(is_post_type_hierarchical($post_type)){
			// $orderby_options['parent']	= '父级';
		}

		list($columns, $hidden, $sortable_columns, $primary) = $wp_list_table->get_column_info();

		$default_sortable_columns	= $wp_list_table->get_sortable_columns();

		foreach($sortable_columns as $sortable_column => $data){
			if(isset($default_sortable_columns[$sortable_column])){
				continue;
			}

			if(isset($columns[$sortable_column])){
				$orderby_options[$sortable_column]	= $columns[$sortable_column];
			}
		}

		echo wpjam_get_field_html([
			'title'		=>'',
			'key'		=>'orderby',
			'type'		=>'select',
			'value'		=>wpjam_get_parameter('orderby', ['method'=>'REQUEST', 'sanitize_callback'=>'sanitize_key']),
			'options'	=>$orderby_options
		]);

		echo wpjam_get_field_html([
			'title'		=>'',
			'key'		=>'order',
			'type'		=>'select',
			'value'		=>wpjam_get_parameter('order', ['method'=>'REQUEST', 'sanitize_callback'=>'sanitize_key', 'default'=>'DESC']),
			'options'	=>['desc'=>'降序','asc'=>'升序']
		]);
	}

	public static function load_plugin_page(){
		wpjam_register_plugin_page_tab('posts', [
			'title'			=> '文章列表',
			'function'		=> 'option',
			'option_name'	=> 'wpjam-basic',
			'load_callback'	=> ['WPJAM_Posts_Admin', 'load_option_page'],
			'order'			=> 20
		]);
	}

	public static function load_option_page(){
		wpjam_register_option('wpjam-basic', [
			'summary'	=> '文章设置把文章编辑的一些常用操作，提到文章列表页面，方便设置和操作，详细介绍请点击：<a href="https://blog.wpjam.com/m/wpjam-basic-posts/" target="_blank">文章设置</a>。',
			'fields'	=> [
				'post_list_set_thumbnail'	=> ['title'=>'缩略图',	'type'=>'checkbox',	'description'=>'在文章列表页显示和设置文章缩略图。'],
				'post_list_update_views'	=> ['title'=>'浏览数',	'type'=>'checkbox',	'description'=>'在文章列表页显示和修改文章浏览数。'],
				'post_list_author_filter'	=> ['title'=>'作者过滤',	'type'=>'checkbox',	'description'=>'在文章列表页支持通过作者进行过滤。'],
				'post_list_sort_selector'	=> ['title'=>'排序选择',	'type'=>'checkbox',	'description'=>'在文章列表页显示排序下拉选择框。'],
			],
			'site_default'	=> true
		]);
	}
}

wpjam_add_basic_sub_page('wpjam-posts', [
	'menu_title'	=> '文章设置',
	'summary'		=> '文章设置优化，增强文章列表和文章功能。',
	'load_callback'	=> ['WPJAM_Posts_Admin', 'load_plugin_page'],
	'function'		=> 'tab',
	'order'			=> 17
]);

add_action('wpjam_builtin_page_load', function($screen_base, $current_screen){
	if($screen_base == 'post'){
		if(wpjam_basic_get_setting('disable_block_editor')){
			add_filter('use_block_editor_for_post_type', '__return_false');
		}else{
			if(wpjam_basic_get_setting('disable_google_fonts_4_block_editor')){	// 古腾堡编辑器不加载 Google 字体
				wp_deregister_style('wp-editor-font');
				wp_register_style('wp-editor-font', '');
			}
		}

		// if(wpjam_basic_get_setting('disable_revision')){
		//	wp_deregister_script('autosave');
		// }

		if(wpjam_basic_get_setting('disable_trackbacks')){
			wp_add_inline_style('wpjam-style', "\n".'label[for="ping_status"]{display:none !important;}'."\n");
		}
	}elseif($screen_base == 'edit'){
		$post_type	= $current_screen->post_type;
		$pt_obj		= get_post_type_object($post_type);

		$instance	= new WPJAM_Posts_Admin($post_type);

		if($post_type == 'page'){
			wpjam_register_list_table_column('template', ['title'=>'模板', 'column_callback'=>'get_page_template_slug']);
		}

		if(wpjam_basic_get_setting('post_list_set_thumbnail') && (is_post_type_viewable($post_type) || post_type_supports($post_type, 'thumbnail'))){
			wpjam_register_list_table_action('set_thumbnail', [
				'title'			=> '设置',
				'page_title'	=> '设置特色图片',
				'fields'		=> ['_thumbnail_id'	=> ['title'=>'缩略图',	'type'=>'img',	'size'=>'600x0']],
				'callback'		=> [$instance, 'set_thumbnail'],
				'row_action'	=> false,
				'tb_width'		=> 500,
				'tb_height'		=> 400
			]);
		}

		if((is_post_type_viewable($post_type) && wpjam_basic_get_setting('post_list_update_views')) || !empty($pt_obj->viewable)){
			wpjam_register_list_table_action('update_views', [
				'title'			=> '修改',
				'page_title'	=> '修改浏览数',
				'fields'		=> ['views'	=> ['title'=>'浏览数',	'type'=>'number']],
				'capability'	=> $pt_obj->cap->edit_others_posts,
				'callback'		=> [$instance, 'update_views'],
				'row_action'	=> false,
				'tb_width'		=> 500
			]);

			wpjam_register_list_table_column('views', ['title'=>'浏览', 'column_callback'=>[$instance, 'get_views'], 'sortable_column'=>'views']);
		}

		if(wpjam_basic_get_setting('post_list_author_filter') && post_type_supports($post_type, 'author')){
			add_action('restrict_manage_posts', [$instance,	'add_users_dropdown'], 99);
		}

		if(wpjam_basic_get_setting('post_list_sort_selector')){
			add_action('restrict_manage_posts', [$instance,	'add_orders_dropdown'], 99);
		}

		wp_add_inline_style('list-tables', "\n".implode("\n", [
			'.tablenav .actions{padding:0 8px 8px 0;}',
			'td.column-title img.wp-post-image{float:left; margin:0px 10px 10px 0;}',
			'th.manage-column.column-views{width:72px;}',
			'th.manage-column.column-template{width:15%;}',
			'.fixed .column-date{width:98px;}',
			'.fixed .column-categories, .fixed .column-tags{width:12%;}'
		])."\n");
	}elseif($screen_base == 'upload'){
		$instance	= new WPJAM_Posts_Admin($current_screen->post_type);

		add_action('restrict_manage_posts', [$instance,	'add_users_dropdown'], 99);
	}
}, 10, 2);