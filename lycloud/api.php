<?php

header('Content-Type:application/json;charset=utf-8');
//error_reporting(0);

const ROOT    = __DIR__ . DIRECTORY_SEPARATOR;
const CLASSES = __DIR__ . DIRECTORY_SEPARATOR . 'Classes' . DIRECTORY_SEPARATOR;

require_once CLASSES . 'common.php';
require_once ROOT . 'config.php';

spl_autoload_register(function ($cls) {
	$file = ROOT . str_replace('\\', DIRECTORY_SEPARATOR, $cls) . '.php';
	if (is_file($file))
		/** @noinspection PhpIncludeInspection */
		require_once $file;
});

/**
 * @var $id
 * @var $pwd
 * @var $page
 * @var $name
 * @var $desc
 */
extract(array_filter($_REQUEST, function ($v) {
	return is_array($v) ? !empty($v) : $v !== null && trim($v) !== '';
}));

/** @var $conf $conf */
if (isset($c) && $c === 'verify') {
	$verify_conf = $conf['verify'];
	\Classes\Verify::config($verify_conf)->create();
	exit();
}

use Classes\Lanzou;

Lanzou::config($conf);

$ret = msg(-3, '未知操作');
if (isset($c))
	switch ($c) {
		case 's':
			if (!isset($id)) $ret = msg(-1, '分享id不能为空');
			else $ret = Lanzou::parseUrlByShareId($id, @$pwd);
			break;
		case 'list':
			$ret = Lanzou::parseList(@$id, @$pwd, @$page, @$name);
			break;
		case 'upload':
			$ret = Lanzou::upload(@$id, $_FILES);
			break;
		case 'desc':
			if (!isset($id)) $ret = msg(-1, '文件夹id不能为空');
			else $ret = Lanzou::desc($id);
			break;
		case 'rename':
			if (!isset($id)) $ret = msg(-1, '文件夹id不能为空');
			else if (!isset($name)) $ret = msg(-1, '文件名不能为空');
			else $ret = Lanzou::rename($id, $name, @$desc);
			break;
		case 'folder':
			if (!isset($id)) $ret = msg(-1, '文件夹id不能为空');
			else if (!isset($name)) $ret = msg(-1, '文件夹名称不能为空');
			else $ret = Lanzou::createFolder($id, $name, @$pwd, @$desc);
			break;
		case 'move':
			if (!isset($file_id)) $ret = msg(-1, '文件id不能为空');
			else $ret = Lanzou::moveFile(@$id, $file_id);
			break;
		case 'delete':
			if (!isset($file_id) && !isset($folder_id)) $ret = msg(-1, '文件(夹)id不能为空');
			else $ret = Lanzou::delete(['file_id' => @$file_id, 'folder_id' => @$folder_id]);
			break;
		case 'login':
			if (!isset($code))
				$ret = msg(-1, '图形验证码不能为空');
			else if (true !== $check = \Classes\Verify::check($code))
				$ret = msg(-2, $check);
			else if (!isset($pass))
				$ret = msg(-3, '管理员密码不能为空');
			else
				$ret = Lanzou::login($pass);
			break;
		case 'logout':
			$ret = Lanzou::logout();
			break;
		case 'pwd':
			if (!isset($id)) $ret = msg(-1, '文件夹id不能为空');
			else $ret = Lanzou::setPwd($id, @$pwd);
			break;
	}
else if (isset($id)) {
	$first = strtolower(substr($id, 0, 1));
	if ($first === 'b')
		$ret = isset($name) ? Lanzou::shareList($id, @$pwd, @$page, $name) : msg(-1, '文件名不能为空');
	else if ($first === 'i')
		$ret = Lanzou::parseUrlByShareId($id, @$pwd);
	else if (isset($name))
		$ret = Lanzou::parseList($id, @$pwd, @$page, $name);
	else
		$ret = Lanzou::parseUrl($id);
}

exit(arr2json($ret));