<?php
/**
 * Filter Application Logic
 *
 * This class handles the core functionality of applying filters to WordPress content.
 *
 * @since      1.0.0
 * @package    FilterFlex
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FilterFlex_Filter_Application {

    /**
     * The filterable elements.
     *
     * @var array
     */
    private $filterable_elements = [];

    /**
     * The available output tags.
     *
     * @var array
     */
    private $available_tags = [];

    /**
     * The available transformations.
     *
     * @var array
     */
    private $available_transformations = [];

    /**
     * Constructor.
     */
    public function __construct() {
        $this->init_filterable_elements();
        $this->init_available_tags();
        $this->init_available_transformations();
        $this->register_hooks();
    }

    /**
     * Initialize the filterable elements.
     *
     * @return void
     */
    private function init_filterable_elements() {
        $default_elements = [
            'the_title' => [
                'label' => __( 'Post Title', 'filterflex' ),
                'callback' => [ $this, 'filter_title' ],
                'priority' => 10,
                'args' => 2,
            ],
            'the_content' => [
                'label' => __( 'Post Content', 'filterflex' ),
                'callback' => [ $this, 'filter_content' ],
                'priority' => 10,
                'args' => 1,
            ],
            'get_the_excerpt' => [
                'label' => __( 'Post Excerpt', 'filterflex' ),
                'callback' => [ $this, 'filter_excerpt' ],
                'priority' => 10,
                'args' => 1,
            ],
            'wp_list_categories' => [
                'label' => __( 'Category List', 'filterflex' ),
                'callback' => [ $this, 'filter_category_list' ],
                'priority' => 10,
                'args' => 1,
            ],
        ];

        // Allow plugins/themes to add custom filterable elements
        $this->filterable_elements = apply_filters( 'filterflex_filterable_elements', $default_elements );
    }

    /**
     * Initialize the available output tags.
     *
     * @return void
     */
    private function init_available_tags() {
        $default_tags = [
            '{filtered_element}' => [
                'label' => __( 'Filtered Element', 'filterflex' ),
                'callback' => [ $this, 'process_filtered_element_tag' ],
            ],
            '{categories}' => [
                'label' => __( 'Categories', 'filterflex' ),
                'callback' => [ $this, 'process_categories_tag' ],
            ],
            '{tags}' => [
                'label' => __( 'Tags', 'filterflex' ),
                'callback' => [ $this, 'process_tags_tag' ],
            ],
            '{author}' => [
                'label' => __( 'Author', 'filterflex' ),
                'callback' => [ $this, 'process_author_tag' ],
            ],
            '{date}' => [
                'label' => __( 'Date', 'filterflex' ),
                'callback' => [ $this, 'process_date_tag' ],
            ],
            '{post_id}' => [
                'label' => __( 'Post ID', 'filterflex' ),
                'callback' => [ $this, 'process_post_id_tag' ],
            ],
            '{custom_field}' => [ // Note: value is just {custom_field}, not {custom_field:key}
                'label' => __( 'Custom Field', 'filterflex' ),
                // 'callback' => null, // or remove this line. It's handled directly.
                'has_meta' => true // A flag for the JS builder to know it needs extra input for 'key'
            ],
        ];

        // Allow plugins/themes to add custom output tags
        $this->available_tags = apply_filters( 'filterflex_available_tags', $default_tags );
    }

    /**
     * Initialize the available transformations.
     *
     * @return void
     */
    private function init_available_transformations() {
        $default_transformations = [
            'search_replace' => [
                'label' => __( 'Search & Replace', 'filterflex' ),
                'callback' => [ $this, 'transform_search_replace' ],
            ],
            'uppercase' => [
                'label' => __( 'Convert to Uppercase', 'filterflex' ),
                'callback' => [ $this, 'transform_uppercase' ],
            ],
            'lowercase' => [
                'label' => __( 'Convert to Lowercase', 'filterflex' ),
                'callback' => [ $this, 'transform_lowercase' ],
            ],
            'trim_whitespace' => [
                'label' => __( 'Trim Whitespace', 'filterflex' ),
                'callback' => [ $this, 'transform_trim_whitespace' ],
            ],
            'limit_words' => [
                'label' => __( 'Limit Words', 'filterflex' ),
                'callback' => [ $this, 'transform_limit_words' ],
            ],
        ];

        // Allow plugins/themes to add custom transformations
        $this->available_transformations = apply_filters( 'filterflex_available_transformations', $default_transformations );
    }

    /**
     * Register hooks for all filterable elements.
     *
     * @return void
     */
    public function register_hooks() {
        foreach ( $this->filterable_elements as $hook => $element ) {
            add_filter( $hook, $element['callback'], $element['priority'], $element['args'] );
        }
    }

    /**
     * Filter the title.
     *
     * @param string $title The post title.
     * @param int    $post_id The post ID.
     * @return string The filtered title.
     */
    public function filter_title( $title, $post_id = 0 ) {
        return $this->apply_filters_to_element( 'the_title', $title, $post_id );
    }

    /**
     * Filter the content.
     *
     * @param string $content The post content.
     * @return string The filtered content.
     */
    public function filter_content( $content ) {
        return $this->apply_filters_to_element( 'the_content', $content );
    }

    /**
     * Filter the excerpt.
     *
     * @param string $excerpt The post excerpt.
     * @return string The filtered excerpt.
     */
    public function filter_excerpt( $excerpt ) {
        return $this->apply_filters_to_element( 'get_the_excerpt', $excerpt );
    }

    /**
     * Filter the category list.
     *
     * @param string $list The category list.
     * @return string The filtered category list.
     */
    public function filter_category_list( $list ) {
        return $this->apply_filters_to_element( 'wp_list_categories', $list );
    }

    /**
     * Apply filters to an element.
     *
     * @param string $hook The filter hook.
     * @param string $content The content to filter.
     * @param int    $post_id The post ID (optional).
     * @return string The filtered content.
     */
    private function apply_filters_to_element( $hook, $content, $post_id = 0 ) {
        // If no post ID is provided, try to get it from the current post
        if ( ! $post_id ) {
            $post_id = get_the_ID();
        }

        // If still no post ID, return the content unmodified
        if ( ! $post_id ) {
            return $content;
        }

        // Get all active filters for this hook
        $filters = $this->get_active_filters( $hook );

        // If no filters, return the content unmodified
        if ( empty( $filters ) ) {
            return $content;
        }

        // Sort filters by priority
        usort( $filters, function( $a, $b ) {
            $a_priority = get_post_meta( $a->ID, '_filterflex_priority', true );
            $b_priority = get_post_meta( $b->ID, '_filterflex_priority', true );
            
            // Default to 10 if not set
            $a_priority = $a_priority ? intval( $a_priority ) : 10;
            $b_priority = $b_priority ? intval( $b_priority ) : 10;
            
            return $b_priority - $a_priority; // Higher priority first
        } );

        // Apply each filter
        $filtered_content = $content;
        foreach ( $filters as $filter ) {
            // Check if the filter applies to the current context
            if ( ! $this->filter_applies_to_context( $filter->ID ) ) {
                continue;
            }

            // Get the output pattern
            $output_config = get_post_meta( $filter->ID, '_filterflex_output_config', true );
            if ( ! is_array( $output_config ) || empty( $output_config['pattern'] ) ) {
                continue;
            }

            // Process the output pattern
            $output = $this->process_output_pattern( $output_config['pattern'], $filtered_content, $post_id );

            // Apply transformations
            $transformations = get_post_meta( $filter->ID, '_filterflex_transformations', true );
            if ( is_array( $transformations ) && ! empty( $transformations ) ) {
                $output = $this->apply_transformations( $output, $transformations );
            }

            // Replace the content with the processed output
            $filtered_content = $output;
        }

        return $filtered_content;
    }

    /**
     * Get all active filters for a specific hook.
     *
     * @param string $hook The filter hook.
     * @return array An array of filter post objects.
     */
    private function get_active_filters( $hook ) {
        // Check if we have cached filters
        $cache_key = 'filterflex_filters_' . $hook;
        $filters = wp_cache_get( $cache_key, 'filterflex' );

        if ( false === $filters ) {
            // Query for filters
            $args = [
                'post_type'      => 'filterflex_filter',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'meta_query'     => [
                    [
                        'key'   => '_filterflex_filterable_element',
                        'value' => $hook,
                    ],
                ],
            ];

            $query = new WP_Query( $args );
            $filters = $query->posts;

            // Cache the results
            wp_cache_set( $cache_key, $filters, 'filterflex', 5 * MINUTE_IN_SECONDS );
        }

        return $filters;
    }

    /**
     * Check if a filter applies to the current context.
     *
     * @param int $filter_id The filter post ID.
     * @return bool True if the filter applies, false otherwise.
     */
    private function filter_applies_to_context( $filter_id ) {
        // Get the "apply area" setting
        $apply_area = get_post_meta( $filter_id, '_filterflex_apply_area', true );
        // Default to 'frontend' if not set or empty
        if ( empty( $apply_area ) ) {
            $apply_area = 'frontend'; 
        }

        // Check if the filter should apply based on the area
        if ( 'admin' === $apply_area && ! is_admin() ) {
            return false; // Admin only, but current context is not admin
        }
        if ( 'frontend' === $apply_area && is_admin() ) {
            return false; // Frontend only, but current context is admin
        }
        // If 'both', or if the area matches, proceed to location rules

        // Get the location rules
        $location_rules = get_post_meta( $filter_id, '_filterflex_location_rules', true );
        
        // If no location rules, and area check passed, apply the filter
        if ( ! is_array( $location_rules ) || empty( $location_rules ) ) {
            return true;
        }

        // Check each rule group (OR logic between groups)
        foreach ( $location_rules as $rule_group ) {
            // If the group is empty, skip it
            if ( ! is_array( $rule_group ) || empty( $rule_group ) ) {
                continue;
            }

            // Check each rule in the group (AND logic within a group)
            $group_matches = true;
            foreach ( $rule_group as $rule ) {
                // If the rule is invalid, skip it
                if ( ! is_array( $rule ) || ! isset( $rule['param'], $rule['operator'], $rule['value'] ) ) {
                    continue;
                }

                // Check if the rule matches
                $rule_matches = $this->rule_matches( $rule['param'], $rule['operator'], $rule['value'] );
                
                // If any rule in the group doesn't match, the group doesn't match
                if ( ! $rule_matches ) {
                    $group_matches = false;
                    break;
                }
            }

            // If any group matches, the filter applies
            if ( $group_matches ) {
                return true;
            }
        }

        // If no group matches, the filter doesn't apply
        return false;
    }

    /**
     * Check if a rule matches the current context.
     *
     * @param string $param The rule parameter.
     * @param string $operator The rule operator.
     * @param string $value The rule value.
     * @return bool True if the rule matches, false otherwise.
     */
    private function rule_matches( $param, $operator, $value ) {
        $actual_value = $this->get_param_value( $param );
        
        // If the parameter is not supported, the rule doesn't match
        if ( null === $actual_value ) {
            return false;
        }

        // Check the operator
        switch ( $operator ) {
            case '==':
                return $actual_value == $value;
            case '!=':
                return $actual_value != $value;
            default:
                return false;
        }
    }

    /**
     * Get the actual value of a parameter in the current context.
     *
     * @param string $param The parameter.
     * @return mixed The value, or null if the parameter is not supported.
     */
    private function get_param_value( $param ) {
        switch ( $param ) {
            case 'post_type':
                return get_post_type();
            case 'user_role':
                $user = wp_get_current_user();
                return ! empty( $user->roles ) ? $user->roles[0] : '';
            case 'page_type':
                if ( is_front_page() ) {
                    return 'front_page';
                } elseif ( is_home() ) {
                    return 'home';
                } elseif ( is_singular() ) {
                    return 'singular';
                } elseif ( is_archive() ) {
                    return 'archive';
                } elseif ( is_search() ) {
                    return 'search';
                } elseif ( is_404() ) {
                    return '404';
                }
                return '';
            case 'page_id':
                return is_singular() ? get_the_ID() : '';
            case 'taxonomy':
                $taxonomies = get_object_taxonomies( get_post_type() );
                return ! empty( $taxonomies ) ? $taxonomies[0] : '';
            case 'term_id':
                $terms = get_the_terms( get_the_ID(), get_query_var( 'taxonomy' ) );
                return ! empty( $terms ) ? $terms[0]->term_id : '';
            default:
                // Allow custom parameters via filter
                return apply_filters( 'filterflex_param_value', null, $param );
        }
    }

    /**
     * Process an output pattern (JSON string).
     *
     * @param string $pattern_json     The JSON string representing the output pattern.
     * @param string $original_content The original content being filtered (used for {filtered_element}).
     * @param int    $post_id          The post ID for context.
     * @return string The processed output string.
     */
    private function process_output_pattern( $pattern_json, $original_content, $post_id ) {
        $output_string = '';
        $pattern_data = json_decode( $pattern_json, true );

        // Check if JSON decoding was successful and if it's an array
        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $pattern_data ) ) {
            // Fallback or error handling:
            // Option 1: Return original content if pattern is invalid
            // return $original_content;
            // Option 2: Log an error and return an empty string or a specific error message
            error_log( 'FilterFlex: Invalid output pattern JSON for post ID ' . $post_id . '. Pattern: ' . $pattern_json );
            // Option 3: Attempt to use original content if the special tag {filtered_element} was the intent.
            // This is a basic fallback if the pattern was simply the filtered element.
            if ( strpos( $pattern_json, '{filtered_element}' ) !== false ) {
                return $original_content;
            }
            return ''; // Or return original_content, depending on desired strictness
        }

        foreach ( $pattern_data as $item ) {
            if ( ! is_array( $item ) || ! isset( $item['type'], $item['value'] ) ) {
                // Skip malformed items
                continue;
            }

            if ( $item['type'] === 'text' ) {
                // For text, append its value.
                // Consider if this text should be escaped here or if it's assumed to be safe
                // from the builder. If it can contain user-inputted HTML, escape it.
                // For now, let's assume it's pre-sanitized or plain text from the builder.
                $output_string .= $item['value'];
            } elseif ( $item['type'] === 'separator' ) {
                // For separators, convert placeholder or append value.
                $sep_val = $item['value'];
                if ( $sep_val === '__{{SPACE}}__' ) {
                    $output_string .= ' '; // Convert placeholder to a regular space
                } else {
                    $output_string .= $sep_val; // Other separators as is
                }
            } elseif ( $item['type'] === 'tag' ) {
                // For tags, process them.
                // The $item itself (which includes type, value, and potentially meta) is passed.
                $output_string .= $this->process_tag_item( $item, $original_content, $post_id );
            }
        }

        return $output_string;
    }

    /**
     * Process a single tag item from the structured pattern.
     *
     * @param array  $tag_item         The tag item array (e.g., ['type' => 'tag', 'value' => '{tag_name}', 'meta' => ...]).
     * @param string $original_content The original content (for {filtered_element}).
     * @param int    $post_id          The post ID for context.
     * @return string The processed value for the tag.
     */
    private function process_tag_item( $tag_item, $original_content, $post_id ) {
        $tag_placeholder = $tag_item['value']; // e.g., "{categories}", "{custom_field}"

        // Handle the {filtered_element} tag directly as it uses the $original_content
        if ( $tag_placeholder === '{filtered_element}' ) {
            return $original_content;
        }

        // Handle {custom_field} specifically because it needs the meta key
        if ( $tag_placeholder === '{custom_field}' ) {
            if ( isset( $tag_item['meta']['key'] ) && ! empty( $tag_item['meta']['key'] ) ) {
                $field_key = $tag_item['meta']['key'];
                return esc_html( get_post_meta( $post_id, $field_key, true ) );
            }
            return ''; // No key provided for custom field
        }

        // Handle dynamic {taxonomy:slug} tags
        if ( preg_match( '/^\{taxonomy:(.+)\}$/', $tag_placeholder, $matches ) ) {
            $taxonomy_slug = $matches[1];
            if ( taxonomy_exists( $taxonomy_slug ) ) {
                $terms = get_the_terms( $post_id, $taxonomy_slug );
                if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                    $term_names = wp_list_pluck( $terms, 'name' );
                    return esc_html( implode( ', ', $term_names ) );
                }
            }
            return ''; // Taxonomy doesn't exist or no terms found
        }

        // Check if the tag (e.g., "{categories}") is registered in our $available_tags (for default tags)
        if ( isset( $this->available_tags[ $tag_placeholder ] ) && is_callable( $this->available_tags[ $tag_placeholder ]['callback'] ) ) {
            // Call the registered callback for this tag
            return call_user_func( $this->available_tags[ $tag_placeholder ]['callback'], $original_content, $post_id );
        }

        // If the tag is not registered or callable, return the placeholder itself or an empty string
        // Returning the placeholder can help debug if a tag is misspelled or not registered.
        // return $tag_placeholder;
        return ''; // Or return empty string to not show unknown tags
    }

    /**
     * Process the filtered element tag.
     *
     * @param string $original_content The original content.
     * @param int    $post_id The post ID.
     * @return string The processed tag.
     */
    public function process_filtered_element_tag( $original_content, $post_id ) {
        return $original_content;
    }

    /**
     * Process the categories tag.
     *
     * @param string $original_content The original content.
     * @param int    $post_id The post ID.
     * @return string The processed tag.
     */
    public function process_categories_tag( $original_content, $post_id ) {
        $categories = get_the_category( $post_id );
        if ( empty( $categories ) ) {
            return '';
        }

        $category_names = wp_list_pluck( $categories, 'name' );
        return implode( ', ', $category_names );
    }

    /**
     * Process the tags tag.
     *
     * @param string $original_content The original content.
     * @param int    $post_id The post ID.
     * @return string The processed tag.
     */
    public function process_tags_tag( $original_content, $post_id ) {
        $tags = get_the_tags( $post_id );
        if ( empty( $tags ) ) {
            return '';
        }

        $tag_names = wp_list_pluck( $tags, 'name' );
        return implode( ', ', $tag_names );
    }

    /**
     * Process the author tag.
     *
     * @param string $original_content The original content.
     * @param int    $post_id The post ID.
     * @return string The processed tag.
     */
    public function process_author_tag( $original_content, $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return '';
        }

        $author_id = $post->post_author;
        return get_the_author_meta( 'display_name', $author_id );
    }

    /**
     * Process the date tag.
     *
     * @param string $original_content The original content.
     * @param int    $post_id The post ID.
     * @return string The processed tag.
     */
    public function process_date_tag( $original_content, $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return '';
        }

        return get_the_date( '', $post_id );
    }

    /**
     * Process the post ID tag.
     *
     * @param string $original_content The original content.
     * @param int    $post_id The post ID.
     * @return string The processed tag.
     */
    public function process_post_id_tag( $original_content, $post_id ) {
        return (string) $post_id;
    }

    /**
     * Apply transformations to content.
     *
     * @param string $content The content to transform.
     * @param array  $transformations The transformations to apply.
     * @return string The transformed content.
     */
    private function apply_transformations( $content, $transformations ) {
        foreach ( $transformations as $transformation ) {
            // Skip invalid transformations
            if ( ! is_array( $transformation ) || empty( $transformation['type'] ) ) {
                continue;
            }

            // Check if the transformation is registered
            $type = $transformation['type'];
            if ( isset( $this->available_transformations[ $type ] ) && is_callable( $this->available_transformations[ $type ]['callback'] ) ) {
                $content = call_user_func( $this->available_transformations[ $type ]['callback'], $content, $transformation );
            }
        }

        return $content;
    }

    /**
     * Transform content with search and replace.
     *
     * @param string $content The content to transform.
     * @param array  $transformation The transformation parameters.
     * @return string The transformed content.
     */
    public function transform_search_replace( $content, $transformation ) {
        if ( ! isset( $transformation['search'] ) || ! isset( $transformation['replace'] ) ) {
            return $content;
        }

        $search = $transformation['search'];
        $replace = $transformation['replace'];

        if ( empty(trim( $content )) || $search === '' ) {
            return $content;
        }

        $doc = new DOMDocument();
        // Explicitly set encoding for the document, useful for saveHTML
        $doc->encoding = 'UTF-8'; 
        
        $previous_libxml_error_use = libxml_use_internal_errors(true);

        // Convert input content to HTML entities to help loadHTML interpret characters correctly.
        // Wrap content in a specific marker div for easier and safer extraction.
        $marker_id = 'filterflex-content-wrapper-' . uniqid();
        $html_fragment = mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8');
        
        // LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD are important for fragment processing.
        if ( ! $doc->loadHTML('<div id="' . $marker_id . '">' . $html_fragment . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD) ) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous_libxml_error_use);
            return $content; 
        }
        
        $xpath = new DOMXPath( $doc );
        // Target text nodes only within our specific wrapper div.
        $textNodes = $xpath->query( '//div[@id="' . $marker_id . '"]//text()' );

        if ($textNodes === false) { // Check if XPath query itself failed
            libxml_clear_errors();
            libxml_use_internal_errors($previous_libxml_error_use);
            return $content;
        }

        foreach ( $textNodes as $textNode ) {
            if ( $textNode->parentNode && ($textNode->parentNode->nodeName === 'script' || $textNode->parentNode->nodeName === 'style') ) {
                continue;
            }

            $originalText = $textNode->nodeValue; // nodeValue is UTF-8 decoded text
            $modifiedText = str_replace( $search, $replace, $originalText );
            
            if ($originalText !== $modifiedText) {
                // Create a new text node with the modified text and replace the old one.
                // This can sometimes be more robust than directly setting nodeValue if there are complex characters.
                // However, direct assignment $textNode->nodeValue = $modifiedText; is usually fine.
                // For now, let's stick to direct assignment as it's simpler.
                $textNode->nodeValue = $modifiedText;
            }
        }

        $wrapper_node = $doc->getElementById($marker_id);
        $processed_html = '';

        if ($wrapper_node && $wrapper_node->hasChildNodes()) {
            foreach ($wrapper_node->childNodes as $child_node) {
                // saveHTML on a node should produce UTF-8 if $doc->encoding was set.
                $processed_html .= $doc->saveHTML($child_node);
            }
        } elseif ($wrapper_node) { // Wrapper exists but is empty
            $processed_html = '';
        } else { // Fallback if our wrapper div is not found
            libxml_clear_errors();
            libxml_use_internal_errors($previous_libxml_error_use);
            return $content; 
        }
        
        libxml_clear_errors();
        libxml_use_internal_errors($previous_libxml_error_use);

        return $processed_html;
    }

    /**
     * Transform content to uppercase.
     *
     * @param string $content The content to transform.
     * @param array  $transformation The transformation parameters.
     * @return string The transformed content.
     */
    public function transform_uppercase( $content, $transformation ) {
        return strtoupper( $content );
    }

    /**
     * Transform content to lowercase.
     *
     * @param string $content The content to transform.
     * @param array  $transformation The transformation parameters.
     * @return string The transformed content.
     */
    public function transform_lowercase( $content, $transformation ) {
        return strtolower( $content );
    }

    /**
     * Transform content by trimming whitespace.
     *
     * @param string $content The content to transform.
     * @param array  $transformation The transformation parameters.
     * @return string The transformed content.
     */
    public function transform_trim_whitespace( $content, $transformation ) {
        return trim( $content );
    }

    /**
     * Transform content by limiting words.
     *
     * @param string $content The content to transform.
     * @param array  $transformation The transformation parameters.
     * @return string The transformed content.
     */
    public function transform_limit_words( $content, $transformation ) {
        // Skip if limit is not set
        if ( ! isset( $transformation['limit'] ) ) {
            return $content;
        }

        $limit = intval( $transformation['limit'] );
        if ( $limit <= 0 ) {
            return $content;
        }

        $words = explode( ' ', $content );
        if ( count( $words ) <= $limit ) {
            return $content;
        }

        $words = array_slice( $words, 0, $limit );
        return implode( ' ', $words ) . '...';
    }

    /**
     * Get the filterable elements.
     *
     * @return array The filterable elements.
     */
    public function get_filterable_elements() {
        return $this->filterable_elements;
    }

    /**
     * Get the available output tags.
     *
     * @return array The available output tags.
     */
    public function get_available_tags() {
        return $this->available_tags;
    }

    /**
     * Get the available transformations.
     *
     * @return array The available transformations.
     */
    public function get_available_transformations() {
        return $this->available_transformations;
    }
}
