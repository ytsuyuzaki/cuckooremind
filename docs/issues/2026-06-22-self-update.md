# Issue #25: リリース通知・画面からのアップデート

## Issue の確認結果

- Issue: [#25 リリース公開したら既存の使用者に通知&更新フローの作成](https://github.com/ytsuyuzaki/cuckooremind/issues/25)
- Issue 本文とコメントはなく、タイトルのみ登録されている。
- 現在は `v*` タグの push を契機に GitHub Actions が配布 ZIP を作り、GitHub Releases に添付している。
- アプリの現在バージョンはルートの `.version` から取得できる。
- 現行の配布 ZIP は新規インストール用であり、初期 `.env` と `storage/db.sqlite` を含む。そのまま既存環境へ展開すると、設定や利用者データを上書きする危険がある。

## 目的

1. GitHub Releases に新しい安定版が公開されたことをアプリ画面で確認できる。
2. リリースノート（更新履歴）を画面で閲覧できる。
3. システム管理者が画面操作で安全にアップデートできる。
4. 更新中の障害で `.env`、データベース、アップロードファイルを失わない。
5. CLI、Git、Node.js、Composer がない一般的なレンタルサーバーでも、配布 ZIP に含まれる PHP コードだけで更新できる。

## 対象範囲

### MVP に含める

- GitHub Releases API から安定版リリース一覧を取得
- 現在版と最新版の比較および更新通知
- リリースノート表示
- 実行環境の事前診断
- ZIP のダウンロード、検証、展開、更新
- Laravel のマイグレーションとキャッシュ再構築
- 更新ロック、進捗・結果・エラーログ表示
- アプリコードと SQLite DB のバックアップ
- 更新失敗時のベストエフォートな自動復旧
- CLI から同じ更新処理を実行できる Artisan コマンド

### MVP に含めない

- プレリリース、ドラフトリリースへの更新
- GitHub 以外の配布元
- 複数サーバー構成への同時デプロイ
- 任意バージョンへのダウングレード
- 更新前のアプリへ画面操作だけで戻す正式なロールバック機能

## 画面仕様

### 通知

- 認証後の共通レイアウトに「新しいバージョンがあります」を表示する。
- 通知には最新版、現在版、「更新内容を見る」リンクを表示する。
- GitHub API 障害時は通常のアプリ利用を妨げず、通知を表示しない。システム管理画面にだけ取得エラーを表示する。
- 通知確認のたびに GitHub API を呼ばず、取得結果を 6 時間程度キャッシュする。「更新を確認」で明示的に再取得できるようにする。

### システム更新画面

`/system/updates` に次の内容を表示する。

- 現在バージョン、最新版、公開日時
- 最新数件のリリースノート
- 更新可否と事前診断結果
- 「ダウンロードして更新」ボタン
- 更新中の状態、開始・終了日時、実行ユーザー、エラー内容
- バックアップの保存先と手動復旧手順へのリンク

リリースノートは Markdown をサーバー側で安全な HTML に変換し、生 HTML と危険な URL スキームを許可しない。

### 実行権限

アプリ更新はチーム単位ではなくインストール全体に影響するため、Jetstream のチーム管理者権限を流用しない。

- `users.is_system_admin` を追加する。
- 新規インストールでは最初のユーザーをシステム管理者にする。
- 既存環境へのマイグレーションでは最小 ID のユーザーをシステム管理者にする。
- 更新画面の閲覧・実行はシステム管理者だけに許可する。
- 更新実行直前にパスワード再確認を要求し、POST + CSRF + レート制限を適用する。

## リリース成果物の変更

新規インストールと更新で同じ ZIP を使用する。

- `cuckooremind-{version}.zip` の一つだけを公開する。
- 現在どおり、新規インストールに必要な初期 `.env` と初期 SQLite DB を含める。
- 更新時は manifest の配置ポリシーに従い、既存の `.env`、`storage/**`、利用者 DB、ログ、アップロードを上書き・削除しない。
- サーバー側で Composer や npm を実行せずに済むよう、`vendor/` とビルド済み `public/build/` を含める。
- `.git`、開発用テスト、不要なソース成果物は除外する。
- ルートに `update-manifest.json` を含め、新規配置用ファイルと更新対象ファイルを判別できるようにする。

`update-manifest.json` には最低限、次を記録する。

```json
{
  "schema": 1,
  "version": "v0.0.3",
  "minimum_upgradable_version": "v0.0.1",
  "minimum_updater_version": "v0.0.3",
  "minimum_php": "8.2.0",
  "files": {
    "app/Helpers.php": "sha256:..."
  },
  "install_only": [".env", "storage/db.sqlite"],
  "preserve": [".env", "storage/"],
  "remove": [],
  "migrate": true
}
```

配置ポリシーは次のように扱う。

- `files`: 新規インストール・更新の両方で配布する管理対象コード。更新時は置換する。
- `install_only`: 新規インストール時だけ配置する。更新時は存在の有無にかかわらず配布物からコピーしない。
- `preserve`: 更新時に配下を上書き・削除しない永続領域。
- `remove`: 過去の対応対象バージョンから削除すべき、既知の管理対象ファイル。利用者が追加した未知のファイルは削除しない。

`minimum_upgradable_version` から最新版への直接更新を保証対象とする。`minimum_updater_version` は manifest schema や更新エンジンが要求する最低バージョンを表す。現在版が条件を満たさない場合は無理に更新せず、必要な中間バージョンまたは手動更新手順を案内する。

Laravel の migration は未実行分が順にすべて適用されるため、例えば `v0.0.1` から `v0.0.3` へ直接更新する場合も `v0.0.2` と `v0.0.3` で追加された migration を順番に実行する。リリース ZIP を一つにすることに加え、この累積 migration、manifest の後方互換性、CI の飛び越し更新テストによってバージョン間の不一致を防止する。

画面更新機能がまだ存在しないバージョンから、画面更新機能を初めて搭載するバージョンへの移行だけは画面から開始できない。この初回に限り、従来手順による手動更新または専用の導入手順を案内する。

GitHub Actions では ZIP の SHA-256 を計算し、`checksums.txt` も Release asset として公開する。アプリは GitHub API が返す asset digest、または `checksums.txt` とダウンロード内容を照合する。CI 内で以下も検証する。

- `.version` とタグ名と manifest の version が一致する。
- ZIP 内の `.env` と初期 DB が `install_only` および `preserve` に指定され、更新対象に入っていない。
- ZIP 内に実利用データ、`.git`、シンボリックリンク、絶対パス、`../` を含まない。
- manifest 記載ファイルのハッシュが一致する。
- ZIP による新規インストールと、サポート対象の最古版および各既存リリースから最新版への直接更新テストが成功する。

## アプリケーション構成案

### 主なクラス

- `GitHubReleaseClient`: GitHub Releases API 呼び出し、タイムアウト、User-Agent、レスポンス検証
- `ReleaseRepository`: キャッシュ、安定版の絞り込み、セマンティックバージョン順の整列
- `UpdatePreflightService`: PHP/拡張/空き容量/書込権限/DB 接続/manifest の検査
- `UpdatePackageService`: ダウンロード、SHA-256 検証、安全な展開
- `ApplicationUpdater`: ロック、バックアップ、ファイル反映、Artisan 処理、復旧のオーケストレーション
- `UpdateStateStore`: `storage/app/updates/state.json` とログの読み書き
- `SystemUpdateController`: 一覧、再確認、実行、状態取得
- `UpdateApplication` Artisan コマンド: 画面と同じサービスを使用する CLI の復旧経路

GitHub リポジトリ URL、API URL、タイムアウト、キャッシュ時間、機能の有効・無効、任意の GitHub token は `config/update.php` と環境変数で設定可能にする。公開リポジトリなので token は必須にしない。

### 永続ファイル

更新によって変更・削除しない対象を明示する。

- `.env`
- `storage/**`（DB、ログ、セッション、アップロード、更新状態を含む）
- 運用環境固有として設定した追加パス

更新後に不要になった旧コードを安全に削除するため、適用済み manifest を `storage/app/updates/installed-manifest.json` に保存する。次回更新時は「旧 manifest にあり、新 manifest にない管理対象ファイル」と、新 manifest の累積的な `remove` に記載されたファイルだけを削除する。旧 manifest がないバージョンからの初回更新では `remove` だけを使い、利用者が追加した未知のファイルは削除しない。

## 更新フロー

1. システム管理者が更新画面を開き、「更新を確認」を実行する。
2. アプリが GitHub Releases API から最新の非 draft・非 prerelease リリースを取得する。
3. `.version` とタグを `version_compare` 用に正規化し、最新版の方が新しい場合だけ更新を提示する。
4. 管理者がリリースノートと注意事項を確認し、パスワード再確認後に更新を実行する。
5. 排他ロックを取得し、二重実行を防止する。
6. 事前診断を行う。
   - PHP バージョンと `ZipArchive`、cURL または HTTP client
   - アプリルート、`storage`、一時ディレクトリの書込権限
   - ZIP の 3 倍を目安にした空き容量
   - DB 接続、保留中マイグレーション、更新中でないこと
7. リリース ZIP を `storage/app/updates/downloads/` に保存し、SHA-256 を照合する。
8. ZIP の全 entry を検査し、Zip Slip、絶対パス、シンボリックリンク、想定外ファイルを拒否する。
9. `storage/app/updates/staging/{version}/` に展開し、manifest と全ファイルのハッシュを再検証する。
10. 更新対象コードと SQLite DB を `storage/app/updates/backups/{timestamp}/` に退避する。
11. Laravel をメンテナンスモードにする。
12. manifest の配置ポリシーで更新対象を選別する。`install_only` と `preserve` を除外し、新しい管理対象ファイルを一時名で配置後に rename する。旧 manifest と累積 `remove` だけを根拠に不要ファイルを削除し、永続対象は触らない。
13. 次を順に実行する。
    - `optimize:clear`
    - `migrate --force`
    - `storage:link`（未作成時のみ）
    - `config:cache`、`route:cache`、`view:cache`（対応可能なもの）
14. `.version`、DB 接続、主要クラスのロードをヘルスチェックする。
15. 成功時は適用済み manifest と状態を保存し、メンテナンスモードとロックを解除する。
16. 失敗時はログを保存してコードをバックアップから戻す。SQLite は更新前 DB も復元し、メンテナンスモードを解除する。復旧自体に失敗した場合はメンテナンスモードを維持し、CLI の復旧手順を表示する。

Web リクエスト切断時も処理を続けられるよう `ignore_user_abort(true)` と十分な実行時間を設定する。ただしホスティング側の強制タイムアウトは回避できないため、CLI が利用できる環境では Artisan コマンドを推奨経路とし、画面には常に状態ファイルを基にした結果を表示する。

## DB とロールバックの方針

- SQLite はメンテナンスモード移行後に DB ファイルをコピーし、失敗時に自動復元する。
- マイグレーション適用後の完全な自動ダウングレードは保証しない。各リリースの DB 変更は少なくとも一つ前のアプリと互換性を保つ expand/contract 方式を原則とする。
- バックアップは保存数または保存期間を設定し、機密情報を含むため Web 公開領域から直接取得できない場所に置く。

## セキュリティ要件

- Release asset の URL は GitHub の許可済みホストか検証し、任意 URL のダウンロードを禁止する（SSRF 対策）。
- HTTP リダイレクト後のホストも検証する。
- HTTPS の証明書検証を無効化しない。
- サイズ上限、接続・読込タイムアウトを設定する。
- SHA-256、manifest、バージョンの全てが一致しない限り展開しない。
- ZIP entry のパスと種類を展開前に検査する。
- 更新ログに token、`.env`、DB 内容を出力しない。
- リリースノートの HTML をサニタイズする。
- 更新ルートはシステム管理者、パスワード再確認、CSRF、レート制限で保護する。

## エラー時の利用者体験

- GitHub API に接続できない: 最終取得日時とキャッシュ済み情報を表示し、アプリ利用は継続する。
- 書込権限・容量不足: 更新開始前に対象パスと必要な対応を表示する。
- checksum 不一致・不正 ZIP: 一切反映せず、ダウンロードを削除して監査ログに残す。
- migration 失敗: 自動復旧結果とログ ID、CLI の復旧コマンドを表示する。
- 更新処理が中断: 次回アクセス時にロックの時刻と状態を確認し、管理者だけが安全確認後に再開または復旧できるようにする。

## 実装フェーズ

### Phase 1: 配布形式とバージョン規約

- [x] `.version`、Git tag、manifest のバージョン規約を定義
- [x] 新規インストール・更新兼用 ZIP と配置ポリシー付き manifest を生成
- [x] manifest schema の後方互換性と最低 updater version の規約を定義
- [x] `update-manifest.json` と `checksums.txt` を生成
- [x] Release workflow に成果物検証を追加
- [x] サポート対象の最古版および各既存リリースから最新版への直接更新を CI で検証

### Phase 2: リリース確認と更新履歴 UI

- [x] `config/update.php` と GitHub Releases client を追加
- [x] API レスポンスのキャッシュと障害時フォールバックを追加
- [x] バージョン比較、draft/prerelease 除外を実装
- [x] システム管理者権限とポリシーを追加
- [x] 更新通知、更新履歴、手動再確認画面を追加
- [x] Markdown の安全な表示を実装

### Phase 3: 更新エンジン

- [x] preflight、排他ロック、状態管理を実装
- [x] 安全な download/checksum/extract を実装
- [x] 永続パス除外、コード・SQLite バックアップを実装
- [x] ファイル反映、旧ファイル削除、Artisan 処理を実装
- [x] 成功時処理とベストエフォート復旧を実装
- [x] `app:update`、`app:update:status`、`app:update:restore` コマンドを追加

### Phase 4: 画面実行と運用整備

- [x] パスワード再確認付き更新 POST を追加
- [x] 状態ポーリング、完了・失敗画面を追加
- [x] 監査ログとバックアップ世代管理を追加
- [x] レンタルサーバー向け権限設定・手動復旧手順を文書化
- [x] 更新を無効化する環境変数を追加

## テスト計画

### Unit

- バージョン比較（`v` prefix、同一版、古い版、pre-release、不正値）
- GitHub API レスポンスの変換、キャッシュ、タイムアウト
- asset URL の許可判定と checksum 検証
- ZIP path traversal、絶対パス、symlink、サイズ超過の拒否
- manifest 差分による削除対象と永続対象の判定

### Feature

- 一般ユーザー・チーム管理者が更新画面と実行 API にアクセスできない
- システム管理者だけが閲覧・実行できる
- CSRF、パスワード再確認、二重実行防止
- 新版あり・最新版・API 障害時の各表示
- リリースノート内の危険な HTML が無害化される

### Integration / CI

- 同じ ZIP で新規インストールできる
- サポート対象の最古版および各既存リリースから最新版へ直接更新できる
- `v0.0.1` から `v0.0.3` のような飛び越し更新で、途中の migration も順に適用される
- 更新時に既存ユーザー・reminder・`.env`・SQLite DB・アップロードが保持される
- migration を伴う更新が完了する
- download、checksum、展開、copy、migration の各地点で意図的に失敗させ、復旧とロック解除を確認する
- 更新後にログイン、Dashboard、reminder CRUD、cron API が動作する
- CLI が使えない想定の PHP-only 経路を確認する

## 受け入れ条件

- 新しい安定版公開後、キャッシュ時間以内または手動確認直後に画面へ通知される。
- 現在版と複数リリース分の更新履歴が読める。
- 権限のないユーザーは更新情報の管理画面および更新実行にアクセスできない。
- 正常更新後に `.version` が最新版となり、既存の `.env`、ユーザー、reminder、アップロードが維持される。
- checksum 不一致または不正 ZIP はアプリコードへ一切反映されない。
- 更新の多重実行が防止され、成功・失敗の状態とログが管理画面で確認できる。
- SQLite 環境では migration 失敗時に更新前 DB とコードへ復旧できる。
- GitHub API 障害や更新失敗が、通常の reminder 閲覧・通知送信を恒常的に妨げない。

## 実装前に確定する事項

1. 利用中ホスティングで PHP の実行時間延長、`ZipArchive`、外部 HTTPS、ファイル rename が利用可能か。
2. 更新中の許容停止時間と、CLI を優先できる環境の割合。
3. GitHub Release を信頼基点とした SHA-256 検証で開始するか、署名付き manifest まで初回から導入するか。
4. システム管理者を「最初のユーザー」とする移行方針で問題ないか。
