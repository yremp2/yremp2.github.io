<?php

// 相关配置
$conf = [
	// 图形验证码
	'verify' => [
		'type' => 2, // 0 数字，1 英文字母，2 数字 + 英文字母
		'len'  => 4  // 验证码字符长度
	],

	// 蓝奏云控制台cookie（必须）
	'cookie' => [
		'ylogin'       => '此处填写您的ylogin',      // 蓝奏云用户id
		'phpdisk_info' => '此处填写您的phpdisk_info' // 蓝奏云令牌
	],

	'redis'          => true,       // 是否启用redis，true|启用（前提redis服务已开启，php已安装并开启redis扩展），false|不启用，
	'upload_timeout' => 120,        // 上传文件超时秒数，若上传大文件总是失败，可增大此值
	'admin_pass'     => 'lianyi',   // 管理员登录密码，自行修改（重要）
	'default_pwd'    => '00'        // 默认空密码（2-12位），当文件夹密码等于此密码时，表示无密码，可供游客访问
];

/* 蓝奏云获取cookie方法，浏览器·F12·控制台执行以下代码：

if(!/(^|\.)woozooo\.com$/i.test(document.location.host))
	throw new Error('请登录到蓝奏云控制台在执行此代码！');

var regex = /(?<=^|;)\s*([^=]+)=\s*(.+?)\s*(?=;|$)/g,
	cookies = {},re;
while(re = regex.exec(document.cookie))
	if(re[1] === 'ylogin'||re[1] === 'phpdisk_info')
		cookies[re[1]] = re[1]+'='+re[2]+';';

if(!cookies.hasOwnProperty('phpdisk_info'))
	throw new Error('获取cookie失败，请确认您已登录到蓝奏云控制台！');

var copy = function (str) {
	var oInput = document.createElement('input');
	oInput.value = str;
	document.body.appendChild(oInput);
	oInput.select();
	document.execCommand("Copy");
	oInput.remove();
	alert('复制成功');
}

copy(Object.values(cookies).join(' '));

*/