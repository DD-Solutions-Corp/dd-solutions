
<?php
// If the old HTML had a footer, keep it within the template below.
// We'll place wp_footer() before body close.
?>
</div>
</main>
<footer>
  <p id="pagetop"><a ><i class="fas fa-chevron-circle-up fa-3x"></i></a></p>
  <div class="footerNaviWrap flex fadeInUp animated">
    <div class="footNavi">
      <?php
      // フッターメニュー（この位置に1回だけ）
      if (has_nav_menu('footer')) {
          wp_nav_menu(array(
              'theme_location' => 'footer',
              'container'      => false,
              'depth'          => 1,
              'fallback_cb'    => '__return_empty_string',
              'items_wrap'     => '<ul class="mb0">%3$s</ul>',
          ));
      }
      ?>
    </div>
  </div>
  <div id="footerDataWrap">
    <div class="footDataArea flex fadeInUp animated">
      <p class="companyName"><img alt="DD Solutions株式会社" src="<?php echo get_stylesheet_directory_uri(); ?>/assets/img/common/foot_logo.png"/></p>
      <div class="footAdress">
        <p>〒150-0002 <span class="pcONspOFF"> </span><br class="pcOFFspON"/>
                  東京都渋谷区渋谷2丁目19番15号</p>
      </div>
    </div>
  </div>
  <div class="copyright">
    <p>© 
          <script type="text/javascript">
    myDate = new Date() ;myYear = myDate.getFullYear ();document.write(myYear);
        </script> DD Solutions株式会社. All Rights Reserved. </p>
  </div>
  <!--　右フローティングバナー
    <ul class="fixRightBnr">
      <li class="insta"><a href="https://instagram.com/" target="_blank"><i class="fa-brands fa-instagram"></i></a></li>
      <li class="twitter"><a href="https://twitter.com/" target="_blank"><i class="fa-brands fa-x-twitter"></i></a></li>
      <li class="facebook"><a href="https://facebook.com/" target="_blank"><i class="fa-brands fa-facebook"></i></a></li>
      <li class="youtube"><a href="https://www.youtube.com/" target="_blank"><i class="fa-brands fa-youtube"></i></a></li>
      <li class="line"><a href="https://line.me/" target="_blank"><i class="fa-brands fa-line"></i></a></li>
      <li class="tictok"><a href="https://www.tiktok.com/" target="_blank"><i class="fa-brands fa-tiktok"></i></a></li>
      <li class="ameba"><a href="#" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square"></i><span>Ameba</span></a></li>
      <li><a href="#"><i class="fa-solid fa-circle-chevron-right"></i> <span>サイト内</span></a></li>
      <li><a href="tel:03-0000-0000"><i class="fa-solid fa-phone-flip"></i></a></li>
    </ul>
    <!--　/ 右フローティングバナー -->
</footer>
<?php wp_footer(); ?>
</body>
</html>
