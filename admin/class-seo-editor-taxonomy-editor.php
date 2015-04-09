<?php
/**
 * Class for displaying a list of taxonomy in an ajaxified HTML table.
 *
 * @since 1.0
 *
 * @package SEO_Editor
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( plugin_dir_path( dirname( __FILE__ ) ) . '/includes/class-wp-list-table.php' );
}

class SEO_Editor_Taxonomy_Editor extends WP_List_Table {

	protected $hierarchical_display;

	function __construct( $content_type = 'taxonomy' ) {

		parent::__construct( array(
			'singular' => 'SEO Entry',
			'plural' => 'SEO Entries',
			'ajax' => true
		) );

		$this->screen->taxonomy = $content_type;
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
			'title' => array( 'post_title', true )
		);
	}

	/**
	 * Generate the table navigation above or below the table
	 *
	 * @since 3.1.0
	 * @access protected
	 */

	protected function display_save_button() {

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

		$taxonomy = $this->screen->taxonomy;

		// Get all taxonomy terms
		$tax_terms = get_terms( $taxonomy, array( 'hide_empty' => false ) );

		// Get all taxonomy terms SEO data - returns an unserialized array of all the taxonomies SEO
		$taxonomies_seo = get_option( 'wpseo_taxonomy_meta' );
		$taxonomies_SEO_Editor = get_option( 'seo_editor_taxonomy_meta' );

		// Loop through taxonomy and add to taxonomy SEO
		foreach ( $tax_terms as $tax_term ) {
			// Init the values so they arn't missing when being used
			$tax_term->wpseo_title = '';
			$tax_term->wpseo_desc = '';
			$tax_term->reviewed = '';
			$tax_term->seo_notes = '';

			// Check if there is any SEO for the term in the array from WordPress SEO
			if ( isset( $taxonomies_seo[$tax_term->taxonomy] ) && isset( $taxonomies_seo[$tax_term->taxonomy][$tax_term->term_id] ) ) {

				// Collect the SEO for this term from the array from WordPress SEO
				$tax_term_seo = $taxonomies_seo[$tax_term->taxonomy][$tax_term->term_id];

				// If the title is set for the term add it to the object
				if ( isset( $tax_term_seo['wpseo_title'] ) ) {
					$tax_term->wpseo_title = $tax_term_seo['wpseo_title'];
				}

				// If the description is set for the term add it to the object
				if ( isset( $tax_term_seo['wpseo_desc'] ) ) {
					$tax_term->wpseo_desc = $tax_term_seo['wpseo_desc'];
				}
			}

			// Check if there is any SEO for the term in the array from SEO Editor
			if ( isset( $taxonomies_seo_editor[$tax_term->taxonomy] ) && isset( $taxonomies_seo_editor[$tax_term->taxonomy][$tax_term->term_id] ) ) {

				// Collect the SEO for this term from the array from WordPress SEO
				$tax_term_seo = $taxonomies_seo_editor[$tax_term->taxonomy][$tax_term->term_id];

				// If the reviewed is set for the term add it to the object
				if ( isset( $tax_term_seo['reviewed'] ) ) {
					$tax_term->reviewed = $tax_term_seo['reviewed'];
				}

				// If the notes is set for the term add it to the object
				if ( isset( $tax_term_seo['notes'] ) ) {
					$tax_term->seo_notes = $tax_term_seo['notes'];
				}
			}
		}

		$this->hierarchical_display = false; //TODO: 1.x use this to help display the taxonomy in a hierarchy

		// Pagination
		$total_items = count( $tax_terms );

		$per_page = $this->get_items_per_page( 'edit_per_page' );

		$paged = isset( $_GET['paged'] ) ? esc_sql( $_GET['paged'] ) : '';

		if ( empty( $paged ) || !is_numeric( $paged ) || $paged <= 0 ) {
			$paged = 1;
		}

		$total_pages = ceil( $total_items / $per_page );

		if ( !empty( $paged ) && !empty( $per_page ) ) {
			$offset = ($paged - 1) * $per_page;
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
		$this->items = $tax_terms;
	}


	/**
	 * Display the table rows
	 *
	 * @since    1.0.0
	 */
	public function display_rows() {

		// Collect the post types table row id
		$taxonomy = esc_attr( $this->screen->taxonomy );

		// Get the rows registered in the prepare_items method
		$rows = $this->items;

		if ( ! empty( $rows ) ) {

			$row_count = 0;

			// Loop through each row
			foreach ( $rows as $row ) {

				$row_count++;
				$row_class = "hentry row-$row_count";
				$row_class .= $row_count % 2 === 0 ? ' alternate' : '';

				echo '<tr id="'.$taxonomy.'-'.$row->term_id.'" data-id="'.$row->term_id.'" data-type="'.$taxonomy.'" data-section="taxonomy" class="'.$row_class.'">';

				$this->single_row( $row );

				echo '</tr>';
			}
		}
	}

	/**
	 * Display Post SEO Data with inline edit fields in the admin settings page table.
	 *
	 * @since    1.0.0
	 */
	public function single_row( $row ) {

		// Get the view link for the term
		$term_link = get_term_link( $row );

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

							<strong><a href="<?php echo esc_url( $term_link ); ?>" title="<?php echo esc_attr( sprintf( __( 'View &#8220;%s&#8221;' ), $row->name ) ); ?>" rel="permalink"><?php echo esc_textarea( $row->name ); ?></a></strong>

						</div>

						<div class="row-actions">

						<?php // Add the actions below the title
							$actions = array();
							$actions['edit'] = '<a href="' . esc_url( get_edit_term_link( $row->term_id, $this->screen->taxonomy ) ) . '" title="' . esc_attr( __( 'Edit this item' ) ) . '">' . __( 'Edit' ) . '</a>';
							$actions['view'] = '<a href="' . esc_url( $term_link ) . '" title="' . esc_attr( sprintf( __( 'View &#8220;%s&#8221;' ), $row->name ) ) . '" rel="permalink">' . __( 'View' ) . '</a>';
							echo $this->row_actions( $actions );
						?>

						</div>

					</td>

				<?php break;

				case 'keyword': ?>

					<td <?php echo $attributes; ?>>

						<div id="seom-keyword-<?php echo $row->term_id; ?>" name="seom-keyword-<?php echo $row->term_id; ?>" class="seom-keyword editable" data-type="keyword" data-value="<?php echo esc_attr( $row->seo_kw ); ?>" data-placeholder="<?php _e( 'Enter a Target Keyword' ); ?>">
							<?php echo esc_textarea( $row->seo_kw ); ?>
						</div>

					</td>

				<?php break;

				case 'meta': ?>

					<td <?php echo $attributes; ?>>

						<div id="seom-title-<?php echo $row->term_id; ?>" name="seom-title-<?php echo $row->term_id; ?>" class="seom-title editable" data-type="title" data-value="<?php echo esc_attr( $row->wpseo_title ); ?>" data-placeholder="<?php _e( 'Enter a Page Title' ); ?>">
							<?php echo esc_textarea( $row->wpseo_title ); ?>
						</div>

						<div class="seom-path">

							<?php $term_link = str_replace( $row->slug, '', untrailingslashit( $term_link ) ); ?>

							<span class="parent-slug"><?php echo $term_link; ?></span><span id="seom-slug-<?php echo $row->term_id; ?>" name="seom-slug-<?php echo $row->term_id; ?>" type="text" class="seom-slug editable" data-type="slug" data-value="<?php echo esc_attr( $row->slug ); ?>"><?php echo esc_textarea( $row->slug ); ?></span>

						</div>

						<div id="seom-desc-<?php echo $row->term_id; ?>" name="seom-desc-<?php echo $row->term_id; ?>" class="seom-desc editable" data-type="desc" data-value="<?php echo esc_attr( $row->wpseo_desc ); ?>" data-placeholder="<?php _e( 'Enter a Meta Description' ); ?>">
							<?php echo esc_textarea( $row->wpseo_desc ); ?>
						</div>

						<span class="title-length" title="Calculating Title Length">?</span> / <span class="desc-length" title="Calculating Description Length">?</span>

					</td>

				<?php break;

				case 'seo_notes': ?>

					<td <?php echo $attributes; ?>>

						<div id="seom-notes-<?php echo $row->term_id; ?>" name="seom-notes-<?php echo $row->term_id; ?>" class="seom-notes editable" data-type="seo_notes" data-value="<?php echo esc_attr( $row->seo_notes ); ?>" data-placeholder="<?php _e( 'Add a note or comment' ); ?>">
							<?php echo esc_textarea( $row->seo_notes ); ?>
						</div>

					</td>

				<?php break;
			}
		}
	}
}