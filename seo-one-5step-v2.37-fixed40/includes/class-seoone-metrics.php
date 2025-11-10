<?php
/**
 * GSC/GA4 メトリクス取得およびダッシュボード表示
 *
 * このクラスは Google Search Console および Google Analytics 4 の API から
 * クリック数、表示回数、CTR、順位、セッション数、エンゲージメント、コンバージョン等の
 * 指標を取得して WordPress 管理画面に表示するための基礎を提供します。
 *
 * Google API への接続にはサービスアカウントや OAuth 認証が必要です。
 * 実運用では Google API PHP クライアントをインストールし、
 * 下記の stub メソッドを実装してください。
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class SeoOne_Metrics {
    /**
     * 初期化
     */
    public static function init() {
        // メトリクスは Hub 内のタブに統合するため、サブメニューを追加しません。
        // add_action('admin_menu', array(__CLASS__, 'register_menu'));
        // add_action('admin_menu', array(__CLASS__, 'register_dashboard_menu'));
        // デイリーの CRON イベントをスケジュール
        add_action('wp', array(__CLASS__, 'schedule_cron'));
        add_action('seoone_daily_metrics_fetch', array(__CLASS__, 'fetch_metrics'));

        // メール送信イベント
        add_action('seoone_daily_email', array(__CLASS__, 'send_daily_email'));

        // REST API ルートを登録
        add_action('rest_api_init', array(__CLASS__, 'register_rest_routes'));

        // スクリプト読込
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_dashboard_scripts'));

        // CSV エクスポートハンドラ
        add_action('admin_post_seoone_export_metrics', array(__CLASS__, 'export_metrics'));
    }

    /**
     * REST API ルートを登録します。
     * /wp-json/seoone/v1/update-metrics への POST リクエストに応答し、
     * 認証済みユーザーであればメトリクスを保存します。
     */
    public static function register_rest_routes() {
        register_rest_route(
            'seoone/v1',
            '/update-metrics',
            array(
                'methods'  => 'POST',
                'callback' => array(__CLASS__, 'update_metrics'),
                'permission_callback' => function() {
                    return current_user_can(SEOONE_CAP);
                },
            )
        );
    }

    /**
     * REST API からメトリクスを更新する
     */
    public static function update_metrics( $request ) {
        $metrics = $request->get_json_params();
        if ( ! is_array( $metrics ) ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => 'invalid payload' ), 400 );
        }
        $metrics['fetched_at'] = current_time('mysql');
        update_option('seoone_metrics', $metrics);
        return new WP_REST_Response( array( 'success' => true ), 200 );
    }

    /**
     * メニュー登録
     */
    public static function register_menu() {
        add_submenu_page(
            'seoone',
            'メトリクス',
            'メトリクス',
            SEOONE_CAP,
            'seoone-metrics',
            array(__CLASS__, 'render_metrics_page')
        );
    }

    /**
     * ダッシュボードメニューを登録
     */
    public static function register_dashboard_menu() {
        add_submenu_page(
            'seoone',
            'メトリクスダッシュボード',
            'メトリクスダッシュボード',
            SEOONE_CAP,
            'seoone-metrics-dashboard',
            array(__CLASS__, 'render_dashboard_page')
        );
    }

    /**
     * ダッシュボードページ用のスクリプトを読み込む
     */
    public static function enqueue_dashboard_scripts($hook) {
        // 現在の画面が当ページでなければ読み込まない
        if ( ! isset($_GET['page']) || $_GET['page'] !== 'seoone-metrics-dashboard' ) {
            return;
        }
        // Chart.js を CDN から読み込み
        wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), null );
    }

    /**
     * ダッシュボードページの描画
     */
    public static function render_dashboard_page() {
        if ( ! current_user_can(SEOONE_CAP) ) return;
        // 履歴データを取得
        $history = get_option('seoone_metrics_history', array());
        // 日付のリストと各メトリクス配列を用意
        $dates = array();
        $clicks = $impressions = $ctr = $position = $sessions = $active_users = $conversions = array();
        foreach ( $history as $entry ) {
            $dates[] = isset($entry['fetched_at']) ? $entry['fetched_at'] : '';
            $clicks[] = $entry['clicks'] ?? 0;
            $impressions[] = $entry['impressions'] ?? 0;
            $ctr[] = isset($entry['ctr']) ? round($entry['ctr'] * 100, 2) : 0;
            $position[] = $entry['position'] ?? 0;
            $sessions[] = $entry['sessions'] ?? 0;
            $active_users[] = $entry['active_users'] ?? 0;
            $conversions[] = $entry['conversions'] ?? 0;
        }
        echo '<div class="wrap"><h1>メトリクスダッシュボード</h1>';
        if ( empty($history) ) {
            echo '<p>まだメトリクスが取得されていません。CRON または fetch_metrics.py を実行してください。</p></div>';
            return;
        }
        // JSON にエスケープ
        $data = array(
            'dates' => $dates,
            'clicks' => $clicks,
            'impressions' => $impressions,
            'ctr' => $ctr,
            'position' => $position,
            'sessions' => $sessions,
            'active_users' => $active_users,
            'conversions' => $conversions,
        );
        $json = wp_json_encode($data);
        echo '<canvas id="seoone-metrics-chart" width="800" height="400"></canvas>';
        echo '<script type="text/javascript">(function(){
            const data = ' . $json . ';
            const ctx = document.getElementById("seoone-metrics-chart").getContext("2d");
            const chart = new Chart(ctx, {
                type: "line",
                data: {
                    labels: data.dates,
                    datasets: [
                        { label: "Clicks", data: data.clicks, borderColor: "#1e90ff", fill: false },
                        { label: "Impressions", data: data.impressions, borderColor: "#32cd32", fill: false },
                        { label: "CTR (%)", data: data.ctr, borderColor: "#ff8c00", fill: false },
                        { label: "Position", data: data.position, borderColor: "#ff1493", fill: false },
                        { label: "Sessions", data: data.sessions, borderColor: "#8a2be2", fill: false },
                        { label: "Active Users", data: data.active_users, borderColor: "#20b2aa", fill: false },
                        { label: "Conversions", data: data.conversions, borderColor: "#dc143c", fill: false }
                    ]
                },
                options: {
                    scales: {
                        y: { beginAtZero: true }
                    },
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        })();</script>';
        echo '</div>';
    }

    /**
     * CRON イベントをスケジュール
     */
    public static function schedule_cron() {
        // 設定から取得間隔を取得
        $settings = get_option('seoone_settings', array());
        $interval = isset($settings['metrics_interval']) ? $settings['metrics_interval'] : 'daily';
        // 既存のスケジュールを解除
        $timestamp = wp_next_scheduled( 'seoone_daily_metrics_fetch' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'seoone_daily_metrics_fetch' );
        }
        // 予定されていなければ新たにスケジュール
        if ( ! wp_next_scheduled('seoone_daily_metrics_fetch') ) {
            wp_schedule_event( time(), $interval, 'seoone_daily_metrics_fetch' );
        }

        // メール送信スケジュール
        // 既存のメールイベントを解除
        $email_ts = wp_next_scheduled( 'seoone_daily_email' );
        if ( $email_ts ) {
            wp_unschedule_event( $email_ts, 'seoone_daily_email' );
        }
        // メール送信が有効な場合はデイリーでスケジュール
        if ( ! empty( $settings['send_email'] ) ) {
            if ( ! wp_next_scheduled( 'seoone_daily_email' ) ) {
                wp_schedule_event( time() + 120, 'daily', 'seoone_daily_email' );
            }
        }
    }

    /**
     * メトリクス履歴を CSV として出力
     */
    public static function export_metrics() {
        if ( ! current_user_can( SEOONE_CAP ) ) {
            wp_die( '権限がありません' );
        }
        check_admin_referer( 'seoone_export_metrics' );
        $history = get_option( 'seoone_metrics_history', array() );
        // CSV ヘッダ
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="seoone-metrics-' . date('YmdHis') . '.csv"' );
        $output = fopen( 'php://output', 'w' );
        // ヘッダー行
        fputcsv( $output, array( 'fetched_at', 'clicks', 'impressions', 'ctr', 'position', 'sessions', 'active_users', 'conversions' ) );
        foreach ( $history as $row ) {
            $fetched_at   = $row['fetched_at'] ?? '';
            $clicks       = $row['clicks'] ?? 0;
            $impressions  = $row['impressions'] ?? 0;
            $ctr          = isset( $row['ctr'] ) ? $row['ctr'] : 0;
            $position     = $row['position'] ?? 0;
            $sessions     = $row['sessions'] ?? 0;
            $active_users = $row['active_users'] ?? 0;
            $conversions  = $row['conversions'] ?? 0;
            fputcsv( $output, array( $fetched_at, $clicks, $impressions, $ctr, $position, $sessions, $active_users, $conversions ) );
        }
        fclose( $output );
        exit;
    }

    /**
     * 日次メトリクスをメールで送信
     */
    public static function send_daily_email() {
        $settings = get_option( 'seoone_settings', array() );
        // メール送信がオフの場合は何もしない
        if ( empty( $settings['send_email'] ) ) return;
        $history = get_option( 'seoone_metrics_history', array() );
        if ( empty( $history ) || ! is_array( $history ) ) return;
        $latest = end( $history );
        if ( ! $latest ) return;
        $lines = array();
        $lines[] = 'SEO ONE 日次レポート';
        $lines[] = '';
        $lines[] = '計測日時: ' . ( $latest['fetched_at'] ?? current_time('mysql') );
        $lines[] = 'クリック数: ' . ( $latest['clicks'] ?? '0' );
        $lines[] = '表示回数: ' . ( $latest['impressions'] ?? '0' );
        $ctr  = isset( $latest['ctr'] ) ? round( $latest['ctr'] * 100, 2 ) : 0;
        $lines[] = 'CTR: ' . $ctr . '%';
        $lines[] = '平均順位: ' . ( $latest['position'] ?? '0' );
        $lines[] = 'セッション数: ' . ( $latest['sessions'] ?? '0' );
        $lines[] = 'アクティブユーザー: ' . ( $latest['active_users'] ?? '0' );
        $lines[] = 'コンバージョン: ' . ( $latest['conversions'] ?? '0' );
        $lines[] = '';
        // トップクエリ
        $top = get_option( 'seoone_metrics_top_queries', array() );
        if ( ! empty( $top ) ) {
            $lines[] = 'トップ検索クエリ:';
            $rank = 1;
            foreach ( $top as $row ) {
                $q = sanitize_text_field( $row['query'] ?? '' );
                $cl = $row['clicks'] ?? 0;
                $impr = $row['impressions'] ?? 0;
                $ctrp = isset( $row['ctr'] ) ? round( $row['ctr'] * 100, 2 ) : 0;
                $lines[] = sprintf( '%d. %s (クリック: %s, 表示: %s, CTR: %s%%)', $rank, $q, $cl, $impr, $ctrp );
                $rank++;
                if ( $rank > 10 ) break;
            }
        }
        $message = implode( "\n", $lines );
        $to = get_option( 'admin_email' );
        if ( ! empty( $to ) ) {
            wp_mail( $to, 'SEO ONE 日次レポート', $message );
        }
    }

    /**
     * メトリクスを取得して保存
     *
     * ここで Search Console API や GA4 Data API を呼び出してデータを取得し、
     * `seoone_metrics` オプションに保存します。実装には Google API PHP クライアント
     * (`google/apiclient`) を利用し、OAuth 認証やサービスアカウント認証を設定してください。
     */
    public static function fetch_metrics() {
        // Python スクリプトを実行してメトリクスを取得します。
        $script = SEOONE_PLUGIN_DIR . 'fetch_metrics.py';
        if ( ! file_exists( $script ) ) {
            // スクリプトが存在しない場合は空のメトリクスを保存
            $metrics = array(
                'clicks'      => 0,
                'impressions' => 0,
                'ctr'         => 0,
                'position'    => 0,
                'sessions'    => 0,
                'active_users'=> 0,
                'conversions' => 0,
                'fetched_at'  => current_time('mysql'),
            );
            update_option('seoone_metrics', $metrics);
            return;
        }
        // コマンド生成。環境に応じて python3/python を検出
        $python = 'python3';
        // 設定から環境変数を取得
        $settings = get_option('seoone_settings', array());
        $env_parts = array();
        if ( ! empty($settings['ga4_property_id']) ) {
            $env_parts[] = 'GA4_PROPERTY_ID=' . escapeshellarg($settings['ga4_property_id']);
        }
        if ( ! empty($settings['gsc_site_url']) ) {
            $env_parts[] = 'GSC_SITE_URL=' . escapeshellarg($settings['gsc_site_url']);
        }
        if ( ! empty($settings['service_account_path']) ) {
            $env_parts[] = 'GOOGLE_APPLICATION_CREDENTIALS=' . escapeshellarg($settings['service_account_path']);
        }
        $env_prefix = !empty($env_parts) ? implode(' ', $env_parts) . ' ' : '';
        $cmd = $env_prefix . escapeshellcmd( $python . ' ' . $script . ' --json' );
        $output = @shell_exec( $cmd );
        $metrics = json_decode( trim( $output ), true );
        if ( ! is_array( $metrics ) ) {
            $metrics = array(
                'clicks'      => 0,
                'impressions' => 0,
                'ctr'         => 0,
                'position'    => 0,
                'sessions'    => 0,
                'active_users'=> 0,
                'conversions' => 0,
            );
        }
        $metrics['fetched_at'] = current_time( 'mysql' );
        update_option( 'seoone_metrics', $metrics );

        // Top queries を保存（あれば）
        if ( isset( $metrics['top_queries'] ) ) {
            update_option( 'seoone_metrics_top_queries', $metrics['top_queries'] );
        }

        // 履歴を保存（直近90件まで）
        $history = get_option('seoone_metrics_history', array());
        $history[] = $metrics;
        // 最大90件を保持
        if ( count($history) > 90 ) {
            $history = array_slice($history, -90);
        }
        update_option('seoone_metrics_history', $history);
    }

    /**
     * メトリクスページの描画
     */
    public static function render_metrics_page() {
        if ( ! current_user_can(SEOONE_CAP) ) return;
        $metrics = get_option('seoone_metrics', array());
        echo '<div class="wrap"><h1>SEO ONE メトリクス</h1>';
        if ( empty($metrics) ) {
            echo '<p>メトリクスがまだ取得されていません。</p>';
        } else {
            echo '<table class="widefat"><thead><tr>';
            echo '<th>項目</th><th>値</th></tr></thead><tbody>';
            foreach ( $metrics as $key => $val ) {
                // top_queries は別テーブルで表示するためスキップ
                if ( $key === 'top_queries' ) continue;
                echo '<tr><th>'.esc_html($key).'</th><td>'.esc_html($val).'</td></tr>';
            }
            echo '</tbody></table>';
            echo '<p>最終取得: '.esc_html( $metrics['fetched_at'] ?? '' ).'</p>';
            // Top queries 表示
            $top_queries = get_option( 'seoone_metrics_top_queries', array() );
            if ( ! empty( $top_queries ) ) {
                echo '<h2>トップ検索クエリ</h2>';
                echo '<table class="widefat"><thead><tr><th>クエリ</th><th>クリック</th><th>表示回数</th><th>CTR</th><th>平均順位</th></tr></thead><tbody>';
                foreach ( $top_queries as $row ) {
                    echo '<tr>';
                    echo '<td>'.esc_html( $row['query'] ).'</td>';
                    echo '<td>'.esc_html( $row['clicks'] ).'</td>';
                    echo '<td>'.esc_html( $row['impressions'] ).'</td>';
                    echo '<td>'.esc_html( round( (float)$row['ctr'] * 100, 2 ) ).'%</td>';
                    echo '<td>'.esc_html( round( (float)$row['position'], 2 ) ).'</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';

                // 改善が見込めるクエリのリスト（CTR>5% かつ 平均順位>10）
                $improvement = array();
                foreach ( $top_queries as $row ) {
                    $ctr_pct = isset( $row['ctr'] ) ? (float)$row['ctr'] * 100 : 0;
                    $pos = isset( $row['position'] ) ? (float)$row['position'] : 0;
                    if ( $ctr_pct > 5 && $pos > 10 ) {
                        $improvement[] = $row;
                    }
                }
                if ( ! empty( $improvement ) ) {
                    echo '<h3>改善が見込めるクエリ</h3>';
                    echo '<p>CTR が 5% を超え、平均順位が 10 位より後のクエリは、SEO 改善や新規記事作成の候補になります。</p>';
                    echo '<table class="widefat"><thead><tr><th>クエリ</th><th>CTR (%)</th><th>平均順位</th></tr></thead><tbody>';
                    foreach ( $improvement as $row ) {
                        $ctr_pct = round( (float)$row['ctr'] * 100, 2 );
                        $pos = round( (float)$row['position'], 2 );
                        echo '<tr>';
                        echo '<td>'.esc_html( $row['query'] ).'</td>';
                        echo '<td>'.esc_html( $ctr_pct ).'%</td>';
                        echo '<td>'.esc_html( $pos ).'</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                }
            }

            // CSV エクスポートフォーム
            echo '<h2>メトリクス履歴のエクスポート</h2>';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
            wp_nonce_field( 'seoone_export_metrics' );
            echo '<input type="hidden" name="action" value="seoone_export_metrics">';
            submit_button( 'CSV エクスポート', 'secondary' );
            echo '</form>';
        }
        echo '<p>Search Console や GA4 の API 認証情報は「せってい」画面の「メトリクス設定」で設定します。</p>';
        echo '</div>';
    }
}