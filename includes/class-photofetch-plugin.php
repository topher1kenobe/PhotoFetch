<?php
/**
 * Core plugin bootstrap class.
 *
 * @package PhotoFetch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core plugin bootstrap. Kept as a single class so hook registration is easy
 * to audit in one place; the actual remote-API and import logic live in
 * class-photofetch-api.php and class-photofetch-importer.php.
 */
class PhotoFetch_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var PhotoFetch_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get (and lazily create) the singleton instance.
	 *
	 * @return PhotoFetch_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Registers all plugin hooks. Private: use instance() instead of `new`.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'register_assets' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue_admin_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
		add_action( 'media_buttons', array( $this, 'media_button' ), 20 );

		add_action( 'admin_menu', array( 'PhotoFetch_Settings', 'register_page' ) );
		add_action( 'admin_init', array( 'PhotoFetch_Settings', 'register_setting' ) );

		add_filter( 'all_plugins', array( $this, 'link_authors_to_profiles' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PHOTOFETCH_PLUGIN_FILE ), array( $this, 'add_settings_link' ) );

		add_action( 'wp_ajax_photofetch_search', array( 'PhotoFetch_API', 'ajax_search' ) );
		add_action( 'wp_ajax_photofetch_terms', array( 'PhotoFetch_API', 'ajax_terms' ) );
		add_action( 'wp_ajax_photofetch_import', array( 'PhotoFetch_Importer', 'ajax_import' ) );
	}

	/**
	 * The Plugins list table wraps the whole "Author" string in a single
	 * link to Author URI — there's no per-name linking in the standard
	 * plugin header format. Since every name in ours is already a
	 * wordpress.org username (matching readme.txt's Contributors field),
	 * this rewrites our own row's Author field into one link per name,
	 * each pointing at that person's own wordpress.org profile, instead of
	 * the whole list pointing at one shared URL.
	 *
	 * `get_plugins()` (which feeds this filter) reads Author/AuthorURI as
	 * plain, unlinked strings, so `$plugins[...]['Author']` here is still
	 * just the raw comma-separated header value — nothing to unwrap first.
	 *
	 * @param array $plugins Plugin data keyed by plugin file, as returned by get_plugins().
	 * @return array
	 */
	public function link_authors_to_profiles( $plugins ) {
		$basename = plugin_basename( PHOTOFETCH_PLUGIN_FILE );

		if ( empty( $plugins[ $basename ]['Author'] ) ) {
			return $plugins;
		}

		$usernames = array_filter( array_map( 'trim', explode( ',', $plugins[ $basename ]['Author'] ) ) );

		$links = array_map(
			function ( $username ) {
				return sprintf(
					'<a href="%s">%s</a>',
					esc_url( 'https://profiles.wordpress.org/' . rawurlencode( $username ) ),
					esc_html( $username )
				);
			},
			$usernames
		);

		$plugins[ $basename ]['Author'] = implode( ', ', $links );
		// Emptied so the list table doesn't also wrap these already-linked
		// names in one more outer <a> pointing at Author URI.
		$plugins[ $basename ]['AuthorURI'] = '';

		return $plugins;
	}

	/**
	 * Adds a "Settings" link to this plugin's row on the Plugins page,
	 * appearing first among the action links (so immediately next to
	 * "Deactivate").
	 *
	 * @param string[] $links Existing action links (Activate/Deactivate, Edit, etc.).
	 * @return string[]
	 */
	public function add_settings_link( $links ) {
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'options-general.php?page=' . PhotoFetch_Settings::PAGE_SLUG ) ),
				esc_html__( 'Settings', 'photofetch' )
			)
		);

		return $links;
	}

	/**
	 * Register (but don't necessarily enqueue) scripts/styles so the same
	 * handles can be pulled in from several different admin contexts.
	 */
	public function register_assets() {
		wp_register_style( 'photofetch-admin', PHOTOFETCH_PLUGIN_URL . 'assets/css/admin.css', array(), PHOTOFETCH_VERSION );

		wp_register_style(
			'photofetch-photo-browser',
			PHOTOFETCH_PLUGIN_URL . 'assets/css/photo-browser.css',
			array( 'dashicons' ),
			PHOTOFETCH_VERSION
		);

		// Built against wp-element rather than a bundler: the plugin ships no
		// build step, and WordPress already serves React under this handle.
		wp_register_script(
			'photofetch-photo-browser',
			PHOTOFETCH_PLUGIN_URL . 'assets/js/photo-browser.js',
			array( 'wp-element', 'wp-i18n' ),
			PHOTOFETCH_VERSION,
			true
		);
		wp_set_script_translations( 'photofetch-photo-browser', 'photofetch', PHOTOFETCH_PLUGIN_DIR . 'languages' );

		wp_localize_script( 'photofetch-photo-browser', 'PhotoFetch_Browser', $this->browser_settings() );

		wp_register_script( 'photofetch-admin', PHOTOFETCH_PLUGIN_URL . 'assets/js/admin.js', array(), PHOTOFETCH_VERSION, true );

		wp_register_style(
			'photofetch-media-tab',
			PHOTOFETCH_PLUGIN_URL . 'assets/css/media-tab.css',
			array( 'dashicons' ),
			PHOTOFETCH_VERSION
		);

		wp_register_script(
			'photofetch-media-modal',
			PHOTOFETCH_PLUGIN_URL . 'assets/js/media-modal.js',
			array( 'media-views', 'wp-i18n' ),
			PHOTOFETCH_VERSION,
			true
		);
		wp_set_script_translations( 'photofetch-media-modal', 'photofetch', PHOTOFETCH_PLUGIN_DIR . 'languages' );

		wp_localize_script( 'photofetch-media-modal', 'PhotoFetch_Modal', $this->modal_settings() );

		wp_register_script( 'photofetch-settings', PHOTOFETCH_PLUGIN_URL . 'assets/js/settings.js', array(), PHOTOFETCH_VERSION, true );

		wp_localize_script(
			'photofetch-admin',
			'PhotoFetch_Settings',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'photofetch_nonce' ),
				'strings' => array(
					'search'        => __( 'Search photos…', 'photofetch' ),
					'import'        => __( 'Import', 'photofetch' ),
					'importing'     => __( 'Importing…', 'photofetch' ),
					'imported'      => __( 'Imported', 'photofetch' ),
					'selected'      => __( 'Selected', 'photofetch' ),
					'loadMore'      => __( 'Load more', 'photofetch' ),
					'noResults'     => __( 'No photos found.', 'photofetch' ),
					'error'         => __( 'Something went wrong. Please try again.', 'photofetch' ),
					'useFeatured'   => __( 'Use as featured image', 'photofetch' ),
					'viewInLibrary' => __( 'View in Media Library', 'photofetch' ),
					'close'         => __( 'Close', 'photofetch' ),
					'tabLabel'      => __( 'Photo Directory', 'photofetch' ),
				),
			)
		);
	}

	/**
	 * Data handed to the browse app. Strings are localized here rather than
	 * through wp-i18n so the plugin keeps working without shipping compiled
	 * translation files, matching how photofetch-admin already does it.
	 *
	 * @return array Settings consumed by assets/js/photo-browser.js.
	 */
	private function browser_settings() {
		/**
		 * Filters the URL of the "Import settings" link in the page header.
		 * Points at Settings > Photo Directory by default.
		 *
		 * @param string $url Settings panel URL.
		 */
		$settings_url = apply_filters(
			'photofetch_settings_url',
			admin_url( 'options-general.php?page=' . PhotoFetch_Settings::PAGE_SLUG )
		);

		return array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'photofetch_nonce' ),
			'libraryUrl'  => admin_url( 'upload.php' ),
			'settingsUrl' => esc_url_raw( $settings_url ),
			'perPage'     => 20,
			'sizes'       => array(
				array(
					'value' => 'full',
					'label' => __( 'Full size (up to 2560px)', 'photofetch' ),
				),
				array(
					'value' => 'large',
					'label' => __( 'Large (1024px)', 'photofetch' ),
				),
				array(
					'value' => 'medium',
					'label' => __( 'Medium (600px)', 'photofetch' ),
				),
			),
			'strings'     => array(
				'title'               => __( 'Photo Directory', 'photofetch' ),
				'description'         => __( 'Free, CC0-licensed photos contributed to WordPress.org. Import any photo straight into your Media Library. No attribution required, credit optional.', 'photofetch' ),
				'importSettings'      => __( 'Import settings', 'photofetch' ),
				'searchLabel'         => __( 'Search photos', 'photofetch' ),
				'searchPlaceholder'   => __( 'Search photos by subject, place or tag', 'photofetch' ),
				'search'              => __( 'Search', 'photofetch' ),
				'allCategories'       => __( 'All categories', 'photofetch' ),
				'anyOrientation'      => __( 'Any orientation', 'photofetch' ),
				'colorLabel'          => __( 'Color', 'photofetch' ),
				'anyColor'            => __( 'Any color', 'photofetch' ),
				/* translators: %s: colour name, e.g. "Green" */
				'colorSwatch'         => __( 'Filter by %s', 'photofetch' ),
				'sortLabel'           => __( 'Sort results', 'photofetch' ),
				'sortRelevance'       => __( 'Most relevant', 'photofetch' ),
				'sortNewest'          => __( 'Newest', 'photofetch' ),
				/* translators: %s: formatted number of photos */
				'photoCount'          => __( '%s photos', 'photofetch' ),
				/* translators: 1: formatted number of photos, 2: search term */
				'photoCountFor'       => __( '%1$s photos for “%2$s”', 'photofetch' ),
				'filtersLabel'        => __( 'Filters', 'photofetch' ),
				/* translators: %s: name of the filter being removed */
				'removeFilter'        => __( 'Remove filter: %s', 'photofetch' ),
				'clearAll'            => __( 'Clear all', 'photofetch' ),

				'recentlyAdded'       => __( 'Recently added', 'photofetch' ),
				/* translators: %s: search term */
				'resultsFor'          => __( 'Results for “%s”', 'photofetch' ),
				'inLibrary'           => __( 'In library', 'photofetch' ),
				'import'              => __( 'Import', 'photofetch' ),
				'importing'           => __( 'Importing…', 'photofetch' ),
				'viewInLibrary'       => __( 'View in library', 'photofetch' ),
				'viewFull'            => __( 'View full', 'photofetch' ),
				'close'               => __( 'Close', 'photofetch' ),
				'loadMore'            => __( 'Load more photos', 'photofetch' ),
				'loading'             => __( 'Loading…', 'photofetch' ),
				/* translators: 1: number of photos shown so far, 2: total number available */
				'showingCount'        => __( 'Showing %1$s of %2$s', 'photofetch' ),

				'trayLabel'           => __( 'Selected photos', 'photofetch' ),
				/* translators: %s: photo title */
				'selectPhoto'         => __( 'Select %s', 'photofetch' ),
				/* translators: %s: photo title */
				'deselectPhoto'       => __( 'Deselect %s', 'photofetch' ),
				'clearSelection'      => __( 'Clear selection', 'photofetch' ),
				'hintSelect'          => __( 'Click a photo to select it, or import one on its own', 'photofetch' ),
				'importSize'          => __( 'Import size', 'photofetch' ),
				'addCredit'           => __( 'Add photographer credit to caption', 'photofetch' ),
				'editDetails'         => __( 'Edit alt text & captions', 'photofetch' ),
				'hideDetails'         => __( 'Hide details', 'photofetch' ),
				'fieldTitle'          => __( 'Title', 'photofetch' ),
				'fieldAlt'            => __( 'Alt text', 'photofetch' ),
				'fieldAltPlaceholder' => __( 'describe the photo', 'photofetch' ),
				'fieldCaption'        => __( 'Caption', 'photofetch' ),
				/* translators: 1: current photo number, 2: total photos, 3: photo title */
				'importProgress'      => __( 'Importing %1$s of %2$s: %3$s', 'photofetch' ),
				'cancel'              => __( 'Cancel', 'photofetch' ),
				'viewInMediaLibrary'  => __( 'View in Media Library', 'photofetch' ),
				'importedBody'        => __( 'Alt text and captions were saved with each file.', 'photofetch' ),
				/* translators: 1: number imported, 2: number that failed */
				'importedPartial'     => __( '%1$s imported, %2$s could not be imported.', 'photofetch' ),
				'importedPartialBody' => __( 'The photos that failed are still selected, so you can try them again.', 'photofetch' ),

				'importFailed'        => __( 'That photo could not be imported.', 'photofetch' ),
				'alreadyImported'     => __( 'That photo is already in your Media Library.', 'photofetch' ),
				'alreadyImportedBody' => __( 'It was imported earlier, so nothing was downloaded again.', 'photofetch' ),

				/* translators: %s: search term */
				'emptyTitle'          => __( 'No photos match “%s”', 'photofetch' ),
				'emptyTitleFiltered'  => __( 'No photos match these filters', 'photofetch' ),
				'emptyBody'           => __( 'Narrow searches often come back empty. Try a broader term, or drop a filter.', 'photofetch' ),
				'errorTitle'          => __( 'Couldn’t reach WordPress.org.', 'photofetch' ),
				'tryAgain'            => __( 'Try again', 'photofetch' ),
				'error'               => __( 'The Photo Directory API didn’t respond. Your Media Library is unaffected.', 'photofetch' ),
			),
		);
	}

	/**
	 * Data handed to the "Photo Directory" tab inside the wp.media modal.
	 * Shares the AJAX endpoints and import sizes with the browse screen but
	 * carries its own strings, since the tab talks about inserting into a
	 * post rather than about the Media Library on its own.
	 *
	 * @return array Settings consumed by assets/js/media-modal.js.
	 */
	private function modal_settings() {
		$browser = $this->browser_settings();

		return array(
			'ajaxUrl' => $browser['ajaxUrl'],
			'nonce'   => $browser['nonce'],
			'sizes'   => $browser['sizes'],
			'strings' => array(
				'tabLabel'          => __( 'Photo Directory', 'photofetch' ),
				'searchPlaceholder' => __( 'Search photos', 'photofetch' ),
				'searchLabel'       => __( 'Search photos', 'photofetch' ),
				'allCategories'     => __( 'All categories', 'photofetch' ),
				'anyOrientation'    => __( 'Any orientation', 'photofetch' ),
				'sortLabel'         => __( 'Sort results', 'photofetch' ),
				'sortRelevance'     => __( 'Most relevant', 'photofetch' ),
				'sortNewest'        => __( 'Newest', 'photofetch' ),
				/* translators: %s: photo title */
				'selectPhoto'       => __( 'Select %s', 'photofetch' ),
				'inLibrary'         => __( 'In library', 'photofetch' ),
				'loading'           => __( 'Loading…', 'photofetch' ),
				'loadMore'          => __( 'Load more photos', 'photofetch' ),
				'noResults'         => __( 'No photos found. Try a broader term, or drop a filter.', 'photofetch' ),
				'error'             => __( 'The Photo Directory API didn’t respond. Your Media Library is unaffected.', 'photofetch' ),

				'detailsLabel'      => __( 'Photo details', 'photofetch' ),
				'detailsEmpty'      => __( 'Select a photo to see its details.', 'photofetch' ),
				/* translators: %s: photographer's display name */
				'byLine'            => __( 'By %s · CC0', 'photofetch' ),
				'fieldTitle'        => __( 'Title', 'photofetch' ),
				'fieldAlt'          => __( 'Alt text', 'photofetch' ),
				'fieldAltHint'      => __( 'describe the photo', 'photofetch' ),
				'fieldCaption'      => __( 'Caption', 'photofetch' ),
				'importSize'        => __( 'Import size', 'photofetch' ),

				'nothingSelected'   => __( 'No photos selected', 'photofetch' ),
				'importOnly'        => __( 'Import only', 'photofetch' ),
				'insertIntoPost'    => __( 'Insert into post', 'photofetch' ),
				/* translators: 1: current photo number, 2: total photos */
				'importingProgress' => __( 'Importing %1$s of %2$s…', 'photofetch' ),
				'importFailed'      => __( 'Some photos could not be imported.', 'photofetch' ),
				'viewFull'          => __( 'View full', 'photofetch' ),
				'close'             => __( 'Close', 'photofetch' ),
			),
		);
	}

	/**
	 * Adds the "Media > Photo Directory" admin page.
	 */
	public function register_admin_page() {
		add_media_page(
			__( 'Photo Directory', 'photofetch' ),
			__( 'Photo Directory', 'photofetch' ),
			'upload_files',
			'photofetch-photo-directory',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Renders the "Media > Photo Directory" admin page. Everything inside
	 * .wrap is rendered by assets/js/photo-browser.js, including the page
	 * heading, so that the title, description and "Import settings" link
	 * share one layout container. No h1 is printed here on purpose: a second
	 * one would leave the screen with two competing top-level headings.
	 */
	public function render_admin_page() {
		wp_enqueue_style( 'photofetch-photo-browser' );
		wp_enqueue_script( 'photofetch-photo-browser' );
		?>
		<div class="wrap photofetch-wrap">
			<div id="photofetch-browser" class="photofetch-browser"></div>
		</div>
		<?php
	}

	/**
	 * Load the picker UI on post edit screens (both classic and block editor
	 * land on post.php/post-new.php) so the "Photo Directory" button and the
	 * native media modal tab have their script available.
	 *
	 * @param string $hook The current admin page hook suffix.
	 */
	public function maybe_enqueue_admin_assets( $hook ) {
		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && current_user_can( 'upload_files' ) ) {
			wp_enqueue_style( 'photofetch-admin' );
			wp_enqueue_script( 'photofetch-admin' );
			wp_enqueue_style( 'photofetch-media-tab' );
			wp_enqueue_media();
			wp_enqueue_script( 'photofetch-media-modal' );
		}
	}

	/**
	 * Enqueues the native media modal tab in the block editor (its Featured
	 * Image / Image block pickers use the same underlying wp.media frame as
	 * the classic editor).
	 */
	public function enqueue_block_editor_assets() {
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}
		wp_enqueue_style( 'photofetch-admin' );
		wp_enqueue_script( 'photofetch-admin' );
		wp_enqueue_style( 'photofetch-media-tab' );
		wp_enqueue_media();
		wp_enqueue_script( 'photofetch-media-modal' );
	}

	/**
	 * Classic editor entry point: adds a button next to "Add Media".
	 *
	 * @param string $editor_id ID of the editor instance the button belongs to.
	 */
	public function media_button( $editor_id ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}
		printf(
			' <button type="button" class="button photofetch-open-modal" data-editor="%s"><span class="wp-media-buttons-icon dashicons dashicons-camera" aria-hidden="true"></span> %s</button>',
			esc_attr( $editor_id ),
			esc_html__( 'Photo Directory', 'photofetch' )
		);
	}
}
