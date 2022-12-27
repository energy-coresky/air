
var sky = {
    version: 0.722,
    id: 0,
    mode: '', // visual or debug
    tz: null,
    home: '/',
    scrf: '',
    err: function(s) {
        alert(s);
    },
    err_t: 0,
    err_show: function(r) {
        if (r) {
            $('#trace-x').html(r.catch_error);
            'undefined' !== typeof r.ctrl ? sky.g.box(r.ctrl, r.func, 0) : dev('_x1');
        } else if (sky.err_t) {
            dev('_x0');
        }
    },
    d: {}, // dev utilities
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
    g: { // sky gate & language extensions
        box: function(ctrl, func, layout) {
            if (layout) {
                $('#trace-x').html('<h1>' + $('#trace-t h1:eq(0)').html() + '</h1>');
                sky.err_t = 0;
            } else {
                func += '&ajax';
            }
            var fn = !ctrl ? '' : ("default_c" == ctrl ? "*" : ctrl.substr(2));
            dev('?_gate=' + fn + '&func=' + func);
            //$('#err-top div:eq(0)').html()
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
    post: function(url, data, func) {
        return $.post(url, data, func);
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
                //ajax(a_, $(this.id).serializeFiles(), func, c_);
                ajax(a_, $(this.id).serialize(), func, c_);
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
    },
    _k27: this.false,
    hide: function() {
        sky.key[27] = sky._k27;//sky.false;
        $('#box').click(sky.true).hide().children('div.esc').remove();
    //  $('#box-in').html('');
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
        el.css('background', 'url(' + sky.home + 'img/ajax2.gif)');
    },
    bgh: function(bg) {
        if (sky.background_obj) {
            sky.background_obj.el.css('background', bg ? bg : sky.background_obj.css);
            sky.background_obj = false;
        }
    },
    set_file_clk: function(id) {
        $(id + ' pre span, ' + id + ' td span').each(function() {
            $(this).click(function() {
                if (!$(this).attr('style')) {
                    var n = $(this).html();
                    $('#top-head').html('<b>' + n + '</b>');
                ajax('', {name:n, c:$(this).next().hasClass('error')}, function(r) {
                    sky.box_html = $('#v-body').html();
                    $('#v-body').html(r);
                    $('#v-body div.code').get(0).scrollIntoView({block:'center',behavior:'smooth'});
                    sky.key[27] = function() { // Escape
                        $('#v-body').html(sky.box_html);
                        sky.set_file_clk(id);
                    };
                }, '_file')};
            });
        });
        sky.key[27] = sky.hide;
    },
    box_html: '',
    trace: function(c) {
        if (1) {
            dev('_x' + c);
            return;
        }
        if (c) $.post(sky.home + '_x' + c, function(r) {
            box('<ul style="position:fixed" id="x-cell">'
                + '<li><a href="javascript:;" onclick="sky.trace(1)" class="' + (1 == c ? 'active' : '') + '">X<sup>0</sup></a></li>'
                + '<li><a href="javascript:;" onclick="sky.trace(2)" class="' + (2 == c ? 'active' : '') + '">X<sup>-1</sup></a></li>'
                + '<li><a href="javascript:;" onclick="sky.trace(3)" class="' + (3 == c ? 'active' : '') + '">X<sup>-2</sup></a></li>'
                + '</ul><pre>' + r + '</pre>', 'x');
            //sky.set_file_clk('#box-in');
        }); else {
            var r = $('#trace').html();
            box('<pre>' + r + '</pre>', 't');
            //sky.set_file_clk('#box-in');
        }
    }
}

function dev(addr, pf) {
    if (pf)
        return ajax(addr, pf, 'v-body');
    if ($.isArray(addr))
        return ajax(addr, {}, 'v-body');
    box('<iframe src="' + sky.home + addr + '" style="width:100%; height:100%;vertical-align:middle;border:0;"></iframe>');
    $('#box a:first').hide();
}

function box(html, c) {
    var el = $('#box-in div.error:eq(0)').get(0),
        h = $(window).height() - 100, css, box, w = $(window).width() - 100;
    switch (c) {
        case 't': css = {backgroundColor:'#005', color:'#7ff', width:w, height:h}; break;
        case 'x': css = {backgroundColor:'#000', color:'#0f0', width:w, height:h}; break;
        case 'e': css = {backgroundColor:'#fff', color:'#000', width:500, height:500}; break;
        default: css = c || {backgroundColor:'#fff', color:'#111'}; break;
    }
    box = $('#box').click(sky.true).show(); //html(html).
    if (null !== html)
        box.children('#box-in').css(css).html(html).click(sky.true);
    sky.show();
    sky.resize();
    //if ('e' == c)
      //  sky.set_file_clk('#box-in');
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
    var mem_x, ctrl0 = c_ || sky.a.div, ctrl1 = c_ || sky.a._0;
    if (sky.a.x_el) {
        mem_x = sky.a.x_el.html();
        sky.a.x_el.html(sky.a.x_html);
    }
    $.ajaxSetup({
        headers: {'X-Orientation': sky.orientation()}
    });
    sky.post(sky.home + '?AJAX=' + ctrl0 + '&' + ctrl1 + '=' + j_, postfields || '', function(r) {
        var error_func = sky.a.error(); // get the current and restore default error handler
        func = func || sky.a.body;
        var x_trace = function () {
            try {
                $('#trace-x').html('');
                window.parent.document.getElementById('trace-x').innerHTML = '';
                //'_' != ctrl1.charAt(0);
            } catch (e) {};
        }
        if (sky.a.x_el)
            sky.a.x_el.html(mem_x);
        if ('undefined' !== typeof r.catch_error) {
            if ('undefined' !== typeof r.ky) {
//                location.href = sky.home + (12 == r.ky ? '' : '_exception');
            } else if ('undefined' !== typeof r.err_no) {
                return error_func ? error_func(r) : sky.err('Error ' + r.err_no + ' (error handler not set)');
            } else {
                sky.err_show(r);
            }
        } else switch (typeof func) {
            case 'function': x_trace(); return func(r);
            case 'string':   x_trace(); return $('#' + func).html(r);
            case 'object':   x_trace(); return func ? func.html(r) : null; // null is object
            default:         x_trace(); return r ? sky.err(r) : null;
        }
    });
}

(function($) {
    sky.home = $('meta[name="sky.home"]').attr('content');
    sky.scrf = $('meta[name="csrf-token"]').attr('content');

    var path = sky.home.replace(/\//g, "\\/");
    var m = location.href.match(new RegExp('^.+?' + path + '(\\w*)[^\\?]*(\\?(\\w+).*?)?(#.*)?$', ''));
    sky.a.div = m && m[1] ? m[1] : 'main';
    sky.a._0  = 'adm' == sky.a.div && m[3] ? m[3] : sky.a.div;

    $.ajaxSetup({
        headers: {'X-Csrf-Token': sky.scrf}
    });
    /* $.fn.serializeFiles = function() {
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
    */
})(jQuery);

$(function() {
    var html = '<div id="box" style="display:none"><div id="box-in"></div></div>'
        + '<div style="opacity:0;position:absolute;left:0;top:0;z-index:-1000"><img src="' + sky.home + 'img/ajax2.gif" /></div>';
    // set box
    $('body').prepend(html).keydown(function(e) {
        if ('function' == typeof sky.key[e.keyCode]) try {
            sky.key[e.keyCode]();
        } catch(e) {}
    });


    if ($('#trace-t')[0] && 0) {///////////////////////////////////////////////
        $('#err-top').show().html($('#trace-t').html());
        $('#trace-t').remove();
        if ('function' == typeof sky.g.show)
            sky.g.show();
    }
  sky.err_show(); // _x0 + sky gate

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
                location.href = sky.home;
        }, '_init');
    }

    //sky.set_file_clk('#err-top');
    sky.load();
});

