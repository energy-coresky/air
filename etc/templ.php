<!doctype html>
<html>
<head><title>Install AB.SKY.</title>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<meta name="surl-path" content="" />
<script src="pub/jquery.min.js"></script>
<script src="pub/sky.js"></script>
<script>sky.tz=0</script>
<link rel="stylesheet" href="pub/sky.css" />
<link href="favicon.ico" rel="shortcut icon" type="image/x-icon" />
<script>
$(function() {
	$(window).resize(sky.app_height = function() {
		var wh = $(window).height();
		$('#page').css({height:wh - 28});
	});
	sky.app_height();
});
</script>
<style>
#page { margin:8px auto;width:600px; padding:5px 100px; }
a, h1 { color: #3d7098; }
h1 { font-size: 25px; margin-top: 30px; border-bottom: 4px solid #3d7098; }
table, td, #page { background: white; font-family: arial, verdana; font-size: 90% }
a:hover { text-decoration: none; color: white; background-color: #3d7098; }
</style>
</head>
<body style="margin:0; display:inline-block; width:100%; background:lightblue;">
	<div id="page">
		<h1>Install AB.SKY.</h1>
		<p>
		<a href="#" onclick="box(11)">box</a>
	</div>
</body>
</html>