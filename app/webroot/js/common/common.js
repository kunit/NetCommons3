/**
 * 共通 js
 *
 * @copyright     Copyright 2012, NetCommons Project
 * @package       webroot.js.main
 * @author        Noriko Arai,Ryuji Masukawa
 * @since         v 3.0.0.0
 * @license       http://www.netcommons.org/license.txt  NetCommons License
 */
;(function($) {
	$.Common ={
		zIndex : 2000,
		blockZIndex : 1000,

		pjaxCacheMapping : [],
		pjaxCacheUniqueId: 0,
		pjaxCachePointer : 0,
		pjaxCacheBackTargetId : null,
		pjaxPrevUrl : $._full_base_url,
		isPopstate : false,
		//pjaxMaxCacheLength : 40,

		// data-pjax属性の値を targetとして置換する。その際、リクエストしたURLに変更される。
		// data-ajax属性ならば、targetと入れ替える。
		// data-ajax-inner属性の値をtargetとして、targetにinnerHtmlを行う。
		//		data-pjax, data-pjax-inner, data-ajax, data-ajax-innerはいずれかを設定すること。
		// data-ajax-url:URL default：href属性から取得
		// data-ajax-type: post or get
		// data-ajax-serialize: default:false boolean　postリクエスト時のみformのpostでない場合でも、elの親elementにformがあればserializeしてdataにセットする。
		// data-ajax-data: postする場合のdataのhash配列を文字列に変換したものをセットすることにより、POSTのdataを送信することができる。
		// data-ajax-effect: 遷移時effect
		// data-ajax-confirm: メッセージをValueに設定すると確認ダイアログ表示
		// data-ajax-dialog: ダイアログとして表示する場合、true trueの場合、data-ajaxが指定dialogTopのid属性となる。
		// data-ajax-dialog-class: ダイアログとして表示した場合のダイアログクラス名
		// data-ajax-dialog-options: ダイアログとして表示した場合のダイアログオプション jquery dialogのoptionsをhash配列を文字列に変換したもの
		//		"position" : "mouse"指定があればマウス位置にダイアログ表示
		//      data-width:ajaxのレスポンスのtopノードにdata-widthがあれば、その広さでダイアログを表示する。
		//      data-height:ajaxのレスポンスのtopノードにdata-heightがあれば、その高さでダイアログを表示する。
		// カスタムイベント ajax:
		// ajax:beforeSend - Ajaxリクエスト前に呼ばれる。falseを返せば処理を中断する。
		// ajax:beforeSendのみ、return値(string or array)でURL及びdataの内容を上書き可。dataはマージ
		//							string url or array('url' => string, 'data' => array) or array(0 => url string, 1 => data array)
		// ajax:success - Ajaxリクエスト直後に呼ばれる。falseを返せば処理を中断する。
		//        @param Event ajax:success event object
		//        @param res ajax response
		//        @param Event parent event object
		// 例：$('form:first', this).on('ajax:beforeSuccess', function(e, res) {
		//	       e.preventDefault();
		//     });
		//
		// params：上記属性情報をパラメータによりセットする場合、使用
		// options：ajax時のoptions マージされる
		ajax : function(e, el, params, options, _confirm, _force_url) {
			var data_pjax, top, url, data = {}, input_type, type, params, is_form, ret, data_url, data_serialize, default_options;
			var target_pjax, confirm, is_pjax;
			var $el = $(el), buf_options = options;
			if(params != undefined && params != null) {
				target_pjax =  params['data-pjax'];
				confirm = (typeof _confirm == "undefined") ? params['data-ajax-confirm'] : _confirm;
				type = params['data-ajax-type'];
				data_url = params['data-ajax-url'];
				data_serialize = params['data-ajax-serialize'];
				data_data = params['data-ajax-data'];
			} else {
				target_pjax =  $el.attr("data-pjax");
				confirm = (typeof _confirm == "undefined") ? $el.attr("data-ajax-confirm") : _confirm;
				type = $el.attr("data-ajax-type");
				data_url = $el.attr("data-ajax-url");
				data_serialize = $el.attr("data-ajax-serialize");
				data_data = $el.attr("data-ajax-data");
			}
			is_pjax = target_pjax && $.support.pjax && !_force_url;

			if($el.hasClass('disable-lbl') && !_force_url) {
				if(e) e.preventDefault();
				return false;
			}
			if(confirm){
				var ok = __('Ok') ,cancel = __('Cancel');
				var default_dialog_params = {
					resizable: false,
					modal: true,
					position: [e.pageX - $(window).scrollLeft(), e.pageY - $(window).scrollTop()]
				}, _buttons = {}, dialog_params = new Object();
				_buttons[ok] = function(){
					$( this ).remove();
					$.Common.ajax(e, $el, params, options, false);
				};
				_buttons[cancel] = function(){
					$( this ).remove();
				};
				dialog_params = $.extend({buttons: _buttons}, default_dialog_params);
				$('<div></div>').html(confirm).dialog(dialog_params);
				if(e) e.preventDefault();
				return;
			}

			if($el.get(0)) {
				if($el.get(0).tagName.toLowerCase() == 'form') {
					input_type = 'POST';
					url = $el.attr('action');
					if(type == 'GET' || type == 'get') {
						// GETならばformの値をPOSTしない。
						data = {};
					} else {
						data = $el.serializeArray();
						var dataHash = new Object();
						$(data).each(function(){
							dataHash[this['name']] = this['value'];
						});
						data = dataHash;
					}
					is_form = true;
				} else {
					input_type = 'GET';
					url = $el.attr('href');
					data = {};
					is_form = false;
				}
			}

			if(_force_url) {
				url = _force_url;
			} else {
				url = data_url ? data_url : url;
			}
			type = (type == 'GET' || type == 'get' || type == 'POST' || type == 'post') ? type.toLowerCase() : input_type.toLowerCase();
			if(data_serialize == true && type == 'post' && Object.keys(data).length == 0) {
				if(!is_form) {
					var $form = $el.parents('form');
					if($form.get(0)) {
						// Postでデータが空でparentにFormタグがあれば、シリアライズしてセット
						// Token等を含める
						data = $form.serializeArray();
						var dataHash = new Object();
						$(data).each(function(){
							dataHash[this['name']] = this['value'];
						});
						data = dataHash;
					}
				}
			}
			ret = $.Common.fire('ajax:beforeSend', [url, data], $el, e);
			if (!ret) {
				if(e) e.preventDefault();
				return false
			}
			if(ret !== true) {
				if(typeof ret == "string") {
					url = ret;
				} else if(ret['url'] || ret['data']) {
					if((ret['url'] != undefined))
						url = ret['url'];
					if((ret['data'] != undefined)) {

					}
					data = $.extend({}, data, ret['data']);
				} else {
					url = ret[0];
					data = $.extend({}, data, ret[1]);
				}
			}
			if(data_data) {
				if(typeof data_data == 'string') {
					data_data = $.parseJSON(data_data);
				}
				data = $.extend({}, data, data_data);
			}

			default_options = {
				type: type,
				url: url,
				data: data,
				success: function(res, status, xhr){
					if (!$.Common.fire('ajax:success', [res, e, status, xhr], $el, e)) {
						return false;
					}
					if(is_pjax) {
						var container = $.Common._extractContainer(res, xhr, url);
						res = container.contents;
						var target = $(target_pjax);
						var target_id = target.attr('id');
						var unique_id = $.Common.uniqueId();
						var sub_target_id = null;
						if($.Common.pjaxCacheBackTargetId) {
							// ２つのブロックにおける画面遷移においては、進む、戻るで、操作中のブロック OR location先のブロックの
							// どちらか一方を戻す必要があるため、１つ前のlocation先のブロックIDも渡す。
							// こちらを渡さなければ、お知らせ(編集画面) => お知らせ(編集決定) => ブログ(編集画面) => ブログ(編集決定)
							// 戻る×４ => 進む ×４等でおかしくなる。
							sub_target_id = $.Common.pjaxCacheBackTargetId;
						}

						$.Common._pjaxCachePush(unique_id, target_id, sub_target_id, $.Common.pjaxCachePointer);
					}

					$.Common.ajaxSuccess(e, $el, res, params);

					if(is_pjax) {
						var hash = $.Common.parseURL(url).hash;

						var state = {
							id: unique_id
						};

						//if(cache_id != target_id) {
						//	target_pjax.attr('id', cache_id);
						//}

						$.Common.pjaxCacheUniqueId = unique_id;
						$.Common.pjaxCachePointer++;
						$.Common.pjaxCacheBackTargetId = target_id;

						//window.location.hash = hash;

						container.url =$.Common._convertPluginUrl(target, container.url);

						window.history.pushState(state, container.title, container.url);
	    				if (container.title) {
	    					document.title = container.title;
	    				}
	    				$.Common.pjaxPrevUrl = location.href;
	    				var hash_target = $(hash);
	    				if (hash_target.length) {
							$(window).scrollTop(hash_target.offset().top);
						}
	    			}
				},
				error: function(xhr, textStatus, errorThrown) {
					if(!target_pjax) {
						return;
					}
					// pjaxならば、header Locationではなく、「X-PJAX-Location」ヘッダーを返し、そのURLを見て、再度、pjaxを呼ぶように修正。
					// リダイレクト前のURLでリダイレクト後の画面が表示されてしまうため。
					if ( textStatus !== 'abort' ) {
						var pjaxUrl = xhr.getResponseHeader('X-PJAX-Location');
						if(pjaxUrl) {
							var re_url = new RegExp("^"+ $.Common.quote($._full_base_url) , 'i');
							var buf_url = pjaxUrl.replace(re_url, '');
							var redirect_params = new Object();

							if(pjaxUrl != buf_url) {
								pjaxUrl = $._base_url + buf_url;
							}

							redirect_params['data-ajax-type'] = 'GET';

							if(!pjaxUrl.match(/.*(#.*)/i)) {
								location.href.match(/.*(#.*)/i);
								redirect_params['data-ajax-url'] = pjaxUrl + RegExp.$1;
							} else {
								redirect_params['data-ajax-url'] = pjaxUrl;
							}
							redirect_params['data-pjax'] = target_pjax;
							if(buf_options) {
								buf_options['async'] = false;
							} else {
								buf_options = {async : false};
							}
							$.Common.ajax(e, $el, redirect_params, buf_options);

							return;
						}

						if(xhr.responseText) {
							var container = $.Common._extractContainer(xhr.responseText, xhr, url);
							var res = '<div>' + container.contents + '</div>';
							$.Common.ajaxSuccess(e, $el, res, params);
						}
					}
				}
			};
			if(options != undefined && options != null) {
				options = $.extend(default_options, options);
			} else {
				options = default_options;
			}

			if(target_pjax) {
				if(!$.support.pjax && (is_form || (options['type'] != 'post' && options['type'] != 'POST'))) {	// $el.attr('href') == url &&
					// pjax非対応ブラウザの場合
					if($el.attr('href') != url) {
						location.href = url;
					}
					return;
				}
				if(params != undefined && params != null) {
					params['data-ajax'] = params['data-pjax'];
					delete params['data-pjax'];
				} else {
					$el.attr('data-ajax', $el.attr('data-pjax'));
					$el.removeAttr('data-pjax');
				}

				if($.support.pjax) {
					options['beforeSend'] = function(xhr) {
						xhr.setRequestHeader('X-PJAX', 'true');
					};
				}
			}
			$.ajax(options);
			if(e) e.preventDefault();
		},
		ajaxSuccess : function(e, el, res, params) {
			var target,replace_target,effect;
			var buf_a, res_target, res_other_target, buf_res_target, effect_cnt = 0, effect_index = -1;
			var dialog_title, dialog_options, dialog, w, h;
			var $el = $(el);
			if (params) {
				target = params['data-ajax-inner'];
				replace_target = params['data-ajax'];
				effect = params['data-ajax-effect'];
				dialog = params['data-ajax-dialog'];
				dialog_class = params['data-ajax-dialog-class'];
				dialog_options = params['data-ajax-dialog-options'];
			} else if($el.get(0)) {
				target = $el.attr("data-ajax-inner");
				replace_target = $el.attr("data-ajax");
				effect = $el.attr("data-ajax-effect") ? $el.attr("data-ajax-effect") : null;
				dialog = $el.attr("data-ajax-dialog");
				dialog_class = $el.attr("data-ajax-dialog-class");
				dialog_options = $el.attr("data-ajax-dialog-options");
			} else {
				return false;
			}

			if (dialog == 'true' || dialog == true) {
				if(typeof dialog_options == 'string') {
					dialog_options = $.parseJSON(dialog_options);
					if(dialog_options['position'] && dialog_options['position'] == 'mouse') {
						dialog_options['position'] = [e.pageX - $(window).scrollLeft(), e.pageY - $(window).scrollTop()];
					}
				}

				if(dialog_options) {
					if(effect) {
						dialog_options['show'] = effect;
						dialog_options['hide'] = effect;
					}
					if(typeof dialog_options['resizable'] == 'undefined') {
						dialog_options['resizable'] = false;
					}
					dialog_options = $.extend({}, {zIndex: ++$.Common.zIndex}, dialog_options);
				}
				dialog = $(target);
				if(!dialog.get(0)) {
					dialog = $('<div id='+ target.slice(1) +' style="display:none;"></div>').appendTo($(document.body));
				}
				dialog.html(res);
				w = dialog.children(':first').attr('data-width');
				h = dialog.children(':first').attr('data-height');
				if(parseInt(w) > 0) {
					dialog_options['width'] = parseInt(w);
				}
				if(parseInt(h) > 0) {
					dialog_options['height'] = parseInt(h);
				}
				dialog.dialog(dialog_options);
				if(dialog_class) {
					dialog.parent().addClass(dialog_class);
				}
			} else if(target) {
				res_target = $(target);
				if(effect && $(target).css('display') != 'none') {
					$(target).hide(effect, function(){
						$(target).html(res);
						$(target).show(effect);
					});
				} else {
					$(target).html(res);
				}
			} else if(replace_target) {
				res_target = $();
				res_other_target = $();
				buf_res_target = $($.trim(res));
				buf_res_target.each(function(){
					if (this.nodeType == 1 && $(this).is(replace_target)) {
						res_target.push(this);
					} else {
						res_other_target.push(this);
					}
				});
				if(!res_target.get(0)) {
					res_target = res_other_target;
					res_other_target = $();
				}
				if(effect && res_target.get(0)) {
					$(replace_target).hide(effect, function(){
						$(res_target).css('display', 'none');
						$(replace_target).replaceWith(res_target);
						$(replace_target).after(res_other_target);
						$(res_target).show(effect);
					});
				} else {
					$(replace_target).replaceWith(res_target);
					$(replace_target).after(res_other_target);
				}
			}
			return res_target;
		},
		// 履歴
		onPjaxPopstate : function(e, loc_href, loc_hash) {
			var state = e.originalEvent.state;
			var direction = 'back';
			var cache = null;

			loc_href = (loc_href) ? loc_href : location.href;
			loc_hash = (loc_hash) ? loc_hash : location.hash;
			if($.Common.isPopstate) {
				setTimeout(function(){
					$.Common.onPjaxPopstate(e, loc_href, loc_hash);
				}, 300);
				return;
			}
			$.Common.isPopstate = true;
			var prev_url = $.Common.pjaxPrevUrl.replace(/#.*$/i,'').replace(/\//i,'');
			var current_url = loc_href.replace(/#.*$/i,'').replace(/\//i,'');
			if(prev_url == current_url) {
				$.Common.isPopstate = false;
				return true;
			}

			$.Common.pjaxPrevUrl = loc_href;

			if(state) {
				direction = ($.Common.pjaxCacheUniqueId < state.id) ? 'forward' : 'back';
			}
			if(direction == 'forward') {
				$.Common.pjaxCachePointer++;
			} else if($.Common.pjaxCachePointer > 0) {
				$.Common.pjaxCachePointer--;
			}

			if($.Common.pjaxCacheMapping[$.Common.pjaxCachePointer]) {
				cache = $.Common.pjaxCacheMapping[$.Common.pjaxCachePointer];
			}

			$.Common.pjaxCacheUniqueId = (state && state.id) ? state.id : 0;
			if(!cache) {
				if(loc_hash == null || $._current_url.replace(/\/$/,'').match('/' + loc_href + '$/i')) {
					window.location.replace(loc_href);
				} else {
					// F5をクリック後、戻るボタンで、強制的にURLをリロードしなければならないため
					window.location.replace(loc_href.replace(/.*(#.*)/i,''));
					var hash_target = $(loc_hash);
					if (hash_target.length) {
						$(window).scrollTop(hash_target.offset().top);
					}
				}
			} else {
				// 履歴から元に戻す
				var top = $('#' + cache.top_id);
				var top_id = cache.top_id;
				var contents = cache.contents;
				var url = cache.url;
				if(url && cache.sub_url) {
					if(url == $('#' + top_id).attr('data-ajax-url')) {
						url = cache.sub_url;
						top_id = cache.sub_top_id;
						contents = cache.sub_contents;
					}
				}

				if(direction == 'back' && $.Common.pjaxCacheMapping.length == $.Common.pjaxCachePointer + 1) {
					var target_id = top_id;
					var unique_id = $.Common.uniqueId();
					$.Common._pjaxCachePush(unique_id, target_id);
				}

				if(url) {
					// url指定があれば、再取得
					// WYSIWYGのような画面だと、キャッシュの画面からjavascriptを再度、実行しても同様の画面にはならないため再取得
					top.attr('data-ajax', '#' + top_id);
					top.removeAttr('data-pjax');
					top.removeAttr('data-ajax-inner');
					$.Common.ajax(e, top, null, {async : false}, false, url);
				} else {
					if (!$.Common.fire('ajax:success', [contents, e, status], top, e)) {
						return false
					}
					$.Common.ajaxSuccess(e, top, contents, {'data-ajax': '#' + top_id});
				}
				document.title = cache.title;


			}
			$.Common.isPopstate = false;
		},
		_extractContainer : function(data, xhr, url) {
			var obj = {};
			obj.url = url;

			// Attempt to parse response html into elements
  			if (/<html/i.test(data)) {
				var $head = $(parseHTML(data.match(/<head[^>]*>([\s\S.]*)<\/head>/i)[0]));
				var $body = $(parseHTML(data.match(/<body[^>]*>([\s\S.]*)<\/body>/i)[0]));
			} else {
				var $head = $body = $($.parseHTML(data, document, true));
			}

			// If response data is empty, return fast
			if ($body.length === 0)
				return obj;

			// If there's a <title> tag in the header, use it as
			// the page's title.
			obj.title = $.Common.findAll($head, 'title').last().text();

			obj.contents = data;

			// Trim any whitespace off the title
			if (obj.title) {
				obj.title = $.trim(obj.title);
				// title remove
				obj.contents = data.replace(/<title[^>]*>([\s\S.]*)<\/title>/i, '');
			}
			return obj;
		},

		_pjaxCachePush : function(id, top_id, sub_top_id, pointer) {
			var target = $('#' + top_id);
			var cache_contents = null;
			var cache_url = null;
			if(target.get(0)) {
				cache_url = $(target.get(0)).attr('data-ajax-url');
				if(!cache_url) {
					cache_contents = $.Common.outerHtml(target.get(0));
				}
			}
			if(sub_top_id) {
				var sub_target = $('#' + sub_top_id);
				var sub_cache_contents = null;
				var sub_cache_url = null;
				if(sub_target.get(0)) {
					sub_cache_url = $(sub_target.get(0)).attr('data-ajax-url');
					if(!sub_cache_url) {
						sub_cache_contents = $.Common.outerHtml(sub_target.get(0));
					}
				}
			}

			if(!pointer) {
				pointer = ($.Common.pjaxCacheMapping.length == 0) ? 0 : $.Common.pjaxCacheMapping.length;
			} else if($.Common.pjaxCacheMapping[pointer + 1]) {
				// pointer以降を削除(Forward分)
				var len = $.Common.pjaxCacheMapping.length;
				for(var i=pointer + 1 ; i < len; i++ ) {
					$.Common.pjaxCacheMapping.pop();
				}
			}

			// キャッシュの最大保存数を超えると削除。
			/* while ($.Common.pjaxCacheMapping.length > $.Common.pjaxMaxCacheLength) {
				$.Common.pjaxCacheMapping.shift();
				if($.Common.pjaxCachePointer - 1 >= $.Common.pjaxCacheMapping.length) {
					$.Common.pjaxCachePointer--;
				}
				if(pointer - 1 >= $.Common.pjaxCacheMapping.length) {
					pointer--;
				}

			}*/
			$.Common.pjaxCacheMapping[pointer] = {
				id: id,
				top_id: top_id,
				title: document.title,
				contents: cache_contents,
				url: cache_url,
				sub_top_id: sub_top_id,
				sub_contents: sub_cache_contents,
				sub_url: sub_cache_url
			};
		},
		//もし、ページ表示中で、ブロック一般画面への遷移ならば、ページのリンク先に変換する。
		// /xxxx/blocks/(block_id)/announcement/#_366 => /xxxx/#_366
		_convertPluginUrl : function(container, url) {
			if($('#container').get(0)) {	// container elementがあるかどうかでPage表示中か否かを判断
				// Page表示中
				var id = container.attr('id')
				var block_id = container.attr('data-block');
				var controller_action = container.attr('data-action');
				if(id && block_id && controller_action) {
					var chk_url = $._page_url + 'blocks/' + block_id + '/' + controller_action;
					if(url == chk_url || url == chk_url + '/') {
						return $._page_url;
					} else if(url == chk_url + '#' + id || url == chk_url + '/#' + id) {
						return $._page_url + '#' + id;
					}
				}
			}
			return url;
		},

		// targetのouterHtmlを取得
		outerHtml : function(target) {
			var buf = $(target).clone().html('');	// 内部scriptが実行する必要はないため、TopElementのみappend
			var buf_div = $("<div>").append(buf).html($(target).html());
			var ret = buf_div.html();
			buf.remove();
			buf_div.remove();
			return ret;
		},

		uniqueId : function () {
  			return (new Date).getTime()
		},
		parseURL : function(url) {
			var a = document.createElement('a')
			a.href = url
			return a
		},
		findAll : function(elems, selector) {
			return elems.filter(selector).add(elems.find(selector));
		},

		fire : function(type, args, content, parent_event) {
			var event = $.Event(type);	// , { relatedTarget: target }
			// mouse系変数マージ
			if(parent_event && parent_event.pageX) {
				event.pageX = parent_event.pageX;
				event.pageY = parent_event.pageY;
				event.clientX = parent_event.clientX;
				event.clientY = parent_event.clientY;
				event.screenX = parent_event.screenX;
				event.screenY = parent_event.screenY;
				event.offsetX = parent_event.offsetX;
				event.offsetY = parent_event.offsetY;
			}

			content.trigger(event, args);
			//return !event.isDefaultPrevented();
			if(typeof event.result == "undefined") {
				return !event.isDefaultPrevented();
			}
			return event.result;
		},

		// ブロックリロード処理
		reloadBlock : function(e, id, data, url) {
			var block = (typeof id == 'string') ? $('#' + id) : $(id),re, params = new Object();
			if(!url) {
				url = block.attr('data-ajax-url');
			}
			if($._block_type == 'blocks') {
				re = new RegExp("/active-blocks/", 'i');
				url = url.replace(re, "/" + $._block_type + "/");
			} else {
				re = new RegExp("/blocks/", 'i');
				url = url.replace(re, "/" + $._block_type + "/");
			}
			$.ajax({
				type: 'GET',
				url: url,
				data: data,
				success: function(res, status, xhr){
					block.replaceWith(res);
					//$.Common.ajaxSuccess(e, a, res);
				}
 			});
		},

		// block_id,controller_action名称からurl取得
		// TODO:廃止予定
		urlBlock : function(block_id, controller_action) {
			if(!block_id) {
				return $._page_url + $._block_type + '/' + controller_action;
			}
			var id = '_' + block_id;
			return $._page_url + $._block_type + '/' + block_id + '/' + controller_action + '/#' + id;
		},
		// javascript動的ロード
	    load : function(src, check, next, timeout) {
			check = new Function('return !!(' + check + ')');
			var script = document.createElement('script');
				script.src = src;
			document.body.appendChild(script);
			this.wait(check, next, timeout);
		},

		// 動的ロードの待機
		wait: function  (check, next, timeout) {
			timeout = (typeof timeout == "undefined") ? 10000 : timeout;
			if (!check()) {
				setTimeout(function() {
					if(timeout != undefined) {
						timeout = timeout - 100;
						if(timeout < 0) return;
					}
					if (!check()) setTimeout(arguments.callee, 100);
					else next();
				}, 100);
	 		} else
	 			next();
	 	},

	 	/**
	 	 * スタイルシートを追加する
	 	 * @param   css_name		CSSファイル名称
	 	 * @param   media			media名(MediaDescタイプ)
	 	 **/
 		loadLink: function (href, media){
 			var nLink = null;
 			for(var i=0; (nLink = document.getElementsByTagName("LINK")[i]); i++) {
 				if(nLink.href == href) {
 					//既に追加済
 					return true;
 				}
 			}
 			return this._loadLink(href, media);
 		},
 		_loadLink: function (href, media){
 			if(typeof document.createStyleSheet != 'undefined') {
 				document.createStyleSheet(href);
 				var oLinks = document.getElementsByTagName('LINK');
 				var nLink = oLinks[oLinks.length-1];
 			} else if(document.styleSheets){
 	  			var nLink=document.createElement('LINK');
 				nLink.rel="stylesheet";
 				nLink.type="text/css";
 				nLink.media= (media ? media : "screen");
 				nLink.href=href;
 				var oHEAD=document.getElementsByTagName('HEAD').item(0);
 				oHEAD.appendChild(nLink);
 			}
 		},

 		flash: function(str, pause) {
 			var mes = $('#flashMessage');
 			if(mes.get(0)) {
 				mes.remove();
 			}
 			mes = $(str).prependTo($("body"));
 			pause = (typeof pause == "undefined") ? 2000 : pause;
 			mes.delay(pause).animate({top: -1 * mes.outerHeight()}, 500, function() {
				mes.remove();
			});

 		},

 		// エラーダイアログ表示
 		showErrorDialog : function(error_str, params, target) {
			var ok = __('Ok');
			var body = '<div class="error-message">' + error_str + '</div>', _buttons = {}, pos;

			_buttons[ok] = function(){
				$( this ).remove();
			};
			var default_params = {
				resizable: false,
	            modal: true,
		        //position:,
		        buttons: _buttons
			}
			if(target) {
				pos = $(target).offset();
				default_params['position'] = [pos.left + 5 - $(window).scrollLeft() ,pos.top + 5 - $(window).scrollTop()];
			}
			params = $.extend({}, default_params);
			$('<div></div>').html(body).dialog(params);
		},

		// ・$this->Form-inputでselector指定した場合のアラート表示をform中のエレメントが変更されたら削除する
		// ・エラーがおこった最初のエレメントにフォーカスを移動する。
		// TODO:WYSIWYGには対応していない。
		closeAlert : function(input, alert) {
			var t = this, i =0,text;
			$.each( $(input), function() {
				var child = $(this), form, focus;
				if (child.is(':button,:submit,:reset,:image') || child.css('display') == 'none') {
					return;
				}
				if(i == 0) {
					form = child.parents('form:first');
					if(form.get(0)) {
						if (child.is(':hidden')) {
							setTimeout(function(){
								focus = $(':focus', form);
								if(!focus.get(0)) {
									if (child.is(':text,:password,textarea')) {
										child.select();
									} else {
										child.focus();
									}
								}
							}, 500);
						} else {
							focus = $(':focus', form);
							if(!focus.get(0)) {
								if (child.is(':text,:password,textarea')) {
									child.select();
								} else {
									child.focus();
								}
							}
						}
					}
				}
				child.addClass('error-input-message');
				if (child.is(':text,:password,textarea')) {
					child.bind("keydown focus", function(e){
						text = child.val();
					});
					child.bind("keyup change", function(e){
						var child = $(this);
						if(child.val() != text) {
							alert.remove();
							child.removeClass('error-input-message');
						}
					});
				} else if(child.is(':input')) {
					child.click(function(e){
						alert.remove();
					});
				}
				i++;
			});

		},
		alert: function(str) {
			str = this._message(str);
			if(str == "") return;
			alert(str);
		},
		confirm: function(str) {
			str = this._message(str);
			if(str == "") return;
			return confirm(str);
		},
		_message: function(str) {
			if(typeof str != 'string') return "";
			var re_html = new RegExp("^[\s\r\n]*<!DOCTYPE html", 'i');
			if(str.match(re_html)) {
				document.write(str);
				return '';
			} else {
				str = str.replace(/&lt;/ig,"<");
				str = str.replace(/&gt;/ig,">");
				str = str.replace(/\\n/ig,"\n");
				str = str.replace(/(<br(?:.|\s|\/)*?>)/ig,"\n");
				return str;
			}
		},
		escapeHTML: function(str) {
			return String(str).replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;").replace(/ /g, "&nbsp;");
		},

		unescapeHTML: function(str) {
			return String(str).replace(/&quot;/g,'"').replace(/&lt;/g,'<').replace(/&gt;/g,'>').replace(/&quot;/g, '"').replace(/&apos;/g, "'").replace(/&#039;/g, "'").replace(/&nbsp;/g, " ").replace(/&amp;/g,'&');
		},
		// jquery selector escape処理
		escapeSelector: function(str) {
			return str.replace(/([#;&,\.\+\*\~':"\!\^$\[\]\(\)=>\|])/g, "\\$1");
		},
		/* 正規表現のエスケープ */
		quote: function (str){
		    return str.replace(/\W/g, function($0){
		        return '\\' + $0;
		    });
		},
		within: function($element, x, y) {
			var offset = $element.offset();

			return (y >= offset.top &&
					y <  offset.top + $element.outerHeight() &&
					x >= offset.left &&
					x <  offset.left + $element.outerWidth());
		},
		/* ダイアログ表示用 */
		showDialog: function(id, ajax_options, dialog_options) {
			var dialog = $('#' + id), w, h;
			var ajax_defaults = {
				success : function(res) {
					var dialog_el = $('<div id='+ id +'></div>').appendTo($(document.body));
					dialog_el.html(res);
					w = dialog_el.children(':first').attr('data-width');
					h = dialog_el.children(':first').attr('data-height');
					if(parseInt(w) > 0) {
						dialog_options['width'] = parseInt(w);
					}
					if(parseInt(h) > 0) {
						dialog_options['height'] = parseInt(h);
					}
					dialog_el.dialog(dialog_options);
				}
			}, dialog_defaults = {
				zIndex: ++$.Common.zIndex
			};
			ajax_options = $.extend({}, ajax_defaults, ajax_options),
				dialog_options = $.extend({}, dialog_defaults, dialog_options);

			if(dialog.get(0)) {
				dialog.dialog('open');
				return;
			}
			$.ajax(ajax_options);
		},
		/* 権限[主坦　モデレータ　一般]スライダー */
		sliderAuthority: function(id, disable, display_guest) {
			display_guest = (typeof display_guest == "undefined") ? false : display_guest;
			var _hierarchy = function(authority_id) {
				var  h = 0;
				switch (authority_id)
				{
					case 2 : h = '301'; break;
					case 3 : h = '201'; break;
					case 4 : h = '101'; break;
					case 5 : h = '1'; break;
				}
				return h;
			};
			var _authority_id = function(h) {
				var authority_id = 2;
				switch (h)
				{
					case '301' : authority_id = 2; break;
					case '201' : authority_id = 3; break;
					case '101' : authority_id = 4; break;
					case '1' : authority_id = 5; break;
				}
				return authority_id;
			};
			var input = $('#' + id);
			if(!input.get(0)) return false;
			var slider = input.next();

			if(!input.get(0) || input.get(0).tagName.toLowerCase() != 'input') {
				return false;
			}

			slider.slider({
				'min'    : 2,
				'max'    : (display_guest) ? 5 : 4,
				'value'  : _authority_id($(input).val()),
				'animate': 'fast',
				'range'  : 'min',
				'change': function( event, ui ) {
					var authority_id = $(this).slider( "option", "value" );
					$(input).val(_hierarchy(authority_id));
				}
			});
			if(disable) {
				slider.slider( "disable" );
			}
		},

		/* 色取得一般メソッド */
		// RBG値から HSL値を取得
		getHSL : function(r, g, b)
		{
			var h,s,l,v,m;
			var r = r/255;
			var g = g/255;
			var b = b/255;
			v = Math.max(r, g), v = Math.max(v, b);
			m = Math.min(r, g), m = Math.min(m, b);
			l = (m+v)/2;
			if (v == m) var sl_s = 0, sl_l = Math.round(l*255),sl_h=0;
			else
			{
				if (l <= 0.5) s = (v-m)/(v+m);
				else s = (v-m)/(2-v-m);
				if (r == v) h = (g-b)/(v-m);
				if (g == v) h = 2+(b-r)/(v-m);
				if (b == v) h = 4+(r-g)/(v-m);
				h = h*60; if (h<0) h += 360;
				var sl_h = Math.round(h/360*255);
				var sl_s = Math.round(s*255);
				var sl_l = Math.round(l*255);
			}
			return { h : sl_h, s : sl_s , l : sl_l };
		},
		getRBG : function(h, s, l)
		{
			var r, g, b, v, m, se, mid1, mid2;
			h = h/255, s = s/255, l = l/255;
			if (l <= 0.5) v = l*(1+s);
			else v = l+s-l*s;
			if (v <= 0) var sl_r = 0, sl_g = 0, sl_b = 0;
			else
			{
				var m = 2*l-v,h=h*6, se = Math.floor(h);
				var mid1 = m+v*(v-m)/v*(h-se);
				var mid2 = v-v*(v-m)/v*(h-se);
				switch (se)
				{
					case 0 : r = v;    g = mid1; b = m;    break;
					case 1 : r = mid2; g = v;    b = m;    break;
					case 2 : r = m;    g = v;    b = mid1; break;
					case 3 : r = m;    g = mid2; b = v;    break;
					case 4 : r = mid1; g = m;    b = v;    break;
					case 5 : r = v;    g = m;    b = mid2; break;
				}
				var sl_r = Math.round(r*255);
				var sl_g = Math.round(g*255);
				var sl_b = Math.round(b*255);
			}
			return { r : sl_r, g : sl_g , b : sl_b };
		},
		getRGBtoHex : function(color) {
			if(color.r ) return color;
			if(color == "transparent" || color.match("^rgba")) return "transparent";
			if(color.match("^rgb")) {
				color = color.replace("rgb(","");
				color = color.replace(")","");
				color_arr = color.split(",");
				return { r : parseInt(color_arr[0]), g : parseInt(color_arr[1]) , b : parseInt(color_arr[2]) };
			}
			if ( color.indexOf('#') == 0 )
				color = color.substring(1);
			var red   = color.substring(0,2);
			var green = color.substring(2,4);
			var blue  = color.substring(4,6);
			return { r : parseInt(red,16), g : parseInt(green,16) , b : parseInt(blue,16) };
		},
		getHex : function(r, g, b)
		{
			var co = "#";
			if (r < 16) co = co+"0"; co = co+r.toString(16);
			if (g < 16) co = co+"0"; co = co+g.toString(16);
			if (b < 16) co = co+"0"; co = co+b.toString(16);
			return co;
		},
		getColorCode: function(el , p_name) {
			if(p_name == "borderColor" || p_name == "border-color") {
				p_name = "borderTopColor";
			}
			if(p_name == "borderTopColor" || p_name == "borderRightColor" ||
				p_name == "borderBottomColor" || p_name == "borderLeftColor") {
				var width = $(el).css(p_name.replace("Color","")+"Width");
				if(width == "" || width == "0px" || width == "0") {
					return "transparent";
				}
			}
			var rgb = $(el).css(p_name);
			if(rgb == undefined || rgb == null) {
				return "transparent";
			} else if (rgb.match("^rgb") && rgb != "transparent" && rgb.substr(0, 1) != "#") {
				rgb = rgb.substr(4, rgb.length - 5);
				var rgbArr = rgb.split(",");
				rgb = $.Common.getHex(parseInt(rgbArr[0]),parseInt(rgbArr[1]),parseInt(rgbArr[2]));
			} else if(rgb.substr(0, 1) != "#"){
				//windowtext等
				if(p_name == "backgroundColor") {
					return "transparent";
				}
				return "";
			}
			return rgb;
		},

		colorCheck: function(event) {
			if(((event.ctrlKey && !event.altKey) || event.keyCode == 229 || event.keyCode == 46 || event.keyCode == 8 ||
				(event.keyCode >= 37 && event.keyCode <= 40) || event.keyCode == 9 || event.keyCode == 13 ||
				(event.keyCode >= 96 && event.keyCode <= 105) ||
				(event.keyCode >= 48 && event.keyCode <= 57) || (event.keyCode >= 65 && event.keyCode <= 70)))
				return true;
			return false;
		},

		numberCheck: function(event) {
			if(((event.ctrlKey && !event.altKey) || event.keyCode == 229 || event.keyCode == 46 || event.keyCode == 8 || event.keyCode == 9 || event.keyCode == 13 ||
				(event.keyCode >= 96 && event.keyCode <= 105) ||
				(event.keyCode >= 37 && event.keyCode <= 40) || (!event.shiftKey && event.keyCode >= 48 && event.keyCode <= 57)))
				return true;
			return false;
		},

		numberConvert: function(event) {
			if(event.keyCode == 13 || event.type == "blur") {
				var event_el = $(event.target);
				var num_value = event_el.val();
				var en_num = "0123456789.,-+";
				var em_num = "０１２３４５６７８９．，－＋";
				var str = "";
				for (var i=0; i< num_value.length; i++) {
					var c = num_value.charAt(i);
					var n = em_num.indexOf(c,0);
					var m = en_num.indexOf(c,0);
					if (n >= 0) {c = en_num.charAt(n);str += c;
					} else if (m >= 0) str += c;
				}
				if(num_value != str) event_el.val(str);
				return true;
			}
			return false;
		},
		// %sのみ変換
		sprintf: function() {
			var str = arguments[0];
			if(str == undefined || str == null) {
				return str;
			}
			for (i = 1; i < arguments.length; i++) {
				str = str.replace(/%s/, arguments[i]);
			}
			return str;
		}
	};
	/* 定数 */
	$.support.pjax =
		window.history && window.history.pushState && window.history.replaceState &&
		// pushState isn't reliable on iOS until 5.
		!navigator.userAgent.match(/((iPod|iPhone|iPad).+\bOS\s+[1-4]|WebApps\/.+CFNetwork)/);
	/* グローバル関数 */
	__ = function(name) {
		var r = [], ret;
		if(name == undefined) {
			return $._lang['common'];
		}
		ret = ($._lang['common'][name]) ? $._lang['common'][name] : name;
		if(arguments.length >= 2) {
			r.push.apply(r, arguments);
			//r.shift();
			r[0] = ret;
			ret = $.Common.sprintf.apply(this, r);
		}
		return ret;
	};
	__d = function(key, name) {
		var r = [], ret;
		if(typeof key != 'string') {
			var buf_key = null;
			$.each(key, function() {
				if(buf_key == null) {
					buf_key = $._lang[this];
				} else {
					buf_key = buf_key[this];
				}
			});
			if(name == undefined) {
				return buf_key;
			}
			ret = (buf_key[name]) ? buf_key[name] : name;
		}
		if(!ret) {
			if(name == undefined) {
				return $._lang[key];
			}
			ret = ($._lang[key][name]) ? $._lang[key][name] : name;
		}
		if(arguments.length > 2) {
			r.push.apply(r, arguments);
			r.shift();
			r[0] = ret;
			ret = $.Common.sprintf.apply(this, r);
		}
		return ret;
	};
})(jQuery);