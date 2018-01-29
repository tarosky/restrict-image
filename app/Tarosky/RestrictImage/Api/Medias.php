<?php

namespace Tarosky\RestrictImage\Api;


use Tarosky\RestrictImage;
use Tarosky\RestrictImage\Pattern\AbstractApi;

/**
 * Media API
 *
 * @package Tarosky\RestrictImage\Api
 */
class Medias extends AbstractApi {
	
	/**
	 * Get arguments.
	 *
	 * @param string $http_method HTTP method.
	 *
	 * @return array
	 */
	protected function get_args( $http_method ) {
		switch ( $http_method ) {
			case 'GET':
				return array_merge( parent::get_args( $http_method ), [
					'paged' => [
						'default'           => 1,
						'sanitize_callback' => function ( $var ) {
							return max( 1, (int) $var );
						},
					],
					'id'    => [
						'default'           => 0,
						'sanitize_callback' => function ( $var ) {
							return (int) $var;
						},
					],
				] );
				break;
			case 'DELETE':
				return array_merge( parent::get_args( $http_method ), [
					'attachment' => [
						'required' => true,
						'validate_callback' => [ $this, 'is_numeric' ],
					],
				] );
				break;
		}
	}
	
	/**
	 * Get media list.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_get( \WP_REST_Request $request ) {
		$post_arr = [
			'post_type' => 'attachment',
			'paged'     => $request->get_param( 'page' ),
			'author'    => get_current_user_id(),
			'suppress_filters' => false,
			'meta_query' => [
				[
					'key'   => '_taroimg_key',
					'value' => $request->get_param( 'key' ),
				],
			],
		];
		/**
		 * taroimg_get_image_args
		 *
		 * @param array            $post_arr
		 * @param \WP_REST_Request $request
		 */
		$post_arr = apply_filters( 'taroimg_get_image_args', $post_arr, $request );
		return new \WP_REST_Response( [
			'limit' => RestrictImage::get_setting( $request->get_param( 'key' ), 'limit' ),
			'media' => array_map( [ $this->model, 'map' ], get_posts( $post_arr ) ),
		] );
	}
	
	/**
	 * Delete specified media.
	 *
	 * @param \WP_REST_Request $request
	 * @return array|\WP_REST_Response|\WP_Error
	 */
	public function handle_delete( \WP_REST_Request $request ) {
		$attachment = get_post( $request->get_param( 'attachment' ) );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return new \WP_Error( 'image_not_found', __( 'Attachment file not found.', 'taroimg' ), [
				'status' => 404,
			] );
		}
		if ( get_current_user_id() != $attachment->post_author || ! $this->user_can_delete( $request->get_param( 'key' ), $attachment, get_current_user_id() ) ) {
			return new \WP_Error( 'not_allowed', __( 'You have no permission to delete this file.', 'taroimg' ), [
				'status' => 403,
			] );
		}
		// Delete attachment.
		$result = wp_delete_attachment( $attachment->ID, true );
		if ( ! $result ) {
			return new \WP_Error( 'failed_to_delete', __( 'Failed to delete file.', 'taroimg' ), [
				'status' => 500,
			] );
		}
		return [
			'id'      => (int) $attachment->ID,
			'success' => true,
			'message' => __( 'Attachment file successfully deleted.', 'taroimg' ),
		];
	}
	
	/**
	 * Dose user have capability?
	 *
	 * @param string   $key
	 * @param \WP_Post $attachment
	 * @param int      $user_id
	 *
	 * @return bool
	 */
	protected function user_can_delete( $key, $attachment, $user_id ) {
		$can = true;
		
		/**
		 * taroimg_delete_capability
		 *
		 * Filter delete capability for attachment.
		 * @param string   $key
		 * @param \WP_Post $attachment
		 * @param int      $user_id
		 * @return bool
		 */
		return apply_filters( 'taroimg_delete_capability', true, $key, $attachment, $user_id );
	}
}
