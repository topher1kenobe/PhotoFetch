<?php
/**
 * Handles downloading and sideloading photos into the Media Library.
 *
 * @package PhotoFetch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Downloads a chosen photo and sideloads it into the local Media Library as
 * a real attachment (so it behaves exactly like any uploaded image —
 * usable as a featured image, in blocks, etc.).
 */
class PhotoFetch_Importer {

	const META_SOURCE_ID     = '_photofetch_source_id';
	const META_SOURCE_URL    = '_photofetch_source_url';
	const META_SOURCE_AUTHOR = '_photofetch_source_author';

	/**
	 * Sizes the UI is allowed to ask for, mapped to the upstream size key
	 * each one downloads. Anything else falls back to the original.
	 *
	 * @return array Map of requested size => upstream size key.
	 */
	public static function allowed_sizes() {
		return array(
			'full'   => 'full',
			'large'  => 'large',
			'medium' => 'medium',
		);
	}

	/**
	 * AJAX handler for `action=photofetch_import`. Expects `photo_id`, plus
	 * optional `size`, `add_credit` and per-photo `title`, `alt` and
	 * `caption` overrides; returns the resulting attachment.
	 */
	public static function ajax_import() {
		check_ajax_referer( 'photofetch_nonce', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to do this.', 'photofetch' ) ),
				403
			);
			return;
		}

		$photo_id = isset( $_POST['photo_id'] ) ? absint( $_POST['photo_id'] ) : 0;

		$size    = isset( $_POST['size'] ) ? sanitize_key( wp_unslash( $_POST['size'] ) ) : 'full';
		$allowed = self::allowed_sizes();
		$size    = isset( $allowed[ $size ] ) ? $allowed[ $size ] : 'full';

		$args = array(
			// Absent means "yes": credit is on by default, and the UI only
			// sends the flag when the editor turns it off.
			'add_credit' => ! isset( $_POST['add_credit'] ) || '0' !== sanitize_key( wp_unslash( $_POST['add_credit'] ) ),
			'title'      => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : null,
			'alt'        => isset( $_POST['alt'] ) ? sanitize_text_field( wp_unslash( $_POST['alt'] ) ) : null,
			'caption'    => isset( $_POST['caption'] ) ? sanitize_textarea_field( wp_unslash( $_POST['caption'] ) ) : null,
		);

		if ( ! $photo_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing photo ID.', 'photofetch' ) ) );
			return;
		}

		// If we've already imported this photo, just return the existing attachment
		// rather than downloading (and cluttering the library with) a duplicate.
		$existing = self::find_existing_attachment( $photo_id );
		if ( $existing ) {
			wp_send_json_success( self::attachment_response( $existing, true ) );
			return;
		}

		$photo = PhotoFetch_API::get_photo( $photo_id );
		if ( is_wp_error( $photo ) ) {
			wp_send_json_error( array( 'message' => $photo->get_error_message() ) );
			return;
		}

		$attachment_id = self::import_photo( $photo, $size, $args );
		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
			return;
		}

		wp_send_json_success( self::attachment_response( $attachment_id ) );
	}

	/**
	 * Look up a local attachment previously imported from a given upstream photo.
	 *
	 * Queries on meta_key/meta_value, which phpcs flags as a potentially slow
	 * query since postmeta isn't indexed on those columns by default. There's
	 * no core-supported alternative for this lookup shape (a custom mapping
	 * table would be overkill for what is, at most, a few thousand rows per
	 * site), so the warning is intentionally suppressed here rather than
	 * "fixed" by switching to a less accurate lookup.
	 *
	 * @param int $photo_id Upstream Photo Directory ID.
	 * @return int Local attachment ID, or 0 if this photo hasn't been imported yet.
	 */
	public static function find_existing_attachment( $photo_id ) {
		$existing = get_posts(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'numberposts' => 1,
				'fields'      => 'ids',
				// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- exact-match lookup on a plugin-owned meta key, no indexed alternative available.
				'meta_key'    => self::META_SOURCE_ID,
				'meta_value'  => $photo_id,
				// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		return $existing ? intval( $existing[0] ) : 0;
	}

	/**
	 * Resolve a whole page of upstream photo IDs to the local attachments
	 * they were imported as, so the grid can mark them without asking one
	 * question per card.
	 *
	 * Runs two queries regardless of how many photos are passed in: one to
	 * find the matching attachments, one to prime their meta cache.
	 *
	 * @param int[] $photo_ids Upstream Photo Directory IDs.
	 * @return array Map of upstream photo ID => local attachment ID.
	 */
	public static function find_existing_attachments( $photo_ids ) {
		$photo_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $photo_ids ) ) ) );
		if ( empty( $photo_ids ) ) {
			return array();
		}

		$attachment_ids = get_posts(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'numberposts'            => -1,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- batched exact-match lookup on a plugin-owned meta key, deliberately replacing one query per card.
					array(
						'key'     => self::META_SOURCE_ID,
						'value'   => $photo_ids,
						'compare' => 'IN',
					),
				),
			)
		);

		if ( empty( $attachment_ids ) ) {
			return array();
		}

		update_meta_cache( 'post', $attachment_ids );

		$map = array();
		foreach ( $attachment_ids as $attachment_id ) {
			$photo_id = absint( get_post_meta( $attachment_id, self::META_SOURCE_ID, true ) );
			// Keep the earliest import when a photo somehow has more than one.
			if ( $photo_id && ! isset( $map[ $photo_id ] ) ) {
				$map[ $photo_id ] = intval( $attachment_id );
			}
		}

		return $map;
	}

	/**
	 * Downloads the chosen size of a normalized photo and sideloads it into
	 * the Media Library.
	 *
	 * @param array  $photo Normalized photo data from PhotoFetch_API::normalize_item().
	 * @param string $size  Preferred size key; falls back to 'full', then the largest available.
	 * @param array  $args  {
	 *     Optional. Import options supplied by the editor.
	 *
	 *     @type bool        $add_credit Whether to write the photographer credit as the
	 *                                   caption. Default true.
	 *     @type string|null $title      Editor-supplied title, overriding the photo's own.
	 *     @type string|null $alt        Editor-supplied alt text.
	 *     @type string|null $caption    Editor-supplied caption, overriding the credit line.
	 * }
	 * @return int|WP_Error Attachment ID on success.
	 */
	public static function import_photo( $photo, $size = 'full', $args = array() ) {
		// Loaded here rather than at file scope: these are wp-admin includes and
		// are only needed while actually importing, so front-end requests should
		// not pay for parsing them.
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$args = wp_parse_args(
			$args,
			array(
				'add_credit' => true,
				'title'      => null,
				'alt'        => null,
				'caption'    => null,
			)
		);

		if ( empty( $photo['sizes'] ) ) {
			return new WP_Error(
				'photofetch_no_image',
				__( 'No downloadable image was found for this photo.', 'photofetch' )
			);
		}

		if ( ! empty( $photo['sizes'][ $size ]['url'] ) ) {
			$source_url = $photo['sizes'][ $size ]['url'];
		} elseif ( ! empty( $photo['sizes']['full']['url'] ) ) {
			$source_url = $photo['sizes']['full']['url'];
		} else {
			$largest    = self::largest_size( $photo['sizes'] );
			$source_url = $largest['url'];
		}

		if ( empty( $source_url ) ) {
			return new WP_Error(
				'photofetch_no_image',
				__( 'No downloadable image was found for this photo.', 'photofetch' )
			);
		}

		// Defense-in-depth: nothing a site visitor or lower-privileged user
		// supplies can influence which host we ever talk to (search terms,
		// filters, and photo IDs only ever become query args appended to
		// our own hardcoded REMOTE_BASE/TAXONOMY_BASE constants) — but the
		// image URL itself is data returned *by* that API, not something
		// this plugin controls. Pinning it to an expected host means a
		// compromised or malicious upstream response can't make this site
		// download and store an attacker's file from an arbitrary server.
		if ( ! self::is_allowed_image_host( $source_url ) ) {
			return new WP_Error(
				'photofetch_untrusted_host',
				sprintf(
					/* translators: %s: the image URL's actual, rejected hostname */
					__(
						// phpcs:ignore Generic.Files.LineLength.TooLong -- deliberately detailed message; splitting the string via concatenation would complicate translation for little benefit.
						'This photo could not be imported because its source URL (host: %s) is not on a trusted host. Add it via the photofetch_allowed_image_hosts filter if you recognize it as legitimate Photo Directory infrastructure.',
						'photofetch'
					),
					wp_parse_url( $source_url, PHP_URL_HOST )
				)
			);
		}

		$tmp_file = download_url( $source_url, 30 );
		if ( is_wp_error( $tmp_file ) ) {
			return $tmp_file;
		}

		$title = ( null !== $args['title'] && '' !== $args['title'] ) ? $args['title'] : $photo['title'];

		$filename  = self::guess_filename( $source_url, $photo, $title );
		$converted = self::maybe_convert_image( $tmp_file, $filename );
		$tmp_file  = $converted['tmp_file'];
		$filename  = $converted['filename'];

		$file_array = array(
			'name'     => $filename,
			'tmp_name' => $tmp_file,
		);

		$alt     = ( null !== $args['alt'] ) ? $args['alt'] : $photo['alt'];
		$caption = $args['caption'];

		// An editor-supplied caption wins outright. Otherwise the credit line
		// is written only when crediting is switched on.
		if ( null === $caption ) {
			$caption = $args['add_credit'] ? self::build_credit_line( $photo ) : '';
		}

		$post_data = array(
			'post_title'   => $title,
			'post_content' => $photo['description'],
			'post_excerpt' => $caption,
		);

		$attachment_id = media_handle_sideload( $file_array, 0, $title, $post_data );

		if ( is_wp_error( $attachment_id ) ) {
			if ( file_exists( $tmp_file ) ) {
				wp_delete_file( $tmp_file );
			}
			return $attachment_id;
		}

		if ( '' !== $alt ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
		}

		update_post_meta( $attachment_id, self::META_SOURCE_ID, $photo['id'] );
		update_post_meta( $attachment_id, self::META_SOURCE_URL, $photo['link'] );
		if ( ! empty( $photo['author'] ) ) {
			update_post_meta( $attachment_id, self::META_SOURCE_AUTHOR, $photo['author'] );
		}
		update_post_meta( $attachment_id, '_photofetch_imported', 1 );

		return $attachment_id;
	}

	/**
	 * Builds the photographer credit line, when the upstream API exposes
	 * an author name. Used as the attachment's Caption field
	 * (post_excerpt); the photo's own description text goes in the
	 * Description field (post_content) — and, as alt text — instead.
	 *
	 * Public because the browse UI prefills its caption field with this
	 * exact line, filters included, so what the editor sees is what gets
	 * written.
	 *
	 * @param array $photo Normalized photo data.
	 * @return string Credit line, or an empty string if no author is available.
	 */
	public static function build_credit_line( $photo ) {
		if ( empty( $photo['author'] ) ) {
			return '';
		}

		$credit = sprintf(
			/* translators: %s: photographer's display name */
			__( 'Photo by %s, via the WordPress Photo Directory.', 'photofetch' ),
			$photo['author']
		);

		/**
		 * Filters the photographer credit written into the attachment caption.
		 *
		 * @param string $credit Credit line.
		 * @param array  $photo  Normalized photo data.
		 */
		return apply_filters( 'photofetch_credit_line', $credit, $photo );
	}

	/**
	 * Picks the size with the largest pixel area from a photo's size map.
	 *
	 * @param array $sizes Map of size name => [ url, width, height ].
	 * @return array The largest size entry, e.g. [ url, width, height ].
	 */
	private static function largest_size( $sizes ) {
		$best = null;
		foreach ( $sizes as $s ) {
			$area = intval( $s['width'] ) * intval( $s['height'] );
			if ( null === $best || $area > $best['_area'] ) {
				$s['_area'] = $area;
				$best       = $s;
			}
		}
		return $best ? $best : reset( $sizes );
	}

	/**
	 * Hosts sideloaded image URLs are allowed to come from. The Photo
	 * Directory API is this plugin's sole source of these URLs, and this
	 * plugin only ever talks to wordpress.org for that API — but a URL
	 * *inside* an API response is still third-party-supplied data, not
	 * something this plugin controls directly.
	 *
	 * Includes:
	 * - `wp.com`, since wordpress.org properties commonly serve uploaded
	 *   images through Automattic's Photon CDN (`i0.wp.com`, etc.).
	 * - `w.org`, WordPress.org's own short domain (the same family as
	 *   `s.w.org`, its well-known static-assets host) — the Photo
	 *   Directory serves its images from `pd.w.org` specifically.
	 *
	 * @return string[] Lowercased allowed hostnames. A URL's host matches if
	 *                   it equals one of these, or is a subdomain of one.
	 */
	private static function allowed_image_hosts() {
		/**
		 * Filters the hostnames sideloaded image URLs are allowed to come from.
		 *
		 * @param string[] $hosts Lowercased allowed hostnames.
		 */
		return apply_filters( 'photofetch_allowed_image_hosts', array( 'wordpress.org', 'wp.com', 'w.org' ) );
	}

	/**
	 * Whether a URL's host is on the allowed list for sideloading.
	 *
	 * @param string $url Candidate image URL.
	 * @return bool
	 */
	private static function is_allowed_image_host( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $host ) {
			return false;
		}
		$host = strtolower( $host );

		foreach ( self::allowed_image_hosts() as $allowed ) {
			$allowed = strtolower( $allowed );
			if ( $host === $allowed || substr( $host, -( strlen( $allowed ) + 1 ) ) === '.' . $allowed ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Derives a sensible local filename for a downloaded photo.
	 *
	 * The Photo Directory serves its images under opaque hashed names like
	 * "2636ad781335e1299.12442141.jpeg", so the filename is built from the
	 * attachment title plus the photo ID instead, e.g.
	 * "low-clouds-along-a-mountain-ridge-250633.jpeg". The ID keeps names
	 * unique and traceable back to the source photo. Falls back to the
	 * source URL's basename when the title yields no usable slug, such as
	 * a title written entirely in a non-Latin script.
	 *
	 * @param string $url   Source image URL.
	 * @param array  $photo Normalized photo data.
	 * @param string $title Title the attachment is being imported with.
	 * @return string Sanitized filename.
	 */
	private static function guess_filename( $url, $photo, $title ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		$base = $path ? basename( $path ) : '';
		$ext  = $base ? strtolower( (string) pathinfo( $base, PATHINFO_EXTENSION ) ) : '';
		if ( ! $ext ) {
			$ext = 'jpg';
		}

		// sanitize_title() keeps non-ASCII characters as percent-encoded
		// octets, which make unreadable filenames, so drop them.
		$slug = sanitize_title( remove_accents( $title ) );
		$slug = trim( preg_replace( '/-+/', '-', preg_replace( '/%[a-f0-9]{2}/i', '', $slug ) ), '-' );

		if ( strlen( $slug ) > 60 ) {
			$slug = substr( $slug, 0, 60 );
			$cut  = strrpos( $slug, '-' );
			if ( false !== $cut ) {
				$slug = substr( $slug, 0, $cut );
			}
		}

		if ( '' !== $slug ) {
			$filename = $slug . '-' . $photo['id'] . '.' . $ext;
		} elseif ( $base && false !== strpos( $base, '.' ) ) {
			$filename = $base;
		} else {
			$filename = 'photo-' . $photo['id'] . '.' . $ext;
		}

		/**
		 * Filters the filename an imported photo is saved under.
		 *
		 * @param string $filename Filename, including the extension of the
		 *                         downloaded file. Swapped to .webp or .avif
		 *                         afterwards when format conversion is on.
		 * @param array  $photo    Normalized photo data.
		 * @param string $title    Title the attachment is being imported with.
		 */
		$filename = apply_filters( 'photofetch_import_filename', $filename, $photo, $title );

		return sanitize_file_name( $filename );
	}

	/**
	 * Converts the downloaded temp file to the site's preferred image
	 * format (Settings > Photo Directory), when one is configured and this
	 * server's image editor actually supports producing it. Falls back to
	 * the original file untouched on any failure — format conversion is a
	 * nice extra, never something that should be able to break an import.
	 *
	 * @param string $tmp_file Path to the downloaded temp file.
	 * @param string $filename Filename guessed for the original download.
	 * @return array {
	 *     @type string $tmp_file Path to use going forward (converted copy, or the original).
	 *     @type string $filename Filename to use going forward (extension swapped to match).
	 * }
	 */
	private static function maybe_convert_image( $tmp_file, $filename ) {
		$original = array(
			'tmp_file' => $tmp_file,
			'filename' => $filename,
		);

		$format = PhotoFetch_Settings::get_format();
		if ( 'original' === $format ) {
			return $original;
		}

		$mime = 'webp' === $format ? 'image/webp' : 'image/avif';
		if ( ! wp_image_editor_supports( array( 'mime_type' => $mime ) ) ) {
			return $original;
		}

		$editor = wp_get_image_editor( $tmp_file );
		if ( is_wp_error( $editor ) || ! $editor->supports_mime_type( $mime ) ) {
			return $original;
		}

		// Quality only ever applies here — this whole branch is unreachable
		// when the format is 'original', so nothing re-encodes (and no
		// quality setting applies to) whatever format the photo already was.
		$editor->set_quality( PhotoFetch_Settings::get_quality() );

		$new_path = wp_tempnam( $filename );
		$saved    = $editor->save( $new_path, $mime );

		if ( is_wp_error( $saved ) || empty( $saved['path'] ) ) {
			if ( file_exists( $new_path ) ) {
				wp_delete_file( $new_path );
			}
			return $original;
		}

		// The original download is no longer needed once the converted copy exists.
		if ( file_exists( $tmp_file ) ) {
			wp_delete_file( $tmp_file );
		}

		return array(
			'tmp_file' => $saved['path'],
			'filename' => self::swap_extension( $filename, 'webp' === $format ? 'webp' : 'avif' ),
		);
	}

	/**
	 * Replaces a filename's extension.
	 *
	 * @param string $filename Original filename.
	 * @param string $ext      New extension, without the leading dot.
	 * @return string Filename with its extension replaced.
	 */
	private static function swap_extension( $filename, $ext ) {
		$dot  = strrpos( $filename, '.' );
		$base = false !== $dot ? substr( $filename, 0, $dot ) : $filename;
		return $base . '.' . $ext;
	}

	/**
	 * Builds the small JSON-friendly attachment payload sent back to the browser.
	 *
	 * @param int  $attachment_id Local attachment ID.
	 * @param bool $already_in_library Whether this attachment was surfaced by the
	 *                                 duplicate guard rather than freshly imported.
	 * @return array {
	 *     @type int    $id       Attachment ID.
	 *     @type string $title    Attachment title.
	 *     @type string $thumbUrl   Medium-size (or original) image URL.
	 *     @type string $editUrl    Admin edit-attachment URL.
	 *     @type string $libraryUrl Media Library URL focused on this attachment.
	 * }
	 */
	private static function attachment_response( $attachment_id, $already_in_library = false ) {
		$image = wp_get_attachment_image_src( $attachment_id, 'medium' );
		return array(
			'id'               => $attachment_id,
			'title'            => get_the_title( $attachment_id ),
			'thumbUrl'         => $image ? $image[0] : wp_get_attachment_url( $attachment_id ),
			'editUrl'          => admin_url( 'post.php?post=' . $attachment_id . '&action=edit' ),
			'libraryUrl'       => admin_url( 'upload.php?item=' . $attachment_id ),
			'alreadyInLibrary' => (bool) $already_in_library,
		);
	}
}
