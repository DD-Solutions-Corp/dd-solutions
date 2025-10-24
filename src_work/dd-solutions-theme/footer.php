<footer>
  <p id="pagetop"><a href="#headWrap"><i class="fas fa-chevron-circle-up fa-3x"></i></a></p>
  <div class="footerNaviWrap flex fadeInUp animated">
    <div class="footNavi">
      <ul class="mb0">
        <li><a href="<?php echo esc_url(home_url('/')); ?>">ホーム</a></li>
        <li><a href="<?php echo esc_url(home_url('/products')); ?>">家電販売・修理</a></li>
        <li><a href="<?php echo esc_url(home_url('/services')); ?>">WEB・ソフトウェア開発</a></li>
        <li><a href="<?php echo esc_url(home_url('/news')); ?>">新着情報</a></li>
        <li><a href="<?php echo esc_url(home_url('/contact')); ?>">お問い合わせ</a></li>
      </ul>
    </div>
  </div>
  <div id="footerDataWrap">
    <div class="footDataArea flex fadeInUp animated">
      <p class="companyName"><img src="<?php echo get_template_directory_uri(); ?>/assets/img/footer/foot_logo.png" alt="DD Solutions株式会社"></p>
      <div class="footAdress">
        <p>〒150-0002 <span class="pcONspOFF">&nbsp;</span><br class="pcOFFspON">
          東京都渋谷区渋谷2丁目19番15号</p>
      </div>
    </div>
  </div>
  <div class="copyright">
    <p>&copy; 
      <script type="text/javascript">
myDate = new Date() ;myYear = myDate.getFullYear ();document.write(myYear);
    </script>&nbsp;DD Solutions株式会社. All Rights Reserved. </p>
  </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
