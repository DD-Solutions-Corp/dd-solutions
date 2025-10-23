# DD Solutions テーマ高速化キット（リポジトリ不要版）
最終更新: 2025-10-19

このフォルダを **WordPressの現在のテーマ直下** に上書き配置してください（`functions.php` は内容を統合するか、バックアップ後に置換）。

## できること（このキット）
- ページごとの CSS / JS を **自動で条件読み込み**（存在する場合のみ）
- 画像の最適化：`<picture>` で **AVIF/WebP優先**, `<img loading="lazy" decoding="async">`
- LCP対策：ヒーロー1枚目を `fetchpriority="high"` で出力するヘルパー
- クリティカルCSSの **自動インライン**（`assets/css/critical/`にファイルがある場合）
- ヒーロー画像の軽量スライダー（依存なしのバニラJS）

## 配置先
```
/your-theme/
  functions.php（本キットの内容を追記/統合）
  /assets/
    /js/
      hero-rotator.js
      /pages/      ← page-<slug>.js（任意）
    /css/
      /components/ ← hero.css など
      /dist/       ← global.min.css（共通最小）
      /pages/      ← page-<slug>.css（任意）
      /critical/   ← front.css / page-<slug>.css など（任意）
    /images/       ← hero/kv-01.(avif|webp|jpg) など
```

## 使い方
1. **functions.php** をテーマに反映（バックアップ必須）。
2. トップページのヒーローHTML例（`front-page.php` など）で：
   ```php
   <section class="hero">
     <div class="js-hero-rotator" data-interval="5000" aria-label="注目コンテンツ">
       <div class="hero-slide">
         <?php dd_lcp_image(1234, 'full', ['class'=>'hero-img']); ?>
       </div>
       <div class="hero-slide">
         <?php dd_picture_sources('hero/kv-02', 'DD Solutionsの強み', 'hero-img'); ?>
       </div>
       <div class="hero-slide">
         <?php dd_picture_sources('hero/kv-03', '導入事例', 'hero-img'); ?>
       </div>
     </div>
   </section>
   ```
   - **1枚目**は `dd_lcp_image()` で LCP強化
   - **2枚目以降**は `dd_picture_sources()` で AVIF/WebP優先

3. **ページ別CSS/JS**：
   - ページのスラッグが `about` なら `assets/css/pages/page-about.css` と `assets/js/pages/page-about.js` を置けば **自動で読み込み** されます。

4. **クリティカルCSS**：
   - `assets/css/critical/front.css` や `assets/css/critical/page-about.css` を置くと、`<head>` 内に自動インライン化します（存在しない場合は何もしません）。

## よくある質問
- **既存のfunctions.phpが大きい** → 競合しないよう「バージョン関数名」「ヘルパー名」を調整してください。
- **GutenbergのCSSを外したい** → `wp_dequeue_style('wp-block-library')` のコメントアウトを外します。
- **画像フォーマットの自動生成** → サーバー環境によりAVIF生成が難しい場合があります。最小限としてWebP変換のみでも可。

## 旧/新ドメイン対応の進め方（手作業ベース）
1. 旧サイト（http://dd-sol.com）の各主要ページを開き、ブラウザのDevToolsで **必要なCSSセレクタ** を抽出。
2. 新サイト（https://dd-solutions.jp）の同一ページを開き、**レイアウト差分** を確認（375/768/1280px）。
3. 本キットの **page-<slug>.css** にピンポイントの差分を反映し、グローバルに不要なCSSを **削除/共通化**。
4. 画像は `assets/images/hero/...` に **同名で拡張子違い** を用意（`kv-01.avif, kv-01.webp, kv-01.jpg`）。
5. Lighthouseで **Performance≥90 / CLS<0.1 / LCP<2.5s** を目標に微修正。

## 免責
このキットは雛形です。必ずステージング環境で検証し、バックアップを取ってから本番に反映してください。
