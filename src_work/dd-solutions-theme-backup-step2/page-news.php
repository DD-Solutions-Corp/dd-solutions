<?php
/**
 * Template Name: 新着情報
 */
get_header();
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
        <div class="news-list">
          <?php
          $types = dd_get_selected_news_types();
          $count = (int) get_theme_mod('news_post_count', 10);
          
          $q = new WP_Query([
            'post_type' => $types,
            'posts_per_page' => $count,
            'paged' => get_query_var('paged') ? get_query_var('paged') : 1
          ]);
          
          if ($q->have_posts()) :
            while ($q->have_posts()) : $q->the_post();
          ?>
            <div class="news-item" style="margin-bottom:30px; padding-bottom:20px; border-bottom:1px solid #e0e0e0;">
              <h3 style="margin-bottom:10px;"><a href="<?php the_permalink(); ?>" style="color:#007cba; text-decoration:none;"><?php the_title(); ?></a></h3>
              <p class="date" style="color:#ff6b35; font-weight:bold; margin-bottom:10px;"><?php echo get_the_date('Y.m.d'); ?></p>
              <?php if (has_post_thumbnail()): ?>
              <div class="news-thumbnail" style="margin-bottom:10px;">
                <?php the_post_thumbnail('medium'); ?>
              </div>
              <?php endif; ?>
              <div class="excerpt"><?php the_excerpt(); ?></div>
            </div>
          <?php
            endwhile;
            
            // ページネーション
            the_posts_pagination([
                'mid_size' => 2,
                'prev_text' => '&laquo; 前へ',
                'next_text' => '次へ &raquo;',
            ]);
            
            wp_reset_postdata();
          else :
          ?>
            <div class="comingsoon2">
              準備中
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
