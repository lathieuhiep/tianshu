<?php
/**
 * Breadcrumbs helper
 * - SEO: schema.org/BreadcrumbList
 * - i18n: dùng text-domain của theme (vd: 'tianshu')
 */

use ExtendSite\PostType\StoryPostType;

if ( ! function_exists( 'es_get_breadcrumbs' ) ) :
    function es_get_breadcrumbs(): array {
        $items = [];
        $home_url  = home_url( '/' );
        $home_text = esc_html__( 'Trang chủ', 'tianshu' );

        // Home
        $items[] = [
            'label' => $home_text,
            'url'   => $home_url,
        ];

        // Front page only
        if ( is_front_page() ) {
            return $items;
        }

        // Search
        if ( is_search() ) {
            $items[] = [
                'label' => sprintf( __( 'Search results for "%s"', 'tianshu' ), get_search_query() ),
                'url'   => '',
            ];
            return $items;
        }

        // 404
        if ( is_404() ) {
            $items[] = [ 'label' => __( '404 Not Found', 'tianshu' ), 'url' => '' ];
            return $items;
        }

        // Category/Tag/Taxonomy archive
        if ( is_tax() || is_category() || is_tag() ) {
            $term = get_queried_object();
            if ( $term && ! is_wp_error( $term ) ) {
                // Optional: link về post type archive nếu là taxonomy gắn riêng cho CPT
                $post_types = get_taxonomy( $term->taxonomy )->object_type ?? [];
                if ( ! empty( $post_types ) ) {
                    $pt = $post_types[0];
                    $archive_link = get_post_type_archive_link( $pt );
                    if ( $archive_link ) {
                        $items[] = [ 'label' => get_post_type_object( $pt )->labels->name, 'url' => $archive_link ];
                    }
                }
                // Cha của term (nếu có)
                if ( $term->parent ) {
                    $parents = [];
                    $p = get_term( $term->parent, $term->taxonomy );
                    while ( $p && ! is_wp_error( $p ) && $p->parent ) {
                        $parents[] = $p;
                        $p = get_term( $p->parent, $term->taxonomy );
                    }
                    if ( $p && ! is_wp_error( $p ) ) {
                        $parents[] = $p;
                    }
                    $parents = array_reverse( $parents );
                    foreach ( $parents as $ptm ) {
                        $items[] = [ 'label' => $ptm->name, 'url' => get_term_link( $ptm ) ];
                    }
                }
                $items[] = [ 'label' => $term->name, 'url' => '' ];
            }
            return es_breadcrumbs_with_paged( $items );
        }

        // Author archive
        if ( is_author() ) {
            $author = get_queried_object();
            $items[] = [ 'label' => sprintf( __( 'Author: %s', 'tianshu' ), $author->display_name ), 'url' => '' ];
            return es_breadcrumbs_with_paged( $items );
        }

        // Date archive
        if ( is_day() || is_month() || is_year() ) {
            if ( is_year() ) {
                $items[] = [ 'label' => get_the_time( 'Y' ), 'url' => '' ];
            } elseif ( is_month() ) {
                $items[] = [ 'label' => get_the_time( 'Y' ), 'url' => get_year_link( get_the_time( 'Y' ) ) ];
                $items[] = [ 'label' => get_the_time( 'F' ), 'url' => '' ];
            } else { // day
                $items[] = [ 'label' => get_the_time( 'Y' ), 'url' => get_year_link( get_the_time( 'Y' ) ) ];
                $items[] = [ 'label' => get_the_time( 'F' ), 'url' => get_month_link( get_the_time( 'Y' ), get_the_time( 'm' ) ) ];
                $items[] = [ 'label' => get_the_time( 'd' ), 'url' => '' ];
            }
            return es_breadcrumbs_with_paged( $items );
        }

        // Post type archive
        if ( is_post_type_archive() ) {
            $pt = get_queried_object();
            $items[] = [ 'label' => $pt->labels->name, 'url' => '' ];
            return es_breadcrumbs_with_paged( $items );
        }

        // Single
        if ( is_singular() ) {
            global $post;
            $post_type = get_post_type( $post );

            // CPT archive link
            if ( $post_type && $post_type !== 'post' && $post_type !== 'page' ) {
                if ( $post_type !== StoryPostType::SLUG ) {
                    $obj = get_post_type_object( $post_type );
                    if ( $obj && $obj->has_archive ) {
                        $items[] = [ 'label' => $obj->labels->name, 'url' => get_post_type_archive_link( $post_type ) ];
                    }
                }
            }

            // Special: chapter → story parent (dựa meta _story_id)
            if ( $post_type === 'chapter' ) {
                $story_id = (int) get_post_meta( $post->ID, '_story_id', true );
                if ( $story_id ) {
                    $items[] = [ 'label' => get_the_title( $story_id ), 'url' => get_permalink( $story_id ) ];
                }
            }

            // For posts: include main category (optional)
            if ( $post_type === 'post' ) {
                $cats = get_the_category( $post->ID );
                if ( $cats ) {
                    $primary = $cats[0];
                    $parents = [];
                    $p = $primary->parent ? get_category( $primary->parent ) : null;
                    while ( $p && $p->parent ) {
                        $parents[] = $p;
                        $p = get_category( $p->parent );
                    }
                    if ( $p ) $parents[] = $p;
                    $parents = array_reverse( $parents );
                    foreach ( $parents as $pc ) {
                        $items[] = [ 'label' => $pc->name, 'url' => get_category_link( $pc ) ];
                    }
                    $items[] = [ 'label' => $primary->name, 'url' => get_category_link( $primary ) ];
                }
            }

            // Pages with ancestors
            if ( $post_type === 'page' ) {
                $ancestors = array_reverse( get_post_ancestors( $post ) );
                foreach ( $ancestors as $aid ) {
                    $items[] = [ 'label' => get_the_title( $aid ), 'url' => get_permalink( $aid ) ];
                }
            }

            // Current single
            $items[] = [ 'label' => get_the_title( $post ), 'url' => '' ];

            return es_breadcrumbs_with_paged( $items );
        }

        // Fallback
        return es_breadcrumbs_with_paged( $items );
    }
endif;

/**
 * Append pagination info if paged
 */
if ( ! function_exists( 'es_breadcrumbs_with_paged' ) ) :
    function es_breadcrumbs_with_paged( array $items ): array {
        $paged = get_query_var( 'paged' ) ?: get_query_var( 'page' );
        if ( $paged && $paged > 1 ) {
            $items[] = [
                'label' => sprintf( __( 'Page %d', 'tianshu' ), (int) $paged ),
                'url'   => '',
            ];
        }
        return $items;
    }
endif;

/**
 * Render breadcrumb HTML with schema.org
 */
if ( ! function_exists( 'es_the_breadcrumbs' ) ) :
    function es_the_breadcrumbs( string $class = 'es-breadcrumb' ): void {
        $items = es_get_breadcrumbs();
        if ( empty( $items ) ) return;

        // Bắt đầu chế độ HTML
        ?>
        <nav class="<?php echo esc_attr( $class ); ?>"
             aria-label="<?php echo esc_attr__( 'Breadcrumb', 'tianshu' ); ?>"
             itemscope
             itemtype="https://schema.org/BreadcrumbList">

            <ol class="es-breadcrumb__list es-list-style-none es-flex es-col-gap-2">
                <?php
                $pos = 1;
                foreach ( $items as $it ) :
                    $label = wp_kses_post( $it['label'] );
                    $url   = esc_url( $it['url'] );
                ?>

                    <li class="es-breadcrumb__item es-flex es-flex-align-center es-col-gap-2 es-fw-bold"
                        itemprop="itemListElement"
                        itemscope
                        itemtype="https://schema.org/ListItem">
                        <?php if ( $url ) : ?>
                            <a itemprop="item" href="<?php echo esc_url( $url ); ?>">
                                <span itemprop="name"><?php echo esc_attr( $label ); ?></span>
                            </a>

                            <i class="es-ic-mask es-ic-mask-angle-right"></i>
                        <?php else : ?>
                            <span class="is-current" itemprop="name"><?php echo esc_html( $label ); ?></span>
                        <?php endif; ?>

                        <meta itemprop="position" content="<?php echo esc_attr( $pos ); ?>" />
                    </li>
                <?php
                    $pos++;
                endforeach;
                ?>
            </ol>
        </nav>
        <?php
    }
endif;