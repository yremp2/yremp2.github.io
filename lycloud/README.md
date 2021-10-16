# 涟漪云 v0.1.5

#### 介绍

蓝奏云挂载程序，自由操作蓝奏云内文件（夹），并可解析直链下载等等。  
演示：[查看演示](https://lz.ly93.cc)，讨论交流联系QQ：29397395

#### 更新说明

1. 修复js文件中substring函数导致的无法使用bug
2. 修复带密码的分享文件id无限密码错误


#### 大体功能如下

1. 浏览任意目录内文件（夹）
2. 批量移动文件
3. 批量删除文件（夹）
4. 重命名文件夹
5. 新建文件夹
6. 文件夹加密、描述及修改
7. 文件直链  
	①id形式：推荐，对文件的重命名、移动等等都不影响直链；  
	②文件名形式：重命名、移动以及同文件夹分页位置的变化都会影响直链的有效性，且同分页下同名文件只能识别1个  
	③文件分享id形式：域名/文件分享id?pwd=分享密码（分享密码选填）  
	④文件夹分享id形式：域名/文件夹分享id/文件名?page=页码&pwd=分享密码（页码和分享密码选填）
8. 文件批量上传

#### 软件架构

1. PHP >= 5.6 (开启redis扩展)
2. Redis

#### 安装教程

1. 下载源码
2. 将源码上传至你的服务器
3. 获取cookie(浏览器F12控制台执行)：
	```javascript
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
	```
4. 修改配置文件(config.php)相关配置
5. 配置伪静态  
	Nginx：
	```nginx
	if (!-e $request_filename) {
		rewrite ^/(-1|\d+|[bi][a-zA-Z0-9]+)(\.[\w]+|/([^/]+))?$ /api.php?id=$1&name=$3 break;
	}
	```
	Apache：
	```apache
	RewriteEngine On

	RewriteCond %{REQUEST_FILENAME} !-d
	RewriteCond %{REQUEST_FILENAME} !-f
	RewriteRule ^(-1|\d+|[bi][a-zA-Z0-9]+)(\.\w+|/([^/]+))?$ /api.php?id=$1&name=$3 [QSA,L]
	```