<?php
class WPJAM_CDN{
	public static function scheme_replace($url){
		if(parse_url($url, PHP_URL_SCHEME) == 'http'){
			return str_replace('http://', 'https://', $url);
		}else{
			return str_replace('https://', 'http://', $url);
		}
	}

	public static function parse_items($items){
		$items	= is_array($items) ? $items : explode('|', $items);
		
		return array_unique(array_filter(array_map('trim', $items)));
	}

	public static function host_replace($html, $to_cdn=true){
		$locals		= wpjam_cdn_get_setting('locals') ?: [];
		$locals[]	= self::scheme_replace(LOCAL_HOST);

		if($to_cdn){
			$locals[]	= self::scheme_replace(CDN_HOST);
			$locals[]	= LOCAL_HOST;
			$to_host	= CDN_HOST;
		}else{
			$to_host	= LOCAL_HOST;
		}

		$locals	= apply_filters('wpjam_cdn_local_hosts', $locals);
		$locals	= array_map('untrailingslashit', array_unique($locals));

		return str_replace($locals, $to_host, $html);
	}

	public static function html_replace($html){
		$html	= self::host_replace($html, false);

		$exts	= wpjam_cdn_get_setting('exts');
		$exts	= self::parse_items($exts);

		if(is_login()){
			$exts	= array_diff($exts, ['js','css']);
		}

		if(empty($exts)){
			return $html;
		}

		$local_host	= preg_quote(LOCAL_HOST, '/');

		if($no_http = wpjam_cdn_get_setting('no_http')){
			$local_host	.= '|'.preg_quote(str_replace(['http://', 'https://'], '//', LOCAL_HOST), '/');
		}

		$pattern	= '('.$local_host.')\/(';
		$replace	= CDN_HOST.'/$2$4';

		if($dirs = wpjam_cdn_get_setting('dirs')){
			$replace	= CDN_HOST.'/$2$5';

			$dirs		= self::parse_items($dirs);
			$dirs		= array_map(function($dir){ return preg_quote(trim($dir, '/'), '/'); }, $dirs);
			$pattern	.= '('.implode('|', $dirs).')\/';
		}

		$pattern	.= '[^\s\?\\\'\"\;\>\<]{1,}\.('.implode('|', $exts).')';
		$pattern	.= ')([\"\\\'\)\s\]\?]{1})';
		
		return preg_replace('/'.$pattern.'/', $replace, $html);
	}

	public static function content_images($content, $max_width=null){
		if(false === strpos($content, '<img')){
			return $content;
		}

		if(!wpjam_is_json_request()){
			$content	= self::host_replace($content, false);
		}

		if(is_null($max_width)){
			$max_width	= wpjam_cdn_get_setting('max_width', ($GLOBALS['content_width'] ?? 0));
			$max_width	= (int)apply_filters('wpjam_content_image_width', $max_width);
		}

		if($max_width){
			add_filter('wp_img_tag_add_srcset_and_sizes_attr', '__return_false');
			remove_filter('the_content', 'wp_filter_content_tags');
		}

		if(!preg_match_all('/<img.*?src=[\'"](.*?)[\'"].*?>/i', $content, $matches)){
			return $content;
		}

		$ratio	= 2;
		$search	= $replace = [];

		foreach($matches[0] as $i => $img_tag){
			$img_url	= $matches[1][$i];

		 	if(empty($img_url) || wpjam_is_external_image($img_url)){
		 		continue;
		 	}

			$size	= ['width'=>0, 'height'=>0, 'content'=>true];

			if(preg_match_all('/(width|height)=[\'"]([0-9]+)[\'"]/i', $img_tag, $hw_matches)){
				$hw_arr	= array_flip($hw_matches[1]);
				$size	= array_merge($size, array_combine($hw_matches[1], $hw_matches[2]));
			}

			$width	= $size['width'];

			$img_serach	= $img_replace = [];

			if($max_width){
				if($size['width'] >= $max_width){
					if($size['height']){
						$size['height']	= (int)(($max_width/$size['width'])*$size['height']);

						$img_serach[]	= $hw_matches[0][$hw_arr['height']];
						$img_replace[]	= 'height="'.$size['height'].'"';
					}

					$size['width']	= $max_width;

					$img_serach[]	= $hw_matches[0][$hw_arr['width']];
					$img_replace[]	= 'width="'.$size['width'].'"';
				}elseif($size['width'] == 0){
					if($size['height'] == 0){
						$size['width']	= $max_width;
					}
				}
			}

			$img_serach[]	= $img_url;

			if(strpos($img_tag, 'size-full ') && (empty($max_width) || $max_width*$ratio >= $width)){
				$img_replace[]	= wpjam_get_thumbnail($img_url, ['content'=>true]);
			}else{
				$size			= wpjam_parse_size($size, $ratio);
				$img_replace[]	= wpjam_get_thumbnail($img_url, $size);
			}

			if(function_exists('wp_lazy_loading_enabled')){
				$add_loading_attr	= wp_lazy_loading_enabled('img', current_filter());

				if($add_loading_attr && false === strpos($img_tag, ' loading=')) {
					$img_serach[]	= '<img';
					$img_replace[]	= '<img loading="lazy"';
				}
			}

			$search[]	= $img_tag;
			$replace[]	= str_replace($img_serach, $img_replace, $img_tag);
		}

		if(!$search){
			return $content;
		}

		return str_replace($search, $replace, $content);
	}

	public static function is_cdn_url($url){
		$status	= strpos($url, CDN_HOST) !== false;

		return apply_filters('wpjam_is_cdn_url', $status, $url);
	}

	public static function filter_html($html){
		if(empty(CDN_NAME) && wpjam_cdn_get_setting('disabled')){
			return self::host_replace($html, false);
		}else{
			if(wpjam_cdn_get_setting('exts')){
				return self::html_replace($html);
			}
		}

		return $html;
	}

	public static function filter_content($content){
		if(doing_filter('get_the_excerpt')){
			return $content;
		}

		return self::content_images($content);
	}

	public static function filter_thumbnail($url){
		return self::host_replace($url);
	}

	public static function filter_is_external_image($status, $img_url, $scene){
		if($status){
			if($scene == 'fetch'){
				if($exceptions = wpjam_cdn_get_setting('exceptions', [])){
					$exceptions	= explode("\n", $exceptions);
					$exceptions	= self::parse_items($exceptions);

					foreach($exceptions as $exception){
						if(strpos($img_url, trim($exception)) !== false){
							return false;
						}
					}
				}

			}

			return !self::is_cdn_url($img_url);
		}

		return $status;
	}

	public static function filter_intermediate_image_sizes_advanced($sizes){
		return isset($sizes['full']) ? ['full'=>$sizes['full']] : [];
	}

	public static function filter_attachment_url($url, $id){
		if(wp_attachment_is_image($id)){
			return self::host_replace($url);
		}

		return $url;
	}

	public static function filter_image_downsize($out, $id, $size){
		if(wp_attachment_is_image($id)){
			$meta	= wp_get_attachment_metadata($id);
			
			if(is_array($meta) && isset($meta['width'], $meta['height'])){
				$ratio	= 2;
				$size	= wpjam_parse_size($size, $ratio);

				if($size['crop']){
					$width	= min($size['width'],	$meta['width']);
					$height	= min($size['height'],	$meta['height']);
				}else{
					list($width, $height)	= wp_constrain_dimensions($meta['width'], $meta['height'], $size['width'], $size['height']);
				}

				$img_url	= wp_get_attachment_url($id);

				if($width < $meta['width'] || $height <  $meta['height']){
					$img_url	= wpjam_get_thumbnail($img_url, compact('width', 'height'));
					$out		= [$img_url, (int)($width/$ratio), (int)($height/$ratio), true];
				}else{
					$img_url	= wpjam_get_thumbnail($img_url);
					$out		= [$img_url, $width, $height, false];
				}
			}	
		}

		return $out;
	}

	public static function filter_wp_resource_hints($urls, $relation_type){
		return $relation_type == 'dns-prefetch' ? $urls+[CDN_HOST] : $urls;
	}
}

class WPJAM_CDN_Setting{
	use WPJAM_Setting_Trait;

	private function __construct(){
		$this->init('wpjam-cdn', true);
	}

	public static function get_show_if($compare='=', $value=''){
		return ['key'=>'cdn_name', 'compare'=>$compare, 'value'=>$value];
	}

	public static function get_sections(){
		$detail = '阿里云 OSS 用户：请点击这里注册和申请<a href="http://wpjam.com/go/aliyun/" target="_blank">阿里云</a>可获得代金券，阿里云OSS<strong><a href="https://blog.wpjam.com/m/aliyun-oss-cdn/" target="_blank">详细使用指南</a></strong>。
		腾讯云 COS 用户：请点击这里注册和申请<a href="http://wpjam.com/go/qcloud/" target="_blank">腾讯云</a>可获得优惠券，腾讯云COS<strong><a href="https://blog.wpjam.com/m/qcloud-cos-cdn/" target="_blank">详细使用指南</a></strong>。';

		$options	= array_merge([''=>' '], wp_list_pluck(WPJAM_CDN_Type::get_by(), 'title'));

		$cdn_fields		= [
			'guide'		=> ['title'=>'使用说明',		'type'=>'view',		'value'=>wpautop($detail)],
			'cdn_name'	=> ['title'=>'云存储',		'type'=>'select',	'options'=>$options,	'class'=>'show-if-key'],
			'host'		=> ['title'=>'CDN 域名',		'type'=>'url',		'show_if'=>self::get_show_if('!=',''),	'description'=>'设置为在CDN云存储绑定的域名。'],
			'disabled'	=> ['title'=>'使用本站',		'type'=>'checkbox',	'show_if'=>self::get_show_if('=',''),	'description'=>'如使用 CDN 之后切换回使用本站图片，请勾选该选项，并将原 CDN 域名填回「本地设置」的「额外域名」中。'],
			'image'		=> ['title'=>'图片处理',		'type'=>'checkbox',	'show_if'=>self::get_show_if('IN', ['aliyun_oss', 'qcloud_cos', 'qiniu']),	'value'=>1,	'description'=>'开启云存储图片处理功能，使用云存储进行裁图、添加水印等操作。<br />&emsp;* 注意：开启云存储图片处理功能，文章和媒体库中的所有图片都会镜像到云存储。'],
		];

		$local_fields	= [
			'local'		=> ['title'=>'本地域名',		'type'=>'url',		'value'=>home_url(),	'description'=>'将该域名填入<strong>云存储的镜像源</strong>。'],
			'no_http'	=> ['title'=>'无HTTP替换',	'type'=>'checkbox',	'show_if'=>self::get_show_if('!=',''),	'description'=>'将无<code>http://</code>或<code>https://</code>的静态资源也进行镜像处理'],
			'exts'		=> ['title'=>'扩展名',		'type'=>'mu-text',	'value'=>['png','jpg','gif','ico'],		'class'=>'',	'description'=>'设置要镜像的静态文件的扩展名。'],
			'dirs'		=> ['title'=>'目录',			'type'=>'mu-text',	'value'=>['wp-content','wp-includes'],	'class'=>'',	'description'=>'设置要镜像的静态文件所在的目录。'],
			'locals'	=> ['title'=>'额外域名',		'type'=>'mu-text',	'item_type'=>'url'],
		];

		$image_fields	= [
			'thumbnail_set'	=> ['title'=>'缩图设置',	'type'=>'fieldset',	'fields'=>[
				'no_subsizes'	=> ['type'=>'checkbox',	'value'=>1,	'description'=>'云存储有更强大的缩图功能，本地不用再生成缩略图。'],
				'thumbnail'		=> ['type'=>'checkbox',	'value'=>1,	'description'=>'使用云存储缩图功能对文章内容中的图片进行最佳尺寸显示处理。'],
				'max_view'		=> ['type'=>'view',		'group'=>'max',	'show_if'=>['key'=>'thumbnail', 'value'=>1],	'value'=>'文章中图片最大宽度：'],
				'max_width'		=> ['type'=>'number',	'group'=>'max',	'show_if'=>['key'=>'thumbnail', 'value'=>1],	'value'=>($GLOBALS['content_width'] ?? 0),	'class'=>'small-text',	'description'=>'px。']
			]],
			'image_set'		=> ['title'=>'格式质量',	'type'=>'fieldset',	'fields'=>[
				'webp'			=> ['type'=>'checkbox',	'description'=>'将图片转换成WebP格式，仅支持阿里云OSS和腾讯云COS。'],
				'interlace'		=> ['type'=>'checkbox',	'description'=>'JPEG格式图片渐进显示。'],
				'quality_view'	=> ['type'=>'view',		'group'=>'quality',	'value'=>'图片质量：'],
				'quality'		=> ['type'=>'number',	'group'=>'quality',	'class'=>'small-text',	'mim'=>0,	'max'=>100]
			]],
		];

		$watermark_options = [
			'SouthEast'	=> '右下角',
			'SouthWest'	=> '左下角',
			'NorthEast'	=> '右上角',
			'NorthWest'	=> '左上角',
			'Center'	=> '正中间',
			'West'		=> '左中间',
			'East'		=> '右中间',
			'North'		=> '上中间',
			'South'		=> '下中间',
		];

		$watermark_fields = [
			'watermark'	=> ['title'=>'水印图片',	'type'=>'image',	'description'=>'请使用云存储域名下的图片'],
			'disslove'	=> ['title'=>'透明度',	'type'=>'number',	'class'=>'small-text',	'description'=>'取值范围：1-100，默认为100（不透明）',	'min'=>0,	'max'=>100],
			'set'		=> ['title'=>'位置边距',	'type'=>'fieldset',	'fields'=>[
				'gravity_v'	=> ['type'=>'view',		'group'=>'gravity',	'value'=>'水印位置：'],
				'gravity'	=> ['type'=>'select',	'group'=>'gravity',	'options'=>$watermark_options],
				'dx_v'		=> ['type'=>'view',		'group'=>'dx',		'value'=>'横轴边距：'],
				'dx'		=> ['type'=>'number',	'group'=>'dx',		'class'=>'small-text',	'value'=>10,	'description'=>'px'],
				'dy_v'		=> ['type'=>'view',		'group'=>'dy',		'value'=>'纵轴边距：'],
				'dy'		=> ['type'=>'number',	'group'=>'dy',		'class'=>'small-text',	'value'=>10,	'description'=>'px']
			]],
		];

		if(is_network_admin()){
			unset($local_fields['local']);
			unset($watermark_fields['watermark']);
		}

		$remote_fields	= [];
		$remote_summary	= '';

		if(!wpjam_basic_get_setting('upload_external_images')){
			if(!is_multisite() && $GLOBALS['wp_rewrite']->using_mod_rewrite_permalinks() && extension_loaded('gd')){
				$remote_options	= [
					''	=>'关闭外部图片镜像到云存储',
					'1'	=>'自动将外部图片镜像到云存储（不推荐）'
				];

				$remote_summary	= '*自动将外部图片镜像到云存储需要博客支持固定链接和服务器支持GD库（不支持gif图片）';

				$remote_fields['remote']	= ['title'=>'外部图片',	'type'=>'select',	'options'=>$remote_options];
			}else{
				$remote_fields['external']	= ['title'=>'外部图片',	'type'=>'view',	'value'=>'请先到「文章设置」中开启「支持在文章列表页上传外部图片」'];
			}
		}else{
			$remote_fields['external']	= ['title'=>'外部图片',	'type'=>'view',	'value'=>'已在「文章设置」中开启「支持在文章列表页上传外部图片」'];
		}

		$remote_fields['exceptions']	= ['title'=>'例外',	'type'=>'textarea',	'class'=>'regular-text','description'=>'如果外部图片的链接中包含以上字符串或域名，就不会被保存并镜像到云存储。'];

		return [
			'cdn'		=> ['title'=>'云存储设置',	'fields'=>$cdn_fields],
			'local'		=> ['title'=>'本地设置',		'fields'=>$local_fields],
			'image'		=> ['title'=>'图片设置',		'fields'=>$image_fields,	'show_if'=>['key'=>'image', 'compare'=>'=', 'value'=>1]],
			'watermark'	=> ['title'=>'水印设置',		'fields'=>$watermark_fields,'show_if'=>['key'=>'image', 'compare'=>'=', 'value'=>1]],
			'remote'	=> ['title'=>'外部图片',		'fields'=>$remote_fields,	'show_if'=>self::get_show_if('!=', ''),	'summary'=>$remote_summary],
		];
	}
}

class WPJAM_CDN_Type{
	use WPJAM_Register_Trait;
}

function wpjam_register_cdn($name, $args){
	WPJAM_CDN_Type::register($name, $args);
}

function wpjam_unregister_cdn($name){
	WPJAM_CDN_Type::unregister($name);
}

function wpjam_cdn_get_setting($name, $default=null){
	return WPJAM_CDN_Setting::get_instance()->get_setting($name,$default);
}

function wpjam_cdn_update_setting($name, $value){
	return WPJAM_CDN_Setting::get_instance()->update_setting($name, $value);
}

function wpjam_is_cdn_url($url){
	return WPJAM_CDN::is_cdn_url($url);
}

function wpjam_cdn_host_replace($html, $to_cdn=true){
	return WPJAM_CDN::host_replace($html, $to_cdn);
}

wpjam_register_cdn('aliyun_oss',	['title'=>'阿里云OSS',	'file'=>WPJAM_BASIC_PLUGIN_DIR.'cdn/aliyun_oss.php']);
wpjam_register_cdn('qcloud_cos',	['title'=>'腾讯云COS',	'file'=>WPJAM_BASIC_PLUGIN_DIR.'cdn/qcloud_cos.php']);
wpjam_register_cdn('ucloud',		['title'=>'UCloud', 	'file'=>WPJAM_BASIC_PLUGIN_DIR.'cdn/ucloud.php']);
wpjam_register_cdn('qiniu',			['title'=>'七牛云存储',	'file'=>WPJAM_BASIC_PLUGIN_DIR.'cdn/qiniu.php']);

add_action('plugins_loaded', function(){
	$local	= wpjam_cdn_get_setting('local');

	define('CDN_NAME',		wpjam_cdn_get_setting('cdn_name'));
	define('CDN_HOST',		untrailingslashit(wpjam_cdn_get_setting('host') ?: site_url()));
	define('LOCAL_HOST',	untrailingslashit($local ? set_url_scheme($local): site_url()));

	if(CDN_NAME){
		do_action('wpjam_cdn_loaded');

		if(!is_admin()){
			if(wpjam_is_json_request()){
				add_filter('the_content',	['WPJAM_CDN', 'filter_html'], 5);
			}else{
				add_filter('wpjam_html',	['WPJAM_CDN', 'filter_html'], 9);
			}
		}else{
			foreach(['exts', 'dirs'] as $k){
				if($v = wpjam_cdn_get_setting($k)){
					if(!is_array($v)){
						wpjam_cdn_update_setting($k, WPJAM_CDN::parse_items($v));
					}
				}
			}
		}

		add_filter('wpjam_is_external_image',	['WPJAM_CDN', 'filter_is_external_image'], 10, 3);
		add_filter('wp_resource_hints',			['WPJAM_CDN', 'filter_wp_resource_hints'], 10, 2);

		if(wpjam_cdn_get_setting('image', 1)){
			$type_obj	= WPJAM_CDN_Type::get(CDN_NAME);
			$cdn_file	= $type_obj ? $type_obj->file : '';

			if($cdn_file && file_exists($cdn_file)){
				$callback	= include $cdn_file;

				if($callback !== 1 && is_callable($callback)){
					add_filter('wpjam_thumbnail', $callback, 10, 2);
				}
			}

			if(wpjam_cdn_get_setting('no_subsizes', 1)){
				add_filter('intermediate_image_sizes_advanced',	['WPJAM_CDN', 'filter_intermediate_image_sizes_advanced']);
			}

			if(wpjam_cdn_get_setting('thumbnail', 1)){
				add_filter('the_content',		['WPJAM_CDN', 'filter_content'], 5);
			}

			add_filter('wpjam_thumbnail',		['WPJAM_CDN', 'filter_thumbnail'], 1);
			add_filter('wp_get_attachment_url',	['WPJAM_CDN', 'filter_attachment_url'], 10, 2);
			// add_filter('upload_dir',			['WPJAM_CDN', 'filter_upload_dir']);
			add_filter('image_downsize',		['WPJAM_CDN', 'filter_image_downsize'], 10 ,3);
		}

		if(!wpjam_basic_get_setting('upload_external_images')){
			if(wpjam_cdn_get_setting('remote') === 'download'){
				if(is_admin()){
					wpjam_basic_update_setting('upload_external_images', 1);
					wpjam_cdn_update_setting('remote', 0);
				}
			}elseif(wpjam_cdn_get_setting('remote')){
				if(!is_multisite()){
					include WPJAM_BASIC_PLUGIN_DIR.'cdn/remote.php';
				}
			}
		}
	}else{
		if(wpjam_cdn_get_setting('disabled')){
			if(!is_admin() && !wpjam_is_json_request()){
				add_filter('wpjam_html',	['WPJAM_CDN', 'filter_html'], 9);
			}

			add_filter('the_content',		['WPJAM_CDN', 'filter_html'], 5);
			add_filter('wpjam_thumbnail',	['WPJAM_CDN', 'filter_html'], 9);
		}
	}
}, 99);