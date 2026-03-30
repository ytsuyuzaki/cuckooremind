<p align="center">
<a href="https://muchuu.net/cuckooremind/" target="_blank">
<img src="https://raw.githubusercontent.com/ytsuyuzaki/cuckooremind/main/resources/images/logo.png" width="400" />
</a>
</p>

## CuckooRemindについて

CuckooRemind はセルフホスティング型の通知アプリケーションです。

定期的な通知を自分自身で管理することで企業による方針に左右されない使い勝手を提供します。通知内容の管理や送信を提供し、通知先をメールなどの様々なクライアントに送信することができます。

目の前の事に集中するために、後から思い出したい事は CuckooRemind で管理しましょう。

## 使い方

CuckooRemind は Laravelフレームワークを使用しているのでPHPの実行環境が必要です。LAMP環境やレンタルサーバーに設置することで使用する事ができます。

ユーザー登録をする必要があるため、メール送信環境が必須になります。

Laravelフレームワークに精通している方であれば このリポジトリを git clone することで使用開始できます。レンタルサーバーには配布パッケージをダウンロード、使用したいドメインのドキュメントルート配下に設置してドメインアクセスすることでセットアップする事ができます。

使用方法の詳細については [ドキュメント](https://muchuu.net/cuckooremind/document/) を参照してください。

## コントリビュート

本プロジェクトはオープンソースプロジェクトなためコントリビュートを歓迎します。

### テストカバレッジ

未テストの箇所を確認したい場合は、HTML と Clover 形式のカバレッジを生成できます。

```bash
composer test:coverage
```

生成物は `coverage/` 配下に出力されます。

- `coverage/html/index.html`: ブラウザで確認できる HTML レポート
- `coverage/clover.xml`: CI 連携などに使いやすい XML レポート

カバレッジの生成には PHP の `Xdebug` もしくは `PCOV` 拡張が必要です。未導入の環境では、`composer test:coverage` 実行時に案内メッセージを表示します。

## セキュリティの脆弱性

セキュリティの脆弱性を発見した場合は、プルリクエストや[お問い合わせフォーム](https://muchuu.net/contact/) 経由で 送信してください。 すべてのセキュリティの脆弱性は直ちに対処されます。

## ライセンス

このプロジェクトは MITライセンスに基づいて公開されています。詳細については、LICENSE.txt ファイルを参照してください。
