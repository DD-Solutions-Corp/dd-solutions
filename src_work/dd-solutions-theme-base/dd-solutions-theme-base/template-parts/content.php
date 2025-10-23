<?php
/**
 * Template Name: Contact
 * Description: お問い合わせページ用テンプレート（共通ヘッダー/フッター使用）
 */
get_header(); ?>
<main id="main" class="site-main" role="main">
  <?php
  while ( have_posts() ) : the_post();
    // 専用のテンプレートパーツがあればそれを、無ければ本文を表示
    if ( locate_template( 'template-parts/content-contact.php', true, false ) ) {
      get_template_part( 'template-parts/content', 'contact' );
    } else {
      get_template_part( 'template-parts/content', 'page' ); // 汎用
    }
  endwhile;
  ?>
</main>
<?php get_footer(); ?>