<?php

namespace ProcessWire;

/**
 * AppApiFile adds the /file endpoint to the AppApi routes definition.
 *
 * You can access all files that are uploaded at any ProcessWire page.
 * Call /file/route/in/pagetree?file=test.jpg to access a page via its route in the pagetree.
 * Alternatively you can call /file/4242?file=test.jpg (e.g.) to access a page by its id.
 * The module will make sure that the page is accessible by the active user.
 * The GET-param "file" defines the basename of the file which you want to get.
 *
 * The following GET-params (optional) can be used to manipulate an image:
 *   - width
 *   - height
 *   - maxwidth
 *   - maxheight
 *   - cropX
 *   - cropY
 *
 * Use GET-Param "format=base64" to receive the file in base64 format.
 */
class AppApiFile extends WireData implements Module {
	public static function getModuleInfo() {
		return [
			'title' => 'AppApi - File',
			'summary' => 'AppApi-Module that adds a file endpoint',
			'version' => '1.0.4',
			'author' => 'Sebastian Schendel',
			'icon' => 'terminal',
			'href' => 'https://modules.processwire.com/modules/app-api-file/',
			'requires' => [
				'PHP>=7.2.0',
				'ProcessWire>=3.0.98',
				'AppApi>=1.2.0'
			],
			'autoload' => true,
			'singular' => true
		];
	}

	public function init() {
		$module = $this->wire('modules')->get('AppApi');
		$module->registerRoute(
			'file',
			[
				['OPTIONS', '{id:\d+}', ['GET']],
				['OPTIONS', '{path:.+}', ['GET']],
				['OPTIONS', '', ['GET']],
				['GET', '{id:\d+}', AppApiFile::class, 'pageIDFileRequest'],
				['GET', '{path:.+}', AppApiFile::class, 'pagePathFileRequest'],
				['GET', '', AppApiFile::class, 'dashboardFileRequest']
			]
		);
	}

	public static function pageIDFileRequest($data) {
		$data = AppApiHelper::checkAndSanitizeRequiredParameters($data, ['id|int']);
		$page = wire('pages')->get('id=' . $data->id);
		return self::fileRequest($page, '');
	}

	public static function dashboardFileRequest($data) {
		$page = wire('pages')->get('/');
		return self::fileRequest($page, '');
	}

	public static function pagePathFileRequest($data) {
		$data = AppApiHelper::checkAndSanitizeRequiredParameters($data, ['path|pagePathName']);
		$path = '/' . trim($data->path, '/') . '/';
		$page = wire('pages')->get('path="' . $path . '"');

		if (!$page->id && wire('modules')->isInstalled('LanguageSupport')) {
			// Check if its a root path
			$rootPage = wire('pages')->get('/');
			foreach ($rootPage->urls as $key => $value) {
				if ($value !== $path) {
					continue;
				}
				return self::fileRequest($rootPage, $key);
			}
		}

		$info = wire('pages')->pathFinder()->get($path);
		if (!empty($info['language']['name'])) {
			return self::fileRequest($page, $info['language']['name']);
		}

		return self::fileRequest($page, '');
	}

	protected static function fileRequest(Page $page, $languageFromPath) {
		if (!$page || !$page->id) {
			throw new ForbiddenException();
		}

		if (wire('modules')->isInstalled('LanguageSupport')) {
			if (!empty($languageFromPath) && wire('languages')->get($languageFromPath) instanceof Page && wire('languages')->get($languageFromPath)->id) {
				wire('user')->language = wire('languages')->get($languageFromPath);
			} else {
				$lang = '' . strtolower(wire('input')->get->pageName('lang'));
				$langAlt = SELF::getLanguageCode($lang);

				if (!empty($lang) && wire('languages')->get($lang) instanceof Page && wire('languages')->get($lang)->id) {
					wire('user')->language = wire('languages')->get($lang);
				} elseif (!empty($langAlt) && wire('languages')->get($langAlt) instanceof Page && wire('languages')->get($langAlt)->id) {
					wire('user')->language = wire('languages')->get($langAlt);
				} else {
					wire('user')->language = wire('languages')->getDefault();
				}
			}
		}

		if ($page instanceof RepeaterPage) {
			$rootPage = $page->getForPage();
			if (!$rootPage || !$rootPage->id || !$rootPage->viewable('', false)) {
				throw new ForbiddenException();
			}
		} elseif (!$page->viewable('', false)) {
			throw new ForbiddenException();
		}

		$filename = wire('input')->get('file', 'filename');
		if (!$filename || !is_string($filename)) {
			throw new BadRequestException('No valid filename.');
		}

		$file = $page->filesManager->getFile($filename);
		if (!$file || empty($file)) {
			throw new NotFoundException('File not found: ' . $filename);
		}

		if ($file instanceof Pageimage) {
			// Modify image-size:
			$width = wire('input')->get('width', 'intUnsigned', 0);
			$height = wire('input')->get('height', 'intUnsigned', 0);
			$maxWidth = wire('input')->get('maxwidth', 'intUnsigned', 0);
			$maxHeight = wire('input')->get('maxheight', 'intUnsigned', 0);
			$cropX = wire('input')->get('cropx', 'intUnsigned', 0);
			$cropY = wire('input')->get('cropy', 'intUnsigned', 0);

			$options = [
				'webpAdd' => (wire('input')->get('webpAdd', 'intUnsigned', 0) !== 0)
			];

			if ($cropX > 0 && $cropY > 0 && $width > 0 && $height > 0) {
				$file = $file->crop($cropX, $cropY, $width, $height, $options);
			} elseif ($width > 0 && $height > 0) {
				$file = $file->size($width, $height, $options);
			} elseif ($width > 0) {
				$file = $file->width($width, $options);
			} elseif ($height > 0) {
				$file = $file->height($height, $options);
			}

			if ($maxWidth > 0 && $maxHeight > 0) {
				$file = $file->maxSize($maxWidth, $maxHeight, $options);
			} elseif ($maxWidth > 0) {
				$file = $file->maxWidth($maxWidth, $options);
			} elseif ($maxHeight > 0) {
				$file = $file->maxHeight($maxHeight, $options);
			}
		}

		$filepath = $file->filename;
		$fileinfo = pathinfo($filepath);
		$filename = $fileinfo['basename'];

		$isStreamable = !!isset($_REQUEST['stream']);

		if (!is_file($filepath)) {
			throw new NotFoundException('File not found: ' . $filename);
		}

		$filesize = filesize($filepath);
		$openfile = @fopen($filepath, 'rb');

		if (!$openfile) {
			throw new InternalServererrorException();
		}

		header('Date: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($filepath)) . ' GMT');
		header('ETag: "' . md5_file($filepath) . '"');
		header('Accept-Encoding: gzip, deflate');

		// Is Base64 requested?
		if (wire('input')->get('format', 'name', '') === 'base64') {
			$data = file_get_contents($filepath);
			echo 'data:' . mime_content_type($filepath) . ';base64,' . base64_encode($data);
			exit();
		}

		header('Pragma: public');
		header('Expires: -1');
		// header("Cache-Control: public,max-age=14400,public");
		header('Cache-Control: public, must-revalidate, post-check=0, pre-check=0');
		// header("Content-Disposition: attachment; filename=\"$filename\"");
		header('Content-type: ' . mime_content_type($filepath));
		header('Content-Transfer-Encoding: binary');

		if ($isStreamable) {
			header("Content-Disposition: inline; filename=\"$filename\"");
		} else {
			header("Content-Disposition: attachment; filename=\"$filename\"");
		}

		$range = '';
		if (isset($_SERVER['HTTP_RANGE']) || isset($_SERVER['HTTP_CONTENT_RANGE'])) {
			if (isset($_SERVER['HTTP_CONTENT_RANGE'])) {
				$rangeParts = explode(' ', $_SERVER['HTTP_CONTENT_RANGE'], 2);
			} else {
				$rangeParts = explode('=', $_SERVER['HTTP_RANGE'], 2);
			}

			$sizeUnit = false;
			if (isset($rangeParts[0])) {
				$sizeUnit = $rangeParts[0];
			}

			$rangeOrig = false;
			if (isset($rangeParts[1])) {
				$rangeOrig = $rangeParts[1];
			}

			if ($sizeUnit != 'bytes') {
				throw new AppApiException('Requested Range Not Satisfiable', 416);
			}

			//multiple ranges could be specified at the same time, but for simplicity only serve the first range
			//http://tools.ietf.org/id/draft-ietf-http-range-retrieval-00.txt
			$rangeOrigParts = explode(',', $rangeOrig, 2);

			$range = '';
			if (isset($rangeOrigParts[0])) {
				$range = $rangeOrigParts[0];
			}

			$extraRanges = '';
			if (isset($rangeOrigParts[1])) {
				$extraRanges = $rangeOrigParts[1];
			}
		}

		$rangeParts = explode('-', $range, 2);

		$filestart = '';
		if (isset($rangeParts[0])) {
			$filestart = $rangeParts[0];
		}

		$fileend = '';
		if (isset($rangeParts[1])) {
			$fileend = $rangeParts[1];
		}

		if (empty($fileend)) {
			$fileend = $filesize - 1;
		} else {
			$fileend = min(abs(intval($fileend)), ($filesize - 1));
		}

		if (empty($filestart) || $fileend < abs(intval($filestart))) {
			// Default: Output filepart from start (0)
			$filestart = 0;
		} else {
			$filestart = max(abs(intval($filestart)), 0);
		}

		if ($filestart > 0 || $fileend < ($filesize - 1)) {
			// Output part of file
			header('HTTP/1.1 206 Partial Content');
			header('Content-Range: bytes ' . $filestart . '-' . $fileend . '/' . $filesize);
			header('Content-Length: ' . ($fileend - $filestart + 1));
		} else {
			// Output full file
			header('HTTP/1.0 200 OK');
			header("Content-Length: $filesize");
		}

		header('Accept-Ranges: bytes');
		// header('Accept-Ranges: 0-'.$filesize);
		set_time_limit(0);
		fseek($openfile, $filestart);
		ob_start();
		while (!feof($openfile)) {
			print(@fread($openfile, (1024 * 8)));
			ob_flush();
			flush();
			if (connection_status() != 0) {
				@fclose($openfile);
				exit;
			}
		}

		@fclose($openfile);
		exit;
	}
}
