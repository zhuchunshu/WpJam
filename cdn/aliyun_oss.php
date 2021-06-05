<?php
add_filter('wpjam_thumbnail','wpjam_get_aliyun_oss_thumbnail', 10, 2);

function wpjam_get_aliyun_oss_thumbnail($img_url, $args=[]){
	if($img_url && (!wpjam_is_image($img_url) || wpjam_is_remote_image($img_url))){
		return $img_url;
	}
	
	extract(wp_parse_args($args, [
		'mode'		=> null,
		'crop'		=> 1,
		'width'		=> 0,
		'height'	=> 0,
		'webp'		=> wpjam_cdn_get_setting('webp'),
		'interlace'	=> wpjam_cdn_get_setting('interlace'),
		'quality'	=> wpjam_cdn_get_setting('quality')
	]));

	if($height > 4096){
		$height = 0;
	}

	if($width > 4096){
		$width = 0;
	}

	$arg	= '';

	if($width || $height){
		if(is_null($mode)){
			$crop	= $crop && ($width && $height);	// 只有都设置了宽度和高度才裁剪
			$mode	= $crop ? ',m_fill' : '';
		}

		$arg	.= '/resize'.$mode;

		if($width){
			$arg .= ',w_'.$width;
		}

		if($height){
			$arg .= ',h_'.$height;
		}
	}

	if($webp && wpjam_is_webp_supported()){
		$arg	.= '/format,webp';
	}else{
		if($interlace){
			$arg	.= '/interlace,1';
		}
	}

	if($quality){
		$arg	.= '/quality,Q_'.$quality;
	}

	if(!empty($args['content'])){
		$watermark	= wpjam_cdn_get_setting('watermark');

		if($watermark && strpos($watermark, CDN_HOST.'/') !== false){
			if($watermark = str_replace(CDN_HOST.'/', '', $watermark)){
				$arg	.= '/watermark,image_'.str_replace(['+','/'], ['-','_'], base64_encode($watermark));

				$dissolve	= wpjam_cdn_get_setting('dissolve');

				if($dissolve && $dissolve != 100){
					$arg	.= ',t_'.$dissolve;
				}

				if($gravity = wpjam_cdn_get_setting('gravity')){
					$gravity_options = [
						'SouthEast'	=> 'se',
						'SouthWest'	=> 'sw',
						'NorthEast'	=> 'ne',
						'NorthWest'	=> 'nw',
						'Center'	=> 'center',
						'West'		=> 'west',
						'East'		=> 'east',
						'North'		=> 'north',
						'South'		=> 'south',
					];

					$arg	.= ',g_'.$gravity_options[$gravity];
				}

				if($dx	= wpjam_cdn_get_setting('dx')){
					$arg	.= ',x_'.$dx;
				}
				
				if($dy	= wpjam_cdn_get_setting('dy')){
					$arg	.= ',y_'.$dy;
				}
			}
		}
	}

	if($arg){
		$arg	= 'x-oss-process=image'.$arg;

		if(strpos($img_url, 'x-oss-process=image')){
			$img_url	= preg_replace('/x-oss-process=image\/(.*?)#/', '', $img_url);
		}

		$img_url	= add_query_arg([$arg=>''], $img_url).'#';
	}

	return $img_url;
}