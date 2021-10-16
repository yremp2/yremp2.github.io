/**
 * [format 字符串格式化函数]
 * @param  {[String]} args [替换的数据]
 * @return {[String]}      [格式化后的字符串]
 */
String.prototype.format = function (args) {
	var result = this;
	if (arguments.length > 0) {
		var reg;
		if (arguments.length === 1 && typeof (args) == "object") {
			for (var key in args) {
				if (args[key] !== undefined) {
					reg = new RegExp("({" + key + "})", "g");
					result = result.replace(reg, args[key]);
				}
			}
		} else {
			for (var i = 0; i < arguments.length; i++) {
				if (arguments[i] !== undefined) {
					reg = new RegExp("({[" + i + "]})", "g");
					result = result.replace(reg, arguments[i]);
				}
			}
		}
	}
	return result;
};

var icons = {
	'file-code-o': 'xml|html?|haml|php|aspx?|js|(le|sa|s?c)cs|bat|cmd|vbs|java|smali|py',
	'file-archive-o': 'zip|rar|gz|7z',
	'android': 'apk|jar|dex',
	'apple': 'ipa|dmg',
	'windows': 'iso|esd|exe|msi',
	'file-text-o': 'plain|log|msg|odt|page|te?xt|ext|md|ini|conf|svg',
	'file-audio-o': 'aac|mid|wma|ogg|m4a|mp3|ape|flac|wav|d[sd]f',
	'file-image-o': 'bmp|jpe?g|png|ico|[tg]if|pc[cx]|tga|exif|fpx|psd|cdr|dxf|ufo|eps|ai|raw|wmf|webp',
	'file-movie-o': '3gp|avi|mkv|mp4|flv|rmvb|mov|wmv',
	'file-word-o': 'docx?',
	'file-excel-o': 'xl(s[xm]?|t)',
	'file-pdf-o': 'pdf',
	'database': 'db|sql'
};

function args (name) {
	if (typeof (name) === 'undefined' || name === '') return '';

	var re    = /^#([a-z0-9]{5,10}|-1)?(\?(.+))?$/.exec(location.hash),
	    param = {id: -1};

	if (re) {
		param.id = re[1] || '-1';

		if (re[3]) {
			var query = re[3].split('&');
			for (var i = 0; i < query.length; i++) {
				var a = query[i].split('=');
				param[a[0]] = a[1];
			}
		}
	}

	return param.hasOwnProperty(name) ? param[name] : '';
}

function parseUrl (id, pwd) {
	var uri = id;
	if (typeof (id) === 'undefined' || id === '') uri = args('id');

	if (typeof (pwd) !== 'undefined' && pwd !== '') uri += '?pwd=' + pwd;
	return uri;
}

function fullUrl (id, name, page, pwd) {
	var uri = id, param = [];

	if (typeof (page) === 'undefined') {
		if (typeof (name) !== 'undefined') uri += '.' + name;
	} else {
		uri += '/' + name;
		if (page > 1) param.push('page=' + page);
	}
	if (typeof (pwd) !== 'undefined')
		param.push('pwd=' + pwd);
	if (param.length > 0)
		uri += '?' + param.join('&');

	return location.href.replace(/[#?].*$/g, '') + uri;
}

//自动转换大小单位
function autoSize (size, digit) {
	if (typeof (digit) === 'undefined') digit = 2;
	var unit = ['', 'K', 'M', 'G', 'T', 'P'];
	var i = Math.floor(Math.log(size) / Math.log(1024));

	return (size / Math.pow(1024, i)).toFixed(i > 0 ? digit : 0) + unit[i];
}

layui.config({
	base: "js/lay-module/",
	version: true
}).extend({
	lianyi: 'lianyi'
}).use(['jquery', 'layer', 'table', 'form', 'upload', 'element', 'lianyi'], function () {
	var $       = layui.jquery,
	    layer   = layui.layer,
	    table   = layui.table,
	    form    = layui.form,
	    upload  = layui.upload,
	    element = layui.element,
	    lianyi  = layui.lianyi;

	var tableIns;
	window.cut_file = {};

	var parseLayer = function (msg) {
		return layer.open({
			title: '涟漪云 (更新于：<b style="color:red">2021-07-13</b>)',
			type: 1,
			closeBtn: false,
			shadeClose: true,
			content: $('#lanzou'),
			btn: [window.isAdmin ? '设置' : '确定', '取消'],
			id: 'parseLayer',
			success: function (elem, index) {
				if (msg) lianyi.fail(msg);
				form.val('lanzou', {id: args('id'), pwd: window.isAdmin ? (window.pwd || '') : args('pwd')});
				var that = this;
				$('[name]', elem).keydown(function (e) {
					if (e.keyCode === 13)
						that.yes(index, elem);
				});
			},
			yes: function (index, elem) {
				var field = form.val('lanzou');

				var re = /^(-1|\d+|[bi][a-zA-Z0-9]+)?$/.exec(field.id);
				if (!re)
					lianyi.fail('目录id不正确');

				if (window.isAdmin) {
					if (window.pwd === field.pwd) return lianyi.fail('无变化');
					lianyi.request({
						url: 'api.php?c=pwd',
						data: field,
						msg: '设置密码...',
						success: function (res) {
							window.pwd = field.pwd;
							layer.close(index);
						}
					});
				} else {
					layer.close(index);
					location.hash = parseUrl(field.id || -1, field.pwd);
				}
			}
		});
	}

	var layerLogin = function () {
		if (!window.isAdmin) {
			layer.open({
				title: '管理员登录',
				type: 1,
				shadeClose: true,
				content:
					'<div class="layui-form" id="login" lay-filter="login" style="padding: 20px">' +
					'	<div class="layui-form-item form-icon">' +
					'		<label class="layui-icon layui-icon-password"></label>' +
					'		<input type="text" name="pass" lay-verify="required" placeholder="管理员密码" lay-reqtext="请输入密码" autocomplete="off" maxlength="32" class="layui-input">' +
					'	</div>' +
					'	<div class="layui-form-item form-icon code-item">' +
					'		<label class="layui-icon layui-icon-vercode"></label>' +
					'		<input type="text" name="code" lay-verify="required" placeholder="图形验证码" lay-reqtext="请输入图形验证码" autocomplete="off" maxlength="4" class="layui-input code-input">' +
					'		<div class="code-img">' +
					'			<img src="api.php?c=verify" alt="图形验证码">' +
					'		</div>' +
					'	</div>' +
					'</div>'
				,
				btn: ['登录', '取消'],
				success: function (elem, index) {
					$('.code-img > img', elem).on('click', function () {
						$(this).attr('src', 'api.php?c=verify&t=' + Math.random());
					});
					$('.layui-form-item.form-icon input[name]', elem).on('input propertychange', function () {
						var val = $(this).val();
						switch ($(this).attr('name')) {
							case 'pass':
								if (/\s+/.test(val)) $(this).val(val.replace(/\s+/g, ''));
								break;
							case 'code':
								if (/[^0-9a-zA-Z]/.test(val)) $(this).val(val.replace(/[^0-9a-zA-Z]/g, ''));
								break;
						}
						$(this).prev().css('color', $(this).val() === '' ? '#d2d2d2' : '#1e9fff');
					});
					var that = this;
					$('input[name]', elem).keydown(function (e) {
						if (e.keyCode === 13)
							that.yes(index, elem);
					});
				},
				yes: function (index, elem) {
					var btn0 = $('.layui-layer-btn0', elem);
					if (btn0.hasClass('layui-disabled')) return

					var field = form.val('login');
					if (field.pass === '') return lianyi.fail('管理员密码不能为空');
					if (field.code === '') return lianyi.fail('图形验证码不能为空');

					btn0.addClass('layui-disabled').prepend('<i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop"></i>');
					lianyi.request({
						url: 'api.php?c=login',
						data: field,
						success: function (res) {
							layer.close(index);
						},
						complete: function (res) {
							btn0.removeClass('layui-disabled').children('.layui-icon.layui-icon-loading').remove();
							if (res.code >= 0)
								$('.code-img > img', elem).click();
						}
					});
				}
			});
			return false;
		}

		return true;
	}

	var refresh = function (page) {
		if (!args('id'))
			return lianyi.fail('请输入正确的目录id');

		layer.closeAll()

		var where = {id: args('id'), pwd: args('pwd')};

		if (table.cache.hasOwnProperty('list'))
			return table.reload('list', {
				where: where,
				page: {curr: page || 1},
				limit: args('id').toString().substring(0, 1).toLowerCase() === 'b' ? 50 : 18
			});

		tableIns = table.render({
			elem: '#list',
			url: 'api.php?c=list',
			method: 'POST',
			where: where,
			toolbar:
				'<div>' +
				'   <div class="table-toolbar">' +
				'       <button type="button" class="layui-btn layui-btn-sm" lay-event="refresh">刷新</button>' +
				'       <button type="button" class="layui-btn layui-btn-sm layui-btn-normal" lay-event="change">更改</button>' +
				'       <div class="layui-btn-group toolbar-admin">' +
				'           <button type="button" class="layui-btn layui-btn-sm" lay-event="folder" tips="新建文件夹"><i class="fa fa-folder fa-fw"></i></button>' +
				'           <button type="button" class="layui-btn layui-btn-sm layui-hide" lay-event="cut" tips="剪贴"><i class="fa fa-cut fa-fw"></i></button>' +
				'           <button type="button" class="layui-btn layui-btn-sm layui-hide" lay-event="paste" tips="粘贴"><i class="layui-icon layui-icon-release"></i></button>' +
				'           <button type="button" class="layui-btn layui-btn-sm layui-hide" lay-event="delete" tips="删除"><i class="fa fa-trash fa-fw"></i></button>' +
				'       </div>' +
				'   </div>' +
				'</div>'
			,
			defaultToolbar: ['filter'],
			height: 'full-100',
			cols: [[
				{type: 'checkbox', title: '全选', id: 'checkAll', width: 40, align: 'center', fixed: 'left'},
				{type: 'numbers', title: '序号', width: 60, align: 'center', fixed: 'left'},
				{
					field: 'name', title: '名称', minWidth: 160, event: 'name', templet: function (d) {
						if (d.isFolder)
							return '<span><i class="fa fa-folder fa-fw" style="margin-right: 5px;"></i>{0}</span>'.format(d.name);

						var icon = 'file-o',
						    ext  = d.ext;

						if (ext)
							$.each(icons, function (k, v) {
								var re = new RegExp('^' + v + '$', 'i');
								if (re.test(ext)) {
									icon = k;
									return false;
								}
							});

						return '<span><i class="fa fa-{0} fa-fw" style="margin-right: 5px;"></i>{1}</span>'.format(icon, d.name);
					}, sort: true
				},
				{field: 'size', title: '大小', width: 82, sort: true},
				{field: 'downs', title: '下载', width: 82, align: 'center', sort: true},
				{field: 'time', title: '时间', width: 102, align: 'center', sort: true},
				{
					title: '操作', width: 112, fixed: 'right', align: 'center', event: 'action', templet: function (d) {
						var html = '<select lay-filter="action">';
						html += '<option value="">操作</option>';
						if (!d.isFolder)
							html += '<option value="links">直链</option>';

						html += '<optgroup label="管理员">';
						html += d.isFolder ? '<option value="rename">重命名</option>' : '<option value="cut">剪切</option>';
						html += '<option value="delete">删除</option>';
						html += '</optgroup>';

						html += '</select>';

						return html;
					}
				}
			]],
			page: {
				first: '首页',
				last: '尾页',
				layout: ['prev', 'page', 'next', 'skip']
			},
			limit: args('id').toString().substring(0, 1).toLowerCase() === 'b' ? 50 : 18,
			parseData: function (res) {
				tableIns.config.page.curr = this.page.curr;
				window.isAdmin = res.admin === true;
				window.pwd = res.pwd || '';
			},
			done: function (res) {
				var main = $('.layui-table-box > .layui-table-body.layui-table-main');
				if (main.height() >= main.children('table.layui-table').height()) {
					$('.layui-table-fixed-r > .layui-table-body').css('overflow', 'visible')
						.parents('.layui-table-box').css('overflow-y', 'auto');
				}
				cut_files(true);
				if (res.path)
					$('#nav').html(function () {
						var navs = ['<a href="#{0}">{1}</a>'.format(parseUrl(-1), '根目录')]
						$.each(res.path, function (id, name) {
							navs.push('<a href="#{0}">{1}</a>'.format(parseUrl(id), name));
						});
						return navs.join('<span lay-separator="">/</span>')
					});
				if (res.hasOwnProperty('desc')) $('.folder_desc').html(res.desc || '');

				if (res.code < 0) {
					setTimeout(function () {
						parseLayer(res.msg);
					}, 200);
				}
			}
		});
	}

	refresh();

	table.on('toolbar(list)', function (obj) {
		var event = obj.event,
		    d     = obj.data,
		    checkStatus, checkeds;
		switch (event) {
			case 'refresh':
				refresh();
				break;
			case 'change':
				parseLayer();
				break;
			case 'folder':
				if (!layerLogin()) return;

				layer.open({
					title: '新建文件夹',
					type: 1,
					shadeClose: true,
					content:
						'<div class="layui-form" lay-filter="folder" style="padding: 20px">' +
						'	<div class="layui-form-item form-icon">' +
						'		<label class="layui-icon layui-icon-file"></label>' +
						'		<input type="text" name="name" lay-verify="required" placeholder="文件夹名称" lay-reqtext="请输文件夹名称" autocomplete="off" maxlength="100" class="layui-input">' +
						'	</div>' +
						'	<div class="layui-form-item form-icon">' +
						'		<label class="layui-icon layui-icon-password"></label>' +
						'		<input type="text" name="pwd" lay-verify="" placeholder="文件夹密码(选填)" autocomplete="off" maxlength="12" class="layui-input code-input">' +
						'	</div>' +
						'	<div class="layui-form-item">' +
						'		<textarea name="desc" placeholder="文件夹描述(选填)" maxlength="170" class="layui-textarea"></textarea>' +
						'	</div>' +
						'</div>'
					,
					btn: ['创建', '取消'],
					success: function (elem, index) {
						$('.layui-form-item.form-icon input[name]', elem).on('input propertychange', function () {
							var val = $(this).val();
							switch ($(this).attr('name')) {
								case 'name':
									if (/\s/.test(val)) $(this).val(val.replace(/\s+/g, ''));
									break;
								case 'pwd':
									if (/[^\w]/.test(val)) $(this).val(val.replace(/[^\w]+/g, ''));
									break;
							}
							$(this).prev().css('color', $(this).val() === '' ? '#d2d2d2' : '#1e9fff');
						});
						var that = this;
						$('input[name]', elem).keydown(function (e) {
							if (e.keyCode === 13)
								that.yes(index, elem);
						});
					},
					yes: function (index, elem) {
						var btn0 = $('.layui-layer-btn0', elem);
						if (btn0.hasClass('layui-disabled')) return;

						var field = form.val('folder');
						if (field.name === '') return lianyi.fail('文件夹名称不能为空！');

						btn0.addClass('layui-disabled').prepend('<i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop"></i>');
						lianyi.request({
							url: 'api.php?c=folder',
							data: $.extend({id: args('id')}, field),
							msg: '新建文件夹...',
							success: function (res) {
								layer.close(index);
							},
							complete: function (res) {
								btn0.removeClass('layui-disabled').children('.layui-icon.layui-icon-loading').remove();
							}
						});
					}
				});
				break;
			case 'cut':
				checkStatus = table.checkStatus('list');
				checkeds = checkStatus.data;

				cut_files(checkeds);
				$('.layui-table-fixed-l input[type="checkbox"][name="layTableCheckbox"]:checked').next('.layui-form-checked').click();
				break;
			case 'paste':
				if (!layerLogin()) return;

				layer.confirm(Object.values(cut_file).join('<hr>'), {
					title: '确认移动以下文件到此目录',
					shadeClose: true,
					btn: ['移动', '清空', '取消'],
					btn1: function (index) {
						lianyi.request({
							url: 'api.php?c=move',
							data: {id: args('id'), file_id: Object.keys(cut_file)},
							msg: '移动文件...',
							success: function (res) {
								layer.close(index);
							},
							complete: function (res) {
								cut_files();
							}
						});
						return false;
					},
					btn2: function (index) {
						cut_files();
					}
				});
				break;
			case 'delete':
				if (!layerLogin()) return;

				checkStatus = table.checkStatus('list');
				checkeds = checkStatus.data;

				var folder = {}, file = {};
				$.each(checkeds, function (i, v) {
					if (v.isFolder) folder[v.id] = v.name;
					else file[v.id] = v.name;
				});

				layer.confirm(Object.values(folder).concat(Object.values(file)).join('<hr>'), {
					title: '确认删除以下文件（夹）',
					shadeClose: true,
					btn: ['删除', '取消'],
					btn1: function (index) {
						lianyi.request({
							url: 'api.php?c=delete',
							data: {id: args('id'), file_id: Object.keys(file), folder_id: Object.keys(folder)},
							msg: '删除文件（夹）...',
							success: function (res) {
								layer.close(index);
							}
						});
						return false;
					}
				});
				break;
		}
	});

	table.on('checkbox(list)', function (obj) {
		var checkStatus = table.checkStatus('list'),
		    checkeds    = checkStatus.data;

		var file = {};
		$.each(checkeds, function (i, v) {
			if (!v.isFolder) file[v.id] = v.name;
		});

		$('.toolbar-admin [lay-event="cut"]')[Object.keys(file).length > 0 ? 'removeClass' : 'addClass']('layui-hide');
		$('.toolbar-admin [lay-event="delete"]')[checkeds.length > 0 ? 'removeClass' : 'addClass']('layui-hide');
	});

	table.on('tool(list)', function (obj) {
		var d     = obj.data,
		    event = obj.event;

		switch (event) {
			case 'name':
				if (d.isFolder)
					location.hash = d.id;
				else
					location.href = d.id;
				break;
			case 'action':
				window.row_obj = obj;
				break;
		}
	});

	form.on('select(action)', function (obj) {
		var elem  = obj.elem,
		    value = obj.value,
		    d     = row_obj.data;

		switch (value) {
			case 'links':
				var links = [
					fullUrl(d.id, d.ext),
					fullUrl(args('id'), d.name, tableIns.config.page.curr, args('pwd'))
				];
				layer.open({
					title: d.name,
					type: 1,
					shadeClose: true,
					content:
						'<div class="layui-form layui-form-pane" lay-filter="links" style="padding: 20px">' +
						'	<div class="layui-form-item">' +
						'		<label class="layui-form-label">直链1(推荐)</label>' +
						'       <div class="layui-input-inline">' +
						'		    <input type="text" id="link1" value="{0}" class="layui-input" readonly>'.format(links[0]) +
						'	    </div>' +
						'	</div>' +
						'	<div class="layui-form-item">' +
						'		<label class="layui-form-label">直链2</label>' +
						'       <div class="layui-input-inline">' +
						'		    <input type="text" id="link2" value="{0}" class="layui-input" readonly>'.format(links[1]) +
						'	    </div>' +
						'	</div>' +
						'</div>'
					,
					btn: ['关闭']
				});
				break;
			case 'cut':
				var file = {};
				file[d.id] = d.name;
				cut_files(file);
				break;
			case 'rename':
				if (!layerLogin()) return;

				lianyi.request({
					url: 'api.php?c=desc',
					data: {id: d.id},
					msg: '获取数据...',
					success: function (res) {
						layer.open({
							title: '重命名文件夹',
							type: 1,
							shadeClose: true,
							content:
								'<div class="layui-form" lay-filter="rename" style="padding: 20px">' +
								'	<div class="layui-form-item form-icon">' +
								'		<label class="layui-icon layui-icon-file"></label>' +
								'		<input type="text" name="name" lay-verify="required" placeholder="文件夹名称" lay-reqtext="文件夹名称不能为空" autocomplete="off" maxlength="100" class="layui-input">' +
								'	</div>' +
								'	<div class="layui-form-item form-icon">' +
								'		<textarea name="desc" placeholder="文件夹描述(选填)" maxlength="170" class="layui-textarea"></textarea>' +
								'	</div>' +
								'</div>'
							,
							btn: ['修改', '取消'],
							success: function (elem, index) {
								form.val('rename', {name: d.name, desc: res.desc});
								$('.layui-form-item.form-icon input[name]', elem).on('input propertychange', function () {
									var val = $(this).val();
									switch ($(this).attr('name')) {
										case 'pass':
											if (/\s+/.test(val)) $(this).val(val.replace(/\s+/g, ''));
											break;
										case 'code':
											if (/[^0-9a-zA-Z]/.test(val)) $(this).val(val.replace(/[^0-9a-zA-Z]/g, ''));
											break;
									}
									$(this).prev().css('color', $(this).val() === '' ? '#d2d2d2' : '#1e9fff');
								});
								var that = this;
								$('input[name]', elem).keydown(function (e) {
									if (e.keyCode === 13)
										that.yes(index, elem);
								});
							},
							yes: function (index, elem) {
								var btn0 = $('.layui-layer-btn0', elem);
								if (btn0.hasClass('layui-disabled')) return

								var field = form.val('rename');
								if (field.name === '') return lianyi.fail('文件夹名称不能为空');

								btn0.addClass('layui-disabled').prepend('<i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop"></i>');
								lianyi.request({
									url: 'api.php?c=rename',
									data: $.extend({id: d.id}, field),
									success: function (res) {
										window.row_obj.update(field);
										layer.close(index);
									},
									complete: function (res) {
										btn0.removeClass('layui-disabled').children('.layui-icon.layui-icon-loading').remove();
									}
								});
							}
						});
					}
				});
				break;
			case 'delete':
				if (!layerLogin()) return;

				var data = {id: args('id')};
				data[d.isFolder ? 'folder_id' : 'file_id'] = d.id;
				layer.confirm('确认删除文件{0}：<b style="color: red;">{1}</b> ？'.format(d.isFolder ? '夹' : '', d.name), {
					title: '删除文件' + (d.isFolder ? '夹' : ''),
					shadeClose: true,
					btn: ['删除', '取消'],
					btn1: function (index) {
						lianyi.request({
							url: 'api.php?c=delete',
							data: data,
							msg: '正在删除...',
							success: function (res) {
								layer.close(index);
							}
						});
						return false;
					}
				});
				break;
		}
	});

	function cut_files (id) {
		if (typeof (window.cut_file) === 'undefined')
			window.cut_file = {};

		if (typeof (id) === 'undefined')
			window.cut_file = {};
		else if (id instanceof Array)
			$.each(id, function (i, v) {
				if (!v.isFolder) window.cut_file[v.id] = v.name;
			});
		else if (id instanceof Object)
			window.cut_file = $.extend(window.cut_file, id);

		var len = Object.keys(window.cut_file).length;
		if (len > 0)
			$('.toolbar-admin [lay-event="paste"]').removeClass('layui-hide').html('<i class="layui-icon layui-icon-release"></i><span class="layui-badge">{0}</span>'.format(len));
		else
			$('.toolbar-admin [lay-event="paste"]').addClass('layui-hide').html('<i class="layui-icon layui-icon-release"></i>');
	}

	$('.nav-upload').on('click', function (e) {
		if (!layerLogin()) return;
		var index = layer.open({
			title: '文件上传(中转上传,速度较慢)',
			type: 1,
			maxmin: true,
			content:
				'<div class="layui-upload">' +
				'   <div class="layui-upload-drag upload-select">' +
				'       <i class="layui-icon"></i>' +
				'       <p>点击上传，或将文件拖拽到此处</p>' +
				'   </div>' +
				'	<div class="layui-upload-list">' +
				'		<table class="layui-table">' +
				'			<colgroup>' +
				'				<col>' +
				'				<col width="80">' +
				'				<col width="150">' +
				'				<col width="114">' +
				'			</colgroup>' +
				'			<thead>' +
				'			<tr>' +
				'				<th>文件名</th>' +
				'				<th>大小</th>' +
				'				<th>进度</th>' +
				'				<th>操作</th>' +
				'			</tr>' +
				'			</thead>' +
				'			<tbody class="upload-list"></tbody>' +
				'		</table>' +
				'	</div>' +
				'	<button type="button" class="layui-btn upload-start">上传</button>' +
				'	<button type="button" class="layui-btn layui-btn-danger upload-close" style="float: right">关闭</button>' +
				'</div>'
			,
			success: function (elem, index) {
				$('.upload-close', elem).on('click', function () {
					layer.close(index);
				});
				var uploadListIns = upload.render({
					elem: $('.upload-select', elem),
					elemList: $('.upload-list', elem),
					url: 'api.php?c=upload',
					data: {id: args('id')},
					accept: 'file',
					multiple: true,
					drag: true,
					number: 20,
					size: 102400,
					auto: false,
					bindAction: $('.upload-start', elem),
					choose: function (obj) {
						var that = this;
						var files = this.files = obj.pushFile(); //将每次选择的文件追加到文件队列
						//读取本地文件
						obj.preview(function (index, file, result) {
							var tr = $([
								'<tr id="upload-{0}">'.format(index),
								'<td>{0}</td>'.format(file.name),
								'<td>{0}</td>'.format(autoSize(file.size)),
								'<td><div class="layui-progress" lay-filter="progress-demo-{0}"><div class="layui-progress-bar" lay-percent=""></div></div></td>'.format(index),
								'<td><button class="layui-btn layui-btn-xs upload-reload layui-hide">重传</button><button class="layui-btn layui-btn-xs layui-btn-danger upload-delete">删除</button></td>',
								'</tr>'
							].join(''));
							//单个重传
							tr.find('.upload-reload').on('click', function () {
								obj.upload(index, file);
							});

							//删除
							tr.find('.upload-delete').on('click', function () {
								delete files[index]; //删除对应的文件
								tr.remove();
								uploadListIns.config.elem.next()[0].value = ''; //清空 input file 值，以免删除后出现同名文件不可选
								$(window).resize();
							});

							that.elemList.append(tr);
							element.render('progress'); //渲染新加的进度条组件
							$(window).resize();
						});
					},
					before: function (obj) {
						if (Object.keys(this.files).length <= 0) return false;
						var that = this;
						obj.preview(function (index, file, result) {
							var tr  = that.elemList.find('tr#upload-' + index),
							    tds = tr.children();
							tds.eq(2).html('<div class="layui-progress" lay-filter="progress-demo-{0}"><div class="layui-progress-bar" lay-percent=""></div></div>'.format(index));
							tds.eq(3).find('.upload-reload')
								.addClass('layui-disabled')
								.prop('disabled', true);
						});
						if ($('.upload-start', elem).hasClass('layui-disabled')) return;
						$('.upload-start', elem).addClass('layui-disabled').prop('disabled', true).prepend('<i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop"></i>');
					},
					done: function (res, index, upload) { //成功的回调
						var that = this,
						    tr   = that.elemList.find('tr#upload-' + index),
						    tds  = tr.children();
						if (res.code === 0) { //上传成功
							tds.eq(2).html('<span class="layui-badge layui-bg-green">上传成功</span>');
							tds.eq(3).find('.upload-reload').addClass('layui-hide');
							delete this.files[index]; //删除文件队列已经上传成功的文件
						} else {
							tds.eq(2).html('<span class="layui-badge">{0}</span>'.format(res.msg));
							tds.eq(3).find('.upload-reload').removeClass('layui-hide')
								.removeClass('layui-disabled')
								.prop('disabled', false);
						}
					},
					allDone: function (obj) { //多文件上传完毕后的状态回调
						$('.upload-start', elem).removeClass('layui-disabled').prop('disabled', false)
							.children('.layui-icon-loading').remove();
					},
					error: function (index, upload) { //错误回调
						var that = this;
						var tr  = that.elemList.find('tr#upload-' + index),
						    tds = tr.children();
						tds.eq(3).find('.upload-reload').removeClass('layui-hide')
							.removeClass('layui-disabled')
							.prop('disabled', false); //显示重传
					},
					progress: function (n, elem, e, index) { //注意：index 参数为 layui 2.6.6 新增
						if (n >= 100) {
							var that = this,
							    tr   = that.elemList.find('tr#upload-' + index),
							    tds  = tr.children();
							tds.eq(2).html('<span class="layui-badge layui-bg-blue">服务器上传...</span>');
						} else
							element.progress('progress-demo-' + index, n + '%'); //执行进度条。n 即为返回的进度百分比
					}
				});
			}
		});
	});

	$('.nav-admin').on('click', function (e) {
		if (layerLogin())
			return layer.confirm('您已登录，现在退出管理员登录 ？', {
				title: '退出登录',
				shadeClose: true,
				btn: ['退出', '取消'],
				btn1: function (index) {
					lianyi.request({
						url: 'api.php?c=logout',
						msg: '退出登录...',
						success: function (res) {
							delete window.isAdmin;
							layer.close(index);
						}
					});
					return false;
				}
			});
	});

	$('#lanzou input[name="id"]').on('input propertychange', function () {
			var val = $(this).val();
			if (/[^a-z0-9\-]/.test(val)) $(this).val(val.replace(/[^a-z0-9\-]/g, ''));
		}
	);

	$('#lanzou input[name="pwd"]').on('input propertychange', function () {
			var val = $(this).val();
			if (/^\s+|\s+$/.test(val)) $(this).val(val.replace(/^\s+|\s+$/g, ''));
		}
	);

	$(window).on('hashchange', function (e) {
			refresh();
		}
	);
});