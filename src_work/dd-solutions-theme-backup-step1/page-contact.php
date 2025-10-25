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
          <p>以下の項目にご記入の上、最下部の&nbsp;[&nbsp;送信&nbsp;]&nbsp;ボタンを押してください。</p>
          <p class="txtInd">※半角カタカナは利用しないようお願い致します。</p>
          <p class="txtInd2"><span class="fcRedOrange">*</span>印は入力必須項目です。</p>
          
          <form id="contactForm" method="post" action="<?php echo admin_url('admin-ajax.php'); ?>">
            <input type="hidden" name="action" value="dd_contact_submit">
            <?php wp_nonce_field('dd_contact_nonce', 'nonce'); ?>
            
            <table>
              <tr>
                <th scope="row">お問い合わせ種別<span class="fcRedOrange">*</span></th>
                <td>
                  <input type="checkbox" name="inquiry_type[]" value="家電販売・修理" class="checkBox"> 家電販売・修理&nbsp;&nbsp;
                  <input type="checkbox" name="inquiry_type[]" value="WEB・ソフトウェア開発" class="checkBox"> WEB・ソフトウェア開発&nbsp;
                </td>
              </tr>
              <tr>
                <th scope="row">会社名</th>
                <td><input name="company" type="text" class="formm"></td>
              </tr>
              <tr>
                <th scope="row">氏名<span class="fcRedOrange">*</span></th>
                <td><input name="name" type="text" class="formm" required></td>
              </tr>
              <tr>
                <th scope="row">氏名（フリガナ）<span class="fcRedOrange">*</span></th>
                <td><input name="kana" type="text" class="formm" required></td>
              </tr>
              <tr>
                <th scope="row">メールアドレス<span class="fcRedOrange">*</span></th>
                <td><input name="email" type="email" class="formm" required></td>
              </tr>
              <tr>
                <th scope="row">確認用メールアドレス<span class="fcRedOrange">*</span></th>
                <td><input type="email" class="formm" name="email_confirm" required></td>
              </tr>
              <tr>
                <th scope="row">電話番号<span class="fcRedOrange">*</span></th>
                <td><input type="tel" name="phone" class="formm" required></td>
              </tr>
              <tr>
                <th scope="row">FAX番号</th>
                <td><input type="text" name="fax" class="formm"></td>
              </tr>
              <tr>
                <th scope="row">お問い合わせ内容<span class="fcRedOrange">*</span></th>
                <td><textarea name="message" class="formm" rows="10" required></textarea></td>
              </tr>
            </table>
            
            <div class="txtCenter mt20">
              <button type="submit" class="txtBnrAr">送信する</button>
            </div>
            
            <div id="formMessage" style="margin-top:20px;"></div>
          </form>
          
          <script>
          jQuery(document).ready(function($) {
              $('#contactForm').on('submit', function(e) {
                  e.preventDefault();
                  
                  var formData = $(this).serialize();
                  
                  $.ajax({
                      url: '<?php echo admin_url('admin-ajax.php'); ?>',
                      type: 'POST',
                      data: formData,
                      success: function(response) {
                          if (response.success) {
                              $('#formMessage').html('<p style="color:green;">' + response.data.message + '</p>');
                              $('#contactForm')[0].reset();
                          } else {
                              $('#formMessage').html('<p style="color:red;">' + response.data.message + '</p>');
                          }
                      },
                      error: function() {
                          $('#formMessage').html('<p style="color:red;">送信に失敗しました。</p>');
                      }
                  });
              });
          });
          </script>
        </div>
      </div>
    </section>
  </article>
</main>
<?php get_footer(); ?>
