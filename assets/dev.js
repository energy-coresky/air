
sky.d.top = function(tr) {

    
};
sky.d.trace = function() {
    if (-1 != sky.d.tracing.indexOf('<div class="error">'))
        $('#v-body h1').each(function () {
            $(this).css({color:'red', backgroundColor:'pink'});
        });
    sky.set_file_clk('#v-body');
};

sky.d.view = function(x) {
    $('#v-tail input').val(sky.d.tracing);
    $('#v-tail form').attr('action', sky.home + '_dev?view=' + x).submit();
};

$(function() {
    /*$('#v-menu a').each(function () {
        $(this).click(function () {
            $('#v-menu a').each(function () {
            });
        });
    });*/
    var m, s = sky.d.tracing, top = '';
    for (a = []; m = s.match(/(TOP|SUB)\-VIEW: (\S+) (\S+)(.*)/s); s = m[4]) {
        a.push({type:m[1], hnd:m[2], tpl:'^' == m[3] ? 'no-tpl' : m[3]});
        if ('TOP' == m[1])
            top = 'Top:&nbsp;<b>' + m[2] + '</b> &nbsp; Template: <b>' + a[a.length - 1].tpl + '</b>';
    }
    $('#master').html(top)
    $('#tpl-list span:eq(0)').html(a.length);

});
