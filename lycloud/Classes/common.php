<?php

/**
 * @param         $code
 * @param  null   $msg
 * @param  array  $data
 *
 * @return array
 */
function msg ($code, $msg = null, $data = []) {
	$arr = ['code' => $code];
	if (null !== $msg) {
		if (is_array($msg) || is_object($msg))
			$arr['data'] = $msg; else $arr['msg'] = $msg;
	}
	if (!empty($data) && is_array($data))
		$arr = array_merge($arr, $data);

	return $arr;
}

/**
 * @param         $code
 * @param  null   $msg
 * @param  array  $data
 *
 * @return string
 */
function jsonMsg ($code, $msg = null, $data = []) {
	return arr2json(msg($code, $msg, $data));
}

/**
 * @param  array  $arr
 * @param  bool   $pre
 *
 * @return string
 */
function arr2json ($arr = [], $pre = true) {
	//return response($arr, 200, ['Content-Type' => 'application/json; charset=utf-8'], 'json');
	return str_replace('\\/', '/', version_compare(phpversion(), '5.4', '<')
		? json_encode($arr) : json_encode($arr,
			$pre ? JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE : JSON_UNESCAPED_UNICODE));
}

//大小自动转换格式
function autoSize ($size, $digit = 2, $coefficient = 1024) {
	$sz = ['', 'K', 'M', 'G', 'T', 'P'];
	$i  = floor(log($size) / log($coefficient));

	return round($size / pow($coefficient, $i), $digit) . @$sz[$i];
}

/**
 * 获取13位时间戳
 *
 * @return string
 */
function getTime () {
	preg_match('/\.(\d{3})\d*.*?(\d+)/', microtime(), $m);

	return $m[2] . $m[1];
}

// session 操作封装
function session (...$args) {
	$status = version_compare(phpversion(), '5.4.0', '>=') ? session_status() === PHP_SESSION_ACTIVE : session_id() !== '';
	if (!$status) session_start();

	$length = count($args);
	if ($length > 1)
		if ($args[1] === null || $args[1] === '')
			unset($_SESSION[$args[0]]);
		else
			$_SESSION[$args[0]] = $args[1];
	else if (is_array($args[0]))
		foreach ($args[0] as $k => $v) {
			if ($v === null || $v === '')
				unset($_SESSION[$k]);
			else
				$_SESSION[$k] = $v;
		}
	else
		return isset($_SESSION[$args[0]]) ? $_SESSION[$args[0]] : null;

	return true;
}

/**
 * 二维数组根据某个字段排序
 *
 * @param  array   $arr   要排序的数组
 * @param  string  $key   要排序的键字段
 * @param  string  $sort  排序类型  SORT_ASC     SORT_DESC
 *
 * @return array 排序后的数组
 */
function arraySort ($arr, $key, $sort = SORT_ASC) {
	$keysValue = arr_column($arr, $key);
	array_multisort($keysValue, $sort, $arr);

	return $arr;
}

/**
 * 删除目录
 *
 * @param         $dir   [目录路径]
 * @param  bool   $self  [是否删除自身]
 */
function del_dir ($dir, $self = true) {
	array_map(function ($path) {
		$name = basename($path);
		if ('.' === $name || '..' === $name)
			return;
		if (is_dir($path)) {
			del_dir($path, false);
			@rmdir($path);
		} else if (!@unlink($path) && chmod($path, 0777))
			@unlink($path);
	}, glob($dir . DIRECTORY_SEPARATOR . '{.*,*}', GLOB_BRACE));
	if ($self && file_exists($dir))
		@rmdir($dir);
}

/**
 * 自动编码转换
 *
 * @param          $str
 * @param  string  $charset
 *
 * @return array|false|mixed|string
 */
function charset ($str, $charset = 'UTF-8') {
	$encode = mb_detect_encoding($str, ["ASCII", 'UTF-8', "GB2312", "GBK", 'BIG5']);
	if ($encode !== strtoupper($charset))
		$str = mb_convert_encoding($str, $charset, $encode);

	return $str;
}

// array_column函数 低版php兼容及bug修复
function arr_column ($array, $column_key, $index_key = null) {
	if (version_compare(phpversion(), '7.0', '<')) {
		$arr = [];
		foreach ($array as $v) {
			$value = is_object($v) ? @$v->$column_key : @$v[$column_key];
			if ($index_key === null) $arr[] = $value;
			else $arr[is_object($v) ? @$v->$index_key : @$v[$index_key]] = $value;
		}

		return $arr;
	}

	return array_column($array, $column_key, $index_key);
}