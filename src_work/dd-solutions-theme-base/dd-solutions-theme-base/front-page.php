<?php get_header(); ?>
<div class="flex fadeInUp animated" id="mainVisualWrap">
  <!-- ヒーロースライダー: data-hero-images 属性に画像リストをカンマ区切りで指定 -->
  <?php
    $hero_images = [
      get_theme_file_uri('assets/img/1.jpg'),
      get_theme_file_uri('assets/img/2.jpg'),
      get_theme_file_uri('assets/img/3.jpg')
    ];
  ?>
  <div id="mainVisual" data-hero-images="<?php echo esc_attr( implode(',', $hero_images ) ); ?>">
    <div class="mainCatch">
      <div class="flex fadeInUp animated mb20" data-wow-delay="0.5s">
        <p>家電IT周り<span class="fs80p">の</span>ホームドクター<br/>
          <span class="fs70p">家電<span class="fs90p">から</span>WEB制作開発<span class="fs90p">まで</span>ご相談ください。</span></p>
      </div>
      <!--<p class="flex fadeInUp animated catchEng ff_DancingScript" data-wow-delay="0.1s">English sentences may be included.</p>-->
    </div>
  </div>
</div>
<section class="bgNews">
<div class="indexNews">
<div class="newsLeft flex fadeInUp animated">
<h2>News</h2>
<!-- <p class="txtBnr"><a href="#">一覧を見る&nbsp;<i class="fas fa-chevron-circle-right"></i></a></p> -->
</div>
<div class="contNews flex fadeInUp animated">
<ul>
<li><span class="date">2025.04.17</span><!-- <span class=" wp_category">施工事例</span> --><span class="title">ホームページを公開いたしました。</span></li>
</ul>
</div>
</div>
</section>
<section class="bgcolorTopBnr">
<div class="f-wrap-HU">
<div class="bnrPic3Box ratio-2_1 bnrGrid01 flex fadeInUp animated" data-wow-delay="0.1s">
<div class="bnrPicInner">
<div class="gridCenter"><a ><!--<span class="englishName">englishName</span><br>-->
          家電販売・修理</a></div>
</div>
</div>
<div class="bnrPic3Box ratio-2_1 bnrGrid02 flex fadeInUp animated" data-wow-delay="0.2s">
<div class="bnrPicInner">
<div class="gridCenter"><a ><!--<span class="englishName">englishName</span><br>-->
          WEB・ソフトウェア開発</a></div>
</div>
</div>
<div class="bnrPic3Box ratio-2_1 bnrGrid03 flex fadeInUp animated" data-wow-delay="0.3s">
<div class="bnrPicInner">
<div class="gridCenter"><a ><!--<span class="englishName">englishName</span><br>-->
          会社案内</a></div>
</div>
</div>
</div>
</section>
<section class="bgWhite flex fadeInUp animated">
<div class="wrapper">
<h2>お客様の思いに応えるサービスを</h2>
<div class="f-wrap-AC">
<div class="f-item1-2 flex fadeInLeft animated">
<p><img alt="" src="<?php echo get_stylesheet_directory_uri(); ?>/assets/img/common/1.jpg"/></p>
</div>
<div class="f-item1-2 flex fadeInRight animated">
<ul class="liBox li1Div liCheck mgb1em">
<li>家電が故障したけど、どこに頼めばいいか分からない</li>
<li>古い家電の修理や部品交換を頼みたい</li>
<li>家電の調子が悪いので診てもらいたい</li>
<li>買い替え時に何を選べばいいか相談したい</li>
<li>地元で信頼できる家電の相談先を見つけたい</li>
</ul>
<p>DD Solutionsは、このようなお客様のお困りごと・ニーズに対応します。<br/>当社代表は自衛隊上がりという経歴から、人に喜んでいただけることに生きがいを感じております。そんな思いをもとに「お客様第一主義」で、お客様の思いとニーズに寄り添ったサービスの提供ができるよう努めております。<br/>これからもお客様が自然と笑顔になるサービスの提供を目指します。</p>
</div>
</div>
</div>
</section>
<section class="bgColor flex fadeInUp animated">
<div class="wrapper">
<h2>代表挨拶</h2>
<div class="f-wrap-AC f-row-reverse">
<div class="f-item1-2 flex fadeInRight animated">
<p><img alt="" src="<?php echo get_stylesheet_directory_uri(); ?>/assets/img/common/2.jpg"/></p>
</div>
<div class="f-item2-3 waterMleft flex fadeInLeft animated">
<p class="mb10">私は、かつて自衛隊に所属し、人の役に立つこと・仲間を支えることの大切さを学びました。<br/>
その経験を活かし、「人に喜ばれる仕事を一つひとつ丁寧に」という想いでこの会社を立ち上げました。<br/>
<br/>
DD Solutionsは、家電量販店のような低価格と、町の電気屋のような顔の見える安心感を両立させることを目指しています。<br/>
販売から設置、修理、そしてその後のアフターフォローまで、すべて自社で責任を持って対応いたします。<br/>
<br/>
お客様から「ここに頼んでよかった」と言っていただけることが、何よりの励みです。<br/>
これからも地域に根ざし、信頼でつながる会社として、皆さまの生活を支えるお手伝いを続けてまいります。</p>
<p class="tar txtSign">DD Solutions株式会社<br/>代表取締役　杉本 忍</p>
<!-- <div class="txtCenter">
          <p class="txtBnrAr"><a >会社案内</a></p>
        </div> -->
</div>
</div>
</div>
</section>
<div id="anch01"></div>
<section class="bgTopad1 flex fadeInUp animated">
<div class="wrapper">
<h2>会社案内</h2>
<div class="f-wrap-AC">
<div class="f-item1-2 flex">
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
<td>家電販売・修理<br/>WEB・ソフトウェア開発<br/><a href="https://msdigit.net/marrige/" target="_blank">結婚相談所</a></td>
</tr>
</table>
</div>
<div class="f-item1-2 flex">
<iframe src="https://www.google.com/maps?q=DD%20Solutions%20%E7%86%8A%E8%B0%B7&z=15&output=embed"
              width="100%" height="420" style="border:0; min-height: 360px;"
              allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
</div>
</div>
</div>
</section>
<section class="bgTopad2 flex fadeInUp animated">
<div class="wrapper">
<h2>DD Solutionsで働きませんか？</h2>
<div class="contPad70p txtPCcenterTabletLeft">
<p class="mgb2em">私たちは、お客様の笑顔を大切にする仲間を求めています。<br/>一緒に成長し、喜びを共有できる方をお待ちしています！</p>
<div class="txtCenter">
<p class="txtBnrAr"><a href="http://dd-sol.com/wp/category/career/">採用情報はこちら</a></p>
</div>
</div>
</div>
</section>
<?php get_footer(); ?>
