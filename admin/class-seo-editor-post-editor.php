<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( plugin_dir_path( dirname( __FILE__ ) ) . '/includes/class-wp-list-table.php' );
}

class SEO_Editor_Post_Editor extends WP_List_Table {

	protected $hierarchical_display;

	function __construct( $content_type = 'page' ) {

		parent::__construct( array(
			'singular' => 'SEO Entry',
			'plural' => 'SEO Entries',
			'ajax' => true
		) );

		$this->screen->post_type = $content_type;
	}

	/**
	 * Check the current user's permissions.
	 *
 	 * @since 1.0.0
	 * @access public
	 */
	public function ajax_user_can() {
		return current_user_can( 'manage_sites' );
	}

	/**
	 * Add table columns.
	 * This must be define in WP_LIST_TABLE extended class.
	 *
	 * @since    1.0.0
	 */
	function get_columns() {
		return $columns = array(
			'title' => __( 'Title' ),
			'keyword' => __( 'Keyword' ),
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
			'title' => array( 'post_title', true ),
			'keyword' => array( 'seo_kw', true )
		);
	}

	/**
	 * Create the view filter links to query post by status
	 *
	 * @since    1.0.0
	 * @access protected
	 */
	protected function get_views() {
		$post_type = $this->screen->post_type;
		$avail_post_stati = get_available_post_statuses( $post_type );

		$status_links = array();
		$num_posts = wp_count_posts( $post_type, 'readable' );
		$class = '';

		$current_user_id = get_current_user_id();

		$total_posts = array_sum( (array) $num_posts );

		// Subtract post types that are not included in the admin all list.
		foreach ( get_post_stati( array( 'show_in_admin_all_list' => false ) ) as $state ) {
			$total_posts -= $num_posts->$state;
		}

		$class = empty( $class ) && empty( $_REQUEST['post_status'] ) && empty( $_REQUEST['show_sticky'] ) ? ' class="current"' : '';
		$status_links['all'] = '<a href="'. admin_url( 'admin.php?page=' . SEO_Editor_Admin::get_current_page() . "&amp;content_type=$post_type" ) . '"' . $class . '>' . sprintf( _nx( 'All <span class="count">(%s)</span>', 'All <span class="count">(%s)</span>', $total_posts, 'posts' ), number_format_i18n( $total_posts ) ) . '</a>';


		foreach ( get_post_stati( array( 'show_in_admin_status_list' => true ), 'objects' ) as $status ) {
			$class = '';

			$status_name = $status->name;

			if ( ! in_array( $status_name, $avail_post_stati ) ) {
				continue;
			}

			if ( empty( $num_posts->$status_name ) ) {
				continue;
			}

			if ( isset($_REQUEST['post_status']) && $status_name == $_REQUEST['post_status'] ) {
				$class = ' class="current"';
			}

			$status_links[$status_name] = '<a href="'. admin_url( 'admin.php?page=' . SEO_Editor_Admin::get_current_page() . "&amp;post_status=$status_name&amp;content_type=$post_type" ) . '"' . $class . '>' . sprintf( translate_nooped_plural( $status->label_count, $num_posts->$status_name ), number_format_i18n( $num_posts->$status_name ) ) . '</a>';
		}

		return $status_links;

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
	 * Generate the table navigation above or below the table
	 *
	 * @since 3.1.0
	 * @access protected
	 */
	protected function display_tablenav( $which ) {

		echo '<div class="tablenav ' . esc_attr( $which ) . '">';

			if ( 'top' == $which ){

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

		$this->hierarchical_display = false;

		// Collect the post types for query
		$post_type = esc_sql( $this->screen->post_type );

		// Collect all the post statuses that are editable by the admin for query
		if ( isset( $_REQUEST['post_status'] ) ) {
			$post_status = esc_sql( $_REQUEST['post_status'] );
			$post_status = "'$post_status'";
		}
		else {
			$post_statuses = get_post_stati( array( 'show_in_admin_all_list' => true ) );
			$post_status = "'" . implode( "', '", $post_statuses ) . "'";
		}

		// Query to collect post and SEO data
		$query = "SELECT
		ID,
		post_title as title,
		post_type as type,
		post_status as status,
		post_name as slug,
		post_modified_gmt as modified,
		a.meta_value AS seo_title,
		b.meta_value AS seo_desc,
		c.meta_value AS seo_kw,
		d.meta_value AS seo_notes,
		menu_order
		FROM $wpdb->posts
		LEFT JOIN (SELECT * FROM $wpdb->postmeta WHERE meta_key = '{$config['title']}')a ON a.post_id = $wpdb->posts.ID
		LEFT JOIN (SELECT * FROM $wpdb->postmeta WHERE meta_key = '{$config['metadesc']}')b ON b.post_id = $wpdb->posts.ID
		LEFT JOIN (SELECT * FROM $wpdb->postmeta WHERE meta_key = '{$config['focuskw']}')c ON c.post_id = $wpdb->posts.ID
		LEFT JOIN (SELECT * FROM $wpdb->postmeta WHERE meta_key = '_seo_editor_notes')d ON d.post_id = $wpdb->posts.ID
		WHERE post_type = '$post_type'
		AND post_status IN ($post_status)";

		// Order By
		$orderby = filter_input( INPUT_GET, 'orderby' );
		$orderby = ! empty( $orderby ) ? esc_sql( sanitize_text_field( $orderby ) ) : 'menu_order, post_title';
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

		if ( empty( $paged ) || !is_numeric( $paged ) || $paged <= 0 ) {
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

		// Collect the post types table row id
		$post_type = esc_attr( $this->screen->post_type );

		// Get the rows registered in the prepare_items method
		$rows = $this->items;

		if ( ! empty( $rows ) ) {

			$row_count = 0;

			// Loop through each row
			foreach ( $rows as $row ) {

				$row_count++;
				$row_class = "hentry row-$row_count";
				$row_class .= $row_count%2==0 ? ' alternate' : '';

				echo '<tr id="'.$post_type.'-'.$row->ID.'" data-id="'.$row->ID.'" data-type="'.$post_type.'" data-section="post" class="'.$row_class.'">';

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
			if ( in_array( $column_name, $hidden ) ) {
				$style = ' style="display:none;"';
			}
			$attributes = $class . $style;

			switch ( $column_name ) {

				case 'title': ?>

					<td <?php echo $attributes; ?>>

						<div class="row-title">

							<strong><a href="/?p=<?php echo $row->ID; ?>" title="<?php echo esc_attr( sprintf( __( 'View &#8220;%s&#8221;' ), $row->title ) ); ?>" rel="permalink"><?php echo esc_textarea( $row->title ); ?></a><?php _post_states( get_post( $row->ID ) ); ?></strong>

						</div>

						<div class="row-actions">

						<?php // Add the actions below the title
							$actions = array();
							$actions['edit'] = '<a href="' . get_edit_post_link( $row->ID, true ) . '" title="' . esc_attr__( 'Edit this item' ) . '">' . __( 'Edit' ) . '</a>';
							$actions['view'] = '<a href="/?p=' . $row->ID . '" title="' . esc_attr( sprintf( __( 'View &#8220;%s&#8221;' ), $row->title ) ) . '" rel="permalink">' . __( 'View' ) . '</a>';
							echo $this->row_actions( $actions );
						?>

						</div>

					</td>

				<?php break;

				case 'keyword': ?>

					<td <?php echo $attributes; ?>>

						<div id="seom-keyword-<?php echo $row->ID; ?>" name="seom-keyword-<?php echo $row->ID; ?>" class="seom-keyword editable" data-type="keyword" data-value="<?php echo esc_attr( $row->seo_kw ); ?>" data-placeholder="<?php _e( 'Enter a Target Keyword' ); ?>">
							<?php echo esc_textarea( $row->seo_kw ); ?>
						</div>

					</td>

				<?php break;

				case 'meta': ?>

					<td <?php echo $attributes; ?>>

						<div id="seom-title-<?php echo $row->ID; ?>" name="seom-title-<?php echo $row->ID; ?>" class="seom-title editable" data-type="title" data-value="<?php echo esc_attr( $row->seo_title ); ?>" data-placeholder="<?php _e( 'Enter a Page Title' ); ?>">
							<?php echo esc_textarea( $row->seo_title ); ?>
						</div>

						<div class="seom-path">

							<span class="parent-slug">/<?php _e( $this->the_parent_slugs( $row->ID ) ); ?></span><span id="seom-slug-<?php echo $row->ID; ?>" name="seom-slug-<?php echo $row->ID; ?>" type="text" class="seom-slug editable" data-type="slug" data-value="<?php echo esc_attr( $row->slug ); ?>"><?php echo esc_textarea( $row->slug ); ?></span>

						</div>

						<div id="seom-desc-<?php echo $row->ID; ?>" name="seom-desc-<?php echo $row->ID; ?>" class="seom-desc editable" data-type="desc" data-value="<?php echo esc_attr( $row->seo_desc ); ?>" data-placeholder="<?php _e( 'Enter a Meta Description' ); ?>">
							<?php echo esc_textarea( $row->seo_desc ); ?>
						</div>

						<span class="title-length" title="Calculating Title Length">?</span> / <span class="desc-length" title="Calculating Description Length">?</span>

					</td>

				<?php break;

				case 'seo_notes': ?>

					<td <?php echo $attributes; ?>>

						<div id="seom-notes-<?php echo $row->ID; ?>" name="seom-notes-<?php echo $row->ID; ?>" class="seom-notes editable" data-type="seo_notes" data-value="<?php echo esc_attr( $row->seo_notes ); ?>" data-placeholder="<?php _e( 'Add a note or comment' ); ?>">
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
	protected function the_parent_slugs( $id ) {

		$parents = array_reverse( get_ancestors( $id, 'post' ) );

		$slug_path = '';

		foreach ( $parents as $parent ) {

			$post = get_post($parent);
			$slug_path .= trailingslashit( $post->post_name );
		}

		return $slug_path;
	}
}