<?php get_header(); ?>
<div id="mainVisualWrap" class="flex fadeInUp animated">
  <div id="mainVisual">
    <?php
    // ヒーロースライダー画像を取得
    $slides = [];
    for ($i = 1; $i <= 3; $i++) {
        $pc = get_theme_mod("hero_slide_{$i}_pc");
        $sp = get_theme_mod("hero_slide_{$i}_sp");
        if ($pc || $sp) {
            $pc_url = $pc ? wp_get_attachment_url($pc) : get_template_directory_uri() . '/assets/img/top/mainimage.jpg';
            $sp_url = $sp ? wp_get_attachment_url($sp) : get_template_directory_uri() . '/assets/img/top/mainimage_sp.jpg';
            $slides[] = ['pc' => $pc_url, 'sp' => $sp_url];
        }
    }
    
    // スライドがない場合はデフォルト画像
    if (empty($slides)) {
        $slides[] = [
            'pc' => get_template_directory_uri() . '/assets/img/top/mainimage.jpg',
            'sp' => get_template_directory_uri() . '/assets/img/top/mainimage_sp.jpg'
        ];
    }
    ?>
    
    <?php if (count($slides) > 1): ?>
    <!-- スライダー表示 -->
    <div class="hero-slider">
      <?php foreach ($slides as $idx => $slide): ?>
      <div class="slide-item <?php echo $idx === 0 ? 'active' : ''; ?>">
        <img src="<?php echo esc_url($slide['pc']); ?>" alt="ヒーロー画像<?php echo $idx + 1; ?>" class="pcONspOFF">
        <img src="<?php echo esc_url($slide['sp']); ?>" alt="ヒーロー画像<?php echo $idx + 1; ?>" class="pcOFFspON">
      </div>
      <?php endforeach; ?>
    </div>
    <script>
    jQuery(document).ready(function($) {
        var currentSlide = 0;
        var slides = $('.slide-item');
        var totalSlides = slides.length;
        
        function showSlide(n) {
            slides.removeClass('active');
            slides.eq(n).addClass('active');
        }
        
        function nextSlide() {
            currentSlide = (currentSlide + 1) % totalSlides;
            showSlide(currentSlide);
        }
        
        setInterval(nextSlide, 5000);
    });
    </script>
    <style>
    .hero-slider { position: relative; }
    .slide-item { display: none; }
    .slide-item.active { display: block; }
    .slide-item img { width: 100%; height: auto; }
    </style>
    <?php else: ?>
    <!-- 単一画像表示 -->
    <img src="<?php echo esc_url($slides[0]['pc']); ?>" alt="ヒーロー画像" class="pcONspOFF">
    <img src="<?php echo esc_url($slides[0]['sp']); ?>" alt="ヒーロー画像" class="pcOFFspON">
    <?php endif; ?>
    
    <div class="mainCatch">
      <div class="flex fadeInUp animated mb20" data-wow-delay="0.5s">
        <p>家電IT周り<span class="fs80p">の</span>ホームドクター<br>
<span class="fs70p">家電<span class="fs90p">から</span>WEB制作開発<span class="fs90p">まで</span>ご相談ください。</span></p>
      </div>
    </div>
  </div>
</div>
<section class="bgNews">
  <div class="indexNews">
    <div class="newsLeft flex fadeInUp animated">
      <h2>News</h2>
    </div>
    <div class="contNews flex fadeInUp animated">
      <?php
      $types = dd_get_selected_news_types();
      $count = (int) get_theme_mod('news_post_count', 5);
      
      $q = new WP_Query([
        'post_type'      => $types,
        'posts_per_page' => $count,
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC'
      ]);
      if ($q->have_posts()) :
      ?>
      <ul>
        <?php while ($q->have_posts()) : $q->the_post(); ?>
        <li><span class="date"><?php echo get_the_date('Y.m.d'); ?></span><span class="title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></span></li>
        <?php endwhile; wp_reset_postdata(); ?>
      </ul>
      <?php else : ?>
      <ul>
        <li><span class="date">2025.04.17</span><span class="title">ホームページを公開いたしました。</span></li>
      </ul>
      <?php endif; ?>
    </div>
  </div>
</section>
<section class="bgcolorTopBnr">
  <div class="f-wrap-HU">
    <div class="bnrPic3Box ratio-2_1 bnrGrid01 flex fadeInUp animated" data-wow-delay="0.1s">
      <div class="bnrPicInner">
        <div class="gridCenter"><a href="<?php echo esc_url(home_url('/products')); ?>">家電販売・修理</a></div>
      </div>
    </div>
    <div class="bnrPic3Box ratio-2_1 bnrGrid02 flex fadeInUp animated" data-wow-delay="0.2s">
      <div class="bnrPicInner">
        <div class="gridCenter"><a href="<?php echo esc_url(home_url('/services')); ?>">WEB・ソフトウェア開発</a></div>
      </div>
    </div>
    <div class="bnrPic3Box ratio-2_1 bnrGrid03 flex fadeInUp animated" data-wow-delay="0.3s">
      <div class="bnrPicInner">
        <div class="gridCenter"><a href="<?php echo esc_url(home_url('/#anch01')); ?>">会社案内</a></div>
      </div>
    </div>
  </div>
</section>
<section class="bgWhite flex fadeInUp animated">
  <div class="wrapper">
    <h2>お客様の思いに応えるサービスを</h2>
    <div class="f-wrap-AC">
      <div class="f-item1-2 flex fadeInLeft animated">
        <p><img src="<?php echo get_template_directory_uri(); ?>/assets/img/top/1.jpg" alt="お客様サービス"></p>
      </div>
      <div class="f-item1-2 flex fadeInRight animated">
        <ul class="liBox li1Div liCheck mgb1em">
          <li>家電が故障したけど、どこに頼めばいいか分からない</li>
          <li>古い家電の修理や部品交換を頼みたい</li>
          <li>家電の調子が悪いので診てもらいたい</li>
          <li>買い替え時に何を選べばいいか相談したい</li>
          <li>地元で信頼できる家電の相談先を見つけたい</li>
        </ul>
        <p>DD Solutionsは、このようなお客様のお困りごと・ニーズに対応します。<br>当社代表は自衛隊上がりという経歴から、人に喜んでいただけることに生きがいを感じております。そんな思いをもとに「お客様第一主義」で、お客様の思いとニーズに寄り添ったサービスの提供ができるよう努めております。<br>これからもお客様が自然と笑顔になるサービスの提供を目指します。</p>
      </div>
    </div>
  </div>
</section>
<section class="bgColor flex fadeInUp animated">
  <div class="wrapper">
    <h2>代表挨拶</h2>
    <div class="f-wrap-AC f-row-reverse">
      <div class="f-item1-2 flex fadeInRight animated">
        <p><img src="<?php echo get_template_directory_uri(); ?>/assets/img/top/2.jpg" alt="代表取締役"></p>
      </div>
      <div class="f-item2-3 waterMleft flex fadeInLeft animated">
        <p class="mb10">当社は「お客様第一主義」を掲げ、地域の皆さまの暮らしとビジネスを技術で支えてまいりました。<br>家電修理とITサービスという異なる領域を一つの会社で提供できるのが私たちの強みです。<br>自衛隊出身という経験を活かし、責任感と誠実さをもって、今後も皆様の笑顔に貢献してまいります。</p>
        <p class="tar txtSign">DD Solutions株式会社<br>代表取締役　杉本 忍</p>
      </div>
    </div>
  </div>
</section>
<div id="anch01"></div>
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
<?php get_footer(); ?>
