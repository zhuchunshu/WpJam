<?php
/*
Name: 常用短代码
URI: https://blog.wpjam.com/m/wpjam-basic-shortcode/
Description: 添加 list, table 等常用短代码，并在后台罗列系统的所有短代码。 
Version: 1.0
*/
if(is_admin() && did_action('wpjam_plugin_page_load') && $GLOBALS['plugin_page'] = 'wpjam-shortcodes'){
	wpjam_set_plugin_page_summary('短代码扩展列出系统中所有的短代码，详细介绍请点击：<a href="https://blog.wpjam.com/m/wpjam-basic-shortcode/" target="_blank">短代码扩展</a>。');

	class WPJAM_Shortcodes_Admin{
		public static function query_items($limit, $offset){
			$shortcodes	= $GLOBALS['shortcode_tags'];
			$items		= [];

			foreach ($shortcodes as $tag => $function) {
				if(is_array($function)){
					if(is_object($function[0])){
						$function	= '<p>'.get_class($function[0]).'->'.(string)$function[1].'</p>';
					}else{
						$function	= '<p>'.$function[0].'->'.(string)$function[1].'</p>';
					}
				}elseif(is_object($function)){
					$function	= '<pre>'.print_r($function, true).'</pre>';
				}else{
					$function	= wpautop($function);
				}

				$tag		= wpautop($tag);
				$items[]	= compact('tag', 'function');
			}

			return ['items'=>$items, 'total'=>count($items)];
		}

		public static function get_actions(){
			return [];
		}

		public static function get_fields($action_key='', $id=0){
			return [
				'tag'		=> ['title'=>'短代码',	'type'=>'view',	'show_admin_column'=>true],
				'function'	=> ['title'=>'函数',		'type'=>'view',	'show_admin_column'=>true]
			];
		}
	}
}else{
	class WPJAM_Shortcode{
		public static function list($atts, $content=''){
			$atts		= shortcode_atts(['type'=>'', 'class'=>''], $atts);

			$content	= str_replace("\r\n", "\n", $content);
			$content	= str_replace("<br />\n", "\n", $content);
			$content	= str_replace("</p>\n", "\n", $content);
			$content	= str_replace("\n<p>", "\n", $content);

			$lists		= explode("\n", $content);

			$output		= '';

			foreach($lists as $li){
				$li = trim($li);
				if(empty($li)){
					continue;
				}

				$output .= "<li>".do_shortcode($li)."</li>\n";
			}

			$class	= $atts['class'] ? ' class="'.$atts['class'].'"' : '';

			if($atts['type']=="order" || $atts['type']=="ol"){
				return "<ol".$class.">\n".$output."</ol>\n";
			}else{
				return "<ul".$class.">\n".$output."</ul>\n";
			}
		}

		public static function table($atts, $content=''){
			$atts	= shortcode_atts([
				'border'		=> '0',
				'cellpading'	=> '0',
				'cellspacing'   => '0',
				'width'			=> '',
				'class'			=> '',
				'caption'		=> '',
				'th'			=> '0',  // 0-无，1-横向，2-纵向，4-横向并且有 footer 
			], $atts);

			$output		= $thead = $tbody = '';
			$content	= str_replace("\r\n", "\n", $content);
			$content	= str_replace("\r\n", "\n", $content);
			$content	= str_replace("<br />\n", "\n", $content);
			$content	= str_replace("</p>\n", "\n\n", $content);
			$content	= str_replace("\n<p>", "\n", $content);

			$trs		= explode("\n\n", $content);

			if($atts['caption']){
				$output	.= '<caption>'.$atts['caption'].'</caption>';
			}

			$th		= $atts['th'];

			$tr_counter = 0;
			foreach($trs as $tr){
				$tr = trim($tr);

				if(empty($tr)){
					continue;
				}

				$tds = explode("\n", $tr);

				if(($th == 1 || $th == 4) && $tr_counter == 0){
					foreach($tds as $td){
						if($td = trim($td)){
							$thead .= "\t\t\t".'<th>'.$td.'</th>'."\n";
						}
					}

					$thead = "\t\t".'<tr>'."\n".$thead."\t\t".'</tr>'."\n";
				}else{
					$tbody .= "\t\t".'<tr>'."\n";

					$td_counter = 0;

					foreach($tds as $td){
						if($td = trim($td)){
							if($th == 2 && $td_counter ==0){
								$tbody .= "\t\t\t".'<th>'.$td.'</th>'."\n";
							}else{
								$tbody .= "\t\t\t".'<td>'.$td.'</td>'."\n";
							}
							$td_counter++;
						}
					}

					$tbody .= "\t\t".'</tr>'."\n";
				}

				$tr_counter++;
			}

			if($th == 1 || $th == 4){ $output .=  "\t".'<thead>'."\n".$thead."\t".'</thead>'."\n"; }
			if($th == 4){ $output .=  "\t".'<tfoot>'."\n".$thead."\t".'</tfoot>'."\n"; }

			$output	.= "\t".'<tbody>'."\n".$tbody."\t".'</tbody>'."\n";

			$table_attrs	= [];

			$table_attrs[]	= 'border="'.esc_attr($atts['border']).'"';
			$table_attrs[]	= 'cellpading="'.esc_attr($atts['cellpading']).'"';
			$table_attrs[]	= 'cellspacing="'.esc_attr($atts['cellspacing']).'"';

			if($atts['width']){
				$table_attrs[]	= 'width="'.esc_attr($atts['width']).'"';
			}

			if($atts['class']){
				$table_attrs[]	= 'class="'.esc_attr($atts['class']).'"';
			}

			$table_attrs	= implode(' ', $table_attrs);

			return "\n".'<table '.$table_attrs.' >'."\n".$output.'</table>'."\n";
		}

		public static function email($atts, $content=''){
			$atts	= shortcode_atts(['mailto'=>false], $atts);

			return antispambot($content, $atts['mailto']);
		}

		public static function code($atts, $content=''){
			$atts	= shortcode_atts(['type'=>'php'], $atts);
			$type	= $atts['type'] == 'html' ? 'markup' : $atts['type'];

			$content	= str_replace("<br />\n", "\n", $content);
			$content	= str_replace("</p>\n", "\n\n", $content);
			$content	= str_replace("\n<p>", "\n", $content);
			$content	= str_replace('&amp;', '&', esc_textarea($content)); // wptexturize 会再次转化 & => &#038;

			$content	= trim($content);

			return $type ? '<pre><code class="language-'.$type.'">'.$content.'</code></pre>' : '<pre>'.$content.'</pre>';
		}

		private static function video_hwstring($atts){
			$atts	= shortcode_atts(['width'=>'510', 'height'=>'498'], $atts);
			return image_hwstring($atts['width'], $atts['height']);
		}

		public static function youku($atts, $content=''){
			if(preg_match('#//v.youku.com/v_show/id_(.*?).html#i',$content, $matches)){
				return '<iframe class="wpjam_video" '.self::video_hwstring($atts).' src="https://player.youku.com/embed/'.esc_attr($matches[1]).'" frameborder=0 allowfullscreen></iframe>';
			}
		}

		public static function qqv($atts, $content=''){
			if(preg_match('#//v.qq.com/(.*)iframe/(player|preview).html\?vid=(.+)#i',$content, $matches)){
				return '<iframe class="wpjam_video" '.self::video_hwstring($atts).' src="https://v.qq.com/'.$matches[1].'iframe/player.html?vid='.esc_attr($matches[3]).'" frameborder=0 allowfullscreen></iframe>';
			}
		}

		public static function bilibili($atts, $content='') {
			if(preg_match('#//www.bilibili.com/video/(.+)#i',$content, $matches)){
				return '<iframe class="wpjam_video" '.self::video_hwstring($atts).' src="https://player.bilibili.com/player.html?bvid='.esc_attr($matches[1]).'" scrolling="no" border="0" frameborder="no" framespacing="0" allowfullscreen="true"> </iframe>';
			}
		}

		public static function tudou($atts, $content=''){
			if(preg_match('#//www.tudou.com/programs/view/(.*?)#i',$content, $matches)){
				return '<iframe class="wpjam_video" '.self::video_hwstring($atts).' src="https://www.tudou.com/programs/view/html5embed.action?code='. esc_attr($matches[1]) .'" frameborder=0 allowfullscreen></iframe>';
			}
		}

		public static function sohutv($atts, $content=''){
			if(preg_match('#//tv.sohu.com/upload/static/share/share_play.html\#(.+)#i',$content, $matches)){
				return '<iframe class="wpjam_video" '.self::video_hwstring($atts).' src="https://tv.sohu.com/upload/static/share/share_play.html#'.esc_attr($matches[1]).'" frameborder=0 allowfullscreen></iframe>';
			}
		}
	}
		
	wp_embed_unregister_handler('tudou');
	wp_embed_unregister_handler('youku');

	add_shortcode('hide',		'__return_empty_string');
	add_shortcode('list',		['WPJAM_Shortcode', 'list']);
	add_shortcode('table',		['WPJAM_Shortcode', 'table']);
	add_shortcode('email',		['WPJAM_Shortcode', 'email']);
	add_shortcode('code',		['WPJAM_Shortcode', 'code']);
	add_shortcode('youku',		['WPJAM_Shortcode', 'youku']);
	add_shortcode('qqv',		['WPJAM_Shortcode', 'qqv']);
	add_shortcode('bilibili',	['WPJAM_Shortcode', 'bilibili']);
	add_shortcode('tudou',		['WPJAM_Shortcode', 'tudou']);
	add_shortcode('sohutv',		['WPJAM_Shortcode', 'sohutv']);

	if(is_admin()){
		wpjam_add_basic_sub_page('wpjam-shortcodes', [
			'menu_title'	=> '短代码',
			'network'		=> false,
			'function'		=> 'list',
			'plural'		=> 'shortcodes',
			'singular'		=> 'shortcode',
			'primary_key'	=> 'tag',
			'model'			=> 'WPJAM_Shortcodes_Admin',
			'page_file'		=> __FILE__,
			'per_page'		=> 300
		]);
	}
}