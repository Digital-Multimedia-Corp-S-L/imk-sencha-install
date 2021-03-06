<?php
/**
 * @package Pods\Global\Functions\Media
 */

/**
 * Get the Attachment ID for a specific image field.
 *
 * @param array|int|string $image The image field array, ID, or guid.
 *
 * @return int Attachment ID.
 *
 * @since 2.0.5
 */
function pods_image_id_from_field( $image ) {
	$id = 0;

	if ( ! empty( $image ) ) {
		if ( is_array( $image ) ) {
			if ( isset( $image[0] ) ) {
				$id = pods_image_id_from_field( $image[0] );
			} elseif ( isset( $image['ID'] ) ) {
				$id = $image['ID'];
			} elseif ( isset( $image['guid'] ) ) {
				$id = pods_image_id_from_field( $image['guid'] );
			} elseif ( isset( $image['id'] ) ) {
				$id = $image['id'];
			} else {
				$id = pods_image_id_from_field( current( $image ) );
			}
		} else {
			if ( false === strpos( $image, '.' ) && is_numeric( $image ) ) {
				$id = $image;

				$the_post_type = get_post_type( $id );

				if ( false === $the_post_type ) {
					$id = 0;
				} elseif ( 'attachment' !== $the_post_type ) {
					$id = get_post_thumbnail_id( $id );
				}
			} else {
				$guid = pods_query( "SELECT `ID` FROM @wp_posts WHERE `post_type` = 'attachment' AND `guid` = %s", array( $image ) );

				if ( ! empty( $guid ) ) {
					$id = $guid[0]->ID;
				}
			}
		}//end if
	}//end if

	$id = (int) $id;

	return $id;
}

/**
 * Parse image size parameter to support custom image sizes.
 *
 * @param string|int[] $size
 *
 * @return string|int[]
 *
 * @since 2.7.23
 */
function pods_parse_image_size( $size ) {

	if ( ! is_array( $size ) ) {
		if ( is_numeric( $size ) && ! has_image_size( $size ) ) {
			// Square sizes.
			$size = $size . 'x' . $size;
		}
		// Fix HTML entity for custom sizes.
		$size = str_replace( '&#215;', 'x', $size );
	}

	return $size;
}

/**
 * Check if an image size exists or is a valid custom format for a size.
 *
 * @param string|int[] $size
 *
 * @return bool
 *
 * @since 2.7.23
 */
function pods_is_image_size( $size ) {

	$valid = false;
	$size  = pods_parse_image_size( $size );

	if ( is_array( $size ) ) {
		// Custom array size format.
		$valid = ( 2 <= count( $size ) && is_numeric( $size[0] ) && is_numeric( $size[1] ) );
	} elseif ( is_numeric( $size ) ) {
		// Numeric (square) size format.
		$valid = true;
	} elseif ( preg_match( '/[0-9]+x[0-9]+/', $size ) || preg_match( '/[0-9]+x[0-9]+x[0-1]/', $size ) ) {
		// Custom size format.
		$valid = true;
	} else {
		$sizes = get_intermediate_image_sizes();
		// Not shown by default.
		$sizes[] = 'full';
		$sizes[] = 'original';
		if ( in_array( $size, $sizes, true ) ) {
			$valid = true;
		}
	}

	return $valid;
}

/**
 * Get the <img> HTML for a specific image field.
 *
 * @param array|int|string $image      The image field array, ID, or guid.
 * @param string|array     $size       Image size to use.
 * @param int              $default    Default image to show if image not found, can be field array, ID, or guid.
 *                                     Passing `-1` prevents default filter.
 * @param string|array     $attributes <img> Attributes array or string (passed to wp_get_attachment_image).
 * @param boolean          $force      Force generation of image (if custom size array provided).
 *
 * @return string <img> HTML or empty if image not found.
 *
 * @since 2.0.5
 */
function pods_image( $image, $size = 'thumbnail', $default = 0, $attributes = '', $force = false ) {
	if ( ! $default && -1 !== $default ) {
		/**
		 * Filter for default value.
		 *
		 * Use to set a fallback image to be used when the image passed to pods_image can not be found. Will only take effect if $default is not set.
		 *
		 * @since 2.3.19
		 *
		 * @param array|int|string $default Default image to show if image not found, can be field array, ID, or guid.
		 */
		$default = apply_filters( 'pods_image_default', $default );
	}

	$html    = '';
	$id      = pods_image_id_from_field( $image );
	$default = pods_image_id_from_field( $default );
	$size    = pods_parse_image_size( $size );

	if ( 0 < $id ) {
		if ( $force ) {
			pods_maybe_image_resize( $id, $size );
		}

		$html = wp_get_attachment_image( $id, $size, true, $attributes );
	}

	if ( empty( $html ) && 0 < $default ) {
		$html = pods_image( $default, $size, -1, $attributes, $force );
	}

	return $html;
}

/**
 * Get the Image URL for a specific image field.
 *
 * @param array|int|string $image   The image field array, ID, or guid.
 * @param string|array     $size    Image size to use.
 * @param int              $default Default image to show if image not found, can be field array, ID, or guid.
 *                                  Passing `-1` prevents default filter.
 * @param boolean          $force   Force generation of image (if custom size array provided).
 *
 * @return string Image URL or empty if image not found.
 *
 * @since 2.0.5
 */
function pods_image_url( $image, $size = 'thumbnail', $default = 0, $force = false ) {
	if ( ! $default && -1 !== $default ) {
		/**
		 * Filter for default value.
		 *
		 * Use to set a fallback image to be used when the image passed to pods_image can not be found. Will only take effect if $default is not set.
		 *
		 * @since 2.7.23
		 *
		 * @param array|int|string $default Default image to show if image not found, can be field array, ID, or guid.
		 */
		$default = apply_filters( 'pods_image_url_default', $default );
	}

	$url     = '';
	$id      = pods_image_id_from_field( $image );
	$default = pods_image_id_from_field( $default );
	$size    = pods_parse_image_size( $size );

	if ( 0 < $id ) {
		if ( $force ) {
			pods_maybe_image_resize( $id, $size );
		}

		$src = wp_get_attachment_image_src( $id, $size );

		if ( ! empty( $src ) ) {
			$url = $src[0];
		} else {
			// Handle non-images
			$attachment = get_post( $id );

			if ( ! preg_match( '!^image/!', get_post_mime_type( $attachment ) ) ) {
				$url = wp_get_attachment_url( $id );
			}
		}
	}//end if

	if ( empty( $url ) && 0 < $default ) {
		$url = pods_image_url( $default, $size, -1, $force );
	}//end if

	return $url;
}

/**
 * Import media from a specific URL, saving as an attachment.
 *
 * @param string  $url         URL to media for import.
 * @param int     $post_parent ID of post parent, default none.
 * @param boolean $featured    Whether to set it as the featured (post thumbnail) of the post parent.
 * @param boolean $strict      Whether to return errors upon failure.
 *
 * @return int Attachment ID.
 *
 * @since 2.3.0
 */
function pods_attachment_import( $url, $post_parent = null, $featured = false, $strict = false ) {
	$filename = explode( '?', $url );
	$filename = $filename[0];

	$filename = explode( '#', $filename );
	$filename = $filename[0];

	$filename = substr( $filename, ( strrpos( $filename, '/' ) ) + 1 );

	$title = substr( $filename, 0, ( strrpos( $filename, '.' ) ) );

	$uploads = wp_upload_dir( current_time( 'mysql' ) );

	if ( ! ( $uploads && false === $uploads['error'] ) ) {
		if ( $strict ) {
			throw new \Exception( sprintf( 'Attachment import failed, uploads directory has a problem: %s', var_export( $uploads, true ) ) );
		}

		return 0;
	}

	$filename = wp_unique_filename( $uploads['path'], $filename );
	$new_file = $uploads['path'] . '/' . $filename;

	$file_data = @file_get_contents( $url );

	if ( ! $file_data ) {
		if ( $strict ) {
			throw new \Exception( 'Attachment import failed, file_get_contents had a problem' );
		}

		return 0;
	}

	file_put_contents( $new_file, $file_data );

	$stat  = stat( dirname( $new_file ) );
	$perms = $stat['mode'] & 0000666;
	@chmod( $new_file, $perms );

	$wp_filetype = wp_check_filetype( $filename );

	if ( ! $wp_filetype['type'] || ! $wp_filetype['ext'] ) {
		if ( $strict ) {
			throw new \Exception( sprintf( 'Attachment import failed, filetype check failed: %s', var_export( $wp_filetype, true ) ) );
		}

		return 0;
	}

	$attachment = array(
		'post_mime_type' => $wp_filetype['type'],
		'guid'           => $uploads['url'] . '/' . $filename,
		'post_parent'    => null,
		'post_title'     => $title,
		'post_content'   => '',
	);

	$attachment_id = wp_insert_attachment( $attachment, $new_file, $post_parent );

	if ( is_wp_error( $attachment_id ) ) {
		if ( $strict ) {
			throw new \Exception( sprintf( 'Attachment import failed, wp_insert_attachment failed: %s', var_export( $attachment_id, true ) ) );
		}

		return 0;
	}

	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $new_file ) );

	if ( 0 < $post_parent && $featured ) {
		update_post_meta( $post_parent, '_thumbnail_id', $attachment_id );
	}

	return $attachment_id;
}

/**
 * Resize an image on if it doesn't exist.
 *
 * @param int          $attachment_id Attachment ID.
 * @param string|array $size          Size to be generated.
 *
 * @return boolean Image generation result.
 *
 * @since 2.7.23
 */
function pods_maybe_image_resize( $attachment_id, $size ) {
	if ( 'full' !== $size ) {
		$full = wp_get_attachment_image_src( $attachment_id, 'full' );

		if ( ! empty( $full[0] ) ) {
			$size = pods_parse_image_size( $size );

			$src = wp_get_attachment_image_src( $attachment_id, $size );

			if ( empty( $src[0] ) || $full[0] == $src[0] ) {
				return pods_image_resize( $attachment_id, $size );
			}
		}
	}

	// No resize needed.
	return true;
}

/**
 * Resize an image on demand.
 *
 * @param int          $attachment_id Attachment ID.
 * @param string|array $size          Size to be generated.
 *
 * @return boolean Image generation result.
 *
 * @since 2.3.0
 */
function pods_image_resize( $attachment_id, $size ) {
	$size_data = array();

	if ( ! is_array( $size ) ) {
		// Basic image size string
		global $wp_image_sizes;

		if ( isset( $wp_image_sizes[ $size ] ) && ! empty( $wp_image_sizes[ $size ] ) ) {
			// Registered image size
			$size_data = $wp_image_sizes[ $size ];
		} elseif ( preg_match( '/[0-9]+x[0-9]+/', $size ) || preg_match( '/[0-9]+x[0-9]+x[0-1]/', $size ) ) {
			// Custom on-the-fly image size
			$size = explode( 'x', $size );

			$size_data = array(
				'width'  => (int) $size[0],
				'height' => (int) $size[1],
				'crop'   => (int) ( isset( $size[2] ) ? $size[2] : 1 ),
			);

			$size = $size_data['width'] . 'x' . $size_data['height'];
		}
	} elseif ( 2 <= count( $size ) ) {
		// Image size array
		if ( isset( $size['width'] ) ) {
			$size_data = $size;
		} else {
			$size_data = array(
				'width'  => (int) $size[0],
				'height' => (int) $size[1],
				'crop'   => (int) ( isset( $size[2] ) ? $size[2] : 1 ),
			);
		}

		$size = $size_data['width'] . 'x' . $size_data['height'];
	}//end if

	if ( empty( $size_data ) ) {
		return false;
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';

	$attachment = get_post( $attachment_id );
	$file       = get_attached_file( $attachment_id );

	if ( $file && file_exists( $file ) ) {
		$metadata = wp_get_attachment_metadata( $attachment_id );

		if ( ! empty( $metadata ) && preg_match( '!^image/!', get_post_mime_type( $attachment ) ) && file_is_displayable_image( $file ) ) {
			$editor = wp_get_image_editor( $file );

			if ( ! is_wp_error( $editor ) ) {
				$metadata['sizes'] = array_merge( $metadata['sizes'], $editor->multi_resize( array( $size => $size_data ) ) );

				wp_update_attachment_metadata( $attachment_id, $metadata );

				return true;
			}
		}
	}

	return false;
}

/**
 * Output an audio field as a video player.
 *
 * @uses  wp_audio_shortcode()
 *
 * @since 2.5.0
 *
 * @param string|array|int $url  The URL string, an array of post information, or an attachment ID.
 * @param bool|array       $args Optional. Additional arguments to pass to wp_audio_shortcode().
 *
 * @return string
 */
function pods_audio( $url, $args = false ) {
	// Support arrays.
	if ( is_array( $url ) ) {
		$url = pods_v( 'ID', $url );
	}

	// Support IDs.
	if ( is_numeric( $url ) ) {
		$url = wp_get_attachment_url( (int) $url );
	}

	if ( empty( $url ) || ! is_string( $url ) ) {
		return '';
	}

	$audio_args = array(
		'src' => $url,
	);

	if ( is_array( $args ) ) {
		$audio_args = array_merge( $audio_args, $args );
	}

	return wp_audio_shortcode( $audio_args );
}

/**
 * Output a video field as a video player.
 *
 * @uses  wp_video_shortcode()
 *
 * @since 2.5.0
 *
 * @param string|array|int $url  The URL string, an array of post information, or an attachment ID.
 * @param bool|array       $args Optional. Additional arguments to pass to wp_video_shortcode().
 *
 * @return string
 */
function pods_video( $url, $args = false ) {
	// Support arrays.
	if ( is_array( $url ) ) {
		$url = pods_v( 'ID', $url );
	}

	// Support IDs.
	if ( is_numeric( $url ) ) {
		$url = wp_get_attachment_url( (int) $url );
	}

	if ( empty( $url ) || ! is_string( $url ) ) {
		return '';
	}

	$video_args = array(
		'src' => $url,
	);

	if ( is_array( $args ) ) {
		$video_args = array_merge( $video_args, $args );
	}

	return wp_video_shortcode( $video_args );
}

/**
 * Get the image URL for a post for a specific pod field.
 *
 * @since 2.7.28
 *
 * @param string $field_name The field name.
 * @param string $size       The image size to use.
 * @param int    $default    The default image ID to use if not found.
 *
 * @return string The image URL for a post for a specific pod field.
 */
function pods_image_url_for_post( $field_name, $size = 'full', $default = 0 ) {
	// pods_field() will auto-detect the post type / post ID.
	$value = pods_field( null, null, $field_name, true );

	// No value found.
	if ( empty( $value ) ) {
		if ( $default ) {
			// Maybe return default if it's set.
			return pods_image_url( $default, $size );
		} else {
			// No value, no default to show.
			return '';
		}
	}

	if ( is_numeric( $value ) ) {
		$attachment_id = $value;
	} elseif ( is_array( $value ) && isset( $value['ID'] ) ) {
		$attachment_id = $value['ID'];
	} elseif ( $default ) {
		// Maybe return default if it's set.
		return pods_image_url( $default, $size );
	} else {
		// Unexpected value, no default to show.
		return '';
	}

	return pods_image_url( $attachment_id, $size, $default );
}

/**
 * Get the image HTML for a post for a specific pod field.
 *
 * @since 2.7.28
 *
 * @param string $field_name The field name.
 * @param string $size       The image size to use.
 * @param int    $default    The default image ID to use if not found.
 *
 * @return string The image HTML for a post for a specific pod field.
 */
function pods_image_for_post( $field_name, $size = 'full', $default = 0 ) {
	// pods_field() will auto-detect the post type / post ID.
	$value = pods_field( null, null, $field_name, true );

	// No value found.
	if ( empty( $value ) ) {
		if ( $default ) {
			// Maybe return default if it's set.
			return pods_image( $default, $size );
		} else {
			// No value, no default to show.
			return '';
		}
	}

	if ( is_numeric( $value ) ) {
		$attachment_id = $value;
	} elseif ( is_array( $value ) && isset( $value['ID'] ) ) {
		$attachment_id = $value['ID'];
	} elseif ( $default ) {
		// Maybe return default if it's set.
		return pods_image( $default, $size );
	} else {
		// Unexpected value, no default to show.
		return '';
	}

	return pods_image( $attachment_id, $size, $default );
}
