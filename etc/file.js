
sky.crop = function(r) {
	var td = $('table#crop td:eq(0)'), td_w = td.width(), td_h = $('#box-in').height() - 15,
		img = $('table#crop img:eq(0)'), v, x, y, mn, sz, left, top,
		reg = td.find('div');
	$('table#crop span:eq(0)').text(r.width + ' x ' + r.height);
	td.height(td_h);
	img.attr('src', 'file?id' + r.id + '&_0').on('load', function() {
		img.css({
			width: (v = r.width / r.height > td_w / td_h) ? td_w : 'auto',
			height: v ? 'auto' : 'inherit'
		});
		x = img.width();
		y = img.height();
		mn = x > y ? y : x;
		reg.css({width: sz = mn / 2 - 2, height: sz}).on('click', function(e) { // crop
			var q = {
				x0: Math.round(left * r.width / x),
				y0: Math.round(top * r.height / y),
				x1: Math.round((left + sz) * r.width / x) + 2,
				y1: Math.round((top + sz) * r.height / y) + 2,
				id: r.id,
				size: 200
			};
			ajax('', q, function(rr) {
				var html = '<a class="delete-img" @href(sky.delete_file(this, ' + r.id + '))></a>';
				r.place.html('<img style="position:absolute" src="file?id' + r.id + '&_"/>' + html);
				sky.hide();
			}, '_crop');
		});
		$('table#crop input[type=range]').on('input', function() {
			reg.css({
				width: sz = mn * this.value / 100 - 2,
				height: sz
			});
		});
		$(document).on('mousemove', function(e) {
			var os = img.offset();
			left = e.clientX - sz / 2 - os.left + $(this).scrollLeft(),
			top = e.clientY - sz / 2 - os.top + $(this).scrollTop();
			reg.css({
				left: left = (left < 0 ? 0 : (left + sz > x ? x - sz - 2 : left)),
				top: top = (top < 0 ? 0 : (top + sz > y ? y - sz - 2 : top))
			});
		});
	});
};
