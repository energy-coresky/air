
var sky = {
    version: 0.722,
    id: 0,
    tz: null,
    home: '/',
    scrf: '',
    err: function(s) {
        alert(s);
    },
    err_t: 0,
    err_core: function(r) {
        if (r) { // from ajax
            $('#trace-x').html(r.catch_error).attr('x', r.err_no);
            if (1 == r.err_no && r.ctrl) {
                sky.g.box(r.ctrl, r.func, 0)
            } else {
                dev('_trace/' + r.err_no);
            }
        } else if (sky.err_t) { // from layout
            dev('_trace/' + $('#trace-x').attr('x'));
        }
    },
    g: { // sky gate & dev utilities
        box: function(ctrl, func, layout) {
            
            if (layout) {
                sky.err_t = 0;
            } else {
                func += '&ajax';
            }
            var fn = !ctrl ? '' : ("default_c" == ctrl ? "*" : ctrl.substr(2));
            dev('?_gate=' + fn + '&func=' + func);
        }
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
        },
        start: false,
        finish: false
    },
    d: {}, // dev utilities
    orientation: function() {
        return 'undefined' === typeof window.orientation ? 0 : (window.orientation == 90 || window.orientation == -90 ? 2 : 1);
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
            '0' == $(el).val() ? $(id).slideUp(150): $(id).slideDown(150);
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
    key: {},//{27: this.false},
    false: function() {
        return false;
    },
    true: function() {
        return true;
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
    },
    head: function() {
        $('html').animate({scrollTop:0});
    },
    tail: function() {
        $('html').animate({scrollTop:$('main').height()});
    },
    box_html: '',
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
    trace: function(c) {
        dev('_trace/' + c);
        return;
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
    var el = $('#box-in div.error:eq(0)').get(0), css, box;
    css = c || {
            backgroundColor:'white', color:'#111',
            //margin:'50px 0 0 50px',
            position:'fixed', left:'50px', top:'50px',
            overflow:'hidden',
            width:'calc(100vw - 100px)', height:'calc(100vh - 100px)'
        };
    box = $('#box').show()//.css({
        //position:'fixed', left:0, top:0,        width: '100vw', height: '100vh', zIndex: 888,
        //backgroundColor: 'rgba(0,0,0, 0.5)'//, padding: '50px'
    //}); //html(html).
    if (null !== html)
        box.children('#box-in').html(html).click(sky.true);
        //box.children('#box-in').css(css).html(html).click(sky.true);
    sky.show();
    //el.scrollIntoView({block:'center',behavior:'smooth'});
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
    var mem_x, to, ctrl0 = c_ || sky.a.div, ctrl1 = c_ || sky.a._0;
    if (sky.a.x_el) {
        mem_x = sky.a.x_el.html();
        sky.a.x_el.html(sky.a.x_html);
    }
    $.ajaxSetup({
        headers: {'X-Orientation': sky.orientation()}
    });
    if (sky.a.start)
        to = sky.a.start(ctrl1, '' + j_);
    sky.post(sky.home + '?AJAX=' + ctrl0 + '&' + ctrl1 + '=' + j_, postfields || '', function(r) {
        var error_func = sky.a.error(); // get the current and restore default error handler
        func = func || sky.a.body;
        if (sky.a.x_el)
            sky.a.x_el.html(mem_x);
        if (sky.a.finish)
            sky.a.finish(to);
        if ('undefined' !== typeof r.catch_error) {
            if (r.err_no > 99) { // r.soft => r.code !!
                return error_func ? error_func(r) : sky.err('Error ' + r.err_no + ' (error handler not set)');
            } else {
                sky.err_core(r);
            }
        } else switch (typeof func) {
            case 'function': return func(r);
            case 'string':   return $('#' + func).html(r);
            case 'object':   return func ? func.html(r) : null; // null is object
            default:         return r ? sky.err(r) : null;
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
})(jQuery);

$(function() {
    var html = '<div id="box" style="display:none"><div id="box-in"></div></div>';
    // set box
    $('body').prepend(html).keydown(function(e) {
        if ('function' == typeof sky.key[e.keyCode]) try {
            sky.key[e.keyCode]();
        } catch(e) {}
    });

    sky.err_core();

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
    sky.load();
});
