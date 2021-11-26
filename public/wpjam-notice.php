<?php
class WPJAM_Notice{
	public static function add($notice){
		return WPJAM_Admin_Notice::get_instance(get_current_blog_id())->add($notice);
	}

	public static function ajax_delete(){
		if($notice_key = wpjam_get_data_parameter('notice_key')){
			WPJAM_User_Notice::get_instance(get_current_user_id())->delete($notice_key);

			if(current_user_can('manage_options')){
				WPJAM_Admin_Notice::get_instance(get_current_blog_id())->delete($notice_key);
			}
		}

		wpjam_send_json();
	}

	public static function on_admin_notices(){
		if($errors = WPJAM_Admin_Error::get_errors()){
			foreach ($errors as $error){
				echo '<div class="notice notice-'.$error['type'].' is-dismissible"><p>'.$error['message'].'</p></div>';
			}
		}

		$admin_notice_obj	= WPJAM_Admin_Notice::get_instance(get_current_blog_id());
		$user_notice_obj	= WPJAM_User_Notice::get_instance(get_current_user_id());

		if($notice_key	= wpjam_get_parameter('notice_key')){
			$user_notice_obj->delete($notice_key);

			if(current_user_can('manage_options')){
				$admin_notice_obj->delete($notice_key);
			}
		}

		$notices	= $user_notice_obj->get_notices();

		if(current_user_can('manage_options')){
			$notices	= array_merge($notices, $admin_notice_obj->get_notices());
		}

		if(empty($notices)){
			return;
		}

		uasort($notices, function($n, $m){ return $m['time'] <=> $n['time']; });

		$modal_notice	= '';

		foreach ($notices as $notice_key => $notice){
			$notice = wp_parse_args($notice, [
				'type'		=> 'info',
				'class'		=> 'is-dismissible',
				'admin_url'	=> '',
				'notice'	=> '',
				'title'		=> '',
				'modal'		=> 0,
			]);

			$admin_notice	= trim($notice['notice']);

			if(empty($admin_notice)){
				$admin_notice_obj->delete($notice_key);
				$user_notice_obj->delete($notice_key);
				continue;
			}

			if($notice['admin_url']){
				$admin_notice	.= $notice['modal'] ? "\n\n" : ' ';
				$admin_notice	.= '<a style="text-decoration:none;" href="'.add_query_arg(compact('notice_key'), home_url($notice['admin_url'])).'">点击查看<span class="dashicons dashicons-arrow-right-alt"></span></a>';
			}

			$admin_notice	= wpautop($admin_notice).wpjam_get_page_button('delete_notice', ['data'=>compact('notice_key')]);

			if($notice['modal']){
				if(empty($modal_notice)){	// 弹窗每次只显示一条
					$modal_notice	= $admin_notice;
					$modal_title	= $notice['title'] ?: '消息';

					echo '<div id="notice_modal" class="hidden" data-title="'.esc_attr($modal_title).'">'.$modal_notice.'</div>';
				}
			}else{
				echo '<div class="notice notice-'.$notice['type'].' '.$notice['class'].'">'.$admin_notice.'</div>';
			}
		}
	}
}

class WPJAM_Admin_Notice{
	private $blog_id	= 0;
	private $notices	= [];

	private static $instances	= [];

	public static function get_instance($blog_id=0){
		if(!isset(self::$instances[$blog_id])){
			self::$instances[$blog_id] = new self($blog_id);
		}

		return self::$instances[$blog_id];
	}

	private function __construct($blog_id=0){
		$this->blog_id	= $blog_id;

		$notices = is_multisite() ? get_blog_option($blog_id, 'wpjam_notices') : get_option('wpjam_notices');

		if($notices){
			$this->notices	= array_filter($notices, function($notice){ return $notice['time'] > time() - MONTH_IN_SECONDS * 3; });
		}
	}

	public function get_notices(){
		return $this->notices;
	}

	public function add($notice){
		$notice['time']	= $notice['time'] ?? time();
		$key			= $notice['key'] ?? md5(maybe_serialize($notice));

		$this->notices[$key]	= $notice;

		return $this->save();
	}

	public function delete($key){
		if(isset($this->notices[$key])){
			unset($this->notices[$key]);
			return $this->save();
		}

		return true;
	}

	public function save(){
		if(empty($this->notices)){
			return is_multisite() ? delete_blog_option($this->blog_id, 'wpjam_notices') : delete_option('wpjam_notices');
		}else{
			return is_multisite() ? update_blog_option($this->blog_id, 'wpjam_notices', $this->notices) : update_option('wpjam_notices', $this->notices);
		}
	}
}

class WPJAM_User_Notice{
	private $user_id	= 0;
	private $notices	= [];

	private static $instances	= [];

	public static function get_instance($user_id){
		if(!isset(self::$instances[$user_id])){
			self::$instances[$user_id] = new self($user_id);
		}

		return self::$instances[$user_id];
	}

	private function __construct($user_id){
		$this->user_id	= $user_id;

		if($user_id && ($notices = get_user_meta($user_id, 'wpjam_notices', true))){
			$this->notices	= array_filter($notices, function($notice){ return $notice['time'] > time() - MONTH_IN_SECONDS * 3; });
		}
	}

	public function get_notices(){
		return $this->notices;
	}

	public function add($notice){
		$notice['time']	= $notice['time'] ?? time();
		$key			= $notice['key'] ?? md5(maybe_serialize($notice));

		$this->notices[$key]	= $notice;

		return $this->save();
	}

	public function delete($key){
		if(isset($this->notices[$key])){
			unset($this->notices[$key]);
			return $this->save();
		}

		return true;
	}

	public function save(){
		if(empty($this->notices)){
			return delete_user_meta($this->user_id, 'wpjam_notices');
		}else{
			return update_user_meta($this->user_id, 'wpjam_notices', $this->notices);
		}
	}
}

class WPJAM_User_Message{
	private $user_id	= 0;
	private $messages	= [];

	private static $instances	= [];

	public static function get_instance($user_id){
		if(!isset(self::$instances[$user_id])){
			self::$instances[$user_id] = new self($user_id);
		}

		return self::$instances[$user_id];
	}

	private function __construct($user_id){
		$this->user_id	= $user_id;

		if($user_id && ($messages = get_user_meta($user_id, 'wpjam_messages', true))){
			$this->messages	= array_filter($messages, function($message){ return $message['time'] > time() - MONTH_IN_SECONDS * 3; });
		}
	}

	public function get_messages(){
		return $this->messages;
	}

	public function get_unread_count(){
		$messages	= array_filter($this->messages, function($message){ return $message['status'] == 0; });

		return count($messages);
	}

	public function set_all_read(){
		array_walk($this->messages, function(&$message){ $message['status'] == 1; });

		return $this->save();
	}

	public function add($message){
		$message	= wp_parse_args($message, [
			'sender'	=> '',
			'receiver'	=> '',
			'type'		=> '',
			'content'	=> '',
			'status'	=> 0,
			'time'		=> time()
		]);

		$message['content'] = wp_strip_all_tags($message['content']);

		$this->messages[]	= $message;

		return $this->save();
	}

	public function delete($i){
		if(isset($this->messages[$i])){
			unset($this->messages[$i]);
			return $this->save();
		}

		return true;
	}

	public function save(){
		if(empty($this->messages)){
			return delete_user_meta($this->user_id, 'wpjam_messages');
		}else{
			return update_user_meta($this->user_id, 'wpjam_messages', $this->messages);
		}
	}

	public function load_plugin_page(){
		wpjam_register_page_action('delete_message', [
			'button_text'	=> '删除',
			'class'			=> 'message-delete',
			'callback'		=> [$this, 'ajax_delete'],
			'direct'		=> true,
			'confirm'		=> true
		]);
	}

	public function ajax_delete(){
		$message_id	= (int)wpjam_get_data_parameter('message_id');
		$messages	= $this->get_messages();

		if($messages && isset($messages[$message_id])){
			$result	= $this->delete($message_id);

			if(is_wp_error($result)){
				wpjam_send_json($result);
			}else{
				wpjam_send_json(['message_id'=>$message_id]);
			}
		}

		wpjam_send_json(['errcode'=>'invalid_message_id', '无效的消息ID']);
	}

	public function plugin_page(){
		$messages	= $this->get_messages();

		if(empty($messages)){ 
			echo '<p>暂无站内消息</p>';
			return;
		}

		if($this->get_unread_count()){
			$this->set_all_read();
		}

		$sender_ids			= [];
		$post_ids_list		= [];
		$comment_ids_list	= [];

		foreach($messages as $message) {
			$sender_ids[]	= $message['sender'];
			$blog_id		= $message['blog_id'];
			$post_id		= $message['post_id'];
			$comment_id		= $message['comment_id'];
			if($blog_id){
				if($post_id){
					$post_ids_list[$blog_id][]		= $post_id;
				}

				if($comment_id){
					$comment_ids_list[$blog_id][]	= $comment_id;
				}
			}
		}

		$senders	= get_users(['blog_id'=>0, 'include'=>$sender_ids]);

		foreach ($post_ids_list as $blog_id => $post_ids) {
			$switched	= is_multisite() ? switch_to_blog($blog_id) : false;

			wpjam_get_posts($post_ids);

			if($switched){
				restore_current_blog();
			}
		}

		foreach ($comment_ids_list as $blog_id => $comment_ids) {
			$switched	= is_multisite() ? switch_to_blog($blog_id) : false;

			get_comments(['include'=>$comment_ids]);

			if($switched){
				restore_current_blog();
			}
		}
		?>

		<ul class="messages">
		<?php foreach ($messages as $i => $message) { 
			$alternate	= empty($alternate)?'alternate':'';
			$sender		= get_userdata($message['sender']);

			$type		= $message['type'];
			$content	= $message['content'];
			$blog_id	= $message['blog_id'];
			$post_id	= $message['post_id'];
			$comment_id	= $message['comment_id'];
			

			if(empty($sender)){
				continue;
			}

			if($blog_id && $post_id){
				$switched	= is_multisite() ? switch_to_blog($blog_id) : false;

				$post		= get_post($post_id);

				if($post){
					$topic_title	= $post->post_title;
				}

				if($switched){
					restore_current_blog();
				}
			}else{
				$topic_title		= '';
			}
		?>
			<li id="message_<?php echo $i; ?>" class="<?php echo $alternate; echo empty($message['status'])?' unread':'' ?>">
				<div class="sender-avatar"><?php echo get_avatar($message['sender'], 60);?></div>
				<div class="message-time"><?php echo wpjam_human_time_diff($message['time']);?><p><?php echo wpjam_get_page_button('delete_message',['data'=>['message_id'=>$i]]);?></p></div>
				<div class="message-content">
				
				<?php 

				if($type == 'topic_comment'){
					$prompt	= '<span class="message-sender">'.$sender->display_name.'</span>在你的帖子「<a href="'.admin_url('admin.php?page=wpjam-topics&action=comment&id='.$post_id.'#comment_'.$comment_id).'">'.$topic_title.'</a>」给你留言了：'."\n\n";
				}elseif($type == 'comment_reply' || $type == 'topic_reply'){
					$prompt	= '<span class="message-sender">'.$sender->display_name.'</span>在帖子「<a href="'.admin_url('admin.php?page=wpjam-topics&action=comment&id='.$post_id.'#comment_'.$comment_id).'">'.$topic_title.'</a>」回复了你的留言：'."\n\n";
				}else{
					$prompt	= '<span class="message-sender">'.$sender->display_name.'：'."\n\n";
				}

				echo wpautop($prompt.$content);

				?>
				</div>
			</li>
			<?php } ?>
		</ul>

		<style type="text/css">
			ul.messages{ max-width:640px; }
			ul.messages li {margin: 10px 0; padding:10px; margin:10px 0; background: #fff; min-height: 60px;}
			ul.messages li.alternate{background: #f9f9f9;}
			ul.messages li.unread{font-weight: bold;}
			ul.messages li a {text-decoration:none;}
			ul.messages li div.sender-avatar {float:left; margin:0px 10px 0px 0;}
			ul.messages li div.message-time{float: right; width: 60px;}
			ul.messages li .message-delete{color: #a00;}
			ul.messages li div.message-content p {margin: 0 70px 10px 70px; }
		</style>
		
		<script type="text/javascript">
		jQuery(function($){
			$('body').on('page_action_success', function(e, response){
				var action		= response.page_action;
				var action_type	= response.page_action_type;

				if(action == 'delete_message'){
					var message_id	= response.message_id;
					$('#message_'+message_id).animate({opacity: 0.1}, 500, function(){ $(this).remove();});
				}
			});
		});
		</script>
		
		<?php
	}
}

Class WPJAM_Admin_Error{
	public static $errors = [];

	public static function add_error($message='', $type='success'){
		if($message){
			if(is_wp_error($message)){
				self::$errors[]	= ['message'=>$message->get_error_message(), 'type'=>'error'];
			}elseif($type){
				self::$errors[]	= compact('message','type');
			}
		}
	}

	public static function get_errors(){
		return self::$errors;
	}
}

function wpjam_add_admin_notice($notice, $blog_id=0){
	$blog_id	= $blog_id ?: get_current_blog_id();
	return WPJAM_Admin_Notice::get_instance($blog_id)->add($notice);
}

function wpjam_add_user_notice($user_id, $notice){
	return WPJAM_User_Notice::get_instance($user_id)->add($notice);
}

function wpjam_admin_add_error($message='', $type='success'){
	return WPJAM_Admin_Error::add_error($message, $type);
}

function wpjam_send_user_message(...$args){
	if(count($args) == 2){
		$user_id	= $args[0];
		$message	= $args[1];
	}else{
		$message	= $args[0];
		$user_id	= $message['receiver'];
	}

	return WPJAM_User_Message::get_instance($user_id)->add($message);
}

if(is_admin()){
	// add_action('wpjam_admin_init', function(){
	// 	$user_id	= get_current_user_id();
	// 	$instance	= WPJAM_User_Message::get_instance($user_id);

	// 	wpjam_add_menu_page('wpjam-messages', [
	// 		'menu_title'	=>'站内消息',
	// 		'capability'	=>'read',
	// 		'parent'		=>'users',
	// 		'function'		=>[$instance, 'plugin_page'],
	// 		'load_callback'	=>[$instance, 'load_plugin_page']
	// 	]);
	// });

	wpjam_register_page_action('delete_notice', [
		'tag'			=> 'span',
		'class'			=> 'hidden delete-notice',
		'button_text'	=> '删除',
		'direct'		=> true,
		'callback'		=> ['WPJAM_Notice', 'ajax_delete']
	]);

	add_action('admin_notices',	['WPJAM_Notice', 'on_admin_notices']);
}