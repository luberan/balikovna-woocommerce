# Changelog

## [1.14.0](https://github.com/luberan/balikovna-woocommerce/compare/v1.13.0...v1.14.0) (2026-05-29)


### 🚀 Nové funkce

* **ci:** include full changelog history in release ZIP readme.txt ([2fa9110](https://github.com/luberan/balikovna-woocommerce/commit/2fa91105871921b47e8d0300ac4e06d8da83324e))
* GitHub-driven auto-updates via Plugin Update Checker ([e29f487](https://github.com/luberan/balikovna-woocommerce/commit/e29f487efd4589f1c7d197be79e3411d56af4fa2))
* implement official CP picker payload and Podani Online CSV format ([146501a](https://github.com/luberan/balikovna-woocommerce/commit/146501a9f1c85093268c72be1b0157678d0776a6))
* per-method admin setting for CP service codes (CSV column I) ([cdd6771](https://github.com/luberan/balikovna-woocommerce/commit/cdd6771f8805be3c9c2fa3ff17147a23597ec7bf))


### 🐛 Opravy chyb

* **ci:** anchor release ZIP excludes so PUC vendor Parsedown survives ([95c4c86](https://github.com/luberan/balikovna-woocommerce/commit/95c4c86a92479b942343c3ca5e8458379cf221b2))
* **ci:** build release ZIP in the same workflow as release-please ([a8d9fc7](https://github.com/luberan/balikovna-woocommerce/commit/a8d9fc73da29661f5d302c66c987dc2b2b7d3f3d))
* **ci:** clone without --depth so PR branch is available for merge ([d8dde35](https://github.com/luberan/balikovna-woocommerce/commit/d8dde35a786c2e9dd834be8a0b54795110035837))
* **ci:** sync readme.txt changelog back to main so PUC sees it ([7007386](https://github.com/luberan/balikovna-woocommerce/commit/70073868a0a041884c6d5b700cbb6fc5525e6f8a))
* **ci:** use 'chore:' prefix in sync commit to avoid release-please bump loop ([d769d86](https://github.com/luberan/balikovna-woocommerce/commit/d769d860f4fc16747692bafb99cb9cd00095328a))
* declare shipping method instance properties (PHP 8.2+ deprecation) ([524ab83](https://github.com/luberan/balikovna-woocommerce/commit/524ab838d3daa87fab146eb036efc648a1b18d58))
* do not break block checkout when picker not yet selected ([0000a78](https://github.com/luberan/balikovna-woocommerce/commit/0000a787b225f4e53109642a4c6382ff6f910c52))
* harden Plugin Update Checker bootstrap against fatals ([ad7a70c](https://github.com/luberan/balikovna-woocommerce/commit/ad7a70c10c01b6c96417927a68d98d2c26b6b923))
* load textdomain on init hook (WP 6.7+ requirement) ([c1a03dc](https://github.com/luberan/balikovna-woocommerce/commit/c1a03dce6b86e2f56c8bb081a55ad6bcf5e7d148))
* override changelog modal with local readme.txt history ([5901ee4](https://github.com/luberan/balikovna-woocommerce/commit/5901ee477117bdcd870fc890aa240c85914fd37c))
* trigger release to publish synced readme.txt changelog to main ([44ef828](https://github.com/luberan/balikovna-woocommerce/commit/44ef8287cb3e543bc1e91e67b373dba714649437))


### 🔧 Kompatibilita

* bump targets to WP 7.0, PHP 8.5, WC 10.8 ([4b758ff](https://github.com/luberan/balikovna-woocommerce/commit/4b758ff6d189dd50b9a04e21bcc099712f215e22))
* declare realistic PHP minimum 7.4 (tested up to 8.5) ([58367d3](https://github.com/luberan/balikovna-woocommerce/commit/58367d3e336806eef395842731740f4f81a9953e))
* declare realistic WordPress minimum 6.0 (tested up to 7.0) ([f2bdaf6](https://github.com/luberan/balikovna-woocommerce/commit/f2bdaf6a4754fef19492e91d29ca9683513115a1))


### 📚 Dokumentace

* add Author URI to plugin header ([295b9de](https://github.com/luberan/balikovna-woocommerce/commit/295b9de28a8fabce3e4f7a1ecadb5dccfd8acf97))
* align license metadata with GPL-3.0-or-later ([597a953](https://github.com/luberan/balikovna-woocommerce/commit/597a953e231ecf2d58d135c9590043730a96b86c))
* fix plugin and POT repository URL to luberan/balikovna-woocommerce ([8ed2e22](https://github.com/luberan/balikovna-woocommerce/commit/8ed2e22982bb95b53624c58da43ef9c11a81c9b0))
* refresh README with current feature set and CP-confirmed details ([c9064f2](https://github.com/luberan/balikovna-woocommerce/commit/c9064f296762ac8af05614e74b639ccd41b24722))
* sync readme.txt changelog with v1.3.1 [skip ci] ([943209c](https://github.com/luberan/balikovna-woocommerce/commit/943209c4b812108dece064a298276751bdd2bf37))
* sync readme.txt changelog with v1.3.2 [skip ci] ([4a6ccf9](https://github.com/luberan/balikovna-woocommerce/commit/4a6ccf9f73d6b486a4a77bd51a80b19755fd80fd))
* sync readme.txt changelog with v1.3.3 [skip ci] ([e04b302](https://github.com/luberan/balikovna-woocommerce/commit/e04b3022ae4fcbfc4120ba83037f2c72b7d1654b))
* sync readme.txt changelog with v1.3.4 [skip ci] ([aa075f1](https://github.com/luberan/balikovna-woocommerce/commit/aa075f1b2c1898d632ed1feaf21bec468427ce64))


### 🛠 CI

* add least-privilege permissions to workflow ([ffb6701](https://github.com/luberan/balikovna-woocommerce/commit/ffb6701b1731d72b95c3f1f3cf589495d6500b09))
* add release-please for automated changelog and version bumps ([a5cd194](https://github.com/luberan/balikovna-woocommerce/commit/a5cd194c961e2c81060b9d8fdc966d0c634e12c7))
* add workflow_dispatch trigger to release-please for manual re-runs ([698f6d9](https://github.com/luberan/balikovna-woocommerce/commit/698f6d954122b766868a2adfee89d392580de744))
* allow phpcodesniffer-composer-installer plugin (Composer 2.2+) ([d60fe6b](https://github.com/luberan/balikovna-woocommerce/commit/d60fe6b95a872502f0956dfd3159a928fa50be37))
* auto-merge release-please PR via PAT for fully unattended releases ([c7f7a9b](https://github.com/luberan/balikovna-woocommerce/commit/c7f7a9bd523efbedd41c9ca65b405bb3785b8ebe))
* build proper plugin ZIP on release publish ([314c053](https://github.com/luberan/balikovna-woocommerce/commit/314c0530a7c0acf100e9ada338f58eef350db028))
* bump actions/checkout from 4 to 6 ([#2](https://github.com/luberan/balikovna-woocommerce/issues/2)) ([aeb12f2](https://github.com/luberan/balikovna-woocommerce/commit/aeb12f206826ed53034f5f9a60759c52fbaa46e8))
* merge release PR via git+PAT instead of gh CLI to bypass anti-loop ([136cb84](https://github.com/luberan/balikovna-woocommerce/commit/136cb844b6f97a606c5d5b4abcce0be4eb140dce))
* use PAT for release-please to break GITHUB_TOKEN anti-loop ([445e8be](https://github.com/luberan/balikovna-woocommerce/commit/445e8bef5399331cd6dd3a55fb8f9eee4f1f3460))

## [1.13.0](https://github.com/luberan/balikovna-woocommerce/compare/v1.12.0...v1.13.0) (2026-05-29)


### 🚀 Nové funkce

* **ci:** include full changelog history in release ZIP readme.txt ([2fa9110](https://github.com/luberan/balikovna-woocommerce/commit/2fa91105871921b47e8d0300ac4e06d8da83324e))
* GitHub-driven auto-updates via Plugin Update Checker ([e29f487](https://github.com/luberan/balikovna-woocommerce/commit/e29f487efd4589f1c7d197be79e3411d56af4fa2))
* implement official CP picker payload and Podani Online CSV format ([146501a](https://github.com/luberan/balikovna-woocommerce/commit/146501a9f1c85093268c72be1b0157678d0776a6))
* per-method admin setting for CP service codes (CSV column I) ([cdd6771](https://github.com/luberan/balikovna-woocommerce/commit/cdd6771f8805be3c9c2fa3ff17147a23597ec7bf))


### 🐛 Opravy chyb

* **ci:** anchor release ZIP excludes so PUC vendor Parsedown survives ([95c4c86](https://github.com/luberan/balikovna-woocommerce/commit/95c4c86a92479b942343c3ca5e8458379cf221b2))
* **ci:** build release ZIP in the same workflow as release-please ([a8d9fc7](https://github.com/luberan/balikovna-woocommerce/commit/a8d9fc73da29661f5d302c66c987dc2b2b7d3f3d))
* **ci:** clone without --depth so PR branch is available for merge ([d8dde35](https://github.com/luberan/balikovna-woocommerce/commit/d8dde35a786c2e9dd834be8a0b54795110035837))
* **ci:** sync readme.txt changelog back to main so PUC sees it ([7007386](https://github.com/luberan/balikovna-woocommerce/commit/70073868a0a041884c6d5b700cbb6fc5525e6f8a))
* **ci:** use 'chore:' prefix in sync commit to avoid release-please bump loop ([d769d86](https://github.com/luberan/balikovna-woocommerce/commit/d769d860f4fc16747692bafb99cb9cd00095328a))
* declare shipping method instance properties (PHP 8.2+ deprecation) ([524ab83](https://github.com/luberan/balikovna-woocommerce/commit/524ab838d3daa87fab146eb036efc648a1b18d58))
* do not break block checkout when picker not yet selected ([0000a78](https://github.com/luberan/balikovna-woocommerce/commit/0000a787b225f4e53109642a4c6382ff6f910c52))
* harden Plugin Update Checker bootstrap against fatals ([ad7a70c](https://github.com/luberan/balikovna-woocommerce/commit/ad7a70c10c01b6c96417927a68d98d2c26b6b923))
* load textdomain on init hook (WP 6.7+ requirement) ([c1a03dc](https://github.com/luberan/balikovna-woocommerce/commit/c1a03dce6b86e2f56c8bb081a55ad6bcf5e7d148))
* override changelog modal with local readme.txt history ([5901ee4](https://github.com/luberan/balikovna-woocommerce/commit/5901ee477117bdcd870fc890aa240c85914fd37c))
* trigger release to publish synced readme.txt changelog to main ([44ef828](https://github.com/luberan/balikovna-woocommerce/commit/44ef8287cb3e543bc1e91e67b373dba714649437))


### 🔧 Kompatibilita

* bump targets to WP 7.0, PHP 8.5, WC 10.8 ([4b758ff](https://github.com/luberan/balikovna-woocommerce/commit/4b758ff6d189dd50b9a04e21bcc099712f215e22))
* declare realistic PHP minimum 7.4 (tested up to 8.5) ([58367d3](https://github.com/luberan/balikovna-woocommerce/commit/58367d3e336806eef395842731740f4f81a9953e))
* declare realistic WordPress minimum 6.0 (tested up to 7.0) ([f2bdaf6](https://github.com/luberan/balikovna-woocommerce/commit/f2bdaf6a4754fef19492e91d29ca9683513115a1))


### 📚 Dokumentace

* add Author URI to plugin header ([295b9de](https://github.com/luberan/balikovna-woocommerce/commit/295b9de28a8fabce3e4f7a1ecadb5dccfd8acf97))
* align license metadata with GPL-3.0-or-later ([597a953](https://github.com/luberan/balikovna-woocommerce/commit/597a953e231ecf2d58d135c9590043730a96b86c))
* fix plugin and POT repository URL to luberan/balikovna-woocommerce ([8ed2e22](https://github.com/luberan/balikovna-woocommerce/commit/8ed2e22982bb95b53624c58da43ef9c11a81c9b0))
* refresh README with current feature set and CP-confirmed details ([c9064f2](https://github.com/luberan/balikovna-woocommerce/commit/c9064f296762ac8af05614e74b639ccd41b24722))
* sync readme.txt changelog with v1.3.1 [skip ci] ([943209c](https://github.com/luberan/balikovna-woocommerce/commit/943209c4b812108dece064a298276751bdd2bf37))
* sync readme.txt changelog with v1.3.2 [skip ci] ([4a6ccf9](https://github.com/luberan/balikovna-woocommerce/commit/4a6ccf9f73d6b486a4a77bd51a80b19755fd80fd))
* sync readme.txt changelog with v1.3.3 [skip ci] ([e04b302](https://github.com/luberan/balikovna-woocommerce/commit/e04b3022ae4fcbfc4120ba83037f2c72b7d1654b))
* sync readme.txt changelog with v1.3.4 [skip ci] ([aa075f1](https://github.com/luberan/balikovna-woocommerce/commit/aa075f1b2c1898d632ed1feaf21bec468427ce64))


### 🛠 CI

* add least-privilege permissions to workflow ([ffb6701](https://github.com/luberan/balikovna-woocommerce/commit/ffb6701b1731d72b95c3f1f3cf589495d6500b09))
* add release-please for automated changelog and version bumps ([a5cd194](https://github.com/luberan/balikovna-woocommerce/commit/a5cd194c961e2c81060b9d8fdc966d0c634e12c7))
* add workflow_dispatch trigger to release-please for manual re-runs ([698f6d9](https://github.com/luberan/balikovna-woocommerce/commit/698f6d954122b766868a2adfee89d392580de744))
* allow phpcodesniffer-composer-installer plugin (Composer 2.2+) ([d60fe6b](https://github.com/luberan/balikovna-woocommerce/commit/d60fe6b95a872502f0956dfd3159a928fa50be37))
* auto-merge release-please PR via PAT for fully unattended releases ([c7f7a9b](https://github.com/luberan/balikovna-woocommerce/commit/c7f7a9bd523efbedd41c9ca65b405bb3785b8ebe))
* build proper plugin ZIP on release publish ([314c053](https://github.com/luberan/balikovna-woocommerce/commit/314c0530a7c0acf100e9ada338f58eef350db028))
* bump actions/checkout from 4 to 6 ([#2](https://github.com/luberan/balikovna-woocommerce/issues/2)) ([aeb12f2](https://github.com/luberan/balikovna-woocommerce/commit/aeb12f206826ed53034f5f9a60759c52fbaa46e8))
* merge release PR via git+PAT instead of gh CLI to bypass anti-loop ([136cb84](https://github.com/luberan/balikovna-woocommerce/commit/136cb844b6f97a606c5d5b4abcce0be4eb140dce))
* use PAT for release-please to break GITHUB_TOKEN anti-loop ([445e8be](https://github.com/luberan/balikovna-woocommerce/commit/445e8bef5399331cd6dd3a55fb8f9eee4f1f3460))

## [1.12.0](https://github.com/luberan/balikovna-woocommerce/compare/v1.11.0...v1.12.0) (2026-05-29)


### 🚀 Nové funkce

* **ci:** include full changelog history in release ZIP readme.txt ([2fa9110](https://github.com/luberan/balikovna-woocommerce/commit/2fa91105871921b47e8d0300ac4e06d8da83324e))
* GitHub-driven auto-updates via Plugin Update Checker ([e29f487](https://github.com/luberan/balikovna-woocommerce/commit/e29f487efd4589f1c7d197be79e3411d56af4fa2))
* implement official CP picker payload and Podani Online CSV format ([146501a](https://github.com/luberan/balikovna-woocommerce/commit/146501a9f1c85093268c72be1b0157678d0776a6))
* per-method admin setting for CP service codes (CSV column I) ([cdd6771](https://github.com/luberan/balikovna-woocommerce/commit/cdd6771f8805be3c9c2fa3ff17147a23597ec7bf))


### 🐛 Opravy chyb

* **ci:** anchor release ZIP excludes so PUC vendor Parsedown survives ([95c4c86](https://github.com/luberan/balikovna-woocommerce/commit/95c4c86a92479b942343c3ca5e8458379cf221b2))
* **ci:** build release ZIP in the same workflow as release-please ([a8d9fc7](https://github.com/luberan/balikovna-woocommerce/commit/a8d9fc73da29661f5d302c66c987dc2b2b7d3f3d))
* **ci:** clone without --depth so PR branch is available for merge ([d8dde35](https://github.com/luberan/balikovna-woocommerce/commit/d8dde35a786c2e9dd834be8a0b54795110035837))
* **ci:** sync readme.txt changelog back to main so PUC sees it ([7007386](https://github.com/luberan/balikovna-woocommerce/commit/70073868a0a041884c6d5b700cbb6fc5525e6f8a))
* **ci:** use 'chore:' prefix in sync commit to avoid release-please bump loop ([d769d86](https://github.com/luberan/balikovna-woocommerce/commit/d769d860f4fc16747692bafb99cb9cd00095328a))
* declare shipping method instance properties (PHP 8.2+ deprecation) ([524ab83](https://github.com/luberan/balikovna-woocommerce/commit/524ab838d3daa87fab146eb036efc648a1b18d58))
* do not break block checkout when picker not yet selected ([0000a78](https://github.com/luberan/balikovna-woocommerce/commit/0000a787b225f4e53109642a4c6382ff6f910c52))
* harden Plugin Update Checker bootstrap against fatals ([ad7a70c](https://github.com/luberan/balikovna-woocommerce/commit/ad7a70c10c01b6c96417927a68d98d2c26b6b923))
* load textdomain on init hook (WP 6.7+ requirement) ([c1a03dc](https://github.com/luberan/balikovna-woocommerce/commit/c1a03dce6b86e2f56c8bb081a55ad6bcf5e7d148))
* override changelog modal with local readme.txt history ([5901ee4](https://github.com/luberan/balikovna-woocommerce/commit/5901ee477117bdcd870fc890aa240c85914fd37c))
* trigger release to publish synced readme.txt changelog to main ([44ef828](https://github.com/luberan/balikovna-woocommerce/commit/44ef8287cb3e543bc1e91e67b373dba714649437))


### 🔧 Kompatibilita

* bump targets to WP 7.0, PHP 8.5, WC 10.8 ([4b758ff](https://github.com/luberan/balikovna-woocommerce/commit/4b758ff6d189dd50b9a04e21bcc099712f215e22))
* declare realistic PHP minimum 7.4 (tested up to 8.5) ([58367d3](https://github.com/luberan/balikovna-woocommerce/commit/58367d3e336806eef395842731740f4f81a9953e))
* declare realistic WordPress minimum 6.0 (tested up to 7.0) ([f2bdaf6](https://github.com/luberan/balikovna-woocommerce/commit/f2bdaf6a4754fef19492e91d29ca9683513115a1))


### 📚 Dokumentace

* add Author URI to plugin header ([295b9de](https://github.com/luberan/balikovna-woocommerce/commit/295b9de28a8fabce3e4f7a1ecadb5dccfd8acf97))
* align license metadata with GPL-3.0-or-later ([597a953](https://github.com/luberan/balikovna-woocommerce/commit/597a953e231ecf2d58d135c9590043730a96b86c))
* fix plugin and POT repository URL to luberan/balikovna-woocommerce ([8ed2e22](https://github.com/luberan/balikovna-woocommerce/commit/8ed2e22982bb95b53624c58da43ef9c11a81c9b0))
* refresh README with current feature set and CP-confirmed details ([c9064f2](https://github.com/luberan/balikovna-woocommerce/commit/c9064f296762ac8af05614e74b639ccd41b24722))
* sync readme.txt changelog with v1.3.1 [skip ci] ([943209c](https://github.com/luberan/balikovna-woocommerce/commit/943209c4b812108dece064a298276751bdd2bf37))
* sync readme.txt changelog with v1.3.2 [skip ci] ([4a6ccf9](https://github.com/luberan/balikovna-woocommerce/commit/4a6ccf9f73d6b486a4a77bd51a80b19755fd80fd))
* sync readme.txt changelog with v1.3.3 [skip ci] ([e04b302](https://github.com/luberan/balikovna-woocommerce/commit/e04b3022ae4fcbfc4120ba83037f2c72b7d1654b))
* sync readme.txt changelog with v1.3.4 [skip ci] ([aa075f1](https://github.com/luberan/balikovna-woocommerce/commit/aa075f1b2c1898d632ed1feaf21bec468427ce64))


### 🛠 CI

* add least-privilege permissions to workflow ([ffb6701](https://github.com/luberan/balikovna-woocommerce/commit/ffb6701b1731d72b95c3f1f3cf589495d6500b09))
* add release-please for automated changelog and version bumps ([a5cd194](https://github.com/luberan/balikovna-woocommerce/commit/a5cd194c961e2c81060b9d8fdc966d0c634e12c7))
* add workflow_dispatch trigger to release-please for manual re-runs ([698f6d9](https://github.com/luberan/balikovna-woocommerce/commit/698f6d954122b766868a2adfee89d392580de744))
* allow phpcodesniffer-composer-installer plugin (Composer 2.2+) ([d60fe6b](https://github.com/luberan/balikovna-woocommerce/commit/d60fe6b95a872502f0956dfd3159a928fa50be37))
* auto-merge release-please PR via PAT for fully unattended releases ([c7f7a9b](https://github.com/luberan/balikovna-woocommerce/commit/c7f7a9bd523efbedd41c9ca65b405bb3785b8ebe))
* build proper plugin ZIP on release publish ([314c053](https://github.com/luberan/balikovna-woocommerce/commit/314c0530a7c0acf100e9ada338f58eef350db028))
* bump actions/checkout from 4 to 6 ([#2](https://github.com/luberan/balikovna-woocommerce/issues/2)) ([aeb12f2](https://github.com/luberan/balikovna-woocommerce/commit/aeb12f206826ed53034f5f9a60759c52fbaa46e8))
* merge release PR via git+PAT instead of gh CLI to bypass anti-loop ([136cb84](https://github.com/luberan/balikovna-woocommerce/commit/136cb844b6f97a606c5d5b4abcce0be4eb140dce))
* use PAT for release-please to break GITHUB_TOKEN anti-loop ([445e8be](https://github.com/luberan/balikovna-woocommerce/commit/445e8bef5399331cd6dd3a55fb8f9eee4f1f3460))

## [1.11.0](https://github.com/luberan/balikovna-woocommerce/compare/v1.10.0...v1.11.0) (2026-05-29)


### 🚀 Nové funkce

* **ci:** include full changelog history in release ZIP readme.txt ([2fa9110](https://github.com/luberan/balikovna-woocommerce/commit/2fa91105871921b47e8d0300ac4e06d8da83324e))
* GitHub-driven auto-updates via Plugin Update Checker ([e29f487](https://github.com/luberan/balikovna-woocommerce/commit/e29f487efd4589f1c7d197be79e3411d56af4fa2))
* implement official CP picker payload and Podani Online CSV format ([146501a](https://github.com/luberan/balikovna-woocommerce/commit/146501a9f1c85093268c72be1b0157678d0776a6))
* per-method admin setting for CP service codes (CSV column I) ([cdd6771](https://github.com/luberan/balikovna-woocommerce/commit/cdd6771f8805be3c9c2fa3ff17147a23597ec7bf))


### 🐛 Opravy chyb

* **ci:** anchor release ZIP excludes so PUC vendor Parsedown survives ([95c4c86](https://github.com/luberan/balikovna-woocommerce/commit/95c4c86a92479b942343c3ca5e8458379cf221b2))
* **ci:** build release ZIP in the same workflow as release-please ([a8d9fc7](https://github.com/luberan/balikovna-woocommerce/commit/a8d9fc73da29661f5d302c66c987dc2b2b7d3f3d))
* **ci:** clone without --depth so PR branch is available for merge ([d8dde35](https://github.com/luberan/balikovna-woocommerce/commit/d8dde35a786c2e9dd834be8a0b54795110035837))
* **ci:** sync readme.txt changelog back to main so PUC sees it ([7007386](https://github.com/luberan/balikovna-woocommerce/commit/70073868a0a041884c6d5b700cbb6fc5525e6f8a))
* **ci:** use 'chore:' prefix in sync commit to avoid release-please bump loop ([d769d86](https://github.com/luberan/balikovna-woocommerce/commit/d769d860f4fc16747692bafb99cb9cd00095328a))
* declare shipping method instance properties (PHP 8.2+ deprecation) ([524ab83](https://github.com/luberan/balikovna-woocommerce/commit/524ab838d3daa87fab146eb036efc648a1b18d58))
* do not break block checkout when picker not yet selected ([0000a78](https://github.com/luberan/balikovna-woocommerce/commit/0000a787b225f4e53109642a4c6382ff6f910c52))
* harden Plugin Update Checker bootstrap against fatals ([ad7a70c](https://github.com/luberan/balikovna-woocommerce/commit/ad7a70c10c01b6c96417927a68d98d2c26b6b923))
* load textdomain on init hook (WP 6.7+ requirement) ([c1a03dc](https://github.com/luberan/balikovna-woocommerce/commit/c1a03dce6b86e2f56c8bb081a55ad6bcf5e7d148))
* override changelog modal with local readme.txt history ([5901ee4](https://github.com/luberan/balikovna-woocommerce/commit/5901ee477117bdcd870fc890aa240c85914fd37c))
* trigger release to publish synced readme.txt changelog to main ([44ef828](https://github.com/luberan/balikovna-woocommerce/commit/44ef8287cb3e543bc1e91e67b373dba714649437))


### 🔧 Kompatibilita

* bump targets to WP 7.0, PHP 8.5, WC 10.8 ([4b758ff](https://github.com/luberan/balikovna-woocommerce/commit/4b758ff6d189dd50b9a04e21bcc099712f215e22))
* declare realistic PHP minimum 7.4 (tested up to 8.5) ([58367d3](https://github.com/luberan/balikovna-woocommerce/commit/58367d3e336806eef395842731740f4f81a9953e))
* declare realistic WordPress minimum 6.0 (tested up to 7.0) ([f2bdaf6](https://github.com/luberan/balikovna-woocommerce/commit/f2bdaf6a4754fef19492e91d29ca9683513115a1))


### 📚 Dokumentace

* add Author URI to plugin header ([295b9de](https://github.com/luberan/balikovna-woocommerce/commit/295b9de28a8fabce3e4f7a1ecadb5dccfd8acf97))
* align license metadata with GPL-3.0-or-later ([597a953](https://github.com/luberan/balikovna-woocommerce/commit/597a953e231ecf2d58d135c9590043730a96b86c))
* fix plugin and POT repository URL to luberan/balikovna-woocommerce ([8ed2e22](https://github.com/luberan/balikovna-woocommerce/commit/8ed2e22982bb95b53624c58da43ef9c11a81c9b0))
* refresh README with current feature set and CP-confirmed details ([c9064f2](https://github.com/luberan/balikovna-woocommerce/commit/c9064f296762ac8af05614e74b639ccd41b24722))
* sync readme.txt changelog with v1.3.1 [skip ci] ([943209c](https://github.com/luberan/balikovna-woocommerce/commit/943209c4b812108dece064a298276751bdd2bf37))
* sync readme.txt changelog with v1.3.2 [skip ci] ([4a6ccf9](https://github.com/luberan/balikovna-woocommerce/commit/4a6ccf9f73d6b486a4a77bd51a80b19755fd80fd))
* sync readme.txt changelog with v1.3.3 [skip ci] ([e04b302](https://github.com/luberan/balikovna-woocommerce/commit/e04b3022ae4fcbfc4120ba83037f2c72b7d1654b))
* sync readme.txt changelog with v1.3.4 [skip ci] ([aa075f1](https://github.com/luberan/balikovna-woocommerce/commit/aa075f1b2c1898d632ed1feaf21bec468427ce64))


### 🛠 CI

* add least-privilege permissions to workflow ([ffb6701](https://github.com/luberan/balikovna-woocommerce/commit/ffb6701b1731d72b95c3f1f3cf589495d6500b09))
* add release-please for automated changelog and version bumps ([a5cd194](https://github.com/luberan/balikovna-woocommerce/commit/a5cd194c961e2c81060b9d8fdc966d0c634e12c7))
* add workflow_dispatch trigger to release-please for manual re-runs ([698f6d9](https://github.com/luberan/balikovna-woocommerce/commit/698f6d954122b766868a2adfee89d392580de744))
* allow phpcodesniffer-composer-installer plugin (Composer 2.2+) ([d60fe6b](https://github.com/luberan/balikovna-woocommerce/commit/d60fe6b95a872502f0956dfd3159a928fa50be37))
* auto-merge release-please PR via PAT for fully unattended releases ([c7f7a9b](https://github.com/luberan/balikovna-woocommerce/commit/c7f7a9bd523efbedd41c9ca65b405bb3785b8ebe))
* build proper plugin ZIP on release publish ([314c053](https://github.com/luberan/balikovna-woocommerce/commit/314c0530a7c0acf100e9ada338f58eef350db028))
* bump actions/checkout from 4 to 6 ([#2](https://github.com/luberan/balikovna-woocommerce/issues/2)) ([aeb12f2](https://github.com/luberan/balikovna-woocommerce/commit/aeb12f206826ed53034f5f9a60759c52fbaa46e8))
* merge release PR via git+PAT instead of gh CLI to bypass anti-loop ([136cb84](https://github.com/luberan/balikovna-woocommerce/commit/136cb844b6f97a606c5d5b4abcce0be4eb140dce))
* use PAT for release-please to break GITHUB_TOKEN anti-loop ([445e8be](https://github.com/luberan/balikovna-woocommerce/commit/445e8bef5399331cd6dd3a55fb8f9eee4f1f3460))

## [1.10.0](https://github.com/luberan/balikovna-woocommerce/compare/v1.9.0...v1.10.0) (2026-05-29)


### 🚀 Nové funkce

* **ci:** include full changelog history in release ZIP readme.txt ([2fa9110](https://github.com/luberan/balikovna-woocommerce/commit/2fa91105871921b47e8d0300ac4e06d8da83324e))
* GitHub-driven auto-updates via Plugin Update Checker ([e29f487](https://github.com/luberan/balikovna-woocommerce/commit/e29f487efd4589f1c7d197be79e3411d56af4fa2))
* implement official CP picker payload and Podani Online CSV format ([146501a](https://github.com/luberan/balikovna-woocommerce/commit/146501a9f1c85093268c72be1b0157678d0776a6))
* per-method admin setting for CP service codes (CSV column I) ([cdd6771](https://github.com/luberan/balikovna-woocommerce/commit/cdd6771f8805be3c9c2fa3ff17147a23597ec7bf))


### 🐛 Opravy chyb

* **ci:** anchor release ZIP excludes so PUC vendor Parsedown survives ([95c4c86](https://github.com/luberan/balikovna-woocommerce/commit/95c4c86a92479b942343c3ca5e8458379cf221b2))
* **ci:** build release ZIP in the same workflow as release-please ([a8d9fc7](https://github.com/luberan/balikovna-woocommerce/commit/a8d9fc73da29661f5d302c66c987dc2b2b7d3f3d))
* **ci:** clone without --depth so PR branch is available for merge ([d8dde35](https://github.com/luberan/balikovna-woocommerce/commit/d8dde35a786c2e9dd834be8a0b54795110035837))
* **ci:** sync readme.txt changelog back to main so PUC sees it ([7007386](https://github.com/luberan/balikovna-woocommerce/commit/70073868a0a041884c6d5b700cbb6fc5525e6f8a))
* **ci:** use 'chore:' prefix in sync commit to avoid release-please bump loop ([d769d86](https://github.com/luberan/balikovna-woocommerce/commit/d769d860f4fc16747692bafb99cb9cd00095328a))
* declare shipping method instance properties (PHP 8.2+ deprecation) ([524ab83](https://github.com/luberan/balikovna-woocommerce/commit/524ab838d3daa87fab146eb036efc648a1b18d58))
* do not break block checkout when picker not yet selected ([0000a78](https://github.com/luberan/balikovna-woocommerce/commit/0000a787b225f4e53109642a4c6382ff6f910c52))
* harden Plugin Update Checker bootstrap against fatals ([ad7a70c](https://github.com/luberan/balikovna-woocommerce/commit/ad7a70c10c01b6c96417927a68d98d2c26b6b923))
* load textdomain on init hook (WP 6.7+ requirement) ([c1a03dc](https://github.com/luberan/balikovna-woocommerce/commit/c1a03dce6b86e2f56c8bb081a55ad6bcf5e7d148))
* override changelog modal with local readme.txt history ([5901ee4](https://github.com/luberan/balikovna-woocommerce/commit/5901ee477117bdcd870fc890aa240c85914fd37c))
* trigger release to publish synced readme.txt changelog to main ([44ef828](https://github.com/luberan/balikovna-woocommerce/commit/44ef8287cb3e543bc1e91e67b373dba714649437))


### 🔧 Kompatibilita

* bump targets to WP 7.0, PHP 8.5, WC 10.8 ([4b758ff](https://github.com/luberan/balikovna-woocommerce/commit/4b758ff6d189dd50b9a04e21bcc099712f215e22))
* declare realistic PHP minimum 7.4 (tested up to 8.5) ([58367d3](https://github.com/luberan/balikovna-woocommerce/commit/58367d3e336806eef395842731740f4f81a9953e))
* declare realistic WordPress minimum 6.0 (tested up to 7.0) ([f2bdaf6](https://github.com/luberan/balikovna-woocommerce/commit/f2bdaf6a4754fef19492e91d29ca9683513115a1))


### 📚 Dokumentace

* add Author URI to plugin header ([295b9de](https://github.com/luberan/balikovna-woocommerce/commit/295b9de28a8fabce3e4f7a1ecadb5dccfd8acf97))
* align license metadata with GPL-3.0-or-later ([597a953](https://github.com/luberan/balikovna-woocommerce/commit/597a953e231ecf2d58d135c9590043730a96b86c))
* fix plugin and POT repository URL to luberan/balikovna-woocommerce ([8ed2e22](https://github.com/luberan/balikovna-woocommerce/commit/8ed2e22982bb95b53624c58da43ef9c11a81c9b0))
* refresh README with current feature set and CP-confirmed details ([c9064f2](https://github.com/luberan/balikovna-woocommerce/commit/c9064f296762ac8af05614e74b639ccd41b24722))
* sync readme.txt changelog with v1.3.1 [skip ci] ([943209c](https://github.com/luberan/balikovna-woocommerce/commit/943209c4b812108dece064a298276751bdd2bf37))
* sync readme.txt changelog with v1.3.2 [skip ci] ([4a6ccf9](https://github.com/luberan/balikovna-woocommerce/commit/4a6ccf9f73d6b486a4a77bd51a80b19755fd80fd))
* sync readme.txt changelog with v1.3.3 [skip ci] ([e04b302](https://github.com/luberan/balikovna-woocommerce/commit/e04b3022ae4fcbfc4120ba83037f2c72b7d1654b))
* sync readme.txt changelog with v1.3.4 [skip ci] ([aa075f1](https://github.com/luberan/balikovna-woocommerce/commit/aa075f1b2c1898d632ed1feaf21bec468427ce64))


### 🛠 CI

* add least-privilege permissions to workflow ([ffb6701](https://github.com/luberan/balikovna-woocommerce/commit/ffb6701b1731d72b95c3f1f3cf589495d6500b09))
* add release-please for automated changelog and version bumps ([a5cd194](https://github.com/luberan/balikovna-woocommerce/commit/a5cd194c961e2c81060b9d8fdc966d0c634e12c7))
* add workflow_dispatch trigger to release-please for manual re-runs ([698f6d9](https://github.com/luberan/balikovna-woocommerce/commit/698f6d954122b766868a2adfee89d392580de744))
* allow phpcodesniffer-composer-installer plugin (Composer 2.2+) ([d60fe6b](https://github.com/luberan/balikovna-woocommerce/commit/d60fe6b95a872502f0956dfd3159a928fa50be37))
* auto-merge release-please PR via PAT for fully unattended releases ([c7f7a9b](https://github.com/luberan/balikovna-woocommerce/commit/c7f7a9bd523efbedd41c9ca65b405bb3785b8ebe))
* build proper plugin ZIP on release publish ([314c053](https://github.com/luberan/balikovna-woocommerce/commit/314c0530a7c0acf100e9ada338f58eef350db028))
* bump actions/checkout from 4 to 6 ([#2](https://github.com/luberan/balikovna-woocommerce/issues/2)) ([aeb12f2](https://github.com/luberan/balikovna-woocommerce/commit/aeb12f206826ed53034f5f9a60759c52fbaa46e8))
* merge release PR via git+PAT instead of gh CLI to bypass anti-loop ([136cb84](https://github.com/luberan/balikovna-woocommerce/commit/136cb844b6f97a606c5d5b4abcce0be4eb140dce))
* use PAT for release-please to break GITHUB_TOKEN anti-loop ([445e8be](https://github.com/luberan/balikovna-woocommerce/commit/445e8bef5399331cd6dd3a55fb8f9eee4f1f3460))

## [1.9.0](https://github.com/luberan/balikovna-woocommerce/compare/v1.8.0...v1.9.0) (2026-05-29)


### 🚀 Nové funkce

* **ci:** include full changelog history in release ZIP readme.txt ([2fa9110](https://github.com/luberan/balikovna-woocommerce/commit/2fa91105871921b47e8d0300ac4e06d8da83324e))
* GitHub-driven auto-updates via Plugin Update Checker ([e29f487](https://github.com/luberan/balikovna-woocommerce/commit/e29f487efd4589f1c7d197be79e3411d56af4fa2))
* implement official CP picker payload and Podani Online CSV format ([146501a](https://github.com/luberan/balikovna-woocommerce/commit/146501a9f1c85093268c72be1b0157678d0776a6))
* per-method admin setting for CP service codes (CSV column I) ([cdd6771](https://github.com/luberan/balikovna-woocommerce/commit/cdd6771f8805be3c9c2fa3ff17147a23597ec7bf))


### 🐛 Opravy chyb

* **ci:** anchor release ZIP excludes so PUC vendor Parsedown survives ([95c4c86](https://github.com/luberan/balikovna-woocommerce/commit/95c4c86a92479b942343c3ca5e8458379cf221b2))
* **ci:** build release ZIP in the same workflow as release-please ([a8d9fc7](https://github.com/luberan/balikovna-woocommerce/commit/a8d9fc73da29661f5d302c66c987dc2b2b7d3f3d))
* **ci:** clone without --depth so PR branch is available for merge ([d8dde35](https://github.com/luberan/balikovna-woocommerce/commit/d8dde35a786c2e9dd834be8a0b54795110035837))
* **ci:** sync readme.txt changelog back to main so PUC sees it ([7007386](https://github.com/luberan/balikovna-woocommerce/commit/70073868a0a041884c6d5b700cbb6fc5525e6f8a))
* declare shipping method instance properties (PHP 8.2+ deprecation) ([524ab83](https://github.com/luberan/balikovna-woocommerce/commit/524ab838d3daa87fab146eb036efc648a1b18d58))
* do not break block checkout when picker not yet selected ([0000a78](https://github.com/luberan/balikovna-woocommerce/commit/0000a787b225f4e53109642a4c6382ff6f910c52))
* harden Plugin Update Checker bootstrap against fatals ([ad7a70c](https://github.com/luberan/balikovna-woocommerce/commit/ad7a70c10c01b6c96417927a68d98d2c26b6b923))
* load textdomain on init hook (WP 6.7+ requirement) ([c1a03dc](https://github.com/luberan/balikovna-woocommerce/commit/c1a03dce6b86e2f56c8bb081a55ad6bcf5e7d148))
* override changelog modal with local readme.txt history ([5901ee4](https://github.com/luberan/balikovna-woocommerce/commit/5901ee477117bdcd870fc890aa240c85914fd37c))
* trigger release to publish synced readme.txt changelog to main ([44ef828](https://github.com/luberan/balikovna-woocommerce/commit/44ef8287cb3e543bc1e91e67b373dba714649437))


### 🔧 Kompatibilita

* bump targets to WP 7.0, PHP 8.5, WC 10.8 ([4b758ff](https://github.com/luberan/balikovna-woocommerce/commit/4b758ff6d189dd50b9a04e21bcc099712f215e22))
* declare realistic PHP minimum 7.4 (tested up to 8.5) ([58367d3](https://github.com/luberan/balikovna-woocommerce/commit/58367d3e336806eef395842731740f4f81a9953e))
* declare realistic WordPress minimum 6.0 (tested up to 7.0) ([f2bdaf6](https://github.com/luberan/balikovna-woocommerce/commit/f2bdaf6a4754fef19492e91d29ca9683513115a1))


### 📚 Dokumentace

* add Author URI to plugin header ([295b9de](https://github.com/luberan/balikovna-woocommerce/commit/295b9de28a8fabce3e4f7a1ecadb5dccfd8acf97))
* align license metadata with GPL-3.0-or-later ([597a953](https://github.com/luberan/balikovna-woocommerce/commit/597a953e231ecf2d58d135c9590043730a96b86c))
* fix plugin and POT repository URL to luberan/balikovna-woocommerce ([8ed2e22](https://github.com/luberan/balikovna-woocommerce/commit/8ed2e22982bb95b53624c58da43ef9c11a81c9b0))
* refresh README with current feature set and CP-confirmed details ([c9064f2](https://github.com/luberan/balikovna-woocommerce/commit/c9064f296762ac8af05614e74b639ccd41b24722))
* sync readme.txt changelog with v1.3.1 [skip ci] ([943209c](https://github.com/luberan/balikovna-woocommerce/commit/943209c4b812108dece064a298276751bdd2bf37))
* sync readme.txt changelog with v1.3.2 [skip ci] ([4a6ccf9](https://github.com/luberan/balikovna-woocommerce/commit/4a6ccf9f73d6b486a4a77bd51a80b19755fd80fd))
* sync readme.txt changelog with v1.3.3 [skip ci] ([e04b302](https://github.com/luberan/balikovna-woocommerce/commit/e04b3022ae4fcbfc4120ba83037f2c72b7d1654b))
* sync readme.txt changelog with v1.3.4 [skip ci] ([aa075f1](https://github.com/luberan/balikovna-woocommerce/commit/aa075f1b2c1898d632ed1feaf21bec468427ce64))


### 🛠 CI

* add least-privilege permissions to workflow ([ffb6701](https://github.com/luberan/balikovna-woocommerce/commit/ffb6701b1731d72b95c3f1f3cf589495d6500b09))
* add release-please for automated changelog and version bumps ([a5cd194](https://github.com/luberan/balikovna-woocommerce/commit/a5cd194c961e2c81060b9d8fdc966d0c634e12c7))
* add workflow_dispatch trigger to release-please for manual re-runs ([698f6d9](https://github.com/luberan/balikovna-woocommerce/commit/698f6d954122b766868a2adfee89d392580de744))
* allow phpcodesniffer-composer-installer plugin (Composer 2.2+) ([d60fe6b](https://github.com/luberan/balikovna-woocommerce/commit/d60fe6b95a872502f0956dfd3159a928fa50be37))
* auto-merge release-please PR via PAT for fully unattended releases ([c7f7a9b](https://github.com/luberan/balikovna-woocommerce/commit/c7f7a9bd523efbedd41c9ca65b405bb3785b8ebe))
* build proper plugin ZIP on release publish ([314c053](https://github.com/luberan/balikovna-woocommerce/commit/314c0530a7c0acf100e9ada338f58eef350db028))
* bump actions/checkout from 4 to 6 ([#2](https://github.com/luberan/balikovna-woocommerce/issues/2)) ([aeb12f2](https://github.com/luberan/balikovna-woocommerce/commit/aeb12f206826ed53034f5f9a60759c52fbaa46e8))
* merge release PR via git+PAT instead of gh CLI to bypass anti-loop ([136cb84](https://github.com/luberan/balikovna-woocommerce/commit/136cb844b6f97a606c5d5b4abcce0be4eb140dce))
* use PAT for release-please to break GITHUB_TOKEN anti-loop ([445e8be](https://github.com/luberan/balikovna-woocommerce/commit/445e8bef5399331cd6dd3a55fb8f9eee4f1f3460))

## [1.8.0](https://github.com/luberan/balikovna-woocommerce/compare/v1.7.0...v1.8.0) (2026-05-29)


### 🚀 Nové funkce

* **ci:** include full changelog history in release ZIP readme.txt ([2fa9110](https://github.com/luberan/balikovna-woocommerce/commit/2fa91105871921b47e8d0300ac4e06d8da83324e))
* GitHub-driven auto-updates via Plugin Update Checker ([e29f487](https://github.com/luberan/balikovna-woocommerce/commit/e29f487efd4589f1c7d197be79e3411d56af4fa2))
* implement official CP picker payload and Podani Online CSV format ([146501a](https://github.com/luberan/balikovna-woocommerce/commit/146501a9f1c85093268c72be1b0157678d0776a6))
* per-method admin setting for CP service codes (CSV column I) ([cdd6771](https://github.com/luberan/balikovna-woocommerce/commit/cdd6771f8805be3c9c2fa3ff17147a23597ec7bf))


### 🐛 Opravy chyb

* **ci:** anchor release ZIP excludes so PUC vendor Parsedown survives ([95c4c86](https://github.com/luberan/balikovna-woocommerce/commit/95c4c86a92479b942343c3ca5e8458379cf221b2))
* **ci:** build release ZIP in the same workflow as release-please ([a8d9fc7](https://github.com/luberan/balikovna-woocommerce/commit/a8d9fc73da29661f5d302c66c987dc2b2b7d3f3d))
* **ci:** clone without --depth so PR branch is available for merge ([d8dde35](https://github.com/luberan/balikovna-woocommerce/commit/d8dde35a786c2e9dd834be8a0b54795110035837))
* **ci:** sync readme.txt changelog back to main so PUC sees it ([7007386](https://github.com/luberan/balikovna-woocommerce/commit/70073868a0a041884c6d5b700cbb6fc5525e6f8a))
* declare shipping method instance properties (PHP 8.2+ deprecation) ([524ab83](https://github.com/luberan/balikovna-woocommerce/commit/524ab838d3daa87fab146eb036efc648a1b18d58))
* do not break block checkout when picker not yet selected ([0000a78](https://github.com/luberan/balikovna-woocommerce/commit/0000a787b225f4e53109642a4c6382ff6f910c52))
* harden Plugin Update Checker bootstrap against fatals ([ad7a70c](https://github.com/luberan/balikovna-woocommerce/commit/ad7a70c10c01b6c96417927a68d98d2c26b6b923))
* load textdomain on init hook (WP 6.7+ requirement) ([c1a03dc](https://github.com/luberan/balikovna-woocommerce/commit/c1a03dce6b86e2f56c8bb081a55ad6bcf5e7d148))
* override changelog modal with local readme.txt history ([5901ee4](https://github.com/luberan/balikovna-woocommerce/commit/5901ee477117bdcd870fc890aa240c85914fd37c))
* trigger release to publish synced readme.txt changelog to main ([44ef828](https://github.com/luberan/balikovna-woocommerce/commit/44ef8287cb3e543bc1e91e67b373dba714649437))


### 🔧 Kompatibilita

* bump targets to WP 7.0, PHP 8.5, WC 10.8 ([4b758ff](https://github.com/luberan/balikovna-woocommerce/commit/4b758ff6d189dd50b9a04e21bcc099712f215e22))
* declare realistic PHP minimum 7.4 (tested up to 8.5) ([58367d3](https://github.com/luberan/balikovna-woocommerce/commit/58367d3e336806eef395842731740f4f81a9953e))
* declare realistic WordPress minimum 6.0 (tested up to 7.0) ([f2bdaf6](https://github.com/luberan/balikovna-woocommerce/commit/f2bdaf6a4754fef19492e91d29ca9683513115a1))


### 📚 Dokumentace

* add Author URI to plugin header ([295b9de](https://github.com/luberan/balikovna-woocommerce/commit/295b9de28a8fabce3e4f7a1ecadb5dccfd8acf97))
* align license metadata with GPL-3.0-or-later ([597a953](https://github.com/luberan/balikovna-woocommerce/commit/597a953e231ecf2d58d135c9590043730a96b86c))
* fix plugin and POT repository URL to luberan/balikovna-woocommerce ([8ed2e22](https://github.com/luberan/balikovna-woocommerce/commit/8ed2e22982bb95b53624c58da43ef9c11a81c9b0))
* refresh README with current feature set and CP-confirmed details ([c9064f2](https://github.com/luberan/balikovna-woocommerce/commit/c9064f296762ac8af05614e74b639ccd41b24722))
* sync readme.txt changelog with v1.3.1 [skip ci] ([943209c](https://github.com/luberan/balikovna-woocommerce/commit/943209c4b812108dece064a298276751bdd2bf37))
* sync readme.txt changelog with v1.3.2 [skip ci] ([4a6ccf9](https://github.com/luberan/balikovna-woocommerce/commit/4a6ccf9f73d6b486a4a77bd51a80b19755fd80fd))
* sync readme.txt changelog with v1.3.3 [skip ci] ([e04b302](https://github.com/luberan/balikovna-woocommerce/commit/e04b3022ae4fcbfc4120ba83037f2c72b7d1654b))
* sync readme.txt changelog with v1.3.4 [skip ci] ([aa075f1](https://github.com/luberan/balikovna-woocommerce/commit/aa075f1b2c1898d632ed1feaf21bec468427ce64))


### 🛠 CI

* add least-privilege permissions to workflow ([ffb6701](https://github.com/luberan/balikovna-woocommerce/commit/ffb6701b1731d72b95c3f1f3cf589495d6500b09))
* add release-please for automated changelog and version bumps ([a5cd194](https://github.com/luberan/balikovna-woocommerce/commit/a5cd194c961e2c81060b9d8fdc966d0c634e12c7))
* add workflow_dispatch trigger to release-please for manual re-runs ([698f6d9](https://github.com/luberan/balikovna-woocommerce/commit/698f6d954122b766868a2adfee89d392580de744))
* allow phpcodesniffer-composer-installer plugin (Composer 2.2+) ([d60fe6b](https://github.com/luberan/balikovna-woocommerce/commit/d60fe6b95a872502f0956dfd3159a928fa50be37))
* auto-merge release-please PR via PAT for fully unattended releases ([c7f7a9b](https://github.com/luberan/balikovna-woocommerce/commit/c7f7a9bd523efbedd41c9ca65b405bb3785b8ebe))
* build proper plugin ZIP on release publish ([314c053](https://github.com/luberan/balikovna-woocommerce/commit/314c0530a7c0acf100e9ada338f58eef350db028))
* bump actions/checkout from 4 to 6 ([#2](https://github.com/luberan/balikovna-woocommerce/issues/2)) ([aeb12f2](https://github.com/luberan/balikovna-woocommerce/commit/aeb12f206826ed53034f5f9a60759c52fbaa46e8))
* merge release PR via git+PAT instead of gh CLI to bypass anti-loop ([136cb84](https://github.com/luberan/balikovna-woocommerce/commit/136cb844b6f97a606c5d5b4abcce0be4eb140dce))
* use PAT for release-please to break GITHUB_TOKEN anti-loop ([445e8be](https://github.com/luberan/balikovna-woocommerce/commit/445e8bef5399331cd6dd3a55fb8f9eee4f1f3460))

## [1.7.0](https://github.com/luberan/balikovna-woocommerce/compare/v1.6.0...v1.7.0) (2026-05-29)


### 🚀 Nové funkce

* **ci:** include full changelog history in release ZIP readme.txt ([2fa9110](https://github.com/luberan/balikovna-woocommerce/commit/2fa91105871921b47e8d0300ac4e06d8da83324e))
* GitHub-driven auto-updates via Plugin Update Checker ([e29f487](https://github.com/luberan/balikovna-woocommerce/commit/e29f487efd4589f1c7d197be79e3411d56af4fa2))
* implement official CP picker payload and Podani Online CSV format ([146501a](https://github.com/luberan/balikovna-woocommerce/commit/146501a9f1c85093268c72be1b0157678d0776a6))
* per-method admin setting for CP service codes (CSV column I) ([cdd6771](https://github.com/luberan/balikovna-woocommerce/commit/cdd6771f8805be3c9c2fa3ff17147a23597ec7bf))


### 🐛 Opravy chyb

* **ci:** anchor release ZIP excludes so PUC vendor Parsedown survives ([95c4c86](https://github.com/luberan/balikovna-woocommerce/commit/95c4c86a92479b942343c3ca5e8458379cf221b2))
* **ci:** build release ZIP in the same workflow as release-please ([a8d9fc7](https://github.com/luberan/balikovna-woocommerce/commit/a8d9fc73da29661f5d302c66c987dc2b2b7d3f3d))
* **ci:** clone without --depth so PR branch is available for merge ([d8dde35](https://github.com/luberan/balikovna-woocommerce/commit/d8dde35a786c2e9dd834be8a0b54795110035837))
* **ci:** sync readme.txt changelog back to main so PUC sees it ([7007386](https://github.com/luberan/balikovna-woocommerce/commit/70073868a0a041884c6d5b700cbb6fc5525e6f8a))
* declare shipping method instance properties (PHP 8.2+ deprecation) ([524ab83](https://github.com/luberan/balikovna-woocommerce/commit/524ab838d3daa87fab146eb036efc648a1b18d58))
* do not break block checkout when picker not yet selected ([0000a78](https://github.com/luberan/balikovna-woocommerce/commit/0000a787b225f4e53109642a4c6382ff6f910c52))
* harden Plugin Update Checker bootstrap against fatals ([ad7a70c](https://github.com/luberan/balikovna-woocommerce/commit/ad7a70c10c01b6c96417927a68d98d2c26b6b923))
* load textdomain on init hook (WP 6.7+ requirement) ([c1a03dc](https://github.com/luberan/balikovna-woocommerce/commit/c1a03dce6b86e2f56c8bb081a55ad6bcf5e7d148))
* override changelog modal with local readme.txt history ([5901ee4](https://github.com/luberan/balikovna-woocommerce/commit/5901ee477117bdcd870fc890aa240c85914fd37c))
* trigger release to publish synced readme.txt changelog to main ([44ef828](https://github.com/luberan/balikovna-woocommerce/commit/44ef8287cb3e543bc1e91e67b373dba714649437))


### 🔧 Kompatibilita

* bump targets to WP 7.0, PHP 8.5, WC 10.8 ([4b758ff](https://github.com/luberan/balikovna-woocommerce/commit/4b758ff6d189dd50b9a04e21bcc099712f215e22))
* declare realistic PHP minimum 7.4 (tested up to 8.5) ([58367d3](https://github.com/luberan/balikovna-woocommerce/commit/58367d3e336806eef395842731740f4f81a9953e))
* declare realistic WordPress minimum 6.0 (tested up to 7.0) ([f2bdaf6](https://github.com/luberan/balikovna-woocommerce/commit/f2bdaf6a4754fef19492e91d29ca9683513115a1))


### 📚 Dokumentace

* add Author URI to plugin header ([295b9de](https://github.com/luberan/balikovna-woocommerce/commit/295b9de28a8fabce3e4f7a1ecadb5dccfd8acf97))
* align license metadata with GPL-3.0-or-later ([597a953](https://github.com/luberan/balikovna-woocommerce/commit/597a953e231ecf2d58d135c9590043730a96b86c))
* fix plugin and POT repository URL to luberan/balikovna-woocommerce ([8ed2e22](https://github.com/luberan/balikovna-woocommerce/commit/8ed2e22982bb95b53624c58da43ef9c11a81c9b0))
* refresh README with current feature set and CP-confirmed details ([c9064f2](https://github.com/luberan/balikovna-woocommerce/commit/c9064f296762ac8af05614e74b639ccd41b24722))
* sync readme.txt changelog with v1.3.1 [skip ci] ([943209c](https://github.com/luberan/balikovna-woocommerce/commit/943209c4b812108dece064a298276751bdd2bf37))
* sync readme.txt changelog with v1.3.2 [skip ci] ([4a6ccf9](https://github.com/luberan/balikovna-woocommerce/commit/4a6ccf9f73d6b486a4a77bd51a80b19755fd80fd))
* sync readme.txt changelog with v1.3.3 [skip ci] ([e04b302](https://github.com/luberan/balikovna-woocommerce/commit/e04b3022ae4fcbfc4120ba83037f2c72b7d1654b))
* sync readme.txt changelog with v1.3.4 [skip ci] ([aa075f1](https://github.com/luberan/balikovna-woocommerce/commit/aa075f1b2c1898d632ed1feaf21bec468427ce64))


### 🛠 CI

* add least-privilege permissions to workflow ([ffb6701](https://github.com/luberan/balikovna-woocommerce/commit/ffb6701b1731d72b95c3f1f3cf589495d6500b09))
* add release-please for automated changelog and version bumps ([a5cd194](https://github.com/luberan/balikovna-woocommerce/commit/a5cd194c961e2c81060b9d8fdc966d0c634e12c7))
* add workflow_dispatch trigger to release-please for manual re-runs ([698f6d9](https://github.com/luberan/balikovna-woocommerce/commit/698f6d954122b766868a2adfee89d392580de744))
* allow phpcodesniffer-composer-installer plugin (Composer 2.2+) ([d60fe6b](https://github.com/luberan/balikovna-woocommerce/commit/d60fe6b95a872502f0956dfd3159a928fa50be37))
* auto-merge release-please PR via PAT for fully unattended releases ([c7f7a9b](https://github.com/luberan/balikovna-woocommerce/commit/c7f7a9bd523efbedd41c9ca65b405bb3785b8ebe))
* build proper plugin ZIP on release publish ([314c053](https://github.com/luberan/balikovna-woocommerce/commit/314c0530a7c0acf100e9ada338f58eef350db028))
* bump actions/checkout from 4 to 6 ([#2](https://github.com/luberan/balikovna-woocommerce/issues/2)) ([aeb12f2](https://github.com/luberan/balikovna-woocommerce/commit/aeb12f206826ed53034f5f9a60759c52fbaa46e8))
* merge release PR via git+PAT instead of gh CLI to bypass anti-loop ([136cb84](https://github.com/luberan/balikovna-woocommerce/commit/136cb844b6f97a606c5d5b4abcce0be4eb140dce))
* use PAT for release-please to break GITHUB_TOKEN anti-loop ([445e8be](https://github.com/luberan/balikovna-woocommerce/commit/445e8bef5399331cd6dd3a55fb8f9eee4f1f3460))

## [1.6.0](https://github.com/luberan/balikovna-woocommerce/compare/v1.5.0...v1.6.0) (2026-05-29)


### 🚀 Nové funkce

* **ci:** include full changelog history in release ZIP readme.txt ([2fa9110](https://github.com/luberan/balikovna-woocommerce/commit/2fa91105871921b47e8d0300ac4e06d8da83324e))
* GitHub-driven auto-updates via Plugin Update Checker ([e29f487](https://github.com/luberan/balikovna-woocommerce/commit/e29f487efd4589f1c7d197be79e3411d56af4fa2))
* implement official CP picker payload and Podani Online CSV format ([146501a](https://github.com/luberan/balikovna-woocommerce/commit/146501a9f1c85093268c72be1b0157678d0776a6))
* per-method admin setting for CP service codes (CSV column I) ([cdd6771](https://github.com/luberan/balikovna-woocommerce/commit/cdd6771f8805be3c9c2fa3ff17147a23597ec7bf))


### 🐛 Opravy chyb

* **ci:** anchor release ZIP excludes so PUC vendor Parsedown survives ([95c4c86](https://github.com/luberan/balikovna-woocommerce/commit/95c4c86a92479b942343c3ca5e8458379cf221b2))
* **ci:** build release ZIP in the same workflow as release-please ([a8d9fc7](https://github.com/luberan/balikovna-woocommerce/commit/a8d9fc73da29661f5d302c66c987dc2b2b7d3f3d))
* **ci:** clone without --depth so PR branch is available for merge ([d8dde35](https://github.com/luberan/balikovna-woocommerce/commit/d8dde35a786c2e9dd834be8a0b54795110035837))
* **ci:** sync readme.txt changelog back to main so PUC sees it ([7007386](https://github.com/luberan/balikovna-woocommerce/commit/70073868a0a041884c6d5b700cbb6fc5525e6f8a))
* declare shipping method instance properties (PHP 8.2+ deprecation) ([524ab83](https://github.com/luberan/balikovna-woocommerce/commit/524ab838d3daa87fab146eb036efc648a1b18d58))
* do not break block checkout when picker not yet selected ([0000a78](https://github.com/luberan/balikovna-woocommerce/commit/0000a787b225f4e53109642a4c6382ff6f910c52))
* harden Plugin Update Checker bootstrap against fatals ([ad7a70c](https://github.com/luberan/balikovna-woocommerce/commit/ad7a70c10c01b6c96417927a68d98d2c26b6b923))
* load textdomain on init hook (WP 6.7+ requirement) ([c1a03dc](https://github.com/luberan/balikovna-woocommerce/commit/c1a03dce6b86e2f56c8bb081a55ad6bcf5e7d148))
* override changelog modal with local readme.txt history ([5901ee4](https://github.com/luberan/balikovna-woocommerce/commit/5901ee477117bdcd870fc890aa240c85914fd37c))
* trigger release to publish synced readme.txt changelog to main ([44ef828](https://github.com/luberan/balikovna-woocommerce/commit/44ef8287cb3e543bc1e91e67b373dba714649437))


### 🔧 Kompatibilita

* bump targets to WP 7.0, PHP 8.5, WC 10.8 ([4b758ff](https://github.com/luberan/balikovna-woocommerce/commit/4b758ff6d189dd50b9a04e21bcc099712f215e22))
* declare realistic PHP minimum 7.4 (tested up to 8.5) ([58367d3](https://github.com/luberan/balikovna-woocommerce/commit/58367d3e336806eef395842731740f4f81a9953e))
* declare realistic WordPress minimum 6.0 (tested up to 7.0) ([f2bdaf6](https://github.com/luberan/balikovna-woocommerce/commit/f2bdaf6a4754fef19492e91d29ca9683513115a1))


### 📚 Dokumentace

* add Author URI to plugin header ([295b9de](https://github.com/luberan/balikovna-woocommerce/commit/295b9de28a8fabce3e4f7a1ecadb5dccfd8acf97))
* align license metadata with GPL-3.0-or-later ([597a953](https://github.com/luberan/balikovna-woocommerce/commit/597a953e231ecf2d58d135c9590043730a96b86c))
* fix plugin and POT repository URL to luberan/balikovna-woocommerce ([8ed2e22](https://github.com/luberan/balikovna-woocommerce/commit/8ed2e22982bb95b53624c58da43ef9c11a81c9b0))
* refresh README with current feature set and CP-confirmed details ([c9064f2](https://github.com/luberan/balikovna-woocommerce/commit/c9064f296762ac8af05614e74b639ccd41b24722))
* sync readme.txt changelog with v1.3.1 [skip ci] ([943209c](https://github.com/luberan/balikovna-woocommerce/commit/943209c4b812108dece064a298276751bdd2bf37))
* sync readme.txt changelog with v1.3.2 [skip ci] ([4a6ccf9](https://github.com/luberan/balikovna-woocommerce/commit/4a6ccf9f73d6b486a4a77bd51a80b19755fd80fd))
* sync readme.txt changelog with v1.3.3 [skip ci] ([e04b302](https://github.com/luberan/balikovna-woocommerce/commit/e04b3022ae4fcbfc4120ba83037f2c72b7d1654b))
* sync readme.txt changelog with v1.3.4 [skip ci] ([aa075f1](https://github.com/luberan/balikovna-woocommerce/commit/aa075f1b2c1898d632ed1feaf21bec468427ce64))


### 🛠 CI

* add least-privilege permissions to workflow ([ffb6701](https://github.com/luberan/balikovna-woocommerce/commit/ffb6701b1731d72b95c3f1f3cf589495d6500b09))
* add release-please for automated changelog and version bumps ([a5cd194](https://github.com/luberan/balikovna-woocommerce/commit/a5cd194c961e2c81060b9d8fdc966d0c634e12c7))
* add workflow_dispatch trigger to release-please for manual re-runs ([698f6d9](https://github.com/luberan/balikovna-woocommerce/commit/698f6d954122b766868a2adfee89d392580de744))
* allow phpcodesniffer-composer-installer plugin (Composer 2.2+) ([d60fe6b](https://github.com/luberan/balikovna-woocommerce/commit/d60fe6b95a872502f0956dfd3159a928fa50be37))
* auto-merge release-please PR via PAT for fully unattended releases ([c7f7a9b](https://github.com/luberan/balikovna-woocommerce/commit/c7f7a9bd523efbedd41c9ca65b405bb3785b8ebe))
* build proper plugin ZIP on release publish ([314c053](https://github.com/luberan/balikovna-woocommerce/commit/314c0530a7c0acf100e9ada338f58eef350db028))
* bump actions/checkout from 4 to 6 ([#2](https://github.com/luberan/balikovna-woocommerce/issues/2)) ([aeb12f2](https://github.com/luberan/balikovna-woocommerce/commit/aeb12f206826ed53034f5f9a60759c52fbaa46e8))
* merge release PR via git+PAT instead of gh CLI to bypass anti-loop ([136cb84](https://github.com/luberan/balikovna-woocommerce/commit/136cb844b6f97a606c5d5b4abcce0be4eb140dce))
* use PAT for release-please to break GITHUB_TOKEN anti-loop ([445e8be](https://github.com/luberan/balikovna-woocommerce/commit/445e8bef5399331cd6dd3a55fb8f9eee4f1f3460))

## [1.5.0](https://github.com/luberan/balikovna-woocommerce/compare/v1.4.0...v1.5.0) (2026-05-29)


### 🚀 Nové funkce

* **ci:** include full changelog history in release ZIP readme.txt ([2fa9110](https://github.com/luberan/balikovna-woocommerce/commit/2fa91105871921b47e8d0300ac4e06d8da83324e))
* GitHub-driven auto-updates via Plugin Update Checker ([e29f487](https://github.com/luberan/balikovna-woocommerce/commit/e29f487efd4589f1c7d197be79e3411d56af4fa2))
* implement official CP picker payload and Podani Online CSV format ([146501a](https://github.com/luberan/balikovna-woocommerce/commit/146501a9f1c85093268c72be1b0157678d0776a6))
* per-method admin setting for CP service codes (CSV column I) ([cdd6771](https://github.com/luberan/balikovna-woocommerce/commit/cdd6771f8805be3c9c2fa3ff17147a23597ec7bf))


### 🐛 Opravy chyb

* **ci:** anchor release ZIP excludes so PUC vendor Parsedown survives ([95c4c86](https://github.com/luberan/balikovna-woocommerce/commit/95c4c86a92479b942343c3ca5e8458379cf221b2))
* **ci:** build release ZIP in the same workflow as release-please ([a8d9fc7](https://github.com/luberan/balikovna-woocommerce/commit/a8d9fc73da29661f5d302c66c987dc2b2b7d3f3d))
* **ci:** clone without --depth so PR branch is available for merge ([d8dde35](https://github.com/luberan/balikovna-woocommerce/commit/d8dde35a786c2e9dd834be8a0b54795110035837))
* **ci:** sync readme.txt changelog back to main so PUC sees it ([7007386](https://github.com/luberan/balikovna-woocommerce/commit/70073868a0a041884c6d5b700cbb6fc5525e6f8a))
* declare shipping method instance properties (PHP 8.2+ deprecation) ([524ab83](https://github.com/luberan/balikovna-woocommerce/commit/524ab838d3daa87fab146eb036efc648a1b18d58))
* do not break block checkout when picker not yet selected ([0000a78](https://github.com/luberan/balikovna-woocommerce/commit/0000a787b225f4e53109642a4c6382ff6f910c52))
* harden Plugin Update Checker bootstrap against fatals ([ad7a70c](https://github.com/luberan/balikovna-woocommerce/commit/ad7a70c10c01b6c96417927a68d98d2c26b6b923))
* load textdomain on init hook (WP 6.7+ requirement) ([c1a03dc](https://github.com/luberan/balikovna-woocommerce/commit/c1a03dce6b86e2f56c8bb081a55ad6bcf5e7d148))
* override changelog modal with local readme.txt history ([5901ee4](https://github.com/luberan/balikovna-woocommerce/commit/5901ee477117bdcd870fc890aa240c85914fd37c))
* trigger release to publish synced readme.txt changelog to main ([44ef828](https://github.com/luberan/balikovna-woocommerce/commit/44ef8287cb3e543bc1e91e67b373dba714649437))


### 🔧 Kompatibilita

* bump targets to WP 7.0, PHP 8.5, WC 10.8 ([4b758ff](https://github.com/luberan/balikovna-woocommerce/commit/4b758ff6d189dd50b9a04e21bcc099712f215e22))
* declare realistic PHP minimum 7.4 (tested up to 8.5) ([58367d3](https://github.com/luberan/balikovna-woocommerce/commit/58367d3e336806eef395842731740f4f81a9953e))
* declare realistic WordPress minimum 6.0 (tested up to 7.0) ([f2bdaf6](https://github.com/luberan/balikovna-woocommerce/commit/f2bdaf6a4754fef19492e91d29ca9683513115a1))


### 📚 Dokumentace

* add Author URI to plugin header ([295b9de](https://github.com/luberan/balikovna-woocommerce/commit/295b9de28a8fabce3e4f7a1ecadb5dccfd8acf97))
* align license metadata with GPL-3.0-or-later ([597a953](https://github.com/luberan/balikovna-woocommerce/commit/597a953e231ecf2d58d135c9590043730a96b86c))
* fix plugin and POT repository URL to luberan/balikovna-woocommerce ([8ed2e22](https://github.com/luberan/balikovna-woocommerce/commit/8ed2e22982bb95b53624c58da43ef9c11a81c9b0))
* refresh README with current feature set and CP-confirmed details ([c9064f2](https://github.com/luberan/balikovna-woocommerce/commit/c9064f296762ac8af05614e74b639ccd41b24722))
* sync readme.txt changelog with v1.3.1 [skip ci] ([943209c](https://github.com/luberan/balikovna-woocommerce/commit/943209c4b812108dece064a298276751bdd2bf37))
* sync readme.txt changelog with v1.3.2 [skip ci] ([4a6ccf9](https://github.com/luberan/balikovna-woocommerce/commit/4a6ccf9f73d6b486a4a77bd51a80b19755fd80fd))
* sync readme.txt changelog with v1.3.3 [skip ci] ([e04b302](https://github.com/luberan/balikovna-woocommerce/commit/e04b3022ae4fcbfc4120ba83037f2c72b7d1654b))
* sync readme.txt changelog with v1.3.4 [skip ci] ([aa075f1](https://github.com/luberan/balikovna-woocommerce/commit/aa075f1b2c1898d632ed1feaf21bec468427ce64))


### 🛠 CI

* add least-privilege permissions to workflow ([ffb6701](https://github.com/luberan/balikovna-woocommerce/commit/ffb6701b1731d72b95c3f1f3cf589495d6500b09))
* add release-please for automated changelog and version bumps ([a5cd194](https://github.com/luberan/balikovna-woocommerce/commit/a5cd194c961e2c81060b9d8fdc966d0c634e12c7))
* add workflow_dispatch trigger to release-please for manual re-runs ([698f6d9](https://github.com/luberan/balikovna-woocommerce/commit/698f6d954122b766868a2adfee89d392580de744))
* allow phpcodesniffer-composer-installer plugin (Composer 2.2+) ([d60fe6b](https://github.com/luberan/balikovna-woocommerce/commit/d60fe6b95a872502f0956dfd3159a928fa50be37))
* auto-merge release-please PR via PAT for fully unattended releases ([c7f7a9b](https://github.com/luberan/balikovna-woocommerce/commit/c7f7a9bd523efbedd41c9ca65b405bb3785b8ebe))
* build proper plugin ZIP on release publish ([314c053](https://github.com/luberan/balikovna-woocommerce/commit/314c0530a7c0acf100e9ada338f58eef350db028))
* bump actions/checkout from 4 to 6 ([#2](https://github.com/luberan/balikovna-woocommerce/issues/2)) ([aeb12f2](https://github.com/luberan/balikovna-woocommerce/commit/aeb12f206826ed53034f5f9a60759c52fbaa46e8))
* merge release PR via git+PAT instead of gh CLI to bypass anti-loop ([136cb84](https://github.com/luberan/balikovna-woocommerce/commit/136cb844b6f97a606c5d5b4abcce0be4eb140dce))
* use PAT for release-please to break GITHUB_TOKEN anti-loop ([445e8be](https://github.com/luberan/balikovna-woocommerce/commit/445e8bef5399331cd6dd3a55fb8f9eee4f1f3460))

## [1.4.0](https://github.com/luberan/balikovna-woocommerce/compare/v1.3.6...v1.4.0) (2026-05-29)


### 🚀 Nové funkce

* **ci:** include full changelog history in release ZIP readme.txt ([2fa9110](https://github.com/luberan/balikovna-woocommerce/commit/2fa91105871921b47e8d0300ac4e06d8da83324e))
* GitHub-driven auto-updates via Plugin Update Checker ([e29f487](https://github.com/luberan/balikovna-woocommerce/commit/e29f487efd4589f1c7d197be79e3411d56af4fa2))
* implement official CP picker payload and Podani Online CSV format ([146501a](https://github.com/luberan/balikovna-woocommerce/commit/146501a9f1c85093268c72be1b0157678d0776a6))
* per-method admin setting for CP service codes (CSV column I) ([cdd6771](https://github.com/luberan/balikovna-woocommerce/commit/cdd6771f8805be3c9c2fa3ff17147a23597ec7bf))


### 🐛 Opravy chyb

* **ci:** anchor release ZIP excludes so PUC vendor Parsedown survives ([95c4c86](https://github.com/luberan/balikovna-woocommerce/commit/95c4c86a92479b942343c3ca5e8458379cf221b2))
* **ci:** build release ZIP in the same workflow as release-please ([a8d9fc7](https://github.com/luberan/balikovna-woocommerce/commit/a8d9fc73da29661f5d302c66c987dc2b2b7d3f3d))
* **ci:** clone without --depth so PR branch is available for merge ([d8dde35](https://github.com/luberan/balikovna-woocommerce/commit/d8dde35a786c2e9dd834be8a0b54795110035837))
* **ci:** sync readme.txt changelog back to main so PUC sees it ([7007386](https://github.com/luberan/balikovna-woocommerce/commit/70073868a0a041884c6d5b700cbb6fc5525e6f8a))
* declare shipping method instance properties (PHP 8.2+ deprecation) ([524ab83](https://github.com/luberan/balikovna-woocommerce/commit/524ab838d3daa87fab146eb036efc648a1b18d58))
* do not break block checkout when picker not yet selected ([0000a78](https://github.com/luberan/balikovna-woocommerce/commit/0000a787b225f4e53109642a4c6382ff6f910c52))
* harden Plugin Update Checker bootstrap against fatals ([ad7a70c](https://github.com/luberan/balikovna-woocommerce/commit/ad7a70c10c01b6c96417927a68d98d2c26b6b923))
* load textdomain on init hook (WP 6.7+ requirement) ([c1a03dc](https://github.com/luberan/balikovna-woocommerce/commit/c1a03dce6b86e2f56c8bb081a55ad6bcf5e7d148))
* override changelog modal with local readme.txt history ([5901ee4](https://github.com/luberan/balikovna-woocommerce/commit/5901ee477117bdcd870fc890aa240c85914fd37c))
* trigger release to publish synced readme.txt changelog to main ([44ef828](https://github.com/luberan/balikovna-woocommerce/commit/44ef8287cb3e543bc1e91e67b373dba714649437))


### 🔧 Kompatibilita

* bump targets to WP 7.0, PHP 8.5, WC 10.8 ([4b758ff](https://github.com/luberan/balikovna-woocommerce/commit/4b758ff6d189dd50b9a04e21bcc099712f215e22))
* declare realistic PHP minimum 7.4 (tested up to 8.5) ([58367d3](https://github.com/luberan/balikovna-woocommerce/commit/58367d3e336806eef395842731740f4f81a9953e))
* declare realistic WordPress minimum 6.0 (tested up to 7.0) ([f2bdaf6](https://github.com/luberan/balikovna-woocommerce/commit/f2bdaf6a4754fef19492e91d29ca9683513115a1))


### 📚 Dokumentace

* add Author URI to plugin header ([295b9de](https://github.com/luberan/balikovna-woocommerce/commit/295b9de28a8fabce3e4f7a1ecadb5dccfd8acf97))
* align license metadata with GPL-3.0-or-later ([597a953](https://github.com/luberan/balikovna-woocommerce/commit/597a953e231ecf2d58d135c9590043730a96b86c))
* fix plugin and POT repository URL to luberan/balikovna-woocommerce ([8ed2e22](https://github.com/luberan/balikovna-woocommerce/commit/8ed2e22982bb95b53624c58da43ef9c11a81c9b0))
* refresh README with current feature set and CP-confirmed details ([c9064f2](https://github.com/luberan/balikovna-woocommerce/commit/c9064f296762ac8af05614e74b639ccd41b24722))
* sync readme.txt changelog with v1.3.1 [skip ci] ([943209c](https://github.com/luberan/balikovna-woocommerce/commit/943209c4b812108dece064a298276751bdd2bf37))
* sync readme.txt changelog with v1.3.2 [skip ci] ([4a6ccf9](https://github.com/luberan/balikovna-woocommerce/commit/4a6ccf9f73d6b486a4a77bd51a80b19755fd80fd))
* sync readme.txt changelog with v1.3.3 [skip ci] ([e04b302](https://github.com/luberan/balikovna-woocommerce/commit/e04b3022ae4fcbfc4120ba83037f2c72b7d1654b))
* sync readme.txt changelog with v1.3.4 [skip ci] ([aa075f1](https://github.com/luberan/balikovna-woocommerce/commit/aa075f1b2c1898d632ed1feaf21bec468427ce64))


### 🛠 CI

* add least-privilege permissions to workflow ([ffb6701](https://github.com/luberan/balikovna-woocommerce/commit/ffb6701b1731d72b95c3f1f3cf589495d6500b09))
* add release-please for automated changelog and version bumps ([a5cd194](https://github.com/luberan/balikovna-woocommerce/commit/a5cd194c961e2c81060b9d8fdc966d0c634e12c7))
* add workflow_dispatch trigger to release-please for manual re-runs ([698f6d9](https://github.com/luberan/balikovna-woocommerce/commit/698f6d954122b766868a2adfee89d392580de744))
* allow phpcodesniffer-composer-installer plugin (Composer 2.2+) ([d60fe6b](https://github.com/luberan/balikovna-woocommerce/commit/d60fe6b95a872502f0956dfd3159a928fa50be37))
* auto-merge release-please PR via PAT for fully unattended releases ([c7f7a9b](https://github.com/luberan/balikovna-woocommerce/commit/c7f7a9bd523efbedd41c9ca65b405bb3785b8ebe))
* build proper plugin ZIP on release publish ([314c053](https://github.com/luberan/balikovna-woocommerce/commit/314c0530a7c0acf100e9ada338f58eef350db028))
* bump actions/checkout from 4 to 6 ([#2](https://github.com/luberan/balikovna-woocommerce/issues/2)) ([aeb12f2](https://github.com/luberan/balikovna-woocommerce/commit/aeb12f206826ed53034f5f9a60759c52fbaa46e8))
* merge release PR via git+PAT instead of gh CLI to bypass anti-loop ([136cb84](https://github.com/luberan/balikovna-woocommerce/commit/136cb844b6f97a606c5d5b4abcce0be4eb140dce))
* use PAT for release-please to break GITHUB_TOKEN anti-loop ([445e8be](https://github.com/luberan/balikovna-woocommerce/commit/445e8bef5399331cd6dd3a55fb8f9eee4f1f3460))

## [1.3.6](https://github.com/luberan/balikovna-woocommerce/compare/v1.3.5...v1.3.6) (2026-05-29)


### 🐛 Opravy chyb

* **ci:** clone without --depth so PR branch is available for merge ([d8dde35](https://github.com/luberan/balikovna-woocommerce/commit/d8dde35a786c2e9dd834be8a0b54795110035837))


### 🛠 CI

* merge release PR via git+PAT instead of gh CLI to bypass anti-loop ([136cb84](https://github.com/luberan/balikovna-woocommerce/commit/136cb844b6f97a606c5d5b4abcce0be4eb140dce))

## [1.3.5](https://github.com/luberan/balikovna-woocommerce/compare/v1.3.4...v1.3.5) (2026-05-29)


### 📚 Dokumentace

* sync readme.txt changelog with v1.3.4 [skip ci] ([aa075f1](https://github.com/luberan/balikovna-woocommerce/commit/aa075f1b2c1898d632ed1feaf21bec468427ce64))


### 🛠 CI

* auto-merge release-please PR via PAT for fully unattended releases ([c7f7a9b](https://github.com/luberan/balikovna-woocommerce/commit/c7f7a9bd523efbedd41c9ca65b405bb3785b8ebe))

## [1.3.4](https://github.com/luberan/balikovna-woocommerce/compare/v1.3.3...v1.3.4) (2026-05-29)


### 📚 Dokumentace

* sync readme.txt changelog with v1.3.3 [skip ci] ([e04b302](https://github.com/luberan/balikovna-woocommerce/commit/e04b3022ae4fcbfc4120ba83037f2c72b7d1654b))


### 🛠 CI

* use PAT for release-please to break GITHUB_TOKEN anti-loop ([445e8be](https://github.com/luberan/balikovna-woocommerce/commit/445e8bef5399331cd6dd3a55fb8f9eee4f1f3460))

## [1.3.3](https://github.com/luberan/balikovna-woocommerce/compare/v1.3.2...v1.3.3) (2026-05-29)


### 🐛 Opravy chyb

* do not break block checkout when picker not yet selected ([0000a78](https://github.com/luberan/balikovna-woocommerce/commit/0000a787b225f4e53109642a4c6382ff6f910c52))


### 📚 Dokumentace

* sync readme.txt changelog with v1.3.2 [skip ci] ([4a6ccf9](https://github.com/luberan/balikovna-woocommerce/commit/4a6ccf9f73d6b486a4a77bd51a80b19755fd80fd))


### 🛠 CI

* add workflow_dispatch trigger to release-please for manual re-runs ([698f6d9](https://github.com/luberan/balikovna-woocommerce/commit/698f6d954122b766868a2adfee89d392580de744))

## [1.3.2](https://github.com/luberan/balikovna-woocommerce/compare/v1.3.1...v1.3.2) (2026-05-29)


### 🐛 Opravy chyb

* override changelog modal with local readme.txt history ([5901ee4](https://github.com/luberan/balikovna-woocommerce/commit/5901ee477117bdcd870fc890aa240c85914fd37c))


### 📚 Dokumentace

* sync readme.txt changelog with v1.3.1 [skip ci] ([943209c](https://github.com/luberan/balikovna-woocommerce/commit/943209c4b812108dece064a298276751bdd2bf37))

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
