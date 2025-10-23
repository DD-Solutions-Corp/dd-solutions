<?php get_header(); ?>
<main id="main" class="site-main" role="main">
  <?php
  while ( have_posts() ) : the_post();
    get_template_part( 'template-parts/content', get_post_type() );
    the_post_navigation([
      'prev_text' => '&larr; %title',
      'next_text' => '%title &rarr;',
    ]);
    if ( comments_open() || get_comments_number() ) {
      comments_template();
    }
  endwhile;
  ?>
</main>
<?php get_footer(); ?>