jQuery(function($){
	$.fn.extend({
		wpjam_add_attachment: function(attachment){
			let render	= wp.template('wpjam-img');

			$(this).prev('input').val($(this).data('item_type') == 'url' ? attachment.url : attachment.id);
			$(this).html(render({
				img_url		: attachment.url,
				img_style	: $(this).data('img_style'),
				thumb_args	: $(this).data('thumb_args')
			})).removeClass('button add_media');
		},

		wpjam_add_mu_attachment: function(attachment){
			let max_items	= parseInt($(this).data('max_items'));

			if(max_items && $(this).parent().parent().find('div.mu-item').length >= max_items){
				return ;
			}

			let render	= wp.template('wpjam-mu-img');
			let i		= $(this).data('i');

			$(this).before(render({
				img_url		: attachment.url, 
				img_value	: ($(this).data('item_type') == 'url') ? attachment.url : attachment.id,
				thumb_args	: $(this).data('thumb_args'),
				name		: $(this).data('name'),
				id			: $(this).data('id'),
				i			: i
			}));

			$(this).data('i', i+1);
		},

		wpjam_show_if: function(){
			this.each(function(){
				let show_if_key	= $(this).data('key');
				let show_if_val	= $(this).val();

				if($(this).is(':checkbox')){
					if($(this).hasClass('mu-checkbox')){
						show_if_val	= [];

						$('.checkbox-'+show_if_key+':checked').each(function(){
							show_if_val.push($(this).val());
						});
					}else{
						if(!$(this).is(':checked')){
							show_if_val	= 0;
						}
					}
				}else if($(this).is(':radio')) {
					if(!$(this).is(':checked')){
						return;
					}
				}

				if($(this).prop('disabled')){
					show_if_val	= null;
				}

				$('.show-if-'+show_if_key).each(function(){
					if($.wpjam_compare(show_if_val, $(this).data('show_if').compare, $(this).data('show_if').value)){
						$(this).find(':input').prop('disabled', false);
						$(this).removeClass('hidden');
					}else{
						$(this).find(':input').prop('disabled', true);
						$(this).addClass('hidden');
					}

					$(this).find('.show-if-key').wpjam_show_if();
				});
			});

			tb_position();
		},

		wpjam_autocomplete: function(){
			this.each(function(){
				if($(this).next('.wpjam-query-title').length){
					$(this).addClass('hidden');
				}else{
					$(this).removeClass('hidden');
				}

				$(this).autocomplete({
					minLength:	0,
					source: function(request, response){
						let args = {
							action:		'wpjam-query',
							data_type:	this.element.data('data_type'),
							query_args:	this.element.data('query_args')
						};

						if(request.term){
							if(args.data_type == 'post_type'){
								args.query_args.s		= request.term;
							}else{
								args.query_args.search	= request.term;
							}
						}

						let mapping_keys	= {'label':'title', 'id':'id'};

						if(args.data_type == 'taxonomy'){
							mapping_keys.label	= 'name';
						}else if(args.data_type == 'model'){
							mapping_keys.label	= args.query_args.label_key;
							mapping_keys.id		= args.query_args.id_key;
						}

						$.post(ajaxurl, args, function(data, status){
							response($.map(data.datas, function(item){
								return {label:item[mapping_keys.label], value:item[mapping_keys.id]};
							}));
						});
					},
					select: function(event, ui){
						$(this).after('<span class="wpjam-query-title"><span class="dashicons dashicons-dismiss"></span>'+ui.item.label+'</span>');
						$(this).addClass('hidden');
					}
				}).focus(function(){
					if(this.value == ''){
						$(this).autocomplete('search');
					}
				});
			});
		},

		wpjam_editor: function(){
			this.each(function(){
				if(wp.editor){
					let id	= $(this).attr('id');

					wp.editor.remove(id);
					wp.editor.initialize(id, $(this).data('settings'));
				}else{
					alert('请在页面加载 add_action(\'admin_footer\', \'wp_enqueue_editor\');');
				}
			});
		},

		wpjam_tabs: function(){
			this.each(function(){
				$(this).tabs({
					activate: function(event, ui){
						$('.ui-corner-top a').removeClass('nav-tab-active');
						$('.ui-tabs-active a').addClass('nav-tab-active');

						let tab_href = window.location.origin + window.location.pathname + window.location.search +ui.newTab.find('a').attr('href');
						window.history.replaceState(null, null, tab_href);
						$('input[name="_wp_http_referer"]').val(tab_href);
					},
					create: function(event, ui){
						if(ui.tab.find('a').length){
							ui.tab.find('a').addClass('nav-tab-active');
							if(window.location.hash){
								$('input[name="_wp_http_referer"]').val($('input[name="_wp_http_referer"]').val()+window.location.hash);
							}
						}
					}
				});
			});
		},

		wpjam_max_reached: function(){
			let	max_items = parseInt($(this).data('max_items'));

			if(max_items){
				if($(this).find(' > div.mu-item').length >= max_items){
					alert('最多'+max_items+'个');

					return true;
				}
			}

			return false;
		}
	});

	$.extend({
		wpjam_compare: function(a, compare, b){
			if(a === null){
				return false;
			}

			if(Array.isArray(a)){
				if(compare == '='){
					return a.indexOf(b) != -1;
				}else if(compare == '!='){
					return a.indexOf(b) == -1;
				}else if(compare == 'IN'){
					return a.filter(function(n) { return b.indexOf(n) !== -1; }).length == b.length;
				}else if(compare == 'NOT IN'){
					return a.filter(function(n) { return b.indexOf(n) !== -1; }).length == 0;
				}else{
					return false;
				}
			}else{
				if(compare == '='){
					return a == b;
				}else if(compare == '!='){
					return a != b;
				}else if(compare == '>'){
					return a > b;
				}else if(compare == '>='){
					return a >= b;
				}else if(compare == '<'){
					return a < b;
				}else if(compare == '<='){
					return a <= b;
				}else if(compare == 'IN'){
					return b.indexOf(a) != -1;
				}else if(compare == 'NOT IN'){
					return b.indexOf(a) == -1;
				}else if(compare == 'BETWEEN'){
					return a > b[0] && a < b[1];
				}else if(compare == 'NOT BETWEEN'){
					return a < b[0] && a > b[1];
				}else{
					return false;
				}
			}
		},

		wpjam_form_init: function(){
			// 拖动排序
			$('.mu-fields').sortable({
				handle: '.dashicons-menu',
				cursor: 'move'
			});

			$('.mu-images').sortable({
				handle: '.dashicons-menu',
				cursor: 'move'
			});

			$('.mu-files').sortable({
				handle: '.dashicons-menu',
				cursor: 'move'
			});

			$('.mu-texts').sortable({
				handle: '.dashicons-menu',
				cursor: 'move'
			});

			$('.mu-imgs').sortable({
				cursor: 'move'
			});

			$('.wpjam-tooltip .wpjam-tooltip-text').css('margin-left', function(){
				return 0 - Math.round($(this).width()/2);
			});

			$('.tabs').wpjam_tabs();
			$('.show-if-key').wpjam_show_if();
			$('.wpjam-autocomplete').wpjam_autocomplete();

			$('input.color').wpColorPicker();

			$('input[type="range"]').change();

			$('textarea.wpjam-editor').wpjam_editor();
		}
	});

	$('body').on('change', '.show-if-key', function(){
		$(this).wpjam_show_if();
	});

	$('body').on('change', 'input[type="range"]', function(){
		$(this).next('span').html($(this).val());
	});

	$.wpjam_form_init();

	$('body').on('list_table_action_success', function(event, response){
		$.wpjam_form_init();
	});

	$('body').on('page_action_success', function(event, response){
		$.wpjam_form_init();
	});

	$('body').on('option_action_success', function(event, response){
		$.wpjam_form_init();
	});

	//  重新设置
	$('body').on('click', '.wpjam-query-title span.dashicons', function(){
		$(this).parent().fadeOut(300, function(){
			$(this).prev('input').val('').removeClass('hidden');
			$(this).remove();
		});
	});

	var del_item = '<a href="javascript:;" class="button wpjam-del-item">删除</a> <span class="dashicons dashicons-menu"></span>';

	var custom_uploader;
	if (custom_uploader) {
		custom_uploader.open();
		return;
	}

	$('body').on('click', '.wpjam-file', function(e) {
		let prev_input	= $(this).prev('input');
		let item_type	= $(this).data('item_type');
		let title		= (item_type == 'image')?'选择图片':'选择文件';

		custom_uploader = wp.media({
			title:		title,
			library:	{ type: item_type },
			button:		{ text: title },
			multiple:	false 
		}).on('select', function() {
			let attachment = custom_uploader.state().get('selection').first().toJSON();
			prev_input.val(attachment.url);
			$('.media-modal-close').trigger('click');
		}).open();

		return false;
	});

	//上传单个图片
	$('body').on('click', '.wpjam-img', function(e) {
		let _this	= $(this);

		if(wp.media.view.settings.post.id){
			custom_uploader = wp.media({
				title:		'选择图片',
				library:	{ type: 'image' },
				button:		{ text: '选择图片' },
				frame:		'post',
				multiple:	false 
			// }).on('select', function() {
			}).on('open',function(){
				$('.media-frame').addClass('hide-menu');
			}).on('insert', function() {
				_this.wpjam_add_attachment(custom_uploader.state().get('selection').first().toJSON());

				$('.media-modal-close').trigger('click');
			}).open();
		}else{
			custom_uploader = wp.media({
				title:		'选择图片',
				library:	{ type: 'image' },
				button:		{ text: '选择图片' },
				multiple:	false 
			}).on('select', function() {
				_this.wpjam_add_attachment(custom_uploader.state().get('selection').first().toJSON());

				$('.media-modal-close').trigger('click');
			}).open();
		}

		return false;
	});

	//上传多个图片或者文件
	$('body').on('click', '.wpjam-mu-file', function(e) {
		if($(this).parents('.mu-files').wpjam_max_reached()){
			return false;
		}

		let _this		= $(this);
		let render		= wp.template('wpjam-mu-file');
		let item_type	= $(this).data('item_type');
		let title		= (item_type == 'image')?'选择图片':'选择文件';

		custom_uploader = wp.media({
			title:		title,
			library:	{ type: item_type },
			button:		{ text: title },
			multiple:	true
		}).on('select', function() {
			let i		= _this.data('i');
			let id		= _this.data('id');
			let name	= _this.data('name');
			let render	= wp.template('wpjam-mu-file');

			custom_uploader.state().get('selection').map( function( attachment ) {
				attachment	= attachment.toJSON();

				i++;

				_this.parent().before(render({
					img_url	: attachment.url,
					name	: name,
					id		: id,
					i		: i
				}));
			});

			_this.data('i', i);

			$('.media-modal-close').trigger('click');
		}).open();

		return false;
	});

	//上传多个图片
	$('body').on('click', '.wpjam-mu-img', function(e) {
		if($(this).parents('.mu-imgs').wpjam_max_reached()){
			return false;
		}

		let _this	= $(this);

		if(wp.media.view.settings.post.id){
			custom_uploader = wp.media({
				title:		'选择图片',
				library:	{ type: 'image' },
				button:		{ text: '选择图片' },
				frame:		'post',
				multiple:	true
			// }).on('select', function() {
			}).on('open',function(){
				$('.media-frame').addClass('hide-menu');
			}).on('insert', function() {
				custom_uploader.state().get('selection').map( function( attachment ) {
					_this.wpjam_add_mu_attachment(attachment.toJSON());
				});

				$('.media-modal-close').trigger('click');
			}).open();
		}else{
			custom_uploader = wp.media({
				title:		'选择图片',
				library:	{ type: 'image' },
				button:		{ text: '选择图片' },
				multiple:	true
			}).on('select', function() {
				custom_uploader.state().get('selection').map( function( attachment ) {
					_this.wpjam_add_mu_attachment(attachment.toJSON());
				});

				$('.media-modal-close').trigger('click');
			}).open();
		}

		return false;
	});

	//  删除图片
	$('body').on('click', '.wpjam-del-img', function(){

		$(this).parent().prev('input').val('');
		$(this).prev('img').fadeOut(300, function(){
			$(this).remove();
		});

		if($(this).parent().parent().hasClass('wp-media-buttons')){
			$(this).parent().addClass('button add_media').html('<span class="wp-media-buttons-icon"></span> 添加图片</button>');
		}

		$(this).remove();

		return false;
	});

	// 添加多个选项
	$('body').on('click', 'a.wpjam-mu-text', function(){
		if($(this).parents('.mu-texts').wpjam_max_reached()){
			return false;
		}

		let i		= $(this).data('i')+1;
		let item	= $(this).parent().clone();

		item.insertAfter($(this).parent());
		item.find(':input').attr('id', $(this).data('id')+'_'+i).val('');
		item.find('.wpjam-query-title').remove();
		item.find('.wpjam-autocomplete').removeClass('hidden').wpjam_autocomplete();

		item.find('a.wpjam-mu-text').data('i', i);

		$(this).parent().append(del_item);
		$(this).remove();

		return false;
	});

	$('body').on('click', 'a.wpjam-mu-fields', function(){
		if($(this).parents('.mu-fields').wpjam_max_reached()){
			return false;
		}

		let render	= wp.template($(this).data('tmpl-id'));
		let i		= $(this).data('i')+1;
		let item	= $(render({i:i}));

		item.insertAfter($(this).parent());
		item.find('.show-if-key').wpjam_show_if();
		item.find('.wpjam-autocomplete').wpjam_autocomplete();

		item.find('a.wpjam-mu-fields').data('i', i);

		$(this).parent().append(del_item);
		$(this).parent().parent().trigger('mu_fields_added', i);
		$(this).remove();

		return false;
	});

	//  删除选项
	$('body').on('click', '.wpjam-del-item', function(){
		let next_input	= $(this).parent().next('input');
		if(next_input.length > 0){
			next_input.val('');
		}

		$(this).parent().fadeOut(300, function(){
			$(this).remove();
		});

		return false;
	});
});

if (self != top) {
	document.getElementsByTagName('html')[0].className += ' TB_iframe';
}

function isset(obj){
	if(typeof(obj) != 'undefined' && obj !== null) {
		return true;
	}else{
		return false;
	}
}