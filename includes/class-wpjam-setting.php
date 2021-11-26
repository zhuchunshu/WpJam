<?php
trait WPJAM_Setting_Trait{
	private static $instance	= null;

	private $settings		= [];
	private $option_name	= '';
	private $site_default	= false;

	private function init($option_name, $site_default=false){
		$this->option_name	= $option_name;
		$this->site_default	= $site_default;

		if(is_null(get_option($option_name, null))){
			add_option($option_name, []);
		}

		$this->reset_settings();
	}

	public function __get($name){
		if(in_array($name, ['option_name', 'site_default'])){
			return $this->$name;
		}

		return $this->get_setting($name);
	}

	public function __set($name, $value){
		return $this->update_setting($name);
	}

	public function __isset($name){
		return isset($this->settings[$name]);
	}

	public function __unset($name){
		$this->delete_setting($name);
	}

	public function get_settings(){
		return $this->settings;
	}

	public function reset_settings(){
		$value	= wpjam_get_option($this->option_name);

		$this->settings	= is_array($value) ? $value : [];

		if($this->site_default){
			$site_value	= wpjam_get_site_option($this->option_name);
			$site_value	= is_array($site_value) ? $site_value : [];

			$this->settings	+= $site_value;
		}
	}

	public function get_setting($name, $default=null){
		return $this->settings[$name] ?? $default;
	}

	public function update_setting($name, $value){
		$this->settings[$name]	= $value;

		return $this->save();
	}

	public function delete_setting($name){
		$this->settings	= wpjam_array_except($this->settings, $name);

		return $this->save();
	}

	private function save($settings=[]){
		if($settings){
			$this->settings	= array_merge($this->settings, $settings);
		}

		return update_option($this->option_name, $this->settings);
	}

	public static function register_option($args=[]){
		$instance	= self::get_instance();
		$defaults	= [];

		$defaults['site_default']	= $instance->site_default;

		if(method_exists($instance, 'sanitize_callback')){
			$defaults['sanitize_callback']	= [$instance, 'sanitize_callback'];
		}

		if(method_exists($instance, 'get_fields')){
			$defaults['fields']		= [$instance, 'get_fields'];
		}elseif(method_exists($instance, 'get_sections')){
			$defaults['sections']	= [$instance, 'get_sections'];
		}

		return wpjam_register_option($instance->option_name, wp_parse_args($args, $defaults));
	}

	public static function get_instance(){
		if(is_null(self::$instance)){
			self::$instance	= new self();
		}

		return self::$instance;
	}
}

class WPJAM_Option_Setting{
	use WPJAM_Register_Trait;

	public function filter_args(){
		$args	= $this->args;

		if(empty($args['filtered'])){
			if(isset($args['sections'])){
				if(is_callable($args['sections'])){
					$args['sections']	= call_user_func($args['sections'], $this->name);
				}

				if(!is_array($args['sections'])){
					$args['sections']	= [];
				}
			}else{
				if(isset($args['fields'])){
					$args['sections']	= [$this->name	=> [
						'title'		=> $args['title'] ?? '', 
						'fields'	=> $args['fields']
					]];
				}else{
					$args['sections']	= [];
				}
			}

			foreach($args['sections'] as $section_id => &$section){
				if(is_callable($section['fields'])){
					$section['fields']	= call_user_func($section['fields'], $section_id, $this->name);
				}
			}

			$args	= apply_filters('wpjam_option_setting_args', $args, $this->name);
			$args['filtered']	= true;
		}

		return $args;
	}

	public function get_fields(){
		return array_merge(...array_values(wp_list_pluck($this->sections, 'fields')));
	}

	public function value_callback($name, $args){
		return $this->get_value($name, wpjam_array_pull($args, 'is_network_admin'));
	}

	public function get_value($name='', $is_network_admin=false){
		if($this->option_type == 'array'){
			if($is_network_admin){
				$value	= wpjam_get_site_option($this->name);
				$value	= is_array($value) ? $value : [];
			}else{
				$value	= wpjam_get_option($this->name);
				$value	= is_array($value) ? $value : [];

				if($this->site_default){
					$site_value	= wpjam_get_site_option($this->name);
					$site_value	= is_array($site_value) ? $site_value : [];
					$value		+= $site_value;
				}
			}	

			return $name ? ($value[$name] ?? null) : $value;
		}else{
			if($name){
				$callback	= $is_network_admin ? 'get_site_option' : 'get_option';
				$value		= call_user_func($callback, $name, null);

				return is_wp_error($value) ? null : $value;
			}else{
				return null;
			}
		}
	}

	public function register_settings(){
		if($this->capability && $this->capability != 'manage_options'){
			add_filter('option_page_capability_'.$this->option_page, function(){
				return $this->capability;
			});
		}

		$args		= ['sanitize_callback'	=> [$this, 'sanitize_callback']];
		$settings	= [];
		
		// 只需注册字段，add_settings_section 和 add_settings_field 可以在具体设置页面添加	
		if($this->option_type == 'single'){
			foreach($this->sections as $section_id => $section){
				foreach($section['fields'] as $key => $field){
					if($field['type'] == 'fieldset' && wpjam_array_get($field, 'fieldset_type') != 'array'){
						foreach ($field['fields'] as $sub_key => $sub_field) {
							$settings[$sub_key]	= array_merge($args, ['field'=>$sub_field]);

							register_setting($this->option_group, $sub_key, $settings[$sub_key]);
						}

						continue;
					}

					$settings[$key]	= array_merge($args, ['field'=>$field]);

					register_setting($this->option_group, $key, $settings[$key]);
				}
			}
		}else{
			$settings[$this->name]	= array_merge($args, ['type'=>'object']);

			register_setting($this->option_group, $this->name, $settings[$this->name]);
		}

		return $settings;
	}

	public function sanitize_callback($value){
		if($this->option_type == 'array'){
			$value		= wpjam_validate_fields_value($this->get_fields(), $value) ?: [];
			$current	= $this->get_value();

			if(!is_wp_error($value)){
				$value	= array_merge($current, $value);
				$value	= wpjam_array_filter($value, function($item){ return !is_null($item); });

				if($this->sanitize_callback && is_callable($this->sanitize_callback)){
					$value	= call_user_func($this->sanitize_callback, $value, $this->name);
				}
			}

			if(is_wp_error($value)){
				add_settings_error($this->name, $value->get_error_code(), $value->get_error_message());

				return $current;
			}
		}else{
			$option		= str_replace('sanitize_option_', '', current_filter());
			$registered	= get_registered_settings();

			if(!isset($registered[$option])){
				return $value;
			}

			$fields	= [$option=>$registered[$option]['field']];
			$value	= wpjam_validate_fields_value($fields, [$option=>$value]);

			if(is_wp_error($value)){
				add_settings_error($option, $value->get_error_code(), $value->get_error_message());

				return get_option($option);
			}else{
				$value	= $value[$option] ?? null;
			}
		}



		return $value;
	}

	public function ajax_response(){
		$option_page	= wpjam_get_data_parameter('option_page');
		$nonce			= wpjam_get_data_parameter('_wpnonce');

		if($option_page != $this->option_page || !wp_verify_nonce($nonce, $option_page.'-options')){
			wpjam_send_json(['errcode'=>'invalid_nonce',	'errmsg'=>'非法操作']);
		}

		$capability	= $this->capability ?: 'manage_options';

		if(!current_user_can($capability)){
			wpjam_send_json(['errcode'=>'bad_authentication',	'errmsg'=>'无权限']);
		}

		$options	= $this->register_settings();

		if(empty($options)){
			wpjam_send_json(['errcode'=>'invalid_option',	'errmsg'=>'字段未注册']);
		}

		$option_action		= wpjam_get_parameter('option_action', ['method'=>'POST']);
		$is_network_admin	= is_multisite() && is_network_admin();

		foreach($options as $option => $args){
			$option = trim($option);

			if($option_action == 'reset'){
				delete_option($option);
			}else{
				$value	= wpjam_get_data_parameter($option);

				if($this->update_callback && is_callable($this->update_callback)){
					call_user_func($this->update_callback, $option, $value, $is_network_admin);
				}else{
					$callback	= $is_network_admin ? 'update_site_option' : 'update_option';

					if($this->option_type == 'array'){
						$callback	= 'wpjam_'.$callback;
					}else{
						$value		= is_wp_error($value) ? null : $value;
					}

					call_user_func($callback, $option, $value);
				}
			}
		}

		if($settings_errors = get_settings_errors()){
			$errmsg = '';

			foreach ($settings_errors as $key => $details) {
				if (in_array($details['type'], ['updated', 'success', 'info'])) {
					continue;
				}

				$errmsg	.= $details['message'].'&emsp;';
			}

			wpjam_send_json(['errcode'=>'update_failed', 'errmsg'=>$errmsg]);
		}else{
			$response	= $this->response ?? ($this->ajax ? $option_action : 'redirect');
			$errmsg		= $option_action == 'reset' ? '设置已重置。' : '设置已保存。';

			wpjam_send_json(['type'=>$response,	'errmsg'=>$errmsg]);
		}
	}

	public function page_load(){
		if(isset($_POST['response_type'])) {
			$message	= $_POST['response_type'] == 'reset' ? '设置已重置。' : '设置已保存。';

			wpjam_admin_add_error($message);
		}

		$this->register_settings();
	}

	// 部分代码拷贝自 do_settings_sections 和 do_settings_fields 函数
	public function page($page_setting, $in_tab=false){
		$sections		= $this->sections;
		$section_count	= count($sections);

		if(!$in_tab && $section_count > 1){
			echo '<div class="tabs">';

			echo '<h2 class="nav-tab-wrapper wp-clearfix"><ul>';

			foreach($sections as $section_id => $section){
				$attr	= WPJAM_Field::parse_wrap_attr($section);

				echo '<li id="tab_title_'.$section_id.'" '.$attr.'><a class="nav-tab" href="#tab_'.$section_id.'">'.$section['title'].'</a></li>';
			}

			echo '</ul></h2>';
		}

		$attr	= ' id="wpjam_option"';

		echo '<form action="options.php" method="POST"'.$attr.'>';

		settings_errors();

		settings_fields($this->option_group);

		foreach($sections as $section_id => $section){
			echo '<div id="tab_'.$section_id.'"'.'>';

			if($section_count > 1 && !empty($section['title'])){
				if(!$in_tab){
					echo '<h2>'.$section['title'].'</h2>';
				}else{
					echo '<h3>'.$section['title'].'</h3>';
				}
			}

			if(!empty($section['callback'])) {
				call_user_func($section['callback'], $section);
			}

			if(!empty($section['summary'])) {
				echo wpautop($section['summary']);
			}

			if(!$section['fields']) {
				echo '</div>';
				continue;
			}

			$args	= [
				'fields_type'		=> 'table',
				'option_name'		=> $this->name,
				'is_network_admin'	=> is_multisite() && is_network_admin(),
				'value_callback'	=> [$this, 'value_callback']
			];

			if($this->option_type == 'array'){
				$args['name']	= $this->name;
			}

			wpjam_fields($section['fields'], $args);

			echo '</div>';
		}

		if($section_count > 1){
			echo '</div>';
		}

		echo '<p class="submit">';

		echo get_submit_button('', 'primary', 'option_submit', false, ['data-action'=>'save']);

		if(!empty($this->reset)){
			echo '&emsp;'.get_submit_button('重置选项', 'secondary', 'option_reset', false, ['data-action'=>'reset']);
		}

		echo '</p>';

		echo '</form>';
	}
}

class WPJAM_Setting{
	public static function get_option($name, $blog_id=0){
		$value	= (is_multisite() && $blog_id) ? get_blog_option($blog_id, $name) : get_option($name);

		return self::sanitize_value($value);
	}

	public static function update_option($name, $value, $blog_id=0){
		$value	= self::sanitize_value($value);

		return (is_multisite() && $blog_id) ? update_blog_option($blog_id, $name, $value) : update_option($name, $value);
	}

	public static function get_site_option($name){
		return is_multisite() ? self::sanitize_value(get_site_option($name, [])) : [];
	}

	public static function update_site_option($name, $value){
		return is_multisite() ? update_site_option($name, self::sanitize_value($value)) : true;
	}

	private static function sanitize_value($value){
		return (is_wp_error($value) || empty($value)) ? [] : $value;
	}

	public static function get_setting($option_name, $setting_name, $blog_id=0){
		$option_value	= is_string($option_name) ? self::get_option($option_name, $blog_id) : $option_name;

		if($option_value && !is_wp_error($option_value) && is_array($option_value) && isset($option_value[$setting_name])){
			$value	= $option_value[$setting_name];

			if(is_wp_error($value)){
				return null;
			}elseif($value && is_string($value)){
				return  str_replace("\r\n", "\n", trim($value));
			}else{
				return $value;
			}
		}else{
			return null;
		}
	}

	public static function update_setting($option_name, $setting_name, $setting_value, $blog_id=0){
		$option_value	= self::get_option($option_name, $blog_id);

		$option_value[$setting_name]	= $setting_value;

		return self::update_option($option_name, $option_value, $blog_id);
	}

	public static function delete_setting($option_name, $setting_name, $blog_id=0){
		if($option_value = self::get_option($option_name, $blog_id)){
			$option_value	= wpjam_array_except($option_value, $setting_name);
		}

		return self::update_option($option_name, $option_value, $blog_id);
	}

	public static function get_option_settings(){	// 兼容代码
		return WPJAM_Option_Setting::get_registereds([], 'settings');
	}
}