
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
    if (-1 != str.indexOf('<div class="error">'))
        $('#v-body h1').each(function () {
            $(this).css({color:'red', backgroundColor:'pink'});
        });
    sky.set_file_clk('#v-body');

    for (var a = []; m = str.match(/(TOP|SUB|BLK)\-VIEW: (\S+) (\S+)(.*)/s); str = m[4]) {
        a.push({type:m[1], hnd:m[2], tpl:'^' == m[3] ? 'no-tpl' : m[3]});
        if ('TOP' == m[1])
            top = 'Top-view: <b>' + m[2] + '</b> &nbsp; Template: <b>' + a[a.length - 1].tpl + '</b>';
    }
    
    var c1 = a.length, c2 = '?', c3 = '?';
    if (m = str.match(/([\.\d]+ sec), SQL queries: (\d+)/s)) {
        c2 = m[2];
        $('#tpl-list').next().find('span:eq(0)').html(m[1]);
    }

    a = $('#trace div.dev-data:eq(0)');
    if (a[0]) {
        eval('var data = ' + $.trim(a.html()) + ';')
        str = '';
        var i = 0;
        Object.keys(data.classes).forEach(function (key) {
            i++;
            str += i + '. ' + data.classes[key] + " ";
        });
        c3 = i;
    }
    if (1 == from || '' !== $('#master').html()) {
        $('#tpl-list').html($('#tpl-list-copy').html());
        $('#tpl-list span:eq(0)').html(c1);
        $('#tpl-list span:eq(1)').html(c2);
        $('#tpl-list span:eq(2)').html(c3);
        $('#master').html(top)
    }
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

$(function() {
    sky.d.show_menu('_trace' != sky.a._0);
    sky.d.init();

    sky.key[27] = function() { // Escape
        sky.d.close_box();
    };

    $('#drop-var').after('<input type="button" value="Yes" onclick="sky.d.drop()" />');
});
