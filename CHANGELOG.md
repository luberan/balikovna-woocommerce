# Changelog

## [1.3.1](https://github.com/luberan/balikovna-woocommerce/compare/v1.3.0...v1.3.1) (2026-05-29)


### 🐛 Opravy chyb

* **ci:** sync readme.txt changelog back to main so PUC sees it ([7007386](https://github.com/luberan/balikovna-woocommerce/commit/70073868a0a041884c6d5b700cbb6fc5525e6f8a))
* trigger release to publish synced readme.txt changelog to main ([44ef828](https://github.com/luberan/balikovna-woocommerce/commit/44ef8287cb3e543bc1e91e67b373dba714649437))

## [1.3.0](https://github.com/luberan/balikovna-woocommerce/compare/v1.2.2...v1.3.0) (2026-05-29)


### 🚀 Nové funkce

* **ci:** include full changelog history in release ZIP readme.txt ([2fa9110](https://github.com/luberan/balikovna-woocommerce/commit/2fa91105871921b47e8d0300ac4e06d8da83324e))

## [1.2.2](https://github.com/luberan/balikovna-woocommerce/compare/v1.2.1...v1.2.2) (2026-05-29)


### 🐛 Opravy chyb

* **ci:** anchor release ZIP excludes so PUC vendor Parsedown survives ([95c4c86](https://github.com/luberan/balikovna-woocommerce/commit/95c4c86a92479b942343c3ca5e8458379cf221b2))
* harden Plugin Update Checker bootstrap against fatals ([ad7a70c](https://github.com/luberan/balikovna-woocommerce/commit/ad7a70c10c01b6c96417927a68d98d2c26b6b923))

## [1.2.1](https://github.com/luberan/balikovna-woocommerce/compare/v1.2.0...v1.2.1) (2026-05-29)


### 🐛 Opravy chyb

* **ci:** build release ZIP in the same workflow as release-please ([a8d9fc7](https://github.com/luberan/balikovna-woocommerce/commit/a8d9fc73da29661f5d302c66c987dc2b2b7d3f3d))

## [1.2.0](https://github.com/luberan/balikovna-woocommerce/compare/v1.1.0...v1.2.0) (2026-05-29)


### 🚀 Nové funkce

* GitHub-driven auto-updates via Plugin Update Checker ([e29f487](https://github.com/luberan/balikovna-woocommerce/commit/e29f487efd4589f1c7d197be79e3411d56af4fa2))

## [1.1.0](https://github.com/luberan/balikovna-woocommerce/compare/v1.0.0...v1.1.0) (2026-05-29)


### 🚀 Nové funkce

* implement official CP picker payload and Podani Online CSV format ([146501a](https://github.com/luberan/balikovna-woocommerce/commit/146501a9f1c85093268c72be1b0157678d0776a6))
* per-method admin setting for CP service codes (CSV column I) ([cdd6771](https://github.com/luberan/balikovna-woocommerce/commit/cdd6771f8805be3c9c2fa3ff17147a23597ec7bf))


### 🐛 Opravy chyb

* declare shipping method instance properties (PHP 8.2+ deprecation) ([524ab83](https://github.com/luberan/balikovna-woocommerce/commit/524ab838d3daa87fab146eb036efc648a1b18d58))
* load textdomain on init hook (WP 6.7+ requirement) ([c1a03dc](https://github.com/luberan/balikovna-woocommerce/commit/c1a03dce6b86e2f56c8bb081a55ad6bcf5e7d148))


### 🔧 Kompatibilita

* bump targets to WP 7.0, PHP 8.5, WC 10.8 ([4b758ff](https://github.com/luberan/balikovna-woocommerce/commit/4b758ff6d189dd50b9a04e21bcc099712f215e22))
* declare realistic PHP minimum 7.4 (tested up to 8.5) ([58367d3](https://github.com/luberan/balikovna-woocommerce/commit/58367d3e336806eef395842731740f4f81a9953e))
* declare realistic WordPress minimum 6.0 (tested up to 7.0) ([f2bdaf6](https://github.com/luberan/balikovna-woocommerce/commit/f2bdaf6a4754fef19492e91d29ca9683513115a1))


### 📚 Dokumentace

* add Author URI to plugin header ([295b9de](https://github.com/luberan/balikovna-woocommerce/commit/295b9de28a8fabce3e4f7a1ecadb5dccfd8acf97))
* align license metadata with GPL-3.0-or-later ([597a953](https://github.com/luberan/balikovna-woocommerce/commit/597a953e231ecf2d58d135c9590043730a96b86c))
* fix plugin and POT repository URL to luberan/balikovna-woocommerce ([8ed2e22](https://github.com/luberan/balikovna-woocommerce/commit/8ed2e22982bb95b53624c58da43ef9c11a81c9b0))
* refresh README with current feature set and CP-confirmed details ([c9064f2](https://github.com/luberan/balikovna-woocommerce/commit/c9064f296762ac8af05614e74b639ccd41b24722))


### 🛠 CI

* add least-privilege permissions to workflow ([ffb6701](https://github.com/luberan/balikovna-woocommerce/commit/ffb6701b1731d72b95c3f1f3cf589495d6500b09))
* add release-please for automated changelog and version bumps ([a5cd194](https://github.com/luberan/balikovna-woocommerce/commit/a5cd194c961e2c81060b9d8fdc966d0c634e12c7))
* allow phpcodesniffer-composer-installer plugin (Composer 2.2+) ([d60fe6b](https://github.com/luberan/balikovna-woocommerce/commit/d60fe6b95a872502f0956dfd3159a928fa50be37))
* build proper plugin ZIP on release publish ([314c053](https://github.com/luberan/balikovna-woocommerce/commit/314c0530a7c0acf100e9ada338f58eef350db028))
* bump actions/checkout from 4 to 6 ([#2](https://github.com/luberan/balikovna-woocommerce/issues/2)) ([aeb12f2](https://github.com/luberan/balikovna-woocommerce/commit/aeb12f206826ed53034f5f9a60759c52fbaa46e8))
