<?php
/*
Plugin Name: Page Template Plugin : 'Good To Be Bad'
Plugin URI: http://www.wpexplorer.com/wordpress-page-templates-plugin/
Version: 1.2.0
Author: WPExplorer
Author URI: http://www.wpexplorer.com/
*/

class PageTemplater {

	/**
	 * A reference to an instance of this class.
	 */
	private static $instance;

	/**
	 * The array of templates that this plugin tracks.
	 */
	protected $templates;
    
	/**
	 * Subdirectory to find the templates.
	 */
	protected $templates_dir;

	/**
	 * Returns an instance of this class. 
	 */
	public static function get_instance() {

		if ( null == self::$instance ) {
			self::$instance = new PageTemplater();
		} 

		return self::$instance;

	} 

	/**
	 * Initializes the plugin by setting filters and administration functions.
	 */
	private function __construct() {

		$this->templates = array();
        
		// Set the templates dir to the plugin dir, ensuring that it has a trailing slash.
		$this->templates_dir = trailingslashit( apply_filters( 'pagetemplater_templates_dir', '.' ) );

		// Add a filter to the attributes metabox to inject template into the cache.
		if ( version_compare( floatval( get_bloginfo( 'version' ) ), '4.7', '<' ) ) {

			// 4.6 and older
			add_filter(
				'page_attributes_dropdown_pages_args',
				array( $this, 'register_project_templates' )
			);

		} else {

			// Add a filter to the wp 4.7 version attributes metabox
			add_filter(
				'theme_page_templates', array( $this, 'add_new_template' )
			);

		}

		// Add a filter to the save post to inject out template into the page cache
		add_filter(
			'wp_insert_post_data', 
			array( $this, 'register_project_templates' ) 
		);


		// Add a filter to the template include to determine if the page has our 
		// template assigned and return it's path
		add_filter(
			'template_include', 
			array( $this, 'view_project_template') 
		);

		add_filter(
			'acf/location/rule_values/page_template',
			array( $this, 'acf_page_templates_rules_values')
		);

		// Get the list of templates dynamically.
		$templates_path = plugin_dir_path( __FILE__ ) . $this->templates_dir;
		$plugin_file = basename( __FILE__ );
		$all_files = scandir( $templates_path );
		$template_files = array();
		foreach ( $all_files as $file ) {
			// Don't examine this file because the reg ex below will match it.
			if ( $plugin_file == $file ) {
				continue;
			}
			if ( preg_match( '/\.php$/', $file ) ) {
				// Read the template name from the file.
				if ( preg_match( '|Template Name:(.*)$|mi', file_get_contents( $templates_path . $file ), $header ) ) {
					// Add filename and template name to templates list.
					$template_files[ $this->templates_dir . $file ] = _cleanup_header_comment( $header[ 1 ] );
				}
			}
		}
        
		// Add your templates to this array.
		$this->templates = apply_filters( 'pagetemplater_templates_found', $template_files );
	} 

	/**
	 * Adds our template to the page dropdown for v4.7+
	 *
	 */
	public function add_new_template( $posts_templates ) {
		$posts_templates = array_merge( $posts_templates, $this->templates );
		return $posts_templates;
	}

	/**
	 * Adds our template to the pages cache in order to trick WordPress
	 * into thinking the template file exists where it doens't really exist.
	 */
	public function register_project_templates( $atts ) {

		// Create the key used for the themes cache
		$cache_key = 'page_templates-' . md5( get_theme_root() . '/' . get_stylesheet() );

		// Retrieve the cache list. 
		// If it doesn't exist, or it's empty prepare an array
		$templates = wp_get_theme()->get_page_templates();
		if ( empty( $templates ) ) {
			$templates = array();
		}

		// New cache, therefore remove the old one
		wp_cache_delete( $cache_key , 'themes');

		// Now add our template to the list of templates by merging our templates
		// with the existing templates array from the cache.
		$templates = array_merge( $templates, $this->templates );

		// Add the modified cache to allow WordPress to pick it up for listing
		// available templates
		wp_cache_add( $cache_key, $templates, 'themes', 1800 );

		return $atts;

	} 

	/**
	 * Checks if the template is assigned to the page
	 */
	public function view_project_template( $template ) {
		// Return the search template if we're searching (instead of the template for the first result)
		if ( is_search() ) {
			return $template;
		}

		// Get global post
		global $post;

		// Return template if post is empty
		if ( ! $post ) {
			return $template;
		}

		// Return default template if we don't have a custom one defined
		if ( ! isset( $this->templates[get_post_meta( 
			$post->ID, '_wp_page_template', true 
		)] ) ) {
			return $template;
		} 

		$file = plugin_dir_path( __FILE__ ). get_post_meta( 
			$post->ID, '_wp_page_template', true
		);

		// Just to be safe, we check if the file exist first
		if ( file_exists( $file ) ) {
			return $file;
		} else {
			// Template file not found.
			echo $file;
		}

		// Return template
		return $template;

	}
	
	public function acf_page_templates_rules_values( $choices ) {
		$templates = wp_get_theme()->get_page_templates();
		if ( empty( $templates ) ) {
			$templates = array();
		}

		$templates = array_merge( $templates, $this->templates );

		foreach( $templates as $key => $value ) {
			$choices[ $key ] = $value;
		}

		return $choices;
	}

} 
add_action( 'plugins_loaded', array( 'PageTemplater', 'get_instance' ) );
