<?php

namespace ExtendSite\PostType;

use function es_add_custom_taxonomy_filter_to_cpt;

defined('ABSPATH') || exit;

class PortfolioPostType extends BasePostType
{
    // slug post type
    public const SLUG = 'portfolio';
    public const TAX_SLUG = 'portfolio_category';
    public const SINGULAR = 'Portfolio';
    public const PLURAL = 'Portfolios';
    public const TAX_NAME = 'Danh mục Portfolio';

    // name file template
    public const TEMPLATE_SINGLE = 'single-portfolio.php';
    public const TEMPLATE_ARCHIVE = 'archive-portfolio.php';
    public const TEMPLATE_TAX_CAT = 'taxonomy-portfolio-category.php';

    public function __construct(array $args = [])
    {
        parent::__construct($args);

        // Đăng ký taxonomy kèm theo (vd: portfolio_category)
        add_action('init', function () {
            $this->register_taxonomy(self::TAX_SLUG, 'Danh mục', 'Danh mục', [
                'hierarchical' => true,
                'rewrite' => ['slug' => 'portfolio-category'],
            ]);

            es_add_custom_taxonomy_filter_to_cpt(self::SLUG, self::TAX_SLUG);
        });
    }
}