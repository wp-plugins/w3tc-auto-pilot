<?php
/**
 * Plugin Name: W3TC Auto Pilot
 * Plugin URI: https://wordpress.org/plugins/w3tc-auto-pilot/
 * Description: Put W3 Total Cache on auto pilot. This plugin allows you to control W3 Total Cache in such a manner that no one knows you're using it, not even your admins. Either network activate it or activate it per site.
 * Version: 1.0.4
 * Author: Sybre Waaijer
 * Author URI: https://cyberwire.nl/
 * License: GPLv2 or later
 */

/* Developer Notes */

/**
 * == Hook reference list ==
 * 
 * after_switch_theme				=> After theme has switched 										=> Action
 * customize_save_after 			=> After customizer settings have been saved 						=> Action
 *
 * widget_update_callback 			=> After widget has been updated or removed							=> Filter		=> Needs to return $instance
 * w3tc_can_print_comment			=> W3TC HTML comment information about the plugin's cache control	=> Filter
 * page_row_actions					=> Same as post_row_actions, shows below each page on post.php		=> Filter
 *  
 * post_submitbox_start				=> The submitbox of a post/page										=> Action
 * after_setup_theme				=> Very early call													=> Action
 */

//* # Initialize this plugin. Uncomment any action you don't wish to use.
function wap_w3tc_init() {
	
	//* Adds advanced flushing on update of certain items (especially related to object cache)
	//* Usage of each action hook is documented above under Developer Notes
	add_action( 'after_switch_theme', 'wap_w3tc_flush_all' );
	add_action( 'customize_save_after', 'wap_w3tc_flush_all' );
	add_filter( 'widget_update_callback', 'wap_w3tc_flush_all_widget', 11, 4 ); // Will not always fire, but does the job :)
	
	//* Removes admin bar entry of W3 Total Cache
	add_action( 'admin_bar_menu', 'wap_w3tc_remove_adminbar', 20 );
	
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
	add_action( 'admin_init', 'wap_mapped_clear', 20);
}
add_action( 'after_setup_theme', 'wap_w3tc_init' ); // Call very early, before init and admin_init

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
		if ( !empty($ismapped) ) {
//			add_action( 'save_post', 'wap_w3tc_flush_single_post' ); // Doesn't work unfortunately with Domain Mapping... somehow 2 ID's are being created?
			add_action( 'save_post', 'wap_w3tc_flush_page' ); // So we just flush it entirely. But only if the domain is mapped! :D
		}
	}
}
	
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

function wap_w3tc_flush_single_post() {
	
	// Purge the single page cache
	if ( function_exists( 'w3tc_pgcache_flush_post' ) ) {
		global $post;
		$post_id = $post->ID;
			
		w3tc_pgcache_flush_post($post_id);
	}
	
}

function wap_w3tc_flush_page() {
	
	// Purge the entire page cache
	if ( function_exists( 'w3tc_pgcache_flush' ) ) {
		w3tc_pgcache_flush();
	}
	
}

function wap_w3tc_flush_object() {
			
	// Purge the entire object cache
	if ( function_exists( 'w3tc_objectcache_flush' ) ) {
		w3tc_objectcache_flush();
	}
	
}

function wap_w3tc_remove_adminbar() {
	global $wp_admin_bar;
	
	// Remove admin menu
	if ( !is_super_admin() ) {
		$wp_admin_bar->remove_menu('w3tc');
	}	
}

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

function wap_w3tc_remove_script_ext() {
	if ( function_exists( 'w3_instance' ) ) {
		$w3_plugin = w3_instance('W3_Plugin_TotalCache');
	}
	
	remove_action( 'wp_print_scripts', array( $w3_plugin, 'popup_script' ), 10);	
}

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
	}
	
	//* TODO: Add wp_die when a user tries to access the pages
}

function wap_w3tc_remove_flush_per_post_page() {
	if ( !is_super_admin() ) {
		
		if ( function_exists( 'w3_instance' ) ) {
			$w3_actions = w3_instance('W3_GeneralActions');
		}
		
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

function wap_w3tc_remove_row($actions) {
	unset( $actions['pgcache_purge'] );
	
	return $actions;
}

function wap_w3tc_remove_notices() {
	if ( !is_super_admin() ) {
		add_filter('w3tc_errors', '__return_false');
		add_filter('w3tc_notes', '__return_false');
	}
}