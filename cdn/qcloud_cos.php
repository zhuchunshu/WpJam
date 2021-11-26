<?php
function wpjam_get_qcloud_cos_thumbnail($img_url, $args=[]){
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

	if($height > 10000){
		$height = 0;
	}

	if($width > 10000){
		$width = 0;
	}

	$thumb_arg	= '';

	if($width || $height){
		$thumb_arg	.= '/thumbnail/';

		if($width && $height){
			$thumb_arg	.= '!'.$width.'x'.$height.'r';
		}elseif($width){
			$thumb_arg	.= $width.'x';
		}elseif($height){
			$thumb_arg	.= 'x'.$height.'';
		}

		$crop	= $crop && ($width && $height);	// 只有都设置了宽度和高度才裁剪

		if($crop){
			$thumb_arg	.= '/gravity/Center/crop/';

			if($width && $height){
				$thumb_arg	.= $width.'x'.$height.'';
			}elseif($width){
				$thumb_arg	.= $width.'x';
			}elseif($height){
				$thumb_arg	.= 'x'.$height.'';
			}
		}
	}

	if($webp && wpjam_is_webp_supported()){
		$thumb_arg	.= '/format/webp';
	}else{
		if($quality){
			$thumb_arg	.= '/quality/'.$quality;
		}

		if($interlace){
			$thumb_arg	.= '/interlace/'.$interlace;
		}
	}

	if($thumb_arg){
		$thumb_arg	= 'imageMogr2'.$thumb_arg;
	}

	if(!empty($args['content']) && strpos($img_url, '.gif') === false){
		if($watermark_arg	= wpjam_get_qcloud_cos_watermark_arg()){
			$thumb_arg	.= $thumb_arg ? '|' : '';
			$thumb_arg	.= 'watermark/'.$watermark_arg;
		}
	}

	$query_args	= [];

	if($thumb_arg){
		if($query = parse_url($img_url, PHP_URL_QUERY)){
			$img_url	= str_replace('?'.$query, '', $img_url);

			if($query_args	= wp_parse_args($query)){
				$query_args	= array_filter($query_args, function($v, $k){
					return strpos($k, 'imageMogr2/') === false && strpos($k, 'watermark/') === false;
				}, ARRAY_FILTER_USE_BOTH);
			}
		}

		$query_args[$thumb_arg]	= '';
	}

	if($query_args){
		$img_url	= add_query_arg($query_args, $img_url);
	}

	return $img_url;
}

function wpjam_get_qcloud_cos_watermark_arg($args=[]){
	extract(wp_parse_args($args, array(
		'watermark'	=> wpjam_cdn_get_setting('watermark'),
		'dissolve'	=> wpjam_cdn_get_setting('dissolve') ?: 100,
		'gravity'	=> wpjam_cdn_get_setting('gravity') ?: 'SouthEast',
		'dx'		=> wpjam_cdn_get_setting('dx') ?: 10,
		'dy'		=> wpjam_cdn_get_setting('dy') ?: 10,
	)));

	if($watermark){
		$watermark	= str_replace(array('+','/'),array('-','_'),base64_encode($watermark));
		
		return '1/image/'.$watermark.'/dissolve/'.$dissolve.'/gravity/'.$gravity.'/dx/'.$dx.'/dy/'.$dy.'/spcent/10';
	}

	return '';
}

return 'wpjam_get_qcloud_cos_thumbnail';