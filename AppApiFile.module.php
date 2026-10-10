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
 *   - cropx
 *   - cropy
 * Images are never scaled up: a requested size above the original size is
 * reduced to the original size (width and height together are reduced by the
 * same factor). Requested sizes are limited to MAX_DIMENSION pixels per axis;
 * without size parameters the original stays available.
 *
 * Use GET-Param "format=base64" to receive the file in base64 format.
 *
 * Caching: add a non-empty GET-param "v" (e.g. a hash of the file's
 * modification time) and change it whenever the file changes. Cache-Control:
 *   - guest, public page, with "v": public, max-age=31536000, immutable
 *   - guest, public page, without "v": no-cache
 *   - logged-in user, public page, with "v": private, max-age=31536000, immutable
 *   - all other cases (non-public page, or logged in without "v"): private, no-cache
 * A matching If-None-Match header is answered with 304 Not Modified (not for
 * format=base64).
 *
 * Files of pages that the current user cannot view are answered with 404,
 * the same as an unknown page. Hook after AppApiFile::isFileAccessible() to
 * add your own access conditions.
 */
class AppApiFile extends WireData implements Module {
	/**
	 * Maximum width and height in pixels that can be requested for an image.
	 */
	const MAX_DIMENSION = 4096;

	public static function getModuleInfo() {
		return [
			'title' => 'AppApi - File',
			'summary' => 'AppApi-Module that adds a file endpoint',
			'version' => '2.1.0',
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
				['OPTIONS', '{id:\d+}', ['GET'], [], [], [
					// documentation
					'summary' => 'Preflight options',
					'tags' => ['File'],
					'security' => [
						['apiKey' => []],
						['bearerAuth' => []]
					],
					'parameters' => [
						[
							'name' => 'id:\d+',
							'in' => 'path',
							'description' => 'Page ID of the parent page of the requested file',
							'required' => true,
							'schema' => [
								'type' => 'integer',
								'format' => 'int64'
							]
						]
					]
				]],
				['OPTIONS', '{path:.+}', ['GET'], [], [], [
					// documentation
					'summary' => 'Preflight options',
					'tags' => ['File'],
					'security' => [
						['apiKey' => []],
						['bearerAuth' => []]
					],
					'parameters' => [
						[
							'name' => 'path:.+',
							'in' => 'path',
							'description' => 'Page path of the parent page of the requested file',
							'required' => true,
							'schema' => [
								'type' => 'string'
							]
						]
					]
				]],
				['OPTIONS', '', ['GET'], [], [], [
					// documention
					'summary' => 'Preflight options',
					'tags' => ['File'],
					'security' => [
						['apiKey' => []],
						['bearerAuth' => []]
					],
				]],
				['GET', '{id:\d+}', AppApiFile::class, 'pageIDFileRequest', [], [
					// documentation
					'summary' => 'Get a file for a page id.',
					'tags' => ['File'],
					'security' => [
						['apiKey' => []],
						['bearerAuth' => []]
					],
					'parameters' => [
						[
							'name' => 'id:\d+',
							'in' => 'path',
							'description' => 'Page ID of the parent page of the requested file',
							'required' => true,
							'schema' => [
								'type' => 'integer',
								'format' => 'int64'
							]
						]
					]
				]],
				['GET', '{path:.+}', AppApiFile::class, 'pagePathFileRequest', [], [
					// documentation
					'summary' => 'Get a file for a page path.',
					'tags' => ['File'],
					'security' => [
						['apiKey' => []],
						['bearerAuth' => []]
					],
					'parameters' => [
						[
							'name' => 'path:.+',
							'in' => 'path',
							'description' => 'Page path of the parent page of the requested file',
							'required' => true,
							'schema' => [
								'type' => 'string'
							]
						]
					]
				]],
				['GET', '', AppApiFile::class, 'dashboardFileRequest', [], [
					'summary' => 'Get a file for the root page',
					'tags' => ['File'],
					'security' => [
						['apiKey' => []],
						['bearerAuth' => []]
					],
				]]
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
		Router::clearOutputBuffer();

		if (!$page || !$page->id) {
			throw new NotFoundException();
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

		// Access to files of repeater items is decided by the page that owns the repeater.
		$accessPage = $page;
		if ($page instanceof RepeaterPage) {
			$accessPage = $page->getForPage();
			if (!$accessPage || !$accessPage->id) {
				throw new NotFoundException();
			}
		}

		if (!wire('modules')->get('AppApiFile')->isFileAccessible($page, $accessPage)) {
			// Same answer as for an unknown id, so that hidden pages cannot be detected.
			throw new NotFoundException();
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

			[$width, $height] = self::limitSize($width, $height, self::MAX_DIMENSION, self::MAX_DIMENSION);
			$maxWidth = min($maxWidth, self::MAX_DIMENSION);
			$maxHeight = min($maxHeight, self::MAX_DIMENSION);

			// Never scale up: sizes above the original are reduced to the original size.
			$originalWidth = (int) $file->width;
			$originalHeight = (int) $file->height;
			if ($originalWidth > 0 && $originalHeight > 0) {
				[$width, $height] = self::limitSize($width, $height, $originalWidth, $originalHeight);

				$isCrop = $cropX > 0 && $cropY > 0 && $width > 0 && $height > 0;
				$isOriginalWidth = $width === 0 || $width === $originalWidth;
				$isOriginalHeight = $height === 0 || $height === $originalHeight;
				if (!$isCrop && ($width > 0 || $height > 0) && $isOriginalWidth && $isOriginalHeight) {
					// The requested size is the original size: deliver the original file.
					$width = 0;
					$height = 0;
				}
			}

			$options = [
				'webpAdd' => ((wire('input')->get('webpAdd') === 'true' || wire('input')->get('webpAdd', 'intUnsigned', 0) !== 0) && self::isWebpSupported($file))
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
		if ($file instanceof Pageimage && (wire('input')->get('webp') === 'true' || wire('input')->get('webp', 'intUnsigned', 0) !== 0) && self::isWebpSupported($file)) {
			$filepath = $file->webp->filename;
		}
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

		$etag = '"' . md5_file($filepath) . '"';

		header('Date: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($filepath)) . ' GMT');
		header('ETag: ' . $etag);
		header('Accept-Encoding: gzip, deflate');

		// Is Base64 requested?
		if (wire('input')->get('format', 'name', '') === 'base64') {
			$data = file_get_contents($filepath);
			echo 'data:' . mime_content_type($filepath) . ';base64,' . base64_encode($data);
			exit();
		}

		// A non-empty "v" parameter marks a versioned URL: the client changes it
		// whenever the file changes, so the response may be cached for a year.
		// Only guests get "public" for files of public pages, so that shared
		// caches never store a response that depended on a user's rights.
		// Logged-in users may cache versioned files of public pages in their
		// own browser; files of non-public pages are always revalidated, so
		// that access is checked on every use.
		$version = wire('input')->get('v');
		$isVersioned = is_string($version) && $version !== '';
		$isPublic = $accessPage->isPublic();
		if (wire('user')->isGuest()) {
			if (!$isPublic) {
				$cacheControl = 'private, no-cache';
			} elseif ($isVersioned) {
				$cacheControl = 'public, max-age=31536000, immutable';
			} else {
				$cacheControl = 'no-cache';
			}
		} elseif ($isPublic && $isVersioned) {
			$cacheControl = 'private, max-age=31536000, immutable';
		} else {
			$cacheControl = 'private, no-cache';
		}

		// Remove headers that the session may have set (e.g. "Pragma: no-cache").
		header_remove('Pragma');
		header_remove('Expires');
		header('Cache-Control: ' . $cacheControl);

		if (self::etagMatches($etag)) {
			@fclose($openfile);
			http_response_code(304);
			exit;
		}

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

	/**
	 * May the current user get the files of the page?
	 *
	 * Called for every file request after the authentication, so the current
	 * user is set. Hook after this method to add your own conditions; if it
	 * returns false, the request is answered with 404 Not Found, the same as
	 * an unknown page.
	 *
	 * @param Page $page the page that holds the requested file (may be a repeater page)
	 * @param Page $accessPage the page that decides the access: $page itself, or for a repeater page the page that owns the repeater
	 * @return bool by default $accessPage->viewable('', false)
	 */
	public function ___isFileAccessible(Page $page, Page $accessPage): bool {
		return $accessPage->viewable('', false);
	}

	/**
	 * Format requested language
	 *
	 * @param string $key
	 * @return void
	 */
	public static function getLanguageCode($key) {
		$languageCodes = [
			'aa' => 'afar',
			'ab' => 'abkhazian',
			'af' => 'afrikaans',
			'am' => 'amharic',
			'ar' => 'arabic',
			'ar-ae' => 'arabic-u-a-e',
			'ar-bh' => 'arabic-bahrain',
			'ar-dz' => 'arabic-algeria',
			'ar-eg' => 'arabic-egypt',
			'ar-iq' => 'arabic-iraq',
			'ar-jo' => 'arabic-jordan',
			'ar-kw' => 'arabic-kuwait',
			'ar-lb' => 'arabic-lebanon',
			'ar-ly' => 'arabic-libya',
			'ar-ma' => 'arabic-morocco',
			'ar-om' => 'arabic-oman',
			'ar-qa' => 'arabic-qatar',
			'ar-sa' => 'arabic-saudi-arabia',
			'ar-sy' => 'arabic-syria',
			'ar-tn' => 'arabic-tunisia',
			'ar-ye' => 'arabic-yemen',
			'as' => 'assamese',
			'ay' => 'aymara',
			'az' => 'azeri',
			'ba' => 'bashkir',
			'be' => 'belarusian',
			'bg' => 'bulgarian',
			'bh' => 'bihari',
			'bi' => 'bislama',
			'bn' => 'bengali',
			'bo' => 'tibetan',
			'br' => 'breton',
			'ca' => 'catalan',
			'co' => 'corsican',
			'cs' => 'czech',
			'cy' => 'welsh',
			'da' => 'danish',
			'de' => 'german',
			'de-at' => 'german-austria',
			'de-ch' => 'german-switzerland',
			'de-li' => 'german-liechtenstein',
			'de-lu' => 'german-luxembourg',
			'div' => 'divehi',
			'dz' => 'bhutani',
			'el' => 'greek',
			'en' => 'english',
			'en-au' => 'english-australia',
			'en-bz' => 'english-belize',
			'en-ca' => 'english-canada',
			'en-gb' => 'english-united-kingdom',
			'en-ie' => 'english-ireland',
			'en-jm' => 'english-jamaica',
			'en-nz' => 'english-new-zealand',
			'en-ph' => 'english-philippines',
			'en-tt' => 'english-trinidad',
			'en-us' => 'english-united States',
			'en-za' => 'english-south-africa',
			'en-zw' => 'english-zimbabwe',
			'eo' => 'esperanto',
			'es' => 'spanish',
			'es-ar' => 'spanish-argentina',
			'es-bo' => 'spanish-bolivia',
			'es-cl' => 'spanish-chile',
			'es-co' => 'spanish-colombia',
			'es-cr' => 'spanish-costa-rica',
			'es-do' => 'spanish-dominican-republic',
			'es-ec' => 'spanish-ecuador',
			'es-es' => 'spanish-espana',
			'es-gt' => 'spanish-guatemala',
			'es-hn' => 'spanish-honduras',
			'es-mx' => 'spanish-mexico',
			'es-ni' => 'spanish-nicaragua',
			'es-pa' => 'spanish-panama',
			'es-pe' => 'spanish-peru',
			'es-pr' => 'spanish-puerto-rico',
			'es-py' => 'spanish-paraguay',
			'es-sv' => 'spanish-el-salvador',
			'es-us' => 'spanish-united-states',
			'es-uy' => 'spanish-uruguay',
			'es-ve' => 'spanish-venezuela',
			'et' => 'estonian',
			'eu' => 'basque',
			'fa' => 'farsi',
			'fi' => 'finnish',
			'fj' => 'fiji',
			'fo' => 'faeroese',
			'fr' => 'french',
			'fr-be' => 'french-belgium',
			'fr-ca' => 'french-canada',
			'fr-ch' => 'french-switzerland',
			'fr-lu' => 'french-luxembourg',
			'fr-mc' => 'french-monaco',
			'fy' => 'frisian',
			'ga' => 'irish',
			'gd' => 'gaelic',
			'gl' => 'galician',
			'gn' => 'guarani',
			'gu' => 'gujarati',
			'ha' => 'hausa',
			'he' => 'hebrew',
			'hi' => 'hindi',
			'hr' => 'croatian',
			'hu' => 'hungarian',
			'hy' => 'armenian',
			'ia' => 'interlingua',
			'id' => 'indonesian',
			'ie' => 'interlingue',
			'ik' => 'inupiak',
			'in' => 'indonesian',
			'is' => 'icelandic',
			'it' => 'italian',
			'it-ch' => 'italian-switzerland',
			'iw' => 'hebrew',
			'ja' => 'japanese',
			'ji' => 'yiddish',
			'jw' => 'javanese',
			'ka' => 'georgian',
			'kk' => 'kazakh',
			'kl' => 'greenlandic',
			'km' => 'cambodian',
			'kn' => 'kannada',
			'ko' => 'korean',
			'kok' => 'konkani',
			'ks' => 'kashmiri',
			'ku' => 'kurdish',
			'ky' => 'kirghiz',
			'kz' => 'kyrgyz',
			'la' => 'latin',
			'ln' => 'lingala',
			'lo' => 'laothian',
			'ls' => 'slovenian',
			'lt' => 'lithuanian',
			'lv' => 'latvian',
			'mg' => 'malagasy',
			'mi' => 'maori',
			'mk' => 'fyro-macedonian',
			'ml' => 'malayalam',
			'mn' => 'mongolian',
			'mo' => 'moldavian',
			'mr' => 'marathi',
			'ms' => 'malay',
			'mt' => 'maltese',
			'my' => 'burmese',
			'na' => 'nauru',
			'nb-no' => 'norwegian-bokmal',
			'ne' => 'nepali-india',
			'nl' => 'dutch',
			'nl-be' => 'dutch-belgium',
			'nn-no' => 'norwegian',
			'no' => 'norwegian-nokmal',
			'oc' => 'occitan',
			'om' => 'afan-oromoor-oriya',
			'or' => 'oriya',
			'pa' => 'punjabi',
			'pl' => 'polish',
			'ps' => 'pashto',
			'pt' => 'portuguese',
			'pt-br' => 'portuguese-brazil',
			'qu' => 'quechua',
			'rm' => 'rhaeto-romanic',
			'rn' => 'kirundi',
			'ro' => 'romanian',
			'ro-md' => 'romanian-moldova',
			'ru' => 'russian',
			'ru-md' => 'russian-moldova',
			'rw' => 'kinyarwanda',
			'sa' => 'sanskrit',
			'sb' => 'sorbian',
			'sd' => 'sindhi',
			'sg' => 'sangro',
			'sh' => 'serbo-croatian',
			'si' => 'singhalese',
			'sk' => 'slovak',
			'sl' => 'slovenian',
			'sm' => 'samoan',
			'sn' => 'shona',
			'so' => 'somali',
			'sq' => 'albanian',
			'sr' => 'serbian',
			'ss' => 'siswati',
			'st' => 'sesotho',
			'su' => 'sundanese',
			'sv' => 'swedish',
			'sv-fi' => 'swedish-finland',
			'sw' => 'swahili',
			'sx' => 'sutu',
			'syr' => 'syriac',
			'ta' => 'tamil',
			'te' => 'telugu',
			'tg' => 'tajik',
			'th' => 'thai',
			'ti' => 'tigrinya',
			'tk' => 'turkmen',
			'tl' => 'tagalog',
			'tn' => 'tswana',
			'to' => 'tonga',
			'tr' => 'turkish',
			'ts' => 'tsonga',
			'tt' => 'tatar',
			'tw' => 'twi',
			'uk' => 'ukrainian',
			'ur' => 'urdu',
			'us' => 'english',
			'uz' => 'uzbek',
			'vi' => 'vietnamese',
			'vo' => 'volapuk',
			'wo' => 'wolof',
			'xh' => 'xhosa',
			'yi' => 'yiddish',
			'yo' => 'yoruba',
			'zh' => 'chinese',
			'zh-cn' => 'chinese-china',
			'zh-hk' => 'chinese-hong-kong',
			'zh-mo' => 'chinese-macau',
			'zh-sg' => 'chinese-singapore',
			'zh-tw' => 'chinese-taiwan',
			'zu' => 'zulu'
		];

		$code = '';
		if (!empty($languageCodes[$key])) {
			$code = $languageCodes[$key];
		}

		return $code;
	}

	/**
	 * Reduce a requested size so that it fits into the given limits. If width
	 * and height are both requested, both are reduced by the same factor to
	 * keep the requested aspect ratio. 0 means "not requested".
	 *
	 * @return int[] [width, height]
	 */
	protected static function limitSize(int $width, int $height, int $limitWidth, int $limitHeight): array {
		if ($width > 0 && $height > 0) {
			$factor = min(1, $limitWidth / $width, $limitHeight / $height);
			if ($factor < 1) {
				$width = max(1, (int) round($width * $factor));
				$height = max(1, (int) round($height * $factor));
			}
			return [$width, $height];
		}

		return [min($width, $limitWidth), min($height, $limitHeight)];
	}

	/**
	 * Check whether the request's If-None-Match header contains the given ETag
	 */
	protected static function etagMatches(string $etag): bool {
		$header = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : '';
		if ($header === '') {
			return false;
		}
		if ($header === '*') {
			return true;
		}

		foreach (explode(',', $header) as $candidate) {
			$candidate = trim($candidate);
			if (stripos($candidate, 'W/') === 0) {
				$candidate = substr($candidate, 2);
			}
			if ($candidate === $etag) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if server can build webp images
	 */
	protected static function isWebpSupported(Pageimage $image) {
		if (isset(wire('config')->webpSupported) && !wire('config')->webpSupported) {
			return false;
		}

		if ($image->ext === 'svg') {
			return false;
		}

		if ($image->url === $image->webp->url) {
			return false;
		}

		return true;
	}
}
