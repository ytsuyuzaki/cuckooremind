# Issue: Laravel 10 から 11 へのバージョンアップ

## 背景
- 現行は Laravel 10 系を利用しているため、まず Laravel 11 系へ段階的に移行する。
- 依存パッケージおよび Sanctum 設定の互換性を確保する。

## 対応予定
- [x] 事前調査（現行依存バージョンと設定の確認）
- [x] Composer 依存の Laravel 11 対応
- [x] Sanctum 設定の Laravel 11 / Sanctum 4 対応
- [ ] Laravel 11 対応後の依存解決（`composer update`）※環境ネットワーク制約で保留
- [ ] マイグレーション / テスト実行で動作確認 ※vendor 未生成のため保留
- [ ] 必要に応じた不具合修正

## 作業履歴
- 2026-03-30: 現行バージョン確認（`laravel/framework v10.50.0`）
- 2026-03-30: `composer.json` を Laravel 11 系制約へ更新
- 2026-03-30: `config/sanctum.php` の middleware 設定を Sanctum 4 向けに更新
- 2026-03-30: `composer update -W` 実行時に `repo.packagist.org` への接続が 403 で失敗
- 2026-03-30: `php artisan test` 実行時に `vendor/autoload.php` 不在で失敗（依存未導入）

## 検証観点
- 認証（ログイン、CSRF、セッション）
- API 認証（Sanctum トークン）
- チーム機能（Jetstream）
- 既存 Feature テストの後方互換
