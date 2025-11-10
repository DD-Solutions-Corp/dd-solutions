<?php
/**
 * A/B テスト機能
 *
 * 投稿ごとにタイトル・FAQ・OGP のバリアントを設定し、ランダムに配信する機能です。
 * 有意差判定や勝者採用などの高度なロジックは含まず、基本的なランダム配信と記録のみを実装しています。
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class SeoOne_ABTest {
    public static function init() {
        // 管理画面のメタボックス
        add_action('add_meta_boxes', array(__CLASS__, 'add_meta_boxes'));
        add_action('save_post', array(__CLASS__, 'save_post')); 
        // フロントエンドでバリアントを適用
        add_action('template_redirect', array(__CLASS__, 'assign_variant_cookie'));
        add_filter('the_title', array(__CLASS__, 'filter_title'), 10, 2);
        add_filter('the_content', array(__CLASS__, 'filter_content'));
        add_action('wp_head', array(__CLASS__, 'inject_ogp'), 1);

        // ビューの記録とショートコード登録
        add_action('wp', array(__CLASS__, 'record_view'));
        add_action('init', array(__CLASS__, 'register_shortcodes'));

        // 結果表示メニュー
        add_action('admin_menu', array(__CLASS__, 'register_results_page'));

        // 管理画面アクションの処理
        add_action('admin_init', array(__CLASS__, 'handle_admin_actions'));
    }

    /**
     * 投稿編集画面にメタボックスを追加
     */
    public static function add_meta_boxes() {
        add_meta_box(
            'seoone_abtest',
            'A/B テスト (タイトル/FAQ/OGP)',
            array(__CLASS__, 'render_meta_box'),
            'post',
            'normal',
            'default'
        );
    }

    /**
     * メタボックスの内容
     */
    public static function render_meta_box($post) {
        wp_nonce_field('seoone_abtest_meta_box','seoone_abtest_meta_box_nonce');
        $enabled = get_post_meta($post->ID, '_seoone_abtest_enabled', true);
        $title_b = get_post_meta($post->ID, '_seoone_abtest_title_b', true);
        $faq_b   = get_post_meta($post->ID, '_seoone_abtest_faq_b', true);
        $ogp_b   = get_post_meta($post->ID, '_seoone_abtest_ogp_b', true);
        echo '<p><label><input type="checkbox" name="seoone_abtest_enabled" value="1" '.checked($enabled, '1', false).' /> A/B テストを有効にする</label></p>';
        echo '<p><strong>バリアントBのタイトル</strong><br/>';
        echo '<input type="text" name="seoone_abtest_title_b" value="'.esc_attr($title_b).'" class="widefat" /></p>';
        echo '<p><strong>バリアントBのFAQ (HTML可)</strong><br/>';
        echo '<textarea name="seoone_abtest_faq_b" rows="4" class="widefat">'.esc_textarea($faq_b).'</textarea></p>';
        echo '<p><strong>バリアントBのOGPタイトル</strong><br/>';
        echo '<input type="text" name="seoone_abtest_ogp_b" value="'.esc_attr($ogp_b).'" class="widefat" /></p>';
    }

    /**
     * メタデータ保存
     */
    public static function save_post($post_id) {
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
        if ( ! isset($_POST['seoone_abtest_meta_box_nonce']) || ! wp_verify_nonce($_POST['seoone_abtest_meta_box_nonce'],'seoone_abtest_meta_box') ) return;
        if ( ! current_user_can('edit_post', $post_id) ) return;
        $enabled = ! empty($_POST['seoone_abtest_enabled']) ? '1' : '0';
        update_post_meta($post_id, '_seoone_abtest_enabled', $enabled);
        $fields = array(
            '_seoone_abtest_title_b' => sanitize_text_field($_POST['seoone_abtest_title_b'] ?? ''),
            '_seoone_abtest_faq_b'   => wp_kses_post($_POST['seoone_abtest_faq_b'] ?? ''),
            '_seoone_abtest_ogp_b'   => sanitize_text_field($_POST['seoone_abtest_ogp_b'] ?? ''),
        );
        foreach ( $fields as $key => $val ) {
            update_post_meta($post_id, $key, $val);
        }
    }

    /**
     * ユーザーごとにバリアントをランダムに割り当てる Cookie をセット
     */
    public static function assign_variant_cookie() {
        if ( is_admin() || wp_doing_ajax() ) return;
        if ( isset($_COOKIE['seoone_ab_variant']) ) return;
        // 0: variant A, 1: variant B
        $variant = ( rand(0,1) === 0 ) ? 'A' : 'B';
        setcookie('seoone_ab_variant', $variant, time() + 3600*24*30, COOKIEPATH, COOKIE_DOMAIN);
        $_COOKIE['seoone_ab_variant'] = $variant;
    }

    /**
     * 現在表示している投稿のインプレッション（ビュー）を記録
     */
    public static function record_view() {
        if ( is_admin() || wp_doing_ajax() ) return;
        if ( ! is_singular('post') ) return;
        global $post;
        // ABテストが有効な場合のみ記録
        if ( '1' === get_post_meta($post->ID, '_seoone_abtest_enabled', true) ) {
            $variant = $_COOKIE['seoone_ab_variant'] ?? 'A';
            $key = 'impressions_' . strtoupper($variant);
            self::increment_stat($post->ID, $key);
        }
    }

    /**
     * ショートコードを登録
     * [seoone_conversion] を記事内に設置するとコンバージョンを記録します。
     */
    public static function register_shortcodes() {
        add_shortcode('seoone_conversion', array(__CLASS__, 'shortcode_conversion'));
    }

    /**
     * コンバージョン用ショートコードのコールバック
     */
    public static function shortcode_conversion($atts = [], $content = null) {
        if ( is_singular('post') ) {
            global $post;
            self::record_conversion($post->ID);
        }
        return '';
    }

    /**
     * コンバージョンを記録
     */
    public static function record_conversion($post_id) {
        if ( '1' !== get_post_meta($post_id, '_seoone_abtest_enabled', true) ) return;
        $variant = $_COOKIE['seoone_ab_variant'] ?? 'A';
        $key = 'conversions_' . strtoupper($variant);
        self::increment_stat($post_id, $key);
    }

    /**
     * 統計データを取得
     */
    protected static function get_stats($post_id) {
        $stats = get_option('seoone_abtest_stats_' . $post_id, array());
        $defaults = array(
            'impressions_A' => 0,
            'impressions_B' => 0,
            'conversions_A' => 0,
            'conversions_B' => 0,
        );
        return wp_parse_args($stats, $defaults);
    }

    /**
     * 統計データを増加させる
     */
    protected static function increment_stat($post_id, $key) {
        $stats = self::get_stats($post_id);
        if ( isset($stats[$key]) ) {
            $stats[$key]++;
        } else {
            $stats[$key] = 1;
        }
        update_option('seoone_abtest_stats_' . $post_id, $stats);
    }

    /**
     * A/Bテスト結果ページを登録
     */
    public static function register_results_page() {
        add_submenu_page(
            'seoone',
            'A/B テスト結果',
            'A/B テスト結果',
            SEOONE_CAP,
            'seoone-abtest-results',
            array(__CLASS__, 'render_results_page')
        );
    }

    /**
     * 結果ページの表示
     */
    public static function render_results_page() {
        if ( ! current_user_can(SEOONE_CAP) ) return;
        echo '<div class="wrap"><h1>A/B テスト結果</h1>';
        // 採用メッセージ
        if ( isset($_GET['adopted']) ) {
            $post_id = intval($_GET['adopted']);
            $post_title = get_the_title($post_id);
            echo '<div class="updated notice"><p>投稿「' . esc_html($post_title) . '」のバリアントBを採用しました。</p></div>';
        }
        // 対象となる投稿を取得
        $args = array(
            'post_type'      => 'post',
            'post_status'    => 'any',
            'meta_key'       => '_seoone_abtest_enabled',
            'meta_value'     => '1',
            'posts_per_page' => -1,
        );
        $posts = get_posts($args);
        if ( empty($posts) ) {
            echo '<p>A/B テストが有効な投稿はありません。</p></div>';
            return;
        }
        echo '<table class="widefat"><thead><tr><th>投稿タイトル</th><th>A インプレッション</th><th>B インプレッション</th><th>A CV</th><th>B CV</th><th>A CVR</th><th>B CVR</th><th>p値</th><th>勝者</th><th>操作</th></tr></thead><tbody>';
        foreach ( $posts as $post ) {
            $stats = self::get_stats($post->ID);
            $imprA = (int) $stats['impressions_A'];
            $imprB = (int) $stats['impressions_B'];
            $convA = (int) $stats['conversions_A'];
            $convB = (int) $stats['conversions_B'];
            $cvrA = ($imprA > 0) ? $convA / $imprA : 0;
            $cvrB = ($imprB > 0) ? $convB / $imprB : 0;
            $pvalue = self::calculate_p_value($imprA, $convA, $imprB, $convB);
            $winner = '';
            if ( $pvalue < 0.05 ) {
                if ( $cvrA > $cvrB ) {
                    $winner = 'A';
                } elseif ( $cvrB > $cvrA ) {
                    $winner = 'B';
                }
            }
            echo '<tr>';
            echo '<td>' . esc_html( get_the_title($post) ) . '</td>';
            echo '<td>' . esc_html( $imprA ) . '</td>';
            echo '<td>' . esc_html( $imprB ) . '</td>';
            echo '<td>' . esc_html( $convA ) . '</td>';
            echo '<td>' . esc_html( $convB ) . '</td>';
            echo '<td>' . esc_html( number_format( $cvrA * 100, 2 ) ) . '%</td>';
            echo '<td>' . esc_html( number_format( $cvrB * 100, 2 ) ) . '%</td>';
            echo '<td>' . esc_html( $pvalue === null ? '-' : sprintf( '%.4f', $pvalue ) ) . '</td>';
            echo '<td>' . esc_html( $winner ) . '</td>';
            // 操作列: バリアントBが勝者かつまだテスト有効なら採用リンクを表示
            echo '<td>';
            $enabled = get_post_meta($post->ID, '_seoone_abtest_enabled', true);
            if ( $winner === 'B' && $enabled === '1' ) {
                $url = wp_nonce_url( add_query_arg( array(
                    'page' => 'seoone-abtest-results',
                    'seoone_abtest_action' => 'adopt_b',
                    'post_id' => $post->ID,
                ), admin_url('admin.php') ), 'seoone_abtest_adopt_' . $post->ID );
                echo '<a href="' . esc_url($url) . '" class="button">バリアントBを採用</a>';
            } else {
                echo '-';
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<p>p値が 0.05 未満で、CVR が高い方が「勝者」と表示されます。必要に応じて手動でコンテンツを更新してください。</p>';
        echo '</div>';
    }

    /**
     * 2つの比率の検定（z検定）を行い p 値を返す。
     * インプレッションとコンバージョン数から導出します。
     * 統計計算が不可能な場合は null を返します。
     */
    protected static function calculate_p_value($n1, $x1, $n2, $x2) {
        if ( $n1 <= 0 || $n2 <= 0 ) {
            return null;
        }
        $p1 = $x1 / $n1;
        $p2 = $x2 / $n2;
        $p  = ($x1 + $x2) / ($n1 + $n2);
        if ( $p === 0 || $p === 1 ) {
            return null;
        }
        $z = ($p1 - p2) / sqrt( $p * (1 - $p) * (1 / $n1 + 1 / $n2) );
        // 両側検定のp値
        $pvalue = 2 * (1 - self::phi(abs($z)));
        return $pvalue;
    }

    /**
     * 正規分布の累積分布関数（標準正規）。
     * エラーファンクション erf() を利用して計算します。
     */
    protected static function phi($z) {
        return 0.5 * (1 + self::erf($z / sqrt(2)));
    }

    /**
     * 誤差関数 erf の近似計算。
     * この実装は Abramowitz and Stegun の式を基にしています。
     * PHP に内蔵の erf() 関数が存在する場合はそちらを利用します。
     */
    protected static function erf($x) {
        if ( function_exists('erf') ) {
            return erf($x);
        }
        // 近似計算
        $sign = ($x >= 0) ? 1 : -1;
        $x = abs($x);
        $t = 1.0 / (1.0 + 0.5 * $x);
        $tau = $t * exp(
            -$x * $x
            - 1.26551223
            + 1.00002368  * $t
            + 0.37409196  * pow($t, 2)
            + 0.09678418  * pow($t, 3)
            - 0.18628806  * pow($t, 4)
            + 0.27886807  * pow($t, 5)
            - 1.13520398  * pow($t, 6)
            + 1.48851587  * pow($t, 7)
            - 0.82215223  * pow($t, 8)
            + 0.17087277  * pow($t, 9)
        );
        return $sign * (1 - $tau);
    }

    /**
     * 管理画面からのアクションを処理
     */
    public static function handle_admin_actions() {
        if ( ! current_user_can('edit_posts') ) {
            return;
        }
        if ( isset($_GET['seoone_abtest_action']) && $_GET['seoone_abtest_action'] === 'adopt_b' && isset($_GET['post_id']) ) {
            $post_id = intval($_GET['post_id']);
            check_admin_referer('seoone_abtest_adopt_' . $post_id);
            self::adopt_variant_b($post_id);
            // リダイレクトしてメッセージを表示
            $redirect = add_query_arg(array(
                'page' => 'seoone-abtest-results',
                'adopted' => $post_id,
            ), admin_url('admin.php'));
            wp_safe_redirect($redirect);
            exit;
        }
    }

    /**
     * バリアントBを本採用して A/B テストを終了
     */
    protected static function adopt_variant_b($post_id) {
        $post = get_post($post_id);
        if ( ! $post || 'post' !== $post->post_type ) return;
        $enabled = get_post_meta($post_id, '_seoone_abtest_enabled', true);
        if ( '1' !== $enabled ) return;
        // 変数取得
        $title_b = get_post_meta($post_id, '_seoone_abtest_title_b', true);
        $faq_b   = get_post_meta($post_id, '_seoone_abtest_faq_b', true);
        $ogp_b   = get_post_meta($post_id, '_seoone_abtest_ogp_b', true);
        // 投稿タイトルを更新
        if ( ! empty( $title_b ) ) {
            wp_update_post( array( 'ID' => $post_id, 'post_title' => $title_b ) );
        }
        // FAQ バリアントを本文中の [faq] 部分に置換して保存
        if ( ! empty( $faq_b ) ) {
            $content = $post->post_content;
            // [faq] タグを FAQ B で置換
            $content = str_replace('[faq]', $faq_b, $content);
            wp_update_post( array( 'ID' => $post_id, 'post_content' => $content ) );
        }
        // OGP タイトルを標準のカスタムフィールドとして保存
        if ( ! empty( $ogp_b ) ) {
            update_post_meta( $post_id, '_seoone_adopted_ogp_title', $ogp_b );
        }
        // A/B テストを無効化
        update_post_meta($post_id, '_seoone_abtest_enabled', '0');
    }

    /**
     * タイトルフィルタ
     */
    public static function filter_title($title, $post_id) {
        $post = get_post($post_id);
        if ( $post && $post->post_type === 'post' ) {
            if ( '1' === get_post_meta($post_id, '_seoone_abtest_enabled', true) ) {
                $variant = $_COOKIE['seoone_ab_variant'] ?? 'A';
                if ( $variant === 'B' ) {
                    $title_b = get_post_meta($post_id, '_seoone_abtest_title_b', true);
                    if ( ! empty($title_b) ) {
                        return $title_b;
                    }
                }
            }
        }
        return $title;
    }

    /**
     * コンテンツフィルタ（FAQ 部分のみバリアントを差し替え）
     */
    public static function filter_content($content) {
        if ( is_singular('post') ) {
            global $post;
            if ( '1' === get_post_meta($post->ID, '_seoone_abtest_enabled', true) ) {
                $variant = $_COOKIE['seoone_ab_variant'] ?? 'A';
                if ( $variant === 'B' ) {
                    $faq_b = get_post_meta($post->ID, '_seoone_abtest_faq_b', true);
                    if ( ! empty($faq_b) ) {
                        // FAQ 部分の置換。ここでは簡易に [faq] 短コードを置換します
                        $content = str_replace('[faq]', $faq_b, $content);
                    }
                }
            }
        }
        return $content;
    }

    /**
     * OGP タグを差し替え
     */
    public static function inject_ogp() {
        if ( ! is_singular('post') ) return;
        global $post;
        $enabled = get_post_meta($post->ID, '_seoone_abtest_enabled', true);
        if ( '1' !== $enabled ) {
            // テストが終了している場合、採用されたOGPタイトルがあればそれを出力
            $adopted_ogp = get_post_meta($post->ID, '_seoone_adopted_ogp_title', true);
            if ( $adopted_ogp ) {
                echo '<meta property="og:title" content="'.esc_attr($adopted_ogp).'" />\n';
            }
            return;
        }
        $variant = $_COOKIE['seoone_ab_variant'] ?? 'A';
        if ( $variant === 'B' ) {
            $ogp_b = get_post_meta($post->ID, '_seoone_abtest_ogp_b', true);
            if ( ! empty($ogp_b) ) {
                echo '<meta property="og:title" content="'.esc_attr($ogp_b).'" />\n';
            }
        }
    }
}