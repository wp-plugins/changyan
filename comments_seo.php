<?php
if (!function_exists('changyan_seo_comment')){ 
	function changyan_seo_comment($comment, $args, $depth ) {
		$GLOBALS['comment'] = $comment;
        switch($comment->comment_type) :
       case 'pingback':
       case 'trackback':
        ?>
        <li class="post pingback">
		<p><?php _e( 'from:', 'changyan' ); ?> <?php comment_author_link(); ?><?php edit_comment_link( __( 'Edit', 'changyan' ), '<span class="edit-link">', '</span>' ); ?></p>
        <?php
            break;
       default:
        ?>
		<li <?php comment_class(); ?> id="li-comment-<?php comment_ID(); ?>">
			<article id="comment-<?php comment_ID(); ?>" class="comment">
				<footer class="comment-meta">
					<cite class="comment-author vcard">
						<?php
							printf( __( '%1$s on %2$s <span class="says">said:</span>', 'changyan' ),
								sprintf( '<span class="fn">%s</span>', get_comment_author_link() ),
								sprintf( '<a rel="nofollow" href="%1$s"><time pubdate datetime="%2$s">%3$s</time></a>',
									esc_url( get_comment_link( $comment->comment_ID ) ),
									get_comment_time( 'c' ),
									sprintf( __( '%1$s at %2$s', 'changyan' ), get_comment_date(), get_comment_time() )
								)
							);
						?>
					</cite>
				</footer>
	
				<div class="comment-content"><?php comment_text(); ?></div>
				
			</article>
		<?php
        break;
                        endswitch;
	}
}
?>

<div id="ds-ssr" style="display:none">
		<?php if (get_comment_pages_count() > 1 && get_option('page_comments')): ?>
		<nav id="comment-nav-above">
			<h1 class="assistive-text"><?php _e( 'Comment navigation', 'changyan' ); ?></h1>
			<div class="nav-previous"><?php previous_comments_link( __( '&larr; Older Comments', 'changyan' ) ); ?></div>
			<div class="nav-next"><?php next_comments_link( __( 'Newer Comments &rarr;', 'changyan' ) ); ?></div>
		</nav>
		<?php endif;?>
            <ol id="commentlist">
                <?php
                    wp_list_comments(array('callback' => 'changyan_seo_comment'));
                ?>
            </ol>

		<?php if ( get_comment_pages_count() > 1 && get_option( 'page_comments' ) ) :?>
		<nav id="comment-nav-below">
			<h1 class="assistive-text"><?php _e( 'Comment navigation', 'changyan' ); ?></h1>
			<div class="nav-previous"><?php previous_comments_link( __( '&larr; Older Comments', 'changyan' ) ); ?></div>
			<div class="nav-next"><?php next_comments_link( __( 'Newer Comments &rarr;', 'changyan' ) ); ?></div>
		</nav>
		<?php endif; // check for comment navigation ?>
    </div>
