<?php
/**
 * 外部リンクの状態を確認する簡易リンクチェッカー
 *
 * 公開済み記事の外部リンクを調査し、HTTP ステータスコードを確認します。
 * 404 以上のステータスは「リンク切れ」として表示されます。
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class SeoOne_LinkChecker {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
    }

    public static function register_menu() {
        add_submenu_page(
            'seoone',
            'リンクチェッカー',
            'リンクチェッカー',
            SEOONE_CAP,
            'seoone-link-checker',
            array( __CLASS__, 'render_page' )
        );
    }

    /**
     * 指定した URL の HEAD リクエストを送信し、ステータスコードを返します。
     * @param string $url
     * @return array {status, code, message}
     */
    protected static function check_url( $url ) {
        // ホストがローカル（同一サイト）の場合は OK とする
        if ( strpos( $url, home_url() ) !== false ) {
            return array( 'status' => 'internal', 'code' => 200, 'message' => 'Internal link' );
        }
        $args = array( 'timeout' => 5, 'redirection' => 3 );
        $response = wp_remote_head( $url, $args );
        if ( is_wp_error( $response ) ) {
            return array( 'status' => 'error', 'code' => null, 'message' => $response->get_error_message() );
        }
        $code = wp_remote_retrieve_response_code( $response );
        $message = wp_remote_retrieve_response_message( $response );
        return array(
            'status' => ( $code >= 400 ) ? 'broken' : 'ok',
            'code'   => $code,
            'message'=> $message,
        );
    }

    public static function render_page() {
        if ( ! current_user_can( SEOONE_CAP ) ) return;
        echo '<div class="wrap">';
        echo '<h1>リンクチェッカー</h1>';
        echo '<p>公開済み記事の外部リンクをチェックし、リンク切れ（HTTP ステータス 404 以上）を検出します。大量の記事を対象にすると時間がかかるため、最新の 5 記事のみを対象としています。</p>';
        // 直近5件の投稿を取得
        $posts = get_posts( array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 5,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );
        if ( empty( $posts ) ) {
            echo '<p>チェック対象の記事が見つかりません。</p></div>';
            return;
        }
        echo '<table class="widefat"><thead><tr><th>記事タイトル</th><th>リンクURL</th><th>ステータスコード</th><th>結果</th></tr></thead><tbody>';
        foreach ( $posts as $post ) {
            // 外部リンク抽出
            $content = $post->post_content;
            preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $content, $link_matches );
            $links = isset( $link_matches[1] ) ? $link_matches[1] : array();
            $checked = 0;
            foreach ( $links as $url ) {
                // 内部リンクはスキップせず internal status
                $result = self::check_url( $url );
                // 行を出力
                echo '<tr>';
                echo '<td>' . esc_html( get_the_title( $post ) ) . '</td>';
                echo '<td><a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $url ) . '</a></td>';
                if ( $result['code'] ) {
                    echo '<td>' . esc_html( $result['code'] ) . '</td>';
                } else {
                    echo '<td>—</td>';
                }
                $status_label = '';
                if ( $result['status'] === 'broken' ) {
                    $status_label = '<span style="color: #dc3545;">リンク切れ</span>';
                } elseif ( $result['status'] === 'ok' ) {
                    $status_label = '<span style="color: #28a745;">OK</span>';
                } elseif ( $result['status'] === 'internal' ) {
                    $status_label = '<span style="color: #17a2b8;">内部</span>';
                } else {
                    $status_label = '<span style="color: #ffc107;">エラー: ' . esc_html( $result['message'] ) . '</span>';
                }
                echo '<td>' . $status_label . '</td>';
                echo '</tr>';
                $checked++;
                // 1記事あたり最大10リンク
                if ( $checked >= 10 ) break;
            }
            if ( $checked === 0 ) {
                echo '<tr><td>' . esc_html( get_the_title( $post ) ) . '</td><td colspan="3">外部リンクはありません。</td></tr>';
            }
        }
        echo '</tbody></table>';
        echo '</div>';
    }
}