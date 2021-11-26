<?php
function wpjam_get_aliyun_oss_thumbnail($img_url, $args=[]){
	if($img_url && (!wpjam_is_image($img_url) || !wpjam_is_cdn_url($img_url))){
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

	$thumb_arg	= '';

	if($width || $height){
		if(is_null($mode)){
			$crop	= $crop && ($width && $height);	// 只有都设置了宽度和高度才裁剪
			$mode	= $crop ? ',m_fill' : '';
		}

		$thumb_arg	.= '/resize'.$mode;

		if($width){
			$thumb_arg .= ',w_'.$width;
		}

		if($height){
			$thumb_arg .= ',h_'.$height;
		}
	}

	if($webp && wpjam_is_webp_supported()){
		$thumb_arg	.= '/format,webp';
	}else{
		if($interlace){
			$thumb_arg	.= '/interlace,1';
		}
	}

	if($quality){
		$thumb_arg	.= '/quality,Q_'.$quality;
	}

	if(!empty($args['content']) && strpos($img_url, '.gif') === false){
		$watermark	= wpjam_cdn_get_setting('watermark');

		if($watermark && strpos($watermark, CDN_HOST.'/') !== false){
			if($watermark = str_replace(CDN_HOST.'/', '', $watermark)){
				$thumb_arg	.= '/watermark,image_'.str_replace(['+','/'], ['-','_'], base64_encode($watermark));

				$dissolve	= wpjam_cdn_get_setting('dissolve');

				if($dissolve && $dissolve != 100){
					$thumb_arg	.= ',t_'.$dissolve;
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

					$thumb_arg	.= ',g_'.$gravity_options[$gravity];
				}

				if($dx	= wpjam_cdn_get_setting('dx')){
					$thumb_arg	.= ',x_'.$dx;
				}
				
				if($dy	= wpjam_cdn_get_setting('dy')){
					$thumb_arg	.= ',y_'.$dy;
				}
			}
		}
	}

	$query_args	= [];

	if($thumb_arg){
		$thumb_arg	= 'x-oss-process=image'.$thumb_arg;

		if($query = parse_url($img_url, PHP_URL_QUERY)){
			$img_url	= str_replace('?'.$query, '', $img_url);

			if($query_args	= wp_parse_args($query)){
				$query_args	= array_filter($query_args, function($v, $k){
					return strpos($k, 'x-oss-process=image/') === false;
				}, ARRAY_FILTER_USE_BOTH);
			}
		}

		$query_args[$thumb_arg]	= '';

		$img_url	= add_query_arg($query_args, $img_url).'#';
	}

	return $img_url;
}

return 'wpjam_get_aliyun_oss_thumbnail';