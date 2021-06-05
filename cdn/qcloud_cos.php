<?php
add_filter('wpjam_thumbnail','wpjam_get_qcloud_cos_thumbnail',10,2);

function wpjam_get_qcloud_cos_thumbnail($img_url, $args=[]){
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

	if($height > 10000){
		$height = 0;
	}

	if($width > 10000){
		$width = 0;
	}

	$arg	= '';

	if($width || $height){
		$arg	.= '/thumbnail/';

		if($width && $height){
			$arg	.= '!'.$width.'x'.$height.'r';
		}elseif($width){
			$arg	.= $width.'x';
		}elseif($height){
			$arg	.= 'x'.$height.'';
		}

		$crop	= $crop && ($width && $height);	// 只有都设置了宽度和高度才裁剪

		if($crop){
			$arg	.= '/gravity/Center/crop/';

			if($width && $height){
				$arg	.= $width.'x'.$height.'';
			}elseif($width){
				$arg	.= $width.'x';
			}elseif($height){
				$arg	.= 'x'.$height.'';
			}
		}
	}

	if($webp && wpjam_is_webp_supported()){
		$arg	.= '/format/webp';
	}else{
		if($quality){
			$arg	.= '/quality/'.$quality;
		}

		if($interlace){
			$arg	.= '/interlace/'.$interlace;
		}
	}

	if($arg){
		if(strpos($img_url, 'imageMogr2/')){
			$img_url	= preg_replace('/imageMogr2\/(.*?)#/', '', $img_url);
		}

		$arg	= 'imageMogr2'.$arg;

		if(strpos($img_url, 'watermark/')){
			$img_url	= $img_url.'|'.$arg;
		}else{
			$img_url	= add_query_arg([$arg=>''], $img_url);
		}
	}

	if(!empty($args['content']) && strpos($img_url, 'watermark/') === false){
		$img_url	= wpjam_get_qcloud_cos_watermark($img_url);
	}

	return $img_url;
}

function wpjam_get_qcloud_cos_watermark($img_url, $args=[]){
	extract(wp_parse_args($args, array(
		'watermark'	=> wpjam_cdn_get_setting('watermark'),
		'dissolve'	=> wpjam_cdn_get_setting('dissolve') ?: 100,
		'gravity'	=> wpjam_cdn_get_setting('gravity') ?: 'SouthEast',
		'dx'		=> wpjam_cdn_get_setting('dx') ?: 10,
		'dy'		=> wpjam_cdn_get_setting('dy') ?: 10,
	)));

	if($watermark){
		$watermark	= str_replace(array('+','/'),array('-','_'),base64_encode($watermark));
		
		$arg 	= 'watermark/1/image/'.$watermark.'/dissolve/'.$dissolve.'/gravity/'.$gravity.'/dx/'.$dx.'/dy/'.$dy.'/spcent/10';

		if(strpos($img_url, 'imageMogr2')){
			$img_url = $img_url.'|'.$arg;
		}else{
			$img_url = add_query_arg([$arg=>''], $img_url);
		}
	}

	return $img_url;
}