<?php
/**
 * Core functions for the FilterFlex plugin.
 */

// Add any global functions here.

add_action( 'rest_api_init', 'filterflex_register_rest_routes' );

function filterflex_register_rest_routes() {
    register_rest_route( 'filterflex/v1', '/filters/save', [
        'methods'             => 'POST',
        'callback'            => 'filterflex_save_filter',
        'permission_callback' => 'filterflex_validate_user',
        'args'                => [
            'filterable_element' => [
                'validate_callback' => function( $param, $request, $key ) {
                    return is_string( $param ); // Should be string
                },
                'sanitize_callback' => 'sanitize_text_field',
                'required'          => true,
            ],
            'context_settings' => [
                'validate_callback' => function( $param, $request, $key ) {
                    return is_array( $param ); // Should be an array
                },
                'sanitize_callback' => 'rest_sanitize_array',  // Custom sanitize function
                'required'          => false,
            ],
            // Add more arguments and validation rules here
        ],
    ] );
} // End filterflex_register_rest_routes() - Ensure this brace closes the function

/**
 * Checks if the current user has permission to perform the requested action.
 *
 * @param WP_REST_Request $request The REST request.
 * @return bool True if the user has permission, false otherwise.
 */
function filterflex_validate_user(WP_REST_Request $request) {
    return current_user_can('manage_options'); // Or any other appropriate capability
}

/**
 * Handles the request to save a filter.
 *
 * @param WP_REST_Request $request The REST request.
 * @return WP_REST_Response The REST response.
 */
function filterflex_save_filter( WP_REST_Request $request ) {
    // Get parameters from the request
    $params = $request->get_params();

    // Validate the request
    if ( empty( $params['filterable_element'] ) ) {
        return new WP_Error( 'missing_filterable_element', __( 'Filterable element is required.', 'filterflex' ), [ 'status' => 400 ] );
    }

    // Sanitize and validate the data
    $filterable_element = sanitize_text_field( $params['filterable_element'] );
    $context_settings = isset( $params['context_settings'] ) ? rest_sanitize_array( $params['context_settings'] ) : [];

    // ... other data validation and sanitization ...
    if ( ! isset( $params['filter_name'] ) || empty( $params['filter_name'] )) {
        $title = __( 'New Filter', 'filterflex' );
    } else {
        $title = sanitize_text_field( $params['filter_name'] );
    }

    $post_id = wp_insert_post( [
        'post_type'   => 'filterflex_filter',
        'post_title'  => $title,
        'post_status' => 'publish',
    ] );

    if ( is_wp_error( $post_id ) ) {
        return new WP_REST_Response( $post_id, 500 );  // Return the WP_Error object
    }

    // Save the filter settings as post meta
    update_post_meta( $post_id, '_filterflex_filterable_element', $filterable_element );
    update_post_meta( $post_id, '_filterflex_context_settings', $context_settings );

    $transient_name = 'filterflex_transient_' . wp_generate_password( 12, false );
    set_transient( $transient_name, 'success', 300 );

    // Send a response back to the client
    return new WP_REST_Response( [ 'message' => __( 'Filter saved successfully!', 'filterflex' ), 'post_id' => $post_id, 'transient' => $transient_name ], 200 );
}

/**
 * Verifies the transient value.
 *
 * @param WP_REST_Request $request The REST request.
 * @return WP_REST_Response The REST response.
 */
function filterflex_verify_transient(WP_REST_Request $request) {
    $transient_name = $request->get_param('transient_name');

    if (empty($transient_name)) {
        return new WP_REST_Response(['success' => false, 'message' => 'Transient name is required.'], 400);
    }

    $transient_value = get_transient($transient_name);

    if ($transient_value === 'success') {
        delete_transient($transient_name); // Delete the transient after verification
        return new WP_REST_Response(['success' => true, 'data' => $transient_value], 200);
    } else {
        return new WP_REST_Response(['success' => false, 'message' => 'Transient verification failed.'], 400);
    }
} // End filterflex_verify_transient()

// AJAX handler hook (Ensure this is at the top level, outside any function)
add_action( 'wp_ajax_filterflex_get_location_values', 'filterflex_ajax_get_location_values' );

/**
 * AJAX handler to get values for a specific location rule parameter.
 */
function filterflex_ajax_get_location_values() {
    // Verify nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'filterflex_location_nonce' ) ) {
        wp_send_json_error( [ 'message' => __( 'Nonce verification failed.', 'filterflex' ) ], 403 );
    }

    // Check user capability
    if ( ! current_user_can( 'edit_posts' ) ) { // Use 'edit_posts' as a general capability check
        wp_send_json_error( [ 'message' => __( 'Permission denied.', 'filterflex' ) ], 403 );
    }

    $param = isset( $_POST['param'] ) ? sanitize_key( $_POST['param'] ) : '';

    if ( empty( $param ) ) {
        wp_send_json_error( [ 'message' => __( 'Parameter not specified.', 'filterflex' ) ], 400 );
    }

    $values = [];

    // Fetch values based on the parameter
    try {
        switch ( $param ) {
            case 'post_type':
                $post_types = get_post_types( [ 'public' => true ], 'objects' );
                $values = is_array($post_types) ? wp_list_pluck( $post_types, 'label', 'name' ) : [];
                break;
            case 'page_template':
                 $theme_templates = wp_get_theme()->get_page_templates();
                 $values = []; // Initialize as empty array
                 if ( ! empty( $theme_templates ) ) {
                     $values['default'] = apply_filters( 'default_page_template_title', __( 'Default Template' ), 'filterflex' );
                     foreach ( $theme_templates as $file => $name ) {
                         $values[ $file ] = $name;
                     }
                 }
                break;
            case 'page':
                $pages = get_posts( [ 'post_type' => 'page', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'post_status' => 'publish' ] );
                $values = is_array($pages) ? wp_list_pluck( $pages, 'post_title', 'ID' ) : [];
                break;
            case 'post':
                 $posts = get_posts( [ 'post_type' => 'post', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'post_status' => 'publish' ] );
                 $values = is_array($posts) ? wp_list_pluck( $posts, 'post_title', 'ID' ) : [];
                break;
            case 'post_category':
                $categories = get_categories( [ 'hide_empty' => false ] );
                $values = is_array($categories) ? wp_list_pluck( $categories, 'name', 'term_id' ) : [];
                break;
            case 'user_role':
                $roles = get_editable_roles();
                // wp_list_pluck needs an array of arrays/objects. get_editable_roles returns [role_key => [name => ..., capabilities => ...]]
                $values = [];
                if (is_array($roles)) {
                    foreach ($roles as $role_key => $role_data) {
                        if (isset($role_data['name'])) {
                            $values[$role_key] = $role_data['name'];
                        }
                    }
                }
                break;
            case 'page_type':
                $values = [
                    'front_page' => __( 'Front Page', 'filterflex' ),
                    'home'       => __( 'Blog Posts Index', 'filterflex' ),
                    'singular'   => __( 'Singular (Post, Page, CPT)', 'filterflex' ),
                    'archive'    => __( 'Archive (Category, Tag, Date, Author, CPT)', 'filterflex' ),
                    'search'     => __( 'Search Results', 'filterflex' ),
                    '404'        => __( '404 Not Found', 'filterflex' ),
                ];
                break;
            default:
                $values = apply_filters( 'filterflex_ajax_location_values', [], $param );
                break;
        }
    } catch (Exception $e) {
         // Log error maybe?
         wp_send_json_error( [ 'message' => 'Error fetching values: ' . $e->getMessage() ], 500 );
    }

    // Ensure $values is always an array before sending
    if (!is_array($values)) {
        $values = [];
    }

    wp_send_json_success( [ 'values' => $values ] );
}
