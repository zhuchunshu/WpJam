<?php
class WPJAM_Upgrader{
	private static $core	= null; 
	private static $plugins	= null; 
	private static $themes	= null; 

	public static function register($type, $name, $args=[]){
		if($type == 'core'){
			self::$core[$name]		= $args;
		}elseif($type == 'plugin'){
			self::$plugins[$name]	= $args;
		}elseif($type == 'theme'){
			self::$themes[$name]	= $args;
		}
	}

	public static function filter_site_transient_update_core($transient){
		if(self::$core && isset($transient->updates)){
			foreach ($transient->updates as $update){
				if(isset(self::$core[$update->locale])){
					$update->download		= self::$core[$update->locale]['download'];
					$update->packages->full	= self::$core[$update->locale]['package_full'];
				}
			}
		}

		return $transient;
	}

	public static function filter_site_transient_update_plugins($transient){
		if(self::$plugins && isset($transient->response)){
			foreach($transient->response as $plugin => $update){
				if(isset(self::$plugins[$plugin])){
					$update->package	= self::$plugins[$plugin];
				}
			}
		}

		return $transient;
	}

	public static function filter_site_transient_update_themes($transient){
		if(self::$themes){
			$theme	= get_template();

			$upgrader_url	= self::$themes[$theme] ?? '';

			if($upgrader_url){
				if(empty($transient->checked[$theme])){
					return $transient;
				}
				
				$remote	= get_transient('wpjam_theme_upgrade_'.$theme);

				if(false == $remote){
					$remote = wpjam_remote_request($upgrader_url);
			 
					if(!is_wp_error($remote)){
						set_transient( 'wpjam_theme_upgrade_'.$theme, $remote, HOUR_IN_SECONDS*12 );
					}
				}

				if($remote && !is_wp_error($remote)){
					if(version_compare( $transient->checked[$theme], $remote['new_version'], '<' )){
						$transient->response[$theme]	= $remote;
					}
				}
			}
		}
		
		return $transient;
	}
}

function wpjam_register_theme_upgrader($upgrader_url){
	WPJAM_Upgrader::register('theme', get_template(), $upgrader_url);
}

add_filter('site_transient_update_core',	['WPJAM_Upgrader', 'filter_site_transient_update_core']);
add_filter('site_transient_update_plugins',	['WPJAM_Upgrader', 'filter_site_transient_update_plugins']);
add_filter('site_transient_update_themes',	['WPJAM_Upgrader', 'filter_site_transient_update_themes']);