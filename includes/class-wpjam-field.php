<?php
class WPJAM_Field{
	private static $del_item_button	= ' <a href="javascript:;" class="button wpjam-del-item">删除</a> ';
	private	static $del_item_icon	= ' <a href="javascript:;" class="del-item-icon dashicons dashicons-no-alt wpjam-del-item"></a>';
	private	static $del_img_icon	= ' <a href="javascript:;" class="del-item-icon dashicons dashicons-no-alt wpjam-del-img"></a>';
	private	static $sortable_icon	= ' <span class="dashicons dashicons-menu"></span>';
	private	static $dismiss_icon	= ' <span class="dashicons dashicons-dismiss"></span>';

	private static $tmpls	= [];

	private static function readonly($field){
		return !empty($field['readonly']) || !empty($field['disabled']);
	}

	private static function show_admin_column_only($field){
		return isset($field['show_admin_column']) && $field['show_admin_column'] === 'only';
	}

	private static function get_max_items($field){
		if(!empty($field['total']) && !isset($field['max_items'])){
			return (int)$field['total'];
		}else{
			return (int)($field['max_items'] ?? 0);
		}
	}

	private static function compare($a, $compare, $b){
		if(is_array($a)){
			if($compare == '='){
				return in_array($b, $a);
			}else if($compare == '!='){
				return !in_array($b, $a);
			}else if($compare == 'IN'){
				return array_intersect($a, $b) == $b;
			}else if($compare == 'NOT IN'){
				return array_intersect($a, $b) == [];
			}else{
				return false;
			}
		}else{
			if($compare == '='){
				return $a == $b;
			}else if($compare == '!='){
				return $a != $b;
			}else if($compare == '>'){
				return $a > $b;
			}else if($compare == '>='){
				return $a >= $b;
			}else if($compare == '<'){
				return $a < $b;
			}else if($compare == '<='){
				return $a <= $b;
			}else if($compare == 'IN'){
				return in_array($a, $b);
			}else if($compare == 'NOT IN'){
				return !in_array($a, $b);
			}else if($compare == 'BETWEEN'){
				return $a > $b[0] && $a < $b[1];
			}else if($compare == 'NOT BETWEEN'){
				return $a < $b[0] && $a > $b[1];
			}else{
				return false;
			}
		}
	}

	public static function validate($field, $value, $validate=true){
		$field		= self::parse($field);
		$required	= $validate ? !empty($field['required']) : false;

		if(is_null($value) && $required){
			return new WP_Error('value_required', $field['title'].'的值不能为空');
		}

		if(!empty($field['validate_callback']) && is_callable($field['validate_callback'])){
			$result	= call_user_func($field['validate_callback'], $value);

			if($result === false){
				return $validate ? new WP_Error('invalid_value', $field['title'].'的值无效') : null;
			}elseif(is_wp_error($result)){
				return $validate ? $result : null;
			}
		}

		$type	= $field['type'];

		if($type == 'checkbox'){
			if($field['options']){
				$value	= is_array($value) ? $value : [];
				$value	= $value ? array_values(array_intersect(array_map('strval', array_keys($field['options'])), $value)) : [];

				if(empty($value)){
					$value	= null;
				}
			}else{
				if($validate){
					$value	= (int)$value;
				}
			}
		}elseif(in_array($type, ['mu-image', 'mu-file', 'mu-text', 'mu-img', 'mu-fields'])){
			if($value){
				if(!is_array($value)){
					$value	= wpjam_json_decode($value);
				}else{
					$value	= wpjam_array_filter($value, function($item){ return !empty($item) || is_numeric($item); });
				}
			}

			if(empty($value) || is_wp_error($value)){
				$value	= null;
			}else{
				$value	= array_values($value);

				if(($max_items = self::get_max_items($field)) && count($value) > $max_items){
					$value	= array_slice($value, 0, $max_items);
				}
			}
		}else{
			if(empty($value) && !is_numeric($value) && $required){
				$value	= null;
			}else{
				if($type == 'radio'){
					if(!in_array($value, array_map('strval', array_keys($field['options'])))){
						$value	= null;
					}
				}elseif($type == 'select'){
					$allows	= [];

					foreach($field['options'] as $option_value => $option_title){
						if(!empty($option_title['optgroup'])){
							foreach($option_title['options'] as $sub_option_value => $sub_option_title){
								$allows[]	= (string)$sub_option_value;
							}
						}else{
							$allows[]	= (string)$option_value;
						}
					}

					if(!in_array($value, $allows)){
						$value	= null;
					}
				}elseif(in_array($type, ['number', 'range'])){
					if(!is_null($value)){
						if(!empty($field['step']) && ($field['step'] == 'any' || strpos($field['step'], '.'))){
							$value	= (float)$value;
						}else{
							$value	= (int)$value;
						}

						if(!empty($field['min']) && is_numeric($field['min'])){
							if($value < $field['min']){
								$value	= $field['min'];
							}
						}

						if(!empty($field['max']) && is_numeric($field['max'])){
							if($value > $field['max']){
								$value	= $field['max'];
							}
						}
					}
				}else{
					if(!is_null($value)){
						if($type == 'textarea'){
							$value	= str_replace("\r\n", "\n", $value);
						}
					}
				}
			}
		}

		if(is_null($value) && $required){
			return new WP_Error('value_required', $field['title'].'的值为空或无效');
		}

		if(!empty($field['sanitize_callback']) && is_callable($field['sanitize_callback'])){
			$value	= call_user_func($field['sanitize_callback'], $value);
		}

		return $value;
	}

	private static function callback($field, $args=[]){
		if(empty($args['is_add'])){
			$field['value']	= self::get_value($field, $args);
		}

		if(!empty($args['name'])){
			$field['name']	= $args['name'].self::generate_sub_name($field['name']);
		}

		if(!empty($args['show_if_keys']) && in_array($field['key'], $args['show_if_keys'])){
			$field['show_if_key']	= true;
		}

		return self::render($field);
	}

	public  static function value_callback($callback, $name, $args){
		if(isset($args['id'])){
			return call_user_func($callback, $name, $args['id']);
		}else{
			return call_user_func($callback, $name, $args);
		}
	}

	public  static function get_value($field, $args=[]){
		$_key		= is_admin() ? 'value' : 'defaule';
		$default	= $field[$_key] ?? null;
		$name		= $field['name'] ?? $field['key'];

		if(preg_match('/\[([^\]]*)\]/', $name)){
			$name_arr	= wp_parse_args($name);
			$name		= current(array_keys($name_arr));
		}else{
			$name_arr	= [];
		}

		if(isset($field['value_callback'])){
			if(!is_callable($field['value_callback'])){
				wp_die($field['key'].'的 value_callback「'.$field['value_callback'].'」无效');
			}

			$value	= self::value_callback($field['value_callback'], $name, $args);
		}else{
			if(in_array($field['type'], ['view', 'br','hr']) && !is_null($default)){
				return $default;
			}

			if(!empty($args['data']) && isset($args['data'][$name])){
				$value	= $args['data'][$name];
			}elseif(!empty($args['value_callback'])){
				$value	= self::value_callback($args['value_callback'], $name, $args);
			}else{
				$value	= null;
			}
		}

		if($name_arr){
			$name_arr	= current(array_values($name_arr));

			do{
				$sub_name	= current(array_keys($name_arr));
				$name_arr	= current(array_values($name_arr));
				$value		= $value[$sub_name] ?? null;
			} while($name_arr && $value);
		}

		if(is_null($value)){
			return $default;
		}

		return $value;
	}

	private static function parse($field){
		$parsed	= [];

		foreach($field as $attr_key => $attr_value){
			if(is_numeric($attr_key)){
				$attr_key	= $attr_value = strtolower(trim($attr_value));
			}else{
				$attr_key	= strtolower(trim($attr_key));
			}

			$parsed[$attr_key]	= $attr_value;
		}

		if(empty($parsed['type'])){
			$parsed['type']	= 'text';
		}

		if(empty($parsed['options'])){
			$parsed['options']	= [];
		}elseif(!is_array($parsed['options'])){
			$parsed['options']	= wp_parse_args($parsed['options']);
		}

		if(isset($parsed['show_if']) && !is_array($parsed['show_if'])){
			$parsed['show_if']	= wp_parse_args($parsed['show_if']);
		}

		return $parsed;
	}

	public  static function render($field){
		$field	= self::parse($field);

		$field['key']	= $key = $field['key'] ?? '';
		$field['name']	= $field['name'] ?? $key;
		$field['id']	= $field['id'] ?? $key;
		$field['sep']	= $field['sep'] ?? '&emsp;';

		if(is_numeric($key)){
			trigger_error('Field 的 key「'.$key.'」'.'为纯数字');
			return;
		}

		if(!isset($field['value'])){
			$field['value']	= $field['type'] == 'radio' ? null : '';
		}

		$type	= $field['type'];
		$value	= $field['value'];
		$name	= $field['name'];
		$id		= $field['id'];

		if(!isset($field['class'])){
			if($type == 'textarea'){
				$field['class']	= ['large-text'];
			}elseif($type == 'mu-text'){
				// do nothing
			}elseif(!in_array($type, ['checkbox', 'radio', 'select', 'color', 'date', 'time', 'datetime-local', 'number'])){
				$field['class']	= ['regular-text'];
			}else{
				$field['class']	= [];
			}
		}elseif($field['class']){
			if(!is_array($field['class'])){
				$field['class']	= explode(' ', $field['class']);
			}
		}else{
			$field['class']	= [];
		}

		if(in_array($type, ['mu-image','mu-file','mu-text','mu-img','mu-fields'])){
			$value			= ($value && is_array($value)) ? wpjam_array_filter($value, function($item){ return !empty($item) || is_numeric($item); }) : [];
			$max_items		= self::get_max_items($field);
			$max_reached	= $max_items && count($value) >= $max_items;
			$value			= $max_reached ? array_slice($value, 0, $max_items) : $value; 
		}else{
			if(isset($field['show_if_key'])){
				$field['class'][]	= 'show-if-key';
			}
		}

		if(!empty($field['description'])){
			if($type == 'checkbox' || $type == 'mu-text'){
				$description	= ' <span class="description">'.$field['description'].'</span>';
			}elseif(empty($field['class']) || !array_intersect(['large-text','regular-text'], $field['class'])){
				$description	= ' <span class="description">'.$field['description'].'</span>';
			}else{
				$description	= '<br /><span class="description">'.$field['description'].'</span>';
			}

			$field['description']	= $description;
		}else{
			$description	= $field['description'] = '';
		}

		$html		= '';
		$item_htmls	= [];

		if($type == 'view' || $type == 'br'){
			if($field['options']){
				$value	= $value ?: 0;
				$html	= $field['options'][$value] ?? $value;
			}else{
				$html	= $value;
			}
		}elseif($type == 'hr'){
			$html	= '<hr />';
		}elseif($type == 'hidden'){
			$html	= self::render_input($field);
		}elseif($type == 'range'){
			$html	= self::render_input($field).' <span>'.$value.'</span>';
		}elseif($type == 'color'){
			$field['class'][]	= 'color';
			$field['type']		= 'text';

			$html	= self::render_input($field);
		}elseif($type == 'checkbox'){
			$field['class'][]	= 'show-if-key';

			if($field['options']){
				$field['class'][]	= 'mu-checkbox';
				$field['class'][]	= 'checkbox-'.esc_attr($field['key']);
				$field['name']		= $name.'[]';

				foreach($field['options'] as $option_value => $option_title){ 
					$checked	= ($value && is_array($value) && in_array($option_value, $value)) ? 'checked' : '';
					$item_field	= array_merge($field, ['id'=>$id.'_'.$option_value, 'value'=>$option_value, 'checked'=>$checked, 'description'=>$option_title]);

					$item_htmls[]	= self::render_input($item_field);
				}

				$html	= '<div id="'.$id.'_options">'.implode($field['sep'], $item_htmls).'</div>'.$description;
			}else{
				$field['checked']	= $value == 1 ? 'checked' : ''; 
				$field['value']		= 1;

				$html	= self::render_input($field);
			}
		}elseif($type == 'radio'){
			$field['class'][]	= 'show-if-key';

			if($field['options']){
				$value	= $value ?? current(array_keys($field['options']));

				foreach($field['options'] as $option_value => $option_title){
					$checked	= $option_value == $value ? 'checked' : '';
					$item_field	= array_merge($field, ['id'=>$id.'_'.$option_value, 'value'=>$option_value, 'checked'=>$checked, 'description'=>'']);

					$data_attr		= '';
					$option_title	= self::parse_option_title($option_title, $data_attr);
					$item_htmls[]	= '<label '.$data_attr.' id="label_'.$item_field['id'].'" for="'.$item_field['id'].'">'.self::render_input($item_field).$option_title.'</label>';
				}

				$html	= '<div id="'.$id.'_options">'.implode($field['sep'], $item_htmls).'</div>'.$description;
			}
		}elseif($type == 'select'){
			$field['class'][]	= 'show-if-key';

			if($field['options']){
				foreach($field['options'] as $option_value => $option_title){
					$data_attr		= '';

					if(is_array($option_title) && !empty($option_title['optgroup'])){
						$sub_options	= wpjam_array_pull($option_title, 'options');
						$sub_item_htmls	= [];

						foreach($sub_options as $sub_option_value => $sub_option_title){
							$sub_data_attr		= '';
							$sub_option_title	= self::parse_option_title($sub_option_title, $sub_data_attr);
							$sub_item_htmls[]	= '<option '.$sub_data_attr.' value="'.esc_attr($sub_option_value).'" '.selected($sub_option_value, $value, false).'>'.$sub_option_title.'</option>';
						}

						$option_title	= self::parse_option_title($option_title, $data_attr);
						$item_htmls[]	= '<optgroup '.$data_attr.' label="'.esc_attr($option_title).'" >'.implode('', $sub_item_htmls).'</optgroup>';
					}else{
						$option_title	= self::parse_option_title($option_title, $data_attr);
						$item_htmls[]	= '<option '.$data_attr.' value="'.esc_attr($option_value).'" '.selected($option_value, $value, false).'>'.$option_title.'</option>';
					}
				}

				$field['options']	= implode('', $item_htmls);
			}else{
				$field['options']	= '';
			}

			$html	= self::render_input($field);
		}elseif($type == 'file' || $type == 'image'){
			if(current_user_can('upload_files')){
				$field['class'][]	= 'wpjam-file-input';

				$item_type	= $type == 'image' ? 'image' : '';
				$item_text	= $type == 'image' ? '图片' : '文件';
				$item_field	= array_merge($field, ['type'=>'url', 'description'=>'']);

				$html	= self::render_input($item_field).' <a class="wpjam-file button" data-item_type="'.$item_type.'">选择'.$item_text.'</a>'.$description;
			}
		}elseif($type == 'img'){
			if(current_user_can('upload_files')){
				$item_type	= $field['item_type'] ?? '';
				$size		= $field['size'] ?? '400x0';

				$img_style	= '';

				if(isset($field['size'])){
					$size	= wpjam_parse_size($field['size']);

					if($size['width'] > 600 || $size['height'] > 600){
						if($size['width'] > $size['height']){
							$size['height']	= (int)(($size['height'] / $size['width']) * 600);
							$size['width']	= 600;
						}else{
							$size['width']	= (int)(($size['width'] / $size['height']) * 600);
							$size['height']	= 600;
						}
					}

					if($size['width']){
						$img_style	.= ' width:'.(int)($size['width']/2).'px;';
					}

					if($size['height']){
						$img_style	.= ' height:'.(int)($size['height']/2).'px;';
					}

					$thumb_args	= wpjam_get_thumbnail('',$size);
				}else{
					$thumb_args	= wpjam_get_thumbnail('',400);
				}

				$img_style	= $img_style ?: 'max-width:200px;';

				$div_class	= 'wpjam-img button add_media';
				$html	= '<span class="wp-media-buttons-icon"></span> 添加图片</button>';

				if(self::readonly($field)){
					$div_class	= '';
					$html	= '';
				}

				if(!empty($value)){
					$img_url	= $item_type == 'url' ? $value : wp_get_attachment_url($value);

					if($img_url){
						$img_url	= wpjam_get_thumbnail($img_url, $size);
						$html	= '<img style="'.$img_style.'" src="'.$img_url.'" alt="" />';

						if(!self::readonly($field)){
							$div_class	= 'wpjam-img';
							$html	.= self::$del_img_icon;
						}
					}
				}

				$item_field	= array_merge($field, ['type'=>'hidden', 'description'=>'']);
				$html	= '<div data-item_type="'.$item_type.'" data-img_style="'.$img_style.'" data-thumb_args="'.$thumb_args.'" class="'.$div_class.'">'.$html.'</div>';
				$html = '<div class="wp-media-buttons wpjam-media-buttons">'.self::render_input($item_field).$html.'</div>'.$description;
			}
		}elseif($type == 'textarea'){
			$field['rows']	= $field['rows'] ?? 6;
			$field['cols']	= $field['cols'] ?? 50;

			$html = self::render_input($field);
		}elseif($type == 'editor'){
			$field['id']= 'editor_'.$field['id'];
			$settings	= wpjam_array_pull($field, 'settings') ?: [];
			$settings	= wp_parse_args($settings, [
				'tinymce'		=>[
					'wpautop'	=> true,
					'plugins'	=> 'charmap colorpicker compat3x directionality hr image lists media paste tabfocus textcolor wordpress wpautoresize wpdialogs wpeditimage wpemoji wpgallery wplink wptextpattern wpview',
					'toolbar1'	=> 'bold italic underline strikethrough | bullist numlist | blockquote hr | alignleft aligncenter alignright alignjustify | link unlink | wp_adv',
					'toolbar2'	=> 'formatselect forecolor backcolor | pastetext removeformat charmap | outdent indent | undo redo | wp_help'
				],
				'quicktags'		=> true,
				'mediaButtons'	=> true
			]);

			if(wp_doing_ajax()){
				$field['type']	= 'textarea';
				$field['class']	= array_merge($field['class'], ['wpjam-editor', 'large-text']);
				$field['rows']	= $field['rows'] ?? 12;
				$field['cols']	= $field['cols'] ?? 50;

				$field['data-settings']	= wpjam_json_encode($settings);

				$html	= self::render_input($field);
			}else{
				ob_start();

				wp_editor($value, $field['id'], $settings);

				$editor	= ob_get_clean();

				$style	= isset($field['style']) ? ' style="'.$field['style'].'"' : '';
				$html 	= '<div'.$style.'>'.$editor.'</div>';

				$html	.= $description;
			}	
		}elseif($type == 'mu-img'){
			if(current_user_can('upload_files')){
				$item_type	= $field['item_type'] ?? '';
				$item_field	= array_merge($field, ['type'=>'hidden', 'name'=>$name.'[]', 'description'=>'']);

				$i	= 0;

				foreach($value as $img){
					$i++;

					$img_url	= $item_type == 'url' ? $img : wp_get_attachment_url($img);
					$img_url	= wpjam_get_thumbnail($img_url, 200, 200);

					if(!self::readonly($field)){
						$item_field		= array_merge($item_field, ['id'=>$id.'_'.$i, 'value'=>esc_attr($img)]);
						$item_htmls[]	= '<img width="100" src="'.$img_url.'" alt="">'.self::render_input($item_field).self::$del_item_icon;
					}else{
						$item_htmls[]	= '<img width="100" src="'.$img_url.'" alt="">';
					}
				}

				$html	= $item_htmls ? '<div class="mu-item mu-img">'.implode('</div> <div class="mu-item mu-img">', $item_htmls).'</div>' : '';

				if(!self::readonly($field)){
					$thumb_args	= wpjam_get_thumbnail('',[200,200]);

					$html	.= '<div title="按住Ctrl点击鼠标左键可以选择多张图片" class="wpjam-mu-img dashicons dashicons-plus-alt2" data-i='.($i+1).' data-id="'.$id.'" data-item_type="'.$item_type.'" data-thumb_args="'.$thumb_args.'" data-name="'.$name.'[]"></div>';
				}

				$html	= '<div class="mu-imgs" data-max_items="'.$max_items.'">'.$html.'</div>'.$description;
			}
		}elseif($type == 'mu-file' || $type == 'mu-image'){
			if(current_user_can('upload_files')){
				$item_type	= $type == 'mu-image' ? 'image' : '';
				$item_text	= $type == 'mu-image' ? '图片' : '文件';
				$item_field	= array_merge($field, ['type'=>'url', 'name'=>$name.'[]', 'description'=>'']);

				$i	= 0;

				foreach($value as $file){
					$i++;

					$item_field		= array_merge($item_field, ['id'=>$id.'_'.$i, 'value'=>esc_attr($file)]);

					if($max_items && $i == $max_items){
						break;
					}

					$item_htmls[]	= self::render_input($item_field).self::$del_item_button.self::$sortable_icon;
				}

				if(!$max_reached){
					$item_field		= array_merge($item_field, ['id'=>$id.'_'.($i+1), 'value'=>'']);
				}

				$item_htmls[]	= self::render_input($item_field).' <a class="wpjam-mu-file button" data-item_type="'.$item_type.'" data-i='.$i.' data-id="'.$id.'" data-name="'.$name.'[]" title="按住Ctrl点击鼠标左键可以选择多个'.$item_text.'">选择'.$item_text.'[多选]'.'</a>';

				$html	= '<div class="mu-item">'.implode('</div> <div class="mu-item">', $item_htmls).'</div>';
				$html	= '<div class="mu-files" data-max_items="'.$max_items.'">'.$html.'</div>'.$description;
			}
		}elseif($type == 'mu-text'){
			$item_type	= $field['item_type'] ?? 'text';
			$item_field	= array_merge($field, ['type'=>$item_type, 'name'=>$name.'[]', 'description'=>'']);

			$i	= 0;

			foreach($value as $item){
				$i++;

				$item_field		= array_merge($item_field, ['id'=>$id.'_'.$i, 'value'=>esc_attr($item)]);

				if($max_items && $i == $max_items){
					break;
				}

				$item_htmls[]	= self::render($item_field).self::$del_item_button.self::$sortable_icon;
			}

			if(!$max_reached){
				$item_field	= array_merge($item_field, ['id'=>$id.'_'.($i+1), 'value'=>'']);
			}

			$item_htmls[]	= self::render($item_field).' <a class="wpjam-mu-text button" data-i='.($i+1).' data-id="'.$id.'">添加选项</a>';

			$html	= '<div class="mu-item">'.implode('</div> <div class="mu-item">', $item_htmls).'</div>';
			$html 	= '<div class="mu-texts" data-max_items="'.$max_items.'">'.$html.'</div>'.$description;
		}elseif($type == 'mu-fields'){
			if(!empty($field['fields'])){
				$i	= 0;

				foreach($value as $item){
					$i++;

					$item_html	= self::render_mu_fields($name, $field['fields'], $i, $item);

					if($max_items && $i == $max_items){
						break;
					}

					$item_htmls[]	= $item_html.self::$del_item_button.self::$sortable_icon;
				}

				if(!$max_reached){
					$item_html	= self::render_mu_fields($name, $field['fields'], ($i+1));
				}

				$data_attr		= ' data-tmpl-id="wpjam-'.md5($name).'"';

				$item_htmls[]	= $item_html.' <a class="wpjam-mu-fields button" data-i='.($i+1).$data_attr.'>添加选项</a>';

				$html	= '<div class="mu-item">'.implode('</div> <div class="mu-item">', $item_htmls).'</div>';
				$html	= '<div class="mu-fields" id="mu_fields_'.$id.'" data-max_items="'.$max_items.'">'.$html.'</div>';

				self::$tmpls[md5($name)]	= '<div class="mu-item">'.self::render_mu_fields($name, $field['fields'], '{{ data.i }}').' <a class="wpjam-mu-fields button" data-i="{{ data.i }}" '.$data_attr.'>添加选项</a>'.'</div>';
			}
		}else{
			if(!empty($field['data_type'])){
				$field['class'][]		= 'wpjam-autocomplete';
				$field['data-data_type']= $field['data_type'];

				$query_title	= '';
				$query_args		= wpjam_array_pull($field, 'query_args') ?: [];

				if($query_args && !is_array($query_args)){
					$query_args	= wp_parse_args($query_args);
				}

				if($field['data_type'] == 'post_type'){
					if(!empty($field['post_type'])){
						$query_args['post_type']	= $field['post_type'];
					}

					if($value && is_numeric($value) && ($_post = get_post($value))){
						$query_title	= $_post->post_title ?: $_post->ID;
					}
				}elseif($field['data_type'] == 'taxonomy'){
					if(!empty($field['taxonomy'])){
						$query_args['taxonomy']	= $field['taxonomy'];
					}

					if($value && is_numeric($value) && ($_term = get_term($value))){
						$query_title	= $_term->name ?: $_term->term_id;
					}
				}elseif($field['data_type'] == 'model'){
					if(!empty($field['model'])){
						$query_args['model']	= $field['model'];
					}

					$label_key	= $query_args['label_key'] = $query_args['label_key'] ?? 'title'; 
					$id_key		= $query_args['id_key'] = $query_args['id_key'] ?? 'id';

					$model	= $query_args['model'] ?? '';

					if(empty($model) || !class_exists($model)){
						wp_die($key.' model 未定义');
					}

					if($value && ($item = $model::get($value))){
						$query_title	= $item[$label_key] ?: $item[$id_key];
					}
				}

				$field['data-query_args']	= wpjam_json_encode($query_args);

				$query_title	= $query_title ? '<span class="wpjam-query-title">'.self::$dismiss_icon.$query_title.'</span>' : '';
				$html		= self::render_input($field).$query_title;
			}else{
				$html		= self::render_input($field);

				if(!empty($field['list']) && $field['options']){
					$html	.= '<datalist id="'.$field['list'].'">';

					foreach($field['options'] as $option_value => $option_title){
						$html	.= '<option label="'.esc_attr($option_title).'" value="'.esc_attr($option_value).'" />';
					}

					$html	.= '</datalist>';
				}
			}
		}

		return apply_filters('wpjam_field_html', $html, $field);
	}

	private static function render_input($field){
		$field['data-key']	= $field['key'];
		$field['class']		= $field['class'] ? implode(' ', $field['class']) : '';

		$keys	= ['type','key','title','value','default','description','options','fields','size','show_admin_column','sortable_column','taxonomies','taxonomy','settings','data_type','post_type','item_type','total','max_items','sep','wrap_class','show_if','show_if_key','sanitize_callback','validate_callback','column_callback','value_callback'];

		$attr	= [];

		foreach($field as $attr_key => $attr_value){
			if(!in_array($attr_key, $keys)){
				if(is_object($attr_value) || is_array($attr_value)){
					trigger_error($attr_key.' '.var_export($attr_value, true).var_export($field, true));
				}elseif(is_int($attr_value) || $attr_value){
					$attr[]	= $attr_key.'="'.esc_attr($attr_value).'"';
				}
			}
		}

		$attr	= implode(' ', $attr);

		if($field['type'] == 'select'){
			$html	= '<select '.$attr.'>'.$field['options'].'</select>' .$field['description'];
		}elseif($field['type'] == 'textarea'){
			$html	= '<textarea '.$attr.'>'.esc_textarea($field['value']).'</textarea>'.$field['description'];
		}else{
			$html	= '<input type="'.esc_attr($field['type']).'" value="'.esc_attr($field['value']).'" '.$attr.' />';

			if($field['type'] != 'hidden' && $field['description']){
				$html	= '<label for="'.esc_attr($field['id']).'">'.$html.$field['description'].'</label>';
			}
		}

		return $html;
	}

	private static function render_mu_fields($sup, $fields, $i, $value=[]){
		$show_if_keys	= self::get_show_if_keys($fields);

		$html	= '';

		foreach($fields as $key=>$field){
			if($field['type'] == 'fieldset'){
				wp_die('mu-fields 不允许内嵌 fieldset');
			}elseif($field['type'] == 'mu-fields'){
				wp_die('mu-fields 不允许内嵌 mu-fields');
			}

			$id		= $field['id'] ?? $key;
			$name	= $field['name'] ?? $key;

			if(preg_match('/\[([^\]]*)\]/', $name)){
				wp_die('mu-fields 类型里面子字段不允许[]模式');
			}

			$field['name']	= $sup.'['.$i.']'.'['.$name.']';

			if($value && isset($value[$name])){
				$field['value']	= $value[$name];
			}

			if($show_if_keys && in_array($key, $show_if_keys)){
				$field['show_if_key']	= true;
			}

			if(isset($field['show_if'])){
				$field['show_if']['key']	.= '_'.$i;
			}

			$field['key']		= $key.'_'.$i;
			$field['id']		= $id.'_'.$i;
			$field['data-i']	= $i;

			if($field['type'] == 'hidden'){
				$html	.= self::render($field);
			}else{
				$title	= $field['title'] ?? ''; 
				$title	= $title ? '<label class="sub-field-label" for="'.$id.'_'.$i.'">'.$title.'</label>' : '';

				$html	.= '<div '.self::parse_wrap_attr($field, ['sub-field']).'>'.$title.'<div class="sub-field-detail">'.self::render($field).'</div></div>';
			}
		}

		return $html;
	}

	private static function parse_option_title($option_title, &$data_attr){
		$attr	= $class	= [];

		if(is_array($option_title)){
			foreach($option_title as $k => $v){
				if($k == 'show_if'){
					if($show_if = self::parse_show_if($v)){
						$class[]	= 'show-if-'.$show_if['key'];
						$attr[]		= 'data-show_if=\''.wpjam_json_encode($show_if).'\'';
					}
				}elseif($k == 'class'){
					$class	= array_merge($class, explode(' ', $v));
				}elseif($k != 'title' && !is_array($v)){
					$attr[]	= 'data-'.$k.'="'.esc_attr($v).'"';
				}
			}

			$option_title	= $option_title['title'];
		}

		if($class){
			$attr[]	= 'class="'.implode(' ', $class).'"';
		}

		$data_attr	= $attr ? implode(' ', $attr) : '';

		return $option_title;
	}

	public  static function parse_wrap_attr($field, $class=[]){
		$attr	= [];

		if(!empty($field['wrap_class'])){
			$class[]	= $field['wrap_class'];
		}

		if(isset($field['show_if'])){
			if($show_if = self::parse_show_if($field['show_if'])){
				$class[]	= 'show-if-'.$show_if['key'];
				$attr[]		= 'data-show_if=\''.wpjam_json_encode($show_if).'\'';
			}
		}

		$attr[]	= $class ? 'class="'.implode(' ', $class).'"' : '';

		return $attr ? implode(' ', $attr) : '';
	}

	private static function parse_show_if($show_if){
		if(empty($show_if['key'])){
			return '';
		}

		if(empty($show_if['compare'])){
			$show_if['compare']	= '=';
		}else{
			$show_if['compare']	= strtoupper($show_if['compare']);

			if($show_if['compare'] == 'ITEM'){
				return '';
			}
		}

		if(in_array($show_if['compare'], ['IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN'])){
			if(!is_array($show_if['value'])){
				$show_if['value']	= preg_split('/[,\s]+/', $show_if['value']);
			}

			if(count($show_if['value']) == 1){
				$show_if['value']	= current($show_if['value']);
				$show_if['compare']	= in_array($show_if['compare'], ['IN', 'BETWEEN']) ? '=' : '!=';
			}
		}else{
			$show_if['value']	= trim($show_if['value']);
		}

		return $show_if;
	}

	private static function generate_sub_name($name){
		if(preg_match('/\[([^\]]*)\]/', $name)){
			$name_arr	= wp_parse_args($name);
			$name		= '';

			do{
				$name		.='['.current(array_keys($name_arr)).']';
				$name_arr	= current(array_values($name_arr));
			} while ($name_arr);

			return $name;
		}else{
			return '['.$name.']';
		}
	}

	private static function get_show_if_keys($fields){
		$show_if_keys	= [];

		foreach($fields as $key => $field){
			if(isset($field['show_if']) && !empty($field['show_if']['key'])){
				$show_if_keys[]	= $field['show_if']['key'];
			}

			if($field['type'] == 'fieldset' && !empty($field['fields'])){
				$show_if_keys	= array_merge($show_if_keys, self::get_show_if_keys($field['fields']));
			}
		}

		return array_unique($show_if_keys);
	}

	public  static function print_media_templates(){
		self::$tmpls	+= [
			'img'		=> '<img style="{{ data.img_style }}" src="{{ data.img_url }}{{ data.thumb_args }}" alt="" />'.self::$del_img_icon,
			'mu-img'	=> '<div class="mu-item mu-img"><img width="100" src="{{ data.img_url }}{{ data.thumb_args }}"><input type="hidden" name="{{ data.name }}" id="{{ data.id }}_{{ data.i }}" value="{{ data.img_value }}" />'.self::$del_item_icon.'</div>',
			'mu-file'	=> '<div class="mu-item"><input type="url" name="{{ data.name }}" id="{{ data.id }}_{{ data.i }}" class="regular-text" value="{{ data.img_url }}" /> '.self::$del_item_button.self::$sortable_icon.'</div>'
		];

		echo self::get_tmpls();
		echo '<div id="tb_modal" style="display:none; background: #f1f1f1;"></div>';
	}

	public  static function get_tmpls(){
		$output = '';

		foreach(self::$tmpls as $tmpl_id => $tmpl){
			$output	.= "\n".'<script type="text/html" id="tmpl-wpjam-'.$tmpl_id.'">'."\n";
			$output	.=  $tmpl."\n";
			$output	.=  '</script>'."\n";
		}

		self::$tmpls	= [];

		return $output;
	}

	public  static function get_data($fields, $values=null, $args=[]){
		$get_show_if	= $args['get_show_if'] ?? false;
		$show_if_values	= $args['show_if_values'] ?? [];
		$field_validate	= $get_show_if ? false : ($args['validate'] ?? true);

		$data	= [];

		foreach($fields as $key => &$field){
			if(self::readonly($field) || self::show_admin_column_only($field) || in_array($field['type'], ['view', 'br','hr'])){
				continue;
			}

			$validate	= $field_validate;

			if($validate){
				if(isset($field['show_if']) && !empty($field['show_if']['key'])){
					$show_if	= self::parse_show_if($field['show_if']);

					$show_if_key	= $show_if['key']; 
					$compare		= $show_if['compare'];
					$compare_value	= $show_if['value'];

					if(!self::compare($show_if_values[$show_if_key], $compare, $compare_value)){
						$validate	= false;
					}
				}
			}

			$name	= $field['name'] ?? $key;

			if($field['type'] == 'fieldset'){
				if(!empty($field['fields'])){
					if(!empty($field['fieldset_type']) && $field['fieldset_type'] == 'array'){
						$sub_fields	= [];

						foreach($field['fields'] as $sub_key => $sub_field){
							$sub_name			= $sub_field['name'] ?? $sub_key;
							$sub_field['name']	= $name.self::generate_sub_name($sub_name);

							if($get_show_if){	// show_if 判断是基于 key 并且 fieldset array 的情况下的 key 是 ${key}_{$sub_key}
								$sub_field['key']	= $sub_key	= $key.'_'.$sub_key;
							}

							$sub_fields[$sub_key]	= $sub_field;
						}
					}else{
						$sub_fields	= $field['fields'];
					}

					$value	= self::get_data($sub_fields, $values, array_merge($args, ['validate'=>$validate]));

					if(is_wp_error($value)){
						return $value;
					}else{
						if(!empty($field['fieldset_type']) && $field['fieldset_type'] == 'array'){
							$value	= array_filter($value, function($item){ return !is_null($item); });
						}
					}

					$data	= wpjam_array_merge($data, $value);
				}
			}else{
				if(preg_match('/\[([^\]]*)\]/', $name)){
					$name_arr	= wp_parse_args($name);
					$name		= current(array_keys($name_arr));

					if(isset($values)){
						$value	= $values[$name] ?? null;
					}else{
						$value	= wpjam_get_parameter($name, ['method'=>'POST']);
					}

					$name_arr		= current(array_values($name_arr));
					$sub_name_arr	= [];

					do{
						$sub_name	= current(array_keys($name_arr));
						$name_arr	= current(array_values($name_arr));

						if(isset($value) && isset($value[$sub_name])){
							$value	= $value[$sub_name];
						}else{
							$value	= null;
						}

						array_unshift($sub_name_arr, $sub_name);
					}while($name_arr && $value);

					if($get_show_if){
						$data[$key]	= self::validate($field, $value, false);
					}else{
						$value = self::validate($field, $value, $validate);

						if(is_wp_error($value)){
							return $value;
						}

						foreach($sub_name_arr as $sub_name){
							$value	= [$sub_name => $value];
						}

						$data	= wpjam_array_merge($data, [$name=>$value]);
					}
				}else{
					if(isset($values)){
						$value	= $values[$name] ?? null;
					}else{
						$value	= wpjam_get_parameter($name, ['method'=>'POST']);
					}

					if($get_show_if){
						$data[$key]	= self::validate($field, $value, false);
					}else{
						$value	= self::validate($field, $value, $validate);

						if(is_wp_error($value)){
							return $value;
						}

						$data[$name]	= $value;
					}
				}
			}
		}

		return $data;
	}

	public  static function fields_validate($fields, $values=null){
		$show_if_keys	= self::get_show_if_keys($fields);
		$show_if_values	= $show_if_keys ? self::get_data($fields, $values, ['get_show_if'=>true]) : [];

		return self::get_data($fields, $values, ['show_if_values'=>$show_if_values]);
	}

	public  static function fields_callback($fields, $args=[]){
		$output			= '';
		$fields_type	= $args['fields_type'] ?? 'table';

		$args['show_if_keys']	= self::get_show_if_keys($fields);

		foreach($fields as $key => $field){
			if(self::show_admin_column_only($field)){
				continue;
			}

			$field['key']	= $key;
			$field['name']	= $field['name'] ?? $key;

			$id		= $field['id'] = $field['id'] ?? $key;
			$title	= $field['title'] = $field['title'] ?? '';

			if($field['type'] == 'fieldset'){
				$html	= '<legend class="screen-reader-text"><span>'.$title.'</span></legend>';

				if(!empty($field['fields'])){
					$fieldset_type	= $field['fieldset_type'] ?? 'single';

					foreach($field['fields'] as $sub_key => &$sub_field){
						if($sub_field['type'] == 'fieldset'){
							wp_die('fieldset 不允许内嵌 fieldset');
						}

						$sub_field['name']	= $sub_field['name'] ?? $sub_key;

						if($fieldset_type == 'array'){
							$sub_key	= $key.'_'.$sub_key;

							$sub_field['name']	= $field['name'].self::generate_sub_name($sub_field['name']);
						}

						$sub_id	= $sub_field['id'] ?? $sub_key;

						$sub_field['key']	= $sub_key;
						$sub_field['id']	= $sub_id;

						$sub_html	= self::callback($sub_field, $args);

						if($sub_field['type'] == 'hidden'){
							$html	.= $sub_html;
						}else{
							$wrap_attr	= self::parse_wrap_attr($sub_field, ['sub-field']);
							$sub_title	= $sub_field['title'] ?? '';
							$sub_title	= $sub_title ? '<label class="sub-field-label" for="'.$sub_id.'">'.$sub_title.'</label>' : '';

							$html	.= '<div '.$wrap_attr.' id="div_'.$sub_id.'">'.$sub_title.'<div class="sub-field-detail">'.$sub_html.'</div>'.'</div>';
						}
					}

					unset($sub_field);
				}
			}else{
				$html	= self::callback($field, $args);

				if($field['type'] == 'hidden'){
					$output	.= $html;
					continue;
				}

				if($title){
					$title	= '<label for="'.$key.'">'.$title.'</label>';
				}
			}

			$wrap_class	= [];

			if(!empty($args['wrap_class'])){
				$wrap_class[]	= $args['wrap_class'];
			}

			$wrap_attr	= self::parse_wrap_attr($field, $wrap_class);

			if($fields_type == 'div'){
				$output	.= '<div '.$wrap_attr.' id="div_'.$id.'">'.$title.$html.'</div>';
			}elseif($fields_type == 'list' || $fields_type == 'li'){
				$output	.= '<li '.$wrap_attr.' id="li_'.$id.'">'.$title.$html.'</li>';
			}elseif($fields_type == 'tr' || $fields_type == 'table'){
				$html	= $title ? '<th scope="row">'.$title.'</th><td>'.$html.'</td>' : '<td colspan="2">'.$html.'</td>';
				$output	.= '<tr '.$wrap_attr.' valign="top" '.'id="tr_'.$id.'">'.$html.'</tr>';
			}else{
				$output	.= $title.$html;
			}
		}

		if($fields_type == 'list'){
			$output	= '<ul>'.$output.'</ul>';
		}elseif($fields_type == 'table'){
			$output	= '<table class="form-table" cellspacing="0"><tbody>'.$output.'</tbody></table>';
		}

		if(wp_doing_ajax()){ 
			$output	.= self::get_tmpls();
		}

		if(!isset($args['echo']) || $args['echo']){
			echo $output;
		}else{
			return $output;
		}
	}

	public  static function form_validate($fields, $nonce_action='', $capability='manage_options'){
		check_admin_referer($nonce_action);

		if(!current_user_can($capability)){
			ob_clean();
			wp_die('无权限');
		}

		return self::fields_validate($fields);
	}

	public  static function form_callback($fields, $form_url, $nonce_action='', $submit_text=''){
		echo '<form method="post" action="'.$form_url.'" enctype="multipart/form-data" id="form">';

		echo self::fields_callback($fields);

		wp_nonce_field($nonce_action);
		wp_original_referer_field(true, 'previous');

		if($submit_text!==false){ 
			submit_button($submit_text);
		}

		echo '</form>';
	}
}