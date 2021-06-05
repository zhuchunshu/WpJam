<?php
class WPJAM_Util{
	public static function parse_shortcode_attr($str,  $tagnames=null){
		$pattern = get_shortcode_regex([$tagnames]);

		if(preg_match("/$pattern/", $str, $m)){
			return shortcode_parse_atts( $m[3] );
		}else{
			return [];
		}
	}

	public static function human_time_diff($from,  $to=0) {
		$to		= ($to)?:time();
		$day	= date('Y-m-d',$from);
		$today	= date('Y-m-d');

		$secs	= $to - $from;	//距离的秒数
		$days	= $secs / DAY_IN_SECONDS;

		$from += get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ;

		if($secs > 0){
			if((date('Y')-date('Y',$from))>0 && $days>3){//跨年且超过3天
				return date('Y-m-d',$from);
			}else{

				if($days<1){//今天
					if($secs<60){
						return $secs.'秒前';
					}elseif($secs<3600){
						return floor($secs/60)."分钟前";
					}else {
						return floor($secs/3600)."小时前";
					}
				}else if($days<2){	//昨天
					$hour=date('g',$from);
					return "昨天".$hour.'点';
				}elseif($days<3){	//前天
					$hour=date('g',$from);
					return "前天".$hour.'点';
				}else{	//三天前
					return date('n月j号',$from);
				}
			}
		}else{
			if((date('Y')-date('Y',$from))<0 && $days<-3){//跨年且超过3天
				return date('Y-m-d',$from);
			}else{

				if($days>-1){//今天
					if($secs>-60){
						return absint($secs).'秒后';
					}elseif($secs>-3600){
						return floor(absint($secs)/60)."分钟前";
					}else {
						return floor(absint($secs)/3600)."小时前";
					}
				}else if($days>-2){	//昨天
					$hour=date('g',$from);
					return "明天".$hour.'点';
				}elseif($days>-3){	//前天
					$hour=date('g',$from);
					return "后天".$hour.'点';
				}else{	//三天前
					return date('n月j号',$from);
				}
			}
		}
	}

	public static function get_current_page_url(){
		return set_url_scheme('http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
	}

	public static function unicode_decode($str){
		return preg_replace_callback('/\\\\u([0-9a-f]{4})/i', function($matches){
			return mb_convert_encoding(pack("H*", $matches[1]), 'UTF-8', 'UCS-2BE');
		}, $str);
	}

	public static function zh_urlencode($url){
		return preg_replace_callback('/[\x{4e00}-\x{9fa5}]+/u', function($matches){ return urlencode($matches[0]); }, $url);
	}

	public static function get_video_mp4($id_or_url){
		if(filter_var($id_or_url, FILTER_VALIDATE_URL)){ 
			if(preg_match('#http://www.miaopai.com/show/(.*?).htm#i',$id_or_url, $matches)){
				return 'http://gslb.miaopai.com/stream/'.esc_attr($matches[1]).'.mp4';
			}elseif(preg_match('#https://v.qq.com/x/page/(.*?).html#i',$id_or_url, $matches)){
				return self::get_qqv_mp4($matches[1]);
			}elseif(preg_match('#https://v.qq.com/x/cover/.*/(.*?).html#i',$id_or_url, $matches)){
				return self::get_qqv_mp4($matches[1]);
			}else{
				return self::zh_urlencode($id_or_url);
			}
		}else{
			return self::get_qqv_mp4($id_or_url);
		}
	}

	public static function get_qqv_mp4($vid){
		if(strlen($vid) > 20){
			return new WP_Error('invalid_qqv_vid', '非法的腾讯视频 ID');
		}

		$mp4 = wp_cache_get($vid, 'qqv_mp4');
		if($mp4 === false){
			$response	= wpjam_remote_request('http://vv.video.qq.com/getinfo?otype=json&platform=11001&vid='.$vid, ['timeout'=>4,	'need_json_decode'	=>false]);

			if(is_wp_error($response)){
				return $response;
			}

			$response	= trim(substr($response, strpos($response, '{')),';');
			$response	= wpjam_json_decode($response);

			if(is_wp_error($response)){
				return $response;
			}

			if(empty($response['vl'])){
				return new WP_Error('illegal_qqv', '该腾讯视频不存在或者为收费视频！');
			}

			$u		= $response['vl']['vi'][0];
			$p0		= $u['ul']['ui'][0]['url'];
			$p1		= $u['fn'];
			$p2		= $u['fvkey'];

			$mp4	= $p0.$p1.'?vkey='.$p2;

			wp_cache_set($vid, $mp4, 'qqv_mp4', HOUR_IN_SECONDS*6);
		}

		return $mp4;
	}

	public static function get_qqv_id($id_or_url){
		if(filter_var($id_or_url, FILTER_VALIDATE_URL)){ 
			if(preg_match('#https://v.qq.com/x/page/(.*?).html#i',$id_or_url, $matches)){
				return $matches[1];
			}elseif(preg_match('#https://v.qq.com/x/cover/.*/(.*?).html#i',$id_or_url, $matches)){
				return $matches[1];
			}else{
				return '';
			}
		}else{
			return $id_or_url;
		}
	}

	// 移除除了 line feeds 和 carriage returns 所有控制字符
	public static function strip_control_characters($text){
		return preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F]/u', '', $text);
		// return preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x80-\x9F]/u', '', $str);
	}

	// 去掉非 utf8mb4 字符
	public static function strip_invalid_text($str, $charset='utf8mb4'){
		$regex	= '/
			(
				(?: [\x00-\x7F]                  # single-byte sequences   0xxxxxxx
				|   [\xC2-\xDF][\x80-\xBF]       # double-byte sequences   110xxxxx 10xxxxxx';

		if($charset === 'utf8mb3' || $charset === 'utf8mb4'){
			$regex	.= '
			|   \xE0[\xA0-\xBF][\x80-\xBF]   # triple-byte sequences   1110xxxx 10xxxxxx * 2
				|   [\xE1-\xEC][\x80-\xBF]{2}
				|   \xED[\x80-\x9F][\x80-\xBF]
				|   [\xEE-\xEF][\x80-\xBF]{2}';
		}

		if($charset === 'utf8mb4'){
			$regex	.= '
				|    \xF0[\x90-\xBF][\x80-\xBF]{2} # four-byte sequences   11110xxx 10xxxxxx * 3
				|    [\xF1-\xF3][\x80-\xBF]{3}
				|    \xF4[\x80-\x8F][\x80-\xBF]{2}';
		}

		$regex		.= '
			){1,40}                  # ...one or more times
			)
			| .                      # anything else
			/x';

		return preg_replace($regex, '$1', $str);
	}

	public static function strip_4_byte_chars($chars){
		return preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $chars);
		// return preg_replace('/[\x{10000}-\x{10FFFF}]/u', "\xEF\xBF\xBD", $chars);
	}

	//获取纯文本
	public static function get_plain_text($text){

		$text = wp_strip_all_tags($text);

		$text = str_replace('"', '', $text); 
		$text = str_replace('\'', '', $text);
		// replace newlines on mac / windows?
		$text = str_replace("\r\n", ' ', $text);
		// maybe linux uses this alone
		$text = str_replace("\n", ' ', $text);
		$text = str_replace("  ", ' ', $text);

		return trim($text);
	}

	// 获取第一段
	public static function get_first_p($text){
		if($text){
			$text = explode("\n", trim(strip_tags($text))); 
			$text = trim($text['0']); 
		}
		return $text;
	}

	public static function mb_strimwidth($text, $start=0, $length=40, $trimmarker='...', $encoding='utf-8'){
		return mb_strimwidth(self::get_plain_text($text), $start, $length, $trimmarker, $encoding);
	}

	public static function blacklist_check($text, $name='内容'){
		if(empty($text)){
			return false;
		}

		$pre	= apply_filters('wpjam_pre_blacklist_check', null, $text, $name);

		if(!is_null($pre)){
			return $pre;
		}

		$moderation_keys	= trim(get_option('moderation_keys'));
		$disallowed_keys	= trim(get_option('disallowed_keys'));

		$words = explode("\n", $moderation_keys ."\n".$disallowed_keys);

		foreach ((array)$words as $word){
			$word = trim($word);

			// Skip empty lines
			if ( empty($word) ) {
				continue;
			}

			// Do some escaping magic so that '#' chars in the
			// spam words don't break things:
			$word	= preg_quote($word, '#');
			if ( preg_match("#$word#i", $text) ) {
				return true;
			}
		}

		return false;
	}

	public static function download_image($image_url, $name='', $media=false){
		$tmp_file	= download_url($image_url);

		if(is_wp_error($tmp_file)){
			return $tmp_file;
		}

		if(empty($name)){
			$type	= wp_get_image_mime($tmp_file);
			$name	= md5($image_url).'.'.(explode('/', $type)[1]);
		}

		$file_array	= ['name'=>$name,	'tmp_name'=>$tmp_file];

		if($media){
			$id		= media_handle_sideload($file_array, 0);

			if(is_wp_error($id)){
				@unlink($tmp_file);
			}

			return $id;
		}else{
			$file	= wp_handle_sideload($file_array, ['test_form'=>false]);

			if(isset($file['error'])){
				@unlink($tmp_file);
				return new WP_Error('upload_error', $file['error']);
			}

			return $file;
		}
	}
}

class WPJAM_Array{
	public static function push(&$array, $data=null, $key=false){
		$data	= (array)$data;

		$offset	= $key === false ? false : array_search($key, array_keys($array));
		$offset	= $offset ? $offset : false;

		if($offset){
			$array = array_merge(
				array_slice($array, 0, $offset), 
				$data, 
				array_slice($array, $offset)
			);
		}else{	// 没指定 $key 或者找不到，就直接加到末尾
			$array = array_merge($array, $data);
		}
	}

	public static function filter($array, $callback, $mode=0){
		$return	= [];

		foreach($array as $key=>$value){
			if(is_array($value)){
				$value	= self::filter($value, $callback, $mode);
			}

			if($mode == ARRAY_FILTER_USE_KEY){
				$result	= call_user_func($callback, $key);
			}elseif($mode == ARRAY_FILTER_USE_BOTH){
				$result	= call_user_func($callback, $value, $key);
			}else{
				$result	= call_user_func($callback, $value);
			}

			if($result){
				$return[$key]	= $value;	
			}
		}

		return $return;
	}

	public static function first($array, $callback=null){
		if(empty($array)){
			return null;
		}

		if($callback && is_callable($callback)){
			foreach($array as $key => $value){
				if(call_user_func($callback, $value, $key)){
					return $value;
				}
			}
		}else{
			return current($array);
		}
	}

	public static function pull(&$array, $key){
		if(isset($array[$key])){
			$value	= $array[$key];

			unset($array[$key]);
			
			return $value;
		}else{
			return null;
		}
	}

	public static function except($array, $keys){
		if(is_string($keys)){
			$keys	= [$keys];
		}

		foreach($keys as $key){
			unset($array[$key]);
		}

		return $array;
	}

	public static function merge($arr1, $arr2){
		foreach($arr2 as $key => &$value){
			if(is_array($value) && isset($arr1[$key]) && is_array($arr1[$key])){
				$arr1[$key]	= self::merge($arr1[$key], $value);
			}else{
				$arr1[$key]	= $value;
			}
		}

		return $arr1;
	}
}

class WPJAM_List_Util{
	public static function sort($list, $orderby='order', $order='DESC', $preserve_keys=true){
		$index	= 0;
		$scores	= [];

		foreach($list as $name => $item){
			$value	= is_object($item) ? ($item->$orderby ?? 10) : ($item[$orderby] ?? 10);
			$index 	= $index+1;

			$scores[$name]	= [$orderby=>$value, 'index'=>$index];
		}

		$scores	= wp_list_sort($scores, [$orderby=>$order, 'index'=>'ASC'], '', $preserve_keys);

		return wp_array_slice_assoc($list, array_keys($scores));
	}
}

class WPJAM_Var{
	public $data	= [];

	public static $instance	= null;

	private function __construct(){
		$this->data	= self::parse_user_agent();
	}

	public function __get($name){
		return $this->data[$name] ?? null;
	}

	public static function get_instance(){
		if(is_null(self::$instance)){
			self::$instance	= new self();
		}

		return self::$instance;
	}

	public static function get_ip(){
		return $_SERVER['REMOTE_ADDR'] ??'';
	}

	public static function parse_ip($ip=''){
		$ip	= $ip ?: self::get_ip();

		if($ip == 'unknown'){
			return false;
		}

		$ipdata	= IP::find($ip);

		return [
			'ip'		=> $ip,
			'country'	=> $ipdata['0'] ?? '',
			'region'	=> $ipdata['1'] ?? '',
			'city'		=> $ipdata['2'] ?? '',
			'isp'		=> '',
		];
	}

	public static function parse_user_agent($user_agent='', $referer=''){
		$user_agent	= $user_agent ?: ($_SERVER['HTTP_USER_AGENT'] ?? '');
		$user_agent	= $user_agent.' ';	// 为了特殊情况好匹配
		$referer	= $referer ?: $_SERVER['HTTP_REFERER'] ?? '';

		$os = $device =  $app = $browser = '';
		$os_version = $browser_version = $app_version = 0;

		if(strpos($user_agent, 'iPhone') !== false){
			$device	= 'iPhone';
			$os 	= 'iOS';
		}elseif(strpos($user_agent, 'iPad') !== false){
			$device	= 'iPad';
			$os 	= 'iOS';
		}elseif(strpos($user_agent, 'iPod') !== false){
			$device	= 'iPod';
			$os 	= 'iOS';
		}elseif(strpos($user_agent, 'Android') !== false){
			$os		= 'Android';

			if(preg_match('/Android ([0-9\.]{1,}?); (.*?) Build\/(.*?)[\)\s;]{1}/i', $user_agent, $matches)){
				if(!empty($matches[1]) && !empty($matches[2])){
					$os_version	= trim($matches[1]);

					$device		= $matches[2];

					if(strpos($device,';')!==false){
						$device	= substr($device, strpos($device,';')+1, strlen($device)-strpos($device,';'));
					}

					$device		= trim($device);
					// $build	= trim($matches[3]);
				}
			}
		}elseif(stripos($user_agent, 'Windows NT')){
			$os		= 'Windows';
		}elseif(stripos($user_agent, 'Macintosh')){
			$os		= 'Macintosh';
		}elseif(stripos($user_agent, 'Windows Phone')){
			$os		= 'Windows Phone';
		}elseif(stripos($user_agent, 'BlackBerry') || stripos($user_agent, 'BB10')){
			$os		= 'BlackBerry';
		}elseif(stripos($user_agent, 'Symbian')){
			$os		= 'Symbian';
		}else{
			$os		= 'unknown';
		}

		if($os == 'iOS'){
			if(preg_match('/OS (.*?) like Mac OS X[\)]{1}/i', $user_agent, $matches)){
				$os_version	= (float)(trim(str_replace('_', '.', $matches[1])));
			}
		}

		if(strpos($user_agent, 'MicroMessenger') !== false){
			if(strpos($referer, 'https://servicewechat.com') !== false){
				$app	= 'weapp';
			}else{
				$app	= 'weixin';
			}

			if(preg_match('/MicroMessenger\/(.*?)\s/', $user_agent, $matches)){
				$app_version = $matches[1];
			}

			if(preg_match('/NetType\/(.*?)\s/', $user_agent, $matches)){
				$net_type = $matches[1];
			}
		}elseif(strpos($user_agent, 'ToutiaoMicroApp') !== false || strpos($referer, 'https://tmaservice.developer.toutiao.com') !== false){
			$app	= 'bytedance';
		}

		global $is_lynx, $is_gecko, $is_opera, $is_safari, $is_chrome, $is_IE, $is_edge;

		if($is_safari){
			$browser	= 'safrai';
			if(preg_match('/Version\/(.*?)\s/i', $user_agent, $matches)){
				$browser_version	= (float)(trim($matches[1]));
			}
		}elseif($is_chrome){
			$browser	= 'chrome';
			if(preg_match('/Chrome\/(.*?)\s/i', $user_agent, $matches)){
				$browser_version	= (float)(trim($matches[1]));
			}
		}elseif(stripos($user_agent, 'Firefox') !== false){
			$browser	= 'firefox';
			if(preg_match('/Firefox\/(.*?)\s/i', $user_agent, $matches)){
				$browser_version	= (float)(trim($matches[1]));
			}
		}elseif($is_edge){
			$browser	= 'edge';
			if(preg_match('/Edge\/(.*?)\s/i', $user_agent, $matches)){
				$browser_version	= (float)(trim($matches[1]));
			}
		}elseif($is_lynx){
			$browser	= 'lynx';
		}elseif($is_gecko){
			$browser	= 'gecko';
		}elseif($is_opera){
			$browser	= 'opera';
		}elseif($is_IE){
			$browser	= 'ie';
		}

		$data	= compact('os', 'device', 'app', 'browser', 'os_version', 'browser_version', 'app_version');

		return apply_filters('wpjam_determine_var', $data, $user_agent, $referer);
	}
}

class WPJAM_Bit{
	protected $bit;

	public function __construct($bit){
		$this->set_bit($bit);
	}

	public function set_bit($bit){
		$this->bit	= $bit;
	}

	public function get_bit(){
		return $this->bit;
	}

	public function has($bit){
		return ($this->bit & $bit) == $bit;
	}

	public function add($bit){
		$this->bit = $this->bit | $bit;

		return $this->bit;
	}

	public function remove($bit){
		$this->bit = $this->bit & (~$bit);

		return $this->bit;
	}
}

wp_cache_add_global_groups(['wpjam_list_cache']);

class WPJAM_ListCache{
	private $key;

	public function __construct($key){
		$this->key	= $key;
	}

	private function get_items(&$cas_token){
		$items	= wp_cache_get_with_cas($this->key, 'wpjam_list_cache', $cas_token);

		if($items === false){
			$items	= [];
			wp_cache_add($this->key, [], 'wpjam_list_cache', DAY_IN_SECONDS);
			$items	= wp_cache_get_with_cas($this->key, 'wpjam_list_cache', $cas_token);
		}

		return $items;
	}

	private function set_items($cas_token, $items){
		return wp_cache_cas($cas_token, $this->key, $items, 'wpjam_list_cache', DAY_IN_SECONDS);
	}

	public function get_all(){
		$items	= wp_cache_get($this->key, 'wpjam_list_cache');
		return $items ?: [];
	}

	public function get($k){
		$items = $this->get_all();
		return $items[$k]??false;  
	}

	public function add($item, $k=null){
		$cas_token	= '';
		$retry		= 10;

		do{
			$items	= $this->get_items($cas_token);

			if($k!==null){
				if(isset($items[$k])){
					return false;
				}

				$items[$k]	= $item;
			}else{
				$items[]	= $item;
			}

			$result	= $this->set_items($cas_token, $items);

			$retry	 -= 1;
		}while (!$result && $retry > 0);

		return $result;
	}

	public function increment($k, $offset=1){
		$cas_token	= '';
		$retry		= 10;

		do{
			$items		= $this->get_items($cas_token);
			$items[$k]	= $items[$k]??0; 
			$items[$k]	= $items[$k]+$offset;

			$result	= $this->set_items($cas_token, $items);

			$retry	 -= 1;
		}while (!$result && $retry > 0);

		return $result;
	}

	public function decrement($k, $offset=1){
		return $this->increment($k, 0-$offset);
	}

	public function set($item, $k){
		$cas_token	= '';
		$retry		= 10;

		do{
			$items		= $this->get_items($cas_token);
			$items[$k]	= $item;
			$result		= $this->set_items($cas_token, $items);
			$retry 		-= 1;
		}while(!$result && $retry > 0);

		return $result;
	}

	public function remove($k){
		$cas_token	= '';
		$retry		= 10;

		do{
			$items	= $this->get_items($cas_token);
			if(!isset($items[$k])){
				return false;
			}
			unset($items[$k]);
			$result	= $this->set_items($cas_token, $items);
			$retry 	-= 1;
		}while(!$result && $retry > 0);

		return $result;
	}

	public function empty(){
		$cas_token		= '';
		$retry	= 10;

		do{
			$items	= $this->get_items($cas_token);
			if($items == []){
				return [];
			}
			$result	= $this->set_items($cas_token, []);
			$retry 	-= 1;
		}while(!$result && $retry > 0);

		if($result){
			return $items;
		}

		return $result;
	}
}

class WPJAM_Cache{
	/* HTML 片段缓存
	Usage:

	if (!WPJAM_Cache::output('unique-key')) {
		functions_that_do_stuff_live();
		these_should_echo();
		WPJAM_Cache::store(3600);
	}
	*/
	public static function output($key) {
		$output	= get_transient($key);
		if(!empty($output)) {
			echo $output;
			return true;
		} else {
			ob_start();
			return false;
		}
	}

	public static function store($key, $cache_time='600') {
		$output = ob_get_flush();
		set_transient($key, $output, $cache_time);
		echo $output;
	}
}

class WPJAM_Crypt{
	private $method		= 'aes-256-cbc';
	private $key 		= '';
	private $iv			= '';
	private $options	= OPENSSL_ZERO_PADDING;
	private $block_size	= 32;	// 注意 PHP 默认 aes cbc 算法的 block size 都是 16 位

	public function __construct($args=[]){
		foreach ($args as $key => $value) {
			if(in_array($key, ['key', 'method', 'options', 'iv', 'block_size'])){
				$this->$key	= $value;
			}
		}
	}

	public function encrypt($text){
		if($this->options == OPENSSL_ZERO_PADDING && $this->block_size){
			$text	= $this->pkcs7_pad($text, $this->block_size);	//使用自定义的填充方式对明文进行补位填充
		}

		return openssl_encrypt($text, $this->method, $this->key, $this->options, $this->iv);
	}

	public function decrypt($encrypted_text){
		try{
			$text	= openssl_decrypt($encrypted_text, $this->method, $this->key, $this->options, $this->iv);
		}catch(Exception $e){
			return new WP_Error('decrypt_aes_failed', 'aes 解密失败');
		}

		if($this->options == OPENSSL_ZERO_PADDING && $this->block_size){
			$text	= $this->pkcs7_unpad($text, $this->block_size);	//去除补位字符
		}

		return $text;
	}

	public static function pkcs7_pad($text, $block_size=32){	//对需要加密的明文进行填充 pkcs#7 补位
		//计算需要填充的位数
		$amount_to_pad	= $block_size - (strlen($text) % $block_size);
		$amount_to_pad	= $amount_to_pad ?: $block_size;

		//获得补位所用的字符
		return $text . str_repeat(chr($amount_to_pad), $amount_to_pad);
	}

	public static function pkcs7_unpad($text, $block_size){	//对解密后的明文进行补位删除
		$pad	= ord(substr($text, -1));

		if($pad < 1 || $pad > $block_size){
			$pad	= 0;
		}

		return substr($text, 0, (strlen($text) - $pad));
	}

	public static function weixin_pad($text, $appid){
		$random = self::generate_random_string(16);		//获得16位随机字符串，填充到明文之前
		return $random.pack("N", strlen($text)).$text.$appid;
	}

	public static function weixin_unpad($text, &$appid){	//去除16位随机字符串,网络字节序和AppId
		$text		= substr($text, 16, strlen($text));
		$len_list	= unpack("N", substr($text, 0, 4));
		$text_len	= $len_list[1];
		$appid		= substr($text, $text_len + 4);
		return substr($text, 4, $text_len);
	}

	public static function sha1(...$args){
		sort($args, SORT_STRING);

		return sha1(implode($args));
	}

	public static function generate_weixin_signature($token, &$timestamp='', &$nonce='', $encrypt_msg=''){
		$timestamp	= $timestamp ?: time();
		$nonce		= $nonce ?: self::generate_random_string(8);
		return self::sha1($encrypt_msg, $token, $timestamp, $nonce);
	}

	public static function generate_random_string($length){
		$alphabet	= "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
		$max		= strlen($alphabet);

		$token		= '';
		for ($i = 0; $i < $length; $i++) {
			$token .= $alphabet[self::crypto_rand_secure(0, $max - 1)];
		}

		return $token;
	}

	private static function crypto_rand_secure($min, $max){
		$range	= $max - $min;

		if($range < 1){
			return $min;
		}

		$log	= ceil(log($range, 2));
		$bytes	= (int)($log / 8) + 1;		// length in bytes
		$bits	= (int)$log + 1;			// length in bits
		$filter	= (int)(1 << $bits) - 1;	// set all lower bits to 1

		do {
			$rnd	= hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));
			$rnd	= $rnd & $filter;	// discard irrelevant bits
		}while($rnd > $range);

		return $min + $rnd;
	}
}

class IP{
	private static $ip = null;
	private static $fp = null;
	private static $offset = null;
	private static $index = null;
	private static $cached = [];

	public static function find($ip){
		if (empty( $ip ) === true) {
			return 'N/A';
		}

		$nip	= gethostbyname($ip);
		$ipdot	= explode('.', $nip);

		if ($ipdot[0] < 0 || $ipdot[0] > 255 || count($ipdot) !== 4) {
			return 'N/A';
		}

		if (isset( self::$cached[$nip] ) === true) {
			return self::$cached[$nip];
		}

		if (self::$fp === null) {
			self::init();
		}

		$nip2 = pack('N', ip2long($nip));

		$tmp_offset	= (int) $ipdot[0] * 4;
		$start		= unpack('Vlen',
			self::$index[$tmp_offset].self::$index[$tmp_offset + 1].self::$index[$tmp_offset + 2].self::$index[$tmp_offset + 3]);

		$index_offset = $index_length = null;
		$max_comp_len = self::$offset['len'] - 1024 - 4;
		for ($start = $start['len'] * 8 + 1024; $start < $max_comp_len; $start += 8) {
			if (self::$index[$start].self::$index[$start+1].self::$index[$start+2].self::$index[$start+3] >= $nip2) {
				$index_offset = unpack('Vlen',
					self::$index[$start+4].self::$index[$start+5].self::$index[$start+6]."\x0");
				$index_length = unpack('Clen', self::$index[$start+7]);

				break;
			}
		}

		if ($index_offset === null) {
			return 'N/A';
		}

		fseek(self::$fp, self::$offset['len'] + $index_offset['len'] - 1024);

		self::$cached[$nip] = explode("\t", fread(self::$fp, $index_length['len']));

		return self::$cached[$nip];
	}

	private static function init(){
		if (self::$fp === null) {
			self::$ip = new self();

			self::$fp = fopen(WP_CONTENT_DIR.'/uploads/17monipdb.dat', 'rb');
			if (self::$fp === false) {
				throw new Exception('Invalid 17monipdb.dat file!');
			}

			self::$offset = unpack('Nlen', fread(self::$fp, 4));
			if (self::$offset['len'] < 4) {
				throw new Exception('Invalid 17monipdb.dat file!');
			}

			self::$index = fread(self::$fp, self::$offset['len'] - 4);
		}
	}

	public function __destruct(){
		if (self::$fp !== null) {
			fclose(self::$fp);
		}
	}
}