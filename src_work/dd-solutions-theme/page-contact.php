<?php
/**
 * Template Name: お問い合わせ
 */
get_header();
?>
<div id="pageTitleMover">
  <div id="pageTitle" class="contactTit">
    <div id="pth1Wrap" class="bgWhite flex fadeInUp animated">
      <h1>お問い合わせ</h1>
    </div>
  </div>
</div>
</div>
<main>
  <article>
    <section class="bgWhite flex fadeInUp animated">
      <div class="wrapper">
        <h2 class="h2Center">メールフォーム<span>Mail form</span></h2>
        <div class="contPad contact"> 
          <p>以下の項目にご記入の上、最下部の&nbsp;[&nbsp;確認&nbsp;]&nbsp;ボタンを押してください。</p>
          <p class="txtInd">※半角カタカナは利用しないようお願い致します。</p>
          <p class="txtInd2"><span class="fcRedOrange">*</span>印は入力必須項目です。</p>
          <?php
          if (function_exists('wpcf7')) {
              echo do_shortcode('[contact-form-7 id="1" title="お問い合わせフォーム"]');
          } else {
              echo '<p class="fcRedOrange mgb1em">※お問い合わせフォームを有効化するには、Contact Form 7プラグインをインストールしてください。</p>';
          }
          ?>
        </div>
      </div>
    </section>
  </article>
</main>
<?php get_footer(); ?>
