<?php
/*
Name: 文章类型转换器
URI: https://blog.wpjam.com/m/wpjam-post-type-switcher/
Description: 可以将文章在多种文章类型中进行转换。
Version: 1.0
*/
if(is_admin()){
	add_action('wpjam_builtin_page_load', function ($screen_base, $current_screen){
		if($screen_base != 'post' || $current_screen->post_type == 'attachment'){
			return;
		}

		add_action('wp_after_insert_post', function ($post_id, $post){
			if((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post', $post_id)){
				return;
			}

			$new_post_type	= wpjam_get_parameter('pts_post_type', ['method'=>'REQUEST', 'sanitize_callback'=>'sanitize_key']);

			if (!empty($new_post_type) && !in_array($post->post_type, [$new_post_type, 'revision'])) {
				$new_pt_object = get_post_type_object($new_post_type);
				if($new_pt_object && current_user_can($new_pt_object->cap->publish_posts)){
					set_post_type($post_id, $new_pt_object->name);
				}
			}
		}, 990, 2);

		add_action('post_submitbox_misc_actions', function (){
			$post_types = get_post_types(['show_ui'=>true], 'objects' );
			unset($post_types['attachment']);
			unset($post_types['wp_block']);

			$current_post_type	= get_post_type();

			$post_type_object	= get_post_type_object($current_post_type);

			if(empty($post_type_object) || is_wp_error($post_type_object)){
				return; 
			}

			?>

			<div class="misc-pub-section post-type-switcher">
				<label for="pts_post_type">文章类型：</label>
				<strong id="post_type_display"><?php echo esc_html( $post_type_object->labels->singular_name ); ?></strong>

				<?php if ( current_user_can( $post_type_object->cap->publish_posts ) ) {?>

					<a href="javascript:;" id="edit_post_type_switcher" class="hide-if-no-js"><?php _e( 'Edit' ); ?></a>

					<div id="post_type_select">
						<select name="pts_post_type" id="pts_post_type">

							<?php foreach ( $post_types as $post_type => $pt ) : ?>

								<?php if ( ! current_user_can( $pt->cap->publish_posts ) ) continue; ?>

								<option value="<?php echo esc_attr( $pt->name ); ?>" <?php selected($current_post_type, $post_type); ?>><?php echo esc_html( $pt->labels->singular_name ); ?></option>

							<?php endforeach; ?>

						</select>

						<a href="javascript:;" id="save_post_type_switcher" class="hide-if-no-js button"><?php _e( 'OK' ); ?></a>
						<a href="javascript:;" id="cancel_post_type_switcher" class="hide-if-no-js button-cancel"><?php _e( 'Cancel' ); ?></a>
					</div>

				<?php } ?>

			</div>

			<?php
		});

		add_action('admin_head', function(){
			?>
			<script type="text/javascript">
			jQuery(function($){
				// $( '.misc-pub-section.curtime.misc-pub-section-last' ).removeClass( 'misc-pub-section-last' );
				$('#edit_post_type_switcher' ).on('click', function(e) {
					$(this).hide();
					$('#post_type_select').slideDown();
				});

				$('#save_post_type_switcher' ).on('click',  function(e) {
					$('#post_type_select').slideUp();
					$('#edit_post_type_switcher').show();
					$('#post_type_display').text($('#pts_post_type :selected').text());
				});

				$('#cancel_post_type_switcher').on('click',  function(e) {
					$('#post_type_select').slideUp();
					$('#edit_post_type_switcher').show();
				});
			});
			</script>
			<style type="text/css">
			#post_type_select {
				margin-top: 3px;
				display: none;
			}
			#post-body .post-type-switcher::before {
				content: '\f109';
				font: 400 20px/1 dashicons;
				speak: none;
				display: inline-block;
				padding: 0 2px 0 0;
				top: 0;
				left: -1px;
				position: relative;
				vertical-align: top;
				-webkit-font-smoothing: antialiased;
				-moz-osx-font-smoothing: grayscale;
				text-decoration: none !important;
				color: #888;
			}
			</style>
			<?php
		});
	}, 10, 2);
}