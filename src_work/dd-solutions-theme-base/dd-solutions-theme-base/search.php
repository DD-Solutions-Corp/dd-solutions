<?php get_header(); ?>
<main id="main" class="site-main" role="main">
  <header class="page-header">
    <h1 class="page-title">“<?php echo esc_html( get_search_query() ); ?>” の検索結果</h1>
  </header>

  <?php if ( have_posts() ) : ?>
    <div class="search-list">
      <?php while ( have_posts() ) : the_post(); ?>
        <?php get_template_part( 'template-parts/content', 'search' ); ?>
      <?php endwhile; ?>
    </div>
    <?php the_posts_pagination(); ?>
  <?php else : ?>
    <?php get_template_part( 'template-parts/content', 'none' ); ?>
  <?php endif; ?>
</main>
<?php get_footer(); ?>