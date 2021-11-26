<?php
if(is_admin() && did_action('wpjam_plugin_page_load') && $GLOBALS['plugin_page'] = 'wpjam-crons'){
	class WPJAM_Crons_Admin{
		public static function get_primary_key(){
			return 'cron_id';
		}

		public static function get($id){
			list($timestamp, $hook, $key)	= explode('--', $id);

			$wp_crons = _get_cron_array();

			if(isset($wp_crons[$timestamp][$hook][$key])){
				$data	= $wp_crons[$timestamp][$hook][$key];

				$data['hook']		= $hook;
				$data['timestamp']	= $timestamp;
				$data['time']		= get_date_from_gmt(date('Y-m-d H:i:s', $timestamp));
				$data['cron_id']	= $id;
				$data['interval']	= $data['interval'] ?? 0;

				return $data;
			}else{
				return new WP_Error('cron_not_exist', '该定时作业不存在');
			}
		}

		public static function insert($data){
			if(!has_filter($data['hook'])){
				return new WP_Error('invalid_hook', '非法 hook');
			}

			$timestamp	= strtotime(get_gmt_from_date($data['time']));

			if($data['interval']){
				wp_schedule_event($timestamp, $data['interval'], $data['hook'], $data['_args']);
			}else{
				wp_schedule_single_event($timestamp, $data['hook'], $data['_args']);
			}

			return true;
		}

		public static function do($id){
			$data = self::get($id);

			if(is_wp_error($data)){
				return $data;
			}

			$result	= do_action_ref_array($data['hook'], $data['args']);

			if(is_wp_error($result)){
				return $result;
			}else{
				return true;
			}
		}

		public static function delete($id){
			$data = self::get($id);

			if(is_wp_error($data)){
				return $data;
			}

			return wp_unschedule_event($data['timestamp'], $data['hook'], $data['args']);
		}

		public static function query_items($limit, $offset){
			$items	= [];

			foreach (_get_cron_array() as $timestamp => $wp_cron) {
				foreach ($wp_cron as $hook => $dings) {
					foreach($dings as $key=>$data) {
						if(!has_filter($hook)){
							wp_unschedule_event($timestamp, $hook, $data['args']);	// 系统不存在的定时作业，自动清理
							continue;
						}

						$schedule	= $schedules[$data['schedule']] ?? $data['interval']??'';
						// $args	= $data['args'] ? '('.implode(',', $data['args']).')' : '';

						$items[] = [
							'cron_id'	=> $timestamp.'--'.$hook.'--'.$key,
							'time'		=> get_date_from_gmt( date('Y-m-d H:i:s', $timestamp) ),
							// 'hook'		=> $hook.$args,
							'hook'		=> $hook,
							'interval'	=> $data['interval'] ?? 0
						];
					}
				}
			}

			return ['items'=>$items, 'total'=>count($items)];
		}

		public static function get_actions(){
			return [
				'add'		=> ['title'=>'新建',		'response'=>'list'],
				'do'		=> ['title'=>'立即执行',	'direct'=>true,	'confirm'=>true,	'bulk'=>2,		'response'=>'list'],
				'delete'	=> ['title'=>'删除',		'direct'=>true,	'confirm'=>true,	'bulk'=>true,	'response'=>'list']
			];
		}

		public static function get_fields($action_key='', $id=0){
			$schedule_options	= [0=>'只执行一次']+wp_list_pluck(wp_get_schedules(), 'display', 'interval');

			return [
				'hook'		=> ['title'=>'Hook',	'type'=>'text',		'show_admin_column'=>true],
				// '_args'		=> ['title'=>'参数',		'type'=>'mu-text',	'show_admin_column'=>true],
				'time'		=> ['title'=>'运行时间',	'type'=>'text',		'show_admin_column'=>true,	'value'=>current_time('mysql')],
				'interval'	=> ['title'=>'频率',		'type'=>'select',	'show_admin_column'=>true,	'options'=>$schedule_options],
			];
		}
	}
}else{
	class WPJAM_Cron{
		use WPJAM_Register_Trait;

		public function schedule(){
			if(is_null($this->callback)){
				$this->callback	= [$this, 'callback'];
			}

			if(is_callable($this->callback)){
				add_action($this->name, $this->callback);

				if(!wpjam_is_scheduled_event($this->name)){
					$args	= $this->args['args'] ?? [];

					if($this->recurrence){
						wp_schedule_event($this->time, $this->recurrence, $this->name, $args);
					}else{
						wp_schedule_single_event($this->time, $this->name, $args);
					}
				}
			}

			return $this;
		}

		public function callback(){
			if(get_site_transient($this->name.'_lock')){
				return;
			}

			set_site_transient($this->name.'_lock', 1, 5);
			
			if($jobs = $this->get_jobs()){
				$callbacks	= array_column($jobs, 'callback');
				$total		= count($callbacks);
				$index		= get_transient($this->name.'_index') ?: 0;
				$index		= $index >= $total ? 0 : $index;
				$callback	= $callbacks[$index];
				
				set_transient($this->name.'_index', $index+1, DAY_IN_SECONDS);

				$this->increment();

				if(is_callable($callback)){
					call_user_func($callback);
				}else{
					trigger_error('invalid_job_callback'.var_export($callback, true));
				}
			}
		}

		public function get_jobs($jobs=null){
			if(is_null($jobs)){
				$jobs	= $this->jobs;

				if($jobs && is_callable($jobs)){
					$jobs	= call_user_func($jobs);
				}
			}

			$jobs	= $jobs ?: [];

			if(!$jobs || !$this->weight){
				return array_values($jobs);
			}

			$queue	= [];
			$next	= [];

			foreach($jobs as $job){
				$job['weight']	= $job['weight'] ?? 1;
 
				if($job['weight']){
					$queue[]	= $job;

					if($job['weight'] > 1){
						$job['weight'] --;
						$next[]	= $job;
					}
				}
			}

			if($next){
				$queue	= array_merge($queue, $this->get_jobs($next)); 
			}

			return $queue;
		}

		public function get_counter($increment=false){
			$today		= date('Y-m-d', current_time('timestamp'));
			$counter	= get_transient($this->name.'_counter:'.$today) ?: 0;

			if($increment){
				$counter ++;
				set_transient($this->name.'_counter:'.$today, $counter, DAY_IN_SECONDS);
			}

			return $counter;
		}

		public function increment(){
			return $this->get_counter(true);
		}
	}

	class WPJAM_Cron_Hook{
		public static function filter_schedules($schedules){
			return array_merge($schedules, [
				'five_minutes'		=> ['interval'=>300,	'display'=>'每5分钟一次'],
				'fifteen_minutes'	=> ['interval'=>900,	'display'=>'每15分钟一次'],
			]);
		}

		public static function filter_pre_option($pre){
			return get_transient('wpjam_crons') ?: $pre;
		}

		public static function filter_pre_update_option($value, $old_value){
			if(wp_doing_cron()){
				set_transient('wpjam_crons', $value, HOUR_IN_SECONDS*6);

				return $old_value;
			}else{
				delete_transient('wpjam_crons');

				return $value;
			}
		}
	}

	class WPJAM_Job{
		use WPJAM_Register_Trait;

		public static function register_cron(){
			return wpjam_register_cron('wpjam_scheduled', [
				'recurrence'	=> wp_using_ext_object_cache() ? 'five_minutes' : 'fifteen_minutes',
				'jobs'			=> [self::class, 'get_jobs'],
				'weight'		=> true
			]);
		}

		public static function get_jobs($raw=false){
			$jobs	= self::get_registereds([], 'args');

			return $raw ? $jobs : array_filter($jobs, function($job){
				if($job['day'] == -1){
					return true;
				}else{
					$day	= (current_time('H') > 2 && current_time('H') < 6) ? 0 : 1;
					return $job['day']	== $day;
				}
			});
		}
	}

	function wpjam_register_cron($hook, $args=[]){
		if(is_callable($hook)){
			wpjam_register_job($hook, $args);
		}else{
			if($cron = WPJAM_Cron::get($hook)){
				return $cron;
			}else{
				$cron = WPJAM_Cron::register($hook, wp_parse_args($args, ['recurrence'=>'', 'time'=>time(),	'args'=>[]]));

				return $cron->schedule();
			}	
		}
	}

	function wpjam_register_job($name, $args=[]){
		if(is_numeric($args)){
			$args	= ['weight'=>$args];
		}elseif(!is_array($args)){
			$args	= [];
		}

		if(empty($args['callback']) || !is_callable($args['callback'])){
			if(is_callable($name)){
				$args['callback']	= $name;

				if(is_object($name)){
					$name	= get_class($name);
				}elseif(is_array($name)){
					$name	= implode(':', $name);
				}
			}else{
				return null;
			}
		}

		return WPJAM_Job::register($name, wp_parse_args($args, ['weight'=>1, 'day'=>-1]));
	}

	function wpjam_is_scheduled_event($hook) {	// 不用判断参数
		foreach(_get_cron_array() as $timestamp => $cron){
			if(isset($cron[$hook])){
				return true;
			}
		}

		return false;
	}

	add_filter('cron_schedules',	['WPJAM_Cron_Hook', 'filter_schedules']);

	if(wp_using_ext_object_cache()){
		add_filter('pre_option_cron',			['WPJAM_Cron_Hook', 'filter_pre_option']);
		add_filter('pre_update_option_cron',	['WPJAM_Cron_Hook', 'filter_pre_update_option'], 10, 2);
	}

	add_action('init',	['WPJAM_Job', 'register_cron']);
}