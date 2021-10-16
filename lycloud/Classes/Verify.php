<?php

namespace Classes;

use Classes\Authcode;

// 图形验证码类
class Verify {
	private static $INSTANCE;

	private $option = [
		'w'        => 110,             // 图片宽度
		'h'        => 40,              // 图片高度
		'len'      => 4,               // 验证码字符数
		'ext'      => 'gif',           // 图片格式
		'type'     => 2,               //0 数字，1 英文字母，2 数字 + 英文字母
		'fontSize' => 16,              // 验证码字体大小
		'bg'       => [243, 251, 254], //背景颜色
		'expiry'   => 300,             //有效期（秒）
		'useCurve' => true,            //干扰线
		'useNoise' => true             //干扰色块
	];
	const DICTIONARY = ['1234567890', 'ABCDEFGHJKLMNPQRTUVWXY', '3456789ABCDEFGHJKLMNPQRTUVWXY'];

	private function __construct (array $option = []) {
		$option = array_merge($this->option, $option);

		$option['key'] = $_SERVER['REMOTE_ADDR'];

		$dicts          = self::DICTIONARY;
		$dict           = str_split($dicts[$option['type'] < count($dicts) ? $option['type'] : 0]);
		$option['dict'] = $dict;
		shuffle($dict);
		$option['code'] = array_slice($dict, 0, $option['len']);

		$option['w'] || $option['w'] = $option['len'] * $option['fontSize'] * 1.5 + $option['fontSize'] * 1.5;

		$option['h'] || $option['h'] = $option['fontSize'] * 2;

		$this->option = $option;
	}

	public static function config (array $option = []) {
		self::$INSTANCE = new self($option);

		return self::$INSTANCE;
	}

	private static function instance () {
		if (!(self::$INSTANCE instanceof self))
			exit('尚未初始化配置');

		return self::$INSTANCE;
	}

	public static function create () {
		$instance = self::instance();

		ob_clean();
		@session_start();
		$_SESSION['verify'] = Authcode::encode(strtolower(join('', $instance->option['code'])),
			$instance->option['key'], $instance->option['expiry']);

		$im = imagecreate($instance->option['w'], $instance->option['h']);
		imagecolorallocate($im, $instance->option['bg'][0], $instance->option['bg'][1], $instance->option['bg'][2]);
		$_color = imagecolorallocate($im, mt_rand(1, 120), mt_rand(1, 120), mt_rand(1, 120));

		// 字体文件
		$ttf = __DIR__ . "/ttfs/t1.ttf";

		//干扰色块
		if ($instance->option['useNoise'])
			$instance->_writeNoise($im);

		//干扰线
		if ($instance->option['useCurve'])
			$instance->_writeCurve($im, $_color);

		// 写入验证码
		$codeNX = 0;
		foreach ($instance->option['code'] as $d) {
			$codeNX += mt_rand($instance->option['fontSize'] * 1.2, $instance->option['fontSize'] * 1.6);

			imagettftext($im, $instance->option['fontSize'], mt_rand(-40, 70), $codeNX, $instance->option['fontSize'] * 1.5, $_color, $ttf, $d);
		}

		header('Pragma: no-cache');
		switch (strtolower($instance->option['ext'])) {
			case 'png':
				@header("Content-Type:image/png");
				imagePNG($im);
				break;
			case 'gif':
				@header("Content-Type:image/gif");
				imageGIF($im);
				break;
			default:
				@header("Content-Type:image/jpeg");
				imageJPEG($im);
				break;
		}
		imagedestroy($im);
	}

	//验证验证码
	public static function check ($code) {
		@session_start();
		if (!isset($_SESSION['verify']) || !($de = Authcode::decode($_SESSION['verify'], $_SERVER['REMOTE_ADDR'])))
			return '图形验证码已过期！';

		if (strtolower($code) !== strtolower($de))
			return '图形验证码错误！';

		unset($_SESSION['verify']);

		return true;
	}

	//干扰线
	private function _writeCurve ($im, $color) {
		$A = mt_rand(1, $this->option['h'] / 2);
		$b = mt_rand($this->option['h'] / 4, $this->option['h'] / 4);
		$f = mt_rand($this->option['h'] / 4, $this->option['h'] / 4);
		$T = mt_rand($this->option['h'] * 1.5, $this->option['w'] * 2);
		$w = (2 * M_PI) / $T;

		$px1 = 0;
		$px2 = mt_rand($this->option['w'] / 2, $this->option['w'] * 0.667);
		for ($px = $px1; $px <= $px2; $px = $px + 0.9) {
			if (0 != $w) {
				$py = $A * sin($w * $px + $f) + $b + $this->option['h'] / 2;
				$i  = (int)(($this->option['fontSize'] - 6) / 4);
				while ($i > 0) {
					imagesetpixel($im, $px + $i, $py + $i, $color);
					$i--;
				}
			}
		}

		$A   = mt_rand(1, $this->option['h'] / 2);
		$f   = mt_rand($this->option['h'] / 4, $this->option['h'] / 4);
		$T   = mt_rand($this->option['h'] * 1.5, $this->option['w'] * 2);
		$w   = (2 * M_PI) / $T;
		$b   = $py - $A * sin($w * $px + $f) - $this->option['h'] / 2;
		$px1 = $px2;
		$px2 = $this->option['w'];
		for ($px = $px1; $px <= $px2; $px = $px + 0.9) {
			if (0 != $w) {
				$py = $A * sin($w * $px + $f) + $b + $this->option['h'] / 2;
				$i  = (int)(($this->option['fontSize'] - 8) / 4);
				while ($i > 0) {
					imagesetpixel($im, $px + $i, $py + $i, $color);
					$i--;
				}
			}
		}
	}

	//干扰色块
	private function _writeNoise ($im) {
		for ($i = 0; $i < 10; $i++) {
			$noiseColor = imagecolorallocate($im, mt_rand(150, 225), mt_rand(150, 225), mt_rand(150, 225));
			for ($j = 0; $j < 5; $j++) {
				imagestring($im, 5, mt_rand(-10, $this->option['w']), mt_rand(-10, $this->option['h']), $this->option['dict'][array_rand($this->option['dict'])], $noiseColor);
			}
		}
	}
}