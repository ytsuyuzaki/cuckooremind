# CuckooRemind 更新ガイド

## 画面から更新する

最初に作成されたユーザーがシステム管理者になります。ログイン後、「システム更新」で更新内容と更新前診断を確認し、現在のパスワードを入力して更新してください。

更新処理は次を自動実行します。

1. GitHub Release の単一 ZIP をダウンロード
2. SHA-256、manifest、全ファイルの検証
3. アプリコードと SQLite DB のバックアップ
4. メンテナンスモードへの切り替え
5. `.env` と `storage/**` を除外してコードを更新
6. migration とキャッシュ再構築
7. ヘルスチェックとメンテナンスモード解除

画面更新機能を搭載していない旧版から、初めて画面更新機能を搭載した版への移行だけは、従来の手動更新が必要です。

## CLIから更新する

```bash
php artisan app:update
php artisan app:update:status
```

非対話実行では `--yes` を指定します。MySQL/PostgreSQLの場合は、外部バックアップを取得した後に `--database-backup-confirmed` も指定してください。

## 復旧する

更新失敗時は自動復旧を試みます。手動で直近のバックアップを復元する場合は次を実行します。

```bash
php artisan app:update:restore
```

バックアップと状態・ログは `storage/app/updates/` に保存されます。復旧にも失敗してメンテナンスモードが維持された場合は、ファイルとDBを確認した後に `php artisan up` を実行してください。

SQLiteは更新前のDBファイルを自動保存・復元します。MySQL/PostgreSQLは自動復元の対象外なので、更新前にホスティングサービス等で完全なバックアップを取得してください。

## 設定

`.env` で次を設定できます。

```dotenv
APP_UPDATE_ENABLED=true
APP_UPDATE_REPOSITORY=ytsuyuzaki/cuckooremind
# APP_UPDATE_GITHUB_TOKEN=
# APP_UPDATE_CACHE_TTL=21600
# APP_UPDATE_TIMEOUT=15
# APP_UPDATE_MAX_SIZE=157286400
# APP_UPDATE_BACKUP_KEEP=3
```

WebサーバーのPHPには、外部HTTPS通信、`ZipArchive`、アプリルートと `storage` への書込権限、ZIPサイズの3倍以上を目安とした空き容量が必要です。
