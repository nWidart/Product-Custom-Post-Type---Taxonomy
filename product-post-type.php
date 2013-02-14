<?php
/*
Plugin Name: Product Post Type
Plugin URI: http://www.nicolaswidart.com
Description: Enables a product post type and taxonomies.
Version: 0.4
Author: Devin Price modified by Nicolas Widart
Author URI: http://www.nicolaswidart.com/site/about
License: GPLv2
*/

if ( ! class_exists( 'Product_Post_Type' ) ) :

class Product_Post_Type {

	// Current plugin version
	var $version = 0.4;

	function __construct() {

		// Runs when the plugin is activated
		register_activation_hook( __FILE__, array( &$this, 'plugin_activation' ) );

		// Add support for translations
		load_plugin_textdomain( 'productposttype', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		// Adds the post type and taxonomies
		add_action( 'init', array( &$this, 'init' ) );

		// Thumbnail support for product posts
		// add_theme_support( 'post-thumbnails', array( 'product' ) );

		// Adds columns in the admin view for thumbnail and taxonomies
		add_filter( 'manage_products_posts_columns', array( &$this, 'edit_columns' ) );
		add_action( 'manage_posts_custom_column', array( &$this, 'column_display' ), 10, 2 );
		// Make Columns sortable
		add_filter( "manage_edit-products_sortable_columns", array( &$this,"sortable_columns" ) );

		// Allows filtering of posts by taxonomy in the admin view
		add_action( 'restrict_manage_posts', array( &$this, 'add_taxonomy_filters' ) );

		// Show portfolio post counts in the dashboard
		add_action( 'right_now_content_table_end', array( &$this, 'add_cpt_counts' ) );

		// Give the portfolio menu item a unique icon
		add_action( 'admin_head', array( &$this, 'portfolio_icon' ) );
	}

	/**
	 * Flushes rewrite rules on plugin activation to ensure portfolio posts don't 404
	 * http://codex.wordpress.org/Function_Reference/flush_rewrite_rules
	 */

	function plugin_activation() {
		$this->init();
		flush_rewrite_rules();
	}

	function init() {

		/**
		 * Enable the Portfolio custom post type
		 * ToDO: Abstract the name away
		 *
		 * http://codex.wordpress.org/Function_Reference/register_post_type
		 */

		$labels = array(
			'name' => __( 'Products', 'productposttype' ),
			'singular_name' => __( 'Product', 'productposttype' ),
			'add_new' => __( 'Add New Product', 'productposttype' ),
			'add_new_item' => __( 'Add New Product', 'productposttype' ),
			'edit_item' => __( 'Edit Product', 'productposttype' ),
			'new_item' => __( 'Add New Product', 'productposttype' ),
			'view_item' => __( 'View Product', 'productposttype' ),
			'search_items' => __( 'Search Product', 'productposttype' ),
			'not_found' => __( 'No products found', 'productposttype' ),
			'not_found_in_trash' => __( 'No products found in trash', 'productposttype' )
		);

		$args = array(
		    	'labels' => $labels,
		    	'public' => true,
				'supports' => array( 'title', 'editor', 'revisions' ),
				'capability_type' => 'post',
				'rewrite' => array("slug" => "products"), // Permalinks format
				'menu_position' => 5,
				'has_archive' => true
		);

		register_post_type( 'products', $args );

		/**
		 * Register a taxonomy for Brands
		 *
		 * http://codex.wordpress.org/Function_Reference/register_taxonomy
		 */

		$taxonomy_labels = array(
			'name' => _x( 'Brands', 'productposttype' ),
			'singular_name' => _x( 'Brand', 'productposttype' ),
			'search_items' => _x( 'Search Brand', 'productposttype' ),
			'popular_items' => _x( 'Popular Brand', 'productposttype' ),
			'all_items' => _x( 'All Brand', 'productposttype' ),
			'parent_item' => _x( 'Parent Brand', 'productposttype' ),
			'parent_item_colon' => _x( 'Parent Brand:', 'productposttype' ),
			'edit_item' => _x( 'Edit Brand', 'productposttype' ),
			'update_item' => _x( 'Update Brand', 'productposttype' ),
			'add_new_item' => _x( 'Add New Brand', 'productposttype' ),
			'new_item_name' => _x( 'New Brand Name', 'productposttype' ),
			'separate_items_with_commas' => _x( 'Separate brand tags with commas', 'productposttype' ),
			'add_or_remove_items' => _x( 'Add or remove brand tags', 'productposttype' ),
			'choose_from_most_used' => _x( 'Choose from the most used brand tags', 'productposttype' ),
			'menu_name' => _x( 'Brands', 'productposttype' )
		);

		$taxonomy_args = array(
			'labels' => $taxonomy_labels,
			'public' => true,
			'show_in_nav_menus' => true,
			'show_ui' => true,
			'show_tagcloud' => true,
			'hierarchical' => true,
			'rewrite' => array( 'slug' => 'brand' ),
			'query_var' => true
		);

		register_taxonomy( 'brands', array( 'products' ), $taxonomy_args );

	}

	/**
	 * Add Columns to Portfolio Edit Screen
	 * http://wptheming.com/2010/07/column-edit-pages/
	 */

	function edit_columns( $columns ) {
		$columns = array(
			'cb'      => '<input type="checkbox" />',
			'title'      => __( 'Title',      'trans' ),
			'price' => __( 'Price', 'trans' ),
			'brand' => __('Brand', 'trans'),
			'date'     => __( 'Date', 'trans' ),
		);
		return $columns;
	}

	// Put data into custom columns
	function column_display( $columns, $post_id ) {

		// Code from: http://wpengineer.com/display-post-thumbnail-post-page-overview
		switch ( $columns ) {
			case 'price':
				$price = get_field('price_tcc', $post_id);
				echo '€ ' . $price;
				break;

			case 'brand':
				$brand = wp_get_post_terms( $post_id, $taxonomy = 'brands' );
				echo $brand[0]->name;
				break;
		}
	}
	/**
	 * Make the custom columns sortable
	 */
	function sortable_columns() {
		return array(
			'price' => 'price',
			'title' => 'title',
			'date' => 'date',
			'brand' => 'brand',
		);
	}
	/**
	 * Adds taxonomy filters to the portfolio admin page
	 * Code artfully lifed from http://pippinsplugins.com
	 */

	function add_taxonomy_filters() {
		global $typenow;

		// An array of all the taxonomyies you want to display. Use the taxonomy name or slug
		$taxonomies = array( 'brands' );

		// must set this to the post type you want the filter(s) displayed on
		if ( $typenow == 'products' ) {

			foreach ( $taxonomies as $tax_slug ) {
				$current_tax_slug = isset( $_GET[$tax_slug] ) ? $_GET[$tax_slug] : false;
				$tax_obj = get_taxonomy( $tax_slug );
				$tax_name = $tax_obj->labels->name;
				$terms = get_terms($tax_slug);
				if ( count( $terms ) > 0) {
					echo "<select name='$tax_slug' id='$tax_slug' class='postform'>";
					echo "<option value=''>$tax_name</option>";
					foreach ( $terms as $term ) {
						echo '<option value=' . $term->slug, $current_tax_slug == $term->slug ? ' selected="selected"' : '','>' . $term->name .' (' . $term->count .')</option>';
					}
					echo "</select>";
				}
			}
		}
	}

	/**
	 * Add Products count to "Right Now" Dashboard Widget
	 */

	function add_cpt_counts() {
	        if ( ! post_type_exists( 'products' ) ) {
	             return;
	        }

	        $num_posts = wp_count_posts( 'products' );
	        $num = number_format_i18n( $num_posts->publish );
	        $text = _n( 'Products', 'Products', intval($num_posts->publish) );
	        if ( current_user_can( 'edit_posts' ) ) {
	            $num = "<a href='edit.php?post_type=products'>$num</a>";
	            $text = "<a href='edit.php?post_type=products'>$text</a>";
	        }
	        echo '<td class="first b b-products">' . $num . '</td>';
	        echo '<td class="t products">' . $text . '</td>';
	        echo '</tr>';

	        if ($num_posts->pending > 0) {
	            $num = number_format_i18n( $num_posts->pending );
	            $text = _n( 'Products Pending', 'Products Pending', intval($num_posts->pending) );
	            if ( current_user_can( 'edit_posts' ) ) {
	                $num = "<a href='edit.php?post_status=pending&post_type=products'>$num</a>";
	                $text = "<a href='edit.php?post_status=pending&post_type=products'>$text</a>";
	            }
	            echo '<td class="first b b-products">' . $num . '</td>';
	            echo '<td class="t products">' . $text . '</td>';

	            echo '</tr>';
	        }
	}

	/**
	 * Displays the custom post type icon in the dashboard
	 */

	function portfolio_icon() { ?>
	    <style type="text/css" media="screen">
	        #menu-posts-products .wp-menu-image {
	            background: url(<?php echo plugin_dir_url( __FILE__ ); ?>images/product-icon.png) no-repeat 6px 8px !important;
	        }
			#menu-posts-products:hover .wp-menu-image, #menu-posts-portfolio.wp-has-current-submenu .wp-menu-image {
	            background-position:6px -16px !important;
	        }
			#icon-edit.icon32-posts-products {background: url(<?php echo plugin_dir_url( __FILE__ ); ?>images/products-32x32.png) no-repeat;}
	    </style>
	<?php }

}

new Product_Post_Type;

endif;
