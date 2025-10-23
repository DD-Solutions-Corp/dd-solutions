<?php $title=get_the_title(); $subtitle=get_post_meta(get_the_ID(), '_page_subtitle', true); ?>
<section class="page-hero"><div class="container"><h1 class="page-title"><?php echo esc_html($title); ?></h1><?php if($subtitle): ?><p class="page-subtitle"><?php echo esc_html($subtitle); ?></p><?php endif; ?></div></section>
