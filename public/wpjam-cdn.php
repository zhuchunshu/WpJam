<?php
class WPJAM_CDN{
	use WPJAM_Setting_Trait;

	private $cdn_name	= '';
	private $cdn_host	= '';
	private $local_host	= '';

	private function __construct(){
		$this->init('wpjam-cdn', true);

		$local	= $this->get_setting('local');

		$this->cdn_name		= $this->get_setting('cdn_name');
		$this->cdn_host		= untrailingslashit($this->get_setting('host') ?: site_url());
		$this->local_host	= untrailingslashit($local ? set_url_scheme($local): site_url());

		// 兼容代码，以后可以去掉
		define('CDN_NAME',		$this->cdn_name);
		define('CDN_HOST',		$this->cdn_host);
		define('LOCAL_HOST',	$this->local_host);

		if($cdn_name = $this->cdn_name){
			$cdn_file	= wpjam_get_cdn_file($cdn_name);

			if($cdn_file && file_exists($cdn_file)){
				include $cdn_file;
			}
		}
	}

	public function host_replace($html, $to_cdn=true){
		$local_hosts	= $this->get_setting('locals') ?: [];

		if($to_cdn){
			$local_hosts[]	= str_replace('https://', 'http://', $this->local_host);
			$local_hosts[]	= str_replace('http://', 'https://', $this->local_host);

			if(strpos($this->cdn_host, 'http://') === 0){
				$local_hosts[]	= str_replace('http://', 'https://', $this->cdn_host);
			}
		}else{
			if(strpos($this->local_host, 'https://') !== false){
				$local_hosts[]	= str_replace('https://', 'http://', $this->local_host);
			}else{
				$local_hosts[]	= str_replace('http://', 'https://', $this->local_host);
			}
		}

		$local_hosts	= apply_filters('wpjam_cdn_local_hosts', $local_hosts);
		$local_hosts	= array_map('untrailingslashit', array_unique($local_hosts));

		if($to_cdn){
			return str_replace($local_hosts, $this->cdn_host, $html);
		}else{
			return str_replace($local_hosts, $this->local_host, $html);
		}
	}

	public function html_replace($html){
		if(empty($this->cdn_name) && $this->get_setting('disabled')){
			$local_hosts	= $this->get_setting('locals') ?: [];			

			$local_hosts	= apply_filters('wpjam_cdn_local_hosts', $local_hosts);
			$local_hosts	= array_map('untrailingslashit', array_unique($local_hosts));

			return str_replace($local_hosts, $this->local_host, $html);
		}else{
			$dirs	= $this->get_setting('dirs') ?: [];
			$exts	= $this->get_setting('exts') ?: [];

			if($exts){
				$html	= $this->host_replace($html, false);

				if($dirs && !is_array($dirs)){
					$dirs	= explode('|', $dirs);
				}

				if(!is_array($exts)){
					$exts	= explode('|', $exts);
				}

				$dirs	= array_unique(array_filter(array_map('trim', $dirs)));
				$exts	= array_unique(array_filter(array_map('trim', $exts)));

				if(is_login()){
					$exts	= array_diff($exts, ['js','css']);
				}

				$exts	= implode('|', $exts);
				$dirs	= implode('|', $dirs);

				if($this->get_setting('no_http')){
					$local_host_no_http	= str_replace(['http://', 'https://'], '//', $this->local_host);
				}

				if($dirs){
					$dirs	= str_replace(['-','/'],['\-','\/'], $dirs);
					$regex	= '/'.str_replace('/','\/',$this->local_host).'\/(('.$dirs.')\/[^\s\?\\\'\"\;\>\<]{1,}.('.$exts.'))([\"\\\'\)\s\]\?]{1})/';
					$html	= preg_replace($regex, $this->cdn_host.'/$1$4', $html);

					if($this->get_setting('no_http')){
						$regex	= '/'.str_replace('/','\/',$local_host_no_http).'\/(('.$dirs.')\/[^\s\?\\\'\"\;\>\<]{1,}.('.$exts.'))([\"\\\'\)\s\]\?]{1})/';
						$html	= preg_replace($regex, $this->cdn_host.'/$1$4', $html);
					}
				}else{
					$regex	= '/'.str_replace('/','\/',$this->local_host).'\/([^\s\?\\\'\"\;\>\<]{1,}.('.$exts.'))([\"\\\'\)\s\]\?]{1})/';
					$html	= preg_replace($regex, $this->cdn_host.'/$1$3', $html);

					if($this->get_setting('no_http')){
						$regex	= '/'.str_replace('/','\/',$local_host_no_http).'\/([^\s\?\\\'\"\;\>\<]{1,}.('.$exts.'))([\"\\\'\)\s\]\?]{1})/';
						$html	= preg_replace($regex, $this->cdn_host.'/$1$3', $html);
					}
				}
			}
		}

		return $html;
	}

	public function content_images($content, $max_width=null){
		if(doing_filter('get_the_excerpt') || false === strpos($content, '<img')){
			return $content;
		}

		if(is_null($max_width)){
			$max_width	= (int)apply_filters('wpjam_content_image_width', ($GLOBALS['content_width'] ?? 0));
		}

		if($max_width){
			add_filter('wp_img_tag_add_srcset_and_sizes_attr', '__return_false');
			remove_filter('the_content', 'wp_filter_content_tags');
		}

		$content	= $this->host_replace($content, false);

		if(!preg_match_all('/<img.*?src=[\'"](.*?)[\'"].*?>/i', $content, $matches)){
			return $content;
		}

		$ratio	= 2;
		$search	= $replace = [];

		foreach($matches[0] as $i => $img_tag){
		 	$img_url	= $matches[1][$i];

		 	if(empty($img_url)){
		 		continue;
		 	}
		 
		 	if($this->is_remote_image($img_url)){
		 		$new_img_url	= apply_filters('wpjam_content_remote_image', $img_url);

		 		if($img_url == $new_img_url){
		 			continue;
		 		}else{
		 			$img_url = $new_img_url;
		 		}
			}

			$size	= ['width'=>0, 'height'=>0, 'content'=>true];

			if(preg_match_all('/(width|height)=[\'"]([0-9]+)[\'"]/i', $img_tag, $hw_matches)){
				$hw_arr	= array_flip($hw_matches[1]);
				$size	= array_merge($size, array_combine($hw_matches[1], $hw_matches[2]));
			}

			$width		= $size['width'];

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

	public function is_remote_image($img_url){
		$status	= strpos($this->host_replace($img_url), $this->cdn_host) === false;

		return apply_filters('wpjam_is_remote_image', $status, $img_url);
	}

	public function fetch_remote_images($content){
		if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE){
			return $content;
		}

		if(get_current_screen()->base != 'post'){
			return $content;
		}

		if(!preg_match_all('/<img.*?src=\\\\[\'"](.*?)\\\\[\'"].*?>/i', $content, $matches)){
			return $content;
		}

		$update		= false;
		$search		= $replace	= [];
		$img_urls	= array_unique($matches[1]);
		$img_tags	= $matches[0];
		$exceptions	= $this->get_setting('exceptions');
		$exceptions	= $exceptions ? explode("\n", $exceptions) : [];

		foreach($img_urls as $i => $img_url){
			if(empty($img_url) || !$this->is_remote_image($img_url)){
				continue;
			}

			if($exceptions){
				foreach ($exceptions as $exception) {
					if(trim($exception) && strpos($img_url, trim($exception)) !== false ){
						continue;
					}
				}
			}

			$attachment_id	= wpjam_download_image($img_url, '', true);

			if(!is_wp_error($attachment_id)){
				$search[]	= $img_url;
				$replace[]	= wp_get_attachment_url($attachment_id);
				$update		= true;
			}
		}

		if($update){
			if(is_multisite()){
				setcookie('wp-saving-post', $_POST['post_ID'].'-saved', time()+DAY_IN_SECONDS, ADMIN_COOKIE_PATH, false, is_ssl());
			}

			$content	= str_replace($search, $replace, $content);
		}

		return $content;
	}

	public function filter_intermediate_image_sizes_advanced($sizes){
		return isset($sizes['full']) ? ['full'=>$sizes['full']] : [];
	}

	public function filter_image_size_names_choose($sizes){
		$new_sizes	= ['full'=>$sizes['full']];

		unset($sizes['full']);

		foreach(['large', 'medium', 'thumbnail'] as $key){
			if(get_option($key.'_size_w') || get_option($key.'_size_h')){
				$new_sizes[$key]	= $sizes[$key];
			}

			unset($sizes[$key]);
		}

		return $sizes ? array_merge($sizes, $new_sizes) : $new_sizes;
	}

	public function filter_attachment_url($url, $id){
		// if(wp_attachment_is_image($id)){
		// 	return $this->host_replace($url);
		// }

		if($exts = $this->get_setting('exts')){
			if(!is_array($exts)){
				$exts	= explode('|', $exts);
			}

			if($file = get_attached_file($id)){
				$check	= wp_check_filetype($file);
				$ext	= $check['ext'] ?? '';

				if($ext && in_array($ext, $exts)){
					return $this->host_replace($url);
				}
			}
		}

		return $url;
	}

	public function filter_image_downsize($out, $id, $size){
		if(wp_attachment_is_image($id)){
			$meta	= wp_get_attachment_metadata($id);
			
			if(is_array($meta) && isset($meta['width'], $meta['height'])){
				$img_url	= wp_get_attachment_url($id);
				$ratio		= 2;
				$size		= wpjam_parse_size($size, $ratio);

				if($size['crop']){
					$width	= min($size['width'],	$meta['width']);
					$height	= min($size['height'],	$meta['height']);
				}else{
					list($width, $height)	= wp_constrain_dimensions($meta['width'], $meta['height'], $size['width'], $size['height']);
				}

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

	public function filter_wp_resource_hints($urls, $relation_type){
		return $relation_type == 'dns-prefetch' ? $urls+[$this->cdn_host] : $urls;
	}

	public function filter_option_value($value){
		foreach (['exts', 'dirs'] as $k) {
			$v	= $value[$k] ?? [];

			if($v){
				if(!is_array($v)){
					$v	= explode('|', $v);
				}

				$v = array_unique(array_filter(array_map('trim', $v)));
			}

			$value[$k]	= $v;
		};

		return $value;
	}

	public function load_option_page($plugin_page){
		$detail = '阿里云 OSS 用户：请点击这里注册和申请<a href="http://wpjam.com/go/aliyun/" target="_blank">阿里云</a>可获得代金券，阿里云OSS<strong><a href="https://blog.wpjam.com/m/aliyun-oss-cdn/" target="_blank">详细使用指南</a></strong>。
		腾讯云 COS 用户：请点击这里注册和申请<a href="http://wpjam.com/go/qcloud/" target="_blank">腾讯云</a>可获得优惠券，腾讯云COS<strong><a href="https://blog.wpjam.com/m/qcloud-cos-cdn/" target="_blank">详细使用指南</a></strong>。';

		$options	= array_merge([''=>' '], wp_list_pluck(WPJAM_CDN_Type::get_by(), 'title'));

		$cdn_fields		= [
			'cdn_name'	=> ['title'=>'云存储',		'type'=>'select',	'options'=>$options,	'class'=>'show-if-key'],
			'disabled'	=> ['title'=>'使用本站',		'type'=>'checkbox',	'show_if'=>['key'=>'cdn_name', 'compare'=>'=', 'value'=>''],	'description'=>'如使用 CDN 之后切换回使用本站图片，请勾选该选项，并将原 CDN 域名填回「本地设置」的「额外域名」中。'],
			'host'		=> ['title'=>'CDN 域名',		'type'=>'url',		'show_if'=>['key'=>'cdn_name', 'compare'=>'!=', 'value'=>''],	'description'=>'设置为在CDN云存储绑定的域名。'],
			'no_http'	=> ['title'=>'无 HTTP 替换',	'type'=>'checkbox',	'show_if'=>['key'=>'cdn_name', 'compare'=>'!=', 'value'=>''],	'description'=>'将无 <code>http://</code> 或者 <code>https://</code> 的图片链接也替换成 CDN 链接'],
			'guide'		=> ['title'=>'使用说明',		'type'=>'view',		'value'=>wpautop($detail)],
		];

		$local_fields	= [
			'exts'		=> ['title'=>'扩展名',	'type'=>'mu-text',	'value'=>['png','jpg','gif','ico'],		'class'=>'',	'description'=>'设置要缓存静态文件的扩展名。'],
			'dirs'		=> ['title'=>'目录',		'type'=>'mu-text',	'value'=>['wp-content','wp-includes'],	'class'=>'',	'description'=>'设置要缓存静态文件所在的目录。'],
			'local'		=> ['title'=>'本地域名',	'type'=>'url',		'value'=>home_url(),	'description'=>'将该域名填入<strong>云存储的镜像源</strong>。'],
			'locals'	=> ['title'=>'额外域名',	'type'=>'mu-text',	'item_type'=>'url'],
		];

		$remote_options	= [
			0			=>'关闭远程图片镜像到云存储。',
			1			=>'自动将远程图片镜像到云存储。',
			'download'	=>'将远程图片下载服务器再镜像到云存储。'
		];

		if(is_multisite() || !$GLOBALS['wp_rewrite']->using_mod_rewrite_permalinks() || !extension_loaded('gd')){
			unset($remote_options[1]);
		}

		$remote_fields	= [
			'remote'		=> ['title'=>'远程图片',	'type'=>'select',	'options'=>$remote_options],
			'exceptions'	=> ['title'=>'例外',		'type'=>'textarea',	'class'=>'regular-text','description'=>'如果远程图片的链接中包含以上字符串或域名，就不会被保存并镜像到云存储。']
		];

		$image_fields	= [
			'webp'		=> ['title'=>'WebP格式',	'type'=>'checkbox',	'description'=>'将图片转换成WebP格式，仅支持阿里云OSS和腾讯云COS。'],
			'interlace'	=> ['title'=>'渐进显示',	'type'=>'checkbox',	'description'=>'是否JPEG格式图片渐进显示。'],
			'quality'	=> ['title'=>'图片质量',	'type'=>'number',	'class'=>'all-options',	'description'=>'<br />1-100之间图片质量。','mim'=>0,'max'=>100]
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
			'watermark'	=> ['title'=>'水印图片',	'type'=>'image',	'description'=>'请使用 CDN 域名下的图片'],
			'disslove'	=> ['title'=>'透明度',	'type'=>'number',	'class'=>'all-options',	'description'=>'<br />取值范围1-100，缺省值为100（不透明）','min'=>0,'max'=>100],
			'gravity'	=> ['title'=>'水印位置',	'type'=>'select',	'options'=>$watermark_options],
			'dx'		=> ['title'=>'横轴边距',	'type'=>'number',	'class'=>'all-options',	'description'=>'<br />单位:像素(px)，缺省值为10'],
			'dy'		=> ['title'=>'纵轴边距',	'type'=>'number',	'class'=>'all-options',	'description'=>'<br />单位:像素(px)，缺省值为10'],
		];

		if(is_network_admin()){
			unset($local_fields['local']);
			unset($watermark_fields['watermark']);
		}

		$remote_summary	= '
		*自动将远程图片镜像到云存储需要你的博客支持固定链接和服务器支持GD库（不支持gif图片）。
		*将远程图片下载服务器再镜像到云存储，会在你保存文章的时候自动执行。
		*古腾堡编辑器已自带上传外部图片的功能，如使用，在模块工具栏点击一下上传按钮。';

		$sections	= [
			'cdn'		=> ['title'=>'CDN设置',	'fields'=>$cdn_fields],
			'local'		=> ['title'=>'本地设置',	'fields'=>$local_fields],
			'image'		=> ['title'=>'图片设置',	'fields'=>$image_fields,	'show_if'=>['key'=>'cdn_name', 'compare'=>'IN', 'value'=>['aliyun_oss',	'qcloud_cos', 'qiniu']]],
			'watermark'	=> ['title'=>'水印设置',	'fields'=>$watermark_fields,'show_if'=>['key'=>'cdn_name', 'compare'=>'IN', 'value'=>['aliyun_oss',	'qcloud_cos', 'qiniu']]],
			'remote'	=> ['title'=>'远程图片',	'fields'=>$remote_fields,	'show_if'=>['key'=>'cdn_name', 'compare'=>'!=', 'value'=>''],	'summary'=>$remote_summary],
		];

		wpjam_register_option('wpjam-cdn', [
			'sections'		=> $sections, 
			'site_default'	=> true,
			'summary'		=>'CDN 加速让你使用云存储对博客的静态资源进行 CDN 加速，详细介绍请点击：<a href="https://blog.wpjam.com/m/wpjam-basic-cdn/" target="_blank">CDN 加速</a>。'
		]);

		add_filter('option_wpjam-cdn', [$this, 'filter_option_value']);
	}
}

class WPJAM_CDN_Type{
	use WPJAM_Register_Trait;
}

//注册 CDN 服务
function wpjam_register_cdn($name, $args){
	WPJAM_CDN_Type::register($name, $args);
}

function wpjam_unregister_cdn($name){
	WPJAM_CDN_Type::unregister($name);
}

function wpjam_get_cdn_file($name){
	$obj	= WPJAM_CDN_Type::get($name);
	return $obj ? $obj->file : '';
}

// 获取 CDN 设置
function wpjam_cdn_get_setting($name){
	return WPJAM_CDN::get_instance()->get_setting($name);
}

function wpjam_is_image($image_url){
	$ext_types	= wp_get_ext_types();
	$img_exts	= $ext_types['image'];

	$image_parts	= explode('?', $image_url);

	return preg_match('/\.('.implode('|', $img_exts).')$/i', $image_parts[0]);
}

function wpjam_is_remote_image($img_url){
	return WPJAM_CDN::get_instance()->is_remote_image($img_url);
}

function wpjam_cdn_host_replace($html, $to_cdn=true){
	return WPJAM_CDN::get_instance()->host_replace($html, $to_cdn);
}

wpjam_register_cdn('aliyun_oss',	['title'=>'阿里云OSS',	'file'=>WPJAM_BASIC_PLUGIN_DIR.'cdn/aliyun_oss.php']);
wpjam_register_cdn('qcloud_cos',	['title'=>'腾讯云COS',	'file'=>WPJAM_BASIC_PLUGIN_DIR.'cdn/qcloud_cos.php']);
wpjam_register_cdn('ucloud',		['title'=>'UCloud', 	'file'=>WPJAM_BASIC_PLUGIN_DIR.'cdn/ucloud.php']);
wpjam_register_cdn('qiniu',			['title'=>'七牛云存储',	'file'=>WPJAM_BASIC_PLUGIN_DIR.'cdn/qiniu.php']);

add_action('plugins_loaded', function(){
	$instance	= WPJAM_CDN::get_instance();

	if(is_admin()){
		wpjam_add_basic_sub_page('wpjam-cdn', ['menu_title'=>'CDN加速',	'function'=>'option',	'load_callback'=>[$instance, 'load_option_page'],	'order'=>19]);
	}

	if($instance->get_setting('cdn_name')){
		// 不用生成 -150x150.png 这类的图片
		add_filter('intermediate_image_sizes_advanced',	[$instance, 'filter_intermediate_image_sizes_advanced']);
		add_filter('image_size_names_choose',			[$instance, 'filter_image_size_names_choose']);

		add_filter('wpjam_thumbnail',		[$instance, 'host_replace'], 1);
		add_filter('wp_get_attachment_url',	[$instance, 'filter_attachment_url'], 10, 2);
		// add_filter('upload_dir',			[$instance, 'filter_upload_dir']);
		add_filter('image_downsize',		[$instance, 'filter_image_downsize'], 10 ,3);
		add_filter('wp_resource_hints',		[$instance, 'filter_wp_resource_hints'], 10, 2);
		add_filter('the_content',			[$instance, 'content_images'], 5);

		if(!is_admin()){
			add_filter('wpjam_html',	[$instance, 'html_replace'], 9);
		}

		if(wpjam_cdn_get_setting('remote') == 'download'){
			if(is_admin()){
				add_filter('content_save_pre', [$instance, 'fetch_remote_images']);
			}
		}elseif(wpjam_cdn_get_setting('remote')){
			if(!is_multisite()){
				include WPJAM_BASIC_PLUGIN_DIR.'cdn/remote.php';
			}
		}
	}else{
		if($instance->get_setting('disabled')){
			if(!is_admin()){
				add_filter('wpjam_html',	[$instance, 'html_replace'], 9);
			}
			
			add_filter('wpjam_thumbnail',	[$instance, 'html_replace'], 9);
		}
	}
}, 99);