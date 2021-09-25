
String.prototype.az = function(search, start) {
    
};

var V = function(name, val) {
    if ('undefined' === typeof val) {
        var v = localStorage.getItem(name),
            defs = {
                main: 0
            };
        return null === v ? defs[name] : v;
    }
    return null === val ? localStorage.removeItem(name) : localStorage.setItem(name, val);
    //localStorage.clear(); ! delete ALL
};


var az = {
    $f: null,
    $el: null,
    $tk: null,
    F: {W:640, H:480},    // frame size current
    prev: {W:640, H:480}, // frame size previouse
    S: {},                // screen (window)
    doc: function(selector) {
        var doc = $(az.$f[0].contentWindow.document);
        return selector ? doc.find(selector) : doc;
    },
    root: function($var, $val) {
        document.documentElement.style.setProperty('--' + $var, $val);
    },
    mm: function(sign) {
        az.switch(az.F.W + sign * 10, az.F.H + sign * 10)
            || setTimeout('az.mm(' + sign + ')', 20);
    },
    resize: function() {
        az.S = {W: $(document.body).width(), H: $(document.body).height()};
        az.switch(az.F.W, az.F.H, 1);
    },
    _mx: 't',
    menu: function(x) {
        ajax('menu.' + (az._mx = x), {}, $('#v-menu ul'));
        $('#v-menu select:eq(0)').val(x);
    },
    switch: function(W, H, force, x, fn, tools) {
        if (fn) {
            if (force)
                az.swap();
            az.fn = fn;
            az.test();
            az.menu(x);
            if (tools) {
                tools = tools.split(' ');
                for (var id; id = tools.pop(); )
                    eval(`ajax('${id}', az.${id})`);
            }
        }
        if (!W) { // F8 key
            W = az.prev.W;
            H = az.prev.H;
            az.prev = az.F;
        } else if (!H) { // select dropdown
            var ary = $(W).find('option:selected').val().split(' x ');
            W = parseInt(ary[0]);
            H = parseInt(ary[1]);
        }
        // else direct W, H values
        var W_ = 0, H_ = 0, wh;
        if (W >= az.S.W - 250)
            W_ = W = az.S.W - 250;
        if (W <= 250)
            W_ = W = 250;
        if (H >= az.S.H - 127)
            H_ = H = az.S.H - 127;
        if (H <= 250)
            H_ = H = 250;

        if (W != az.F.W || H != az.F.H || force) {
            var op = $('#fsize option[value="' + (wh = W + ' x ' + H) + '"]');
            if (op[0]) {
                op.prop('selected', true);
            } else {
                $('#fsize option:last').prop('selected', true).val(wh).html(wh + '<sup>*</sup>');
            }
            $('#main').css({
                gridTemplateColumns: '150px ' + W + 'px ' + (az.S.W - 150 - W) + 'px',
                gridTemplateRows:    '27px '  + H + 'px ' + (az.S.H - 27  - H) + 'px'
            });
            az.F = {W:W, H:H};
            az.root('frame-w', W + 'px');
            az.root('frame-h', H + 'px');
        }

        return H_ && W_;
    },
    Vmain: function() {
        var s = az.F.W + ',' + az.F.H + ',' + az.div + ",'" + az._mx + "','" + az.fn + "'";
        V('main', s + az.tools());
    },
    save: function() {
        az.Vmain();
        ajax('save', {
            html: $('#code-body pre:eq(1)').html(),
            fn: az.fn
        }, function(r) {
            
        });
    },
    m_move: function(e) {
        var w = this == document;
        az.info(w ? `X:${e.clientX} Y:${e.clientY}` : `x:${e.clientX} y:${e.clientY}`, 0);
        if (az.mouse)
            az.mouse(w ? {X:e.clientX, Y:e.clientY} : {X:150 + e.clientX, Y:27 + e.clientY});
    },
    mouse: null,
    m_up: function() {
        $(document.body).css('userSelect', 'initial');
        az.doc('body').css('userSelect', 'initial');
        if (az.mouse)
            az.Vmain();
        az.mouse = null;
    },
    m_enter: function(e) {
        az.$el = $(this);
        az.info($(this).prop('tagName'), 1);
    },
    _catch: function() {//alert()
        az.$tk = az.$el;
        var s = az.$el.prop('tagName') + ' e=' + az.$el.attr('e');
        az.info(s, 2);
    },
    _bg: 0,
    bg: function() {
        if (az._bg = 1 - az._bg) {
            $('#v-menu').css('background', 'var(--bg)');
            az.root('border', 'none');
        } else {
            $('#v-menu').css('background', '#fff');
            az.root('border', '1px solid #ccc');
        }
    },
    get: function(el, pref) {
        var i, ary = el.className.split(' '), len = pref.length;
        for (i in ary)
            if (pref == ary[i].substr(0, len))
                return ary[i];
        return '';
    },
    set: function(el, name, len) {
        var ary = [], pref = name.substr(0, len);
        if (el.className)
            ary = el.className.split(' ');
        for (i in ary)
            if (pref == ary[i].substr(0, len))
                $(el).removeClass(ary[i])
        $(el).addClass(name)
    },
    info: function(html, pos) {
        $('#info span:eq(' + pos + ')').html(html);
    },
    style: function(el, name) {
        return getComputedStyle(el, null).getPropertyValue(name)
    },
    rgb2hex: function(r, g, b) {
        if (!g) {
            var ary = /(\d+),\s?(\d+),\s?(\d+)/.exec(r);
            r = parseInt(ary[1]), g = parseInt(ary[2]), b = parseInt(ary[3]);
        }
        return "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
    },
    init: function(id) {
        $(id).find('td').each(function() {
            $(this).css({
                cursor:'default'
            }).on('click', function() {
                if ($(this).hasClass('font-mono'))
                    return;
                $(this).css('textDecoration', 'underline');
                var bg = az.get(this, 'bg-'), el = $(id).find('td:eq(3)')[0];
                az.set(el, bg, 3);
                var hex = az.rgb2hex(az.style(this, 'background-color'));
                $(el).html(hex + ' ' + bg).css('color', az.style(this, 'color'));

   if(az.$tk) az.set(az.$tk[0], bg, 3);

            }).on('mouseenter', function() {
                if ($(this).hasClass('font-mono'))
                    return;
                var bg = az.get(this, 'bg-'), el = $(id).find('td:eq(2)')[0];
                az.set(el, bg, 3);
                bg = az.rgb2hex(az.style(this, 'background-color'));
                $(el).html(bg).css('color', az.style(this, 'color'));
            })
        });
    },
    html: function(str, cls) {
        str = str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        if (cls)
            str = `<span class="az-${cls}">` + str + '</span>';
        return str;
    },
    attr: function(el) {
        var ary = [], nn = el.nodeName.toLowerCase();
        $.each(el.attributes, function() {//if (this.specified)
            if ('id' == this.name) {
                ary.push('id="' + az.html(this.value, 'id') + '"');
            } else if ('class' == this.name) {
                ary.push('class="' + az.html(this.value, 'class') + '"');
            } else if ('href' == this.name || 'src' == this.name) {
                ary.push(this.name + '="' + az.html(this.value, 'link') + '"');
            } else {
                ary.push(this.name + '="' + az.html(this.value) + '"');
            }
        });
        ary = ary.join(' ');
        return '&lt;' + '<span class="az-tag">' + nn + '</span>' + (ary ? ' ' + ary : '') + '&gt;';
    },
    self_ct: [
        'br', 'input', 'img', 'meta', 'area', 'col', 'link', 'hr', 'source',
        'base', 'wbr', 'embed', 'param', 'track', 'command', 'keygen'
    ],
    self_ct2: [
        'path', 'rect', 'circle', 'ellipse', 'line', 'polygon', 'polyline',
        'animate', 'stop'
    ],
    tidy: function(html, indent = '', parent = 0) {
        var prevIsText, out = '', child = 0, simple = 0;

        $.each($.parseHTML(html, document, true), function(i, el) {
            var tt = 0, nn = el.nodeName.toLowerCase();
            if (nn == '#text') { // #cdata-section #document #document-fragment
                if (tt = $(el).text().trim())
                    out += indent + az.html(tt) + '\n';
            } else if (nn == '#comment') {
                out += indent + az.html('<!-- ' + el.data.trim().replace(/\n+/g, "\n") + ' -->\n', 'com');
            } else {
                var curr = $(el).html().trim();
                var depth = el.children[0] && nn != 'pre';
                if (depth) {
                    curr = az.tidy(curr, indent + '  ', el);
                    curr = curr.simple ? curr.out : '\n' + curr.out + indent;
                }
             $(el).html(curr);//??
                if (curr && ('script' == nn || 'style' == nn || 'pre' == nn))
                    curr = '\n' + curr + '\n' + indent;
                curr = az.attr(el) + curr; // el.outerHTML.trim()
                if (!el.hasChildNodes() && az.self_ct2.includes(nn)) {
                    curr = curr.replace(/&gt;$/, '/&gt;');
                } else if (!az.self_ct.includes(nn)) {
                    curr += '&lt;/' + '<span class="az-tag">' + nn + '</span>&gt;';
                }
                if (nn == 'br') {
                    out = (child ? '' : indent) + out.replace(/\s+$/gm, '') + curr + '\n';
                } else if (nn == 'a' && !depth && parent && 'LI' == parent.nodeName) {
                    out = curr;
                    simple = 1;
                } else if (!depth && prevIsText) {
                    out = out.replace(/\s+$/gm, '') + ' ' + curr + '\n';
                } else {
                    out += indent + curr + '\n';
                }
            }
            prevIsText = tt;
            child = 1;
        });
        return parent ? {out:out, simple:simple} : out.trimRight();
    },
    parse: function(s) {
        var re = '(<!doctype[^>]*>)\\s*'
               + '(<html[^>]*>)\\s*'
               + '(<head[^>]*>)([\\s\\S]+)</head>\\s*'
               + '(<body[^>]*>)([\\s\\S]+)</body>\\s*'
               + '</html>', m = s.match(new RegExp(re, 'i'));
        if (!m)
            return az.tidy(s);
        return az.html(m[1]) + az.html('\n' + m[2] + '\n' + m[3] + '\n') + az.tidy(m[4], '  ')
            + az.html('\n</head>\n') + az.html(m[5]) + '\n' + az.tidy(m[6]) + az.html('\n</body>\n</html>');
    },
    code: function(r) {
        $('#project-list').html(r.list);
        $('#code-head b').html(az.fn);
        html = az.parse(r.html.replaceAll('\r\n', '\n').replaceAll('\r', '\n'));
        var br = html.replace(/[^\n]/g, '').length;
        for (var i = 1, lines = '  1'; i <= br; lines += '\n' + ++i);
        $('#code-body pre:eq(0)').html(lines).next().html(html);
    },
    fn: 'index.html',
    test: function(fn) {
        if (fn)
            az.fn = fn;
        az.$f.attr('src', 'http://des.loc/tw/web/?_visual=' + az.fn).on('load', function (e) {
            ajax('code.' + az.fn, az.code);
            az.doc().mouseup(az.m_up).mousemove(az.m_move).find('body *').mouseenter(az.m_enter);
        });
    },
    div: 0,
    swap: function() {
        var r = $('#v-right').html();
        $('#v-right').html($('#tail').html());
        $('#tail').html(r)
        az.div = 1 - az.div;
    },
    m_clk: function(e, show) {
        var id = 'string' == typeof e ? e : '#popup',
            el = $(id)[0], hide = 'none' == el.style.display;
        if (el.hasAttribute('running') || hide && !show) // 'string' != typeof e
            return;
        if (!el.onanimationend) el.onanimationend = function () {
            if ($(this).hasClass('hide-a1'))
                $(this).hide();
            this.removeAttribute('running');
        };
        $(el).show().attr('running', 1).removeClass(hide ? 'hide-a1' : 'show-a1').addClass(hide ? 'show-a1' : 'hide-a1');
    },
    tools: function(id, html) {
        var t = $(az.div ? '#tail' : '#v-right');
        if (!id) {
            var ary = [];
            t.find('.tool').each(function () {
                ary.push(this.id.substr(2));
            });
            return ",'" + ary.join(" ") + "'";
        }
        t.find(id).remove();
        t.prepend(html);
    },
    utf8: function() {
        var rule = ['0', '110 10', '1110 10 10', '11110 10 10 10'], out = '<div id="t-utf8"><table>';
        for (var i = 0; i < 16; i++) {
            out += '<tr>';
            for (var k = 0; k < 16; k++) {
                out += '<td>&#9763;' + String.fromCodePoint(0x2220 + 16*i + k); + '</td>';
            }
            out += '</tr>';
        }
        out += '</table></div>';
        az.tools('#t-utf8', out)
    },
    colors: function(r) {
        az.tools('#t-colors', r.right)
    },
    htmlcolors: function(r) {
        az.tools('#t-htmlcolors', r)
    },
    text: function(r) {
        az.tools('#t-text', r)
    },
    icons: function(r) {
        az.tools('#t-icons', r)
    },
    css: function(el) {
        var i = 0, s = '', list = getComputedStyle(el);
        for (; i < list.length; i++)
            s += (1 + i) + ' ' + list[i] + ' = ' + list.getPropertyValue(list[i]) + '<br>';
        az.tools('#t-css').prepend(s)
    }
};

(function() {
    sky.a.error(function(r) {
        if (!r.soft)
            location.href = '_exception?' + r.err_no;
        $('#tail').html(r.catch_error);
    });
    sky.err = function(s) {
        $('#tail').html(s);
        //f1[0] ? ab.message(s, 0, 1) : alert(s); // red, no animation
    };
})();

$(function() {
    /*$('body').prepend('<div id="popup" style="display:none"></div>');
    (ab.aside = $('header').get(0) ? 120 : 0) || $('body').width('100%');
    $(window).scroll(ab.scroll);
    
body{
    -webkit-touch-callout: none;
    -webkit-user-select: none;
    -khtml-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
}
    
    */

    az.$f = $('iframe:first');

    $(window).resize(az.resize);
    az.resize();

    sky.key[27] = function() { // Escape
        var esc = $('.escape:last');
        esc.get(0) ? esc.click() : run();
    };
    sky.key[113] = function() { // F2
        $('.f2:first').click();
    };
    sky.key[115] = function() { // F4
        $('.f4:first').click();
    };
    sky.key[117] = function() { // F6
        $('.f6:first').click();
    };
    sky.key[119] = function() { // F8
        $('.f8:first').click();
    };
    sky.key[120] = function() { // F9
        az.swap();
    };
    sky.key[121] = az._catch; // F10

    $(document).click(az.m_clk).mouseup(az.m_up).mousemove(az.m_move).mouseenter(function () {
        az.info('-', 1);
    });
    az.doc().mouseup(az.m_up).mousemove(az.m_move);

    $('#mov-y, #mov-x').mousedown(function (e) {
        az.doc('body').css('userSelect', 'none');
        $(document.body).css('userSelect', 'none');
        var is_x = $(this).attr('id') == 'mov-x';
        az.mouse = function (pos) {
            az.switch(
                is_x ? pos.X - 150 : az.F.W,
                is_x ? az.F.H : pos.Y - 27
            );
        };
    });
    var v = V('main');
    if (v) {
        eval('az.switch(' + v + ')');
    } else {
        az.menu('t')
    }
});
