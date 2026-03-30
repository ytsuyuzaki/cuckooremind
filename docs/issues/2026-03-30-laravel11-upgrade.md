# Issue: Laravel 10 から 11 へのバージョンアップ

## 背景
- 現行は Laravel 10 系を利用しているため、まず Laravel 11 系へ段階的に移行する。
- 依存パッケージおよび Sanctum 設定の互換性を確保する。

## 対応予定
- [x] 事前調査（現行依存バージョンと設定の確認）
- [x] Composer 依存の Laravel 11 対応
- [x] Sanctum 設定の Laravel 11 / Sanctum 4 対応
- [x] Laravel 11 対応後の依存解決（`composer update`）
- [x] マイグレーション / テスト実行で動作確認
- [x] 必要に応じた不具合修正

## 作業履歴
- 2026-03-30: 現行バージョン確認（`laravel/framework v10.50.0`）
- 2026-03-30: `composer.json` を Laravel 11 系制約へ更新
- 2026-03-30: `config/sanctum.php` の middleware 設定を Sanctum 4 向けに更新
- 2026-03-30: `composer update -W` 実行時に `repo.packagist.org` への接続が 403 で失敗
- 2026-03-30: `php artisan test` 実行時に `vendor/autoload.php` 不在で失敗（依存未導入）
- 2026-03-30: `composer update -W` を再実行し、`laravel/framework v11.51.0` / `laravel/jetstream v5.5.2` / `laravel/sanctum v4.3.1` へ更新
- 2026-03-30: テスト用 SQLite ファイルを `tests/CreatesApplication.php` で自動生成するようにして、`database/database_testing.sqlite` 不在によるテスト失敗を解消
- 2026-03-30: `tests/TestCase.php` で `withoutVite()` を共通適用し、`public/build/manifest.json` 不在による画面テスト失敗を解消
- 2026-03-30: `php artisan test` 実行結果: 43 passed, 2 skipped（Registration 無効設定のため想定スキップ）

## 検証観点
- 認証（ログイン、CSRF、セッション）
- API 認証（Sanctum トークン）
- チーム機能（Jetstream）
- 既存 Feature テストの後方互換
