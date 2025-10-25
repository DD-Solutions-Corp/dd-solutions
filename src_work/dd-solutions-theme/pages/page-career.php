<?php
/**
 * Template Name: 採用情報
 */
get_header();
?>
<div id="pageTitleMover">
  <div id="pageTitle" class="careerTit">
    <div id="pth1Wrap" class="bgWhite flex fadeInUp animated">
      <h1>採用情報</h1>
    </div>
  </div>
</div>
</div>
<main>
  <article>
    <!-- ビジョンセクション -->
    <section class="bgWhite flex fadeInUp animated">
      <div class="wrapper">
        <h2>私たちのビジョン</h2>
        <p class="mgb2em txtCenter" style="font-size:20px; line-height:1.8;">
          DD Solutionsは、お客様の笑顔を大切にする仲間を求めています。<br>
          家電修理とITサービスという異なる領域を一つの会社で提供できるのが私たちの強みです。<br>
          一緒に成長し、喜びを共有できる方をお待ちしています！
        </p>
      </div>
    </section>
    
    <!-- 募集職種一覧 -->
    <section class="bgColor flex fadeInUp animated">
      <div class="wrapper">
        <h2>募集職種</h2>
        
        <div class="job-list">
          <!-- 家電修理スタッフ -->
          <div class="job-item" style="background:#fff; padding:30px; margin-bottom:30px; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.1);">
            <h3 style="color:#007cba; margin-bottom:15px; font-size:24px;">家電修理スタッフ</h3>
            <div class="job-details" style="margin-bottom:20px;">
              <p style="margin-bottom:10px;"><strong>仕事内容：</strong></p>
              <ul class="liDef" style="margin-bottom:15px;">
                <li>お客様宅への出張修理サービス</li>
                <li>家電の診断・修理・メンテナンス</li>
                <li>家電販売・設置サポート</li>
                <li>修理後のアフターフォロー</li>
              </ul>
              
              <p style="margin-bottom:10px;"><strong>求める人物像：</strong></p>
              <ul class="liDef" style="margin-bottom:15px;">
                <li>お客様第一の姿勢で仕事ができる方</li>
                <li>家電修理の経験がある方（未経験者も歓迎）</li>
                <li>コミュニケーション能力が高い方</li>
                <li>普通自動車免許をお持ちの方</li>
              </ul>
              
              <p style="margin-bottom:10px;"><strong>勤務地：</strong><?php echo esc_html(get_theme_mod('recruit_location', '東京都渋谷区渋谷2丁目19番15号')); ?></p>
              <p style="margin-bottom:10px;"><strong>給与：</strong><?php echo esc_html(get_theme_mod('recruit_salary', '月給25万円〜40万円（経験・能力に応じて優遇）')); ?></p>
              <p style="margin-bottom:10px;"><strong>勤務時間：</strong>9:00〜18:00（休憩1時間）</p>
              <p style="margin-bottom:10px;"><strong>休日：</strong>週休2日制（土日祝）、年末年始、夏季休暇</p>
            </div>
          </div>
          
          <!-- WEB開発エンジニア -->
          <div class="job-item" style="background:#fff; padding:30px; margin-bottom:30px; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.1);">
            <h3 style="color:#007cba; margin-bottom:15px; font-size:24px;">WEB開発エンジニア</h3>
            <div class="job-details" style="margin-bottom:20px;">
              <p style="margin-bottom:10px;"><strong>仕事内容：</strong></p>
              <ul class="liDef" style="margin-bottom:15px;">
                <li>Webサイト・Webアプリケーションの開発</li>
                <li>システム設計・実装・テスト</li>
                <li>既存システムの保守・運用</li>
                <li>クライアントとの要件定義・提案</li>
              </ul>
              
              <p style="margin-bottom:10px;"><strong>求める人物像：</strong></p>
              <ul class="liDef" style="margin-bottom:15px;">
                <li>PHP、JavaScript、HTML/CSSの実務経験がある方</li>
                <li>WordPressでの開発経験がある方（優遇）</li>
                <li>チームでの開発経験がある方</li>
                <li>新しい技術に興味がある方</li>
              </ul>
              
              <p style="margin-bottom:10px;"><strong>勤務地：</strong><?php echo esc_html(get_theme_mod('recruit_location', '東京都渋谷区渋谷2丁目19番15号')); ?></p>
              <p style="margin-bottom:10px;"><strong>給与：</strong>月給30万円〜50万円（経験・能力に応じて優遇）</p>
              <p style="margin-bottom:10px;"><strong>勤務時間：</strong>9:00〜18:00（フレックスタイム制）</p>
              <p style="margin-bottom:10px;"><strong>休日：</strong>週休2日制（土日祝）、年末年始、夏季休暇</p>
            </div>
          </div>
        </div>
      </div>
    </section>
    
    <!-- 福利厚生 -->
    <section class="bgWhite flex fadeInUp animated">
      <div class="wrapper">
        <h2>福利厚生・待遇</h2>
        <div class="f-wrap-AC">
          <div class="f-item1-2">
            <h3>各種保険</h3>
            <ul class="liDef">
              <li>健康保険</li>
              <li>厚生年金</li>
              <li>雇用保険</li>
              <li>労災保険</li>
            </ul>
          </div>
          <div class="f-item1-2">
            <h3>その他</h3>
            <ul class="liDef">
              <li>交通費全額支給</li>
              <li>資格取得支援制度</li>
              <li>社員研修制度</li>
              <li>昇給・賞与あり</li>
            </ul>
          </div>
        </div>
      </div>
    </section>
    
    <!-- 応募フォーム -->
    <section class="bgColor flex fadeInUp animated">
      <div class="wrapper">
        <h2>応募フォーム</h2>
        <div class="contPad contact">
          <p>以下の項目にご記入の上、送信ボタンを押してください。</p>
          <p class="txtInd2"><span class="fcRedOrange">*</span>印は入力必須項目です。</p>
          <form id="careerForm" method="post">
            <input type="hidden" name="action" value="dd_career_submit">
            <?php wp_nonce_field('dd_career_nonce', 'nonce'); ?>
            <table>
              <tr>
                <th>応募職種<span class="fcRedOrange">*</span></th>
                <td>
                  <select name="position" class="formm" required>
                    <option value="">選択してください</option>
                    <option value="家電修理スタッフ">家電修理スタッフ</option>
                    <option value="WEB開発エンジニア">WEB開発エンジニア</option>
                  </select>
                </td>
              </tr>
              <tr>
                <th>お名前<span class="fcRedOrange">*</span></th>
                <td><input type="text" name="name" class="formm" required></td>
              </tr>
              <tr>
                <th>フリガナ<span class="fcRedOrange">*</span></th>
                <td><input type="text" name="kana" class="formm" required></td>
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
                <th>履歴書・職務経歴書</th>
                <td>
                  <input type="file" name="resume" accept=".pdf,.doc,.docx">
                  <p class="txtInd" style="font-size:12px; color:#666;">PDF、Word形式（最大5MB）</p>
                </td>
              </tr>
              <tr>
                <th>志望動機・自己PR<span class="fcRedOrange">*</span></th>
                <td><textarea name="message" class="formm" rows="8" required></textarea></td>
              </tr>
            </table>
            <div class="txtCenter mt20">
              <button type="submit" class="txtBnrAr">応募する</button>
            </div>
            <div id="careerMessage" style="margin-top:20px;"></div>
          </form>
          
          <script>
          jQuery(document).ready(function($) {
              $('#careerForm').on('submit', function(e) {
                  e.preventDefault();
                  var formData = $(this).serialize();
                  $.ajax({
                      url: '<?php echo admin_url('admin-ajax.php'); ?>',
                      type: 'POST',
                      data: formData,
                      success: function(response) {
                          if (response.success) {
                              $('#careerMessage').html('<p style="color:green; font-weight:bold;">' + response.data.message + '</p>');
                              $('#careerForm')[0].reset();
                          } else {
                              $('#careerMessage').html('<p style="color:red; font-weight:bold;">' + response.data.message + '</p>');
                          }
                      },
                      error: function() {
                          $('#careerMessage').html('<p style="color:red; font-weight:bold;">送信に失敗しました。</p>');
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
