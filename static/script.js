jQuery(function($){
	$.fn.extend({
		wpjam_notice: function(notice, type){
			$(this).html('<p><strong>'+notice+'</strong></p>')
			.removeClass('notice-success notice-error notice-info hidden')
			.addClass('notice-'+type).css('opacity', 0)
			.slideDown(200, function(){
				$(this).fadeTo(200, 1, function(){
					$(this).wpjam_make_notice_dismissible();

					if($('#TB_ajaxContent').length > 0){
						tb_position();
						$('#TB_ajaxContent').scrollTop(0);
					}
				});
			});

			return $(this);
		},

		wpjam_make_notice_dismissible: function(){
			let $el = $(this);

			if($el.find('button.notice-dismiss').length > 0){
				return;
			}

			let $button = $('<button type="button" class="notice-dismiss"><span class="screen-reader-text"></span></button>' );

			$button.find('.screen-reader-text' ).text('忽略此通知。');
			$button.on('click.wp-dismiss-notice', function(event){
				event.preventDefault();
				$el.fadeTo(100, 0, function(){
					$el.slideUp(100, function(){
						$el.remove();
					});
				});
			});

			$el.append($button);
		}
	});

	$.extend({
		wpjam_list_table_clear_tablenav_top: function(){
			if($('.tablenav.top').find('div.alignleft').length == 0){
				$('.tablenav.top').css({clear:'none'});
			}
		},

		wpjam_list_table_action: function(args){
			let list_action	= args.list_action;
			let action_type	= args.list_action_type;
			let action_data	= args.data;
			let item_prefix	= wpjam_page_setting.item_prefix;
			
			args.action 		= 'wpjam-list-table-action';
			args.data			= $.wpjam_append_query_data(args.data);
			args.screen_id		= wpjam_page_setting.screen_id;
			args.plugin_page	= wpjam_page_setting.plugin_page;
			args.current_tab	= wpjam_page_setting.current_tab;

			if($('#TB_ajaxContent').length > 0){
				$('input#list-table-submit').prop('disabled', true);
				$('.spinner').addClass('is-active');
				$('.list-table-action-notice').fadeOut(400);
			}else{
				$('body').append("<div id='TB_load'><img src='"+imgLoader.src+"' width='208' /></div>");
				$('#TB_load').show();

				if(args.bulk){
					$.each(args.ids, function(index, id){
						let tr_id	= $.wpjam_list_table_tr_id(id);
						$(item_prefix+tr_id+' .check-column input').after('<span class="spinner is-active"></span>').hide();
					});
				}else{
					if(args.id){
						let tr_id	= $.wpjam_list_table_tr_id(args.id);
						$(item_prefix+tr_id+' .check-column input').after('<span class="spinner is-active"></span>').hide();
					}
				}

				$('.list-table-notice').fadeOut(400);
			}

			$.post(ajaxurl, args, function(data, status){
				let response	= (typeof data == 'object') ? data : JSON.parse(data);

				if(action_type == 'submit'){
					$('input#list-table-submit').prop('disabled', false);
					$('.spinner').removeClass('is-active');
				}else{
					$('#TB_load').remove();

					if(args.bulk){
						$.each(args.ids, function(index, id){
							let tr_id	= $.wpjam_list_table_tr_id(id);
							$(item_prefix+tr_id+' .check-column input').show();
							$(item_prefix+tr_id+' .check-column .spinner').remove();
						});
					}else{
						if(args.id){
							let tr_id	= $.wpjam_list_table_tr_id(args.id);

							$(item_prefix+tr_id+' .check-column input').show();
							$(item_prefix+tr_id+' .check-column .spinner').remove();
						}
					}
				}

				if($('.list-table-notice').length < 1){
					$('hr.wp-header-end').after('<div class="list-table-notice notice is-dismissible hidden"></div>');
				}

				if(response.errcode != 0){
					if(action_type == 'submit'){
						$('#TB_ajaxContent').scrollTop(0);
					}

					if($('#TB_ajaxContent').length > 0){
						$('.list-table-action-notice').wpjam_notice(response.errmsg, 'error');
					}else{
						$('.list-table-notice').wpjam_notice(response.errmsg, 'error');
					}
				}else{
					let response_type	= response.type;

					args.tb_width	= args.tb_width || 720;
					args.tb_height	= args.tb_height || 200;

					$('.wp-list-table > tbody tr').css('background-color', '');

					if(action_type == 'list'){
						$('div.list-table').html(response.data);

						$(document).scrollTop(0);

						$.wpjam_list_table_clear_tablenav_top();

						$('body').trigger('list_table_loaded');
					}else if(action_type == 'left'){
						$('div.list-table').html(response.data);
						$('div#col-left div').html(response.left);

						$(document).scrollTop(0);

						$.wpjam_list_table_clear_tablenav_top();

						$('body').trigger('list_table_loaded');
					}else if(action_type == 'form'){
						if($('#TB_ajaxContent').length > 0){
							$('#TB_ajaxWindowTitle').html(response.page_title);
							$('#TB_ajaxContent').html(response.form);
						}else{
							$('#tb_modal').html(response.form);
							tb_show(response.page_title, '#TB_inline?inlineId=tb_modal&width='+args.tb_width+'&height='+args.tb_height);
						}

						if(!wpjam_page_setting.bulk){
							var params	= {};

							if(wpjam_page_setting.plugin_page){
								params.action	= args.list_action;
							}else{
								params.modal_action	= args.list_action;
							}

							if(args.list_action != 'add' && args.id){
								params.id	= args.id;
							}

							if(action_data){
								params.data	= encodeURIComponent(action_data);
							}

							window.history.replaceState(null, null, $.wpjam_admin_url(params));
						}
					}else if(action_type == 'direct'){
						if(response_type == 'append'){
							$('#tb_modal').html(response.data);
							tb_show(response.page_title, '#TB_inline?inlineId=tb_modal&width='+args.tb_width+'&height='+args.tb_height);
						}else if(response_type == 'list'){
							$('div.list-table').html(response.data);
						}else if(response_type == 'add' || response_type == 'duplicate'){
							$.wpjam_list_table_create_item(response);
						}else if(response_type == 'up' || response_type == 'down'){
							tr_id	= $.wpjam_list_table_tr_id(args.id);

							if(response_type == 'up'){
								tr_next	= $.wpjam_list_table_tr_id(args.next);
								$(item_prefix+tr_next).insertAfter(item_prefix+tr_id);
							}else{
								tr_prev	= $.wpjam_list_table_tr_id(args.prev);
								$(item_prefix+tr_id).insertAfter(item_prefix+tr_prev);
							}
							$.wpjam_list_table_update_item(response, '#eeffff');
							$.wpjam_list_table_sortable_init();
						}else if(response_type == 'move'){
							$.wpjam_list_table_update_item(response, '#eeffee');
							$('.wp-list-table > tbody').sortable('enable');
							$.wpjam_list_table_sortable_init();
						}else if(response_type == 'delete'){
							$.wpjam_list_table_delete_item(response);
						}else if(response_type == 'redirect'){
							if(response.url){
								window.location.href	= response.url;
							}else{
								window.location.reload();
							}
						}else{
							$.wpjam_list_table_update_item(response);
						}

						// if(response.errmsg && $.inArray(response_type, ['append', 'up', 'down', 'move']) == -1){
						// 	$('.list-table-notice').removeClass('notice-error').addClass('notice-success').html('<p>'+response.errmsg+'</p>').fadeIn(400);
						// }
					}else if(action_type == 'submit'){
						$('.spinner').removeClass('is-active');

						if(response_type == 'append'){
							var scrollto = $('#TB_ajaxContent')[0].scrollHeight;
							$('#TB_ajaxContent').scrollTop(scrollto-50);

							$('.response').html(response.data).fadeIn(400);
						}else if(response_type == 'delete'){
							tb_remove();

							$('.list-table-notice').wpjam_notice(response.errmsg, 'success');

							$.wpjam_list_table_delete_item(response);
						}else if(response_type == 'redirect'){
							if(response.url){
								window.location.href	= response.url;
							}else{
								window.location.reload();
							}
						}else{
							$('#TB_ajaxWindowTitle').html(response.page_title);
							$('#TB_ajaxContent').html(response.form);
							$('#TB_ajaxContent').scrollTop(0);

							if(response_type == 'list'){
								$('div.list-table').html(response.data);
							}else if(response_type == 'form'){
								// 
							}else if(response_type == 'add' || response_type == 'duplicate'){
								$.wpjam_list_table_create_item(response);
							}else{
								$.wpjam_list_table_update_item(response);
							}

							if(response.errmsg){
								$('.list-table-action-notice').wpjam_notice(response.errmsg, 'success');
							}
						}

						if(response_type != 'form' && response.next_action){
							var params	= {};

							if(wpjam_page_setting.plugin_page){
								params.action	= args.next_action;
							}else{
								params.modal_action	= args.next_action;
							}

							if(args.next_action != 'add' && args.id){
								params.id	= args.id;
							}

							if(action_data){
								params.data	= encodeURIComponent(action_data);
							}

							window.history.replaceState(null, null, $.wpjam_admin_url(params));
						}
					}

					response.list_action		= list_action;
					response.list_action_type	= action_type;

					$('body').trigger('list_table_action_success', response);

					if($('#TB_ajaxContent').length > 0){
						tb_position();
					}
				}
			});

			return false;
		},

		wpjam_list_table_create_item: function(response){
			if(response.data){
				if(response.layout == 'calendar'){
					$.wpjam_list_table_update_calendar_date(response, '#ffffee');
				}else{
					if(response.last){
						$('.wp-list-table > tbody tr').last().after(response.data);
						$('.wp-list-table > tbody tr').last().hide().css('background-color','#ffffee').fadeIn(400);

						$(document).scrollTop($('.wp-list-table > tbody tr').last().offset().top-100);
					}else{
						$('.wp-list-table > tbody tr').first().before(response.data);
						$('.wp-list-table > tbody tr').first().hide().css('background-color','#ffffee').fadeIn(400);
					}

					$('.no-items').remove();
				}
			}
		},

		wpjam_list_table_update_item: function(response, bg_color){
			bg_color	= bg_color || '#ffffee';

			if(response.layout == 'calendar'){
				$.wpjam_list_table_update_calendar_date(response, bg_color);
			}else{
				if(response.bulk){
					$.each(response.data, function(id, item){
						bg_color 	= bg_color == '#ffffdd' ? '#ffffee' : '#ffffdd';
						$.wpjam_list_table_update_item({id: id, data: item, bulk: false}, bg_color);
					});
				}else{
					if(response.id){
						let tr_id	= $.wpjam_list_table_tr_id(response.id);
						let prefix	= wpjam_page_setting.item_prefix;

						if(response.data){
							$(prefix+tr_id).last().after('<span class="edit-'+tr_id+'"></span>');
							$(prefix+tr_id).remove();
							$('.edit-'+tr_id).before(response.data).remove();
						}

						$(prefix+tr_id).hide().css('background-color', bg_color).fadeIn(400);
					}
				}
			}
		},

		wpjam_list_table_update_calendar_date(response, bg_color){
			$.each(response.data, function(date, item){
				bg_color 	= bg_color == '#ffffdd' ? '#ffffee' : '#ffffdd';
				$('td#date_'+date).html(item).css('background-color', bg_color);
			});
		},

		wpjam_list_table_delete_item: function(response){
			if(response.layout == 'calendar'){
				$.wpjam_list_table_update_calendar_date(response, '#ffffee');
			}else{
				if(response.bulk){
					$.each(response.ids, function(index, id){
						$.wpjam_list_table_delete_item({id: id, bulk: false});
					});
				}else{
					let prefix	= wpjam_page_setting.item_prefix;
					let tr_id	= $.wpjam_list_table_tr_id(response.id);
					$(prefix+tr_id).css('background-color', 'red').fadeOut(400, function(){ $(this).remove();});
				}
			}
		},

		wpjam_list_table_tr_id: function(id){
			if(typeof(id) == "string"){
				return id.replace(/\./g, '-');
			}else{
				return id;
			}
		},

		wpjam_list_table_left_action:function(pushState){
			if($('#wpjam_left_keys').length > 0){
				let left_keys	= JSON.parse($('#wpjam_left_keys').val());

				$.each(left_keys, function(index, left_key){
					delete wpjam_page_setting.params[left_key];
				});
			}

			$.wpjam_list_table_action({
				list_action_type:	'left',
				data:				$.param(wpjam_page_setting.params)
			});

			if(pushState){
				let push_url = $.wpjam_admin_url(wpjam_page_setting.params, true);

				if(window.location.href != push_url){
					window.history.pushState(null, null, push_url);
				}
			}

			return false;
		},

		wpjam_list_table_left_pagination:function(left_paged){
			wpjam_page_setting.params.left_paged	= left_paged;

			return $.wpjam_list_table_left_action(true);
		},

		wpjam_list_table_query_items: function(pushState){
			$.wpjam_list_table_action({
				list_action_type:	'list',
				_ajax_nonce:		$('#_wpnonce').val(),
				data:				$.param(wpjam_page_setting.params)
			});

			if(pushState){
				let push_url = $.wpjam_admin_url(wpjam_page_setting.params, true);

				if(window.location.href != push_url){
					window.history.pushState(null, null, push_url);
				}
			}

			return false;
		},

		wpjam_list_table_filter_action: function(params){
			wpjam_page_setting.params	= {};

			$.each(params, function(index, param){
				if($.inArray(param.name, ['page', 'tab', 's', 'paged', '_wp_http_referer', '_wpnonce', 'action', 'action2']) == -1){
					wpjam_page_setting.params[param.name]	= param.value;
				}
			});

			return $.wpjam_list_table_query_items(true);
		},

		wpjam_list_table_search_action: function(){
			wpjam_page_setting.params	= {s:$('#wpjam-search-input').val()};

			return $.wpjam_list_table_query_items(true);
		},

		wpjam_list_table_sortable_init: function(){
			let items = $('.wp-list-table > tbody').sortable('option', 'items');

			$('.wp-list-table > tbody .up').show();
			$('.wp-list-table > tbody .down').show();
			$('.wp-list-table > tbody '+items).first().find('.up').hide();
			$('.wp-list-table > tbody '+items).last().find('.down').hide();
		},

		wpjam_list_table_sortable: function(items){
			items	= items || ' > tr';

			$('.wp-list-table > tbody').sortable({
				items:		items,
				axis:		'y',
				containment:'.wp-list-table',
				cursor:		'move',
				handle:		'.list-table-move-action',

				create: function(e, ui){
					if($('.wp-list-table > tbody '+items).length < 2){
						$('.wp-list-table > tbody .move').hide();
					}

					$.wpjam_list_table_sortable_init();
				},

				start: function(e, ui){
					$('.wp-list-table > tbody tr').css('background-color', '');
					ui.placeholder.height(ui.item.height()).css({'visibility':'visible'});
				},

				helper: function(e, ui) {
					var children = ui.children();
					for (var i=0; i<children.length; i++){
						var selector = $(children[i]);
						selector.width( selector.width() );
					};
					return ui;
				},

				update:		function(e, ui) {
					$(this).sortable('disable');
					// $(this).sortable('serialize');
					// $(this).sortable('toArray');

					var handle	= ui.item.find('.row-actions .move a');
					var data	= handle.data('data');
					data		= data ? data + '&type=drag' : 'type=drag';

					var	next	= ui.item.next().find('.ui-sortable-handle');
					var	prev	= ui.item.prev().find('.ui-sortable-handle');

					if(next.length > 0) {
						data	= data + '&next='+next.data('id');
					}else{
						data	= data + '&next=0';	// 最后
					}

					if(prev.length > 0) {
						data	= data + '&prev='+prev.data('id');
					}else{
						data	= data + '&prev=0';	// 最前
					}

					$.wpjam_list_table_action({
						list_action_type:	'direct',
						list_action:		'move',
						id:					handle.data('id'),
						data:				data,
						_ajax_nonce: 		handle.data('nonce')
					});
				}
			});
		},

		wpjam_list_table_pagination:function(paged){
			$('#current-page-selector').val(paged);
			wpjam_page_setting.params.paged	= paged;

			return $.wpjam_list_table_query_items(true);
		},

		wpjam_list_table_bulk_action: function(active_element_id){
			if(active_element_id == 'doaction'){
				var bulk_action	= $('select#bulk-action-selector-top').val();
				var bulk_option	= $('select#bulk-action-selector-top').find("option:selected");
			}else if(active_element_id == 'doaction2'){
				var bulk_action	= $('select#bulk-action-selector-bottom').val();
				var bulk_option	= $('select#bulk-action-selector-bottom').find("option:selected");
			}else{
				return false;
			}

			if(bulk_action == '-1'){
				alert('请选择要进行的批量操作！');
				return false;
			}

			var ids	= new Array();

			$('tbody .check-column input[type="checkbox"]:checked').each(function(index, element){
				ids.push($(this).val());
			});

			if(ids.length == 0){
				alert('请至少选择一项！');
				return false;
			}

			if(bulk_option.data('confirm')){
				if(confirm('确定要'+bulk_option.text()+'吗?') == false){
					return false;
				}
			}

			if(bulk_option.data('action')){
				$.wpjam_list_table_action({
					bulk:				true,
					ids:				ids,
					list_action_type:	bulk_option.data('direct') ? 'direct' : 'form',
					list_action:		bulk_action,
					data:				bulk_option.data('data'),
					_ajax_nonce: 		bulk_option.data('nonce')
				});
				return false;
			}
		},

		wpjam_page_action: function (args){
			let action_type		= args.page_action_type;
			let page_action		= args.page_action;
			let action_title	= args.action_title;
			let action_data		= args.data;

			args.action			= 'wpjam-page-action';
			args.data			= $.wpjam_append_query_data(args.data);
			args.screen_id		= wpjam_page_setting.screen_id;
			args.plugin_page	= wpjam_page_setting.plugin_page;
			args.current_tab	= wpjam_page_setting.current_tab;

			if(action_type == 'submit'){
				$('input#page_submit').prop('disabled', true);
				$('.spinner').addClass('is-active');
			}else{
				$("body").append("<div id='TB_load'><img src='"+imgLoader.src+"' width='208' /></div>");
				$('#TB_load').show();
			}

			$.post(ajaxurl, args, function(data, status){
				var response	= (typeof data == 'object') ? data : JSON.parse(data);

				if($('.page-notice').length < 1){
					$('hr.wp-header-end').after('<div class="page-notice notice is-dismissible hidden"></div>');
				}

				if(response.errcode != 0){
					if(action_type == 'submit'){
						$('.spinner').removeClass('is-active');
						$('.page-action-notice').wpjam_notice(action_title+'失败：'+response.errmsg, 'error');
					}else{
						$('#TB_load').remove();
						alert(response.errmsg);
					}
				}else{
					var response_type	= response.type;

					if(action_type == 'submit'){
						$('input#page_submit').prop('disabled', false);

						$('.spinner').removeClass('is-active');
						$('.response').hide();

						if(response_type == 'append'){
							if($('#TB_ajaxContent').length > 0){
								var scrollto = $('#TB_ajaxContent')[0].scrollHeight;
							}

							$('.response').html($('.response').html()+response.data);
							$('.response').fadeIn(400);

							if($('#TB_ajaxContent').length > 0){
								$('#TB_ajaxContent').scrollTop(scrollto);
							}

							if(response.errmsg){
								$('.page-action-notice').wpjam_notice(response.errmsg, 'info');
							}
						}else if(response_type == 'redirect'){
							if(response.url){
								window.location.href	= response.url;
							}else{
								window.location.reload();
							}
						}else{
							if($('#TB_ajaxContent').length > 0){
								$('#TB_ajaxContent').scrollTop(0);
							}

							if(('#wpjam_form').length > 0){
								if(response.form){
									$('.page-action-notice').remove();
									$('#wpjam_form').html(response.form);
								}
							}

							if(response.errmsg){
								$('.page-action-notice').wpjam_notice(response.errmsg, 'info');
							}else{
								$('.page-action-notice').wpjam_notice(action_title+'成功', 'success');
							}
						}

						if(response.done == 0){
							setTimeout(function(){
								$.wpjam_page_action({
									page_action_type:	'submit',
									data: 				response.args,
									page_action:		page_action,
									action_title:		action_title,
									_ajax_nonce:		args._ajax_nonce
								});
							}, 400);
						}
					}else if(action_type == 'form'){
						$('#TB_load').remove();

						if(response.form){
							$('#tb_modal').html(response.form);
						}else if(response.data){
							$('#tb_modal').html(response.data);
						}else{
							alert('服务端未返回表单数据');
						}

						args.tb_width	= args.tb_width || 720;
						args.tb_height	= args.tb_height || 200;
						tb_show(action_title, '#TB_inline?inlineId=tb_modal&width='+args.tb_width+'&height='+args.tb_height);
					}else{
						if(response_type == 'redirect'){
							if(response.url){
								window.location.href	= response.url;
							}else{
								window.location.reload();
							}
						}else{
							$('#TB_load').remove();

							if(response.errmsg){
								$('.page-notice').wpjam_notice(response.errmsg, 'success');
							}
						}
					}

					if($('#TB_ajaxContent').length > 0){
						tb_position();
					}

					response.page_action		= page_action;
					response.page_action_type	= action_type;

					$('body').trigger('page_action_success', response);
				}
			});

			return false;
		},

		wpjam_option_action: function(args){
			let action_data	= args.data;

			args.action			= 'wpjam-option-action';
			args.data			= $.wpjam_append_query_data(args.data);
			args.plugin_page	= wpjam_page_setting.plugin_page;
			args.current_tab	= wpjam_page_setting.current_tab;
			args.screen_id		= wpjam_page_setting.screen_id;

			$('.spinner').addClass('is-active');

			$.post(ajaxurl, args, function(data, status){
				let response	= (typeof data == 'object') ? data : JSON.parse(data);

				if($('.option-notice').length < 1){
					$('hr.wp-header-end').after('<div class="option-notice notice is-dismissible hidden"></div>');
				}

				if(response.errcode != 0){
					$('.spinner').removeClass('is-active');

					if(args.option_action == 'reset'){
						$('.option-notice').wpjam_notice('重置失败：'+response.errmsg, 'error');
					}else{
						$('.option-notice').wpjam_notice('保存失败：'+response.errmsg, 'error');
					}
				}else{
					let notice_msg	= '';

					if(response.errmsg){
						notice_msg	= response.errmsg;
					}else{
						if(args.option_action == 'reset'){
							notice_msg	= '设置已重置。';
						}else{
							notice_msg	= '设置已保存。';
						}
					}

					$('.spinner').removeClass('is-active');
					$('.option-notice').wpjam_notice(notice_msg, 'success');

					$('body').trigger('option_action_success', response);

					if(args.option_action == 'reset'){
						window.location.reload();
					}
				}
			});

			return false;
		},

		wpjam_append_query_data(data){
			if(!$.isEmptyObject(wpjam_page_setting.query_data)){
				if(data && typeof(data) != 'undefined'){
					$.each(data.split('&'), function(){
						let query = this.split('=');

						if(wpjam_page_setting.query_data.hasOwnProperty(query[0])){
							wpjam_page_setting.query_data[query[0]]	= query[1];
						}
					});

					return $.param(wpjam_page_setting.query_data)+'&'+data;
				}else{
					return $.param(wpjam_page_setting.query_data);
				}
			}else{
				return data;
			}
		},

		wpjam_admin_url(params, base){
			base	= base || false;
			params	= params || {};

			if(base){
				if(!$.isEmptyObject(wpjam_page_setting.query_data)){
					params	= $.extend({}, wpjam_page_setting.query_data, params);
				}
			}

			var query_params = {};

			if(window.location.search){
				$.each(window.location.search.substring(1).split('&'), function(){
					var query = this.split('=');

					if(base){
						if($.inArray(query[0], ['page', 'tab', 'post_type']) != -1){
							query_params[query[0]]	= query[1];
						}
					}else{
						if($.inArray(query[0], ['action', 'modal_action', 'id', 'data']) == -1){
							query_params[query[0]]	= query[1];
						}
					}
				});
			}

			params	= $.extend({}, query_params, params);

			var location_query = $.param(params);

			if(location_query){
				location_query	= '?'+decodeURIComponent(location_query);
			}

			return window.location.origin + window.location.pathname + location_query;
		}
	});

	window.tb_position	= function(){

		var tbWindow	= $('#TB_window');

		if(tbWindow.length){

			var tbIframeContent	= $('#TB_iframeContent');
			var tbAjaxContent	= $('#TB_ajaxContent');

			var win_width	= $(window).width();
			var	win_height	= $(window).height();

			if( tbIframeContent.length ){
				var H, W;

				if( 804 < win_width ) {
					W	= 784;
					H	= ( 720 < win_height) ? 600 : win_height - 120;
					H	= (TB_HEIGHT < H)?TB_HEIGHT:H;
				}else{
					W	= win_width - 20;
					H	= win_height - 80;;
				}

				tbIframeContent.width( W ).height( H );
				tbWindow.width( W );
				tbWindow.css({
					marginLeft: '-' + parseInt( ( W / 2 ), 10 ) + 'px',
					marginTop:	'-' + parseInt( ( H / 2 ), 10 ) + 'px',
				});
			}else if(tbAjaxContent.length){
				var H, W;

				if( 804 < win_width ) {
					win_height	= win_height-100;
					win_height	= Math.min(win_height, 700);

					W	= Math.min(TB_WIDTH, 720);
				}else{
					win_height	= win_height - 80;

					win_width	= win_width - 20;
					W	= Math.min(TB_WIDTH, win_width);
				}

				if(tbWindow.css('visibility') != 'hidden'){
					H	= Math.max(tbAjaxContent.prop('scrollHeight')+40, TB_HEIGHT);
				}else{
					H	= TB_HEIGHT;
				}

				H	= Math.min(H, win_height);

				tbAjaxContent.width(W-50).height(H-57);

				tbWindow.width(W).height(H);

				tbWindow.css({
					marginLeft: '-' + parseInt( ( W / 2 ), 10 ) + 'px',
					marginTop:	'-' + parseInt( ( H / 2 ), 10 ) + 'px',
				});
			}else{
				// 默认图片效果
				tbWindow.css({marginLeft: '-' + parseInt((TB_WIDTH / 2),10) + 'px', width: TB_WIDTH + 'px'});
				tbWindow.css({marginTop: '-' + parseInt((TB_HEIGHT / 2),10) + 'px'});
			}
		}
	};

	let old_tb_remove	= window.tb_remove;
	window.tb_remove	= function(){
		old_tb_remove();

		if($('#TB_ajaxContent #wpjam_button_delete_notice').length){
			$('#TB_ajaxContent #wpjam_button_delete_notice').trigger('click');
		}

		if(wpjam_page_setting.function == 'list_table' || $.inArray(wpjam_page_setting.screen_base, ['edit', 'upload', 'edit-tags', 'users']) != -1){
			window.history.replaceState(null, null, $.wpjam_admin_url());
		}
	}

	let old_send_to_editor	= window.send_to_editor;
	window.send_to_editor = function(html){
		let old_tb_remove	= window.tb_remove;
		window.tb_remove	= function(){}
		old_send_to_editor(html);
		window.tb_remove	= old_tb_remove
	};

	$(window).resize(function(){
		tb_position();
	});

	$('body').on('click', '.is-dismissible .notice-dismiss', function(){
		if($(this).prev('#wpjam_button_delete_notice').length){
			$(this).prev('#wpjam_button_delete_notice').trigger('click');
		}
	});

	window.onpopstate = function(event){
		if(wpjam_page_setting.function == 'list_table'){
			var params_pairs	= window.location.search.substring(1).split('&');
			var params			= {};

			$.each(params_pairs, function() { 
				if($.inArray(this.split('=')[0], ['page', 'tab', '_wp_http_referer', '_wpnonce', 'action', 'action2']) == -1){
					params[this.split('=')[0]] = this.split('=')[1];
				}
			});

			wpjam_page_setting.params	= params;

			return $.wpjam_list_table_query_items(false);
		}
	};

	if(typeof(wpjam_list_args) != 'undefined' && !$.isEmptyObject(wpjam_list_args)){
		if(wpjam_list_args.current_action){
			$.wpjam_list_table_action(wpjam_list_args.current_action);
		}

		if(wpjam_list_args.sortable){
			$.wpjam_list_table_sortable(wpjam_list_args.sortable.items);
		}
	}

	$.wpjam_list_table_clear_tablenav_top();

	$('body').on('submit', '#list_table_action_form', function(e){
		e.preventDefault();

		var list_action = $(this).data('action');

		var args	= {
			list_action_type:	'submit',
			bulk: 				$(this).data('bulk'),
			data: 				$(this).serialize(),
			defaults:			$(this).data('data'),
			// next_action:		$(this).data('next'),
			_ajax_nonce: 		$(this).data('nonce'),
			list_action:		list_action
		};

		if($(this).data('next')){
			wpjam_page_setting.action_flows = wpjam_page_setting.action_flows || new Array();
			wpjam_page_setting.action_flows.push(list_action);
		}

		if($(this).data('bulk')){
			args.ids	= $(this).data('ids');
		}else{
			args.id		= $(this).data('id');
		}

		$.wpjam_list_table_action(args);
	});

	$('body').on('click', '.list-table-action', function(){
		if($(this).data('confirm')){
			if(confirm('确定要'+$(this).attr('title')+'吗?') == false){
				return false;
			}
		}

		var action_type	= $(this).data('direct') ? 'direct' : 'form';
		var list_action	= $(this).data('action');
		var item_prefix	= wpjam_page_setting.item_prefix;

		var id		= $(this).data('id');
		var data	= $(this).data('data');

		var	next	= '';
		var	prev	= '';

		var tr_id	= $.wpjam_list_table_tr_id(id);

		if(list_action == 'up'){
			next	= $(item_prefix+tr_id).prev().find('.ui-sortable-handle');

			if(next.length > 0){
				next	= next.data('id');
				data	= data ? data + '&' : '';
				data	= data + 'type=up&next='+next;
			}else{
				alert('已经是第一个了，不可上移了。');
				return false;
			}
		}else if(list_action == 'down'){
			prev	= $(item_prefix+tr_id).next().find('.ui-sortable-handle');

			if(prev.length > 0){
				prev	= prev.data('id');
				data	= data ? data + '&' : '';
				data	= data + 'type=up&prev='+prev;
			}else{
				alert('已经最后一个了，不可下移了。');
				return false;
			}
		}

		$.wpjam_list_table_action({
			list_action_type:	action_type,
			list_action:		list_action,
			id:					id,
			data:				data,
			prev:				prev,
			next:				next,
			tb_width:			$(this).data('tb_width'),
			tb_height:			$(this).data('tb_height'),
			_ajax_nonce: 		$(this).data('nonce')
		});

		$(this).blur();
	});

	// From mdn: On Mac, elements that aren't text input elements tend not to get focus assigned to them.
	$('body').on('click', '#list_table_form input[type=submit]', function(e){
		$('input[type=submit]', $(this).parents('form')).removeClass('focus');
		$(this).addClass('focus');
	});

	$('body').on('submit', '#list_table_form', function(e){
		console.log(e.target.current)

		var active_element_id	= $(document.activeElement).attr('id');

		if(typeof(active_element_id) == 'undefined'){
			active_element_id	= $('#list_table_form input[type=submit].focus').attr('id');
		}

		if(active_element_id == 'export_action'){
			return;
		}else if(active_element_id == 'current-page-selector'){
			return $.wpjam_list_table_pagination($('#current-page-selector').val());
		}else if(active_element_id == 'search-submit' || active_element_id == 'wpjam-search-input'){
			return $.wpjam_list_table_search_action();
		}else if(active_element_id == 'filter_action'){
			return $.wpjam_list_table_filter_action($(this).serializeArray());
		}else if(active_element_id == 'doaction'){
			return $.wpjam_list_table_bulk_action(active_element_id);
		}else if(active_element_id == 'doaction2'){
			return $.wpjam_list_table_bulk_action(active_element_id);
		}
	});

	$('body').on('click', '#posts-filter input[type=submit]', function(e){
		$('input[type=submit]', $(this).parents('form')).removeClass('focus');
		$(this).addClass('focus');
	});

	$('body').on('submit', '#posts-filter', function(e){

		var active_element_id	= $(document.activeElement).attr('id');

		if(typeof(active_element_id) == 'undefined'){
			active_element_id	= $('#posts-filter input[type=submit].focus').attr('id');
		}

		if(active_element_id == 'doaction'){
			return $.wpjam_list_table_bulk_action(active_element_id);
		}else if(active_element_id == 'doaction2'){
			return $.wpjam_list_table_bulk_action(active_element_id);
		}
	});

	$('body').on('click', '.list-table-filter', function(){
		return $.wpjam_list_table_filter_action($(this).data('filter'));
	});

	$('body').on('click', '#list_table_form .first-page', function(){
		return $.wpjam_list_table_pagination(1);
	});

	$('body').on('click', '#list_table_form .prev-page', function(){
		return $.wpjam_list_table_pagination(parseInt($('#current-page-selector').val())-1);
	});

	$('body').on('click', '#list_table_form .next-page', function(){
		return $.wpjam_list_table_pagination(parseInt($('#current-page-selector').val())+1);
	});

	$('body').on('click', '#list_table_form .last-page', function(){
		return $.wpjam_list_table_pagination($('.total-pages').html().replace(/,/,''));
	});

	$('body').on('click', '#col-left .prev-page', function(){
		return $.wpjam_list_table_left_pagination($(this).data('left_paged'));
	});

	$('body').on('click', '#col-left .next-page', function(){
		return $.wpjam_list_table_left_pagination($(this).data('left_paged'));
	});

	$('body').on('click', '#col-left .left-pagination', function(){
		return $.wpjam_list_table_left_pagination(parseInt($('#left-current-page').val()));
	});

	$('body').on('click', '#list_table_form th.sorted, #list_table_form th.sortable', function(){
		wpjam_page_setting.params.orderby	= $(this).attr('id');
		wpjam_page_setting.params.order		= $(this).hasClass('asc') ? 'desc' : 'asc';
		wpjam_page_setting.params.paged		= 1;

		return $.wpjam_list_table_query_items(true);
	});

	$('body').on('click', '.wpjam-button', function(e){
		e.preventDefault();

		if($(this).data('confirm')){
			if(confirm('确定要'+$(this).data('title')+'吗?') == false){
				return false;
			}
		}

		$('.response').html('');

		$.wpjam_page_action({
			page_action_type:	$(this).data('direct') ? 'direct' : 'form',
			data:				$(this).data('data'),
			form_data:			$(this).parents('form').serialize(),
			tb_width:			$(this).data('tb_width'),
			tb_height:			$(this).data('tb_height'),
			page_action:		$(this).data('action'),
			action_title:		$(this).data('title'),
			_ajax_nonce:		$(this).data('nonce')
		});
	});

	$('body').on('submit', '#wpjam_form', function(e){
		e.preventDefault();

		$('.response').html('');

		$.wpjam_page_action({
			page_action_type:	'submit',
			data: 				$(this).serialize(),
			page_action:		$(this).data('action'),
			action_title:		$(this).data('title'),
			_ajax_nonce:		$(this).data('nonce')
		});
	});

	$('body').on('submit', '#wpjam_option', function(e){
		e.preventDefault();

		let option_action	= $(document.activeElement).attr('id');

		if(option_action == 'reset'){
			if(confirm('确定要重置吗?') == false){
				return false;
			}
		}

		$.wpjam_option_action({
			option_action:	option_action,
			data:			$(this).serialize()
		});
	});

	$('img.weixin_img').each(function(index, element){
		// console.log($(this).attr('src'));
		console.log(element);
		console.log($(this).data('url'));
		console.log($(element).data('url'));
		$(element).before(show_wx_img($(element).attr('src'), $(element).attr('width'), $(element).attr('height'), $(element).data('url'))).remove();
	});

	$('body').on('list_table_loaded', function(e){
		$('body img.weixin_img').each(function(index, element){
			$(this).before(show_wx_img($(this).attr('src'), $(this).attr('width'), $(this).attr('height'), $(this).data('url'))).remove();
		});
	});

	$('body').on('list_table_action_success', function(e,response){
		$('body img.weixin_img').each(function(index, element){
			$(this).before(show_wx_img($(this).attr('src'), $(this).attr('width'), $(this).attr('height'), $(this).data('url'))).remove();
		});
	});

	if(wpjam_page_setting.screen_base == 'edit'){
		var wp_inline_edit_function = inlineEditPost.edit;

		inlineEditPost.edit = function(id){

			wp_inline_edit_function.apply(this, arguments);

			if(typeof(id) === 'object'){
				id = this.getId(id);
			}

			if(id > 0){
				var edit_row	= $('#edit-' + id);

				if($('#inline_'+id+' div.post_excerpt').length){
					var excerpt		= $('#inline_'+id+' div.post_excerpt').text();
					$(':input[name="the_excerpt"]', edit_row).val(excerpt);
				}
			}
		}
	}
});

window.frame_imgs = [];
function show_wx_img(src, iframe_width, iframe_height, url) {
	iframe_width	= iframe_width || 0;
	iframe_height	= iframe_height || 0;
	url				= url || 0;

	if(iframe_width){
		var img_html	= '<img id="img" src=\'' + src + '?' + Math.random() + '\' />';
	}else{
		var img_html	= '<img id="img" style="max-width:100%;" src=\'' + src + '?' + Math.random() + '\' />';
	}

	if(url){
		img_html	= '<a href="'+url+'" target="_blank">'+img_html+'</a>';
	}

	var frame_id		= 'frameimg' + Math.random();

	if(iframe_width){
		window.frame_imgs[frame_id] = '<body style="margin:0;padding:0;">'+img_html+'<script>window.onload = function() {wx_iframe=parent.document.getElementById(\'' + frame_id + '\'); wx_img = document.getElementById(\'img\'); iframe_width=wx_iframe.width; iframe_height=wx_iframe.height; img_width = wx_img.width; img_height = wx_img.height; if((img_width/img_height)>(iframe_width/iframe_height)){ wx_img.style.height=\'100%\'; img_width=Math.ceil(iframe_height/img_height*img_width); wx_img.style.marginLeft=(iframe_width - img_width) / 2+\'px\'; }else{ wx_img.style.width=\'100%\'; img_height=Math.ceil(iframe_width/img_width*img_height); wx_img.style.marginTop=(iframe_height - img_height) / 2+\'px\'; } }<' + '/script></body>';

		return '<iframe id="' + frame_id + '" src="javascript:parent.frame_imgs[\''+frame_id+'\'];" width="'+iframe_width+'" height="'+iframe_height+'" frameBorder="0" scrolling="no"></iframe>';
	}else{
		window.frame_imgs[frame_id] = '<body style="margin:0;padding:0;">'+img_html+'<script>window.onload = function() { parent.document.getElementById(\'' + frame_id + '\').height = document.getElementById(\'img\').height+\'px\'; }<' + '/script></body>';

		return '<iframe id="' + frame_id + '" src="javascript:parent.frame_imgs[\''+frame_id+'\'];" width="100%" frameBorder="0" scrolling="no"></iframe>';
	}
}