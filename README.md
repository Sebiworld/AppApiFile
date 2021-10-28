AppApiFile adds the /file endpoint to the [AppApi](https://modules.processwire.com/modules/app-api/) routes definition. Makes it possible to query files via the api.

[![Current Version](https://img.shields.io/github/v/tag/Sebiworld/AppApiFile?label=Current%20Version)](https://img.shields.io/github/v/tag/Sebiworld/AppApiFile?label=Current%20Version) [![Current Version](https://img.shields.io/github/issues-closed-raw/Sebiworld/AppApiFile?color=%2356d364)](https://img.shields.io/github/issues-closed-raw/Sebiworld/AppApiFile?color=%2356d364) [![Current Version](https://img.shields.io/github/issues-raw/Sebiworld/AppApiFile)](https://img.shields.io/github/issues-raw/Sebiworld/AppApiFile)

<a href="https://www.buymeacoffee.com/Sebi.dev" target="_blank"><img src="https://cdn.buymeacoffee.com/buttons/default-orange.png" alt="Buy Me A Coffee" height="41" width="174"></a>

| | |
| ------------------: | -------------------------------------------------------------------------- |
| ProcessWire-Module: | [https://modules.processwire.com/modules/app-api-file/](https://modules.processwire.com/modules/app-api-file/)                                                                    |
|      Support-Forum: | [https://processwire.com/talk/topic/26272-appapi-module-appapifile/](https://processwire.com/talk/topic/26272-appapi-module-appapifile/)                                                                      |
|         Repository: | [https://github.com/Sebiworld/AppApiFile](https://github.com/Sebiworld/AppApiFile) |

Relies on AppApi:

| | |
| ------------------: | -------------------------------------------------------------------------- |
| AppApi-Module: | [https://modules.processwire.com/modules/app-api/](https://modules.processwire.com/modules/app-api/)                                                                    |
|      Support-Forum: | [https://processwire.com/talk/topic/24014-new-module-appapi/](https://processwire.com/talk/topic/24014-new-module-appapi/)                                                                      |
|         Repository: | [https://github.com/Sebiworld/AppApi](https://github.com/Sebiworld/AppApi) |
| AppApi Wiki: | [https://github.com/Sebiworld/AppApi/wiki](https://github.com/Sebiworld/AppApi/wiki) |
| | |

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
| **maxWidth**  | int >= 0 | Maximum Width, if the original image's resolution is sufficient                   |
| **maxHeight** | int >= 0 | Maximum Height, if the original image's resolution is sufficient                  |
| **cropX**     | int >= 0 | Start-X-position for cropping (crop enabled, if width, height, cropX & cropY set) |
| **cropY**     | int >= 0 | Start-Y-position for cropping (crop enabled, if width, height, cropX & cropY set) |

Use GET-Param `format=base64` to receive the file in base64 format.

**Pro tip**: If you want to include an image from the api using the standard `<img src="">` tag, it can be very difficult to include the api key and a token as headers. However, it is possible to include these values as GET parameters. The GET parameter with the apikey is called `api_key`. A token can be sent as parameter `authorization`.

> Disclaimer: I recommend to use this solution only for this exceptional case. Generally headers are the better and more elegant solution.

<a name="changelog"></a>

## Changelog

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
