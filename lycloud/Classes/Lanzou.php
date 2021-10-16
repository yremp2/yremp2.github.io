<?php

namespace Classes;

class Lanzou {
	const API        = 'https://pc.woozooo.com/doupload.php';
	const API_UPLOAD = 'https://pc.woozooo.com/fileup.php';
	const HOST       = 'https://pan.lanzoui.com/';

	const allowedExts = [
		'7z', 'accdb', 'apk', 'appimage', 'azw', 'azw3', 'bat', 'bdi', 'bds', 'cad', 'ce', 'cetrainer', 'conf', 'cpk',
		'crx', 'ct', 'db', 'deb', 'dll', 'dmg', 'doc', 'docx', 'dwg', 'e', 'enc', 'epub', 'exe', 'flac', 'gho', 'hwt',
		'imazingapp', 'img', 'ipa', 'ipa', 'iso', 'it', 'jar', 'ke', 'lolgezi', 'lua', 'mobi', 'mobileconfig', 'mp3',
		'osk', 'osz', 'pdf', 'ppt', 'pptx', 'rar', 'rp', 'rplib', 'rpm', 'ssf', 'tar', 'ttc', 'ttf', 'txf', 'txt',
		'w3x', 'xapk', 'xls', 'xlsx', 'xmind', 'xpa', 'z', 'zip'
	];

	const prefix = 'lanzou_';

	private static $INSTANCE;

	private $user;
	private $cookie;
	private $admin_pass;           // 管理员密码
	private $default_pwd;          // 文件夹默认密码，等于此密码即表示无密码（2-12位数）
	private $upload_timeout;       // 上传文件超时秒数
	private $redis;

	/**
	 * Lanzou 初始化.
	 *
	 * @param  array  $conf
	 */
	private function __construct ($conf = []) {
		if (isset($conf['user']) && !empty($conf['user']))
			$this->user = $conf['user'];
		else if (isset($conf['cookie']))
			$this->cookie = $conf['cookie'];

		$this->admin_pass     = isset($conf['admin_pass']) ? $conf['admin_pass'] : 'lianyi';
		$this->default_pwd    = isset($conf['default_pwd']) ? $conf['default_pwd'] : '00';
		$this->upload_timeout = isset($conf['upload_timeout']) ? $conf['upload_timeout'] : 120;

		if (!isset($conf['redis']) || boolval($conf['redis']) !== false)
			$this->redis = Redis::prefix(self::prefix);
	}

	/**
	 * @return self
	 */
	private static function instance () {
		if (!(self::$INSTANCE instanceof self))
			exit(msg(-2, '尚未初始化配置'));

		return self::$INSTANCE;
	}

	/**
	 * 初始化配置
	 *
	 * @param  array  $conf
	 */
	public static function config ($conf) {
		self::$INSTANCE = $ins = new self($conf);

		return $ins;
	}

	/**
	 * 文件列表
	 *
	 * @param  string  $folder_id  文件夹id
	 * @param  string  $pwd        文件夹密码
	 * @param  int     $page       页码
	 * @param  string  $name       下载文件名
	 *
	 * @return array
	 */
	public static function parseList ($folder_id, $pwd = '', $page = 1, $name = '') {
		if (empty($folder_id)) $folder_id = '-1';
		if ($pwd === null) $pwd = '';
		$page = intval($page);
		if ($page < 1) $page = 1;
		if (strtolower(substr($folder_id, 0, 1)) === 'b')
			return self::shareList($folder_id, $pwd, $page, $name);

		$ins = self::instance();

		if (true !== $ret = $ins->getCookie())
			return $ret;

		$admin = $ins->isAdmin();
		$page  = empty($page) || !is_numeric($page) || $page < 1 ? 1 : intval($page);
		$extra = ['admin' => true === $admin];

		if ($page === 1) {
			// 获取子文件夹及父文件夹
			$folder = $ins->cCurl(47, ['folder_id' => $folder_id])->obj();
			if ($folder->zt !== 1 && $folder->zt !== 2)
				return msg(1, is_string($folder->info) ? $folder->info : '解析文件夹失败');

			$path = arr_column($folder->info, 'name', 'folderid');
			if ($folder_id !== '-1' && empty($path))
				return msg(2, '文件夹不存在');
			$extra['path'] = $path;

			$folders = arraySort($folder->text, 'name', SORT_NATURAL | SORT_FLAG_CASE);
		}

		if (false === $folderInfo = $ins->folderInfo($folder_id))
			return msg(3, '获取文件夹数据失败');

		if ($page === 1)
			$extra['desc'] = $folder_id === '-1' || $folderInfo->des === '' ? '<a href="https://gitee.com/lianyi007/lianyi-cloud" style="color: red">涟漪云</a>' : html_entity_decode($folderInfo->des);

		$real_pwd = isset($folderInfo->onof) && $folderInfo->onof === '1' && isset($folderInfo->pwd) && $folderInfo->pwd !== $ins->default_pwd ? $folderInfo->pwd : '';
		if ($real_pwd !== null && $real_pwd !== '') {
			if (true === $admin) {
				$extra['pwd'] = $real_pwd;
			} else if ($name === null || $name === '') {
				$key  = self::prefix . $folder_id;
				$pwd2 = session($key);

				if ($pwd === '' && $pwd2 === null)
					return msg(-4, '请输入密码', $extra);

				if ($real_pwd !== $pwd && $real_pwd !== $pwd2)
					return msg(-5, '密码错误', $extra);

				session($key, $real_pwd);
			}
		}

		// 获取子文件
		$file = $ins->cCurl(5, ['folder_id' => $folder_id, 'pg' => $page])->obj();
		if ($file->zt === 0)
			return msg(3, '解析文件夹失败', $extra);

		if ($name !== null && $name !== '') {
			$name = charset($name);
			foreach ($file->text as $v) {
				preg_match('/^((.+?)\.([^.]+))(\.it)?$/i', $v->name_all, $m);
				if ($m[1] === $name) {
					return self::parseUrl($v->id);
				}
			}

			return msg(4, '没有找到文件：' . $name);
		}
		$files = arraySort($file->text, 'name_all', SORT_NATURAL | SORT_FLAG_CASE);

		$data = isset($folders) ? array_merge($folders, $files) : $files;

		if (empty($data))
			return msg(5, $page > 1 ? '没有更多文件' : '空文件夹', $extra);

		$extra['count'] = pow(10, 6) * 18;
		$data           = array_map(function ($v) {
			if (isset($v->id)) {
				preg_match('/^((.+?)\.([^.]+))(\.it)?$/i', $v->name_all, $m);

				return [
					'id'    => $v->id,
					'name'  => $m[1],
					'size'  => $v->size,
					'ext'   => $m[3],
					'downs' => $v->downs,
					'time'  => $v->time
				];
			} else return [
				'id'       => $v->fol_id,
				'name'     => $v->name,
				'isFolder' => true
			];
		}, $data);

		return msg(0, $data, $extra);
	}

	/**
	 * 文件列表（分享id）
	 *
	 * @param  string  $shareId  文件夹分享id
	 * @param  string  $pwd      文件夹密码
	 * @param  int     $page     页码
	 * @param  string  $name     下载文件名
	 *
	 * @return array
	 */
	public static function shareList ($shareId, $pwd = '', $page = 1, $name = '') {
		if ($pwd === null) $pwd = '';
		$page = intval($page);
		if ($page < 1) $page = 1;
		if (strtolower(substr($shareId, 0, 1)) !== 'b')
			return msg(-1, '此分享id不是文件夹');

		$ins = self::instance();

		$page = empty($page) || !is_numeric($page) || $page < 1 ? 1 : intval($page);

		$key = "d_{$shareId}";
		if ($ins->redis !== null && $ins->redis->exists($key)) {
			$data = json_decode($ins->redis->get($key), true);
		} else {
			$html = $ins->curl()->get(self::HOST . $shareId)->html();

			if (preg_match('/\n\s*url\s*:\s*[\'"](\/filemoreajax\.php)[\'"],[\s\r\n]*data\s*:\s*({[\s\S]+?})/i', $html, $m)
				&& preg_match_all('/[\'"](\w+)[\'"]\s*:\s*([\w\'"]+)/i', $m[2], $m1)) {
				// t, k 参数有效期10分钟
				$data = array_combine($m1[1], $m1[2]);
				$data = $ins->replace_vars($html, $data);
				if ($ins->redis !== null)
					$ins->redis->set($key, arr2json($data, false), 580);

				if (isset($data['pwd']) && $pwd === '')
					return msg(-4, '请输入访问密码');
			} else if (preg_match('/(<div\s+class="off\d?">){3}<\/div><\/div>(.+?)<\/div>/i', $html, $m))
				return msg(1, $m[2]);
			else
				return msg(2, '解析文件夹数据失败');
		}
		$data['pg'] = $page;
		if (isset($data['pwd']) && $pwd !== '')
			$data['pwd'] = $pwd;

		$url  = self::HOST . 'filemoreajax.php';
		$file = $ins->curl()->post($url, $data)->obj();
		if ($file->zt !== 1)
			return msg(3, isset($file->info) ? $file->info : '获取文件数据失败');

		if ($name !== null && $name !== '') {
			$name = charset($name);
			foreach ($file->text as $v) {
				preg_match('/^((.+?)\.([^.]+))(\.it)?$/i', $v->name_all, $m);
				if ($m[1] === $name)
					return self::parseUrlByShareId($v->id);
			}

			return msg(4, '没有找到文件：' . $name);
		}
		$data = arraySort($file->text, 'name_all', SORT_NATURAL | SORT_FLAG_CASE);

		if (empty($data))
			return msg(6, $page > 1 ? '没有更多文件' : '空文件夹');

		$data  = array_map(function ($v) {
			preg_match('/^((.+?)\.([^.]+))(\.it)?$/i', $v->name_all, $m);

			return [
				'id'   => $v->id,
				'name' => $m[1],
				'size' => $v->size,
				'ext'  => $m[3],
				'time' => $v->time
			];
		}, $data);
		$count = count($data);

		return msg(0, $data, [
			'count' => $count >= 50 ? pow(10, 6) * 50 : ($page - 1) * 50 + $count
		]);
	}

	/**
	 * 解析直链（文件id）
	 *
	 * @param  string  $id  文件id
	 *
	 * @return array
	 */
	public static function parseUrl ($id) {
		$ins = self::instance();

		$key = "f_{$id}";
		if ($ins->redis !== null && $ins->redis->exists($key)) {
			$url = $ins->redis->get($key);
			header('Location: ' . $url);
			exit();
		}

		if (true !== $ret = $ins->getCookie())
			return $ret;

		if (false === $fileInfo = $ins->fileInfo($id))
			return msg(1, '解析文件信息失败');

		return self::parseUrlByShareId($fileInfo->f_id, $fileInfo->onof === '1' ? $fileInfo->pwd : '', $fileInfo->is_newd . '/', $key);
	}

	/**
	 * 解析直链（文件分享id）
	 *
	 * @param  string  $shareId  文件分享id
	 * @param  string  $pwd      分享密码
	 * @param  string  $host     域名
	 * @param  null    $key      redis key
	 *
	 * @return array
	 */
	public static function parseUrlByShareId ($shareId, $pwd = '', $host = self::HOST, $key = null) {
		if ($pwd === null) $pwd = '';
		if (strtolower(substr($shareId, 0, 1)) !== 'i')
			return msg(2, '文件分享id有误');

		$ins = self::instance();

		if ($key === null) {
			$key = $pwd === '' ? "s_{$shareId}" : "s_{$shareId}_{$pwd}";
			if ($ins->redis !== null && $ins->redis->exists($key)) {
				$url = $ins->redis->get($key);
				header('Location: ' . $url);
				exit();
			}
		}

		$html = $ins->curl()->get($host . $shareId)->html();

		if (preg_match('/\n\s*url\s*:\s*[\'"](.+?)[\'"]/', $html, $m1)
			&& preg_match('/\n\s*data\s*:\s*[\'"](.+?&p=)[\'"]\s*\+\s*pwd\s*,/', $html, $m2)) {
			// 需要访问密码
			return $ins->redirect($host . $m1[1], $m2[1] . $pwd, $key);
		} else if (preg_match('/<iframe\s+class="ifr2".*?src="(\/fn\?[\w]{3,}?)".*?><\/iframe>/i', $html, $m)) {
			// 不需要访问密码
			$html = $ins->curl()->get($host . $m[1])->html();
			if (preg_match('/\n\s*url\s*:\s*[\'"](.+?)[\'"],[\s\r\n]*data\s*:\s*{(.+?)}/i', $html, $m)
				&& preg_match_all('/[\'"](\w+)[\'"]\s*:\s*([\w\'"]+)/', $m[2], $m1) > 0) {
				$data = array_combine($m1[1], $m1[2]);
				$data = $ins->replace_vars($html, $data);

				return $ins->redirect($host . $m[1], $data, $key);
			}
		}

		if (preg_match('/(<div\s+class="off\d?">){3}<\/div><\/div>(.+?)<\/div>/i', $html, $m))
			return msg(3, $m[2]);

		return msg(4, '解析失败');
	}

	/**
	 * 上传文件到指定文件夹
	 *
	 * @param  string  $folder_id  目标文件夹
	 * @param          $files
	 *
	 * @return array
	 */
	public static function upload ($folder_id, $files) {
		if (empty($folder_id)) $folder_id = '-1';

		$ins = self::instance();

		if (true !== $ret = $ins->isAdmin())
			return $ret;

		if (true !== $ret = $ins->getCookie())
			return $ret;

		if (empty($files) || !isset($files['file']))
			return msg(-1, '没有文件被上传');

		$file = $files['file'];
		$name = $file['name'];
		if (preg_match('/^(.*?)\.([^.]*)$/', $name, $m) && in_array(strtolower($m[2]), self::allowedExts)) {
			$ext = $m[2];
		} else {
			$ext  = 'it';
			$name .= '.' . $ext;
		}
		if (!in_array(strtolower($ext), self::allowedExts))
			return msg(1, '不支持的文件格式！');

		if ($file['size'] > 100 * 1024 * 1024)
			return msg(2, '文件大小不能超过100M！');

		if ($file['error'] > 0)
			return msg(3, '文件错误！');

		$temp = __DIR__ . DIRECTORY_SEPARATOR . '.temp' . DIRECTORY_SEPARATOR;
		$dir  = $temp . md5_file($file['tmp_name']) . DIRECTORY_SEPARATOR;
		if (!file_exists($dir))
			mkdir($dir, 0777, true);

		$path = $dir . $name;
		if (!move_uploaded_file($file['tmp_name'], $path))
			return msg(4, '上传失败！');

		$obj = $ins->curl()->timeout($ins->upload_timeout)->cookie($ins->cookie)->upload(self::API_UPLOAD, [
			'task'        => 1,
			'folder_id'   => $folder_id,
			'upload_file' => new \CURLFile($path)
		])->obj();

		del_dir($dir);
		@rmdir($temp);
		if ($obj->zt !== 1)
			return msg(5, is_string($obj->info) ? $obj->info : '未知错误');

		return msg(0, ['name' => $name, 'id' => $obj->text[0]->id, 'pId' => $folder_id]);
	}

	/**
	 * 获取文件夹描述
	 *
	 * @param  string  $folder_id  文件夹id
	 *
	 * @return array
	 */
	public static function desc ($folder_id) {
		$ins = self::instance();

		if (true !== $ret = $ins->isAdmin())
			return $ret;

		if (true !== $ret = $ins->getCookie())
			return $ret;

		if (false === $folderInfo = $ins->folderInfo($folder_id))
			return msg(1, '获取文件夹数据失败');

		return msg(0, null, ['desc' => html_entity_decode($folderInfo->des)]);
	}

	/**
	 * 重命名文件夹
	 *
	 * @param  string  $folder_id  文件夹id
	 * @param  string  $name       新文件夹名称
	 * @param  string  $desc       文件夹描述
	 *
	 * @return array
	 */
	public static function rename ($folder_id, $name, $desc = '') {
		if ($desc === null) $desc = '';

		$ins = self::instance();

		if (true !== $ret = $ins->isAdmin())
			return $ret;

		if (true !== $ret = $ins->getCookie())
			return $ret;

		$obj = $ins->cCurl(4, [
			'folder_id'          => $folder_id,
			'folder_name'        => $name,
			'folder_description' => $desc
		])->obj();

		if ($obj->zt !== 1)
			return msg(1, is_string($obj->info) ? $obj->info : '修改失败');

		return msg(0, is_string($obj->info) ? $obj->info : '修改成功');
	}

	/**
	 * 新建文件夹
	 *
	 * @param  string  $folder_id  目标文件夹id
	 * @param  string  $name       文件夹名称
	 * @param  string  $pwd        文件夹密码
	 * @param  string  $desc       文件夹描述
	 *
	 * @return array
	 */
	public static function createFolder ($folder_id, $name, $pwd = '', $desc = '') {
		if (empty($folder_id)) $folder_id = -1;
		if ($pwd === null) $pwd = '';
		if ($desc === null) $desc = '';

		$ins = self::instance();

		if (true !== $ret = $ins->isAdmin())
			return $ret;

		if (true !== $ret = $ins->getCookie())
			return $ret;

		$obj = $ins->cCurl(2, [
			'parent_id'          => $folder_id,
			'folder_name'        => $name,
			'folder_description' => $desc
		])->obj();

		if ($obj->zt !== 1)
			return msg(1, is_string($obj->info) ? $obj->info : '创建失败');

		$obj2 = $ins->cCurl(16, [
			'folder_id' => $obj->text,
			'shows'     => 1,
			'shownames' => $pwd === '' ? $ins->default_pwd : $pwd
		])->obj();

		if ($obj2->zt !== 1)
			return msg(0, is_string($obj2->info) ? $obj2->info : '创建成功但修改密码失败', ['url' => true]);

		return msg(0, is_string($obj->info) ? $obj->info : '创建成功', ['id' => $obj->text, 'url' => true]);
	}

	/**
	 * 移动文件
	 *
	 * @param  string  $folder_id  目标文件夹id
	 * @param  array   $file_id    待移动的文件id
	 *
	 * @return array
	 */
	public static function moveFile ($folder_id, $file_id) {
		if (empty($folder_id)) $folder_id = -1;

		$ins = self::instance();

		if (true !== $ret = $ins->isAdmin())
			return $ret;

		if (true !== $ret = $ins->getCookie())
			return $ret;

		$error = [];
		$total = 0;
		foreach ($file_id as $id) {
			$obj = $ins->cCurl(20, ['folder_id' => $folder_id, 'file_id' => $id])->obj();

			if ($obj->zt !== 1)
				$error[] = $id;

			$total++;
		}
		if (!empty($error))
			return msg(1, count($error) . '个文件移动失败', $total !== count($error) ? ['url' => true] : null);

		return msg(0, '移动成功', ['url' => true]);
	}

	/**
	 * 删除文件（夹）
	 *
	 * @param  array  $files  ['file_id' => [], 'folder_id' => []]
	 *
	 * @return array
	 */
	public static function delete ($files = []) {
		$ins = self::instance();

		if (true !== $ret = $ins->isAdmin())
			return $ret;

		if (true !== $ret = $ins->getCookie())
			return $ret;

		$error = [];
		$total = 0;
		foreach ($files as $key => $value) {
			if (!in_array($key, ['file_id', 'folder_id']) || $value === null || $value === '') continue;
			if (!is_array($value)) $value = [$value];
			foreach ($value as $v) {
				$obj = $ins->cCurl($key === 'file_id' ? 6 : 3, [$key => $v])->obj();
				if ($obj->zt !== 1)
					$error[] = $v;
				$total++;
			}
		}
		if (!empty($error))
			return msg(1, count($error) . '个文件(夹)删除失败', $total !== count($error) ? ['url' => true] : null);

		return msg(0, '删除成功', ['url' => true]);
	}

	/**
	 * 修改密码（2-12位数）
	 *
	 * @param  string  $folder_id  文件夹id
	 * @param  string  $pwd        新文件夹密码
	 *
	 * @return array
	 */
	public static function setPwd ($folder_id, $pwd = '') {
		if ($pwd === null) $pwd = '';

		$ins = self::instance();

		if ($folder_id === '-1')
			return msg(1, '根文件夹不允许设置密码');

		if (true !== $ret = $ins->isAdmin())
			return $ret;

		if (true !== $ret = $ins->getCookie())
			return $ret;

		$obj = $ins->cCurl(16, [
			'folder_id' => $folder_id,
			'shows'     => 1,
			'shownames' => $pwd === '' ? $ins->default_pwd : $pwd
		])->obj();

		if ($obj->zt !== 1)
			return msg(2, is_string($obj->info) ? $obj->info : '修改失败');

		return msg(0, '修改成功');
	}

	/**
	 * 管理员登录
	 *
	 * @param  string  $pass    管理员密码
	 * @param  int     $expiry  登录过期时间
	 *
	 * @return array
	 */
	public static function login ($pass, $expiry = 86400) {
		$ins = self::instance();

		if ($ins->admin_pass !== $pass)
			return msg(1, '管理员密码错误');

		session('admin', Authcode::encode($pass, $_SERVER['REMOTE_ADDR'], $expiry));

		return msg(0, '登陆成功', ['url' => true]);
	}

	/**
	 * 管理员退出登录
	 *
	 * @return array
	 */
	public static function logout () {
		$ins = self::instance();

		if (true !== $ret = $ins->isAdmin())
			return $ret;

		session('admin', null);

		return msg(0, '退出成功');
	}

	private function getCookie ($key = 'cookie') {
		if (!empty($this->cookie) && isset($this->cookie['phpdisk_info']))
			return true;

		if ($this->redis !== null && $this->redis->exists($key)) {
			$this->cookie = json_decode($this->redis->get($key), true);

			return true;
		}

		/* 为防和谐，此处移除自动登录获取cookie相关代码 */

		return msg(11, '未配置蓝奏云网盘账号密码或cookie');
	}

	// 检测是否管理员
	private function isAdmin () {
		if (null === $str = session('admin'))
			return msg(21, '未登录', ['url' => true]);

		if (!($pass = Authcode::decode($str, $_SERVER['REMOTE_ADDR'])) || $pass !== $this->admin_pass)
			return msg(22, '登录已过期，请重新登录', ['url' => true]);

		return true;
	}

	/**
	 * 请求直链并重定向
	 *
	 * @param  string        $url
	 * @param  array|string  $data
	 * @param  int           $expiry  过期时间，官方链接有效期是30分钟，这里最好小于30分钟
	 *
	 * @return array
	 */
	private function redirect ($url, $data, $key = '', $expiry = 60 * 20) {
		$obj = $this->curl()->post($url, $data)->obj();
		if ($obj->zt === 1) {
			$url          = $obj->dom . '/file/' . $obj->url;
			$redirect_url = $this->curl()->location(false)->get($url)->all('redirect_url');
			if (filter_var($redirect_url, FILTER_VALIDATE_URL)) {
				if ($this->redis !== null && $key !== null && $key !== '')
					$this->redis->set($key, $redirect_url, $expiry);
				header('Location: ' . $redirect_url);
				exit();
			}
		}

		return msg(4, $obj->inf ?: '解析直链失败');
	}

	/**
	 * 获取文件夹信息
	 *
	 * @param $folder_id
	 *
	 * @return object|false
	 */
	private function folderInfo ($folder_id) {
		$obj = $this->cCurl(18, ['folder_id' => $folder_id])->obj();

		if ($obj->zt !== 1)
			return false;

		return $obj->info;
	}

	/**
	 * 获取文件信息
	 *
	 * @param $file_id
	 *
	 * @return object|false
	 */
	private function fileInfo ($file_id) {
		$obj = $this->cCurl(22, ['file_id' => $file_id])->obj();

		if ($obj->zt !== 1)
			return false;

		return $obj->info;
	}

	/**
	 * ajax表单相关参数替换组合
	 *
	 * @param $html
	 * @param $data
	 *
	 * @return array|mixed|string[]
	 */
	private function replace_vars ($html, $data) {
		if (preg_match_all('/\n\s*(var\s*)?([a-zA-Z_]\w*)\s*=\s*([\'"](.*)[\'"]|\d+)\s*;/', $html, $m) > 0) {
			$vars = array_combine($m[2], $m[3]);
			$data = array_map(function ($v) use ($vars) {
				return trim(preg_match('/^([a-zA-Z_]\w*)$/', $v) && isset($vars[$v]) ? $vars[$v] : $v, '"\'');
			}, $data);
		}

		return $data;
	}

	private function curl ($ua = 'pc') {
		return Curl::ua($ua)->referer('https://pan.lanzou.com/')
			->header([
				'accept'          => 'text/html,application/xhtml+xml,application/xml',
				'accept-language' => 'zh-CN,zh;q=0.9,en;q=0.8,en-GB;q=0.7,en-US;q=0.6'
			]);
	}

	private function cCurl ($task, $data = []) {
		$data['task'] = $task;

		return $this->curl()->cookie($this->cookie)->post(self::API, $data);
	}
}