#.jet core template

#.table //////////////////////////////////////////////////////////////////////////////
CREATE TABLE `{{SQL::$dd->pref}}language` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `lg` char(2) NOT NULL,
  `name` varchar(32) NOT NULL,
  `flag` int(11) DEFAULT 1,
  `tmemo` mediumtext,
  `dt` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
{!$obj->sql!}
#.table

#.generate
formal tpl
#.generate

#.list.fix.store /////////////////////////////////////////////////////////////////////////
<div id="lang">
@if(1 == $obj->error)
    <h1>Please setup constant DEBUG to 1</h1>
@elseif(2 == $obj->error)
    <h1>Array $sky->lg is not set :(</h1>
@elseif(3 == $obj->error)
    <h1>DEFAULT_LG is not in array $sky->lg</h1>
@elseif(4 == $obj->error)
    <h1>The table `{{SQL::$dd->pref}}language` is absent. Create?</h1>
    <pre>@inc(.table)</pre>
    <input value="Yes!" type="button" onclick="ajax('fix',{sql:$(this).prev().text()},box)"/>
@elseif(5 == $obj->error)
    <h1>Languages not match (table and $sky->lg). Fix?</h1>
    <pre>{!$obj->sql!}</pre>
    <input value="Yes!" type="button" onclick="ajax('fix',{sql:$(this).prev().text()},box)"/>
@else
    <fieldset><legend>Languages</legend>
    @for($obj->list as $one)
        <input style="{!$one==DEFAULT_LG and 'text-transform:uppercase;'!}{!$one==$obj->lg and 'background:#afa;'!}"
            value="{{$one}}" type="button" onclick="ajax('list.{{$one}}',box)"/>
    ~for
        sorted: <b><u>{{$obj->nsort?'no':'yes'}}</u></b>, sync: <b><u id="sync">{{$obj->nsync?'no':'yes'}}</u></b>
    <div class="fr">Legend:
        <table style="display:inline-table">
            <tr>
            <td width="7" bgcolor="yellow"></td><td>Key longer then value</td>
            <td width="7" bgcolor="red"></td><td>Duplicated</td>
            <td width="7" bgcolor="pink"></td><td>Not translated</td>
            </tr>
        </table>
    </div>
    </fieldset>
    @if(!$obj->lg)
        <h1>Sky Languages: you will redirected..</h1>
        <script>setTimeout("ajax('list.{{DEFAULT_LG}}',box)", 500)</script>
    @else
        <p>
        <div class="fr" style="margin:0 15px 10px 0;">
            <input value="parse application" type="button" onclick="sky.g.parse()"/>
            <input value="generate files" type="button" onclick="sky.g.generate()"/>
            <input value="add new item" type="button" onclick="sky.g.all(0, 0)"/>
        </div>
        <div class="fl" style="margin:0 0 10px 15px;">
            <input value="sort items" type="button" onclick="ajax('list.{{DEFAULT_LG}}',{sort:1},box)"/> total: <b>{{$e_list->cnt}}</b>
        </div>
        <table width="100%" cellspacing="0" cellpadding="2" class="clist_data">
        <tr id="lang-head">
        <th colspan="2" width="2%"></th>
        <th width="39%">{{DEFAULT_LG}} (default)</th>
        <th width="7%" style="text-align:center">ID</th>
        <th width="1%"></th>
        <th width="37%">{{$obj->lg}}</th>
        <th width="14%">KEY</th>
        </tr>
        @for($e_list)
            @if($row->char)
                <tr>
                <td colspan="2"></td>
                <td style="text-align:right"><a style="font:bold 17px arial" name="{{strtolower($row->char)}}">{{$row->char}}</a></td>
                <td colspan="4"></td>
                </tr>
            ~if
            <tr>
            <td{!$row->yell and ' bgcolor="yellow"'!}></td>
            <td{!$row->red and ' bgcolor="red"'!}></td>
            <td><a @href(sky.g.delete({{$row->id}}))><img src="img/a_del.gif"/></a> {{$row->val}}</td>
            <td style="text-align:right">{{$row->id}} <a @href(sky.g.edit(this, {{$row->id}}))><img src="img/a_upd.gif"/></a></td>
            <td{!$row->pink and ' bgcolor="pink"'!}></td>
            <td>{{$row->val2}}</td>
            <td><a @href(sky.g.const(this,{{$row->id}}))><img src="img/a_upd.gif"/></a> {{$row->key}}</td>
            </tr>
        ~for
        </table>
        Items: {{$e_list->cnt}}
    ~if
~if
</div>
@inc(.jscss)
#.list.fix.store

#.const /////////////////////////////////////////////////////////////////////////
<input type="hidden" name="const-prev" value="{{$v_val}}"/>
<input id="tr-textarea" value="{{$v_val}}"/>
<a @href($(this).prev().val(''))><img src="img/a_del.gif"/></a>
<input onclick="sky.g.cancel()" type="button" value="cancel"/>
<input onclick="sky.g.save({{$_POST['id']}}, 1)" type="button" value="OK"/>
#.const

#.edit /////////////////////////////////////////////////////////////////////////
<textarea id="tr-textarea" rows="2" cols="40">{{$v_val}}</textarea><br/>
<input onclick="sky.g.cancel()" type="button" value="cancel"/>
<input onclick="sky.g.save({{$_POST['id']}}, 0)" type="button" value="OK"/>
#.edit

#.all /////////////////////////////////////////////////////////////////////////
<td colspan="7">
<form id="all-edit">
@for($e_list)
    @if(1==$a)
        <dl><dt><u>const</u>:</dt>
        <dd>
            <input type="hidden" name="const-prev" value="{{$row->const}}"/>
            <input size="30" name="const" value="{{$row->const}}"/>
            <a @href($(this).prev().val(''))><img src="img/a_del.gif"/></a>
        </dd>
        </dl>
    ~if
    <dl><dt>@if($row->lg==DEFAULT_LG)default language - ~if{{$row->lg}}:</dt>
    <dd>
        <input size="60" name="{{$row->lg}}" value="{{$row->val}}"
        @if($row->lg==DEFAULT_LG)
            onkeyup="sky.g.key(this.value)"/>
            <label><input type="checkbox" onclick="sky.g.chk = this.checked"/> sync input</label>
        @else class="ndef-lg"/>
        ~if
    </dd>
    </dl>
~for
<dl><dt>&nbsp;</dt>
<dd>
    <input onclick="sky.g.cancel()" type="button" value="cancel"/>
    <input onclick="sky.g.all(this, {{$v_id}}, 1)" type="button" value="save"/>
</dd>
</dl>
</form>
</td>
#.all

#.jscss //////////////////////////////////////////////////////////////////////////////
<script>
sky.g.editing = false;
sky.g.info = 'Please finish current editing before open new one';
sky.g.delete = function(id) {
    if (confirm('Are you sure? Delete item?'))
        ajax('fix.{{$obj->lg}}', {delete:id}, box);
};
sky.g.all = function(el, id, is_save) {
    if (is_save) {
        var s = $('#all-edit input[name=const]').val();
        if ('' !== s && !s.match(/^[a-z\d_]+$/i))
            return alert('Use identifiers chars only!');
        ajax('store.' + id, $('#all-edit').serialize(), function(r) {
            if ('1' == r)
                return alert('Identifier non unique!');
            box(r);
        });
    } else {
        if (el) {
            el = $(el).parents('tr');
        } else {
            el = $('#lang-head').before('<tr></tr>').prev();
        }
        sky.g.editing = [el, el.html()];
        ajax('all.' + id, {}, function(r) {
            el.html(r);
        });
    }
};
sky.g.edit = function(el, id) {
    if (sky.g.editing)
        return alert(sky.g.info);
    if ({{(int)($obj->lg==DEFAULT_LG)}})
        return sky.g.all(el, id, 0);
    el = $(el).parent().next().next();
    sky.g.editing = [el, el.html()];
    ajax('edit.{{$obj->lg}}', {id:id}, function(r) {
        el.html(r);
    });
};
sky.g.const = function(el, id) {
    if (sky.g.editing)
        return alert(sky.g.info);
    el = $(el).parent();
    sky.g.editing = [el, el.html()];
    ajax('const.{{$obj->lg}}', {id:id}, function(r) {
        el.html(r);
    });
};
sky.g.cancel = function() {
    sky.g.editing[0].html(sky.g.editing[1]);
    sky.g.editing = false;
};
sky.g.save = function(id, is_const) {
    var s = $('#tr-textarea').val();
    if (is_const && '' !== s && !s.match(/^[a-z\d_]+$/i))
        return alert('Use identifiers chars only!');
    var cp = is_const ? $('input[name=const-prev]').val() : '~';
    ajax('fix.{{$obj->lg}}', {save:s, id:id, 'const-prev':cp}, function(r) {
        if ('1' == r)
            return alert('Identifier non unique!');
        box(r);
    });
};
sky.g.generate = function() {
    ajax('generate.{{$obj->lg}}', function(r) {
        $('#sync').html('yes').css('background', '#afa');
        setTimeout("$('#sync').css('background', '')", 500);
    });
};
sky.g.chk = 0;
sky.g.key = function(v) {
    sky.g.chk && $('input.ndef-lg').val(v);
};
sky.g.parse = function(v) {
    ajax('test', {v: v || ''}, function(r) {
        var s, i, a = r.split(' '), tbl = $('#lang-head').parent();
        if ('.' == r) {
            tbl.css('background', 'gold');
            setTimeout(function() {
                tbl.css('background', '');
            }, 500);
        } else {
            a.shift(), a.shift(), a.shift();
            for (i in a) {
                s = a[i];
                tbl.find('tr').each(function() {
                    var td = $(this).find('td:eq(6)');
                    if (td && $.trim(td.text()) == s)
                        $(this).css('background', '#cfc');
                });
            }
            sky.g.parse(r);
        }
    });
};
</script>

<style>
#lang { margin:10px 15px }
#lang legend { font-size:14px; }
</style>
#.jscss

