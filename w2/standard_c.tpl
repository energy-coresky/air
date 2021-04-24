#.jet core template

#.dev //////////////////////////////////////////////////////////////////////////////
{- this jet template used by `standard_c` class -}

<h1><u>DEV instance only</u> configuration and information</h1>
<fieldset><legend>DEV instance settings, {{$_SERVER['REMOTE_ADDR']}}</legend>
{! $v_form !}
</fieldset>
<fieldset id="dev-tasks"><legend>Other tasks</legend>
    <div><a @href(sky.g.box()))>Open SkyGate</a></div>
    <div><a @href(ajax(['_lang','list'],box))>Open SkyLang</a></div>
    <div><a @href(ajax(['_inst','database'],box))>Prepare Install</a></div>
    <div><a @href(ajax('',alert,'_drop'))>Drop All Cache</a></div>
    @if('WINNT'==PHP_OS)<div><a href="adm?get_dev">Open DEV.SKY.</a></div>~if
</fieldset>
<style>
#dev-tasks div { width:100px; background:#ffd; float:left; margin-right:30px; padding:10px; }
</style>
#.dev

#.404 this template for 404 (or others) error inside app layout
@php isset($tracing) or $tracing = ''; ~php
#if(is_file(($sky->style ? DIR_V . "/$sky->style" : DIR_V) . "/error.php"))@inc(error.404)
#else
    <h1>404 - Page not found</h1>
    <pre>{!$tracing!}</pre>
#end
#.404

#.exception //////////////////////////////////////////////////////////////////////////////
@php # this template for Exceptions and die() functions
$ru = DEFAULT_LG && 'ru' == LG;
$list = [ # production errors
    403 => $ru ? 'Ошибка 403 - Попытка взлома заблокирована' : 'Error 403 - Denied hacking attempt',
    404 => $ru ? 'Ошибка 404 - Страница не найдена'          : 'Error 404 - Page not found',
    500 => $ru ? 'Ошибка 500 - Внутренняя ошибка сервера'    : 'Error 500 - Internal server error',
    11  => $ru ? 'Для работы этого приложения требуется:'    : 'This application require to be tunning on:',
];
$p_prev = $ru ? 'вернуться на предыдущую страницу' : 'return to previouse page';
$p_main = $ru ? 'открыть главную' : 'open main page';
isset($error) && $error && $sky->debug or $error = isset($list[$no]) ? $list[$no] : $list[500];
~php
#if(is_file(($sky->style ? DIR_V . "/$sky->style" : DIR_V) . "/error.php"))@inc(error.exception)
#else
<!doctype html><html>
<head>@head()</head>
<body style="background:url({!PATH!}img/cloud.jpg);background-size:cover;margin:0 10px;@if(!$tracing)text-align:center;~if">
#.ex_inner
    @if(!$tracing)<div style="display:inline-block; background:#fff; padding:10px; margin-top:15%">~if
    <h1>{{$error}}</h1>
    @if(11 == $no)
        @if($ky[1])<p>Cookies - {!sprintf($ky[3] ? span_r : span_g, $ky[3] ? 'FAIL' : 'OK')!}</p>~if
        @if($ky[2])<p>Javascript - {!sprintf($ky[4] ? span_r : span_g, $ky[4] ? 'FAIL' : 'OK')!}</p>~if
        <a href="{!PATH!}_exception">@if($ru)я устранил проблему @else I fix the problem ~if</a>
    @else
        <a href="javascript:;" onclick="history.back()">{{$p_prev}}</a>
        @if($ru) или@else or~if
        <a href="{!PATH!}">{{$p_main}}</a>
    ~if
    @if(!$tracing)</div>
    @else
        <p><div id="err-top">{!$tracing!}</div></p>
    ~if
#.ex_inner
</body>
</html>
#end
#.exception

#.inst //////////////////////////////////////////////////////////////////////////////
<div id="inst">
<form>
<input type="hidden" name="step" value="">
<h1>Prepare app installation</h1>
<input type="button" value="database" onclick="ajax('database', box)"/>
<input type="button" value="files" onclick="ajax('files', box)"/>
<input type="button" value="system" onclick="ajax('system', box)"/>
<b style="margin-left:77px">{{$v_title or ''}}</b>
@if('files'!=$v_page)<input type="button" value="save" onclick="sky.g.save(this)">~if
 <progress style="display:none"></progress>
<br><hr><div>
@if('system'==$v_page)@inc(.system)
@elseif('files'==$v_page)@inc(.files)
@else@inc(.database)
~if
</div><pre id="log" style="display:none"></pre>
</form>
</div>
<style>
#inst {margin:10px 15px;}
.mod { width:120px; display:inline-block}
.md-span { color: blue}
</style>
<script>
var c_ = false, r_ = false, proc = false;
sky.g.save = function(el) {
    if (el) { // button hit
        @if('system'==$v_page)
        if (!$('input[name=do]').is(':checked'))
            return ajax('system', $('#inst form').serialize(), sky.false);
        ~if
        $('#inst progress').show();
        $('#log').show().prev().hide();
        $(el).val((proc = !proc) ? 'pause' : 'continue');
        return sky.g.save();
    }
    if (proc) ajax('{{$v_page}}', $('#inst form').serialize(), function(r) {
        if (r.end)
            proc = false;
        $('#log').append(r.str + "\n");
        $('#inst progress').val(r.progress).attr('max', r.max);
        $('#inst input[name=step]').val(r.end ? '' : r.step);
        sky.g.save();
    });
};
</script>
#.inst

#.database                          -- fix SQL::$dd->pref
<p>
<label>empty file from the start <input type="checkbox" name="empty"></label>
&nbsp; &nbsp; current table's prefix: <input value="{{SQL::$dd->pref}}" name="pref" size="5">
<label><input type="checkbox" name="mixed"> mixed</label>
&nbsp; &nbsp; use prefix: <input value="" name="pref_use" size="5">
<table>
<tr><th>Tables</th><th>Create table</th><th>Insert rows</th></tr>
<tr><td></td><td><a @href($('#inst input.c').prop('checked',c_=!c_))>[un]check all</a></td>
<td><a @href($('#inst input.r').prop('checked',r_=!r_))>[un]check all</a></td></tr>
@for($v_tables as $one)
    <tr><td>{{$one}}</td>
    <td><input type="checkbox" class="c" name="c_{{$one}}"{{in_array("c_$one", $v_mem) ? ' checked' : ''}}></td>
    <td><input type="checkbox" class="r" name="r_{{$one}}"{{in_array("r_$one", $v_mem) ? ' checked' : ''}}></td></tr>
~for
</table>
#.database

#.files
<a @href(sky.g.walk(1))>expand all</a>@php $cnt = 0; ~php
<p>
@for($v_files as $one => $ary)
    <div class="md">
        <a @href(sky.g.files(this, 2))>{!$one!}</a>
        <input type="hidden" name="{!$one!}" value="{{$v_exd[$one] or 0}}"> <span class="md-span"></span>
        <a @href(sky.toggle(this))>&gt;&gt;&gt;</a>
        <div style="display:none; min-height:10px;">@php $cnt += count($ary[1]); ~php
        @for($ary[1] as $file)
            <div style="margin-left:20px">
                <label><input type="checkbox" name="{{$file}}"{{isset($v_exf[$file]) ? ' checked' : ''}}> {{$file}}</label>
            </div>
        ~for
        </div>
    </div>
~for
<p>
When load: dirs-files &nbsp; Total {{count($v_files)}}-{{$cnt}}
<script>
sky.g.skip = [];
sky.g.walk = function(is_expand) {
    var one, ary = function(name, x) {
        if (!sky.g.skip.includes(name) && !x)
            sky.g.skip.push(name);
        if (sky.g.skip.includes(name) && x)
            sky.g.skip.splice(sky.g.skip.indexOf(name), 1);
    };
    $('#inst div.md').each(function() {
        var flag = false, h = $(this).find('input[type=hidden]'), v = parseInt(h.val()), fn = h.attr('name');
        if (is_expand)
            return h.next().next().next().css('display', v ? 'none' : '');
        switch (v) {
            case 0:
                h.next().hide().next().show();
                h.prev().css({textDecoration:'none', color:''});
                ary(fn, 1);
                break;
            case 1: // skip dir
                h.next().hide().next().hide().next().hide();
                h.prev().css({textDecoration:'line-through', color:'red'});
                ary(fn, 0);
                break;
            case 2:
                h.next().html('use dir, excludes all files inside').show().next().hide().next().hide();
                h.prev().css({textDecoration:'none', color:''});
                ary(fn, 1);
                break;
        }
        for (one of sky.g.skip)
            if (fn.substr(0, one.length) == one && one != fn)
                flag = true;
        flag ? $(this).hide() : $(this).show();
    });
};
sky.g.walk();
$('div.md input[type=checkbox]').change(function() {
    sky.g.files($.trim($(this).parent().text()), this.checked ? 1 : 0);
});
sky.g.files = function(fn, dir) {
    var m = dir;
    if (2 == dir) {
        var hidden = $(fn).next();
        m = parseInt(hidden.val());
        hidden.val(m = 2 == m ? 0 : 1 + m);
        sky.g.walk();
        fn = $(fn).text();
    }
    ajax('files', {fn:fn, dir:2 == dir ? 1 : 0, m:m}, sky.false);
};
</script>
#.files

#.system
<p>Filename <input name="fn" size="7" value="{{$row->fn or 'app'}}">
&nbsp; &nbsp; <label><input type="checkbox" name="do"> do make</label>
&nbsp; &nbsp; <label><input type="checkbox" name="bz2"> compress data using bz2</label>
&nbsp; &nbsp; App version: <input name="vapp" size="7" value="{{$v_vapp}}">
&nbsp; &nbsp; Coresky version: <b>{{$v_vcore}}</b>
<p>SQL compiled {{date(DATE_DT, $row->ts_sql)}} ^<b>TR:</b> {{$row->count_tr}}
&nbsp; &nbsp; <label><input checked type="checkbox" name="sql"> include</label>
<fieldset><legend><small>check required modules</small></legend>
@for($v_modules as $one)
    @if('Core'!==($name=$one))
        @if('apache2handler'===$one) @php $name="<small>$name</small>" ~php ~if
        <div class="mod"><label>
            <input type="checkbox" name="m_{{$one}}"{{in_array($one, $v_mem) ? ' checked' : ''}}> {!$name!}
        </label></div>
    ~if
~for
</fieldset>
<p>
PHP version &gt;= <input name="vphp" value="{{$row->vphp or PHP_VERSION}}" size="5">
&nbsp; &nbsp; <label><input type="checkbox" name="and"{{$v_and ? ' checked' : ''}}> and</label>
&nbsp; &nbsp; PHP &lt;= <input name="vphp2" value="{{$row->vphp2 or '7.1'}}" size="5">
&nbsp; &nbsp; MySQL version &gt;= <input name="vmysql" value="{{$row->vmysql or $v_mysql}}" size="5">
<p>Description:<br><textarea name="desc" rows="5">{{$row->desc or ''}}</textarea>
#.system

#.crop_code //////////////////////////////////////////////////////////////////////////////
<table width="100%" id="crop" cellpadding="5">
<tr>
    <td width="75%">
        <img style="position:absolute"/><div style="position:relative; z-index:777; border:1px dashed red"></div>
    </td>
    <td width="25%" style="border-left:2px solid #bbb; padding:5px;"><h2>Crop an image</h2>
        Original picture size: <span></span><hr>
        Required size: <select>{! $v_opt !}</select><br><br>
        Region: <input type="range" min="20" max="100" value="50"/><br><br>
        <button>finish</button> &nbsp; mode: <span style="background:#bfb">move</span>
    </td>
</tr>
</table>
#.crop_code

#.lock //////////////////////////////////////////////////////////////////////////////
<script>
sky.g.show = function() {
    sky.g.box("{{$sky->ca_path['ctrl']}}", "{{$sky->ca_path['func']}}", $('#err-top div:eq(0)').html());
}
</script>
#.lock

#.gate.virt.delete //////////////////////////////////////////////////////////////////////////////
<div id="gate">
@if($v_error){!$v_error!}~if
<fieldset id="contr"><legend>Controllers</legend>
@for($v_list as $one => $ex)
    <input style="{!$one==$y_1 and 'color:green;'!}{!is_int($ex)?($ex?'':'background:#fcc'):'background:#ffc'!}"
        value="{!$one!}" type="button" onclick="ajax('{!is_int($ex)?$one:$ex!}',box)">
~for
</fieldset>
@if($y_1)
    <h1 style="margin-top:15px">Controller
        @if($v_list[$y_1])
            `<span style="color:#000"><i>{{$v_h1}}.php</i></span>`
        @else
            `<span style="color:red"><i>{{$v_h1}}.php</i></span>` (not exists)
            <input type="button" onclick="ajax('{{$y_1}}',box,'_delete')" value="delete"/>
        ~if
    </h1>
    @if('default_c'!=$v_h1 && $v_list[$y_1])
        <b>Virtual references</b>: <input size="70" value="{{$v_virtuals}}"/>
        <input type="button" value="save" onclick="ajax('{{$y_1}}', {v:$(this).prev().val()}, box, '_virt')"/>
        <br><span style="margin-left:200px"><i>list virtuals via space</i></span>
    ~if
    <label class="fr">
        Show code <input type="checkbox"{{$v_cshow}} onchange="ajax('{!$y_1!}',{s:this.checked},box)"/>
    </label><br><br>
    <div style="display:none" id="c23-tpl">@view(c23_edit)</div>
    @for($e_func)
    <fieldset>
        @if($row->delete)
            <legend style="background:pink;color:#000">{{$row->func}}</legend>
        @else
            <legend><b>{{$row->func}}</b>{{$row->pars}}</legend>
        ~if
        <input type="hidden" name="args" value="{{$row->pars}}"/>
        @if ($v_cshow)
            <div class="func-code" style="overflow:auto;padding: 7px;">{!$row->code!}</div>
            <div class="the-url" style="overflow:auto">
                {{PROTO}}://{!$row->url!}
            </div>
        @else
            <div class="function">@inc(.edit)</div>
        ~if
    </fieldset>
    ~for
@else
    <h1><u>Sky Gate</u>: No controllers</h1>
~if
</div>
@inc(.jscss)
#.gate.virt.delete

#.edit.save //////////////////////////////////////////////////////////////////////////////
<table width="100%"><tr>
    <td width="33%" valign="top">
        {!$row->c1!}
    </td>
    <td width="33%" valign="top" id="column-2">
        <u>End-point address</u>:<br><br>
        {!$row->c2!}
    </td>
    <td width="33%" valign="top">
        @if(!$row->code)
        <div class="fr" style="text-align:right">
            @if($row->delete)
                <input type="button" class="edit" value="delete" onclick="ajax('{{$y_1}}.{{$row->func}}',box,'_delete')"/>
            ~if
            <input type="button" class="edit" value="edit" onclick="sky.g.edit(this,'{{$y_1}}.{{$row->func}}')"/>
        </div>
        ~if
        <u>Postfields or body</u>:<br><br>
        {!$row->c3!}
    </td>
</tr></table>
@if($row->code)
    <table width="100%"><tr>
        <td width="85%" style="background:#fff;border:none;padding:10px;">
            <div class="fl" style="position:absolute">
                <div class="fl" style="position:relative;top:-18px; left:120px; background:#fff;">
                    <label><input type="checkbox" name="production"{{$row->prod}}>show production code</label>
                </div>
            </div>
            <div id="func-code" style="overflow:auto">{!$row->code!}</div>
        </td>
        <td width="15%" valign="top" style="border:none" onmouseenter="sky.g.code(this,'{{$sky->_1}}')">
            <input type="button" value="save" onclick="sky.g.save(this,'{{$sky->_1}}')"/>
            <input type="button" value="cancel" onclick="sky.g.cancel(this)"/>
        </td>
    </tr></table>
    <div id="the-url" style="overflow:auto">
        {{PROTO}}://{!$row->url!}
    </div>
@elseif($row->error)
    <table width="100%">
        <tr><td style="background:pink; padding:5px 10px; text-align:center; color:#f00;">{{$row->error}}</td></tr>
    </table>
~if
#.edit.save

#.c23_edit //////////////////////////////////////////////////////////////////////////////
<div class="c23-tpl" style="background:{!$v_isaddr?'#efe':'#fee'!}">
    <a class="red-link fr" @href(sky.g.hide(this))>[X]</a>
    <input name="kname[]" size="6" placeholder="key name" value="{{$v_kname}}"/>
    <input name="key[]" size="15" placeholder="key" value="{{$v_key}}"/>
    <div style="margin-top:2px">
        <input name="vname[]" size="6" placeholder="val name" value="{{$v_vname}}"/>
        <input name="val[]" size="15" placeholder="val" value="{{$v_val}}"/>
        <label>
            ns<input type="checkbox" onclick="$(this).next().val(this.checked?1:0)"{!$v_chk and ' checked'!}/>
            <input type="hidden" name="chk[]" value="{{$v_chk}}"/>
        </label>
    </div>
</div>
#.c23_edit

#.c23_view //////////////////////////////////////////////////////////////////////////////
<div class="c23-view" style="background:{!$v_isaddr?'#efe':'#fee'!}">
    <div class="fr" style="color:red">{!$v_ns!}</div>
    {{$v_data}}
</div>
#.c23_view

#.jscss //////////////////////////////////////////////////////////////////////////////
<script>
sky.g.edit = function(el, func, scroll) {
    sky.g.mem = func;
    $('input.edit').hide();
    el = $(el).parents('.function');
    sky.g.html = el.html();
    ajax(encodeURIComponent(func), function(r) {
        el.html(r).parent().css({background:'rgba(173,228,255,0.5)',borderBottom:'3px solid #77f'}).find(':checkbox').click(function() {
            sky.g.code(el, func);
        });
        if (scroll)
            el.get(0).scrollIntoView({block:'center',behavior:'smooth'});
        $('#func-code').width($('#box-in').width() * 0.8);
    }, '_edit');
};
sky.g.code = function(el, func) {
    ajax(encodeURIComponent(func), $(el).parents('fieldset:eq(0)').serialize(), function(r) {
        $('#func-code').html(r.code);
        $('#the-url').html(r.url);
    }, '_code');
};
sky.g.cancel = function(el) {
    $(el).parents('.function').html(sky.g.html).parent().css({background:'',borderBottom:''});
    $('input.edit').show();
};
sky.g.save = function(el, func) {
    ajax(encodeURIComponent(func), $(el).parents('fieldset:eq(0)').serialize(), function(r) {
        $(el).parents('.function:eq(0)').html(r).parent().css({background:'#e7ebf2', borderBottom:''});
        $('input.edit').show();
    }, '_save');
};
sky.g.tpl = function(el, v) {
    var div = $($('#c23-tpl').html()).insertBefore(el).css({background:v ? '#efe' : '#fee'});
    el = div.find("input:checkbox");
    v && $('input[name=cnt-addr]').val(1 + parseInt($('input[name=cnt-addr]').val()));
    el.click(function() {
        sky.g.code(el, sky.g.mem);
    });
};
sky.g.hide = function(el) {
    if ($(el).parents('#column-2').attr('id'))
        $('input[name=cnt-addr]').val(parseInt($('input[name=cnt-addr]').val()) - 1);
    var el2 = $(el).parents('.c23-tpl'), el3 = el2.parent();
    el2.remove();
    sky.g.code(el3, sky.g.mem);
};
</script>

<style>
fieldset { border:none; background:#e7ebf2; margin-bottom:10px; padding-top:0px; }
fieldset legend { position:relative;top:-7px; }
#gate { margin:10px 15px }
#gate legend { font-size:14px; }
div.function { margin-top:-9px; }
.c23-tpl {
    width:95%; margin:0 0 5px 0; padding:3px;
    border-bottom:1px solid #555; border-right:1px solid #555; border-left:1px solid #ddd; border-top:1px solid #ddd;
}
.c23-view {
    width:95%; margin:0 0 5px 0; padding:3px; font-family:monospace;
    border-bottom:1px solid #555; border-right:1px solid #555; border-left:1px solid #ddd; border-top:1px solid #ddd;
}
#func-code code, .func-code, .func-code code {
    font:normal 12px monospace;
    background: #fff;
}
#the-url, .the-url {
    font:normal 14px monospace;
    background: #ffc;
    padding: 2px 5px;
}
</style>
#.jscss

