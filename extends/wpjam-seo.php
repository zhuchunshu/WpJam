<?php
/*
Name: 简单 SEO
URI: https://blog.wpjam.com/m/wpjam-seo/
Description: 设置简单快捷，功能强大的 WordPress SEO 功能。
Version: 1.0
*/
class WPJAM_SEO{
	use WPJAM_Setting_Trait;

	private function __construct(){
		$this->init('wpjam-seo');
	}

	public function sitemap($action){
		$sitemap	= '';

		if(empty($action)){
			$last_mod	= str_replace(' ', 'T', get_lastpostmodified('GMT')).'+00:00';
			$sitemap	.= "\t<url>\n";
			$sitemap	.="\t\t<loc>".home_url()."</loc>\n";
			$sitemap	.="\t\t<lastmod>".$last_mod."</lastmod>\n";
			$sitemap	.="\t\t<changefreq>daily</changefreq>\n";
			$sitemap	.="\t\t<priority>1.0</priority>\n";
			$sitemap	.="\t</url>\n";

			$taxonomies = [];
			foreach (get_taxonomies(['public' => true]) as $taxonomy => $value) {
				if($taxonomy != 'post_format'){
					$taxonomies[]	= $taxonomy;
				}
			}

			$terms	= get_terms(['taxonomy'=>$taxonomies]);

			foreach ($terms as $term) {
				$priority		= ($term->taxonomy == 'category')?0.6:0.4;
				$sitemap	.="\t<url>\n";
				$sitemap	.="\t\t<loc>".get_term_link($term)."</loc>\n";
				$sitemap	.="\t\t<lastmod>".$last_mod."</lastmod>\n";
				$sitemap	.="\t\t<changefreq>daily</changefreq>\n";
				$sitemap	.="\t\t<priority>".$priority."</priority>\n";
				$sitemap	.="\t</url>\n";
			}
		}elseif(is_numeric($action)){
			$post_types = [];

			foreach (get_post_types(['public' => true]) as $post_type => $value) {
				if($post_type != 'page' && $post_type != 'attachment'){
					$post_types[] = $post_type;
				}
			}

			$sitemap_posts	= WPJAM_Query([
				'posts_per_page'	=> 1000,
				'paged'				=> $action,
				'post_type'			=> $post_types,
			])->posts;

			if($sitemap_posts){
				foreach ($sitemap_posts as $sitemap_post) {
					$permalink	= get_permalink($sitemap_post->ID); //$siteurl.$sitemap_post->post_name.'/';
					$last_mod	= str_replace(' ', 'T', $sitemap_post->post_modified_gmt).'+00:00';
					$sitemap	.="\t<url>\n";
					$sitemap	.="\t\t<loc>".$permalink."</loc>\n";
					$sitemap	.="\t\t<lastmod>".$last_mod."</lastmod>\n";
					$sitemap	.="\t\t<changefreq>weekly</changefreq>\n";
					$sitemap	.="\t\t<priority>0.8</priority>\n";
					$sitemap	.="\t</url>\n";
				}
			}
		}else{
			$sitemap = apply_filters('wpjam_'.$action.'_sitemap', '');
		}

		if(!wpjam_doing_debug()){
			header ("Content-Type:text/xml"); 

			$renderer	= new WP_Sitemaps_Renderer();

			echo '<?xml version="1.0" encoding="UTF-8"?>
		<?xml-stylesheet type="text/xsl" href="'.$renderer->get_sitemap_stylesheet_url().'"?>
		<!-- generated-on="'.date('d. F Y').'" -->
		<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n".$sitemap."\n".'</urlset>'."\n";
		}else{
			global $wpdb;
			echo get_num_queries();echo ' queries in ';timer_stop(1);echo ' seconds.<br>';

			echo '按执行顺序：<br>';
			echo '<pre>';
			var_dump($wpdb->queries);
			echo '</pre>';

			echo '按耗时：<br>';
			echo '<pre>';
			$qs = array();
			foreach($wpdb->queries as $q){
			$qs[''.$q[1].''] = $q;
			}
			krsort($qs);
			print_r($qs);
			echo '</pre>';
		}
		exit;
	}

	public function filter_robots_txt($output, $public){
		return '0' == $public ? "Disallow: /\n" : $this->get_setting('robots');
	}

	public function on_wp_head(){
		$meta_title	= $meta_keywords	= $meta_description	= '';

		if(is_singular()){
			$post_id = get_the_ID();

			if($this->get_setting('individual')){
				$seo_post_types	= $this->get_setting('post_types') ?? ['post'];

				if($seo_post_types && in_array(get_post_type(), $seo_post_types)){
					$meta_title 		= get_post_meta($post_id, 'seo_title', true);
					$meta_description	= get_post_meta($post_id, 'seo_description', true);
					$meta_keywords		= get_post_meta($post_id, 'seo_keywords', true);
				}
			}

			if(empty($meta_description)){
				$meta_description	= get_the_excerpt();
			}

			if(empty($meta_keywords)){
				if($tags = get_the_tags($post_id)){
					$meta_keywords = implode(',', wp_list_pluck($tags, 'name'));
				}
			}
		}elseif($GLOBALS['paged']<2){
			if((is_home() || is_front_page()) && !wpjam_is_module()){
				$meta_title 		= $this->get_setting('home_title');
				$meta_description	= $this->get_setting('home_description');
				$meta_keywords		= $this->get_setting('home_keywords');
			}elseif(is_tag() || is_category() || is_tax()){
				if($this->get_setting('individual')){
					$seo_taxonomies	= $this->get_setting('taxonomies') ?? ['category'];

					if($seo_taxonomies && in_array(get_queried_object()->taxonomy, $seo_taxonomies)){
						$term_id	= get_queried_object_id();

						$meta_title			= get_term_meta(get_queried_object_id(), 'seo_title', true);
						$meta_description	= get_term_meta($term_id, 'seo_description', true);
						$meta_keywords		= get_term_meta($term_id, 'seo_keywords', true);
					}
				}

				if(empty($meta_description) && term_description()){
					$meta_description	= term_description();
				}
			}
		}

		remove_action('wp_head', '_wp_render_title_tag', 1);

		if($meta_title){
			echo '<title>'.wp_slash($meta_title).'</title>'."\n";
		}else{
			_wp_render_title_tag();
		}

		if($meta_description){
			$meta_description	= wp_slash(wpjam_get_plain_text($meta_description));
			echo "<meta name='description' content='{$meta_description}' />\n";
		}

		if($meta_keywords){
			$meta_keywords	= wp_slash(wpjam_get_plain_text($meta_keywords));
			echo "<meta name='keywords' content='{$meta_keywords}' />\n";
		}
	}

	public function get_fields(){
		return [
			'seo_title'			=> ['title'=>'SEO标题', 		'type'=>'text',		'class'=>'large-text',	'placeholder'=>'不填则使用标题'],
			'seo_description'	=> ['title'=>'SEO描述', 		'type'=>'textarea'],
			'seo_keywords'		=> ['title'=>'SEO关键字',	'type'=>'text',		'class'=>'large-text']
		];
	}

	public function get_term_fields(){
		return array_map(function($field){ return array_merge($field, ['action'=>'edit']); }, $this->get_fields());
	}

	public function update_post_data($post_id, $data){
		foreach(['seo_title', 'seo_description', 'seo_keywords'] as $meta_key){
			$meta_value	= $data[$meta_key] ?? '';

			WPJAM_Post::update_meta($post_id, $meta_key, $meta_value);
		}

		return true;
	}

	public function update_term_data($post_id, $data){
		foreach(['seo_title', 'seo_description', 'seo_keywords'] as $meta_key){
			$meta_value	= $data[$meta_key] ?? '';

			WPJAM_Term::update_meta($post_id, $meta_key, $meta_value);
		}

		return true;
	}

	public function get_default_robots(){
		$site_url	= parse_url( site_url() );
		$path		= !empty($site_url['path'])  ? $site_url['path'] : '';

		return "User-agent: *
		Disallow: /wp-admin/
		Disallow: /wp-includes/
		Disallow: /cgi-bin/
		Disallow: $path/wp-content/plugins/
		Disallow: $path/wp-content/themes/
		Disallow: $path/wp-content/cache/
		Disallow: $path/author/
		Disallow: $path/trackback/
		Disallow: $path/feed/
		Disallow: $path/comments/
		Disallow: $path/search/";
	}

	public function on_builtin_page_load($screen_base, $current_screen){
		if($screen_base == 'edit' || $screen_base == 'post'){
			$seo_post_types	= $this->get_setting('post_types') ?? ['post'];

			if($seo_post_types && in_array($current_screen->post_type, $seo_post_types)){
				wpjam_register_list_table_action('seo', [
					'title'			=> 'SEO设置',
					'page_title'	=> 'SEO设置',
					'submit_text'	=> '设置',
					'fields'		=> [$this, 'get_fields'],
					'callback'		=> [$this, 'update_post_data']
				]);

				wpjam_register_post_option('wpjam-seo', [
					'title'		=> 'SEO设置',
					'context'	=> 'side',
					'fields'	=> [$this,'get_fields']
				]);
			}
		}elseif($screen_base == 'edit-tags' || $screen_base == 'term'){
			$seo_taxonomies	= $this->get_setting('taxonomies') ?? ['category'];

			if($seo_taxonomies && in_array($current_screen->taxonomy, $seo_taxonomies)){
				wpjam_register_list_table_action('seo', [
					'title'			=> 'SEO设置',
					'page_title'	=> 'SEO设置',
					'submit_text'	=> '设置',
					'fields'		=> [$this, 'get_fields'],
					'callback'		=> [$this, 'update_term_data']
				]);

				wpjam_register_term_option('seo', [$this, 'get_term_fields']);
			}
		}
	}

	public function load_option_page($plugin_page){
		if(file_exists(ABSPATH.'robots.txt')){
			$robots_type	= 'view';
			$robots_value	= '博客的根目录下已经有 robots.txt 文件。<br />请直接编辑或者删除之后在后台自定义。';
		}else{
			$robots_type	= 'textarea';
			$robots_value	= $this->get_default_robots();
		}

		if(file_exists(ABSPATH.'sitemap.xml')){
			$wpjam_sitemap	= '博客的根目录下已经有 sitemap.xml 文件。<br />删除之后才能使用插件自动生成的 sitemap.xml。';
		}else{
			$wpjam_sitemap	= '<table>
				<tr><td style="padding:0 10px 8px 0;">首页/分类/标签：</td><td style="padding:0 10px 8px 0;"><a href="'.home_url('/sitemap.xml').'" target="_blank">'.home_url('/sitemap.xml').'</a></td></tr>
				<tr><td style="padding:0 10px 8px 0;">前1000篇文章：</td><td style="padding:0 10px 8px 0;"><a href="'.home_url('/sitemap-1.xml').'" target="_blank">'.home_url('/sitemap-1.xml').'</a></td></tr>
				<tr><td style="padding:0 10px 8px 0;">1000-2000篇文章：</td><td style="padding:0 10px 8px 0;"><a href="'.home_url('/sitemap-2.xml').'" target="_blank">'.home_url('/sitemap-2.xml').'</a></td></tr>
				<tr><td style="padding:0 10px 8px 0;" colspan=2>以此类推...</a></td></tr>
			</table>';
		}

		$wp_sitemap	= '<a href="'.home_url('/wp-sitemap.xml').'" target="_blank">'.home_url('/wp-sitemap.xml').'</a>';

		$post_type_options	= wp_list_pluck(get_post_types(['show_ui'=>true,'public'=>true], 'objects'), 'label', 'name');
		$taxonomy_options	= wp_list_pluck(get_taxonomies(['show_ui'=>true,'public'=>true], 'objects'), 'label', 'name');

		unset($post_type_options['attachment']);

		$individual_options	= [0=>'文章和分类页自动获取摘要和关键字',1=>'文章和分类页单独的 SEO TDK 设置。'];
		$auto_view			= '文章摘要作为页面的 Meta Description，文章的标签作为页面的 Meta Keywords。<br />分类和标签的描述作为页面的 Meta Description，页面没有 Meta Keywords。';

		$setting_fields	= [
			'individual'		=> ['title'=>'SEO设置',		'type'=>'select', 	'options'=>$individual_options],
			'auto'				=> ['title'=>'自动获取规则',	'type'=>'view', 	'show_if'=>['key'=>'individual', 'value'=>'0'],	'value'=>$auto_view],
			'individual_set'	=> ['title'=>'单独设置支持',	'type'=>'fieldset',	'show_if'=>['key'=>'individual', 'value'=>'1'],	'fields'=>[
				'post_types'	=> ['title'=>'文章类型','type'=>'checkbox',	'options'=>$post_type_options,	'value'=>['post']],
				'taxonomies'	=> ['title'=>'分类模式','type'=>'checkbox',	'options'=>$taxonomy_options,	'value'=>['category']],
			]],
			'robots'		=> ['title'=>'robots.txt',	'type'=>$robots_type,	'class'=>'',	'rows'=>10,	'value'=>$robots_value],
			'sitemap'		=> ['title'=>'Sitemap',		'type'=>'select',		'options'=>[0=>'使用 WPJAM 生成的 sitemap', 'wp'=>'使用 WordPress 内置生成的 sitemap']],
			'wpjam_sitemap'	=> ['title'=>'Sitemap地址',	'type'=>'view',			'value'=>$wpjam_sitemap,	'show_if'=>['key'=>'sitemap',	'value'=>0]],
			'wp_sitemap'	=> ['title'=>'Sitemap地址',	'type'=>'view',			'value'=>$wp_sitemap,		'show_if'=>['key'=>'sitemap',	'value'=>'wp']],
		];

		$home_fields	= [
			'home_title'		=> ['title'=>'SEO 标题',		'type'=>'text'],
			'home_description'	=> ['title'=>'SEO 描述',		'type'=>'textarea', 'class'=>''],
			'home_keywords'		=> ['title'=>'SEO 关键字',	'type'=>'text' ],
		];

		wpjam_register_option('wpjam-seo', [
			'sections'	=> [ 
				'setting'	=> ['title'=>'SEO设置',	'fields'=>$setting_fields],
				'home'		=> ['title'=>'首页设置',	'fields'=>$home_fields]
			],
			'summary'		=>'简单 SEO 扩展让你最简单快捷的方式设置站点的 SEO，详细介绍请点击：<a href="https://blog.wpjam.com/m/wpjam-seo/" target="_blank">简单SEO扩展</a>。'
		]);

		flush_rewrite_rules();
	}
}

add_action('init', function(){
	$instance	= WPJAM_SEO::get_instance();

	if(empty($instance->get_setting('sitemap'))){
		add_rewrite_rule($GLOBALS['wp_rewrite']->root.'sitemap\.xml?$', 'index.php?module=sitemap', 'top');
		add_rewrite_rule($GLOBALS['wp_rewrite']->root.'sitemap-(.*?)\.xml?$', 'index.php?module=sitemap&action=$matches[1]', 'top');

		wpjam_register_route_module('sitemap', ['callback'=>[$instance, 'sitemap']]);
	}

	add_action('wp_head',		[$instance, 'on_wp_head'], 0);
	add_filter('robots_txt',	[$instance, 'filter_robots_txt'], 10, 2);

	if(is_admin() && (!is_multisite() || !is_network_admin())){
		wpjam_add_basic_sub_page('wpjam-seo',	['menu_title'=>'SEO设置', 'page_title'=>'简单SEO', 'function'=>'option', 'load_callback'=>[$instance, 'load_option_page']]);

		if($instance->get_setting('individual')){
			add_action('wpjam_builtin_page_load',	[$instance, 'on_builtin_page_load'], 10, 2);
		}
	}
});


	