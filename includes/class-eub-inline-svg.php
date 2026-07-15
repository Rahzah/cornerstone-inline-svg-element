<?php
/**
 * Plugin bootstrap and secure SVG renderer.
 *
 * @package EubuleusInlineSVG
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Eubuleus_Inline_SVG {

	/**
	 * Register WordPress and Cornerstone hooks.
	 *
	 * @return void
	 */
	public static function boot() {
		add_action( 'plugins_loaded', array( __CLASS__, 'load_textdomain' ) );
		add_action( 'cs_register_elements', array( __CLASS__, 'register_element' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
		add_action( 'admin_notices', array( __CLASS__, 'dependency_notice' ) );
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public static function load_textdomain() {
		load_plugin_textdomain( 'eubuleus-inline-svg', false, dirname( plugin_basename( EUB_INLINE_SVG_FILE ) ) . '/languages' );
	}

	/**
	 * Load the small amount of structural CSS required by the inline SVG.
	 * Cornerstone generates all user-configurable design CSS itself.
	 *
	 * @return void
	 */
	public static function enqueue_styles() {
		wp_enqueue_style(
			'eubuleus-inline-svg',
			EUB_INLINE_SVG_URL . 'assets/inline-svg.css',
			array(),
			EUB_INLINE_SVG_VERSION
		);
	}

	/**
	 * Register the element after Cornerstone has loaded its own definitions.
	 *
	 * @return void
	 */
	public static function register_element() {
		if (
			! function_exists( 'cs_register_element' )
			|| ! class_exists( '\\enshrined\\svgSanitize\\Sanitizer' )
			|| ! class_exists( 'DOMDocument' )
		) {
			return;
		}

		require_once EUB_INLINE_SVG_PATH . 'includes/element-inline-svg.php';
	}

	/**
	 * Tell administrators when the required sanitizer is unavailable.
	 *
	 * @return void
	 */
	public static function dependency_notice() {
		if (
			! current_user_can( 'activate_plugins' )
			|| ( class_exists( '\\enshrined\\svgSanitize\\Sanitizer' ) && class_exists( 'DOMDocument' ) )
		) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo wp_kses_post(
			__(
				'<strong>Eubuleus Inline SVG for Cornerstone</strong> requires the active <strong>Safe SVG</strong> plugin and the PHP DOM/XML extension. The element stays disabled until both dependencies are available.',
				'eubuleus-inline-svg'
			)
		);
		echo '</p></div>';
	}

	/**
	 * Render a Cornerstone element.
	 *
	 * @param array $data Cornerstone element data.
	 * @return string
	 */
	public static function render_element( $data ) {
		$source = isset( $data['svg_source'] ) && is_scalar( $data['svg_source'] )
			? trim( (string) $data['svg_source'] )
			: '';

		// Match the native Image element: resolve Dynamic Content before
		// interpreting a URL or Cornerstone's "attachment-id:size" notation.
		if ( '' !== $source && function_exists( 'cs_dynamic_content' ) ) {
			$resolved_source = cs_dynamic_content( $source );
			if ( is_scalar( $resolved_source ) ) {
				$source = trim( (string) $resolved_source );
			}
		}

		$attachment_id = self::attachment_id_from_source( $source );
		$svg           = '';
		$error         = '';

		if ( $attachment_id ) {
			$result = self::load_attachment_svg( $attachment_id );

			if ( is_wp_error( $result ) ) {
				$error = $result->get_error_message();
			} else {
				$svg = self::prepare_svg( $result, $attachment_id, $data );
				if ( is_wp_error( $svg ) ) {
					$error = $svg->get_error_message();
					$svg   = '';
				}
			}
		} elseif ( '' !== $source ) {
			$error = __( 'Only SVG files selected from the local WordPress media library are allowed.', 'eubuleus-inline-svg' );
		}

		$is_preview = did_action( 'cs_element_rendering' );

		if ( '' === $svg && ! $is_preview ) {
			return '';
		}

		$classes = isset( $data['classes'] ) && is_array( $data['classes'] ) ? $data['classes'] : array();
		$atts    = array(
			'class' => array_merge( array( 'x-image', 'eub-inline-svg' ), $classes ),
		);

		if ( ! empty( $data['id'] ) ) {
			$atts['id'] = $data['id'];
		}

		if ( ! empty( $data['style'] ) ) {
			$atts['style'] = $data['style'];
		}

		$atts = cs_apply_effect( $atts, $data );

		$data['image_tag'] = ! empty( $data['image_link'] ) ? 'a' : 'span';
		list( $tag, $atts ) = cs_apply_link( $atts, $data, 'image' );
		if ( 'a' === $tag ) {
			$link_label = isset( $data['svg_link_label'] ) ? trim( wp_strip_all_tags( (string) $data['svg_link_label'] ) ) : '';
			if ( '' === $link_label ) {
				$link_label = self::accessible_title( $attachment_id, $data );
			}
			if ( '' !== $link_label ) {
				$atts['aria-label'] = $link_label;
			}
		}

		if ( '' === $svg ) {
			$message = $error ? $error : __( 'Select an SVG file.', 'eubuleus-inline-svg' );
			$svg     = '<span class="eub-inline-svg__placeholder">' . esc_html( $message ) . '</span>';
		}

		$custom_atts = isset( $data['custom_atts'] ) ? $data['custom_atts'] : null;

		return cs_tag( $tag, $atts, $custom_atts, $svg );
	}

	/**
	 * Resolve only IDs, Cornerstone image references, or URLs belonging to
	 * local Media Library attachments. Cornerstone stores Media Library images
	 * as "attachment-id:size", for example "5501:raw". The size is irrelevant
	 * for an inline SVG, so only its verified attachment ID is used.
	 *
	 * @param string $source File control value.
	 * @return int
	 */
	private static function attachment_id_from_source( $source ) {
		if ( '' === $source ) {
			return 0;
		}

		if ( preg_match( '/^\s*(\d+)(?::[a-zA-Z0-9_-]+)?\s*$/', $source, $matches ) ) {
			return absint( $matches[1] );
		}

		$url = strtok( $source, '?#' );
		if ( ! $url ) {
			return 0;
		}

		return absint( attachment_url_to_postid( $url ) );
	}

	/**
	 * Read and sanitize an SVG attachment. This deliberately fails closed.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string|WP_Error
	 */
	private static function load_attachment_svg( $attachment_id ) {
		if ( 'attachment' !== get_post_type( $attachment_id ) ) {
			return new WP_Error( 'eub_inline_svg_not_attachment', __( 'The selected file is not a Media Library attachment.', 'eubuleus-inline-svg' ) );
		}

		$file      = get_attached_file( $attachment_id );
		$mime_type = get_post_mime_type( $attachment_id );
		$extension = $file ? strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) : '';

		if ( 'image/svg+xml' !== $mime_type || 'svg' !== $extension ) {
			return new WP_Error( 'eub_inline_svg_wrong_type', __( 'The selected attachment is not an SVG file.', 'eubuleus-inline-svg' ) );
		}

		if ( ! $file || ! is_readable( $file ) ) {
			return new WP_Error( 'eub_inline_svg_unreadable', __( 'The selected SVG file cannot be read.', 'eubuleus-inline-svg' ) );
		}

		$uploads   = wp_get_upload_dir();
		$real_file = realpath( $file );
		$real_base = ! empty( $uploads['basedir'] ) ? realpath( $uploads['basedir'] ) : false;
		if (
			! $real_file
			|| ! $real_base
			|| 0 !== strpos( $real_file, trailingslashit( $real_base ) )
		) {
			return new WP_Error( 'eub_inline_svg_outside_uploads', __( 'The selected SVG is not stored in the local WordPress uploads directory.', 'eubuleus-inline-svg' ) );
		}
		$file = $real_file;

		$max_size  = (int) apply_filters( 'eub_inline_svg_max_file_size', 2 * MB_IN_BYTES, $attachment_id );
		$file_size = filesize( $file );
		if ( false === $file_size || $file_size > $max_size ) {
			return new WP_Error( 'eub_inline_svg_too_large', __( 'The selected SVG file is too large to render inline.', 'eubuleus-inline-svg' ) );
		}

		$cache_key = $attachment_id . ':' . (string) filemtime( $file ) . ':' . (string) $file_size;
		$cache     = wp_cache_get( $cache_key, 'eubuleus_inline_svg' );

		if ( is_string( $cache ) && '' !== $cache ) {
			return $cache;
		}

		$dirty = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $dirty || '' === trim( $dirty ) ) {
			return new WP_Error( 'eub_inline_svg_empty', __( 'The selected SVG file is empty.', 'eubuleus-inline-svg' ) );
		}

		$sanitizer = new \enshrined\svgSanitize\Sanitizer();
		$sanitizer->removeRemoteReferences( true );
		$sanitizer->minify( true );
		$clean = $sanitizer->sanitize( $dirty );

		if ( ! is_string( $clean ) || '' === trim( $clean ) ) {
			return new WP_Error( 'eub_inline_svg_unsafe', __( 'The selected SVG could not be sanitized safely.', 'eubuleus-inline-svg' ) );
		}

		wp_cache_set( $cache_key, $clean, 'eubuleus_inline_svg', HOUR_IN_SECONDS );

		return $clean;
	}

	/**
	 * Make IDs instance-safe and apply accessible SVG semantics.
	 *
	 * @param string $svg           Sanitized SVG markup.
	 * @param int    $attachment_id Attachment ID.
	 * @param array  $data          Cornerstone element data.
	 * @return string|WP_Error
	 */
	private static function prepare_svg( $svg, $attachment_id, $data ) {
		$previous = libxml_use_internal_errors( true );
		$document = new DOMDocument();
		$loaded   = $document->loadXML( $svg, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_COMPACT );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded || ! $document->documentElement || 'svg' !== strtolower( $document->documentElement->localName ) ) {
			return new WP_Error( 'eub_inline_svg_invalid_xml', __( 'The selected file does not contain valid SVG markup.', 'eubuleus-inline-svg' ) );
		}

		$root       = $document->documentElement;
		$element_id = isset( $data['_id'] ) ? sanitize_html_class( (string) $data['_id'] ) : '';
		$prefix     = 'eub-svg-' . ( $element_id ? $element_id . '-' : '' ) . sanitize_html_class( wp_unique_id( (string) $attachment_id . '-' ) ) . '-';

		self::prefix_internal_ids( $document, $prefix );

		$existing_class = trim( $root->getAttribute( 'class' ) );
		$root->setAttribute( 'class', trim( 'eub-inline-svg__graphic ' . $existing_class ) );
		$root->setAttribute( 'focusable', 'false' );

		$title_node = self::direct_child( $root, 'title' );
		$desc_node  = self::direct_child( $root, 'desc' );
		$old_title  = $title_node ? trim( $title_node->textContent ) : '';
		$old_desc   = $desc_node ? trim( $desc_node->textContent ) : '';

		if ( $title_node ) {
			$root->removeChild( $title_node );
		}
		if ( $desc_node ) {
			$root->removeChild( $desc_node );
		}

		$decorative = ! empty( $data['svg_decorative'] );

		if ( $decorative ) {
			$root->setAttribute( 'aria-hidden', 'true' );
			$root->removeAttribute( 'role' );
			$root->removeAttribute( 'aria-label' );
			$root->removeAttribute( 'aria-labelledby' );
			$root->removeAttribute( 'aria-describedby' );
		} else {
			$title = self::accessible_title( $attachment_id, $data, $old_title );
			$desc  = isset( $data['svg_description'] ) ? trim( wp_strip_all_tags( (string) $data['svg_description'] ) ) : '';
			if ( '' === $desc ) {
				$desc = $old_desc;
			}

			$title_id = $prefix . 'title';
			$desc_id  = $prefix . 'desc';
			$has_title = false;
			$has_desc  = false;

			if ( '' !== $title ) {
				$new_title = $document->createElementNS( 'http://www.w3.org/2000/svg', 'title' );
				$new_title->setAttribute( 'id', $title_id );
				$new_title->appendChild( $document->createTextNode( $title ) );
				$root->insertBefore( $new_title, $root->firstChild );
				$has_title = true;
			}

			if ( '' !== $desc ) {
				$new_desc = $document->createElementNS( 'http://www.w3.org/2000/svg', 'desc' );
				$new_desc->setAttribute( 'id', $desc_id );
				$new_desc->appendChild( $document->createTextNode( $desc ) );
				$insert_before = $root->firstChild && 'title' === strtolower( $root->firstChild->localName )
					? $root->firstChild->nextSibling
					: $root->firstChild;
				$root->insertBefore( $new_desc, $insert_before );
				$has_desc = true;
			}

			$root->setAttribute( 'role', 'img' );
			$root->removeAttribute( 'aria-hidden' );
			$root->removeAttribute( 'aria-label' );
			if ( $has_title ) {
				$root->setAttribute( 'aria-labelledby', $title_id );
			} else {
				$root->removeAttribute( 'aria-labelledby' );
			}
			if ( $has_desc ) {
				$root->setAttribute( 'aria-describedby', $desc_id );
			} else {
				$root->removeAttribute( 'aria-describedby' );
			}
		}

		$output = $document->saveXML( $root );

		return is_string( $output ) ? $output : new WP_Error( 'eub_inline_svg_serialization', __( 'The SVG could not be rendered.', 'eubuleus-inline-svg' ) );
	}

	/**
	 * Resolve the accessible label using the same predictable fallback order.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param array  $data          Cornerstone data.
	 * @param string $svg_title     Original SVG title.
	 * @return string
	 */
	private static function accessible_title( $attachment_id, $data, $svg_title = '' ) {
		$title = isset( $data['svg_title'] ) ? trim( wp_strip_all_tags( (string) $data['svg_title'] ) ) : '';

		if ( '' === $title && $attachment_id ) {
			$title = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
		}
		if ( '' === $title ) {
			$title = trim( $svg_title );
		}
		if ( '' === $title && $attachment_id ) {
			$title = trim( (string) get_the_title( $attachment_id ) );
		}

		return $title;
	}

	/**
	 * Prefix IDs and all common local fragment references so repeated inline
	 * instances cannot interfere with gradients, masks or accessibility IDs.
	 *
	 * @param DOMDocument $document SVG document.
	 * @param string      $prefix   Unique element prefix.
	 * @return void
	 */
	private static function prefix_internal_ids( DOMDocument $document, $prefix ) {
		$xpath = new DOMXPath( $document );
		$map   = array();

		foreach ( $xpath->query( '//*[@id]' ) as $node ) {
			$old_id = $node->getAttribute( 'id' );
			if ( '' === $old_id ) {
				continue;
			}

			$new_id         = $prefix . sanitize_html_class( $old_id );
			$map[ $old_id ] = $new_id;
			$node->setAttribute( 'id', $new_id );
		}

		if ( ! $map ) {
			return;
		}

		foreach ( $xpath->query( '//*' ) as $node ) {
			if ( ! $node->hasAttributes() ) {
				continue;
			}

			foreach ( $node->attributes as $attribute ) {
				$value = $attribute->value;
				foreach ( $map as $old_id => $new_id ) {
					$value = str_replace( 'url(#' . $old_id . ')', 'url(#' . $new_id . ')', $value );
					if ( '#' . $old_id === $value ) {
						$value = '#' . $new_id;
					}
					$value = preg_replace( '/(^|\\s)' . preg_quote( $old_id, '/' ) . '(?=\\s|$)/', '$1' . $new_id, $value );
				}
				$attribute->value = $value;
			}
		}
	}

	/**
	 * Find a direct SVG child by local name.
	 *
	 * @param DOMElement $root Root SVG node.
	 * @param string     $name Local name.
	 * @return DOMElement|null
	 */
	private static function direct_child( DOMElement $root, $name ) {
		foreach ( $root->childNodes as $child ) {
			if ( $child instanceof DOMElement && $name === strtolower( $child->localName ) ) {
				return $child;
			}
		}

		return null;
	}
}
