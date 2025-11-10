<?php
/**
 * 記事（投稿）ごとの 5 ステップ設定メタボックス
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class SeoOne_MetaBox {

  public static function init(){
    add_action( 'add_meta_boxes', array(__CLASS__, 'add') );
    add_action( 'save_post', array(__CLASS__, 'save') );
  }

  public static function add(){
    add_meta_box(
      'seoone_pipeline_box',
      'SEO ONE：記事ごとのAIパイプライン設定',
      array(__CLASS__, 'render'),
      'post','side','default'
    );
  }

  public static function render( $post ){
    wp_nonce_field( 'seoone_pipeline_box', 'seoone_pipeline_box_nonce' );
    $val = get_post_meta( $post->ID, '_seoone_pipeline', true );
    $val = is_array($val) ? $val : array();

    $defaults = array(
      'keywords' => '',
      'steps' => array(
        'retrieval' => array('on'=>0, 'model'=>'', 'temp'=>'0.2', 'max'=>'1000'),
        'draft'     => array('on'=>1, 'model'=>'', 'temp'=>'0.3', 'max'=>'4096'),
        'coding'    => array('on'=>1, 'model'=>'', 'temp'=>'0.2', 'max'=>'1500'),
        'scoring'   => array('on'=>0, 'model'=>'', 'temp'=>'0.0', 'max'=>'800'),
        'factcheck' => array('on'=>0, 'model'=>'', 'temp'=>'0.0', 'max'=>'1200'),
      ),
      'cost_cap_usd' => ''
    );
    $cfg = wp_parse_args( $val, $defaults );

    $labels = array(
      'retrieval' => '情報取得',
      'draft'     => '初稿',
      // 編集ステップを「コーディング」として表記します
      'coding'    => 'コーディング',
      'scoring'   => '採点',
      'factcheck' => 'ファクトチェック',
    );

    echo '<p><label>この記事のキーワード<br>';
    printf('<input type="text" name="seoone_pipeline[keywords]" value="%s" style="width:100%%" placeholder="例：エアコン, 暖房, 節電">', esc_attr($cfg['keywords']));
    echo '</label></p>';

    echo '<table style="width:100%; border-collapse:collapse;"><tbody>';
    foreach($labels as $key=>$label){
      $s = $cfg['steps'][$key];
      echo '<tr style="border-top:1px solid #ddd;"><td colspan="2">';
      printf('<label><input type="checkbox" name="seoone_pipeline[steps][%s][on]" value="1" %s> <strong>%s</strong></label>',
        esc_attr($key), checked(!empty($s['on']), true, false), esc_html($label));
      echo '</td></tr>';

      echo '<tr><td>モデル</td><td>';
      printf('<input type="text" name="seoone_pipeline[steps][%s][model]" value="%s" style="width:100%%" placeholder="gpt-4o / claude-3-7-sonnet">',
        esc_attr($key), esc_attr($s['model']));
      echo '</td></tr>';

      echo '<tr><td>温度</td><td>';
      printf('<input type="number" step="0.1" min="0" max="1" name="seoone_pipeline[steps][%s][temp]" value="%s" style="width:100%%">',
        esc_attr($key), esc_attr($s['temp']));
      echo '</td></tr>';

      echo '<tr><td>MaxTokens</td><td>';
      printf('<input type="number" min="200" name="seoone_pipeline[steps][%s][max]" value="%s" style="width:100%%">',
        esc_attr($key), esc_attr($s['max']));
      echo '</td></tr>';
    }
    echo '</tbody></table>';

    echo '<p><label>この記事の費用上限（USD）<br>';
    printf('<input type="number" step="0.01" min="0" name="seoone_pipeline[cost_cap_usd]" value="%s" style="width:100%%" placeholder="空欄=制限なし">',
      esc_attr($cfg['cost_cap_usd']));
    echo '</label></p>';
  }

  public static function save( $post_id ){
    if ( ! isset($_POST['seoone_pipeline_box_nonce']) || ! wp_verify_nonce($_POST['seoone_pipeline_box_nonce'], 'seoone_pipeline_box') ) return;
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( ! current_user_can('edit_post', $post_id) ) return;
    if ( ! isset($_POST['seoone_pipeline']) || ! is_array($_POST['seoone_pipeline']) ) return;

    $in = wp_unslash($_POST['seoone_pipeline']);
    $out = array(
      'keywords'     => sanitize_text_field( $in['keywords'] ?? '' ),
      'steps'        => array(),
      'cost_cap_usd' => isset($in['cost_cap_usd']) ? floatval($in['cost_cap_usd']) : '',
    );
    foreach( array('retrieval','draft','coding','scoring','factcheck') as $k ){
      $src = $in['steps'][$k] ?? array();
      $out['steps'][$k] = array(
        'on'    => !empty($src['on']) ? 1 : 0,
        'model' => sanitize_text_field($src['model'] ?? ''),
        'temp'  => sanitize_text_field($src['temp'] ?? '0.2'),
        'max'   => intval($src['max'] ?? 1000),
      );
    }
    update_post_meta( $post_id, '_seoone_pipeline', $out );
  }
}
