
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
    ajax(1, {}, function(r) {
        sky.d.trace_x = 1;
            if (!$('#dev-trace')[0])
        $('#v-body').append('<div id="dev-trace" style="display:none"></div>');

        $('#dev-trace').html(r);
        sky.d.init(r, 2);
    }, '_trace');
};

sky.d.close_box = function() {
    $(window.parent.document.getElementById('box')).find('.esc a:first').click();
};

sky.d.draw = {
    v: function(data, str) {
        str = '';
        var j, vars, blks = {}, blkc = {};
        for (var view of data.views) {
            vars = 'BLK' == view.type ? blks[view.no][blkc[view.no]++] : data.vars[view.no];
            var c = 'BLK' == view.type ? '.' + blkc[view.no] : '';
            str += '<span style="color:#f77">#' + (1 + parseInt(view.no)) + c + ' ' + view.type + '</span> ' + view.hnd + '<br>';
            str += 'TEMPLATE ' + view.tpl + '<br>';
            j = 0;
            if (!vars) {
                str += '<span style="color:red">empty-response</span>';
            } else Object.keys(vars).forEach(function (name) {
                if ('' === name) { // BLKs vars
                    blks[view.no] = vars[name];
                    blkc[view.no] = 0;
                } else {
                    let s = ++j + '. <b>$' + name + '</b>', v = vars[name];
                    if ('$' == name.charAt(0))
                        s = '<span style="color:#93c">' + ('$$' == name ? 'JSON' : 'STDOUT') + '</span>';
                    if ('number' == typeof v) {
                        v = '<span style="color:red">' + v + '</span>';
                    } else if (null === v || 'boolean' == typeof v) {
                        v = '<span style="color:#93c">' + v + '</span>';
                    } else if ('<' == v.charAt(0)) {
                        var cls = v.match(/<o>([\(\)\w]+)/i);
                        !cls || (cls = '(object)' == cls[1] ? 'stdClass' : cls[1]);
                        v = '<span style="color:#b88">' + ('o' == v.charAt(1) ? 'Object ' + cls : 'Array') + '</span>'
                            + ' <a href="javascript:;" onclick="sky.toggle(this)">&gt;&gt;&gt;</a>'
                            + v.replace('<'+v.charAt(1)+'>', '<pre style="display:none">').replace('</'+v.charAt(1)+'>', '</pre>');
                    }
                    str += s +'&nbsp;=&nbsp;' + v + '<br>';
                }
            });
            str += '<hr>';
        }
        return str;
    },
    s: function(data, str) {
        var m, i = 0, s = '';
        for (; m = str.match(/>\nSQL: (.+?)\n\n<(.*)/sm); str = m[2]) {
            s += '<span style="color:#f77">#' + ++i + '</span> ' + m[1] + '<br><br>';
        }
        return s ? s : '?';
    },//#f77 #93c #0a0 #b88
    c: function(data, str) {
        str = '';
        const base = [
            'Bolt', 'Controller', 'DEV', 'Debug', 'dc_file', 'Func', 'Gape', 'HEAVEN', 'MVC', 'MVC_BASE',
            'Model_m', 'Model_q', 'Model_t', 'Plan', 'SKY', 'SQL', 'Stop', 'USER', 'common_c', 'eVar'
        ];
        for (var i in data.classes) {
            let s = data.classes[i], inc = base.includes(s);
            str += (1 + parseInt(i)) + '.&nbsp;' + (inc ? s : `<span style="color:#93c">${s}</span>`) + '<br>';
        }
        //Object.keys(data.classes).forEach(function (key) {
        //});
        return str;
    }
};

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
    if (sky.d.trace_x = x) {
        ajax('' + x, {}, function(r) {
            $('#dev-trace').html(r).css({display:'table-cell'});
            sky.d.init(r, 1)
        }, '_trace')
    } else {
        $('#dev-trace').html(sky.d.trace_t).css({display:'table-cell'});
        sky.d.init(sky.d.trace_t, 1)
    }
};

sky.d.trace_x = 0;
sky.d.trace_t = '';
sky.d.parent_t = window.parent.document.getElementById('trace-t').innerHTML;
sky.d.parent_x = window.parent.document.getElementById('trace-x').innerHTML;

sky.d.init = function(str, from) {
    var self_t = $('#trace-t').html(), m, top = '', black = {backgroundColor:'#000', color:'#0f0'};

    if ('_trace' == sky.a._0)
        sky.d.trace_x = parseInt(sky.a._1)
    $('#dev-trace').css(sky.d.trace_x ? black : {backgroundColor:'#005', color:'#7ff'});
    if ('view' != sky.a._1)
        sky.d.trace_t = sky.d.dev || !self_t ? sky.d.parent_t : self_t;
    if ('_trace/1' == sky.a.uri) {
        $('#dev-trace').prepend(sky.d.parent_x);
    } else if ('_trace/0' == sky.a.uri || !str && sky.d.dev) {
        $('#dev-trace').html(sky.d.trace_t);
    }

    if (!str)
        str = $('#trace').html();
    var trc = str;
    if (-1 != str.indexOf('<div class="error">'))
        $('#v-body h1').each(function () {
            $(this).css({color:'red', backgroundColor:'pink'});
        });
    sky.set_file_clk('#v-body');

    for (var a = []; m = str.match(/(TOP|SUB|BLK)\-VIEW: (\d+) (\S+) (\S+)(.*)/s); str = m[5]) {
        a.push({type:m[1], no:m[2], hnd:m[3], tpl:'^' == m[4] ? ('BLK' == m[1] ? 'injected-to-parent' : 'not-used') : m[4]});
        if ('TOP' == m[1])
            top = 'Top-view: <b>' + m[3] + '</b> &nbsp; Template: <b>' + a[a.length - 1].tpl + '</b>';
    }

    var csql = '?';
    if (m = str.match(/([\.\d]+ sec), SQL queries: (\d+)/s)) {
        csql = m[2];
        $('#tpl-list').next().find('span:eq(0)').html(m[1]);
    }

    if (1 == from || '' !== $('#master').html()) {
        var data = JSON.parse($.trim($('#trace div.dev-data:eq(0)').text()));
        //eval('var data = ' + $.trim($('#trace div.dev-data:eq(0)').html()) + ';');
        data.views = a;
        $('#tpl-list').html($('#tpl-list-copy').html()).find('a')
            .mouseenter(function() {
                if (sky.d.to)
                    clearTimeout(sky.d.to);
                sky.d.to = 0;
                var n = $(this).attr('n'), poh = sky.d.draw[n](data, trc)
                $('div#popup-in').html(poh).parent().show().css({left:$(this).offset().left});
            }).mouseleave(sky.d.mouseleave);
            $('div#popup').mouseenter(function() {
                if (sky.d.to)
                    clearTimeout(sky.d.to);
                sky.d.to = 0;
            }).mouseleave(sky.d.mouseleave);
        $('#tpl-list span:eq(0)').html(a.length);
        $('#tpl-list span:eq(1)').html(csql);
        $('#tpl-list span:eq(2)').html(data.classes.length);
        $('#master').html(top)
    }
};

sky.d.to = 0;
sky.d.mouseleave = function() {
    if (sky.d.to)
        clearTimeout(sky.d.to);
    sky.d.to = setTimeout(function() {
        $('div#popup').hide();
    }, 300);
};

sky.d.view = function() {
    if (!sky.d.trace_t)
        return;
    $('#v-tail input').val(sky.d.trace_t);
    $('#v-tail form').attr('action', sky.home + '_dev?view=' + sky.d.trace_x).submit();
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

sky.key[27] = function() { // Escape
    sky.d.close_box();
};

$(function() {
    $('body').prepend('<div id="popup" style="display:none"><div id="popup-in"></div></div>');
    sky.d.show_menu('_trace' != sky.a._0);
    sky.d.init();
    $('#drop-var').after('<input type="button" value="Yes" onclick="sky.d.drop()" />');
});
