AppApiFile adds the /file endpoint to the [AppApi](https://modules.processwire.com/modules/app-api/) routes definition. Makes it possible to query files via the api.

[![Current Version](https://img.shields.io/github/v/tag/Sebiworld/AppApiFile?label=Current%20Version)](https://img.shields.io/github/v/tag/Sebiworld/AppApiFile?label=Current%20Version) [![Current Version](https://img.shields.io/github/issues-closed-raw/Sebiworld/AppApiFile?color=%2356d364)](https://img.shields.io/github/issues-closed-raw/Sebiworld/AppApiFile?color=%2356d364) [![Current Version](https://img.shields.io/github/issues-raw/Sebiworld/AppApiFile)](https://img.shields.io/github/issues-raw/Sebiworld/AppApiFile)

<a href="https://www.buymeacoffee.com/Sebi.dev" target="_blank"><img src="https://cdn.buymeacoffee.com/buttons/default-orange.png" alt="Buy Me A Coffee" height="41" width="174"></a>

|                     |                                                                                                                                          |
| ------------------: | ---------------------------------------------------------------------------------------------------------------------------------------- |
| ProcessWire-Module: | [https://modules.processwire.com/modules/app-api-file/](https://modules.processwire.com/modules/app-api-file/)                           |
|      Support-Forum: | [https://processwire.com/talk/topic/26272-appapi-module-appapifile/](https://processwire.com/talk/topic/26272-appapi-module-appapifile/) |
|         Repository: | [https://github.com/Sebiworld/AppApiFile](https://github.com/Sebiworld/AppApiFile)                                                       |

Relies on AppApi:

|                |                                                                                                                            |
| -------------: | -------------------------------------------------------------------------------------------------------------------------- |
| AppApi-Module: | [https://modules.processwire.com/modules/app-api/](https://modules.processwire.com/modules/app-api/)                       |
| Support-Forum: | [https://processwire.com/talk/topic/24014-new-module-appapi/](https://processwire.com/talk/topic/24014-new-module-appapi/) |
|    Repository: | [https://github.com/Sebiworld/AppApi](https://github.com/Sebiworld/AppApi)                                                 |
|   AppApi Wiki: | [https://github.com/Sebiworld/AppApi/wiki](https://github.com/Sebiworld/AppApi/wiki)                                       |
|                |                                                                                                                            |

<a name="installation"></a>

## Installation

AppApiFile relies on the base module AppApi, which must be installed before AppApiFile can do its work.

AppApi and AppApiFile can be installed like every other module in ProcessWire. Check the following guide for detailed information: [How-To Install or Uninstall Modules](http://modules.processwire.com/install-uninstall/)

The prerequisites are **PHP>=7.2.0** and a **ProcessWire version >=3.93.0** (+ **AppApi>=1.2.0**). However, this is also checked during the installation of the module. No further dependencies.

<a name="features"></a>

## Features

You can access all files that are uploaded at any ProcessWire page. Call `/file/route/in/pagetree?file=test.jpg` to access a page via its route in the page tree. Alternatively you can call /file/4242?file=test.jpg (e.g.,) to access a page by its id. The module will make sure that the page is accessible by the active user.

The GET-param "file" defines the basename of the file which you want to get.

The following GET-params (optional) can be used to manipulate an image:

| Param         | Value    | Description                                                                       |
| ------------- | -------- | --------------------------------------------------------------------------------- |
| **width**     | int >= 0 | Width of the requested image                                                      |
| **height**    | int >= 0 | Height of the requested image                                                     |
| **maxwidth**  | int >= 0 | Maximum Width, if the original image's resolution is sufficient                   |
| **maxheight** | int >= 0 | Maximum Height, if the original image's resolution is sufficient                  |
| **cropx**     | int >= 0 | Start-X-position for cropping (crop enabled, if width, height, cropx & cropy set) |
| **cropy**     | int >= 0 | Start-Y-position for cropping (crop enabled, if width, height, cropx & cropy set) |

Images are never scaled up. If only `width` or only `height` is larger than the original image, the original size is delivered. If `width` and `height` are both set and one of them is larger than the original, both are reduced by the same factor until the image fits into the original size, so the requested aspect ratio is kept (e.g. 300x800 for a 600x400 image gives 150x400). Requested sizes (`width`, `height`, `maxwidth`, `maxheight`) are limited to 4096 pixels per axis (`AppApiFile::MAX_DIMENSION`). Without size parameters the original file is delivered.

Use GET-Param `format=base64` to receive the file in base64 format.

### Caching

| Param | Value            | Description                                                                                                 |
| ----- | ---------------- | ----------------------------------------------------------------------------------------------------------- |
| **v** | non-empty string | Version of the file, e.g. a hash of its modification time and size. Change it whenever the file changes. |

A page counts as public if `$page->isPublic()` is true (for repeater items: the page that owns the repeater). Access modules can make a page non-public, e.g. `PageAccessReleasetime` for pages with a release time.

| Request                           | `v` set                                | no `v`              |
| --------------------------------- | -------------------------------------- | ------------------- |
| Guest, public page                | `public, max-age=31536000, immutable`  | `no-cache`          |
| Logged-in user, public page       | `private, max-age=31536000, immutable` | `private, no-cache` |
| Non-public page (any user)        | `private, no-cache`                    | `private, no-cache` |

The value of `v` does not change the response. Only guests get `public`, so shared caches never store a response that depended on a user's rights. Files of non-public pages are revalidated on every use, so access is checked every time.

A request whose `If-None-Match` header contains the current `ETag` (also as weak `W/` ETag or in a list) is answered with `304 Not Modified` and no body. This does not apply to `format=base64`: those responses are always sent in full and without the caching headers above.

### Access

Files of a page that the current user cannot view (e.g. unpublished pages) are answered with `404 Not Found`, the same as an unknown page.

#### Hook: own access conditions

Every file request asks the hookable method `AppApiFile::isFileAccessible(Page $page, Page $accessPage)` whether the current user may get the file. `$page` is the page that holds the file, `$accessPage` is the page that decides the access (the page itself, or for a repeater item the page that owns the repeater). By default it returns `$accessPage->viewable('', false)`. The hook runs after the authentication, so `$user` is the requesting user. If it returns `false`, the request is answered with `404 Not Found`.

Add conditions with an after-hook, e.g. in `site/ready.php`. Keep a `false` that is already there, so the default visibility check still applies:

```php
$wire->addHookAfter('AppApiFile::isFileAccessible', function (HookEvent $event) {
	if (!$event->return) {
		return;
	}
	$accessPage = $event->arguments(1);
	if ($accessPage->template->name === 'members_only' && !$event->wire('user')->isLoggedin()) {
		$event->return = false;
	}
});
```

**Pro tip**: If you want to include an image from the api using the standard `<img src="">` tag, it can be very difficult to include the api key and a token as headers. However, it is possible to include these values as GET parameters. The GET parameter with the apikey is called `api_key`. A token can be sent as parameter `authorization`.

> Disclaimer: I recommend to use this solution only for this exceptional case. Generally headers are the better and more elegant solution.

<a name="changelog"></a>

## Changelog

### Changes in 2.1.0 (2026-10-10)

#### New

- New hookable method `AppApiFile::isFileAccessible(Page $page, Page $accessPage): bool`. It decides whether the current user may get a file (after authentication); `false` answers `404 Not Found`, the same as for a page the user cannot view. The default is unchanged (`$accessPage->viewable('', false)`), so existing sites behave as before. See "Hook: own access conditions".

### Changes in 2.0.0 (2026-10-02)

#### Behavior changes

Check these before updating:

- Files of pages that the current user cannot view now return `404 Not Found` instead of `403 Forbidden`, the same as an unknown page.
- Images are no longer scaled up beyond their original size (`width`/`height` above the original deliver the original size).
- Requested image sizes are limited to 4096 pixels per axis.
- New caching headers: `Cache-Control: no-cache` (guests, public pages without `v`) or `private, …` (logged-in users and non-public pages, see "Caching") instead of `public, must-revalidate, post-check=0, pre-check=0`. The headers `Expires: -1` and `Pragma: public` are no longer sent.

#### New

- GET-param `v`: versioned URLs of public pages are cached for a year (`public, max-age=31536000, immutable` for guests, `private, max-age=31536000, immutable` for logged-in users)
- `If-None-Match` with the current ETag is answered with `304 Not Modified`

### Changes in 1.0.6 (2022-06-01)

- Bugfix throw 404 status if not found

### Changes in 1.0.5 (2022-05-30)

- Fix Webp support

### Changes in 1.0.4 (2022-04-29)

- Added support for Multi-Language URLS

### Changes in 1.0.3 (2021-10-31)

- Improved access to repeater images

### Changes in 1.0.2 (2021-10-23)

- Updated module infos

### Changes in 1.0.1 (2021-10-23)

- Smaller improvements in README

### Changes in 1.0.0 (2021-10-21)

- Added file endpoint
- implemented different ways to access a page
- implemented image-manipulation parameters

<a name="versioning"></a>

## Versioning

We use [SemVer](http://semver.org/) for versioning. For the versions available, see the [tags on this repository](https://github.com/Sebiworld/AppApiFile/tags).

<a name="license"></a>

## License

This project is licensed under the Mozilla Public License Version 2.0 - see the [LICENSE.md](LICENSE.md) file for details.
