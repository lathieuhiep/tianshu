<?php
namespace ExtendSite\Ajax;

use ExtendSite\DB\LatestChapterTable;
use ExtendSite\Repositories\ChapterRepository;
use ExtendSite\Views\ViewTracker;

defined('ABSPATH') || exit;

class LoadLatestStories
{
    public const ACTION = 'load_latest_stories';

    /**
     * Initialize AJAX handlers
     */
    public static function init(): void
    {
        add_action('wp_ajax_' . self::ACTION, [self::class, 'handle']);
        add_action('wp_ajax_nopriv_' . self::ACTION, [self::class, 'handle']);
    }

    /**
     * Handle AJAX request
     */
    public static function handle(): void {
        check_ajax_referer(EXTEND_SITE_NONCE_ACTION, 'security');

        $page        = max(1, absint($_POST['page'] ?? 1));
        $limit       = max(1, min(36, absint($_POST['limit'] ?? 12)));
        $image_size  = sanitize_text_field($_POST['image_size'] ?? 'medium');
        $offset      = ($page - 1) * $limit;

        $rows = LatestChapterTable::get_latest_stories_paginated($limit, $offset);

        if (empty($rows)) {
            wp_send_json_success(['html' => '', 'next' => false]);
        }

        $story_ids = wp_list_pluck($rows, 'story_id');
        $html = self::render_view($story_ids, $image_size);

        // Kiểm tra xem còn dữ liệu tiếp không
        $count_rows = count($rows);
        $has_next   = $count_rows === $limit;

        wp_send_json_success([
            'html' => $html,
            'next' => $has_next,
        ]);
    }

    /**
     * Render HTML (can be reused in template part)
     * @param array $story_ids
     * @param string $image_size
     * @return string
     */
    public static function render_view( array $story_ids, string $image_size): string
    {
        $query = new \WP_Query([
            'post_type'      => 'story',
            'post__in'       => $story_ids,
            'orderby'        => 'post__in',
            'posts_per_page' => count($story_ids),
            'no_found_rows'  => true,
        ]);

        if ( !$query->have_posts() ) {
            return esc_html__('Chưa có dữ liệu.', 'extend-site');
        }

        ob_start();
        while ($query->have_posts()): $query->the_post();
            $latest_chapter = ChapterRepository::get_latest_chapter( get_the_ID() );
            $story_views = ViewTracker::format_short( ViewTracker::get_story_views( get_the_ID() ) );
        ?>

            <div class="item">
                <div class="thumbnail">
                    <a class="image-link" href="<?php the_permalink(); ?>" title="<?php the_title(); ?>">
                        <?php
                        if (has_post_thumbnail()) :
                            the_post_thumbnail( $image_size );
                        else:
                            ?>
                            <img src="<?php echo esc_url(EXTEND_SITE_URL . 'assets/images/no-image.png'); ?>"
                                 alt="<?php the_title(); ?>"/>
                        <?php endif; ?>
                    </a>

                    <div class="meta-data">
                        <div class="meta-item es-flex es-flex-align-center es-gap-2">
                            <i class="es-ic-mask es-ic-mask-eye" aria-hidden="true"></i>
                            <span itemprop="interactionCount"><?php echo esc_html( $story_views ); ?></span>
                        </div>
                    </div>
                </div>

                <div class="detail es-p-3">
                    <h4 class="title  es-fs-sm es-mb-2 es-two-line-clamp">
                        <a href="<?php the_permalink(); ?>" title="<?php the_title() ?>"><?php echo the_title() ?></a>
                    </h4>

                    <div class="detail__info es-text-sm es-text-gray-600 es-flex es-items-center es-flex-justify-space-between es-row-gap-1 es-col-gap-2 es-fs-sm">
                        <?php if ( !empty( $latest_chapter ) ): ?>
                            <div class="story-latest-box"
                                 itemprop="hasPart"
                                 itemscope
                                 itemtype="https://schema.org/Chapter"
                            >
                                <a class="es-story-link"
                                   href="<?php echo esc_url( $latest_chapter['url'] ); ?>"
                                   title="<?php echo esc_attr( sprintf( esc_html__( 'Đọc chương %s truyện %s', 'extend-site' ), $latest_chapter['number'], get_the_title() ) ); ?>"
                                   aria-label="<?php echo esc_attr( sprintf( esc_html__( 'Đọc chương %s truyện %s', 'extend-site' ), $latest_chapter['number'], get_the_title() ) ); ?>"
                                   itemprop="url"
                                   rel="bookmark"
                                >
                                    <span itemprop="name">
                                        <?php
                                        printf(
                                            esc_html__( 'Chương %d: %s', 'extend-site' ),
                                            intval( $latest_chapter['number'] ),
                                            esc_html( $latest_chapter['title'] )
                                        );
                                        ?>
                                    </span>
                                </a>
                                <meta itemprop="position" content="<?php echo intval( $latest_chapter['number'] ); ?>">
                            </div>
                        <?php endif; ?>

                        <time datetime="<?php echo esc_attr( get_the_modified_date( 'c' ) ); ?>"
                              itemprop="dateModified">
                            <?php echo esc_html( es_display_time_ago() ); ?>
                        </time>
                    </div>
                </div>
            </div>

        <?php
        endwhile;
        wp_reset_postdata();

        return trim(ob_get_clean());
    }
}