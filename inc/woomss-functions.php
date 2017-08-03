<?php





/**
 * Set uploaded image as attachment.
 *
 * @since 1.0
 * @param array $upload Upload information from wp_upload_bits.
 * @param int $id Post ID. Default to 0.
 * @return int Attachment ID
 */
function woomss_set_uploaded_image_as_attachment( $upload, $id = 0 ) {
	$info    = wp_check_filetype( $upload['file'] );
	$title = '';
	$content = '';

	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
		include_once( ABSPATH . 'wp-admin/includes/image.php' );
	}

	if ( $image_meta = wp_read_image_metadata( $upload['file'] ) ) {
		if ( trim( $image_meta['title'] ) && ! is_numeric( sanitize_title( $image_meta['title'] ) ) ) {
			$title = wc_clean( $image_meta['title'] );
		}
		if ( trim( $image_meta['caption'] ) ) {
			$content = wc_clean( $image_meta['caption'] );
		}
	}

	$attachment = array(
		'post_mime_type' => $info['type'],
		'guid'           => $upload['url'],
		'post_parent'    => $id,
		'post_title'     => $title,
		'post_content'   => $content,
	);

	$attachment_id = wp_insert_attachment( $attachment, $upload['file'], $id );
	if ( ! is_wp_error( $attachment_id ) ) {
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
	}

	return $attachment_id;
}

/**
 * Upload image from URL.
 *
 * @since 1.0
 * @param string $image_url
 * @param string $file_name
 * @return array|WP_Error Attachment data or error message.
 */
function woomss_upload_image_from_url( $image_url, $file_name = '' ) {
	if(empty($file_name)){
		$file_name  = basename( current( explode( '?', $image_url ) ) );
	}

	$parsed_url = @parse_url( $image_url );

	// Check parsed URL.
	if ( ! $parsed_url || ! is_array( $parsed_url ) ) {
		return new WP_Error( 'woomss_invalid_image_url', sprintf( 'Invalid URL %s.', $image_url ), array( 'status' => 400 ) );
	}

	// Ensure url is valid.
	$image_url = esc_url_raw( $image_url );

	// Get the file.
	$response = wp_safe_remote_get( $image_url, array(
		'timeout' => 10,
    'headers' => array(
        'Authorization' => 'Basic ' . base64_encode( get_option( 'woomss_login' ) . ':' . get_option( 'woomss_pass' ) )
    )
	));


	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'woomss_invalid_remote_image_url', sprintf( __( 'Error getting remote image %s.', 'woocommerce' ), $image_url ) . ' ' . sprintf( __( 'Error: %s.', 'woocommerce' ), $response->get_error_message() ), array( 'status' => 400 ) );
	} elseif ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return new WP_Error( 'woomss_invalid_remote_image_url', sprintf( __( 'Error getting remote image %s.', 'woocommerce' ), $image_url ), array( 'status' => 400 ) );
	}

	// Ensure we have a file name and type.
	$wp_filetype = wp_check_filetype( $file_name, wc_rest_allowed_image_mime_types() );

	if ( ! $wp_filetype['type'] ) {
		$headers = wp_remote_retrieve_headers( $response );
		if ( isset( $headers['content-disposition'] ) && strstr( $headers['content-disposition'], 'filename=' ) ) {
			$disposition = end( explode( 'filename=', $headers['content-disposition'] ) );
			$disposition = sanitize_file_name( $disposition );
			$file_name   = $disposition;
		} elseif ( isset( $headers['content-type'] ) && strstr( $headers['content-type'], 'image/' ) ) {
			$file_name = 'image.' . str_replace( 'image/', '', $headers['content-type'] );
		}
		unset( $headers );

		// Recheck filetype
		$wp_filetype = wp_check_filetype( $file_name, wc_rest_allowed_image_mime_types() );

		if ( ! $wp_filetype['type'] ) {
			return new WP_Error( 'woomss_invalid_image_type', __( 'Invalid image type.', 'woocommerce' ), array( 'status' => 400 ) );
		}
	}

	// Upload the file.
	$upload = wp_upload_bits( $file_name, '', wp_remote_retrieve_body( $response ) );

	if ( $upload['error'] ) {
		return new WP_Error( 'woomss_image_upload_error', $upload['error'], array( 'status' => 400 ) );
	}

	// Get filesize.
	$filesize = filesize( $upload['file'] );

	if ( 0 == $filesize ) {
		@unlink( $upload['file'] );
		unset( $upload );

		return new WP_Error( 'woomss_image_upload_file_error', __( 'Zero size file downloaded.', 'woocommerce' ), array( 'status' => 400 ) );
	}

	do_action( 'woomss_uploaded_image_from_url', $upload, $image_url );

	return $upload;
}
