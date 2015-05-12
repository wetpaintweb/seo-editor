<?php

/**
 * @link              http://www.wetpaintwebtools.com/product/seo-editor/
 * @since             1.0.0
 * @package           SEO_Editor
 *
 * @wordpress-plugin
 * Plugin Name:       SEO Editor
 * Plugin URI:        http://www.wetpaintwebtools.com/product/seo-editor/
 * Description:       Edit SEO data in bulk to save time.
 * Version:           1.0.1
 * Author:            WetPaint
 * Author URI:        http://www.wetpaintwebtools.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       seo-editor
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The core plugin class that is used to define internationalization,
 * dashboard-specific hooks, and public-facing site hooks.
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-seo-editor.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_SEO_Editor() {

	$plugin = new SEO_Editor();
	$plugin->run();

}
add_action('plugins_loaded', 'run_SEO_Editor');
