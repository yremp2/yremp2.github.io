<?php

namespace Classes;

use Exception;

class Redis {
	private static $INSTANCE;

	private $redis;
	private $prefix = '';

	/**
	 * @param  string  $host
	 * @param  int     $port
	 * @param  string  $password  密码
	 */
	private function __construct ($host = '127.0.0.1', $port = 6379, $password = '') {
		// 检测php环境
		if (!extension_loaded('Redis'))
			exit(json_encode([
				'code' => -10, 'msg' => '未开启redis扩展'
			], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

		try {
			$this->redis = new \Redis();
			$this->redis->connecT($host, $port);
			if ($password !== '') $this->redis->auth($password);
		} catch (Exception $e) {
			exit(json_encode([
				'code' => -11, 'msg' => 'Redis 连接失败:' . $e->getMessage()
			], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
		}
	}

	/**
	 * redis 实例生成
	 *
	 * @param  array  $config
	 *
	 * @return Redis
	 */
	public static function instance ($config = []) {
		if (!(self::$INSTANCE instanceof self)) {
			$host     = isset($config['host']) ? $config['host'] : '127.0.0.1';
			$port     = isset($config['port']) ? $config['port'] : 6379;
			$password = isset($config['password']) ? $config['password'] : '';
			try {
				self::$INSTANCE = new self($host, $port, $password);
			} catch (Exception $e) {
				exit($e->getMessage());
			}
		}
		self::$INSTANCE->prefix = '';

		return self::$INSTANCE;
	}

	public static function prefix ($prefix = '', $select = 0) {
		$that = self::instance();
		$that->redis->select($select);
		$that->prefix = $prefix;

		return $that;
	}

	// 切换库
	public static function select ($select, $prefix = '') {
		$that = self::instance();
		$that->redis->select($select);
		$that->prefix = $prefix;

		return $that;
	}

	/**
	 * 设置值  构建一个字符串
	 *
	 * @param  string|array-key  $key      KEY名称或键值对
	 * @param  string|int        $value    设置值或过期时间
	 * @param  int               $timeOut  时间  -1表示无过期时间
	 *
	 * @return boolean
	 */
	public function set ($key, $value, $timeOut = -1) {
		$key = $this->convertKey($key);
		if (is_array($key)) {
			$timeOut = $value;
			$retRes  = $this->redis->mset($key);
			if ($retRes && $timeOut > 0)
				foreach ($key as $k => $v)
					$this->redis->expire($k, $timeOut);

			return $retRes;
		}

		return $timeOut > 0 ? $this->redis->setex($key, $timeOut, $value) : $this->redis->set($key, $value);
	}

	public function expiry ($key, $expiry = -1) {
		$this->redis->expire($this->convertKey($key), $expiry);

		return $this;
	}

	/*
	 * 构建一个集合(无序集合)
	 * @param string $key 集合Y名称
	 * @param string|array $value  值
	 */
	public function sadd ($key, $value) {
		return $this->redis->sadd($this->convertKey($key), $value);
	}

	/*
	 * 构建一个集合(有序集合)
	 * @param string $key 集合名称
	 * @param string|array $value  值
	 * @return mixed
	 */
	public function zadd ($key, $score, $value, $value1) {
		return $this->redis->zadd($this->convertKey($key), $score, $value, $value1);
	}

	/**
	 * 取集合对应元素
	 *
	 * @param  string  $key  集合名字
	 *
	 * @return array
	 */
	public function gets ($key) {
		return $this->redis->smembers($key);
	}

	/**
	 * 构建一个列表(先进后去，类似栈)
	 *
	 * @param  string  $key    KEY名称
	 * @param  string  $value  值
	 *
	 * @return false|int
	 */
	public function lpush ($key, $value) {
		return $this->redis->LPUSH($this->convertKey($key), $value);
	}

	/**
	 * 构建一个列表(先进先去，类似队列)
	 *
	 * @param  string  $key    KEY名称
	 * @param  string  $value  值
	 *
	 * @return bool|int
	 */
	public function rpush ($key, $value) {
		return $this->redis->rpush($this->convertKey($key), $value);
	}

	/**
	 * 获取所有列表数据（从头到尾取）
	 *
	 * @param  string  $key   KEY名称
	 * @param  int     $head  开始
	 * @param  int     $tail  结束
	 *
	 * @return array
	 */
	public function lrange ($key, $head, $tail) {
		return $this->redis->lrange($this->convertKey($key), $head, $tail);
	}

	/**
	 * HASH类型
	 *
	 * @param  string  $tableName  表名字key
	 * @param          $field
	 * @param  string  $value      值
	 *
	 * @return bool|int
	 */
	public function hset ($tableName, $field, $value) {
		return $this->redis->hset($this->convertKey($tableName), $field, $value);
	}

	public function hget ($tableName, $field) {
		return $this->redis->hget($this->convertKey($tableName), $field);
	}

	/**
	 * 通过key获取数据
	 *
	 * @param  string|array  $key  KEY名称
	 *
	 * @return mixed
	 */
	public function get ($key) {
		$key = $this->convertKey($key);

		return is_array($key) ? $this->redis->mget($key) : $this->redis->get($key);
	}

	/**
	 * 获取所有匹配的key名，不是值
	 *
	 * @param  string  $rule  dos表达式规则
	 *
	 * @return array
	 */
	public function keys ($rule = '*') {
		return $this->redis->keys($this->convertKey($rule));
	}

	/**
	 * 删除key数据
	 *
	 * @param  string|array  $key
	 *
	 * @return int
	 */
	public function del ($key) {
		return $this->redis->del($this->convertKey($key));
	}

	/**
	 * 数据自增
	 *
	 * @param  string  $key  KEY名称
	 *
	 * @return int
	 */
	public function increment ($key) {
		return $this->redis->incr($this->convertKey($key));
	}

	/**
	 * 数据自减
	 *
	 * @param  string  $key  KEY名称
	 *
	 * @return int
	 */
	public function decrement ($key) {
		return $this->redis->decr($this->convertKey($key));
	}

	/**
	 * 判断key是否存在
	 *
	 * @param  string  $key  KEY名称
	 *
	 * @return boolean
	 */
	public function exists ($key) {
		return $this->redis->exists($this->convertKey($key));
	}

	/**
	 * 重命名- 当且仅当newkey不存在时，将key改为newkey ，当newkey存在时候会报错哦RENAME
	 *  和 rename不一样，它是直接更新（存在的值也会直接更新）
	 *
	 * @param          $key
	 * @param  string  $newKey  新key名称
	 *
	 * @return bool
	 */
	public function updateName ($key, $newKey) {
		return $this->redis->RENAMENX($this->convertKey($key), $this->convertKey($newKey));
	}

	/**
	 * 获取KEY存储的值类型
	 * none(key不存在) int(0)  string(字符串) int(1)   list(列表) int(3)  set(集合) int(2)   zset(有序集) int(4)    hash(哈希表) int(5)
	 *
	 * @param  string  $key  KEY名称
	 *
	 * @return int
	 */
	public function dataType ($key) {
		return $this->redis->type($this->convertKey($key));
	}

	/**
	 * 清空数据
	 */
	public function flushAll () {
		return $this->redis->flushAll();
	}

	public function __clone () {
		trigger_error('Clone is not allow!', E_USER_ERROR);
	}

	/**
	 * 返回redis对象
	 * redis有非常多的操作方法，这里只封装了一部分
	 * 拿着这个对象就可以直接调用redis自身方法
	 * eg:$redis->redisOtherMethods()->keys('*a*')   keys方法没封
	 */
	public function redisOtherMethods () {
		return $this->redis;
	}

	/**
	 * 根据key所设定的前缀转化为真实key
	 *
	 * @param  array|string  $key
	 *
	 * @return array|string
	 */
	private function convertKey ($key) {
		if (empty($this->prefix))
			return $key;

		if (!is_array($key))
			return $this->prefix . $key;

		$keys  = [];
		$assoc = array_keys($key) === range(0, count($key) - 1);
		foreach ($key as $k => $v) {
			if ($assoc)
				$keys[] = $this->prefix . $v;
			else
				$keys[$this->prefix . $k] = $v;
		}

		return $keys;
	}
}