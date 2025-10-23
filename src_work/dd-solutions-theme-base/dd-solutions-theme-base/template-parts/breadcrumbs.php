<?php if ( is_front_page() ) return; ?>
<nav class="breadcrumbs" aria-label="パンくずリスト"><ol><li><a href="<?php echo esc_url(home_url('/')); ?>">HOME</a></li><?php $anc=array_reverse(get_post_ancestors(get_the_ID())); foreach($anc as $a){echo '<li><a href="'.esc_url(get_permalink($a)).'">'.esc_html(get_the_title($a)).'</a></li>'; } ?><li aria-current="page"><?php the_title(); ?></li></ol></nav>
