<?php
/**
 * The dashboard-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the dashboard-specific stylesheet and JavaScript.
 *
 * @package    SEO_Editor
 * @subpackage SEO_Editor/admin
 * @author     WetPaint
 */

class SEO_Editor_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $name    The ID of this plugin.
	 */
	private $name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * The SEO Editor Page
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $admin_pages    The SEO Editor hook
	 */
	private $admin_pages;

	/**
	 * The SEO Editor Page Screen
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $admin_screen    The Page Screen
	 */
	private $admin_screen;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @var      string    $name       The name of this plugin.
	 * @var      string    $version    The version of this plugin.
	 */
	public function __construct( $name, $version ) {

		$this->name = $name;
		$this->version = $version;
		$this->admin_pages = array();

	}

	/**
	 * Register the stylesheets for the Dashboard.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		if ( empty( $this->admin_pages ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( in_array( $screen->id, $this->admin_pages ) ) {
			wp_enqueue_style( $this->name, plugin_dir_url( __FILE__ ) . 'css/seo-editor-admin.css', array(), $this->version, 'all' );
			//wp_enqueue_style('thickbox'); // TODO: 1.x Content Preview feature.
		}
	}

	/**
	 * Register the JavaScript for the dashboard.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		if ( empty( $this->admin_pages ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( in_array( $screen->id, $this->admin_pages ) ) {
			// Load TinyMCE
			if ( ! class_exists('_WP_Editors' ) ) {
				require_once( ABSPATH . WPINC . '/class-wp-editor.php' );
			}
			$set = array(
				'teeny' => false,
				'tinymce' => true,
				'quicktags' => false
			);
			$set = _WP_Editors::parse_settings( $this->name, $set );
			_WP_Editors::editor_settings( $this->name, $set );

			//wp_enqueue_script( 'thickbox' ); // Modal Window // TODO: 1.x Content Preview feature.
			wp_enqueue_script( $this->name, plugin_dir_url( __FILE__ ) . 'js/seo-editor-admin.js', array( 'jquery' ), $this->version, FALSE );
			wp_localize_script( $this->name, 'seom_obj', array( 'seom_nonce' => wp_create_nonce( 'seom-nonce' ) ) );
		}
	}


	/**
	 * Add notice if users don't have an SEO plugin installed yet.
	 *
	 * @since    1.0.0
	 */
	public function activation_notice() {
		if ( ! is_plugin_active( 'wordpress-seo/wp-seo.php' ) && ! is_plugin_active( 'all-in-one-seo-pack/all_in_one_seo_pack.php' ) ) {
			$screen = get_current_screen();
			if ( $screen->id == 'plugins' ) {
				echo '<div class="error"><p>';
		        	echo _e( '<strong>SEO Editor</strong>: Please install one of the following SEO plugins to get started: <a href="/wp-admin/plugin-install.php?tab=search&s=wordpress+seo">WordPress SEO by Yoast</a> or <a href="/wp-admin/plugin-install.php?tab=search&s=all+in+one+seo+pack">All In One SEO Pack</a>.' );
				echo '</p></div>';
			}
		}
	}

	/**
	 * Add the SEO Editor dashboard to the SEO Menu.
	 *
	 * @since    1.0.0
	 */
	public function add_pages() {

		if ( is_plugin_active( 'wordpress-seo/wp-seo.php' ) ) {
			$admin_page = add_submenu_page(
				'wpseo_dashboard',
				'Bulk Editor',
				'Bulk Editor',
				'manage_options',
				$this->name.'_yoast_seo',
				array( $this, 'editor_page' )
			);

			// Remove existing WordPress SEO Bulk Editor
			remove_submenu_page(
				'wpseo_dashboard',
				'wpseo_bulk-editor'
			);

			if ( ! in_array( $admin_page, $this->admin_pages ) ) {
				$this->admin_pages[] = $admin_page;
			}

			add_action( "load-{$admin_page}", array( $this, 'create_options_help_screen' ) );
		}
	}

	/**
	 * Add the SEO Editor dashboard to the SEO Menu for All In oOne SEO
	 *
	 * @since    1.0.0
	 */
	public function aioseop_add_pages() {

		$admin_page = add_submenu_page (
			'all-in-one-seo-pack/aioseop_class.php',
			'Bulk Editor',
			'Bulk Editor',
			'manage_options',
			$this->name.'_aioseop',
			array( $this, 'editor_page' )
		);

		if ( ! in_array( $admin_page, $this->admin_pages ) ) {
			$this->admin_pages[] = $admin_page;
		}

		add_action( "load-{$admin_page}", array( $this, 'create_options_help_screen' ) );
	}

	/**
	 * Reorder the sub-menu under the WordPress SEO top menu item
	 *
	 * @since    1.0.0
	 */
	public function reorder_pages( $menu_order ) {
		global $submenu;

		// Reorder Yoast SEO menu items
		if ( isset( $submenu['wpseo_dashboard'] ) ) {
			$arr = array();

			// Version 2.0 of WordPress SEO moved the editor inside of the Tools submenu.
			if ( floatval( WPSEO_VERSION ) < 2 ) {
				$submenu_re_index = array( 0, 1, 2, 3, 4, 5, 6, 7, 9, 11, 10 );
			}
			elseif ( floatval( WPSEO_VERSION ) < 2.3 ) {
				$submenu_re_index = array( 0, 1, 2, 3, 4, 5, 7, 6 );
			}
			else {
				$submenu_re_index = array( 0, 1, 2, 3, 4, 5, 6, 8, 7 );
			}

			foreach ( $submenu_re_index as $re_index ) {
				$arr[] = $submenu['wpseo_dashboard'][$re_index];
			}

			$submenu['wpseo_dashboard'] = $arr;
		}
		return $menu_order;
	}

	/**
	 * Add Options and Help to the admin Page
	 *
	 * Example: http://www.generalthreat.com/2011/11/wordpress-help-panels-with-wp_screen/
	 *
	 * @since    1.0.0
	 */
	public function create_options_help_screen() {

		// Get current screen
		$this->admin_screen = get_current_screen();

		// Content specified inline
		$this->admin_screen->add_help_tab(
			array(
				'title'    => 'Getting Started',
				'id'       => 'getting_started_tab',
				'content'  => '<p>Built for search marketers, the SEO Editor is designed to edit SEO data in bulk across your entire site.</p><p>Keywords, Page Meta, and SEO notes are inline editable - so just click on the text you want to edit and begin typing!</p>',
				'callback' => false
			)
		);

		$this->admin_screen->set_help_sidebar(
			'<p>Need more info?</p><p>Visit us at <a href="http://www.wetpaintwebtools.com" target="_blank" title="Our Website">WetPaintWebTools.com</a></p>'
		);

		// Add Options
		$this->admin_screen->add_option(
			'per_page',
			array(
				'label' => 'Entries per page',
				'default' => 20,
				'option' => 'edit_per_page'
			)
		);
	}

	/**
	 * Add content_type filter dropdown to
	 * allow the user to select the content SEO they want to edit
	 * and the plugin to use the correct sub-class
	 *
	 * @since    1.0.0
	 */
	public function admin_page_content_filter() {
		global $content_type;

		$screen = get_current_screen();
		$config = SEO_Editor_Admin::get_screen_config( $screen->id );

		echo '<form id="seom-filter" method="get" action="">';

		echo '<input name="page" type="hidden" value="' . esc_attr( SEO_Editor_Admin::get_current_page() ) . '" />';

		echo '<div class="alignleft actions">';

		$options = '';

		// Posts
		$post_types = get_post_types( array( 'public' => true, 'exclude_from_search' => false ) );
		$options .= '<optgroup label="Post Types">';
		foreach ( $post_types as $post_type ) {
			$obj = get_post_type_object( $post_type );
			if ( $obj->labels->name != 'Media' ) {
				$options .= sprintf( '<option value="%2$s" %3$s>%1$s</option>', __( $obj->labels->name ), __( $post_type ), selected( $content_type, $post_type, false ) );
			}
		}
		$options .= '</optgroup>';

		if ($config['tax_support']) {
			// Taxonomy
			$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
			$options .= '<optgroup label="Taxonomy">';
			foreach ( $taxonomies as $taxonomy ) {
				if ($taxonomy->name != 'post_format') {
					$options .= sprintf( '<option value="%2$s" %3$s>%1$s</option>', __( $taxonomy->labels->name ), __( $taxonomy->name ), selected( $content_type, $taxonomy->name, false ) );
				}
			}
			$options .= '</optgroup>';
		}

		if ($config['user_support']) {
			$options .= '<optgroup label="Users">';
			$options .= '<option value="' . __('users') . '"' . selected( $content_type, 'users', false ) . '>' . __('All Users') . '</option>';
			$options .= '</optgroup>';
		}

		echo sprintf( '<select name="content_type">%1$s</select>' , $options );

		submit_button( __( 'Select' ), 'button', false, false, array( 'id' => 'post-query-submit' ) );

		echo '</div>';

		echo '</form>';
	}

	/**
	 * Add the SEO_Editor_Editor class and display function for the admin.
	 *
	 * @since    1.0.0
	 */
	public function editor_page() {
		global $content_type;

		// Get content type filter value
		$content_type = isset( $_REQUEST['content_type'] ) ? $_REQUEST['content_type'] : 'page';

		//Get all the available taxonomies to compare with the content_type
		$taxonomies = get_taxonomies( array( 'public' => true ) );

		//TODO: Break this up into user sub-groups? and make sure it matches what is set in the admin_page_content_filter options
		$user = array('users');

		// Check the content type and load the correct class to handle it
		if ( isset( $taxonomies ) && in_array( $content_type, $taxonomies ) ) {
			if ( !class_exists( 'SEO_Editor_Taxonomy_Editor' ) ) {
				require_once(  plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-seo-editor-taxonomy-editor.php' );
				$seo_editor_list_table = new SEO_Editor_Taxonomy_Editor( $content_type );
			}
		}
		elseif ( isset( $user ) && in_array( $content_type, $user ) ) {
			if ( !class_exists( 'SEO_Editor_User_Editor' ) ) {
				require_once(  plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-seo-editor-user-editor.php' );
				$seo_editor_list_table = new SEO_Editor_User_Editor( $content_type );
			}
		}
		else {
			if ( ! class_exists( 'SEO_Editor_Post_Editor' ) ) {
				require_once(  plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-seo-editor-post-editor.php' );
				$seo_editor_list_table = new SEO_Editor_Post_Editor( $content_type );
			}
		}

		if ( ! empty( $_REQUEST['_wp_http_referer'] ) ) {
			wp_redirect( remove_query_arg( array( '_wp_http_referer', '_wpnonce' ), stripslashes( $_SERVER['REQUEST_URI'] ) ) );
			exit;
		}

		$seo_editor_list_table->prepare_items();

	?>

		<div class="wrap seo_editor_table_page">
			<div id="icon-edit-pages" class="icon32 icon32-posts-page"></div>
			<h2>SEO Editor</h2>

			<?php if ( isset( $notice ) && $notice ) : ?>
			<div id="notice" class="error"><p id="has-newer-autosave"><?php echo $notice ?></p></div>
			<?php endif; ?>

			<?php // This is used to display messages from the ajax save call ?>
			<div id="message" class=""></div>

			<?php //TODO: Test heartbeat API to make sure there is a connection ?>
			<div id="lost-connection-notice" class="error hidden">
				<p><span class="spinner"></span> <?php _e( '<strong>Connection lost.</strong> Saving has been disabled until you&#8217;re reconnected.' ); ?>
				<span class="hide-if-no-sessionstorage"><?php _e( 'We&#8217;re backing up this post in your browser, just in case.' ); ?></span>
				</p>
			</div>

			<?php $this->admin_page_content_filter(); ?>

			<?php $this->export_button(); ?>

			<?php $seo_editor_list_table->display_save_button(); ?>

			<?php $seo_editor_list_table->display(); ?>

			<div id="ajax-response"></div>
			<br class="clear" />
		</div>

	<?php
	}

	/**
	 * Review the changes made to the SEO and changes to the correct function
	 *
	 * @since    1.0.0
	 */
	public function save_changes() {
		$config = SEO_Editor_Admin::get_screen_config( $_POST['adminpage'] );

		$response = array(); // Set empty array for response to be sent back to ajax call

		// Return if empty nonse failed
		if ( !isset( $_POST['seom_nonce'] ) || ! wp_verify_nonce( $_POST['seom_nonce'], 'seom-nonce' ) ) {
			$response = array(
				'status' => 0,
				'message' => 'Permissions Check Failed'
			);
			echo json_encode( $response ); // Error Message
			die();
		}

		// Return if empty SEO
		if ( empty( $_POST['seom_data'] ) ) {
			$response = array(
				'status' => 0,
				'message' => 'Nothing was saved, because there were no changes. Don\'t be shy!'
			);
			echo json_encode( $response ); // Error Message
			die();
		}


		$taxonomies_seo = false;
		$taxonomies_seo_editor = false;
		$taxonomies_slug = false;

		$seom_data = $_POST['seom_data'];
		foreach ( $seom_data as $key => $seo_changes ) {
			$status = false;

			$data = sanitize_text_field( $seo_changes['data'] );

			switch ( $seo_changes['section'] ) {

				// Save user content
				case 'user':
					switch ( $seo_changes['field'] ) {
						case 'title':
							$status = update_user_meta( $seo_changes['id'], $config['usertitle'], $data );
						break;
						case 'desc':
							$status = update_user_meta( $seo_changes['id'], $config['userdesc'], $data );
						break;
						case 'reviewed':
							$status = update_user_meta( $seo_changes['id'], '_seo_editor_reviewed', $data );
						break;
						case 'seo_notes':
							$status = update_user_meta( $seo_changes['id'], '_seo_editor_notes', $data );
						break;
					}
				break;

				// Save taxonomy content
				case 'taxonomy':
					if ( $taxonomies_seo === false ) {
						// Get all taxonomy terms SEO data - returns an unserialized array of all the taxonomies SEO
						$taxonomies_seo = get_option( 'wpseo_taxonomy_meta' );
						$taxonomies_seo_editor = get_option( 'seo_editor_taxonomy_meta' );
					}
					if ( ! isset( $taxonomies_seo[$seo_changes['type']] ) ) {
						$taxonomies_seo[$seo_changes['type']] = array();
					}
					if ( ! isset( $taxonomies_seo_editor[$seo_changes['type']] ) ) {
						$taxonomies_seo_editor[$seo_changes['type']] = array();
					}
					if ( ! isset($taxonomies_seo[$seo_changes['type']][$seo_changes['id']] ) ) {
						$taxonomies_seo[$seo_changes['type']][$seo_changes['id']] = array();
					}
					if ( ! isset($taxonomies_seo_editor[$seo_changes['type']][$seo_changes['id']] ) ) {
						$taxonomies_seo_editor[$seo_changes['type']][$seo_changes['id']] = array();
					}
					switch ( $seo_changes['field'] ) {
						case 'slug':
							wp_update_term( $seo_changes['id'], $seo_changes['type'], array( 'slug' => $data ) );
						break;
						case 'title':
							$taxonomies_seo[$seo_changes['type']][$seo_changes['id']][$config['taxtitle']] = $data;
						break;
						case 'desc':
							$taxonomies_seo[$seo_changes['type']][$seo_changes['id']][$config['taxdesc']] = $data;
						break;
						case 'reviewed':
							$taxonomies_seo_editor[$seo_changes['type']][$seo_changes['id']]['reviewed'] = $data;
						break;
						case 'seo_notes':
							$taxonomies_seo_editor[$seo_changes['type']][$seo_changes['id']]['notes'] = $data;
						break;
					}
					$status = true;
				break;

				// Save post content
				case 'post':
				default:
					switch ( $seo_changes['field'] ) {
						case 'slug':
							$status = wp_update_post( array (
									'ID'        => $seo_changes['id'],
									'post_name' => $data,
								));
						break;
						case 'keyword':
							$status = update_post_meta( $seo_changes['id'], $config['focuskw'], $data );
						break;
						case 'title':
							$status = update_post_meta( $seo_changes['id'], $config['title'], $data );
						break;
						case 'desc':
							$status = update_post_meta( $seo_changes['id'], $config['metadesc'], $data );
						break;
						case 'reviewed':
							$status = update_post_meta( $seo_changes['id'], '_seo_editor_reviewed', $data );
						break;
						case 'seo_notes':
							$status = update_post_meta( $seo_changes['id'], '_seo_editor_notes', $data );
						break;
					}

				break;
			}

			$seom_data[$key]['status'] = $status;
		}

		if ($taxonomies_seo !== false) {
			update_option( 'wpseo_taxonomy_meta', $taxonomies_seo );
		}
		if ($taxonomies_seo_editor !== false) {
			update_option( 'seo_editor_taxonomy_meta', $taxonomies_seo_editor );
		}

		$response = array(
			'status' => 1,
			'changes' => $seom_data
		);

		echo json_encode( $response );

		die();
	}

	/**
	 * Add .csv export button.
	 *
	 * @since    1.0.0
	 */
	public function export_button() {
		echo '<form id="export-seo-post" action="" method="get">';
		echo '<div class="alignleft actions">';
		echo '<input type="hidden" name="page" value="' . esc_attr( SEO_Editor_Admin::get_current_page() ) . '" />';
		echo '<input type="hidden" name="seo_editor_export" value="true" />';
		submit_button( __( 'Export SEO' ), 'button', false, false, array( 'id' => 'post-export', 'title' => 'Export All SEO into a .CSV' ) );
		echo '</div>';
		echo "</form>";
	}

	/**
	 * Export a .cvs of the content in the SEO Editor
	 * TODO: The export currently runs off a separate DB query then what is displayed in the SEO Editor's admin page.
	 * This is causing issue the the export showing content that may exist in the DB but is no longer accessible, example a custom content type.
	 *
	 * @since    1.0.0
	 */
	public function export() {

		if ( ! empty($_REQUEST['seo_editor_export']) ) {
			global $wpdb;

			$config = SEO_Editor_Admin::get_screen_config( SEO_Editor_Admin::get_current_page() );

			// Set up CSV file headers and columns
			header( 'Content-type: application/csv' );
			header( 'Content-Disposition: attachment; filename=seo-edtior-export.' . date( 'Y-m-d' ) . '.csv' );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );
			$out = fopen('php://output', 'w');
			fputcsv($out, array(
				'Title/Name',
				'Type',
				'Status',
				'URL',
				'Canonical Link',
				'Meta Keyword',
				'Meta Title',
				'Meta Description',
				'Comments/Notes'
			));

			/* Export Post SEO */
			// Get available post types for query
			$post_types = get_post_types( array( 'public' => true, 'exclude_from_search' => false ) );
			$post_types = "'" . implode( "', '", $post_types ) . "'";

			// Get available post statuses for query
			$post_statuses = get_post_stati( array('show_in_admin_all_list' => true) );
			$post_status = "'" . implode( "', '", $post_statuses ) . "'";

			// Query Posts
			$post_query = "SELECT
			ID,
			post_title as title,
			post_type as type,
			post_status as status,
			a.meta_value AS seo_title,
			b.meta_value AS seo_desc,
			c.meta_value AS seo_kw,
			d.meta_value AS seo_canonical,
			e.meta_value AS seo_notes
			FROM $wpdb->posts
			LEFT JOIN (SELECT * FROM $wpdb->postmeta WHERE meta_key = '{$config['title']}')a ON a.post_id = $wpdb->posts.ID
			LEFT JOIN (SELECT * FROM $wpdb->postmeta WHERE meta_key = '{$config['metadesc']}')b ON b.post_id = $wpdb->posts.ID
			LEFT JOIN (SELECT * FROM $wpdb->postmeta WHERE meta_key = '{$config['focuskw']}')c ON c.post_id = $wpdb->posts.ID
			LEFT JOIN (SELECT * FROM $wpdb->postmeta WHERE meta_key = '{$config['canonical']}')d ON d.post_id = $wpdb->posts.ID
			LEFT JOIN (SELECT * FROM $wpdb->postmeta WHERE meta_key = '_seo_editor_notes')e ON e.post_id = $wpdb->posts.ID
			WHERE post_type IN ($post_types)
			AND post_status IN ($post_status)";

			$post_items = $wpdb->get_results( $post_query );
			foreach ( $post_items as $item ) {

				fputcsv($out, array(
					$item->title, //~ 'Name',
					get_post_type_object( $item->type )->labels->singular_name, //~ 'Type',
					get_post_status_object( $item->status )->label, //~ 'Post Status',
					get_permalink( $item->ID ), //~ 'URL',
					$item->seo_canonical, //~ 'Canonical Link'
					$item->seo_kw, //~ 'Focus Keyword',
					$item->seo_title, //~ 'Title',
					$item->seo_desc, //~ 'Meta Description'
					$item->seo_notes //~ 'Comments'
				));
			}

			if ( $config['user_support'] ) {
				/* Export User SEO */
				$user_query = "SELECT
				ID,
				display_name as title,
				a.meta_value AS seo_title,
				b.meta_value AS seo_desc,
				c.meta_value AS seo_notes,
				d.meta_value AS seo_modified
				FROM $wpdb->users
				LEFT JOIN (SELECT * FROM $wpdb->usermeta WHERE meta_key = '{$config['usertitle']}')a ON a.user_id = $wpdb->users.ID
				LEFT JOIN (SELECT * FROM $wpdb->usermeta WHERE meta_key = '{$config['userdesc']}')b ON b.user_id = $wpdb->users.ID
				LEFT JOIN (SELECT * FROM $wpdb->usermeta WHERE meta_key = '_seo_editor_notes')c ON c.user_id = $wpdb->users.ID
				LEFT JOIN (SELECT * FROM $wpdb->usermeta WHERE meta_key = '_yoast_wpseo_profile_updated')d ON d.user_id = $wpdb->users.ID";

				$user_items = $wpdb->get_results( $user_query );

				foreach ( $user_items as $item ) {
					$user_data = get_userdata( $item->ID );
					fputcsv($out, array(
						$item->title, //~ 'Name',
						'User', //~ 'Type',
						implode( ", ", $user_data->roles ), //~ 'Post Status',
						get_author_posts_url( $item->ID ), //~ 'URL',
						'n/a for this content', //~ 'Canonical Link' not available
						'n/a for this content', //~ 'Focus Keyword', not available
						$item->seo_title, //~ 'Title',
						$item->seo_desc, //~ 'Meta Description'
						$item->seo_notes //~ 'Comments'
					));
				}
			}

			if ($config['tax_support']) {
				// Query to collect taxonomy and SEO data
				$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );

				// Get all taxonomy terms SEO data - returns an unserialized array of all the taxonomies SEO
				$taxonomies_seo = get_option('wpseo_taxonomy_meta');
				$taxonomies_seo_editor = get_option('seo_editor_taxonomy_meta');

				foreach ( $taxonomies as $taxonomy ) {

					// Get all taxonomy terms
					$tax_terms = get_terms( $taxonomy->name );

					foreach ($tax_terms as $term) {

						$tax_term_seo = $taxonomies_seo[$taxonomy->name][$term->term_id];

						$tax_term_seo_editor = $taxonomies_seo_editor[$taxonomy->name][$term->term_id];

						fputcsv($out, array(
							$term->name, //~ 'Name',
							$taxonomy->labels->name, //~ 'Type',
							'', //~ 'Status',
							get_term_link( $term ), //~ 'URL',
							$tax_term_seo['wpseo_canonical'], //~ 'Canonical Link' not available
							'n/a for this content', //~ 'Focus Keyword', not available
							$tax_term_seo['wpseo_title'], //~ 'Title',
							$tax_term_seo['wpseo_desc'], //~ 'Meta Description'
							$tax_term_seo_editor['notes'] //~ 'Comments'
						));
					}
				}
			}

			exit();
		}
	}


	/**
	 * Get screen config based on which admin screen we are on
	 *
	 * @since    1.0.0
	 *
	 * @param $screen
	 *
	 * @return array
	 */
	public static function get_screen_config( $screen ) {
		// Determine which admin page we are running:
		if ( strpos( $screen, 'yoast_seo' ) !== false ) {
			// Yoast WordPress SEO
			return array(
				'title' => '_yoast_wpseo_title',
				'metadesc' => '_yoast_wpseo_metadesc',
				'focuskw' => '_yoast_wpseo_focuskw',
				'canonical' => '_yoast_wpseo_canonical',
				'user_support' => true,
				'usertitle' => 'wpseo_title',
				'userdesc' => 'wpseo_desc',
				'tax_support' => true,
				'taxtitle' => 'wpseo_title',
				'taxdesc' => 'wpseo_desc'
			);
		}
		elseif ( strpos( $screen, 'aioseop' ) !== false ) {
			// All In One SEO Pack
			return array(
				'title' => '_aioseop_title',
				'metadesc' => '_aioseop_description',
				'focuskw' => '_aioseop_keywords',
				'canonical' => '_aioseop_custom_link',
				'user_support' => false,
				'tax_support' => false
			);
		}
	}

	/**
	 * Get current page striping out any invalid characters
	 *
	 * @since    1.0.0
	 *
	 * @return string
	 */
	public static function get_current_page() {
		return preg_replace( '/[^a-z0-9-_]/', '', $_GET['page'] );
	}


	/**
	 * Heavily restricts the possible columns by which a user can order the table in the bulk editor, thereby preventing a possible CSRF vulnerability.
	 * Taken from wordpress seo class-bulk-editor-list-table.php
	 * https://github.com/Yoast/wordpress-seo/blob/master/admin/class-bulk-editor-list-table.php
	 *
	 * @since    1.0.0
	 *
	 * @param $orderby
	 *
	 * @return string
	 */
	public static function sanitize_orderby( $orderby ) {
		$valid_column_names = array(
			'post_title',
			'seo_kw',
			'display_name'
		);

		if ( in_array( $orderby, $valid_column_names ) ) {
			return $orderby;
		}

		return 'post_title';
	}

	/**
	 * Makes sure the order clause is always ASC or DESC for the bulk editor table, thereby preventing a possible CSRF vulnerability
	 * Taken from wordpress seo class-bulk-editor-list-table.php
	 * https://github.com/Yoast/wordpress-seo/blob/master/admin/class-bulk-editor-list-table.php
	 *
	 * @since    1.0.0
	 *
	 * @param $order
	 *
	 * @return string
	 */
	public static function sanitize_order( $order ) {
		if ( in_array( strtoupper( $order ), array( 'ASC', 'DESC' ) ) ) {
			return $order;
		}

		return 'ASC';
	}

}