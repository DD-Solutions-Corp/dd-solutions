<?php
/**
 * Template Name: 採用情報
 */
get_header();
?>
<div id="pageTitleMover">
  <div id="pageTitle" class="recruitTit">
    <div id="pth1Wrap" class="bgWhite flex fadeInUp animated">
      <h1>採用情報</h1>
    </div>
  </div>
</div>
</div>
<main>
  <article>
    <section class="bgWhite flex fadeInUp animated">
      <div class="wrapper">
        <h2>DD Solutionsで働きませんか？</h2>
        <p class="mgb2em txtPCcenterTabletLeft">私たちは、お客様の笑顔を大切にする仲間を求めています。<br>一緒に成長し、喜びを共有できる方をお待ちしています！</p>
        
        <div class="contPad">
          <h3>募集職種</h3>
          <p><?php echo nl2br(esc_html(get_theme_mod('recruit_position', '家電修理スタッフ / WEB開発エンジニア'))); ?></p>
          
          <h3>勤務地</h3>
          <p><?php echo esc_html(get_theme_mod('recruit_location', '東京都渋谷区渋谷2丁目19番15号')); ?></p>
          
          <h3>給与</h3>
          <p><?php echo esc_html(get_theme_mod('recruit_salary', '経験・能力に応じて優遇')); ?></p>
          
          <h3>応募方法</h3>
          <p><?php echo nl2br(esc_html(get_theme_mod('recruit_method', '下記フォームよりご応募ください。'))); ?></p>
        </div>
      </div>
    </section>
    
    <section class="bgColor flex fadeInUp animated">
      <div class="wrapper">
        <h2>応募フォーム</h2>
        <div class="contPad contact">
          <p>以下の項目にご記入の上、送信ボタンを押してください。</p>
          <p class="txtInd2"><span class="fcRedOrange">*</span>印は入力必須項目です。</p>
          <form id="recruitForm" method="post">
            <table>
              <tr>
                <th>お名前<span class="fcRedOrange">*</span></th>
                <td><input type="text" name="name" class="formm" required></td>
              </tr>
              <tr>
                <th>メールアドレス<span class="fcRedOrange">*</span></th>
                <td><input type="email" name="email" class="formm" required></td>
              </tr>
              <tr>
                <th>電話番号<span class="fcRedOrange">*</span></th>
                <td><input type="tel" name="phone" class="formm" required></td>
              </tr>
              <tr>
                <th>応募内容<span class="fcRedOrange">*</span></th>
                <td><textarea name="message" class="formm" rows="5" required></textarea></td>
              </tr>
            </table>
            <div class="txtCenter mt20">
              <button type="submit" class="txtBnrAr">送信する</button>
            </div>
          </form>
        </div>
      </div>
    </section>
  </article>
</main>
<?php get_footer(); ?>
