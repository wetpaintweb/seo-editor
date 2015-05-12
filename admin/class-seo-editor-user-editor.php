<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( plugin_dir_path( dirname( __FILE__ ) ) . '/includes/class-wp-list-table.php' );
}

class SEO_Editor_User_Editor extends WP_List_Table {

	protected $hierarchical_display;

	function __construct( $content_type = 'users' ) {

		parent::__construct( array(
			'singular' => 'SEO Entry',
			'plural' => 'SEO Entries',
			'ajax' => true
		) );
	}

	/**
	 * Check the current user's permissions.
	 *
 	 * @since 1.0.0
	 * @access public
	 */
	public function ajax_user_can() {
		if ( $this->is_site_users ) {
			return current_user_can( 'manage_sites' );
		}
		else {
			return current_user_can( 'list_users' );
		}
	}

	/**
	 * Add table columns.
	 * This must be define in WP_LIST_TABLE extended class.
	 *
	 * @since    1.0.0
	 */
	function get_columns() {
		return $columns = array(
			'title' => __( 'Display Name' ),
			'meta' => __( 'Page Meta' ),
			'seo_notes' => __( 'SEO Notes' )
		);
	}

	/**
	 * Set which table columns user can sort by.
	 *
	 * @since    1.0.0
	 */
	function get_sortable_columns() {
		return $sortable = array(
			'title' => array( 'display_name', true )
		);
	}

	/**
	 * Generate the table navigation above or below the table
	 *
	 * @since 3.1.0
	 * @access public
	 */

	public function display_save_button() {

		echo '<a class="button-primary seom-save alignright" href="#save-changes" accesskey="s">' . __('Save Changes') . '</a>';

	}

	/**
	 * Create the view filter links to query post by status
	 *
	 * @since    1.0.0
	 * @access protected
	 */
	protected function get_views() {}

	/**
	 * Generate the table navigation above or below the table
	 *
	 * @since 3.1.0
	 * @access protected
	 */
	protected function display_tablenav( $which ) {

		echo '<div class="tablenav ' . esc_attr( $which ) . '">';

			if ( 'top' == $which ) {

				$this->views();

			}

			$this->pagination( $which );

			echo '<br class="clear" />';
		echo '</div>';
	}

	/**
	 * Collect data for the SEO Editor table.
	 *
	 * @since    1.0.0
	 */
	public function prepare_items() {
		global $wpdb, $_wp_column_headers;

		$screen = get_current_screen();

		$config = SEO_Editor_Admin::get_screen_config( $screen->id );

		// Query to collect post and SEO data
		$query = "SELECT
		ID,
		display_name as title,
		user_nicename as slug,
		a.meta_value AS seo_title,
		b.meta_value AS seo_desc,
		c.meta_value AS seo_notes,
		d.meta_value AS seo_modified
		FROM $wpdb->users
		LEFT JOIN (SELECT * FROM $wpdb->usermeta WHERE meta_key = '{$config['usertitle']}')a ON a.user_id = $wpdb->users.ID
		LEFT JOIN (SELECT * FROM $wpdb->usermeta WHERE meta_key = '{$config['userdesc']}')b ON b.user_id = $wpdb->users.ID
		LEFT JOIN (SELECT * FROM $wpdb->usermeta WHERE meta_key = '_seo_editor_notes')c ON c.user_id = $wpdb->users.ID
		LEFT JOIN (SELECT * FROM $wpdb->usermeta WHERE meta_key = '_yoast_wpseo_profile_updated')d ON d.user_id = $wpdb->users.ID";

		// Alter Order By
		$orderby = filter_input( INPUT_GET, 'orderby' );
		$orderby = ! empty( $orderby ) ? esc_sql( sanitize_text_field( $orderby ) ) : 'user_registered, display_name';
		$orderby = SEO_Editor_Admin::sanitize_orderby( $orderby );

		// Order clause
		$order = filter_input( INPUT_GET, 'order' );
		$order = ! empty( $order ) ? esc_sql( strtoupper( sanitize_text_field( $order ) ) ) : 'DESC';
		$order = SEO_Editor_Admin::sanitize_order( $order );

		$query .= ' ORDER BY '.$orderby.' '.$order;

		// Pagination
		$total_items = $wpdb->query( $query );

		$per_page = $this->get_items_per_page( 'edit_per_page' );

		$paged = isset( $_GET['paged'] ) ? esc_sql( $_GET['paged'] ) : '';

		if ( empty( $paged ) || ! is_numeric( $paged ) || $paged <= 0 ) {
			$paged = 1;
		}

		$total_pages = ceil( $total_items / $per_page );

		if ( !empty( $paged ) && !empty( $per_page ) ) {
			$offset = ($paged - 1) * $per_page;
			$query .= ' LIMIT ' . (int)$offset . ',' . (int)$per_page;
		}

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'total_pages' => $total_pages,
			'per_page' => $per_page
		) );

		// Set column headers
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		// Set the query results to the objects items
		$this->items = $wpdb->get_results( $query );
	}


	/**
	 * Display the table rows
	 *
	 * @since    1.0.0
	 */
	public function display_rows() {

		// Get the rows registered in the prepare_items method
		$rows = $this->items;

		if ( ! empty( $rows ) ) {

			$row_count = 0;

			// Loop through each row
			foreach ( $rows as $row ) {

				$row_count++;
				$row_class = "hentry row-$row_count";
				$row_class .= $row_count%2==0 ? ' alternate' : '';

				echo '<tr id="user-'.$row->ID.'" data-id="'.$row->ID.'" data-type="user" data-section="user" class="'.$row_class.'">';

				$this->single_row( $row );

				echo '</tr>';
			}
		}
	}

	/**
	 * Display Post SEO Data with inline edit fields in the admin settings page table.
	 * TODO: Add ability to hide/show columns in options tab?
	 *
	 * @since    1.0.0
	 */
	public function single_row( $row ) {

		// Get the columns registered in the get_columns and get_sortable_columns methods
		list( $columns, $hidden ) = $this->get_column_info();

		// Loop for each row's columns
		foreach ( $columns as $column_name => $column_display_name ) {

			$class = "class='$column_name'";
			$style = "";
			if ( in_array( $column_name, $hidden ) ) $style = ' style="display:none;"';
			$attributes = $class . $style;

			switch ( $column_name ) {

				case 'title': ?>

					<td <?php echo $attributes; ?>>

						<div class="row-title">

							<strong><a href="<?php echo get_edit_user_link( $row->ID, true ); ?>" title="<?php echo esc_attr( sprintf( __( 'View &#8220;%s&#8221;' ), $row->title ) ); ?>" rel="permalink"><?php echo esc_textarea( $row->title ); ?></a></strong>

						</div>

						<?php
						// Add the actions below the title
							$actions = array();
							$actions['edit'] = '<a href="' . get_edit_user_link( $row->ID, true ) . '" title="' . esc_attr( __( 'Edit this item' ) ) . '">' . __( 'Edit' ) . '</a>';
							$actions['view'] = '<a href="' . get_author_posts_url( $row->ID ) . '" title="' . esc_attr( sprintf( __( 'View &#8220;%s&#8221;' ), $row->title ) ) . '" rel="permalink">' . __( 'View' ) . '</a>';
							echo $this->row_actions( $actions );
						?>

					</td>

				<?php break;

				case 'meta': ?>

					<td <?php echo $attributes; ?>>

						<div id="seom-title-<?php echo $row->ID; ?>" name="seom-title-<?php echo $row->ID; ?>" class="seom-title editable" data-type="title" data-id="<?php echo $row->ID; ?>" data-placeholder="<?php _e( 'Enter a Page Title' ); ?>">
							<?php echo esc_textarea( $row->seo_title ); ?>
						</div>

						<div class="seom-path">
							<span id="seom-slug-<?php echo $row->ID; ?>" name="seom-slug-<?php echo $row->ID; ?>" type="text" class="seom-slug" data-type="slug"><?php echo str_replace(get_site_url(), '', get_author_posts_url( $row->ID )); ?></span>
						</div>

						<div id="seom-desc-<?php echo $row->ID; ?>" name="seom-desc-<?php echo $row->ID; ?>" class="seom-desc editable" data-type="desc" data-id="<?php echo $row->ID; ?>" data-placeholder="<?php _e( 'Enter a Meta Description' ); ?>">
							<?php echo esc_textarea( $row->seo_desc ); ?>
						</div>

						<span class="title-length" title="Calculating Title Length">?</span> / <span class="desc-length" title="Calculating Description Length">?</span>

					</td>

				<?php break;

				case 'seo_notes': ?>

					<td <?php echo $attributes; ?>>

						<div id="seom-notes-<?php echo $row->ID; ?>" name="seom-notes-<?php echo $row->ID; ?>" class="seom-notes editable" data-type="seo_notes" data-placeholder="<?php _e( 'Add a note or comment' ); ?>">
							<?php echo esc_textarea( $row->seo_notes ); ?>
						</div>

					</td>

				<?php break;
			}
		}
	}

	/**
	 * Get the parent page post_name to display in the URL slug
	 *
	 * @since    1.0.0
	 */
	protected function the_parent_slugs( $id ){

		$parents = array_reverse( get_ancestors( $id, 'post' ) );

		$slug_path = '';

		foreach ( $parents as $parent ) {

			$post = get_post( $parent );
			$slug_path .= trailingslashit( $post->post_name );
		}

		return $slug_path;
	}
}