<?php
/**
 * 管理画面機能
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class SeoOne_Admin {

  public static function init(){
    add_action( 'admin_menu', array(__CLASS__,'register_menu') );
    add_action( 'admin_init', array(__CLASS__,'register_settings') );
    add_action( 'admin_post_seoone_generate_article', array(__CLASS__,'handle_generate_article') );
    add_action( 'admin_post_seoone_export_metrics', array(__CLASS__,'handle_export_metrics') );

    // 独自の設定保存ハンドラーは廃止し、WordPress設定APIに任せます。
    // add_action( 'admin_post_seoone_save_settings', array(__CLASS__, 'handle_save_settings' ) );

    // ダークモード用スタイル
    add_action( 'admin_head', array(__CLASS__, 'maybe_print_dark_mode') );

    // 自動生成のスケジュールを初期化・更新します。init 時点で設定を確認し必要に応じて WP Cron を登録します。
    add_action( 'init', array( __CLASS__, 'maybe_schedule_auto_generation' ) );
    // 自動生成イベントのフック。WP Cron によって呼び出される処理を登録します。
    add_action( 'seoone_auto_generate_event', array( __CLASS__, 'auto_generate_articles' ) );
  }

  public static function register_menu(){
    // トップレベルメニューではハブを表示します
    add_menu_page(
        'SEO ONE',
        'SEO ONE',
        SEOONE_CAP,
        'seoone',
        array( 'SeoOne_Hub', 'render_page_static' ),
        'dashicons-chart-line',
        80
    );
    // 設定ページはハブのタブから利用しますが、オプション保存が正しく機能するようにサブメニューとして登録も残しておきます。
    add_submenu_page(
        'seoone',
        '設定',
        '設定',
        SEOONE_CAP,
        'seoone-settings',
        array( __CLASS__, 'render_settings_page' )
    );

    // 記事リライトページを追加
    add_submenu_page(
        'seoone',
        '記事リライト',
        '記事リライト',
        SEOONE_CAP,
        'seoone-rewrite',
        array( __CLASS__, 'render_rewrite_page' )
    );
  }

  public static function register_settings(){
    register_setting('seoone_options','seoone_settings', array(__CLASS__,'sanitize_settings'));

    add_settings_section('seoone_ai', 'AIの設定', function(){
      echo '<p>OpenRouter APIキー、既定モデル、プロンプトプロファイル等を設定します。</p>';
    }, 'seoone-settings');

    add_settings_field('seoone_ai_api_key','OpenRouter APIキー', function(){
      $opt = get_option('seoone_settings',array());
      $v = $opt['ai_api_key'] ?? '';
      printf('<input type="text" name="seoone_settings[ai_api_key]" value="%s" class="regular-text" placeholder="sk-...">', esc_attr($v));
      echo '<p class="description">OpenRouter (または互換API) のキーを入力します。このキーがないとAI機能は動作しません。開発者ダッシュボード等で取得した「sk-」から始まる文字列を入力してください。</p>';
    }, 'seoone-settings','seoone_ai');

    add_settings_field('seoone_ai_model','既定モデル', function(){
      $opt = get_option('seoone_settings',array());
      $v = $opt['ai_model'] ?? '';
      // モデル候補一覧。必要に応じて追加してください。
      // モデル候補一覧と簡易説明。コストはおおよその米ドル換算で、外部情報を参考にしています。
      $models = array(
        '' => 'デフォルト (gpt‑4o 高精度/高速)',
        'gpt-4o' => 'gpt‑4o (高精度・高速, 約$3/$10)',
        'gpt-4'  => 'gpt‑4 (高精度・高価格, 約$10/$30)',
        'gpt-3.5-turbo' => 'gpt‑3.5 (低価格・高速, 約$0.5/$1.5)',
        'claude-3-sonnet' => 'Claude Sonnet (バランス, 約$3/$15)',
        'claude-3-opus'   => 'Claude Opus (最高性能, 約$15/$75)',
        'mistral-7b-instruct' => 'Mistral 7B (激安, 約$0.03/$0.05)',
        // 新しく追加した情報取得向けモデル
        'perplexity'        => 'Perplexity (検索連携, 約$0.5/$1.5)',
        'perplexity-sonar'  => 'Perplexity Sonar (強化された検索連携)',
        'gemini'            => 'Gemini Pro (Google製, 約$1/$4)',
        'gemini-2.5-flash'  => 'Gemini Flash (高速版)'
      );
      echo '<select name="seoone_settings[ai_model]" class="regular-text">';
      foreach ( $models as $key => $label ) {
        printf('<option value="%s" %s>%s</option>', esc_attr($key), selected($v, $key, false), esc_html($label));
      }
      echo '</select>';
      echo '<p class="description">全ステップ共通で使用するAIモデルを指定します。空欄の場合はデフォルトの <code>gpt-4o</code> が使われます。ドロップダウンから選択してください。</p>';
    }, 'seoone-settings','seoone_ai');

    // 各ステップのモデル指定。オンボーディングの設定と同じフィールドを提供します。
    $step_fields = array(
        'ai_model_retrieval' => '情報取得ステップのモデル',
        'ai_model_draft'     => '初稿ステップのモデル',
        'ai_model_coding'    => 'コーディングステップのモデル',
        'ai_model_scoring'   => '採点ステップのモデル',
        'ai_model_fact'      => 'ファクトチェックステップのモデル',
    );
    foreach ( $step_fields as $field_key => $label ) {
        add_settings_field( 'seoone_' . $field_key, $label, function() use ( $field_key ) {
            $opt = get_option('seoone_settings', array());
            $v = $opt[ $field_key ] ?? '';
            // モデル候補一覧。既定モデル用に空欄オプションを追加します。
            $models = array(
              '' => '既定モデルを使用',
              'gpt-4o' => 'gpt‑4o 高精度/高速',
              'gpt-4'  => 'gpt‑4 高精度/高価格',
              'gpt-3.5-turbo' => 'gpt‑3.5 低価格/高速',
              // 最新 Anthropic モデル。Sonnet 3/Opus 3 は旧モデルとして扱います。
              'claude-sonnet-4.5' => 'Claude Sonnet 4.5 (バランス)',
              'claude-opus-4.1'   => 'Claude Opus 4.1 (最高性能)',
              'claude-3-sonnet'   => 'Claude Sonnet 3 (旧)',
              'claude-3-opus'     => 'Claude Opus 3 (旧)',
              'mistral-7b-instruct' => 'Mistral 7B 激安',
              // 追加: Perplexity および Gemini 系列
              'perplexity'       => 'Perplexity 検索連携',
              'perplexity-sonar' => 'Perplexity Sonar 強化検索',
              'gemini'           => 'Gemini Pro (Google)',
              'gemini-2.5-flash' => 'Gemini Flash 高速版',
              // 最新の OpenAI モデル
              'gpt-5-pro'        => 'GPT‑5 Pro (OpenAI)',
              'gpt-5-image'      => 'GPT‑5 Image (画像生成)'
            );
            echo '<select name="seoone_settings[' . esc_attr( $field_key ) . ']" class="regular-text">';
            foreach ( $models as $key => $label ) {
              printf('<option value="%s" %s>%s</option>', esc_attr($key), selected($v, $key, false), esc_html($label));
            }
            echo '</select>';
            echo '<p class="description">ステップ別にモデルを指定します。未選択の場合は既定モデルが使われます。</p>';
        }, 'seoone-settings','seoone_ai');
    }

    add_settings_section('seoone_display', '表示/画像', function(){
      echo '<p>目次・読了時間・画像最適化などの表示制御。</p>';
    }, 'seoone-settings');

    // メトリクス設定
    add_settings_section('seoone_metrics', 'メトリクス設定', function(){
      echo '<p>GA4およびSearch Consoleデータ取得に使用する設定を入力します。サービスアカウントキーのパスはサーバー内のファイルシステム上の絶対パスを指定してください。</p>';
    }, 'seoone-settings');
    add_settings_field('seoone_ga4_property_id','GA4 プロパティID', function(){
      $opt = get_option('seoone_settings', array());
      $v = $opt['ga4_property_id'] ?? '';
      printf('<input type="text" name="seoone_settings[ga4_property_id]" value="%s" class="regular-text" placeholder="例：G-XXXXXXXXXX">', esc_attr($v));
      echo '<p class="description">Google Analytics 4 の<strong>計測ID</strong>を入力します。GA4 管理画面の「管理 → データストリーム → 対象ストリーム → 測定ID」で確認できる <code>G-</code> から始まる英数字です。</p>';
    }, 'seoone-settings','seoone_metrics');
    add_settings_field('seoone_gsc_site_url','Search Console サイトURL', function(){
      $opt = get_option('seoone_settings', array());
      $v = $opt['gsc_site_url'] ?? '';
      printf('<input type="text" name="seoone_settings[gsc_site_url]" value="%s" class="regular-text" placeholder="https://example.com">', esc_attr($v));
      echo '<p class="description">Google Search Console に登録された<strong>URL プレフィックス プロパティ</strong>の URL を入力します。例: <code>https://example.com</code>。プロパティ追加時の URL と完全一致させてください。</p>';
    }, 'seoone-settings','seoone_metrics');
    add_settings_field('seoone_service_account_path','サービスアカウントJSONパス', function(){
      $opt = get_option('seoone_settings', array());
      $v = $opt['service_account_path'] ?? '';
      printf('<input type="text" name="seoone_settings[service_account_path]" value="%s" class="regular-text" placeholder="/path/to/key.json">', esc_attr($v));
      echo '<p class="description">Google API サービスアカウントのJSONファイルがサーバー上にある場合、その絶対パスを入力します。外部からアクセスできない安全な場所に保存し、ここにパスを設定してください。</p>';
    }, 'seoone-settings','seoone_metrics');

    // メトリクス取得間隔
    add_settings_field('seoone_metrics_interval','メトリクス取得間隔', function(){
      $opt = get_option('seoone_settings', array());
      $current = $opt['metrics_interval'] ?? 'daily';
      $options = array(
        'hourly'     => '1時間ごと',
        'twicedaily' => '12時間ごと',
        'daily'      => '1日ごと'
      );
      echo '<select name="seoone_settings[metrics_interval]">';
      foreach ( $options as $key => $label ) {
        printf('<option value="%s" %s>%s</option>', esc_attr($key), selected($current, $key, false), esc_html($label));
      }
      echo '</select>';
      echo '<p class="description">GSC/GA4 メトリクスを取得する頻度を選択します。</p>';
    }, 'seoone-settings','seoone_metrics');

    // 日次メールレポート
    add_settings_field('seoone_send_email','日次メールレポート', function(){
      $opt = get_option('seoone_settings', array());
      $v = !empty($opt['send_email']);
      printf('<label><input type="checkbox" name="seoone_settings[send_email]" value="1" %s> 管理者へ日次メトリクスレポートをメール送信</label>', checked($v,true,false));
      echo '<p class="description">チェックすると、最新のメトリクスを毎日メールで管理者へ送信します。</p>';
    }, 'seoone-settings','seoone_metrics');

    add_settings_field('seoone_display_toc','目次を表示', function(){
      $opt = get_option('seoone_settings',array());
      $v = !empty($opt['display_toc']);
      printf('<label><input type="checkbox" name="seoone_settings[display_toc]" value="1" %s> 固定目次＋サイド目次</label>', checked($v,true,false));
    }, 'seoone-settings','seoone_display');

    add_settings_field('seoone_img_convert','画像変換をプラグインで行う', function(){
      $opt = get_option('seoone_settings',array());
      $v = !empty($opt['display_image_conversion']);
      printf('<label><input type="checkbox" name="seoone_settings[display_image_conversion]" value="1" %s> AVIF/WebP/JPG 変換</label>', checked($v,true,false));
    }, 'seoone-settings','seoone_display');

    // --- Pexels API 設定 ---
    add_settings_section('seoone_pexels', 'Pexels API設定', function(){
      echo '<p>Pexels APIキーを入力すると、記事生成時にPexelsからフリー画像を自動取得して挿入します。</p>';
    }, 'seoone-settings');
    // Pexels APIキー入力欄
    add_settings_field('seoone_pexels_api_key','Pexels APIキー', function(){
      $opt = get_option('seoone_settings',array());
      $v = $opt['pexels_api_key'] ?? '';
      printf('<input type="text" name="seoone_settings[pexels_api_key]" value="%s" class="regular-text" placeholder="pexels_api_key">', esc_attr($v));
      echo '<p class="description">PexelsのAPIキーを入力してください。<a href="https://www.pexels.com/api/" target="_blank">Pexels API</a>から取得できます。</p>';
    }, 'seoone-settings','seoone_pexels');
    // Pexels 画像使用スイッチ
    add_settings_field('seoone_use_pexels','Pexels画像を使用', function(){
      $opt = get_option('seoone_settings',array());
      $v = !empty($opt['use_pexels']);
      printf('<label><input type="checkbox" name="seoone_settings[use_pexels]" value="1" %s> 記事生成時にPexelsから画像を挿入する</label>', checked($v,true,false));
      echo '<p class="description">チェックすると、各H2の画像説明に合わせてPexels APIで画像を検索し挿入します。APIキーが空の場合は挿入しません。</p>';
    }, 'seoone-settings','seoone_pexels');

    // --- 地域設定 ---
    add_settings_section('seoone_local', '地域設定', function(){
      echo '<p>参考文献の地域や具体例を優先するための地域名（都道府県や市区町村）を指定します。</p>';
    }, 'seoone-settings');
    add_settings_field('seoone_region','地域名', function(){
      $opt = get_option('seoone_settings',array());
      $v = $opt['region'] ?? '';
      printf('<input type="text" name="seoone_settings[region]" value="%s" class="regular-text" placeholder="例：埼玉県熊谷市">', esc_attr($v));
      echo '<p class="description">例：埼玉県熊谷市。空の場合は全国レベルの情報を使用します。</p>';
    }, 'seoone-settings','seoone_local');

    // ダークモード
    add_settings_field('seoone_dark_mode','ダークモード', function(){
      $opt = get_option('seoone_settings', array());
      $v = ! empty( $opt['dark_mode'] );
      printf('<label><input type="checkbox" name="seoone_settings[dark_mode]" value="1" %s> 管理画面（SEO ONE）のダークモードを有効にする</label>', checked($v,true,false));
    }, 'seoone-settings','seoone_display');

    // 構造化データ出力
    add_settings_field('seoone_display_schema','構造化データ（Article）を出力', function(){
      $opt = get_option('seoone_settings',array());
      $v = !empty($opt['display_schema']);
      printf('<label><input type="checkbox" name="seoone_settings[display_schema]" value="1" %s> 投稿記事にArticle schema JSON-LDを出力</label>', checked($v,true,false));
    }, 'seoone-settings','seoone_display');

    // 記事生成設定とペルソナ管理のUIは記事生成タブに集約したため、設定タブでは登録しません。

    // 追加ペルソナ(JSON)フィールドは廃止しました

    // ペルソナ入力欄の動的追加/削除スクリプトを管理画面フッターに出力します。
    add_action('admin_footer', array(__CLASS__, 'print_persona_js'));
  }

  public static function sanitize_settings($in){
    $out = array();
    $out['ai_api_key'] = sanitize_text_field( $in['ai_api_key'] ?? '' );
    $out['ai_model']   = sanitize_text_field( $in['ai_model'] ?? '' );

    // 各ステップのモデル指定を保存します。空欄の場合でもキーは存在させます。
    $step_fields = array(
        'ai_model_retrieval',
        'ai_model_draft',
        'ai_model_coding',
        'ai_model_scoring',
        'ai_model_fact'
    );
    foreach ( $step_fields as $field_key ) {
        $out[ $field_key ] = sanitize_text_field( $in[ $field_key ] ?? '' );
    }
    $out['display_toc'] = !empty($in['display_toc']) ? 1 : 0;
    $out['display_image_conversion'] = !empty($in['display_image_conversion']) ? 1 : 0;
    $out['display_schema'] = !empty($in['display_schema']) ? 1 : 0;

    // GA4/GSC 設定
    $out['ga4_property_id']   = sanitize_text_field( $in['ga4_property_id'] ?? '' );
    $out['gsc_site_url']      = sanitize_text_field( $in['gsc_site_url'] ?? '' );
    $out['service_account_path'] = sanitize_text_field( $in['service_account_path'] ?? '' );

    // メトリクス取得間隔
    $allowed_intervals = array( 'hourly', 'twicedaily', 'daily' );
    $interval = $in['metrics_interval'] ?? 'daily';
    if ( ! in_array( $interval, $allowed_intervals, true ) ) {
        $interval = 'daily';
    }
    $out['metrics_interval'] = $interval;

    // メール送信
    $out['send_email'] = ! empty( $in['send_email'] ) ? 1 : 0;
    $out['dark_mode'] = ! empty( $in['dark_mode'] ) ? 1 : 0;

    // Pexels API キーと使用フラグ
    $out['pexels_api_key'] = sanitize_text_field( $in['pexels_api_key'] ?? '' );
    $out['use_pexels']     = ! empty( $in['use_pexels'] ) ? 1 : 0;

    // 地域
    $out['region'] = sanitize_text_field( $in['region'] ?? '' );

    // 既定キーワードと自動生成設定
    $out['default_keywords'] = sanitize_text_field( $in['default_keywords'] ?? '' );
    $out['auto_generate_count'] = isset( $in['auto_generate_count'] ) ? intval( $in['auto_generate_count'] ) : 0;
    // 自動生成時刻は "HH:MM" の単一指定、カンマ区切り、または範囲指定 "HH:MM-HH:MM" を許可します。
    // 例: "06:00,14:00,22:00" または "08:00-20:00"。その他の形式は保存しません。
    if ( isset( $in['auto_generate_time'] ) ) {
        $raw_time = trim( (string) $in['auto_generate_time'] );
        $pattern = '/^\s*(\d{1,2}:\d{2}\s*(,\s*\d{1,2}:\d{2}\s*)*|\d{1,2}:\d{2}\s*-\s*\d{1,2}:\d{2})\s*$/';
        if ( preg_match( $pattern, $raw_time ) ) {
            $out['auto_generate_time'] = sanitize_text_field( $raw_time );
        } else {
            $out['auto_generate_time'] = '';
        }
    } else {
        $out['auto_generate_time'] = '';
    }

    // ペルソナプロファイルを保存（配列形式）。空欄のエントリは除外し、各値をサニタイズします。
    if ( isset( $in['persona_profiles'] ) && is_array( $in['persona_profiles'] ) ) {
        $profiles_clean = array();
        foreach ( $in['persona_profiles'] as $p ) {
            // 全ての値が空の場合はスキップ
            $all_empty = true;
            $entry = array();
            // 名前
            if ( isset( $p['name'] ) && $p['name'] !== '' ) {
                $entry['name'] = sanitize_text_field( $p['name'] );
                $all_empty = false;
            }
            // 職業(役職)
            if ( isset( $p['role'] ) && $p['role'] !== '' ) {
                $entry['role'] = sanitize_text_field( $p['role'] );
                $all_empty = false;
            }
            // 重み
            if ( isset( $p['weight'] ) && $p['weight'] !== '' ) {
                $entry['weight'] = floatval( $p['weight'] );
                $all_empty = false;
            }
            // 性別
            if ( isset( $p['gender'] ) && $p['gender'] !== '' ) {
                $entry['gender'] = sanitize_text_field( $p['gender'] );
                $all_empty = false;
            }
            // 年齢
            if ( isset( $p['age'] ) && $p['age'] !== '' ) {
                $entry['age'] = sanitize_text_field( $p['age'] );
                $all_empty = false;
            }
            // 性格
            if ( isset( $p['character'] ) && $p['character'] !== '' ) {
                $entry['character'] = sanitize_text_field( $p['character'] );
                $all_empty = false;
            }
            // 好きなもの
            if ( isset( $p['likes'] ) && $p['likes'] !== '' ) {
                $entry['likes'] = sanitize_text_field( $p['likes'] );
                $all_empty = false;
            }
            // 嫌いなもの
            if ( isset( $p['dislikes'] ) && $p['dislikes'] !== '' ) {
                $entry['dislikes'] = sanitize_text_field( $p['dislikes'] );
                $all_empty = false;
            }
            // 趣味
            if ( isset( $p['hobbies'] ) && $p['hobbies'] !== '' ) {
                $entry['hobbies'] = sanitize_text_field( $p['hobbies'] );
                $all_empty = false;
            }
            // 出身
            if ( isset( $p['origin'] ) && $p['origin'] !== '' ) {
                $entry['origin'] = sanitize_text_field( $p['origin'] );
                $all_empty = false;
            }
            // トピック/キーワード
            if ( isset( $p['topics'] ) && $p['topics'] !== '' ) {
                $entry['topics'] = sanitize_text_field( $p['topics'] );
                $all_empty = false;
            }
            if ( ! $all_empty ) {
                $profiles_clean[] = $entry;
            }
        }
        $out['persona_profiles'] = $profiles_clean;
    }
    // persona_profiles_extra は廃止のため無視します
    // persona_profiles キーが存在しない場合は空配列を格納
    if ( ! isset( $out['persona_profiles'] ) ) {
        $out['persona_profiles'] = array();
    }
    return $out;
  }

  /**
   * ペルソナ入力欄の動的追加/削除用スクリプトを出力します。
   * seoone-settings ページでのみ動作します。
   */
  public static function print_persona_js() {
?>
    <script type="text/javascript">
    jQuery(function($){
      var container = $('#seoone-persona-container');
      var addBtn = $('#seoone-add-persona');
      // 追加ボタン
      addBtn.on('click', function(){
        var entries = container.find('.seoone-persona-entry');
        var count = entries.length;
        var template = entries.first().clone();
        // すべての入力値をクリア
        template.find('input').val('');
        // name 属性のインデックスを書き換え
        template.find('input').each(function(){
          var name = $(this).attr('name');
          name = name.replace(/\[\d+\]/, '[' + count + ']');
          $(this).attr('name', name);
        });
        template.find('strong').text('ペルソナ' + (count + 1));
        container.append(template);
      });
      // 削除ボタン
      container.on('click', '.seoone-remove-persona', function(){
        var entries = container.find('.seoone-persona-entry');
        if ( entries.length <= 1 ) return;
        $(this).closest('.seoone-persona-entry').remove();
        // 再番号付け
        container.find('.seoone-persona-entry').each(function(index){
          $(this).find('strong').text('ペルソナ' + (index + 1));
          $(this).find('input').each(function(){
            var name = $(this).attr('name');
            name = name.replace(/\[\d+\]/, '[' + index + ']');
            $(this).attr('name', name);
          });
        });
      });
    });
    </script>
    <?php
  }

  /**
   * ダークモード用スタイルを出力
   */
  public static function maybe_print_dark_mode() {
    // 現在ページが SEO ONE の管理ページか確認
    if ( ! isset($_GET['page']) || strpos( $_GET['page'], 'seoone' ) !== 0 ) return;
    $opt = get_option('seoone_settings', array());
    if ( empty( $opt['dark_mode'] ) ) return;
    echo '<style>
    /* SEO ONE ダークモード */
    .wrap h1, .wrap h2, .wrap h3, .wrap h4, .wrap h5, .wrap h6 { color: #e0e0e0; }
    .wrap { background-color: #1e1e1e; color: #ddd; }
    .wrap table, .wrap th, .wrap td { border-color: #555; }
    .wrap input[type="text"], .wrap input[type="number"], .wrap input[type="datetime-local"], .wrap select, .wrap textarea {
        background-color: #2a2a2a; color: #eee; border-color: #555;
    }
    .wrap .notice-success { background-color: #2e4e2e; color: #cfc; }
    .wrap .notice-error { background-color: #4e2e2e; color: #fcc; }
    .wrap .button { background-color: #3a3a3a; color: #fff; border-color: #555; }
    .wrap .button:hover { background-color: #555; }
    </style>';
  }

  public static function render_dashboard(){
    echo '<div class="wrap">';
    echo '<h1>SEO ONE ダッシュボード</h1>';
    echo '<p>ようこそ。ここから記事生成や翻訳などの機能をご利用いただけます。</p>';
    // ハブページの機能をこのダッシュボードに統合
    if ( class_exists( 'SeoOne_Hub' ) && method_exists( 'SeoOne_Hub', 'render_page_static' ) ) {
        // タブがネストしてもわかりやすいように、別ラップで出力
        echo '<div class="seoone-dashboard-hub">';
        SeoOne_Hub::render_page_static();
        echo '</div>';
    }
    echo '</div>';
  }

  public static function render_settings_page(){
    // 設定ページの説明とフォームを出力します。ステップごとの概要とモデル比較表を追加しています。
    echo '<div class="wrap"><h1>SEO ONE 設定</h1>';
    // ステップ概要
    echo '<h2>ステップの概要</h2>';
    echo '<p>SEO ONE では次のステップで記事を生成します。必要に応じてオン/オフを切り替えてご利用ください。</p>';
    echo '<ol>';
    echo '<li><strong>競合分析</strong> – 指定したキーワードやタイトルについて、検索上位コンテンツから主要な見出しや不足トピックを抽出します。分析結果は次のリトリーバルステップに参考情報として組み込まれます。</li>';
    echo '<li><strong>情報取得</strong> – 最新の信頼ソースから要点を箇条書きで収集します。出典URLも含まれます。</li>';
    echo '<li><strong>初稿生成</strong> – 要約や目次、本文、FAQ/CTA まで含めたドラフトを作成します。ペルソナやキーワードも反映されます。</li>';
    echo '<li><strong>コーディング</strong> – 見出しIDの付与や冗長表現の削減など、読みやすさを整えます。</li>';
    echo '<li><strong>採点</strong> – 網羅性、日本語品質、SEO、YMYL の４軸で評価し改善点を提示します。</li>';
    echo '<li><strong>ファクトチェック</strong> – 固有名詞や数値の誤りを検証し、安全かつ中立的な表現に修正します。</li>';
    echo '<li><strong>要約</strong> – 完成した記事を 300〜500 字で要約し、メタ情報として保存します。</li>';
    echo '</ol>';

    // ステップごとの推奨モデルと役割を示す表を追加
    echo '<h2>ステップ別おすすめモデル</h2>';
    echo '<p>各ステップで主にどのような処理を行うか、その役割に適したAIモデルの例を示します。処理内容が多いほどトークン消費が増え、長文の記事ほどコストも上がります。以下は目安としてご覧ください。</p>';
    echo '<table class="widefat striped" style="max-width:100%;"><thead><tr><th>ステップ</th><th>役割</th><th>おすすめモデル</th><th>理由</th></tr></thead><tbody>';
    echo '<tr><td>競合分析</td><td>上位ページの見出しや取り扱いトピックをAIが抽出</td><td>gpt‑5‑pro / Claude Sonnet 4.5</td><td>最新モデルによる高精度な分析が推奨。旧モデルでは網羅性が落ちる</td></tr>';
    echo '<tr><td>情報取得</td><td>信頼できる最新情報を箇条書きで収集</td><td>Perplexity / gpt‑5‑pro / Claude Sonnet 4.5</td><td>Perplexityは検索連携で最新情報に強く、gpt‑5‑proやSonnet 4.5も高い要約能力を持つ</td></tr>';
    echo '<tr><td>初稿生成</td><td>要約・目次・本文・FAQ/CTAまで全体を生成</td><td>gpt‑5‑pro / Claude Sonnet 4.5</td><td>長文の品質と一貫性が重要なため高性能モデルを推奨</td></tr>';
    echo '<tr><td>コーディング</td><td>冗長な表現の削減、構造化、文法修正</td><td>gpt‑3.5 / Mistral 7B / gpt‑4o‑mini</td><td>文章を大きく変えない処理なので安価なモデルで十分</td></tr>';
    echo '<tr><td>採点</td><td>網羅性・日本語品質・SEO・YMYLの4軸で評価</td><td>gpt‑3.5 / Claude Sonnet 4.5</td><td>評価のみなのでコストと性能のバランスを重視</td></tr>';
    echo '<tr><td>ファクトチェック</td><td>固有名詞や数値の検証、修正案と出典URLの提示</td><td>gpt‑5‑pro / Claude Opus 4.1</td><td>正確性と判断力を優先し最新モデルを使用</td></tr>';
    echo '<tr><td>要約</td><td>完成記事を300〜500字に要約</td><td>gpt‑3.5 / Claude Sonnet 4.5</td><td>要約は比較的軽い処理のためコスト優先</td></tr>';
    echo '</tbody></table>';
    // モデル比較テーブル
    echo '<h2>モデル比較</h2>';
    echo '<p>以下は主要モデルの概算コストと特長の比較です。価格は 2025 年 7 月時点の参考値で、提供者の変更により変動する可能性があります。1記事5000文字を超える長文の場合は総トークン数が増えるため、表の料金より高くなる点にご注意ください。</p>';
    echo '<table class="widefat striped" style="max-width:100%;"><thead><tr><th>モデル</th><th>コスト (入力/出力)</th><th>メリット</th><th>デメリット</th></tr></thead><tbody>';
    echo '<tr><td>gpt‑4o</td><td>$3 / $10 per million tokens</td><td>マルチモーダル対応、128Kコンテキスト、高速で高精度</td><td>長文や高負荷タスクではコストがやや高い</td></tr>';
    echo '<tr><td>gpt‑4</td><td>$10 / $30 per million tokens</td><td>高精度の安定モデル</td><td>gpt‑4oより遅く高価</td></tr>';
    echo '<tr><td>gpt‑3.5‑turbo</td><td>$0.50 / $1.50 per million tokens</td><td>コストが非常に低く、簡易タスクや大量生成に向く</td><td>最新モデルより知識が古く推論力が低い</td></tr>';
    echo '<tr><td>Claude 3 Haiku</td><td>$0.25 / $1.25 per million tokens</td><td>高速かつ低コストで簡易なチャットボットやコンテンツモデレーション向き</td><td>複雑なタスクには向かない</td></tr>';
    echo '<tr><td>Claude Sonnet 4.5</td><td>$3 / $15 per million tokens</td><td>高度なエージェント性能とコードワークフローの改善【777976306835073†L30-L42】</td><td>gpt‑4oよりやや遅く長文処理では時間がかかる</td></tr>';
    echo '<tr><td>Claude Opus 4.1</td><td>$15 / $75 per million tokens</td><td>推論・コーディング・エージェント性能が大幅向上、64Kコンテキスト【862493082321476†L14-L21】</td><td>非常に高価で通常は不要</td></tr>';
    echo '<tr><td>Mistral 7B Instruct</td><td>$0.03 / $0.05 per million tokens</td><td>オープンソース由来で非常に低コスト</td><td>知識量が限られ、高度な日本語表現が不得意</td></tr>';
    // 追加: 検索モデルおよび Google Gemini 系列
    echo '<tr><td>Perplexity Sonar</td><td>$1 / $5 per million tokens</td><td>検索と推論を組み合わせた深い調査に最適</td><td>処理がやや遅く、APIコストが高め</td></tr>';
    echo '<tr><td>Gemini Pro</td><td>$1.25 / $10 per million tokens</td><td>Googleが提供する高性能モデル、最新情報や数学に強い</td><td>応答が遅めでコストが高い</td></tr>';
    echo '<tr><td>Gemini Flash</td><td>$0.15 / $0.60 per million tokens</td><td>高速レスポンスでコストも抑えめ、長文に対応</td><td>Proよりも推論能力が劣る</td></tr>';
    // 新規: OpenAI GPT-5 Pro と GPT-5 Image
    echo '<tr><td>GPT‑5 Pro</td><td>$15 / $120 per million tokens</td><td>OpenAIの最新モデル。400Kコンテキストで高度な推論・精度を提供【439123829345859†L16-L24】</td><td>非常に高価で応答速度も遅い</td></tr>';
    echo '<tr><td>GPT‑5 Image</td><td>$10 / $10 per million tokens<br>$0.01/K imgs in / $0.04/K imgs out</td><td>テキストと画像生成を統合し、従来のGPT‑Imageより高品質【199453292602112†L14-L20】</td><td>画像生成が不要なタスクではコストが割高</td></tr>';
    echo '</tbody></table>';

    // 1記事あたりの概算コスト表
    echo '<h2>1記事あたりの概算コスト</h2>';
    echo '<p>5000文字程度の記事を対象としたおおよその料金を示します。実際の価格はプロンプトや記事の長さにより増減します。長文になるほどトークン消費が増えるのでコストも増大します。</p>';
    echo '<table class="widefat striped" style="max-width:100%;"><thead><tr><th>モデル</th><th>概算コスト/記事</th><th>備考</th></tr></thead><tbody>';
    echo '<tr><td>gpt‑4o</td><td>約 $0.09</td><td>16K トークン程度を想定 (入力 9.6k + 出力 6.4k)</td></tr>';
    echo '<tr><td>gpt‑4</td><td>約 $0.28</td><td>gpt‑4o の約3倍のコスト</td></tr>';
    echo '<tr><td>gpt‑3.5‑turbo</td><td>約 $0.014</td><td>低価格で 1〜2 円程度のコスト</td></tr>';
    echo '<tr><td>Claude Sonnet 4.5</td><td>約 $0.12</td><td>バランス型で gpt‑4o よりやや高め</td></tr>';
    echo '<tr><td>Claude Opus 4.1</td><td>約 $0.64</td><td>最高性能だが非常に高価</td></tr>';
    echo '<tr><td>Mistral 7B Instruct</td><td>約 $0.0006</td><td>極めて安価だが日本語長文には不向き</td></tr>';
    // 新モデルの概算コストを追加（5000文字程度を想定）
    echo '<tr><td>Perplexity Sonar</td><td>約 $0.02</td><td>検索を伴うため割高だが、情報取得に最適</td></tr>';
    echo '<tr><td>Gemini Pro</td><td>約 $0.05</td><td>高度な推論と最新情報が必要な記事向け</td></tr>';
    echo '<tr><td>Gemini Flash</td><td>約 $0.003</td><td>高速で低コスト。品質と速度のバランス型</td></tr>';
    echo '<tr><td>GPT‑5 Pro</td><td>約 $0.25</td><td>400Kコンテキストで高度な推論を行うが高価</td></tr>';
    echo '<tr><td>GPT‑5 Image</td><td>約 $0.05</td><td>テキストと画像を統合生成。画像出力を含む場合のコスト</td></tr>';
    echo '</tbody></table>';

    // 設定の手引きセクション
    echo '<h2>設定の手引き</h2>';
    echo '<p><strong>OpenRouter APIキーの取得:</strong> OpenRouter の API キーは <a href="https://openrouter.ai/keys" target="_blank" rel="noopener">openrouter.ai/keys</a> でアカウントを作成すると発行できます。取得したキーは <code>sk-</code> から始まる文字列です。これを AI APIキー欄に入力してください。</p>';
    echo '<p><strong>GA4 プロパティID:</strong> Google Analytics 4 の計測ID (例: <code>G‑XXXXXXXXXX</code>) を入力します。GA4プロパティの管理画面の「管理 &gt; データストリーム」から確認できます。</p>';
    echo '<p><strong>Search Console サイトURL:</strong> Google Search Console に登録されているサイトの URL を入力します。<code>https://</code> で始め、末尾のスラッシュを付けずに入力してください。</p>';
    echo '<p><strong>サービスアカウント JSONパス:</strong> GA4 と Search Console のデータを取得するには、Google Cloud でサービスアカウントを作成し、鍵ファイル (JSON) をサーバーに配置する必要があります。Google Cloud コンソールで「サービス アカウントを作成 &gt; キーを作成 &gt; JSON」を選択して生成し、そのファイルへの絶対パスを入力してください。詳細手順は <a href="https://cloud.google.com/iam/docs/creating-managing-service-account-keys" target="_blank" rel="noopener">Google Cloud の公式ドキュメント</a> を参照してください。</p>';
    // 設定フォーム (options.php)
    echo '<form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '">';
    settings_fields( 'seoone_options' );
    do_settings_sections( 'seoone-settings' );
    submit_button( '保存する' );
    echo '</form></div>';
  }

  public static function render_generate_page(){
    // シンプルな記事生成フォーム（記事単位メタがあればメタを優先）
    echo '<div class="wrap"><h1>記事をつくる</h1>';
    if ( isset($_GET['seoone_message']) && $_GET['seoone_message']==='success' ) {
      echo '<div class="notice notice-success"><p>記事を生成しました（下書き保存）。</p></div>';
    } elseif ( isset($_GET['seoone_message']) && $_GET['seoone_message']==='error' ) {
      echo '<div class="notice notice-error"><p>記事生成に失敗しました。</p></div>';
    }
    // 以前保存した生成設定を読み込みます。存在しない場合は空配列です。
    $generation_defaults = get_option( 'seoone_generation_settings', array() );
    echo '<form method="post" action="'.esc_url( admin_url('admin-post.php') ).'">';
    wp_nonce_field('seoone_generate_article');
    echo '<input type="hidden" name="action" value="seoone_generate_article">';
    echo '<table class="form-table">';
    $def_topic = isset( $generation_defaults['topic'] ) ? esc_attr( $generation_defaults['topic'] ) : '';
    echo '<tr><th>タイトル/テーマ</th><td><input type="text" name="seoone_topic" value="'.$def_topic.'" class="regular-text" placeholder="例：冬のエアコン修理のコツ"></td></tr>';
    // キーワード入力欄 (必須)
    // キーワードは記事生成のベースとなるため必須項目です。ラベルから「(任意)」を削除して強調します。
    $def_keywords = isset( $generation_defaults['keywords'] ) ? esc_attr( $generation_defaults['keywords'] ) : '';
    echo '<tr><th>キーワード <span style="color:#d00;">*</span></th><td><input type="text" name="seoone_keywords" value="'.$def_keywords.'" class="regular-text" placeholder="例：節電, エコ, 暖房">';
    // 既定キーワード一覧を表示
    $opt_global = get_option( 'seoone_settings', array() );
    $default_keywords = array();
    if ( isset( $opt_global['default_keywords'] ) && trim( $opt_global['default_keywords'] ) !== '' ) {
      $default_keywords = array_filter( array_map( 'trim', explode( ',', $opt_global['default_keywords'] ) ) );
    }
    if ( ! empty( $default_keywords ) ) {
      echo '<br /><div style="margin-top:8px;">';
      echo '<strong>登録キーワード:</strong><br />';
      foreach ( $default_keywords as $kw ) {
        $esc = esc_attr( $kw );
        echo '<label style="margin-right:10px;"><input type="checkbox" name="seoone_selected_default_keywords[]" value="' . $esc . '"> ' . esc_html( $kw ) . '</label>';
      }
      echo '<p class="description">チェックしたキーワードは上のキーワード欄に自動的に追加されます。</p>';
      echo '</div>';
    }
    // トレンドキーワードの候補を表示
    $trend_suggestions = array();
    $script = dirname( dirname( __FILE__ ) ) . '/fetch_trends.py';
    if ( file_exists( $script ) ) {
      $cmd = 'python3 ' . escapeshellcmd( $script ) . ' --json 2>/dev/null';
      $out = shell_exec( $cmd );
      if ( ! empty( $out ) ) {
        $data = json_decode( $out, true );
        if ( is_array( $data ) && isset( $data['keywords'] ) && is_array( $data['keywords'] ) ) {
          $trend_suggestions = $data['keywords'];
        }
      }
      // POSTされた除外キーワードがあれば候補から除外します
      if ( isset( $_POST['seoone_exclude_keywords'] ) && trim( $_POST['seoone_exclude_keywords'] ) !== '' ) {
        $ex_list = array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_POST['seoone_exclude_keywords'] ) ) ) );
        if ( ! empty( $ex_list ) ) {
          $trend_suggestions = array_filter( $trend_suggestions, function( $kw ) use ( $ex_list ) {
            foreach ( $ex_list as $ex ) {
              if ( stripos( $kw, $ex ) !== false ) {
                return false;
              }
            }
            return true;
          } );
        }
      }
    }
    if ( ! empty( $trend_suggestions ) ) {
      // トレンド候補をチェックボックス形式で表示
      echo '<br /><div style="margin-top:8px;">';
      echo '<strong>トレンドキーワード候補:</strong><br />';
      foreach ( $trend_suggestions as $kw ) {
        $esc = esc_attr( $kw );
        echo '<label style="margin-right:10px;"><input type="checkbox" name="seoone_trend_keywords[]" value="' . $esc . '"> ' . esc_html( $kw ) . '</label>';
      }
      // 説明を改善: 除外キーワードが設定されている場合は候補から除外される
      echo '<p class="description">チェックしたキーワードは上のキーワード欄に自動的に追加されます。除外キーワードに一致するものは候補から除かれます。</p>';
      echo '</div>';
    }
    echo '</td></tr>';
    // 除外キーワード入力欄
    $def_ex = isset( $generation_defaults['exclude_keywords'] ) ? esc_attr( $generation_defaults['exclude_keywords'] ) : '';
    echo '<tr><th>除外キーワード (任意)</th><td><input type="text" name="seoone_exclude_keywords" value="'.$def_ex.'" class="regular-text" placeholder="例：故障, 事故"><br /><span class="description">指定したキーワードを含む場合は除外されます（カンマ区切り）。</span></td></tr>';
    // ジャンル入力欄
    $def_genre = isset( $generation_defaults['genre'] ) ? esc_attr( $generation_defaults['genre'] ) : '';
    echo '<tr><th>ジャンル (任意)</th><td><input type="text" name="seoone_genre" value="'.$def_genre.'" class="regular-text" placeholder="例：テクノロジー, 健康, ビジネス"></td></tr>';
    $def_wc = isset( $generation_defaults['word_count'] ) ? intval( $generation_defaults['word_count'] ) : 5000;
    if ( $def_wc < 1000 ) $def_wc = 1000;
    echo '<tr><th>文字数目安</th><td><input type="number" name="seoone_word_count" value="'. esc_attr( $def_wc ) .'" min="1000" step="500"> <span class="description">記事本文の目安文字数を指定します。</span></td></tr>';
    // ペルソナ設定
    echo '<tr><th>ペルソナ設定</th><td>';
    // 単発記事向けペルソナ指定（性別・年齢など）
    echo '<p><strong>単発用ペルソナ指定</strong></p>';
    // 名前
    $def_persona_name = isset( $generation_defaults['persona_name'] ) ? esc_attr( $generation_defaults['persona_name'] ) : '';
    echo '<label title="作者の名前を指定すると記事に自己紹介が含まれます">名前: <input type="text" name="seoone_persona_name" value="' . $def_persona_name . '" placeholder="例：山田太郎"></label><br />';
    // 職業(役職)
    $def_persona_role = isset( $generation_defaults['persona_role'] ) ? esc_attr( $generation_defaults['persona_role'] ) : '';
    echo '<label title="作者の職業または役職を指定すると記事に自己紹介が含まれます">職業(役職): <input type="text" name="seoone_persona_role" value="' . $def_persona_role . '" placeholder="例：エンジニア"></label><br />';
    // 性別
    $def_persona_gender = isset( $generation_defaults['persona_gender'] ) ? $generation_defaults['persona_gender'] : '';
    echo '<label title="作者の性別を指定すると文体に反映されます">性別: ';
    echo '<select name="seoone_persona_gender">';
    echo '<option value=""' . selected( $def_persona_gender, '', false ) . '>指定なし</option>';
    echo '<option value="男性"' . selected( $def_persona_gender, '男性', false ) . '>男性</option>';
    echo '<option value="女性"' . selected( $def_persona_gender, '女性', false ) . '>女性</option>';
    echo '<option value="その他"' . selected( $def_persona_gender, 'その他', false ) . '>その他</option>';
    echo '</select>';
    echo '</label><br />';
    // 年齢
    $def_persona_age = isset( $generation_defaults['persona_age'] ) ? esc_attr( $generation_defaults['persona_age'] ) : '';
    echo '<label title="作者の年齢を指定すると語彙や視点が変化します">年齢: <input type="number" name="seoone_persona_age" value="'.$def_persona_age.'" min="0" step="1" placeholder="例：30"></label><br />';
    // 性格
    $def_persona_character = isset( $generation_defaults['persona_character'] ) ? esc_attr( $generation_defaults['persona_character'] ) : '';
    echo '<label title="性格を指定すると記事の口調や雰囲気が変わります">性格: <input type="text" name="seoone_persona_character" value="'.$def_persona_character.'" placeholder="例：明るく社交的"></label><br />';
    // 好きなもの
    $def_persona_likes = isset( $generation_defaults['persona_likes'] ) ? esc_attr( $generation_defaults['persona_likes'] ) : '';
    echo '<label title="好きなものを指定すると例え話に反映されることがあります">好きなもの: <input type="text" name="seoone_persona_likes" value="'.$def_persona_likes.'" placeholder="例：音楽, 映画"></label><br />';
    // 嫌いなもの
    $def_persona_dislikes = isset( $generation_defaults['persona_dislikes'] ) ? esc_attr( $generation_defaults['persona_dislikes'] ) : '';
    echo '<label title="苦手なものを指定すると避ける表現が増えます">嫌いなもの: <input type="text" name="seoone_persona_dislikes" value="'.$def_persona_dislikes.'" placeholder="例：雨, 辛い食べ物"></label><br />';
    // 趣味
    $def_persona_hobbies = isset( $generation_defaults['persona_hobbies'] ) ? esc_attr( $generation_defaults['persona_hobbies'] ) : '';
    echo '<label title="趣味を指定すると記事内での具体例に使われます">趣味: <input type="text" name="seoone_persona_hobbies" value="'.$def_persona_hobbies.'" placeholder="例：登山, 読書"></label><br />';
    // 出身
    $def_persona_origin = isset( $generation_defaults['persona_origin'] ) ? esc_attr( $generation_defaults['persona_origin'] ) : '';
    echo '<label title="出身地を指定すると方言や地域性が反映されます">出身: <input type="text" name="seoone_persona_origin" value="'.$def_persona_origin.'" placeholder="例：東京"></label>';
    echo '<br /><span class="description">上記の単発用項目をすべて空欄にすると、下のペルソナ一覧から重み付けで自動選択されます。特定のペルソナで記事を書きたい場合のみ入力してください。</span>';
    // ペルソナ一覧管理
    // 既存のペルソナプロファイルを取得し、繰り返しフィールドで表示します
    $personas = array();
    if ( isset( $opt_global['persona_profiles'] ) && is_array( $opt_global['persona_profiles'] ) ) {
      $personas = $opt_global['persona_profiles'];
    }
    echo '<hr />';
    echo '<p><strong>ペルソナ一覧管理</strong></p>';
    echo '<div id="seoone-persona-container">';
    // 必ず1つは表示
    if ( empty( $personas ) ) {
      $personas[] = array();
    }
    foreach ( $personas as $index => $p ) {
      echo '<div class="seoone-persona-entry" style="border:1px solid #ccc;padding:10px;margin-bottom:10px;">';
      echo '<strong>ペルソナ' . ( $index + 1 ) . '</strong> ';
      echo '<button type="button" class="button-link seoone-remove-persona" style="color:#c00;">削除</button><br />';
      // 名前
      $name = isset( $p['name'] ) ? esc_attr( $p['name'] ) : '';
      echo '名前: <input type="text" name="persona_profiles[' . $index . '][name]" value="' . $name . '" placeholder="例：山田太郎"><br />';
      // 職業(役職)
      $role = isset( $p['role'] ) ? esc_attr( $p['role'] ) : '';
      echo '職業(役職): <input type="text" name="persona_profiles[' . $index . '][role]" value="' . $role . '" placeholder="例：エンジニア"><br />';
      // weight
      $weight = isset( $p['weight'] ) ? esc_attr( $p['weight'] ) : '';
      echo '重み: <input type="number" step="0.1" name="persona_profiles[' . $index . '][weight]" value="' . $weight . '" placeholder="例：1.0"><br />';
      // gender
      $gender = isset( $p['gender'] ) ? $p['gender'] : '';
      echo '性別: <select name="persona_profiles[' . $index . '][gender]">';
      echo '<option value=""' . selected( $gender, '' , false ) . '>指定なし</option>';
      echo '<option value="男性"' . selected( $gender, '男性', false ) . '>男性</option>';
      echo '<option value="女性"' . selected( $gender, '女性', false ) . '>女性</option>';
      echo '<option value="その他"' . selected( $gender, 'その他', false ) . '>その他</option>';
      echo '</select><br />';
      // age
      $age = isset( $p['age'] ) ? esc_attr( $p['age'] ) : '';
      echo '年齢: <input type="text" name="persona_profiles[' . $index . '][age]" value="' . $age . '" placeholder="例：25"><br />';
      // character
      $character = isset( $p['character'] ) ? esc_attr( $p['character'] ) : '';
      echo '性格: <input type="text" name="persona_profiles[' . $index . '][character]" value="' . $character . '" placeholder="例：明るい"><br />';
      // likes
      $likes = isset( $p['likes'] ) ? esc_attr( $p['likes'] ) : '';
      echo '好きなもの: <input type="text" name="persona_profiles[' . $index . '][likes]" value="' . $likes . '" placeholder="例：音楽, 映画"><br />';
      // dislikes
      $dislikes = isset( $p['dislikes'] ) ? esc_attr( $p['dislikes'] ) : '';
      echo '嫌いなもの: <input type="text" name="persona_profiles[' . $index . '][dislikes]" value="' . $dislikes . '" placeholder="例：雨, 辛い食べ物"><br />';
      // hobbies
      $hobbies = isset( $p['hobbies'] ) ? esc_attr( $p['hobbies'] ) : '';
      echo '趣味: <input type="text" name="persona_profiles[' . $index . '][hobbies]" value="' . $hobbies . '" placeholder="例：登山, 読書"><br />';
      // origin
      $origin = isset( $p['origin'] ) ? esc_attr( $p['origin'] ) : '';
      echo '出身: <input type="text" name="persona_profiles[' . $index . '][origin]" value="' . $origin . '" placeholder="例：東京"><br />';
      // topics
      $topics = isset( $p['topics'] ) ? esc_attr( $p['topics'] ) : '';
      echo 'トピックキーワード: <input type="text" name="persona_profiles[' . $index . '][topics]" value="' . $topics . '" placeholder="例：洗濯機, 電気修理"><br />';
      echo '</div>';
    }
    echo '</div>';
    echo '<button type="button" id="seoone-add-persona" class="button">ペルソナを追加</button>';
    echo '<p class="description">ペルソナは複数登録できます。重み合計は1.0になる必要はありませんが、自動選択時の比率に影響します。</p>';
    echo '</td></tr>';
    // 競合分析を含めるオプション
    $def_include_comp = ! empty( $generation_defaults['include_competitor'] );
    $checked_comp = $def_include_comp ? ' checked' : '';
    echo '<tr><th>競合分析を含める</th><td><label><input type="checkbox" name="seoone_include_competitor" value="1"'.$checked_comp.'> 生成時に競合分析ステップを実行し、分析結果を参考情報として使用します。</label></td></tr>';
    // 予約投稿
    $def_schedule = isset( $generation_defaults['schedule'] ) ? esc_attr( $generation_defaults['schedule'] ) : '';
    echo '<tr><th>予約投稿日時 (任意)</th><td><input type="datetime-local" name="seoone_schedule" value="'.$def_schedule.'" step="60"> <span class="description">指定するとこの日時に予約投稿されます（未入力なら下書き保存）。</span></td></tr>';
    // 言語選択（複数選択可）
    $saved_langs = isset( $generation_defaults['languages'] ) && is_array( $generation_defaults['languages'] ) ? $generation_defaults['languages'] : array();
    echo '<tr><th>言語</th><td>';
    $langs = array( 'ja' => '日本語', 'en' => '英語', 'zh' => '中国語' );
    foreach ( $langs as $code => $label ) {
      $chk = in_array( $code, $saved_langs, true ) ? ' checked' : '';
      // デフォルトでは何も保存されていない場合のみ日本語をチェック
      if ( empty( $saved_langs ) && $code === 'ja' ) {
        $chk = ' checked';
      }
      echo '<label><input type="checkbox" name="seoone_languages[]" value="'.$code.'"'.$chk.'> '. $label .'</label><br />';
    }
    echo '<span class="description">複数選択すると、それぞれの言語で個別の記事が生成されます。</span>';
    echo '</td></tr>';

    // 必ず含めたい内容を追加
    $def_force = isset( $generation_defaults['force_content'] ) ? esc_textarea( $generation_defaults['force_content'] ) : '';
    echo '<tr><th>必ず含めたい内容</th><td><textarea name="seoone_force_content" rows="3" cols="50" placeholder="例：アトム電器DD熊谷店は家電販売と家電修理を行っています">'.$def_force.'</textarea><br /><span class="description">ここで指定したテキストは記事のトーンに合わせて自然に織り交ぜられます。企業のPRやサービス説明にご利用ください。</span></td></tr>';

    // 自動生成件数と時刻設定
    $auto_count = isset( $opt_global['auto_generate_count'] ) ? intval( $opt_global['auto_generate_count'] ) : 0;
    $auto_time  = isset( $opt_global['auto_generate_time'] ) ? $opt_global['auto_generate_time'] : '';
    echo '<tr><th>自動生成件数/日</th><td><input type="number" name="seoone_auto_generate_count" value="' . esc_attr( $auto_count ) . '" min="0" step="1"> <span class="description">1日あたり自動生成する記事数です。0の場合は自動生成を行いません。</span></td></tr>';
    echo '<tr><th>自動生成タイミング</th><td><input type="text" name="seoone_auto_generate_time" value="' . esc_attr( $auto_time ) . '" placeholder="例: 06:00,14:00,22:00 または 08:00-20:00"> <span class="description">カンマ区切りで複数の時刻、または範囲指定(HH:MM-HH:MM)が可能です。範囲指定の場合は指定数に応じてランダムに生成されます。</span></td></tr>';
    // 設定保存ボタン
    echo '<tr><th>生成設定の保存</th><td><input type="submit" name="seoone_action" value="save_generation_settings" class="button-secondary" /> <span class="description">入力したキーワード、ジャンル、文字数目安、ペルソナ設定、競合分析の有無、言語、予約投稿日時などすべての生成設定を保存します。</span></td></tr>';
    echo '</table>';
    // AIで記事をつくるボタン
    echo '<p><button type="submit" name="seoone_action" value="generate_article" class="button button-primary">AIで記事をつくる</button></p>';
    echo '</form></div>';
  }

  public static function handle_generate_article(){
    // 管理者以外の編集者でも実行できるよう、SEOONE_CAP を用いて権限チェック
    if ( ! current_user_can( SEOONE_CAP ) ) wp_die('権限がありません');
    check_admin_referer('seoone_generate_article');
    // 分岐: 設定保存の場合はオプションを更新してリダイレクト
    $action = isset( $_POST['seoone_action'] ) ? sanitize_text_field( wp_unslash( $_POST['seoone_action'] ) ) : '';
    if ( $action === 'save_generation_settings' ) {
      // 保存する生成フォームの値をまとめます。入力値をサニタイズし、次回ページ表示時にプリセットとして使えるようオプションに保存します。
      $saved = array();
      // タイトル/テーマ
      if ( isset( $_POST['seoone_topic'] ) ) {
        $saved['topic'] = sanitize_text_field( wp_unslash( $_POST['seoone_topic'] ) );
      }
      // キーワード
      if ( isset( $_POST['seoone_keywords'] ) ) {
        $saved['keywords'] = sanitize_text_field( wp_unslash( $_POST['seoone_keywords'] ) );
      }
      // 除外キーワード
      if ( isset( $_POST['seoone_exclude_keywords'] ) ) {
        $saved['exclude_keywords'] = sanitize_text_field( wp_unslash( $_POST['seoone_exclude_keywords'] ) );
      }
      // ジャンル
      if ( isset( $_POST['seoone_genre'] ) ) {
        $saved['genre'] = sanitize_text_field( wp_unslash( $_POST['seoone_genre'] ) );
      }
      // 文字数目安
      if ( isset( $_POST['seoone_word_count'] ) ) {
        $saved['word_count'] = max( 1000, intval( $_POST['seoone_word_count'] ) );
      }
      // 単発用ペルソナ指定
      $saved['persona_gender']    = isset( $_POST['seoone_persona_gender'] )    ? sanitize_text_field( wp_unslash( $_POST['seoone_persona_gender'] ) )    : '';
      $saved['persona_age']       = isset( $_POST['seoone_persona_age'] )       ? sanitize_text_field( wp_unslash( $_POST['seoone_persona_age'] ) )       : '';
      $saved['persona_character'] = isset( $_POST['seoone_persona_character'] ) ? sanitize_text_field( wp_unslash( $_POST['seoone_persona_character'] ) ) : '';
      $saved['persona_likes']     = isset( $_POST['seoone_persona_likes'] )     ? sanitize_text_field( wp_unslash( $_POST['seoone_persona_likes'] ) )     : '';
      $saved['persona_dislikes']  = isset( $_POST['seoone_persona_dislikes'] )  ? sanitize_text_field( wp_unslash( $_POST['seoone_persona_dislikes'] ) )  : '';
      $saved['persona_hobbies']   = isset( $_POST['seoone_persona_hobbies'] )   ? sanitize_text_field( wp_unslash( $_POST['seoone_persona_hobbies'] ) )   : '';
      $saved['persona_origin']    = isset( $_POST['seoone_persona_origin'] )    ? sanitize_text_field( wp_unslash( $_POST['seoone_persona_origin'] ) )    : '';
      // 単発用ペルソナ 名前・職業
      $saved['persona_name']      = isset( $_POST['seoone_persona_name'] )      ? sanitize_text_field( wp_unslash( $_POST['seoone_persona_name'] ) )      : '';
      $saved['persona_role']      = isset( $_POST['seoone_persona_role'] )      ? sanitize_text_field( wp_unslash( $_POST['seoone_persona_role'] ) )      : '';
      // 競合分析
      $saved['include_competitor'] = isset( $_POST['seoone_include_competitor'] ) ? true : false;
      // 予約投稿日時
      if ( isset( $_POST['seoone_schedule'] ) ) {
        $saved['schedule'] = sanitize_text_field( wp_unslash( $_POST['seoone_schedule'] ) );
      }
      // 言語
      $saved_languages = array();
      if ( isset( $_POST['seoone_languages'] ) && is_array( $_POST['seoone_languages'] ) ) {
        $saved_languages = array_map( 'sanitize_text_field', wp_unslash( $_POST['seoone_languages'] ) );
      }
      $saved['languages'] = $saved_languages;
      // 必ず含めたい内容
      if ( isset( $_POST['seoone_force_content'] ) ) {
        // allow HTML tags per WP kses for content
        $saved['force_content'] = trim( wp_kses_post( wp_unslash( $_POST['seoone_force_content'] ) ) );
      }
      // auto generation settings
      $saved['auto_generate_count'] = isset( $_POST['seoone_auto_generate_count'] ) ? intval( $_POST['seoone_auto_generate_count'] ) : 0;
      $saved['auto_generate_time']  = isset( $_POST['seoone_auto_generate_time'] )  ? sanitize_text_field( wp_unslash( $_POST['seoone_auto_generate_time'] ) ) : '';
      // 保存
      update_option( 'seoone_generation_settings', $saved );

      // 既存の設定も更新: 自動生成件数と時刻/範囲およびペルソナ一覧（全体設定）
      $opt = get_option( 'seoone_settings', array() );
      // 件数
      $opt['auto_generate_count'] = $saved['auto_generate_count'];
      // タイミング
      $opt['auto_generate_time'] = $saved['auto_generate_time'];
      // ペルソナプロファイルを保存（記事生成タブに集約されているため）
      if ( isset( $_POST['persona_profiles'] ) && is_array( $_POST['persona_profiles'] ) ) {
        $profiles_raw = wp_unslash( $_POST['persona_profiles'] );
        $profiles_clean = array();
        foreach ( $profiles_raw as $p ) {
          $all_empty = true;
          $entry = array();
          // 名前
          if ( isset( $p['name'] ) && $p['name'] !== '' ) {
            $entry['name'] = sanitize_text_field( $p['name'] );
            $all_empty = false;
          }
          // 職業(役職)
          if ( isset( $p['role'] ) && $p['role'] !== '' ) {
            $entry['role'] = sanitize_text_field( $p['role'] );
            $all_empty = false;
          }
          // 重み
          if ( isset( $p['weight'] ) && $p['weight'] !== '' ) {
            $entry['weight'] = floatval( $p['weight'] );
            $all_empty = false;
          }
          // 性別
          if ( isset( $p['gender'] ) && $p['gender'] !== '' ) {
            $entry['gender'] = sanitize_text_field( $p['gender'] );
            $all_empty = false;
          }
          // 年齢
          if ( isset( $p['age'] ) && $p['age'] !== '' ) {
            $entry['age'] = sanitize_text_field( $p['age'] );
            $all_empty = false;
          }
          // 性格
          if ( isset( $p['character'] ) && $p['character'] !== '' ) {
            $entry['character'] = sanitize_text_field( $p['character'] );
            $all_empty = false;
          }
          // 好きなもの
          if ( isset( $p['likes'] ) && $p['likes'] !== '' ) {
            $entry['likes'] = sanitize_text_field( $p['likes'] );
            $all_empty = false;
          }
          // 嫌いなもの
          if ( isset( $p['dislikes'] ) && $p['dislikes'] !== '' ) {
            $entry['dislikes'] = sanitize_text_field( $p['dislikes'] );
            $all_empty = false;
          }
          // 趣味
          if ( isset( $p['hobbies'] ) && $p['hobbies'] !== '' ) {
            $entry['hobbies'] = sanitize_text_field( $p['hobbies'] );
            $all_empty = false;
          }
          // 出身
          if ( isset( $p['origin'] ) && $p['origin'] !== '' ) {
            $entry['origin'] = sanitize_text_field( $p['origin'] );
            $all_empty = false;
          }
          // トピックキーワード
          if ( isset( $p['topics'] ) && $p['topics'] !== '' ) {
            $entry['topics'] = sanitize_text_field( $p['topics'] );
            $all_empty = false;
          }
          if ( ! $all_empty ) {
            $profiles_clean[] = $entry;
          }
        }
        $opt['persona_profiles'] = $profiles_clean;
      }
      update_option( 'seoone_settings', $opt );
      // スケジュールを更新
      self::maybe_schedule_auto_generation();
      // 設定保存後はハブの「記事生成」タブへ戻ります
      wp_safe_redirect( admin_url( 'admin.php?page=seoone&tab=generate&seoone_message=settings-saved' ) );
      exit;
    }
    $settings = array();
    if ( isset($_POST['seoone_topic']) ) {
      $settings['title'] = sanitize_text_field( wp_unslash($_POST['seoone_topic']) );
    }
    // 追加パラメータ
    if ( isset($_POST['seoone_keywords']) ) {
      $settings['keywords'] = sanitize_text_field( wp_unslash($_POST['seoone_keywords']) );
    }
    // 既定キーワードのチェックボックスがあればキーワードに追加
    if ( isset( $_POST['seoone_selected_default_keywords'] ) && is_array( $_POST['seoone_selected_default_keywords'] ) ) {
      $selected_defaults = array_map( 'sanitize_text_field', wp_unslash( $_POST['seoone_selected_default_keywords'] ) );
      if ( ! empty( $selected_defaults ) ) {
        $defaults_str = implode( ', ', $selected_defaults );
        if ( ! empty( $settings['keywords'] ) ) {
          $settings['keywords'] .= ', ' . $defaults_str;
        } else {
          $settings['keywords'] = $defaults_str;
        }
      }
    }
    // トレンドキーワードのチェックボックスがあればキーワードに追加
    if ( isset( $_POST['seoone_trend_keywords'] ) && is_array( $_POST['seoone_trend_keywords'] ) ) {
      $selected_trends = array_map( 'sanitize_text_field', wp_unslash( $_POST['seoone_trend_keywords'] ) );
      if ( ! empty( $selected_trends ) ) {
        $trend_str = implode( ', ', $selected_trends );
        if ( ! empty( $settings['keywords'] ) ) {
          $settings['keywords'] .= ', ' . $trend_str;
        } else {
          $settings['keywords'] = $trend_str;
        }
      }
    }
    // 除外キーワードがあれば追加
    if ( isset($_POST['seoone_exclude_keywords']) ) {
      $settings['exclude_keywords'] = sanitize_text_field( wp_unslash($_POST['seoone_exclude_keywords']) );
    }

    // キーワード必須チェック。キーワードが空の場合はエラーを返してページに戻る。
    if ( empty( $settings['keywords'] ) ) {
      wp_safe_redirect( admin_url( 'admin.php?page=seoone-generate&seoone_message=keyword_required' ) );
      exit;
    }
    // ジャンル指定があれば保持
    if ( isset($_POST['seoone_genre']) ) {
      $settings['genre'] = sanitize_text_field( wp_unslash($_POST['seoone_genre']) );
    }
    if ( isset($_POST['seoone_word_count']) ) {
      $settings['word_count'] = max(1000, intval( $_POST['seoone_word_count'] ) );
    }
    // ペルソナ情報を取得
    $settings['persona_gender']    = isset($_POST['seoone_persona_gender'])    ? sanitize_text_field( wp_unslash($_POST['seoone_persona_gender']) )    : '';
    $settings['persona_age']       = isset($_POST['seoone_persona_age'])       ? sanitize_text_field( wp_unslash($_POST['seoone_persona_age']) )       : '';
    $settings['persona_character'] = isset($_POST['seoone_persona_character']) ? sanitize_text_field( wp_unslash($_POST['seoone_persona_character']) ) : '';
    $settings['persona_likes']     = isset($_POST['seoone_persona_likes'])     ? sanitize_text_field( wp_unslash($_POST['seoone_persona_likes']) )     : '';
    $settings['persona_dislikes']  = isset($_POST['seoone_persona_dislikes'])  ? sanitize_text_field( wp_unslash($_POST['seoone_persona_dislikes']) )  : '';
    $settings['persona_hobbies']   = isset($_POST['seoone_persona_hobbies'])   ? sanitize_text_field( wp_unslash($_POST['seoone_persona_hobbies']) )   : '';
    $settings['persona_origin']    = isset($_POST['seoone_persona_origin'])    ? sanitize_text_field( wp_unslash($_POST['seoone_persona_origin']) )    : '';

    // 名前・職業（役職）を取得。これらが設定されている場合、記事冒頭に自己紹介を含める指示が有効になります。
    $settings['persona_name'] = isset($_POST['seoone_persona_name']) ? sanitize_text_field( wp_unslash($_POST['seoone_persona_name']) ) : '';
    $settings['persona_role'] = isset($_POST['seoone_persona_role']) ? sanitize_text_field( wp_unslash($_POST['seoone_persona_role']) ) : '';

    // 必ず含めたい内容 (記事のトーンに合わせて自然に挿入)
    if ( isset( $_POST['seoone_force_content'] ) ) {
      $settings['force_content'] = trim( wp_kses_post( wp_unslash( $_POST['seoone_force_content'] ) ) );
    }
    if ( isset($_POST['seoone_schedule']) ) {
      $settings['schedule'] = sanitize_text_field( $_POST['seoone_schedule'] );
    }
    // 競合分析フラグを設定
    $settings['include_competitor'] = isset( $_POST['seoone_include_competitor'] );
    // 言語選択：複数対応
    $languages = array();
    if ( isset( $_POST['seoone_languages'] ) && is_array( $_POST['seoone_languages'] ) ) {
      $languages = array_map( 'sanitize_text_field', wp_unslash( $_POST['seoone_languages'] ) );
      // フォームでチェックなしの場合はデフォルトで日本語
      if ( empty( $languages ) ) {
        $languages = array( 'ja' );
      }
    } elseif ( isset( $_POST['seoone_language'] ) ) {
      // 旧フィールドとの互換
      $languages = array( sanitize_text_field( $_POST['seoone_language'] ) );
    } else {
      $languages = array( 'ja' );
    }
    $gen = new SeoOne_Generator();
    $created_posts = array();
    foreach ( $languages as $lang ) {
      $settings_lang = $settings;
      $settings_lang['language'] = $lang;
      $post = $gen->generate_article( $settings_lang );
      if ( $post && ! is_wp_error( $post ) ) {
        $created_posts[] = $post->ID;
      }
    }
    if ( ! empty( $created_posts ) ) {
      // 最後に生成した記事を開く
      $pid = end( $created_posts );
      wp_safe_redirect( admin_url( 'post.php?post=' . $pid . '&action=edit&seoone_message=success' ) );
    } else {
      wp_safe_redirect( admin_url( 'admin.php?page=seoone-generate&seoone_message=error' ) );
    }
    exit;
  }

  /**
   * 設定ページの保存を処理します。
   * ハブ内の設定タブから送信されるフォームは options.php を利用しないため、独自に保存します。
   */
  public static function handle_save_settings(){
    // 権限チェック
    if ( ! current_user_can( SEOONE_CAP ) ) {
      wp_die( '権限がありません' );
    }
    // ノンスチェック
    check_admin_referer( 'seoone_save_settings' );
    // seoone_settings の取得とサニタイズ
    $raw_settings = isset( $_POST['seoone_settings'] ) && is_array( $_POST['seoone_settings'] ) ? $_POST['seoone_settings'] : array();
    $clean_settings = self::sanitize_settings( $raw_settings );
    update_option( 'seoone_settings', $clean_settings );
    // 広告設定も保存
    if ( class_exists( 'SeoOne_Ads' ) ) {
      $ads_raw = isset( $_POST[ SeoOne_Ads::OPTION_NAME ] ) && is_array( $_POST[ SeoOne_Ads::OPTION_NAME ] ) ? $_POST[ SeoOne_Ads::OPTION_NAME ] : array();
      $ads_clean = SeoOne_Ads::sanitize( $ads_raw );
      update_option( SeoOne_Ads::OPTION_NAME, $ads_clean );
    }
    // 保存後にハブの設定タブへリダイレクト
    wp_safe_redirect( admin_url( 'admin.php?page=seoone&tab=settings&settings-updated=true' ) );
    exit;
  }

  /**
   * メトリクス履歴を CSV ファイルとしてダウンロードします。
   */
  public static function handle_export_metrics(){
    // 管理者以外の編集者でも実行できるよう、SEOONE_CAP を用いて権限チェック
    if ( ! current_user_can( SEOONE_CAP ) ) {
      wp_die('権限がありません');
    }
    check_admin_referer('seoone_export_metrics');
    $history = get_option('seoone_metrics_history', array());
    if ( empty($history) ) {
      wp_die('履歴データがありません');
    }
    // CSV ヘッダー
    $headers = array('fetched_at','clicks','impressions','ctr','position','sessions','active_users','conversions');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="seoone_metrics_history.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);
    foreach ( $history as $row ) {
      $data = array();
      foreach ( $headers as $h ) {
        $data[] = isset($row[$h]) ? $row[$h] : '';
      }
      fputcsv($output, $data);
    }
    fclose($output);
    exit;
  }

  /**
   * 設定に基づいて自動生成用の WP Cron を登録または解除します。
   * 初期化時に実行され、設定値が変わった場合にも適切に再スケジュールします。
   */
  public static function maybe_schedule_auto_generation(){
    $opt = get_option( 'seoone_settings', array() );
    $count = isset( $opt['auto_generate_count'] ) ? intval( $opt['auto_generate_count'] ) : 0;
    $time  = isset( $opt['auto_generate_time'] ) ? trim( $opt['auto_generate_time'] ) : '';
    // 既存イベントがある場合は取得
    $timestamp = wp_next_scheduled( 'seoone_auto_generate_event' );
    // 自動生成無効の場合はイベントを解除
    if ( $count <= 0 || $time === '' ) {
      if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'seoone_auto_generate_event' );
      }
      return;
    }
    // 登録済みイベントがない場合はスケジュール
    if ( ! $timestamp ) {
      $next = self::calculate_next_run_time( $time );
      if ( $next ) {
        wp_schedule_event( $next, 'daily', 'seoone_auto_generate_event' );
      }
      return;
    }
    // 登録済みイベントがある場合、時刻が変更されているなら再スケジュール
    // 5 分以上の差があれば再スケジュール。
    $desired = self::calculate_next_run_time( $time );
    if ( $desired && abs( $desired - $timestamp ) > 300 ) {
      wp_unschedule_event( $timestamp, 'seoone_auto_generate_event' );
      wp_schedule_event( $desired, 'daily', 'seoone_auto_generate_event' );
    }
  }

  /**
   * HH:MM 形式の時刻から次回実行時刻を Unix タイムスタンプで返します。
   * 今日の指定時刻が過ぎていれば翌日の同じ時刻を返します。
   *
   * @param string $time_str 例: "06:30"
   * @return int|false
   */
  private static function calculate_next_run_time( $time_str ){
    // 正常化：トリム
    $time_str = trim( (string) $time_str );
    // カンマ区切りの複数時刻対応
    if ( strpos( $time_str, ',' ) !== false ) {
      $times    = array_map( 'trim', explode( ',', $time_str ) );
      $next_ts  = false;
      $now      = current_time( 'timestamp' );
      $today    = date( 'Y-m-d', $now );
      foreach ( $times as $t ) {
        if ( preg_match( '/^(\d{1,2}):(\d{2})$/', $t, $m ) ) {
          $h = intval( $m[1] );
          $mi = intval( $m[2] );
          $candidate = strtotime( $today . ' ' . sprintf( '%02d:%02d:00', $h, $mi ) );
          if ( $candidate === false ) {
            continue;
          }
          if ( $candidate <= $now ) {
            $candidate = strtotime( '+1 day', $candidate );
          }
          if ( $next_ts === false || $candidate < $next_ts ) {
            $next_ts = $candidate;
          }
        }
      }
      return $next_ts;
    }
    // 時刻範囲 (HH:MM-HH:MM) の場合は開始時刻を使う
    if ( preg_match( '/^(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})$/', $time_str, $m ) ) {
      $start = $m[1];
      // $end = $m[2]; // 将来的な利用を想定
      // 範囲指定では開始時刻を用いて次回実行時刻を計算します。
      $time_str = $start;
    }
    // 単一時刻 (HH:MM)
    if ( ! preg_match( '/^(\d{1,2}):(\d{2})$/', $time_str, $m ) ) {
      return false;
    }
    $hour = intval( $m[1] );
    $min  = intval( $m[2] );
    $now   = current_time( 'timestamp' );
    $today = date( 'Y-m-d', $now );
    $target = strtotime( $today . ' ' . sprintf( '%02d:%02d:00', $hour, $min ) );
    if ( $target === false ) {
      return false;
    }
    if ( $target <= $now ) {
      $target = strtotime( '+1 day', $target );
    }
    return $target;
  }

  /**
   * WP Cron から呼び出され、自動的に記事を生成します。
   * 設定された件数分だけ、既定キーワードからランダムに選択して記事を生成します。
   */
  public static function auto_generate_articles(){
    $opt = get_option( 'seoone_settings', array() );
    $count = isset( $opt['auto_generate_count'] ) ? intval( $opt['auto_generate_count'] ) : 0;
    if ( $count <= 0 ) {
      return;
    }
    $kw_str = $opt['default_keywords'] ?? '';
    $keywords = array_filter( array_map( 'trim', explode( ',', $kw_str ) ) );
    if ( empty( $keywords ) ) {
      return;
    }
    $gen = new SeoOne_Generator();
    for ( $i = 0; $i < $count; $i++ ) {
      // ランダムにキーワードを選ぶ
      $kw = $keywords[ array_rand( $keywords ) ];
      $settings = array(
        'keywords'    => $kw,
        'word_count'  => 5000,
        // 既定言語: 日本語
        'language'    => 'ja',
        'include_competitor' => false
      );
      // generate without user-specified persona or title; auto persona selection will apply if登録あり
      $post = $gen->generate_article( $settings );
      // 無視: エラー時は単にスキップ
    }
  }
}
