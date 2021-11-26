<?php
class WPJAM_Post_Builtin{
	public static function page_load($screen_base, $current_screen){
		if($screen_base == 'post'){
			if(wpjam_basic_get_setting('disable_block_editor')){
				add_filter('use_block_editor_for_post_type', '__return_false');
			}else{
				if(wpjam_basic_get_setting('disable_google_fonts_4_block_editor')){	// 古腾堡编辑器不加载 Google 字体
					wp_deregister_style('wp-editor-font');
					wp_register_style('wp-editor-font', '');
				}
			}

			// if(!$current_screen->is_block_editor()){
			// 	add_filter('content_save_pre', [self::class, 'filter_content_save_pre']);
			// }

			// if(wpjam_basic_get_setting('disable_revision')){
			//	wp_deregister_script('autosave');
			// }

			if(wpjam_basic_get_setting('disable_trackbacks')){
				wp_add_inline_style('wpjam-style', "\n".'label[for="ping_status"]{display:none !important;}'."\n");
			}
		}elseif($screen_base == 'edit'){
			$post_type	= $current_screen->post_type;
			$pt_obj		= get_post_type_object($post_type);

			$inline_css	= [
				'td img.wp-post-image{float:left; margin:0px 10px 10px 0;}',
				'.fixed .column-views{width:70px;}',
				'.fixed .column-date{width:98px;}'
			];

			if($post_type == 'page'){
				wpjam_register_posts_column('template', '模板', 'get_page_template_slug');

				$inline_css[]	= '.fixed .column-template{width:15%;}';
			}

			if(wpjam_basic_get_setting('post_list_set_thumbnail', 1) && post_type_supports($post_type, 'thumbnail')){
				wpjam_register_list_table_action('set_thumbnail', [
					'title'			=> '设置',
					'page_title'	=> '设置特色图片',
					'fields'		=> ['_thumbnail_id'	=> ['title'=>'缩略图', 'type'=>'img', 'size'=>'600x0']],
					'row_action'	=> false,
					'width'			=> 500
				]);

				add_filter('wpjam_single_row', [self::class, 'filter_post_single_row'], 10, 2);
			}

			if((wpjam_basic_get_setting('post_list_update_views', 1) && is_post_type_viewable($post_type)) || !empty($pt_obj->viewable)){
				wpjam_register_list_table_action('update_views', [
					'title'			=> '修改',
					'page_title'	=> '修改浏览数',
					'capability'	=> $pt_obj->cap->edit_others_posts,
					'fields'		=> ['views'=>['title'=>'浏览数', 'type'=>'number']],
					'row_action'	=> false,
					'width'			=> 500
				]);

				wpjam_register_posts_column('views', [
					'title'				=> '浏览',
					'sortable_column'	=> 'views',
					'column_callback'	=> [self::class, 'views_column']
				]);
			}

			if(wpjam_basic_get_setting('upload_external_images')){
				wpjam_register_list_table_action('upload_external_images', [
					'title'			=> '上传外部图片',
					'page_title'	=> '上传外部图片',
					'direct'		=> true,
					'confirm'		=> true,
					'bulk'			=> 2,
					'order'			=> 9,
					'callback'		=> [self::class, 'upload_external_images']
				]);
			}

			add_action('restrict_manage_posts',	[self::class, 'taxonomy_dropdown'], 1);

			if(is_object_in_taxonomy($post_type, 'category')){
				add_filter('disable_categories_dropdown', '__return_true');
			}

			if(wpjam_basic_get_setting('post_list_author_filter', 1) && post_type_supports($post_type, 'author')){
				add_action('restrict_manage_posts', [self::class, 'author_dropdown'], 99);
			}

			if(wpjam_basic_get_setting('post_list_sort_selector', 1)){
				add_action('restrict_manage_posts', [self::class, 'orderby_dropdown'], 99);
			}

			add_filter('post_column_taxonomy_links',	[self::class, 'filter_taxonomy_links'], 10, 3);

			add_filter('request',	[self::class, 'filter_request']);

			$width_columns	= [];

			if(post_type_supports($post_type, 'author')){
				$width_columns[]	= '.fixed .column-author';
			}

			$taxonomies	= get_object_taxonomies($post_type, 'objects');
			$taxonomies	= wp_filter_object_list($taxonomies, ['show_admin_column' => true], 'and', 'name');

			foreach($taxonomies as $taxonomy){
				if('category' === $taxonomy) {
					$column_key	= 'categories';
				}elseif('post_tag' === $taxonomy){
					$column_key	= 'tags';
				}else{
					$column_key	= 'taxonomy-'.$taxonomy;
				}
				
				$width_columns[]	= '.fixed .column-'.$column_key;
			}

			$column_widths	= [1=>'14%',	2=>'12%',	3=>'10%',	4=>'8%',	5=>'7%'];

			if($count = count($width_columns)){
				$column_width	= $column_widths[$count] ?? '6%';
				$inline_css[]	= implode(',', $width_columns).'{width:'.$column_width.'}';
			} 

			wp_add_inline_style('list-tables', "\n".implode("\n", $inline_css)."\n");
		}elseif($screen_base == 'upload'){
			add_action('restrict_manage_posts',	[self::class, 'taxonomy_dropdown'], 1);
			add_action('restrict_manage_posts', [self::class, 'author_dropdown'], 99);

			add_filter('request',	[self::class, 'filter_request']);
		}elseif($screen_base == 'edit-tags'){
			add_filter('wpjam_single_row',	[self::class, 'filter_term_single_row'], 10, 2);
		}elseif($screen_base == 'users'){
			if(wpjam_get_user_signups()){
				wpjam_register_list_table_column('openid', ['title'=>'绑定账号',	'column_callback'=>[self::class, 'openid_column']]);
			}
		}

		if(in_array($screen_base, ['edit', 'upload', 'edit-tags']) && !wpjam_basic_get_setting('post_list_ajax', 1)){
			add_action('admin_head', [self::class, 'inline_script']);
		}
	}

	public static function inline_script(){
		?>

		<script type="text/javascript">
			wpjam_page_setting.list_table.ajax	= false;
		</script>
		
		<?php
	}

	public static function views_column($post_id){
		$views	= wpjam_get_post_views($post_id, false) ?: 0;
		$pt_obj	= get_post_type_object(get_current_screen()->post_type);

		if(current_user_can($pt_obj->cap->edit_others_posts)){
			$views	= wpjam_get_list_table_row_action('update_views', ['id'=>$post_id, 'title'=>$views]);
		}

		return $views;
	}

	public static function openid_column($user_id){
		$wpjam_user	= WPJAM_User::get_instance($user_id);

		$values 	= [];

		foreach(wpjam_get_user_signups() as $name => $object){
			if($openid = $wpjam_user->get_openid($name, $object->appid)){
				$values[]	= $object->title.'：<br />'.$openid;
			}
		}

		return $values ? implode('<br /><br />', $values) : '';
	}

	public static function filter_post_single_row($single_row, $post_id){
		$thumbnail	= get_the_post_thumbnail($post_id, [50,50]) ?: '<span class="no-thumbnail">暂无图片</span>';

		if(post_type_supports(get_post_type($post_id), 'thumbnail') && current_user_can('edit_post', $post_id)){
			$thumbnail = wpjam_get_list_table_row_action('set_thumbnail',['id'=>$post_id, 'title'=>$thumbnail]);
		}

		return str_replace('<a class="row-title" ', $thumbnail.'<a class="row-title" ', $single_row);
	}

	public static function filter_term_single_row($single_row, $term_id){
		$taxonomy	= get_term($term_id)->taxonomy;

		if(WPJAM_List_Table_Action::get('set_thumbnail')){
			$thumb_url	= wpjam_get_term_thumbnail_url($term_id, [100, 100]);
			$thumbnail	= $thumb_url ? '<img class="wp-term-image" src="'.$thumb_url.'"'.image_hwstring(50,50).' />' : '<span class="no-thumbnail">暂无图片</span>';

			if(current_user_can(get_taxonomy($taxonomy)->cap->edit_terms)){
				$thumbnail = wpjam_get_list_table_row_action('set_thumbnail', ['id'=>$term_id, 'title'=>$thumbnail]);
			}

			return str_replace('<a class="row-title" ', $thumbnail.'<a class="row-title" ', $single_row);
		}

		$permastruct	= wpjam_get_permastruct($taxonomy);

		if(empty($permastruct) || strpos($permastruct, '/%'.$taxonomy.'_id%')){
			$single_row	= self::term_edit_link_replace($single_row, $term_id);
		}

		return $single_row;
	}

	public static function term_edit_link_replace($link, $term_id){
		$term		= get_term($term_id);
		$taxonomy	= $term->taxonomy;

		$query_var	= get_taxonomy($taxonomy)->query_var;
		$query_key	= wpjam_get_taxonomy_query_key($taxonomy);
		$query_str	= $query_var ? $query_var.'='.$term->slug : 'taxonomy='.$taxonomy.'&#038;term='.$term->slug;

		return str_replace($query_str, $query_key.'='.$term->term_id, $link);
	}

	public static function filter_taxonomy_links($term_links, $taxonomy, $terms){
		$permastruct	= wpjam_get_permastruct($taxonomy);

		if(empty($permastruct) || strpos($permastruct, '/%'.$taxonomy.'_id%')){
			foreach($terms as $i => $term){
				$term_links[$i]	= self::term_edit_link_replace($term_links[$i], $term);
			}
		}

		return $term_links;
	}

	public static function filter_request($query_vars){
		$tax_query	= [];

		foreach(get_object_taxonomies(get_current_screen()->post_type, 'objects') as $taxonomy=>$tax_obj){
			if(!$tax_obj->show_ui){
				continue;
			}

			$tax	= $taxonomy == 'post_tag' ? 'tag' : $taxonomy;

			if($tax != 'category'){
				if($tax_id = (int)wpjam_get_data_parameter($tax.'_id')){
					$query_vars[$tax.'_id']	= $tax_id;
				}
			}

			$tax__and		= wpjam_get_data_parameter($tax.'__and',	['sanitize_callback'=>'wp_parse_id_list']);
			$tax__in		= wpjam_get_data_parameter($tax.'__in',		['sanitize_callback'=>'wp_parse_id_list']);
			$tax__not_in	= wpjam_get_data_parameter($tax.'__not_in',	['sanitize_callback'=>'wp_parse_id_list']);

			if($tax__and){
				if(count($tax__and) == 1){
					$tax__in	= is_null($tax__in) ? [] : $tax__in;
					$tax__in[]	= reset($tax__and);
				}else{
					$tax_query[]	= [
						'taxonomy'	=> $taxonomy,
						'terms'		=> $tax__and,
						'field'		=> 'term_id',
						'operator'	=> 'AND',
						// 'include_children'	=> false,
					];
				}
			}

			if($tax__in){
				$tax_query[]	= [
					'taxonomy'	=> $taxonomy,
					'terms'		=> $tax__in,
					'field'		=> 'term_id'
				];
			}

			if($tax__not_in){
				$tax_query[]	= [
					'taxonomy'	=> $taxonomy,
					'terms'		=> $tax__not_in,
					'field'		=> 'term_id',
					'operator'	=> 'NOT IN'
				];
			}
		}

		if($tax_query){
			$tax_query['relation']		= wpjam_get_data_parameter('tax_query_relation',	['default'=>'and']);
			$query_vars['tax_query']	= $tax_query;
		}

		return $query_vars;
	}

	public static function filter_content_save_pre($content){
		if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE){
			return $content;
		}

		if(!preg_match_all('/<img.*?src=\\\\[\'"](.*?)\\\\[\'"].*?>/i', $content, $matches)){
			return $content;
		}

		$img_urls	= array_unique($matches[1]);
		
		if($replace	= wpjam_fetch_external_images($img_urls)){
			if(is_multisite()){
				setcookie('wp-saving-post', $_POST['post_ID'].'-saved', time()+DAY_IN_SECONDS, ADMIN_COOKIE_PATH, false, is_ssl());
			}

			$content	= str_replace($img_urls, $replace, $content);
		}

		return $content;
	}

	public static function upload_external_images($post_id){
		$content	= get_post($post_id)->post_content;
		$bulk		= (int)wpjam_get_parameter('bulk', ['method'=>'POST']);

		if(preg_match_all('/<img.*?src=[\'"](.*?)[\'"].*?>/i', $content, $matches)){
			$img_urls	= array_unique($matches[1]);

			if($replace	= wpjam_fetch_external_images($img_urls, true, $post_id)){
				$content	= str_replace($img_urls, $replace, $content);
				return wp_update_post(['post_content'=>$content, 'ID'=>$post_id], true);
			}else{
				return $bulk == 2 ? true : new WP_Error('no_external_images', '文章中无外部图片');
			}
		}

		return $bulk == 2 ? true : new WP_Error('no_images', '文章中无图片');
	}

	public static function author_dropdown($post_type){
		wp_dropdown_users([
			'name'						=>'author',
			'who'						=>'authors',
			'hide_if_only_one_author'	=> true,
			'show_option_all'			=> $post_type == 'attachment' ? '所有上传者' : '所有作者',
			'selected'					=> (int)wpjam_get_data_parameter('author')
		]);
	}

	public static function taxonomy_dropdown($post_type){
		foreach(get_object_taxonomies($post_type, 'objects') as $taxonomy => $tax_obj){
			$filterable	= $tax_obj->filterable ?? ($taxonomy == 'category' ? true : false);

			if(empty($filterable) || empty($tax_obj->show_admin_column)){
				continue;
			}

			$query_key	= wpjam_get_taxonomy_query_key($taxonomy);
			$selected	= wpjam_get_data_parameter($query_key);

			if(is_null($selected)){
				if($query_var = $tax_obj->query_var){
					$term_slug	= wpjam_get_data_parameter($query_var);
				}elseif(wpjam_get_data_parameter('taxonomy') == $taxonomy){
					$term_slug	= wpjam_get_data_parameter('term');
				}else{
					$term_slug	= '';
				}

				$term 		= $term_slug ? get_term_by('slug', $term_slug, $taxonomy) : null;
				$selected	= $term ? $term->term_id : '';
			}

			if($tax_obj->hierarchical){
				wp_dropdown_categories([
					'taxonomy'			=> $taxonomy,
					'show_option_all'	=> $tax_obj->labels->all_items,
					'show_option_none'	=> '没有设置',
					'name'				=> $query_key,
					'selected'			=> (int)$selected,
					'hierarchical'		=> true
				]);
			}else{
				echo wpjam_get_field_html([
					'key'			=> $query_key,
					'value'			=> $selected,
					'type'			=> 'text',
					'data_type'		=> 'taxonomy',
					'taxonomy'		=> $taxonomy,
					'placeholder'	=> '请输入'.$tax_obj->label,
					'title'			=> '',
					'class'			=> ''
				]);
			}
		}
	}

	public static function orderby_dropdown($post_type){
		$wp_list_table	= _get_list_table('WP_Posts_List_Table', ['screen'=>get_current_screen()->id]);

		list($columns, $hidden, $sortable_columns)	= $wp_list_table->get_column_info();

		$options	= [''=>'排序','ID'=>'ID'];

		foreach($sortable_columns as $sortable_column => $data){
			if(isset($columns[$sortable_column])){
				$options[$data[0]]	= $columns[$sortable_column];
			}
		}

		echo wpjam_get_field_html([
			'key'		=> 'orderby',
			'type'		=> 'select',
			'value'		=> wpjam_get_data_parameter('orderby', ['sanitize_callback'=>'sanitize_key']),
			'options'	=> array_merge($options, ['modified'=>'修改时间'])
		]);

		echo wpjam_get_field_html([
			'key'		=> 'order',
			'type'		=> 'select',
			'value'		=> wpjam_get_data_parameter('order', ['sanitize_callback'=>'sanitize_key', 'default'=>'DESC']),
			'options'	=> ['desc'=>'降序','asc'=>'升序']
		]);
	}
}

class WPJAM_Dashboad_Builtin{
	public static function page_load(){
		add_action('wp_dashboard_setup',			[self::class, 'on_dashboard_setup'], 1);
		add_action('wp_network_dashboard_setup',	[self::class, 'on_dashboard_setup'], 1);
		add_action('wp_user_dashboard_setup',		[self::class, 'on_dashboard_setup'], 1);
	}

	public static function on_dashboard_setup(){
		remove_meta_box('dashboard_primary', get_current_screen(), 'side');

		add_filter('dashboard_recent_posts_query_args', function($query_args){
			$query_args['post_type']	= 'any';
			$query_args['cache_it']		= true;
			// $query_args['posts_per_page']	= 10;

			return $query_args;
		});

		add_filter('dashboard_recent_drafts_query_args', function($query_args){
			$query_args['post_type']	= 'any';

			return $query_args;
		});

		add_action('pre_get_comments', function($query){
			$query->query_vars['type']	= 'comment';
		});
			
		if(is_multisite()){
			if(!is_user_member_of_blog()){
				remove_meta_box('dashboard_quick_press', get_current_screen(), 'side');
			}
		}
		
		$dashboard_widgets	= [];

		$dashboard_widgets['wpjam_update']	= [
			'title'		=> 'WordPress资讯及技巧',
			'context'	=> 'side',	// 位置，normal 左侧, side 右侧
			'callback'	=> [self::class, 'update_dashboard_widget']
		];

		if($dashboard_widgets	= apply_filters('wpjam_dashboard_widgets', $dashboard_widgets)){
			foreach ($dashboard_widgets as $widget_id => $meta_box){
				$title		= $meta_box['title'];
				$callback	= $meta_box['callback'] ?? wpjam_get_filter_name($widget_id, 'dashboard_widget_callback');
				$context	= $meta_box['context'] ?? 'normal';	// 位置，normal 左侧, side 右侧
				$args		= $meta_box['args'] ?? [];

				add_meta_box($widget_id, $title, $callback, get_current_screen(), $context, 'core', $args);
			}
		}
	}

	public static function update_dashboard_widget(){
		?>
		<style type="text/css">
			#dashboard_wpjam .inside{margin:0; padding:0;}
			a.jam-post {border-bottom:1px solid #eee; margin: 0 !important; padding:6px 0; display: block; text-decoration: none; }
			a.jam-post:last-child{border-bottom: 0;}
			a.jam-post p{display: table-row; }
			a.jam-post img{display: table-cell; width:40px; height: 40px; margin:4px 12px; }
			a.jam-post span{display: table-cell; height: 40px; vertical-align: middle;}
		</style>
		<div class="rss-widget">
		<?php

		$jam_posts = get_transient('dashboard_jam_posts');

		if($jam_posts === false){
			$response	= wpjam_remote_request('https://jam.wpweixin.com/api/post/list.json', ['timeout'=>1]);

			if(is_wp_error($response)){
				$jam_posts	= [];
			}else{
				$jam_posts	= $response['posts'];
			}

			set_transient('dashboard_jam_posts', $jam_posts, 12 * HOUR_IN_SECONDS );
		}

		if($jam_posts){
			$i = 0;
			foreach ($jam_posts as $jam_post){
				if($i == 5) break;
				echo '<a class="jam-post" target="_blank" href="http://blog.wpjam.com'.$jam_post['post_url'].'"><p>'.'<img src="'.str_replace('imageView2/1/w/200/h/200/', 'imageView2/1/w/100/h/100/', $jam_post['thumbnail']).'" /><span>'.$jam_post['title'].'</span></p></a>';
				$i++;
			}
		}	
		?>
		</div>

		<?php
	}
}

class WPJAM_Plugin_Builtin{
	public static function page_load(){
		add_filter('update_plugins_blog.wpjam.com', [self::class, 'filter_update_plugins'], 10, 4);
		add_action('admin_head', [self::class, 'on_admin_head']);

		// delete_site_transient( 'update_plugins' );
		// wpjam_print_r(get_site_transient( 'update_plugins' ));
	}

	public static function get_jam_plugin($plugin_file){
		if($jam_plugins	= self::get_jam_plugins()){
			$plugin_fields	= array_column($jam_plugins['fields'], 'index', 'title');
			$plugin_index	= $plugin_fields['插件'];

			foreach($jam_plugins['content'] as $plugin_data){
				if($plugin_data['i'.$plugin_index] == $plugin_file){
					$new_data	= [];

					foreach($plugin_fields as $name => $index){
						$new_data[$name]	= $plugin_data['i'.$index] ?? '';
					}

					return $new_data;
				}
			}
		}

		return null;
	}

	public static function get_jam_plugins(){
		$jam_plugins = get_transient('jam_plugins');

		if($jam_plugins === false){
			$response	= wpjam_remote_request('https://jam.wpweixin.com/api/template/get.json?id=7506');

			if(!is_wp_error($response)){
				$jam_plugins	= $response['template']['table'];

				set_transient('jam_plugins', $jam_plugins, HOUR_IN_SECONDS);
			}
		}

		return $jam_plugins;
	}

	public static function filter_update_plugins($update, $plugin_data, $plugin_file, $locales){
		if($jam_plugin = self::get_jam_plugin($plugin_file)){
			return [
				'id'			=> $plugin_data['UpdateURI'],
				'plugin'		=> $plugin_file,
				'url'			=> $jam_plugin['更新地址'],
				'package'		=> '',
				'icons'			=> [],
				'banners'		=> [],
				'banners_rtl'	=> [],
				'requires'		=> '',
				'tested'		=> '',
				'requires_php'	=> 7.2,
				'new_version'	=> $jam_plugin['版本'],
				'version'		=> $jam_plugin['版本'],
			];
		}

		return $update;
	}

	public static function on_admin_head(){
		?>
		<script type="text/javascript">
		jQuery(function($){
			$('tr.plugin-update-tr').each(function(){
				if($(this).data('slug').indexOf('https://blog.wpjam.com/') === 0){
					$(this).find('a.open-plugin-details-modal').removeClass('thickbox open-plugin-details-modal').attr('target','_blank');
				}
			});
		});
		</script>
		<?php
	}
}

add_action('wpjam_builtin_page_load', function ($screen_base, $current_screen){
	if(in_array($screen_base, ['post', 'edit', 'upload', 'edit-tags', 'users'])){
		WPJAM_Post_Builtin::page_load($screen_base, $current_screen);
	}elseif(in_array($screen_base, ['plugins', 'plugins-network'])){
		WPJAM_Plugin_Builtin::page_load();
	}elseif(in_array($screen_base, ['dashboard', 'dashboard-network', 'dashboard-user'])){
		WPJAM_Dashboad_Builtin::page_load();
	}
}, 99, 2);