<?php
// Chỉ chạy khi có bài (đảm bảo hiệu năng)
use ExtendSite\Repositories\ChapterRepository;

if ( have_posts() ) :
    rewind_posts(); // Reset loop để đọc lại các truyện
    $schema_books = [];

    while ( have_posts() ) :
        the_post();

        $story_id    = get_the_ID();
        $story_url   = get_permalink();
        $story_title = get_the_title();
        $story_desc  = wp_strip_all_tags( get_the_excerpt() );
        $cover_url   = get_the_post_thumbnail_url( $story_id, 'medium_large' );
        $author_name = get_the_author();
        $genres      = get_the_terms( $story_id, 'story_genre' );
        $genre_names = [];

        if ( $genres && ! is_wp_error( $genres ) ) {
            $genre_names = wp_list_pluck( $genres, 'name' );
        }

        // Lấy chương mới nhất (đã có trong ChapterRepository)
        $latest = ChapterRepository::get_latest_chapter( $story_id );

        // Xây object Book
        $book_data = [
            '@type'          => 'Book',
            '@id'            => $story_url,
            'url'            => $story_url,
            'name'           => $story_title,
            'description'    => $story_desc,
            'author'         => [
                '@type' => 'Person',
                'name'  => $author_name,
            ],
            'datePublished'  => get_the_date( 'c' ),
            'image'          => $cover_url,
            'genre'          => $genre_names,
        ];

        // Nếu có chương mới nhất → thêm hasPart
        if ( $latest ) {
            $book_data['hasPart'] = [
                '@type'    => 'Chapter',
                '@id'      => $latest['url'],
                'url'      => $latest['url'],
                'name'     => sprintf( __( 'Chương %d', 'extend-site' ), intval( $latest['number'] ) ),
                'position' => intval( $latest['number'] ),
            ];
        }

        $schema_books[] = $book_data;

    endwhile;

    // Gói toàn bộ thành CollectionPage
    $schema_page = [
        '@context' => 'https://schema.org',
        '@type'    => 'CollectionPage',
        '@id'      => get_pagenum_link(),
        'name'     => get_the_archive_title(),
        'url'      => get_pagenum_link(),
        'hasPart'  => $schema_books,
    ];
    ?>

    <script type="application/ld+json">
        <?php echo wp_json_encode( $schema_page, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ); ?>
    </script>
<?php
endif;