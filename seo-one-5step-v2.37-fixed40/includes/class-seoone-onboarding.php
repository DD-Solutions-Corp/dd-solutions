<?php
/**
 * 初期セットアップのためのオンボーディングウィザード
 *
 * プラグインインストール後、必要な設定（AI APIキー、メトリクス設定など）を案内するページを提供します。
 * 完了するとダッシュボードにリダイレクトし、今後この案内は表示されません。
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class SeoOne_Onboarding {
    /**
     * 初期化
     */
    public static function init() {
        $self = new self();
        add_action( 'admin_menu', [ $self, 'register_menu' ] );
        add_action( 'admin_notices', [ $self, 'admin_notice' ] );
    }

    /**
     * メニュー登録
     */
    public function register_menu() {
        /*
         * 初期設定ウィザードのサブメニューは、オンボーディングが
         * 完了している場合は表示しません。完了フラグはプラグイン
         * 有効化時に false に設定され、ウィザードの最終ステップで
         * true に更新されます。これにより、ユーザーが初期設定を
         * 済ませたあとサイドバーに「初期設定ガイド」が残ってしまう
         * 問題を解消します。
         */
        $onboarded = get_option( 'seoone_onboarded', false );
        if ( $onboarded ) {
            return;
        }
        add_submenu_page(
            'seoone',
            '初期設定ガイド',
            '初期設定ガイド',
            SEOONE_CAP,
            'seoone-onboarding',
            [ $this, 'render_page' ]
        );
    }

    /**
     * オンボーディングガイド表示
     */
    public function render_page() {
        if ( ! current_user_can( SEOONE_CAP ) ) {
            return;
        }

        // Determine the current step
        $step = isset( $_GET['step'] ) ? intval( $_GET['step'] ) : 1;
        // Load existing settings
        $settings = get_option( 'seoone_settings', array() );

        /*
         * POST リクエストの処理を先に行います。
         * ユーザーがフォームを送信した場合、画面に何も出力する前に
         * 設定の保存とリダイレクトを行うことで、"headers already sent"
         * 警告を回避します。
         */
        if ( $step === 1 ) {
            if ( isset( $_POST['seoone_onboard_nonce'] ) && wp_verify_nonce( $_POST['seoone_onboard_nonce'], 'seoone_onboard_step1' ) ) {
                $api_key = sanitize_text_field( wp_unslash( $_POST['ai_api_key'] ?? '' ) );
                $ai_model_default   = sanitize_text_field( wp_unslash( $_POST['ai_model'] ?? '' ) );
                $ai_model_retrieval = sanitize_text_field( wp_unslash( $_POST['ai_model_retrieval'] ?? '' ) );
                $ai_model_draft     = sanitize_text_field( wp_unslash( $_POST['ai_model_draft'] ?? '' ) );
                $ai_model_coding    = sanitize_text_field( wp_unslash( $_POST['ai_model_coding'] ?? '' ) );
                $ai_model_scoring   = sanitize_text_field( wp_unslash( $_POST['ai_model_scoring'] ?? '' ) );
                $ai_model_factcheck = sanitize_text_field( wp_unslash( $_POST['ai_model_factcheck'] ?? '' ) );
                $settings['ai_api_key']         = $api_key;
                $settings['ai_model']           = $ai_model_default;
                $settings['ai_model_retrieval'] = $ai_model_retrieval;
                $settings['ai_model_draft']     = $ai_model_draft;
                $settings['ai_model_coding']    = $ai_model_coding;
                $settings['ai_model_scoring']   = $ai_model_scoring;
                $settings['ai_model_fact']      = $ai_model_factcheck;
                // 既存設定を保持しつつ上書きします
                $existing = get_option( 'seoone_settings', array() );
                $merged   = array_merge( $existing, $settings );
                update_option( 'seoone_settings', $merged );
                wp_safe_redirect( admin_url( 'admin.php?page=seoone-onboarding&step=2' ) );
                exit;
            }
        } elseif ( $step === 2 ) {
            if ( isset( $_POST['seoone_onboard_nonce'] ) && wp_verify_nonce( $_POST['seoone_onboard_nonce'], 'seoone_onboard_step2' ) ) {
                $settings['ga4_property_id']      = sanitize_text_field( wp_unslash( $_POST['ga4_property_id'] ?? '' ) );
                $settings['gsc_site_url']         = sanitize_text_field( wp_unslash( $_POST['gsc_site_url'] ?? '' ) );
                $settings['service_account_path'] = sanitize_text_field( wp_unslash( $_POST['service_account_path'] ?? '' ) );
                $settings['metrics_interval']     = sanitize_text_field( wp_unslash( $_POST['metrics_interval'] ?? 'daily' ) );
                $settings['send_email']           = isset( $_POST['send_email'] ) ? true : false;
                // 既存設定を保持しつつ上書きします
                $existing = get_option( 'seoone_settings', array() );
                $merged   = array_merge( $existing, $settings );
                update_option( 'seoone_settings', $merged );
                wp_safe_redirect( admin_url( 'admin.php?page=seoone-onboarding&step=3' ) );
                exit;
            }
        } else {
            // 完了ページ (step >= 3)
            if ( isset( $_POST['seoone_onboard_nonce'] ) && wp_verify_nonce( $_POST['seoone_onboard_nonce'], 'seoone_onboard_step3' ) ) {
                update_option( 'seoone_onboarded', true );
                wp_safe_redirect( admin_url( 'admin.php?page=seoone' ) );
                exit;
            }
        }

        // ここからページの描画を開始します。
        echo '<div class="wrap">';
        echo '<h1>SEO ONE 初期設定ガイド</h1>';

        if ( $step === 1 ) {
            echo '<h2>ステップ1: AI設定</h2>';
            echo '<p>OpenRouter APIキーとモデル名を入力してください。これによりAI機能が利用可能になります。</p>';
            echo '<p>SEO ONEは5つのAI工程（情報取得→初稿→コーディング→採点→ファクトチェック＋要約）で記事を生成します。以下では各工程で使用するモデルを個別に指定できます。空欄の場合はデフォルトモデルが使われます。</p>';
            echo '<form method="post" action="" class="seoone-admin-form">';
            wp_nonce_field( 'seoone_onboard_step1', 'seoone_onboard_nonce' );
            $ai_key_val   = $settings['ai_api_key'] ?? '';
            $ai_model_val = $settings['ai_model'] ?? 'gpt-4o';
            echo '<p>APIキー:</p>';
            echo '<input type="text" name="ai_api_key" value="' . esc_attr( $ai_key_val ) . '" placeholder="sk-..." required />';
            echo '<p>デフォルトモデル（全ステップ共通）:</p>';
            // モデル候補の定義
            $model_options = [
                '' => '（デフォルトモデルを使用）',
                'gpt-4o'            => 'GPT‑4o — 高精度・創造性 (≈$0.014/回)',
                'gpt-4o-mini'       => 'GPT‑4o‑mini — バランス型 (≈$0.002/回)',
                'gpt-4-turbo'       => 'GPT‑4‑turbo — 精度と速度の両立 (≈$0.012/回)',
                'claude-3-sonnet'   => 'Claude 3 Sonnet — 論理と創造性のバランス (≈$0.015/回)',
                'claude-3-haiku'    => 'Claude 3 Haiku — 高速・低コスト (≈$0.005/回)',
                'gpt-3.5-turbo'     => 'GPT‑3.5‑turbo — 低コスト・高速 (≈$0.0015/回)',
                'perplexity'        => 'Perplexity — 検索連携リサーチ (≈$0.015/回)',
                'perplexity-sonar'  => 'Perplexity Sonar — 深いリサーチ向け (検索費別途)',
                'gemini'            => 'Gemini Pro — 深い推論と最新情報 (≈$0.05/回)',
                'gemini-2.5-flash'  => 'Gemini Flash — 高速版 (≈$0.003/回)',
                'deepseek-coder-v2' => 'DeepSeek Coder V2 — コード生成に強い (≈$0.27/回)',
            ];
            echo '<select name="ai_model">';
            foreach ( $model_options as $val => $label ) {
                $selected = selected( $ai_model_val, $val, false );
                echo '<option value="' . esc_attr( $val ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
            }
            echo '</select>';

            /*
             * 各ステップで利用される工程の概要とおすすめモデルを説明します。
             * 詳細な説明と候補モデルは $model_options を再利用して
             * セレクトボックスを出力します。
             */

            $retrieval_val = $settings['ai_model_retrieval'] ?? '';
            $draft_val     = $settings['ai_model_draft'] ?? '';
            $coding_val    = $settings['ai_model_coding'] ?? '';
            $scoring_val   = $settings['ai_model_scoring'] ?? '';
            $fact_val      = $settings['ai_model_fact'] ?? '';
            $render_select = function( $field_name, $current_val, $title, $description ) use ( $model_options ) {
                echo '<p><strong>' . esc_html( $title ) . ':</strong><br><em>' . $description . '</em></p>';
                echo '<select name="' . esc_attr( $field_name ) . '">';
                foreach ( $model_options as $val => $label ) {
                    $selected = selected( $current_val, $val, false );
                    echo '<option value="' . esc_attr( $val ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
                }
                echo '</select>';
            };
            // 情報取得ステップ
            $render_select(
                'ai_model_retrieval',
                $retrieval_val,
                '情報取得ステップのモデル',
                '推奨: Perplexity, GPT‑4o, Claude 3 Sonnet — 検索連携により最新情報を取得しやすく、要約能力も高い。代替: GPT‑3.5‑turbo — 低コストだが精度はやや劣ります'
            );
            // 初稿ステップ
            $render_select(
                'ai_model_draft',
                $draft_val,
                '初稿ステップのモデル',
                '推奨: GPT‑4o, Claude 3 Sonnet — 創造性と論理性に優れる。代替: GPT‑3.5‑turbo — 低コストで素早い生成'
            );
            // コーディングステップ（旧：編集ステップ）
            $render_select(
                'ai_model_coding',
                $coding_val,
                'コーディングステップのモデル',
                '推奨: GPT‑4o — 文法とスタイルの精度が高い。代替: GPT‑3.5‑turbo — コスト重視の場合'
            );
            // 採点ステップ
            $render_select(
                'ai_model_scoring',
                $scoring_val,
                '採点ステップのモデル',
                '推奨: GPT‑4o — 基準に沿った評価が得意。代替: GPT‑3.5‑turbo — 簡易的な評価向け'
            );
            // ファクトチェックステップ
            $render_select(
                'ai_model_factcheck',
                $fact_val,
                'ファクトチェックステップのモデル',
                '推奨: GPT‑4o, Claude 3 Sonnet — 正確な言語理解と要約。代替: GPT‑3.5‑turbo — 簡易要約や低コスト'
            );
            echo '<button type="submit" class="button button-primary">次へ</button>';
            echo '</form>';
        } elseif ( $step === 2 ) {
            echo '<h2>ステップ2: メトリクス設定</h2>';
            echo '<p>GA4/Search Console 連携に必要な情報を入力してください。正しく設定するとメトリクスダッシュボードや日次レポートが利用できます。</p>';
            echo '<form method="post" action="" class="seoone-admin-form">';
            wp_nonce_field( 'seoone_onboard_step2', 'seoone_onboard_nonce' );
            $ga_id    = $settings['ga4_property_id'] ?? '';
            $gsc      = $settings['gsc_site_url'] ?? '';
            $service  = $settings['service_account_path'] ?? '';
            $interval = $settings['metrics_interval'] ?? 'daily';
            $send_email = $settings['send_email'] ?? false;
            echo '<p>GA4 プロパティID:</p>';
            echo '<input type="text" name="ga4_property_id" value="' . esc_attr( $ga_id ) . '" placeholder="123456789" required />';
            echo '<p>Search Console サイトURL:</p>';
            echo '<input type="text" name="gsc_site_url" value="' . esc_attr( $gsc ) . '" placeholder="https://example.com/" required />';
            echo '<p>サービスアカウントJSONのパス:</p>';
            echo '<input type="text" name="service_account_path" value="' . esc_attr( $service ) . '" placeholder="/path/to/key.json" required />';
            echo '<p>メトリクス取得間隔:</p>';
            echo '<select name="metrics_interval">';
            $intervals = array( 'hourly' => '1時間ごと', 'twicedaily' => '12時間ごと', 'daily' => '1日ごと' );
            foreach ( $intervals as $key => $label ) {
                $selected = selected( $interval, $key, false );
                echo '<option value="' . esc_attr( $key ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
            }
            echo '</select>';
            echo '<p><label><input type="checkbox" name="send_email" value="1" ' . checked( $send_email, true, false ) . '> 日次メールレポートを受け取る</label></p>';
            echo '<button type="submit" class="button button-primary">次へ</button>';
            echo '</form>';
        } else {
            echo '<h2>ステップ3: 完了</h2>';
            echo '<p>初期設定が完了しました！引き続き SEO ONE のダッシュボードや記事生成機能をお楽しみください。</p>';
            echo '<form method="post" action="" class="seoone-admin-form">';
            wp_nonce_field( 'seoone_onboard_step3', 'seoone_onboard_nonce' );
            echo '<button type="submit" class="button button-primary">完了してダッシュボードへ</button>';
            echo '</form>';
        }
        echo '</div>';
    }

    /**
     * 初期設定を促すアドミン通知
     */
    public function admin_notice() {
        if ( ! current_user_can( SEOONE_CAP ) ) return;
        $screen = get_current_screen();
        // インストール直後またはオンボーディング未完了の場合に表示
        $onboarded = get_option( 'seoone_onboarded', false );
        if ( $onboarded ) return;
        // プラグインページやSEO ONE 管理画面でのみ表示
        if ( strpos( $screen->id, 'seoone' ) !== false || 'plugins' === $screen->id ) {
            $url = admin_url( 'admin.php?page=seoone-onboarding' );
            echo '<div class="notice notice-info"><p><strong>SEO ONE:</strong> プラグインの初期設定がまだ完了していません。<a href="' . esc_url( $url ) . '">初期設定ガイド</a>を開き、必要な設定を行ってください。</p></div>';
        }
    }
}