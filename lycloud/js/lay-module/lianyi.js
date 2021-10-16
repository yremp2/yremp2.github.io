layui.define(['jquery', 'layer', 'form'], function (exports) {
	var $     = layui.jquery,
	    layer = layui.layer,
	    form  = layui.form;

	var lianyiTips;
	$(document).on("mouseenter", "[tips]", function () {
		lianyiTips = layer.tips($(this).attr('tips'), $(this), {
			tips: [1, '#3595cc'],
			time: 0
		});
	});
	$(document).on("mouseleave", "[tips]", function () {
		if (lianyiTips) {
			layer.close(lianyiTips);
			lianyiTips = null;
		}
	});

	// 禁用textarea换行符
	$(document).on('input propertychange', 'textarea[nowarp]', function () {
		if (/^\s+|[\r\n]+/.test($(this).val())) $(this).val($(this).val().replace(/^\s+|[\r\n]+/g, ''));
	});

	// 表单lay-verify验证
	form.verify({
		//数组的两个值分别代表：[正则匹配、匹配不符时的提示文字]
		zh: [
			/^[\u0391-\uFFE5]*$/,
			'只允许输入中文'
		], // 只能输入中文
		date: [
			/^([1-9]\d{3}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1]))?$/,
			'日期格式错误！'
		],
		datetime: [
			/^([1-9]\d{3}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])\s+(2[0-3]|[0-1]\d):[0-5]\d:[0-5]\d)?$/,
			'日期 + 时间格式错误！'
		], // 2019-12-12 24:59:59
		datetime2: [
			/^([1-9]\d{3}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])\s+(2[0-3]|[0-1]\d):[0-5]\d:[0-5]\d\.\d{3})?$/,
			'日期 + 时间格式错误！'
		], //2019-12-12 24:59:59.123
		mobile: [
			/^(1(([38]\d)|(4[5-9])|(5[0-35-9])|66|(7[0-8])|(9[189]))\d{8})?$/,
			'手机号码格式错误！'
		], // 手机号码
		card: [
			/^(((1[1-5])|(2[1-3])|(3[1-7])|(4[1-6])|(5[0-4])|(6[1-5])|71|(8[12])|91)\d{4}((19\d{2}(0[13-9]|1[012])(0[1-9]|[12]\d|30))|(19\d{2}(0[13578]|1[02])31)|(19\d{2}02(0[1-9]|1\d|2[0-8]))|(19([13579][26]|[2468][048]|0[48])0229))\d{3}(\d|X|x)?)?$/,
			'身份证号码格式错误！'
		], //身份证号码
		email2: [
			/^(\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*)?$/,
			'邮箱格式错误！'
		], // 邮箱
		qq: [
			/^([1-9]\d{4,9})?$/,
			'QQ号码格式错误！'
		], // QQ号码
		money: [
			/^((0|[1-9]\d*)(\.[\d]{1,2})?)?$/,
			'价格格式错误！'
		], // 价格 12.34
		rate: [
			/^(100|(0|[1-9]\d*)(\.[\d]{1,2})?)?$/,
			'百分比格式错误！'
		], // 比率 0.00-100.00
		decimal: [
			/^((0|[1-9][0-9]*)(\.[\d]+)?)?$/,
			'仅允许输入小数！'
		], //小数
		int: [
			/^(0|[1-9]\d*)?$/,
			'仅允许输入非负整数！'
		], // 非负整数 >= 0
		int2: [
			/^([1-9]\d*)?$/,
			'仅允许输入正整数！'
		], // 正整数 >= 1
		url: [
			/^(https?:\/\/([^\/\s\r\n]+\.)+[^\/\s\r\n]+)?/i,
			'请输入标准URL地址！'
		],
		md5Str: [ // MD5字符串大小写均可
			/^([0-9A-Fa-f]{32})?$/,
			'仅允许输入32位MD5字符串！'
		],
		md5Str2: [ // MD5字符串，大写
			/^([0-9A-Fa-f]{32})?$/,
			'仅允许输入32位大写的MD5字符串！'
		]
	});

	var lianyi = {
		/**
		 * [load 加载框]
		 * @param  {[string]} msg [提示文字]
		 * @return {[int]}     [layer的index]
		 * @param  {[function]} fun   [关闭后回调事件]
		 */
		load: function (msg, fun) {
			return layer.msg(msg || '加载中...', {
				icon: 16,
				time: 0,
				shade: [0.1, '#000']
			}, function () {
				if (typeof (fun) === 'function')
					fun();
			});
		},

		/**
		 * [success 成功提示框]
		 * @param  {[string]} msg [提示文字]
		 * @param  {[function]} fun   [关闭后回调事件]
		 * @return {[int]}       [layer的index]
		 */
		success: function (msg, fun) {
			return layer.msg(msg, {
				icon: 1,
				shade: [0.1, '#000'],
				scrollbar: false,
				time: 1500,
				shadeClose: true
			}, function () {
				if (typeof (fun) === 'function')
					fun();
			});
		},

		/**
		 * [fail 失败提示框]
		 * @param  {[string]} msg [提示文字]
		 * @param  {[function]} fun   [关闭后回调事件]
		 * @return {[int]}       [layer的index]
		 */
		fail: function (msg, fun) {
			return layer.msg(msg, {
				icon: 2,
				shade: [0.1, '#000'],
				scrollbar: false,
				time: 2500,
				shadeClose: true
			}, function () {
				if (typeof (fun) === 'function')
					fun();
			});
		},

		/**
		 * [post 封装网络ajax请求]
		 * @param  {object} options [配置选项]
		 * @param  {object|void} fun    [成功回调函数]
		 * @return {[type]}         [无]
		 */
		request: function (options) {
			var loading;
			$.ajax({
				url: options.url,
				type: options.data ? 'POST' : 'GET',
				data: options.data || {},
				dataType: options.dataType || 'json',
				cache: options.hasOwnProperty('cache') ? options.cache : false,
				beforeSend: function (xhr) {
					if (options.msg !== null && options.msg !== '') loading = lianyi.load(options.msg || '请求中...');
				},
				success: function (res) {
					var code   = res.code,
					    msg    = res.msg,
					    status = code === 0 ? 'success' : 'fail',
					    fun    = function () {
						    if (typeof (options[status]) === 'function')
							    options[status](res);
						    lianyi.jump(res);
					    };

					if (msg)
						lianyi[status](msg, function () {
							fun();
						});
					else fun();
				},
				error: function (xhr, textStatus, errorThrown) {
					lianyi.fail(xhr.status + '：' + xhr.statusText, function () {
						if (typeof (options.error) === 'function') options.error();
					});
				},
				complete: function (xhr) {
					if (loading) layer.close(loading);
					if (typeof (options.complete) === 'function') options.complete(xhr.responseJSON);
				}
			});
		},

		// 根据条件自动刷新、跳转页面
		jump: function (obj) {
			var hash = top.location.hash,
			    o    = {'url': window, 'parent': parent, 'top': top};

			for (var k in o) {
				if (obj.hasOwnProperty(k)) {
					if (obj[k] === true)
						o[k].location.reload();
					else
						o[k].location.href = obj[k] + hash;

					return true;
				}
			}

			return false;
		}
	}

	exports("lianyi", lianyi);
});