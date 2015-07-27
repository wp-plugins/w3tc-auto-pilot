<?php
/**
 * Plugin Name: W3TC Auto Pilot
 * Plugin URI: https://wordpress.org/plugins/w3tc-auto-pilot/
 * Description: Put W3 Total Cache on auto pilot. This plugin allows you to control W3 Total Cache in such a manner that no one knows you're using it, not even your admins. Either network activate it or activate it per site.
 * Version: 1.1.0
 * Author: Sybre Waaijer
 * Author URI: https://cyberwire.nl/
 * License: GPLv2 or later
 * Textdomain: WapPilot
 */

/* Developer Notes */

/**
 * == Hook reference list ==
 * 
 * after_switch_theme				=> After theme has switched 										=> Action
 * customize_save_after 			=> After customizer settings have been saved 						=> Action
 * wp_update_nav_menu	 			=> After menu has been updated										=> Action		=> Needs $nav_menu_selected_id
 *
 * widget_update_callback 			=> After widget has been updated or removed							=> Filter		=> Needs to return $instance
 *
 * w3tc_can_print_comment			=> W3TC HTML comment information about the plugin's cache control	=> Filter
 * page_row_actions					=> Same as post_row_actions, shows below each page on post.php		=> Filter
 *
 * admin_bar_menu					=> Admin bar is rendered											=> Action
 * wp_before_admin_bar_render 		=> Before admin bar gets rendered									=> Action
 *
 * admin_menu						=> The admin_menu rendering											=> Action
 *  
 * post_submitbox_start				=> The submitbox of a post/page										=> Action
 * after_setup_theme				=> Very early call													=> Action
 */

/** 
 * Initialize this plugin. Uncomment any action you don't wish to use.
 *
 * @since 1.0.0
 */
function wap_w3tc_init() {
	
	//* Adds advanced flushing on update of certain items (especially related to object cache)
	//* Usage of each action hook is documented above under Developer Notes
	add_action( 'after_switch_theme', 'wap_w3tc_flush_all' );
	add_action( 'customize_save_after', 'wap_w3tc_flush_all', 20 );
	add_action( 'wp_update_nav_menu', 'wap_w3tc_flush_menu', 11, 1 );
	add_filter( 'widget_update_callback', 'wap_w3tc_flush_all_widget', 11, 4 ); // Will not always fire, but does the job :)	
	
	//* Removes admin bar entry of W3 Total Cache
	add_action( 'admin_bar_menu', 'wap_w3tc_remove_adminbar', 20 );
	add_action( 'wp_before_admin_bar_render', 'wap_w3tc_remove_adminbar', 20 );
	
	//* Removes admin menu entry of W3 Total Cache
	add_action( 'admin_menu', 'wap_w3tc_remove_adminmenu', 20 );
	
	//* Removes admin menu popup script
	add_action( 'init', 'wap_w3tc_remove_script', 20);
	
	//* Removes "Purge From Cache" link above the "publish/save" button on posts/pages
	//* Also removes the "Purge From Cache" link in post/pages lists
	add_action( 'admin_init', 'wap_w3tc_remove_flush_per_post_page', 20); 
	
	//* Removes the W3 Total Cache comments in the HTML output
	add_filter( 'w3tc_can_print_comment', '__return_false', 20);
	
	//* Removes admin notices for non-super-admins
	add_action( 'admin_init', 'wap_w3tc_remove_notices', 20);
	
	//* Added functionality to prevent cache bugs with Domain Mapping (by WPMUdev)
	add_action( 'save_post', 'wap_mapped_clear', 20);
	
	//* Adds a redirect notice if a user still tries to access the w3tc dashboard.
	add_action( 'admin_init', 'wap_die_notice', 20);
	
}
add_action( 'after_setup_theme', 'wap_w3tc_init' ); // Call very early, before init and admin_init

/**
 * Plugin locale 'AutoDescription'
 *
 * File located in plugin folder autodescription/language/
 *
 * @since 1.0.0
 */
function wap_locale_init() {
	$plugin_dir = basename(dirname(__FILE__));
	load_plugin_textdomain( 'WapPilot', false, $plugin_dir . '/language/');
}
add_action('plugins_loaded', 'wap_locale_init');

/** 
 * Forces an extra flush on mapped domain.
 *
 * @requires plugin Domain Mapping by WPMUdev
 *
 * @since 1.0.2
 */
function wap_mapped_clear() {
	//* Check for domain-mapping plugin
	if ( is_plugin_active( 'domain-mapping/domain-mapping.php' ) ) {
		global $wpdb,$blog_id;
				
		$ismapped = wp_cache_get('wap_mapped_clear_' . $blog_id, 'domain_mapping' );
		if ( false === $ismapped ) {
			$ismapped = $wpdb->get_var( $wpdb->prepare( "SELECT domain FROM {$wpdb->base_prefix}domain_mapping WHERE blog_id = %d", $blog_id ) ); //string
			wp_cache_set('wap_mapped_clear_' . $blog_id, $ismapped, 'domain_mapping', 3600 ); // 1 hour
		}
		
		//* string $ismapped, if mapped != ''
		// We shouldn't flush the object cache here, otherwise the wp_cache_set is useless above this.
		if ( !empty($ismapped) ) {
			add_action( 'save_post', 'wap_w3tc_flush_all', 21 ); // We just flush it entirely. But only if the domain is mapped! :D
		//	add_action( 'save_post', 'wap_w3tc_flush_page_mapped', 21 ); // Unfortunately, not working. I'll try to discuss this with Frederick.
		}
	}
}

/**
 * Flushes entire page cache & the specific page on both mapped / non mapped url.
 *
 * Fixes Domain Mapping mixed cache
 *
 * @param array $post_ID 	the updated post_ID
 *
 * @since 1.1.0
 */
function wap_w3tc_flush_page_mapped( $post_ID ) {
	global $wpdb, $blog_id, $current_blog;
	
	$post = get_post( $post_ID );
	
	//* Check if subdomain install, else just flush all
	if ((defined('SUBDOMAIN_INSTALL') && SUBDOMAIN_INSTALL) || (defined('VHOST') && VHOST == 'yes')) {
	
		$originaldomain = $current_blog->domain;
		
		//* Get mapped domain
		$mappeddomain = wp_cache_get('wap_mapped_domain_' . $blog_id, 'domain_mapping' );
		if ( false === $mappeddomain ) {
			$mappeddomain = $wpdb->get_var( $wpdb->prepare( "SELECT domain FROM {$wpdb->base_prefix}domain_mapping WHERE blog_id = %d", $blog_id ) ); //string
			wp_cache_set('wap_mapped_domain_' . $blog_id, $mappeddomain, 'domain_mapping', 3600 ); // 1 hour
		}
		
		//* Get scheme setting of mapped domain
		$mappedscheme = wp_cache_get('wap_mapped_scheme_' . $blog_id, 'domain_mapping' );
		if ( false === $mappedscheme ) {
			$mappedscheme = $wpdb->get_var( $wpdb->prepare( "SELECT scheme FROM {$wpdb->base_prefix}domain_mapping WHERE blog_id = %d", $blog_id ) ); //bool
			wp_cache_set('wap_mapped_scheme_' . $blog_id, $mappedscheme, 'domain_mapping', 3600 ); // 1 hour
		}
		
		//* Get scheme of mapped domain
		if ($mappedscheme === '1') {
			$scheme_mapped = 'https://';
		} else if ($mappedscheme === '0') {
			$scheme_mapped = 'http://';
		}
		
		//* Get scheme of orginal domain
		if ( method_exists( 'Domainmap_Plugin', 'instance' ) ) {
			$domainmap_instance = Domainmap_Plugin::instance();
			$schemeoriginal = $domainmap_instance->get_option("map_force_frontend_ssl") ? 'https://' : 'http://';
		} else {
			$schemeoriginal = is_ssl() ? 'https://' : 'http://'; //Fallback, not too reliable.
		}
		
		$relative_url_slash_it = wp_make_link_relative( trailingslashit( get_permalink( $post_ID ) ) );
		$relative_url = wp_make_link_relative( get_permalink( $post_ID ) );
		
		if ( $post->ID == get_option( 'page_on_front' ) ) {
			$geturls = array(				
				$mappeddomain, // example: mappedomain.com
				$scheme_mapped . $mappeddomain, // example: http://mappedomain.com
				$originaldomain, // example: subdomain.maindomain.com
				$schemeoriginal . $originaldomain, // example: https://subdomain.maindomain.com
			);
		} else {
			$geturls = array (
				$mappeddomain . $relative_url, // example: mappedomain.com/some-post(/)
				$mappeddomain . $relative_url_slash_it, // example: mappedomain.com/some-post/
				
				$scheme_mapped .  $mappeddomain . $relative_url, // example: http://mappedomain.com/some-post(/)
				$scheme_mapped .  $mappeddomain . $relative_url_slash_it, // example: http://mappedomain.com/some-post/
				
				$originaldomain . $relative_url, // example: subdomain.maindomain.com/some-post(/)
				$originaldomain . $relative_url_slash_it, // example: subdomain.maindomain.com/some-post/
				
				$schemeoriginal . $originaldomain . $relative_url, // example: https://subdomain.maindomain.com/some-post(/)
				$schemeoriginal . $originaldomain . $relative_url_slash_it, // example: https://subdomain.maindomain.com/some-post/
			);
		}
		
		//* Flush both mapped and original
		foreach ( $geturls as $key => $url ) {
			if ( function_exists( 'w3tc_pgcache_flush_url' ) )
				w3tc_pgcache_flush_url( $url );
			
			if ( function_exists( 'w3tc_pgcache_flush_post' ) ) {
				w3tc_pgcache_flush_post( $post_ID );
				w3tc_pgcache_flush_post( $post_id = null );
			}
			
			if ( function_exists( 'w3_instance' ) ) {	
				$w3_pgcache = w3_instance('W3_CacheFlush');
				return $w3_pgcache->prime_post($post_ID);
			}
		}
		
		/**
		 * There are only two hard things in Computer Science: cache invalidation and naming things.
		 * -- Phil Karlton
		 *
		 * Why this doesn't work as expected:
		 *  + Cache IS invalidated. This is good.
		 *	- Old cache file/chunk still exists.
		 *	- Cache ISN'T rebuilt. So the invalid cache is loaded upon request until the ORIGINAL url fetches it.
		 *	- ORIGINAL url can't be requested (on visit), this is because the domain is mapped. 
		 *	- Old cache stays in there until explicit expiration date sent in the cron as set in the w3 total cache settings.
		 *
		 * The solution:
		 *	+ Add a cache rebuild mechanism that we can fire directly
		 *	+ Make urls' be more explicit. This is a hard issue and shouldn't be addressed by unique ID's, but rather a find and replace (for URLs).
		 *
		 * This is a serious bug that should've been addressed a few years ago. This url clearing thing simply doesn't work.
		 */
		 
		/**
		 * The original functions
		 * @param url
		 *
		 * @example
		 */
		/*
		function flush_url($url) {
			static $cache, $mobile_groups, $referrer_groups, $encryptions, $compressions;
			if (!isset($cache)) $cache = $this->_get_cache();
			if (!isset($mobile_groups)) $mobile_groups  = $this->_get_mobile_groups();
			if (!isset($referrer_groups)) $referrer_groups = $this->_get_referrer_groups();
			if (!isset($encryptions)) $encryptions = $this->_get_encryptions();
			if (!isset($compressions)) $compressions = $this->_get_compressions();
			
			$this->_flush_url($url, $cache, $mobile_groups, $referrer_groups, $encryptions, $compressions);
		}
		
		function _flush_url($url, $cache, $mobile_groups, $referrer_groups, $encryptions, $compressions) {
			foreach ($mobile_groups as $mobile_group) {
				foreach ($referrer_groups as $referrer_group) {
					foreach ($encryptions as $encryption) {
						foreach ($compressions as $compression) {
							$page_key = $this->_get_page_key($mobile_group, $referrer_group, $encryption, $compression, false, $url);
							$cache->delete($page_key);
						}
					}
				}
			}
		}		
		*/

	} else {
		
		// Purge the entire page cache (current domain)
		// This should be more elaborated, but I don't have the resources or time to apply this.
		if ( function_exists( 'w3tc_pgcache_flush' ) )
			w3tc_pgcache_flush();
	
	}
}

/** 
 * Runs all flushes
 *
 * @since 1.0.0
 */
function wap_w3tc_flush_all() {
	
	// Purge the entire db cache
	if ( function_exists( 'w3tc_dbcache_flush' ) ) {
		w3tc_dbcache_flush();
	}
	
	// Purge the entire object cache
	if ( function_exists( 'w3tc_objectcache_flush' ) ) {
		w3tc_objectcache_flush();
	}
	
	// Purge the entire minify cache
	if ( function_exists( 'w3tc_minify_flush' ) ) {
		w3tc_minify_flush();
	}	
	
	// Purge the entire page cache
	if ( function_exists( 'w3tc_pgcache_flush' ) ) {
		w3tc_pgcache_flush();
	}
	
}

/**
 * Runs all flushes
 *
 * @param array $nav_menu_selected_id, the nav menu id's
 *
 * @since 1.1.0
 */
function wap_w3tc_flush_menu( $nav_menu_selected_id = array() ) {
	
	// Purge the entire db cache
	if ( function_exists( 'w3tc_dbcache_flush' ) ) {
		w3tc_dbcache_flush();
	}
	
	// Purge the entire object cache
	if ( function_exists( 'w3tc_objectcache_flush' ) ) {
		w3tc_objectcache_flush();
	}
		
	// Purge the entire page cache
	if ( function_exists( 'w3tc_pgcache_flush' ) ) {
		w3tc_pgcache_flush();
	}
	
}

/**
 * Runs all flushes
 *
 * @param array $instance		returns values for the widget, required to update widget
 * @param array $new_instance
 * @param array $old_instance
 * @param array $this
 *
 * @since 1.0.0
 */
function wap_w3tc_flush_all_widget($instance, $new_instance, $old_instance, $this) {
	
	// Purge the entire db cache
	if ( function_exists( 'w3tc_dbcache_flush' ) ) {
		w3tc_dbcache_flush();
	}
	
	// Purge the entire object cache
	if ( function_exists( 'w3tc_objectcache_flush' ) ) {
		w3tc_objectcache_flush();
	}
	
	// Purge the entire minify cache
	if ( function_exists( 'w3tc_minify_flush' ) ) {
		w3tc_minify_flush();
	}	
	
	// Purge the entire page cache
	if ( function_exists( 'w3tc_pgcache_flush' ) ) {
		w3tc_pgcache_flush();
	}
	
	//* Pass $instance to parse updating
	return $instance;
}

/**
 * Runs single post flush
 *
 * @unused
 *
 * @since 1.0.2
 */
function wap_w3tc_flush_single_post() {
	
	// Purge the single page cache
	if ( function_exists( 'w3tc_pgcache_flush_post' ) ) {
		global $post;
		$post_id = $post->ID;
			
		w3tc_pgcache_flush_post($post_id);
	}
	
}

/**
 * Flushes entire object cache
 *
 * @unused
 *
 * @since 1.0.0
 */
function wap_w3tc_flush_object() {
			
	// Purge the entire object cache
	if ( function_exists( 'w3tc_objectcache_flush' ) ) {
		w3tc_objectcache_flush();
	}
	
}

/**
 * Removes the Performance admin bar
 *
 * @param array $wp_admin_bar 	the admin bar id's or names
 *
 * @since 1.0.0
 */
function wap_w3tc_remove_adminbar() {
	global $wp_admin_bar;
	
	// Remove admin menu
	if ( !is_super_admin() ) {
		$wp_admin_bar->remove_menu('w3tc');
		$wp_admin_bar->remove_node('w3tc');
	}	
}

/**
 * Removes the popup admin script for non-super-admins or non-admins (single)
 *
 * @since 1.0.4
 */
function wap_w3tc_remove_script() {
	if ( !is_super_admin() ) {
		
		if ( function_exists( 'w3_instance' ) ) {
			$w3_plugin = w3_instance('W3_Plugin_TotalCache');
		}
		
		// Remove popupadmin script
		remove_action( 'wp_print_scripts', array( 
			$w3_plugin,
			'popup_script'
			), 10);
	}
}

/**
 * Removes the Performance admin menu
 *
 * @param array $submenu
 * @param array $menu
 *
 * @since 1.0.0
 */
function wap_w3tc_remove_adminmenu() {
	global $submenu,$menu;
	
	if ( ! is_super_admin() ) {
		
		if ( ! empty($menu) ) {
			foreach($menu as $key => $submenuitem) {
			if( __($submenuitem[0]) == __('Performance') || $submenuitem[2] == "w3tc_dashboard") {
				unset($menu[$key]);
				unset( $submenu[ 'w3tc_dashboard' ] );
				break;
				}
			}
		}
		
		//* Adds redirect to dashboard home with error if query arg contains w3tc_
		if (stripos($_SERVER['REQUEST_URI'],'admin.php?page=w3tc_') !== false) {
			wp_redirect( get_option('siteurl') . '/wp-admin/index.php?w3tc_permission_denied=true');
		}
		
	}
	
}

/**
 * Adds a notice after the redirect takes place
 *
 * @since 1.1.0
 */
function wap_die_notice() {
	if(isset($_GET['w3tc_permission_denied']) && $_GET['w3tc_permission_denied']) {
		add_action('admin_notices', 'wap_no_permissions_admin_notice');
	}
}

/**
 * The redirect notice
 *
 * @since 1.1.0
 */
function wap_no_permissions_admin_notice() { // delete site notice
	echo "<div id='permissions-warning' class='error fade'><p><strong>".__("You do not have the right permissions to access this page.", 'WapPilot' )."</strong></p></div>";
}

/**
 * Removes the post_row_actions and post_submitbox_start "Purge from cache" links
 *
 * @uses wap_w3tc_remove_row
 *
 * @since 1.0.0
 */
function wap_w3tc_remove_flush_per_post_page() {
	if ( !is_super_admin() ) {
		
		if ( function_exists( 'w3_instance' ) )
			$w3_actions = w3_instance('W3_GeneralActions');
		
		// Within /wp-admin/edit.php
		add_filter('post_row_actions', 'wap_w3tc_remove_row');
		
		// Within /wp-admin/edit.php?post_type=page
		add_filter('page_row_actions', 'wap_w3tc_remove_row');
		
		// Within /wp-admin/post.php?post=xxxx&action=edit
		remove_action('post_submitbox_start', array(
			$w3_actions,
			'post_submitbox_start'
			), 10);
	}
}

/**
 * Removes the post_row_actions "Purge from cache" links
 *
 * @since 1.0.0
 */
function wap_w3tc_remove_row($actions) {
	unset( $actions['pgcache_purge'] );
	
	return $actions;
}

/**
 * Removes the w3tc_errors and notices from non-super-admins / non-admins (single)
 *
 * @since 1.0.1
 */
function wap_w3tc_remove_notices() {
	if ( !is_super_admin() ) {
		add_filter('w3tc_errors', '__return_false');
		add_filter('w3tc_notes', '__return_false');
	}
}