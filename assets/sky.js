
var sky = {
	version: 0.7,
	max_filesize: 5000000,
	id: 0,
	mode: '', // visual or debug
	tz: '',
	path: '',
	scrf: '',
	err: function(s) {
		alert(s);
	},
	a: { // ajax
		body: null,
		div: 'main',
		_0: 'main',
		_1: '',
		x_el: false,
		x_html: '',
		err: false, // no error handler by default
		err_tmp: false,
		error: function(func) { // mean 2 err handlers: default and custom
			var tmp = sky.a.err ? sky.a.err : func;
			if (func)
				sky.a.err_tmp = tmp;
			sky.a.err = func ? func : sky.a.err_tmp;
			return tmp;
		}
	},
	key: {27: this.false},
	orientation: function() {
		return 'undefined' === typeof window.orientation ? 0 : (window.orientation == 90 || window.orientation == -90 ? 2 : 1);
	},
	false: function() {
		return false;
	},
	true: function() {
		return true;
	},
	toggle: function(el) {
		$(el).html('&gt;&gt;&gt;' == $(el).html() ? '<<<' : '>>>').next().slideToggle();
	},
	file_delete_c: false,
	file_delete: function(el, id) {
		ajax('', {id:id}, function(r) {
			if ('ok' == r) {
				var imgs = $(el).parent(), tpl = imgs.siblings('div:eq(0)').html();
				imgs.html(tpl);
				imgs.siblings('input[type=hidden]').val('');
				if (sky.file_delete_c)
					sky.file_delete_c();
			}
		}, '_file_delete');
	},
	crop: function(r) { // crop images
		var td = $('table#crop td:eq(0)'), reg = td.find('div'), img = $('table#crop img:eq(0)'),
			td_w = td.width(), td_h = $('#box-in').height() - 15;
		$('table#crop span:eq(0)').text(r.width + ' x ' + r.height);
		td.height(td_h);
		img.attr('src', 'file?id' + r.id + '&_0').on('load', function() {
			var ratio = r.width / r.height > td_w / td_h, left, top, rw, rh, reg_css = function() {
				reg.css({
					left: left = (left < 0 ? 0 : (left + rw > x ? x - rw - 2 : left)), width: rw,
					top:   top = (top < 0 ? 0 : (top + rh > y ? y - rh - 2 : top)),    height: rh
				});
			};
			img.css({
				width: ratio ? td_w : 'auto',
				height: ratio ? 'auto' : 'inherit'
			});
			var x = img.width(), y = img.height(), minimal, size, flag = false;
			reg.click(function() {
				flag = !flag;
				$('table#crop span:eq(1)').text(flag ? 'crop' : 'move').css('background', flag ? '#fbb' : '#bfb');
			});
			var range = $('table#crop input[type=range]').on('input', function() {
				var val = minimal * this.value / 100 - 2;
				rw = val * (x == minimal ? 1 : ratio);
				rh = val / (x == minimal ? ratio : 1);
				reg_css();
			});
			$('table#crop select:eq(0)').change(function() {
				size = $(this).val().match(/(\d+)\D+(\d+)/);
				ratio = size[1] / size[2];
				minimal = ratio > x / y ? x : y;
				range.trigger('input');
			}).change();
			$('table#crop button:eq(0)').click(function() { // crop
				var q = {
					x0: Math.round(left * r.width / x),
					y0: Math.round(top * r.height / y),
					x1: Math.round((left + rw) * r.width / x) + 2,
					y1: Math.round((top + rh) * r.height / y) + 2,
					id: r.id, szx: size[1], szy: size[2]
				};
				ajax('', q, function() {
					var html = '<a class="delete-img" href="javascript:;" onclick="sky.file_delete(this, ' + r.id + ')"></a>';
					r.place.html('<img style="position:absolute" src="file?id' + r.id + '&_"/>' + html);
					sky.hide();
				}, '_crop');
			});
			$(document).mousemove(function(e) {
				if (flag)
					return;
				var os = img.offset();
				left = e.clientX - rw / 2 - os.left + $(this).scrollLeft();
				top = e.clientY - rh / 2 - os.top + $(this).scrollTop();
				reg_css();
			});
		});
	},
	post_files: false,
	post: function(url, data, func) {
		if (!sky.post_files)
			return $.post(url, data, func);
		var el = sky.post_files === true ? false : sky.post_files;
		sky.post_files = false;
		$.ajax({
			url: url,
			data: data,
			cache: false,
			contentType: false,
			processData: false,
			method: 'POST',
			type: 'POST', // For jQuery < 1.9
			success: func,
			xhr: function() {
				var a = $.ajaxSettings.xhr();
				if (a.upload) {
					a.upload.addEventListener('progress', function(e) {
						if (e.lengthComputable && el) {
							el.attr({
								value: e.loaded,
								max: e.total,
							});
						}
					}, false);
				}
				return a;
			}
		});
	},
	g: { // sky gate & language extensions
		box: function(ctrl, func, err) {
			var fn = !ctrl ? '' : ("default_c" == ctrl ? "*" : ctrl.substr(2));
			ajax(['_gate', fn], {err:err || ''}, function(r) {
				box(r);
				$('h1 span.gate-err').html(ctrl + '::' + func);
				sky.set_file_clk('#box-in');
				if (func) setTimeout(function() {
					var el = false;
					$('#gate legend b').each(function() {
						if ($(this).text() == func)
							el = $(this).parent().parent().find('input[type=button]')[0];
					});
					sky.g.edit(el, fn + '.' + func, true);
				}, 777);
			});
		}
	},
	f: { // forms
		h: {},
		id: '',
		set: function(id, h, repeat) {
			this.id = id;
			this.h = h;
			for(; repeat.length; ) {
				var cls = repeat.shift();
				sky.f.plus(cls, repeat.shift());
			}
		},
		submit: function() {
			var k, a, m, val, flag = true, vf = false, vh = {}, ve, cv;
			for (k in this.h) {
				if (k.match(/^[^a-z\d_]/i)) continue;
				a = this.h[k];
				if (typeof a == 'string') a = this.h[a];
				$(this.id + ' *[name="' + k + '"]').each(function() {
					if ($(this).attr('type') == 'radio' && !$(this).is(':checked')) return;
					val = $(this).val();
					m = cv = true;
					if ('object' == typeof a[1]) { // regexp
						m = val.match(a[1]);
						cv = false;
					} else if ('' == a[1]) { // +
						m = '' != val.replace(/^\s+/, '').replace(/\s+$/, '');
						cv = false;
					} else if ('0' == val) { // case not valid condition part
						vh[a[0]] = a[1];
					}
					vf || !vh[k] || (vf = true, ve = vh[k]);
					m || !vf || (m = true); // reset by condition validation
					k != ve || (vf = false);
					m || (flag = false);
					cv || $(this).next().html(m ? '' : a[0]);
				});
			}
			if (this.h['!']) { // common message
				$(this.id + '-message').html(this.h['!']).css('display', flag ? 'none' : 'block');
				if (!flag) sky.head();
			}
			return flag;
		},
		ajax: function(a_, func, c_) {
			if (this.submit()) {
				if ('function' == typeof a_) {
					func = a_;
					a_ = '';
				}
				ajax(a_, $(this.id).serializeFiles(), func, c_);
			}
			return false;
		},
		slide: function(el, id) {
			'0' == $(el).val() ? $(id).slideUp(150, sky.resize): $(id).slideDown(150, sky.resize);
		},
		plus: function(cls, h) {
			var name, el = $('#' + cls).before($('.' + cls + ':eq(0)')[0].outerHTML).prev();
			el.prepend('<a class="fr" href="javascript:;" onclick="sky.f.del(this);">[X]</a>').find('input').val('');
			if (h) for (name in h) {
				el.find('input[name="' + name + '[]"]').val(h[name]);
			}
		},
		del: function(el) {
			$(el).parent().remove();
		}
	},
	head: function() {
		$('html').animate({scrollTop:0});
	},
	tail: function() {
		$('html').animate({scrollTop:$('main').height()});
	},
	resize: function() {
		var wh = $(window).height(), ww = $(window).width(), t;
		$('#box').css({width:ww, height:wh}).children().css({
			left: (ww - $('#box-in').width()) / 2,
			top: t = (wh - $('#box-in').height()) / 2
		});
		$('#box div:first').css('top', t - 18);
	},
	load: function() {
		// process elm-hide
		$('li.elm-hide').each(function() {
			var el = $(this),
				chk = el.prev().find(':checked');
			if (chk[0]) { // form view
				el.prev().find('input').on('change', function() {
					'0' == this.value ? el.hide() : el.show();
				});
				if ('0' == chk[0].value)
					el.hide();
			} else { // table view
				chk = el.prev().find('.concl:first');
				if (chk.hasClass('hide-elms'))
					el.hide();
			}
		});
		// process files
		$('form div.imgs, form div.files').each(function() {
			var el = $(this),
				input = el.next(),
				img = el.hasClass('imgs');
			el.on('dragover', function(e) {
				$(this).addClass('hover');
				return false;
			}).on('dragleave', function(e) {
				$(this).removeClass('hover');
				return false;
			}).on('drop', function(e) {
				e.preventDefault();
				$(this).removeClass('hover');
				input[0].files = e.originalEvent.dataTransfer.files;
				input.trigger('change');
				return false;
			}).on('click', function(e) {
				e.preventDefault();
				if (el.has('span')[0])
					input.trigger('click');
				return false;
			});
			input.on('change', function() {
				var file = this.files[0];
				if (undefined === file)
					return;
				if (file.size > sky.max_filesize) {// Also see .name, .type
					sky.err('max upload size is ' + sky.max_filesize + ' bytes');
				} else {
					sky.post_files = el.find('progress').show();
					sky.post_files.prev().remove();
					var formData = new FormData();
					formData.append('file', file);
					ajax('', formData, function(r) {
						el.html('<div class="doc-file-name">' + file.name + '</div>');
						if (r.id !== undefined) {
							el.siblings('input[type=hidden]').val(r.id);
							var s0 = '<a href="javascript:;" class="delete-' + (img ? 'img' : 'doc')
								+ '" onclick="sky.file_delete(this, ' + r.id + ')"></a>';
							el.append(s0);
							if (r.img == 1 && img) { // crop image if required
								ajax('', {}, function(h) {
									box(h);
									r.place = el;
									sky.crop(r);
								}, '_crop_code');
							}
						} else {
							sky.err(r);
						}
					}, '_file_tmp');
				}
			});
		});
	},
	_k27: this.false,
	hide: function() {
		sky.key[27] = sky._k27;//sky.false;
		$('#box').click(sky.true).hide().children('div.esc').remove();
	//	$('#box-in').html('');
		if (sky.esc_refresh)
			location.href = location.href;
	},
	show: function() {
		if ($('#box div.esc').html())
			return;
		sky._k27 = sky.key[27];
		sky.key[27] = sky.hide;
		$('#box').click(sky.true).prepend('<div class="esc"><a href="javascript:;" onclick="sky.hide()" class="red-link fr">Esc - Close [X]</a></div>');
		$('#box .esc').css('width', $('#box-in').width() - 20);
		sky.resize();
	},
	bg: '#ded url(img/bg.png)',
	background_obj: false,
	bgs: function(el) {
		if (!el) el = $('#box-in');
		sky.background_obj = {el:el, css:el.css('background')};
		el.css('background', 'url(' + sky.path + 'img/ajax2.gif)');
	},
	bgh: function(bg) {
		if (sky.background_obj) {
			sky.background_obj.el.css('background', bg ? bg : sky.background_obj.css);
			sky.background_obj = false;
		}
	},
	set_file_clk: function(id) {
		$(id + ' pre span').each(function() {
			$(this).click(function() {
				if (!$(this).attr('style'))
				ajax('', {name:$(this).html(), c:$(this).next().hasClass('error')}, function(r) {
					sky.box_html = $('#box').html();
					box(r);
					$('#box-in div.code').get(0).scrollIntoView({block:'center',behavior:'smooth'});
					sky.key[27] = function() { // Escape
						$('#box').html(sky.box_html);
						sky.set_file_clk(id);
					};
				}, '_file');
			});
		});
		sky.key[27] = sky.hide;
	},
	box_html: '',
	trace: function(c) {
		if (c) $.post(addr + '_x' + c, function(r) {
			box('<ul style="position:fixed" id="x-cell">'
				+ '<li><a href="javascript:;" onclick="sky.trace(1)" class="' + (1 == c ? 'active' : '') + '">X<sup>0</sup></a></li>'
				+ '<li><a href="javascript:;" onclick="sky.trace(2)" class="' + (2 == c ? 'active' : '') + '">X<sup>-1</sup></a></li>'
				+ '<li><a href="javascript:;" onclick="sky.trace(3)" class="' + (3 == c ? 'active' : '') + '">X<sup>-2</sup></a></li>'
				+ '</ul><pre>' + r + '</pre>', 'x');
			sky.set_file_clk('#box-in');
		}); else {
			var r = $('#trace').html();
			box('<pre>' + r + '</pre>', 't');
			sky.set_file_clk('#box-in');
		}
	}
}

function box(html, c) {
	var w = $(window).width() - 100,
		h = $(window).height() - 100, css, box, el = $('#box-in div.error:eq(0)').get(0);
	switch (c) {
		case 't': css = {backgroundColor:'#005', color:'#7ff', width:w, height:h}; break;
		case 'x': css = {backgroundColor:'#000', color:'#0f0', width:w, height:h}; break;
		case 'e': css = {backgroundColor:'#fff', color:'#000', width:500, height:500}; break;
		default: css = c || {backgroundColor:'#fff', color:'#111', width:w, height:h}; break;
	}
	box = $('#box').click(sky.true).show(); //html(html).
	if (null !== html)
		box.children('#box-in').css(css).html(html).click(sky.true);
	sky.show();
	sky.resize();
	if ('e' == c)
		sky.set_file_clk('#box-in');
	if (el && ('t' == c || 'x' == c))
		el.scrollIntoView({block:'center',behavior:'smooth'});
}

function ajax(j_, postfields, func, c_) {
	if ('function' == typeof postfields) {
		c_ = func;
		func = postfields;
		postfields = '';
	}
	if ($.isArray(j_)) {
		c_ = sky.a._0 = sky.a.div = j_[0];
		j_ = sky.a._1 = 1 == j_.length ? '' : j_[1];
	}
	j_ = 'number' == typeof j_ ? 'ajax' + (j_ ? j_ : '') : j_;
	var mem_x, url = sky.path + '?AJAX=' + (c_ || sky.a.div) + '&' + (c_ || sky.a._0) + '=' + j_;
	if (sky.a.x_el) {
		mem_x = sky.a.x_el.html();
		sky.a.x_el.html(sky.a.x_html);
	}
	$.ajaxSetup({
		headers: {'X-Orientation': sky.orientation()}
	});
	sky.post(url, postfields || '', function(r) {
		var error_func = sky.a.error(); // get the current and restore default error handler
		func = func || sky.a.body;
		if (sky.a.x_el)
			sky.a.x_el.html(mem_x);
		if ('undefined' !== typeof r.catch_error) {
			if ('undefined' !== typeof r.ky) {
				location.href = sky.path + (12 == r.ky ? '' : '_exception');
			} else if ('undefined' !== typeof r.err_no) {
				return error_func ? error_func(r) : sky.err('Error ' + r.err_no + ' (error handler not set)');
			} else {
				'undefined' !== typeof r.ctrl ? sky.g.box(r.ctrl, r.func, r.catch_error) : box(r.catch_error, 'e');
			}
		}
		else if ('function' == typeof func) func(r);
		else if ('string' == typeof func) $('#' + func).html(r);
		else if ('object' == typeof func) func.html(r);
		else if (r) sky.err(r);
	});
}

(function($) {
	sky.path = $('meta[name="surl-path"]').attr('content');
	sky.scrf = $('meta[name="csrf-token"]').attr('content');

	var path = sky.path.replace(/\//g, "\\/");
	var m = location.href.match(new RegExp('^.+?' + path + '(\\w*)[^\\?]*(\\?(\\w+).*?)?(#.*)?$', ''));
	sky.a.div = m && m[1] ? m[1] : 'main';
	sky.a._0  = 'adm' == sky.a.div && m[3] ? m[3] : sky.a.div;

	$.ajaxSetup({
		headers: {'X-Csrf-Token': sky.scrf}
	});
	$.fn.serializeFiles = function() {
		var obj = $(this);
		sky.post_files = true;
		var formData = new FormData();
		$.each($(obj).find("input[type='file']"), function(i, tag) {
			$.each($(tag)[0].files, function(i, file) {
				formData.append(tag.name, file);
			});
		});
    
		var params = $(obj).serializeArray();
		$.each(params, function (i, val) {
			formData.append(val.name, val.value);
		});
		return formData;
	};
})(jQuery);

$(function() {
	var html = '<div id="box" style="display:none"><div id="box-in"></div></div>'
		+ '<div style="opacity:0;position:absolute;left:0;top:0;z-index:-1000"><img src="' + sky.path + 'img/ajax2.gif" /></div>';
	// set box
	$('body').prepend(html).keydown(function(e) {
		if ('function' == typeof sky.key[e.keyCode]) try {
			sky.key[e.keyCode]();
		} catch(e) {}
	});
	if ($('#err-bottom')[0]) {
		$('#err-top').show().html($('#err-bottom').html());
		$('#err-bottom').remove();
		if ('function' == typeof sky.g.show)
			sky.g.show();
	}
	sky.resize();
	$(window).resize(sky.resize);

	var scr = '';
	if ('' === sky.tz) {
		try { scr = screen.width + 'x' + screen.height } catch(e) {}
		sky.tz = (new Date().getTimezoneOffset()) / 60 * -1;
		if ('' === sky.tz)
			sky.tz = 0;
		ajax('_', {tz:sky.tz, scr:scr}, function (r) {
			if ('main' == r)
				location.href = sky.path;
		}, '_init');
	}

	sky.set_file_clk('#err-top');
	sky.load();
});

