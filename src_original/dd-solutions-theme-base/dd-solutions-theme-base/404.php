<?php get_header(); ?>
<main id="main" class="site-main" role="main">
  <section class="error-404 not-found">
    <h1 class="page-title">ページが見つかりません</h1>
    <p>URLが変更されたか、削除された可能性があります。検索またはトップへお戻りください。</p>
    <?php get_search_form(); ?>
    <p><a href="<?php echo esc_url( home_url('/') ); ?>" class="btn">トップへ戻る</a></p>
  </section>
</main>
<?php get_footer(); ?>