<?php

namespace Tarosky\RestrictImage;


use Hametuha\Pattern\Singleton;

/**
 * Model file for resticted media.
 *
 * @package Tarosky\RestrictImage
 */
class Model extends Singleton {
	
	/**
	 * Get attachment object
	 *
	 * @param int|null|\WP_Post $attachment
	 *
	 * @return mixed
	 */
	public function map( $attachment = null ) {
		$attachment = get_post( $attachment );
		$return = [];
		foreach ( [
			'ID' => 'id',
			'post_title' => 'title',
			'post_published' => 'published',
			'post_modified'  => 'modified',
			'guid' => 'url',
			'post_mime_type' => 'mime',
			'post_status' => 'status',
			'menu_order' => 'order',
		] as $key => $new_key ) {
			$value = $attachment->{$key};
			switch ( $key ) {
				case 'ID':
				case 'post_author':
				case 'post_parent':
				case 'order':
					$value = (int) $value;
					break;
				default:
					// Do nothing.
					break;
			}
			$return[ $new_key ] = $value;
		}
		$return['name'] = basename( $attachment->guid );
		if ( false !== strpos( $attachment->post_mime_type, 'image/' ) ){
			$return['thumbnail'] = wp_get_attachment_image_url( $attachment->ID );
		} else {
			$return['thumbnail'] = '';
		}
		return $return;
	}
}
