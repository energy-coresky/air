<?php
#__std
extract($_vars, EXTR_REFS);
trace('TPL: __std'); MVC::in_tpl(true);
if (false != ($sky->return || HEAVEN::J_FLY == $sky->fly && !$sky->no))
throw new Error('Return status do not match for file: ' . __FILE__);
# this template for fatal FLY=0
$ru = defined('LG') && 'ru' == LG;
$list = [ # production errors
    403 => $ru ? 'Ошибка 403 - Попытка взлома заблокирована' : 'Error 403 - Denied hacking attempt',
    404 => $ru ? 'Ошибка 404 - Страница не найдена'          : 'Error 404 - Page not found',
    500 => $ru ? 'Ошибка 500 - Внутренняя ошибка сервера'    : 'Error 500 - Internal server error',
];
$p_prev = $ru ? 'вернуться на предыдущую страницу' : 'return to previouse page';
$p_main = $ru ? 'открыть главную' : 'open main page';
$error = isset($list[$no]) ? $list[$no] : $list[500];
echo $redirect;
?><!doctype html><html>
<head><?php MVC::head() ?></head>
<body style="background:url(<?php echo html(PATH) ?>_img?cloud2);background-size:cover;margin:0 10px;<?php if (!$tracing): ?>text-align:center;<?php endif ?>">
<style>.error {background-color:red !important;color:#fff !important}</style>
    <?php if (!$tracing): ?><div style="display:inline-block; background:#fff; padding:10px; margin-top:15%"><?php endif ?>
    <h1><?php echo html($error) ?></h1>
    <a href="javascript:;" onclick="history.back()"><?php echo html($p_prev) ?></a>
    <?php if ($ru): ?> или<?php else: ?> or<?php endif ?>
    <a href="<?php echo PATH ?>"><?php echo html($p_main) ?></a>
    <?php if (DEV && !$sky->last_ware): ?>
        or <a href="javascript:;" onclick="dev('_trace/1')">open X-Tracing</a>
    <?php elseif (DEV): ?>
        or this link
        <input value="_<?php echo html($sky->last_ware) ?>?ware" size="33"/>
        <input type="button" onclick="location.href = $(this).prev().val()" value="Open"/>
    <?php endif ?>
    <?php if (!$tracing): ?></div><?php endif ?>
    <p><div id="trace-t"><?php echo $tracing ?></div></p>
    <div id="trace-x" x="1" style="display:none"></div>
</body>
</html>
<?php DEV::vars(get_defined_vars(), $sky->no);
MVC::in_tpl();
return '';
