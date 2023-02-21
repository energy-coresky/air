
sky.a.start = function(ctrl, act) {
    if ('_trace' == ctrl)
        return 0;
    let a = $('#v-head div:eq(1) a:eq(1)'), i = 0;
    return setInterval(function() {
        ++i % 2 ? a.attr('active', 1) : a.removeAttr('active');
    }, 50);
};

sky.a.finish = function(to) {
    if (!to)
        return;
    clearInterval(to);
    $('#v-head div:eq(1) a').removeAttr('active');
    $('#v-head div:eq(1) a:eq(' + (sky.d.dev ? 0 : 1) + ')').attr('active', 1);
    if (sky.d.dev)
        return;
    ajax(1, function(r) {
        sky.d.x = 1;
        $('#dev-trace').html(r);
        sky.d.init(2);
    }, '_trace');
};

sky.d.draw = {
    x: '',
    v: function(data, str) {
        str = '';
        var j, vars, cls, blks = {}, blkc = {}, see_more = false;
        for (var view of data.views) {
            vars = 'BLK' == view.type ? blks[view.no][blkc[view.no]++] : data.vars[view.no];
            var c = 'BLK' == view.type ? '.' + blkc[view.no] : '';
            str += '<span style="color:#f77">#' + (1 + parseInt(view.no)) + c + ' ' + view.type + '</span> ' + view.hnd + ' ';
            str += '<span style="color:#f77">TEMPLATE</span> ' + view.tpl + '<br>';
            j = 0;
            if (!vars) {
                str += '<span style="color:red">empty-response</span>';
            } else Object.keys(vars).forEach(function (name) {
            //} else for (let [name, _var] of vars) { vars is not iterable
                if ('@' === name) { // BLKs vars
                    blks[view.no] = vars[name];
                    blkc[view.no] = 0;
                } else {
                    let s = '', dollar = -1 == name.indexOf('::') ? '$' : '', v = vars[name],
                        red_plus = '<span style="color:#f00">+</span>';
                    if (false === see_more && !dollar) {
                        see_more = j > 10 ? true : 0;
                        if (see_more)
                            s += '<a href="javascript:;" onclick="$(this).hide().next().show()">see more ('
                                + (Object.keys(vars).length - j - 2) + ')..</a><div style="display:none">'
                    }
                    if ('$' == name.charAt(0)) {
                        s += '<span style="color:#93c">' + ('$$' == name ? 'JSON' : 'STDOUT') + '</span>';
                    } else {
                        s += ('sky$' == name ? j + red_plus : ++j + '.') + ' <b>' + dollar
                        s += ('sky$' == name ? 'sky' : name) + '</b>'
                    }
                    if ('' === v) {
                        v = '<span style="color:#93c">empty string</span>';
                    } else if ('number' == typeof v) {
                        v = '<span style="color:red">' + v + '</span>';
                    } else if (null === v || 'boolean' == typeof v) {
                        v = '<span style="color:#93c">' + v + '</span>';
                    } else if ('<' == v.charAt(0) && (cls = v.match(/<(r|o c="([^"]+)")>/i))) {
                        v = '<span style="color:#b88">' + ('r' == cls[1] ? 'Array' : 'Object ' + cls[2]) + '</span>'
                            + ' <a href="javascript:;" onclick="sky.toggle(this)">&gt;&gt;&gt;</a>'
                            + v.replace('<' + cls[1] + '>', '<pre style="display:none">').replace('</' + v.charAt(1) + '>', '</pre>');
                    }
                    str += s +'&nbsp;=&nbsp;' + v + '<br>';
                }
            });
            if (see_more)
                str += '</div>';
            str += '<hr>';
        }
        return str;
    },
    s: function(data, str) {
        var m, n, i = 0, s = '';
        for (; m = str.match(/>\nSQL: (.+?)\n\n<(.*)/sm); str = m[2]) {
            //s && (s += '<br>');
            m[1] = m[1].replace(/(select|update|insert|join|from|where|group|order)/gi, '<span style="color:#93c">$1</span>');
            m[1] = m[1].replace(/^([\d\.]+ sec)/m, '<span style="color:red;border-bottom:1px solid blue">$1</span>');
            s += '<span style="color:#f77">#' + ++i + '</span> ' + m[1] + '<hr>';
        }
        return s ? s : '?';
    },
    c: function(data, str) {
        str = '';
        const base = [
            'Bolt', 'Controller', 'DEV', 'Debug', 'dc_file', 'Func', 'HEAVEN', 'MVC', 'MVC_BASE',
            'Model_m', 'Model_q', 'Model_t', 'Plan', 'SKY', 'SQL', 'Stop', 'USER', 'common_c', 'eVar',
            'PARADISE', 'Cache_driver', 'Database_driver', 'SQL_COMMON', 'HOOK', 'Hacker'
        ];
        for (var i in data.classes) {
            let s = data.classes[i], inc = base.includes(s), n;
            if (data.cnt.includes(n = parseInt(i)))
                str += '<hr>';
            str += (1 + n) + '.&nbsp;' + (inc ? s : `<span style="color:#93c">${s}</span>`) + '<br>';
        }
        return str + '<hr>';
    },
    b: function() {
        $('div#popup-in b').css({cursor: 'default'})
            .mouseenter(function() {
                var v = $(this).html();
                if (v == sky.d.v)
                    return;
                if (sky.d.v)
                    $('div#popup-in b').css({background:''});
                sky.d.v = v;
                $(this).css({background:'#bfdbfe'});
            }).click(function() {
                var found = 0;
                $('div.code span').each(function() {
                    $(this).css({background:'', color:''});
                    if ($(this).text().includes(sky.d.v)) {
                        found || this.scrollIntoView({block:'center',behavior:'smooth'});
                        found = 1;
                        $(this).css({background:'blue', color:'#fff'});
                    }
                });
            });
    }//#f77 #93c #0a0 #b88
};
sky.d.v = '';

sky.d.drop_cache = function(r, el) {
    var s = $(el).html(), ok = 'OK' == r;
    $(el).html(ok ? 'Dropped OK' : r).css({background: ok ? '#dfd' : '#fdd'});
    setTimeout(function () {
        $(el).html(s).css({background: ''});
    }, 2000);
};

sky.d.attach = function(s, m, form) {
    ajax('attach', form ? form : {s:s, mode:m}, function(r) {
        r = $.trim(r);
        if ('+' == r.charAt(0)) {
            $('#v-body div:eq(0)').html(r.substr(1));
        } else {
            'OK' == r ? (location.href = '_dev?ware') : alert(r);
        }
    });
};

sky.d.download = function(n) {
    ajax('download', {n:n}, function(r) {
        'OK' == r ? (location.href = '_dev?ware') : alert(r);
    });
};

sky.d.second_dir = function(el) {
    var s = $(el).prev().val();
    if (s) ajax('second_dir', {s:s}, function(r) {
        'OK' == r ? (location.href = '_dev?ware') : alert(r);
    });
};

sky.d.show_menu = function(x) {
    $('#v-menu').css('width', x ? '170px' : 0);
    $('#v-body').css('width', x ? 'calc(100vw - 170px)' : '100vw');
};

sky.d.trace = function(x, page, el) {
    if ('trace' == page)
        return location.href = sky.home + '_trace/' + x;
    let me = $(el).is('[active]');
    let is_menu = $('#v-menu').width()

    if (is_menu || me) {
        sky.d.show_menu(!is_menu)
        $('#v-body div:first').toggle()
        $('#dev-trace').toggle()
        if (me && !is_menu)
            return;
    }
    $('#v-head div:eq(1) a').removeAttr('active');
    $(el).attr('active', 1);
    sky.d.trace_t = sky.d.dev ? sky.d.parent_t : $('#trace-t').html();
    if (sky.d.x = x) {
        ajax('' + x, {}, function(r) {
            $('#dev-trace').html(r).css({display:'table-cell'});
            sky.d.init(1)
        }, '_trace')
    } else {
        $('#dev-trace').html(sky.d.trace_t).css({display:'table-cell'});
        sky.d.init(1)
    }
};

sky.d.x = 0;
sky.d.trace_t = '';
sky.d.parent_t = window.parent.document.getElementById('trace-t').innerHTML;

sky.d.init = function(from) {
    var self_t = $('#trace-t').html(), m, top = '',
        black = {backgroundColor:'#000', color:'#0f0'}, is_trace = '_trace' == sky.a._0;

    if (is_trace)
        sky.d.x = parseInt(sky.a._1)

    $('#dev-trace').css(sky.d.x ? black : {backgroundColor:'#005', color:'#7ff'});

    if ('view' != sky.a._1)
        sky.d.trace_t = sky.d.dev || !self_t ? sky.d.parent_t : self_t;

    if (!from && (!is_trace || '_trace/0' == sky.a.uri))
        $('#dev-trace').html(sky.d.dev || is_trace ? sky.d.parent_t : self_t);

    var str = $('#dev-trace').html();

    var trc = str, data = JSON.parse($.trim($('#dev-trace div.dev-data:eq(0)').text()));
    for (let i in data.errors) if ('0' != i) {
        let err = data.errors[i];
        $('#dev-trace').prepend(`<h1>${err[0]}</h1><pre>${err[1]}</pre>`);
    }
    var wpx = window.parent.document.getElementById('trace-x'), z_err = false;
    if (from && !sky.d.dev) {
        $(wpx).html('');
    } else if ($(wpx).find('h1.z-err')[0]) {
        z_err = true;
    }
    if ($(wpx).html() && sky.d.x == parseInt($(wpx).attr('x'))) {
        if (z_err) {
            $('#dev-trace').html(trc = str = $(wpx).html());
            data = JSON.parse($.trim($('#dev-trace div.dev-data:eq(0)').text()));
        } else {
            $('#dev-trace').prepend($(wpx).html());
        }
    }
    if (data.errors[0])
        $('#dev-trace h1').each(function () {
            $(this).css({color:'red', backgroundColor:'pink'});
        });

    sky.d.files();

    for (var a = []; m = str.match(/(TOP|SUB|BLK)\-VIEW: (\d+) (\S+) (\S+)(.*)/s); str = m[5]) {
        a.push({type:m[1], no:m[2], hnd:m[3], tpl:'^' == m[4] ? ('BLK' == m[1] ? 'injected-to-parent' : 'not-used') : m[4]});
        if ('TOP' == m[1])
            top = 'TOP: <b>' + m[3] + '</b> &nbsp; <b>' + a[a.length - 1].tpl + '</b>';
    }

    var csql = '?';
    if (m = str.match(/([\.\d]+ sec), SQL queries: (\d+)/s)) {
        csql = m[2];
        $('#tpl-list').next().find('span:eq(0)').html(m[1]);
    }

    if (1 == from || '' !== $('#master').html()) {
        data.views = a.length ? a : [{type:'UNKNOWN', no:0, hnd:'?', tpl:'?'}];
        $('#tpl-list').html($('#tpl-list-copy').html()).find('a')
            .mouseenter(function() {
                sky.d.to && clearTimeout(sky.d.to);
                sky.d.to = 0;
                var n = $(this).attr('n'), i = 's' == n ? 1 : ('c' == n ? 2 : 0);
                $('div#popup').show().css({left:$(this).offset().left, maxWidth: i ? '35%' : '50%'});
                $('#tpl-list a').css({background:'', color:''});
                $('#tpl-list a:eq(' + i + ')').css({background:'#ddd', color:'#000'});
                if (n != sky.d.draw.x) {
                    sky.d.draw.x = n;
                    $('div#popup-in').html(sky.d.draw[n](data, trc));
                    'v' == n && sky.d.draw.b();
                }
            }).mouseleave(sky.d.mouseleave);
            $('div#popup').mouseenter(function() {
                sky.d.to && clearTimeout(sky.d.to);
                sky.d.to = 0;
            }).mouseleave(sky.d.mouseleave);
        $('#tpl-list span:eq(0)').html(a.length);
        $('#tpl-list span:eq(1)').html(csql);
        $('#tpl-list span:eq(2)').html(data.cnt[0]);
        $('#master').html(top)
    }
};

sky.d.to = 0;
sky.d.mouseleave = function() {
    if (sky.d.to)
        clearTimeout(sky.d.to);
    sky.d.to = setTimeout(function() {
        $('div#popup').hide();
        $('#tpl-list a').css({background:'', color:''});
    }, 300);
};

sky.d.view = function() {
    if (!sky.d.trace_t)
        return;
    $('#v-tail input').val(sky.d.trace_t);
    $('#v-tail form').attr('action', sky.home + '_dev?view=' + sky.d.x).submit();
};

sky.d.drop = function() {
    var s = $('#drop-var span')
    ajax('drop', {v:s.text(), cid:s.attr('cid')}, function(r) {
        location.href = location.href.replace(/^(.*?&show)=\w+$/, '$1');
    });
};

sky.d.pp = function(el) {
    var checked = $(el).is(':checked') ? 1 : 0;
    ajax('pp', {pp:checked}, function(r) {
        location.href = location.href;
    });
};

sky.d.reflect = function(el, type) {
    var td = $(el).parent(), name = td.prev().text();
    ajax('reflect', {t:type, n:name}, function(r) {
        td.append(r);
    });
};

sky.d.files_html = ['', '', ''];
sky.d.files = function() {
    $('#v-body pre span, #v-body td span').each(function() {
        if ($(this).attr('style'))
            return;
        $(this).click(function() {
            var filine = $(this).html();
            ajax('', {name:filine, c:$(this).next().hasClass('error')}, function(r) {
                sky.d.files_html = [$('#v-body').html(), $('#top-head').html(), sky.key[27]];
                $('#v-body').html(r);
                $('#top-head').html('<b>' + filine + '</b>');
                $('#v-body div.code').get(0).scrollIntoView({inline:'start',block:'center',behavior:'smooth'});
                sky.key[27] = function() {
                    $('#v-body').html(sky.d.files_html[0]);
                    $('#top-head').html(sky.d.files_html[1]);
                    sky.d.files();
                    sky.key[27] = sky.d.files_html[2];
                };
            }, '_file');
        });
    });
};

sky.d.close_box = function(url) {
    $(window.parent.document.getElementById('box')).find('#box-esc a').click();
    //if (url)
        //window.parent.location.href = url;
};

(function() {
    sky.a.error(function(r) {
        if (801 == r.err_no)
            $('#top-head').html(r.catch_error);
    });
    sky.key[27] = sky.d.close_box;
})();

$(function() {
    $('body').prepend('<div id="popup" style="display:none"><div id="popup-in"></div></div>');
    sky.d.show_menu('_trace' != sky.a._0);
    sky.d.init();
    $('#drop-var').after('<input type="button" value="Yes" onclick="sky.d.drop()" />');
});

