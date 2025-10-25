<?php
/**
 * Template Name: 新着情報
 */
get_header();

// 絞り込みパラメータ
$selected_type = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : '';
$selected_tag = isset($_GET['tag']) ? sanitize_text_field($_GET['tag']) : '';

// 表示する投稿タイプを取得
$types = dd_get_selected_news_types();
$count = (int) get_theme_mod('news_post_count', 10);

// クエリ引数
$args = [
    'post_type' => $selected_type ? [$selected_type] : $types,
    'posts_per_page' => $count,
    'paged' => get_query_var('paged') ? get_query_var('paged') : 1,
    'post_status' => 'publish',
    'orderby' => 'date',
    'order' => 'DESC'
];

if ($selected_tag) {
    $args['tag'] = $selected_tag;
}

$q = new WP_Query($args);

// タグ一覧を取得
$all_tags = get_tags(['hide_empty' => true]);
?>
<div id="pageTitleMover">
  <div id="pageTitle" class="content3Tit">
    <div id="pth1Wrap" class="bgWhite flex fadeInUp animated">
      <h1>新着情報</h1>
    </div>
  </div>
</div>
</div>
<main>
  <article>
    <section class="bgWhite flex fadeInUp animated">
      <div class="wrapper">
        <!-- 絞り込みフィルター -->
        <div class="news-filter" style="margin-bottom:30px; padding:20px; background:#f8f9fa; border-radius:8px;">
          <form method="get" action="">
            <div class="f-wrap-AC" style="gap:20px;">
              <div class="filter-item">
                <label for="postTypeFilter" style="display:block; margin-bottom:5px; font-weight:bold;">投稿タイプで絞り込み</label>
                <select id="postTypeFilter" name="post_type" class="formm" style="width:200px;" onchange="this.form.submit()">
                  <option value="">全て</option>
                  <?php
                  $post_types = get_post_types(['public' => true], 'objects');
                  foreach ($post_types as $pt) {
                      if ($pt->name !== 'attachment' && $pt->name !== 'page') {
                          $selected = ($selected_type === $pt->name) ? 'selected' : '';
                          echo '<option value="' . esc_attr($pt->name) . '" ' . $selected . '>' . esc_html($pt->label) . '</option>';
                      }
                  }
                  ?>
                </select>
              </div>
              
              <div class="filter-item">
                <label for="tagFilter" style="display:block; margin-bottom:5px; font-weight:bold;">タグで絞り込み</label>
                <select id="tagFilter" name="tag" class="formm" style="width:200px;" onchange="this.form.submit()">
                  <option value="">全て</option>
                  <?php
                  foreach ($all_tags as $tag) {
                      $selected = ($selected_tag === $tag->slug) ? 'selected' : '';
                      echo '<option value="' . esc_attr($tag->slug) . '" ' . $selected . '>' . esc_html($tag->name) . ' (' . $tag->count . ')</option>';
                  }
                  ?>
                </select>
              </div>
              
              <?php if ($selected_type || $selected_tag): ?>
              <div class="filter-item">
                <label style="display:block; margin-bottom:5px;">&nbsp;</label>
                <a href="<?php echo esc_url(get_permalink()); ?>" class="txtBnrAr" style="display:inline-block; padding:8px 15px; font-size:14px;">絞り込み解除</a>
              </div>
              <?php endif; ?>
            </div>
          </form>
        </div>
        
        <!-- 新着情報リスト -->
        <div class="news-list">
          <?php
          if ($q->have_posts()) :
            while ($q->have_posts()) : $q->the_post();
              $post_type_obj = get_post_type_object(get_post_type());
          ?>
            <div class="news-item" style="background:#fff; margin-bottom:30px; padding:25px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.08); transition:all 0.3s ease;">
              <div class="news-header" style="display:flex; align-items:center; gap:15px; margin-bottom:15px; flex-wrap:wrap;">
                <span class="date" style="color:#ff6b35; font-weight:bold; font-size:16px;"><?php echo get_the_date('Y.m.d'); ?></span>
                <span class="post-type-badge" style="background:#007cba; color:#fff; padding:4px 12px; border-radius:4px; font-size:12px;"><?php echo esc_html($post_type_obj->label); ?></span>
                <?php
                $tags = get_the_tags();
                if ($tags) {
                    foreach ($tags as $tag) {
                        echo '<span class="tag-badge" style="background:#e0e0e0; color:#333; padding:4px 10px; border-radius:4px; font-size:11px;">' . esc_html($tag->name) . '</span>';
                    }
                }
                ?>
              </div>
              
              <h3 style="margin-bottom:15px; font-size:22px;">
                <a href="<?php the_permalink(); ?>" style="color:#333; text-decoration:none; transition:color 0.3s;">
                  <?php the_title(); ?>
                </a>
              </h3>
              
              <?php if (has_post_thumbnail()): ?>
              <div class="news-thumbnail" style="margin-bottom:15px; border-radius:6px; overflow:hidden;">
                <?php the_post_thumbnail('medium', ['style' => 'width:100%; height:auto;']); ?>
              </div>
              <?php endif; ?>
              
              <div class="excerpt" style="color:#666; line-height:1.8; margin-bottom:15px;">
                <?php the_excerpt(); ?>
              </div>
              
              <a href="<?php the_permalink(); ?>" class="read-more" style="color:#007cba; text-decoration:none; font-weight:bold; display:inline-block;">
                続きを読む <i class="fas fa-chevron-right"></i>
              </a>
            </div>
          <?php
            endwhile;
            
            // ページネーション
            echo '<div class="pagination" style="margin-top:40px; text-align:center;">';
            the_posts_pagination([
                'mid_size' => 2,
                'prev_text' => '<i class="fas fa-chevron-left"></i> 前へ',
                'next_text' => '次へ <i class="fas fa-chevron-right"></i>',
            ]);
            echo '</div>';
            
            wp_reset_postdata();
          else :
          ?>
            <div class="comingsoon2" style="text-align:center; padding:60px 20px; font-size:18px; color:#999;">
              該当する記事が見つかりませんでした。
            </div>
          <?php endif; ?>
        </div>
      </div>
    </section>
    
    <section class="bgTopad1 flex fadeInUp animated">
      <div class="wrapper">
        <h2>会社案内</h2>
        <div class="f-wrap-AC ">
          <div class="f-item1-2 flex ">
            <table class="table3Dot">
              <tr>
                <th>会社名</th>
                <td>DD Solutions株式会社</td>
              </tr>
              <tr>
                <th>代表</th>
                <td>杉本 忍</td>
              </tr>
              <tr>
                <th>所在地</th>
                <td>〒150-0002 東京都渋谷区渋谷2丁目19番15号</td>
              </tr>
              <tr>
                <th>事業内容</th>
                <td>家電販売・修理<br>WEB・ソフトウェア開発<br><a href="https://msdigit.net/marrige/" target="_blank">結婚相談所</a></td>
              </tr>
            </table>
          </div>
          <div class="f-item1-2 flex ">
            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3241.7065667268516!2d139.70118707471482!3d35.65960053116367!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x60188b585f2fb139%3A0xea165c22084c241b!2z44CSMTUwLTAwMDIg5p2x5Lqs6YO95riL6LC35Yy65riL6LC377yS5LiB55uu77yR77yZ4oiS77yR77yV!5e0!3m2!1sja!2sjp!4v1745470786178!5m2!1sja!2sjp" width="100%" height="500" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
          </div>
        </div>
      </div>
    </section>
    <section class="bgTopad2 flex fadeInUp animated">
      <div class="wrapper">
        <h2>DD Solutionsで働きませんか？</h2>
        <div class="contPad70p txtPCcenterTabletLeft">
          <p class="mgb2em">私たちは、お客様の笑顔を大切にする仲間を求めています。<br>一緒に成長し、喜びを共有できる方をお待ちしています！</p>
          <div class="txtCenter">
            <p class="txtBnrAr"><a href="<?php echo esc_url(home_url('/career')); ?>">採用情報はこちら</a></p>
          </div>
        </div>
      </div>
    </section>
  </article>
</main>
<?php get_footer(); ?>
