
sky.d.top = function(tr) {
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

sky.d.trace_t = '';

sky.d.trace = function() {
    if ('' != $('#master').html())
        return;
    sky.d.trace_t = window.parent.document.getElementById('trace-t').innerHTML
    if ('_x0' == sky.a.uri) {
        $('.trace-t').html(sky.d.trace_t);
    } else if ('_x1' == sky.a.uri) {
        $('.trace-x').prepend(window.parent.document.getElementById('trace-x').innerHTML);
    }
    if ('_trace' != sky.a._0 && '' === $('#trace-h').html())
        $('#trace-h').html(sky.d.trace_t);
    sky.d.tracing = $('#trace').html();

    if ('_trace' == sky.a._0) {
        if (-1 != sky.d.tracing.indexOf('<div class="error">'))
            $('#v-body h1').each(function () {
                $(this).css({color:'red', backgroundColor:'pink'});
            });
        sky.set_file_clk('#v-body');
    }

    var m, s = sky.d.tracing, top = '';
    for (a = []; m = s.match(/(TOP|SUB|BLK)\-VIEW: (\S+) (\S+)(.*)/s); s = m[4]) {
        a.push({type:m[1], hnd:m[2], tpl:'^' == m[3] ? 'no-tpl' : m[3]});
        if ('TOP' == m[1])
            top = 'Top-view: <b>' + m[2] + '</b> &nbsp; Template: <b>' + a[a.length - 1].tpl + '</b>';
    }
    $('#master').html(top)
    $('#tpl-list span:eq(0)').html(a.length);
};

sky.d.view = function(x) {
    if (!sky.d.trace_t)
        return;
    $('#v-tail input').val(sky.d.trace_t);
    $('#v-tail form').attr('action', sky.home + '_dev?view=' + x).submit();
};

$(function() {
    /*$('#v-menu a').each(function () {
        $(this).click(function () {
            $('#v-menu a').each(function () {
            });
        });
    });*/

    //if ('_trace' == sky.a._0)
    sky.d.trace();

    sky.key[27] = function() { // Escape
        sky.d.close_box();
    };
});
