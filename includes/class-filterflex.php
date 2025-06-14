<?php
/**
 * Main FilterFlex plugin class.
 */
class FilterFlex {

    /**
     * Plugin version.
     *
     * @var string
     */
    public $version = FILTERFLEX_VERSION;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->includes();
        $this->init_hooks();
        if ( is_admin() ) {
            $this->init_admin_hooks();
        }
    }

    /**
     * Include any classes.
     *
     * @return void
     */
    public function includes() {
        require_once FILTERFLEX_PLUGIN_DIR . 'includes/class-filterflex-filter-application.php';
    }

    /**
     * The filter application instance.
     *
     * @var FilterFlex_Filter_Application
     */
    private $filter_application;

    /**
     * Hook into actions and filters.
     *
     * @return void
     */
    public function init_hooks() {
        add_action( 'init', [ $this, 'register_post_type' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        
        // Initialize the filter application
        $this->init_filter_application();
    }

    /**
     * Initialize the filter application.
     *
     * @return void
     */
    private function init_filter_application() {
        // Create an instance of the filter application class
        $filter_application = new FilterFlex_Filter_Application();
        
        // Store the instance for later use
        $this->filter_application = $filter_application;
    }

    /**
     * Add hooks related to admin functionality.
     */
    public function init_admin_hooks() {
        add_action( 'add_meta_boxes_filterflex_filter', [ $this, 'add_settings_metabox' ] );
        add_action( 'save_post_filterflex_filter', [ $this, 'save_settings_metabox' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'wp_ajax_filterflex_get_preview', [ $this, 'ajax_get_preview' ] );
        add_action( 'wp_ajax_filterflex_get_location_values', [ $this, 'ajax_get_location_values' ] );
        
        // Add admin menu for settings page
        add_action( 'admin_menu', [ $this, 'add_admin_menu_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );

        // Add custom columns to the FilterFlex post type listing
        add_filter( 'manage_filterflex_filter_posts_columns', [ $this, 'add_custom_columns' ] );
        add_action( 'manage_filterflex_filter_posts_custom_column', [ $this, 'render_custom_columns' ], 10, 2 );
        add_filter( 'manage_edit-filterflex_filter_sortable_columns', [ $this, 'make_custom_columns_sortable' ] );
        add_action( 'pre_get_posts', [ $this, 'custom_columns_orderby' ] );

        // Customize post status UI
        add_action( 'post_submitbox_misc_actions', [ $this, 'custom_status_toggle' ] );
        add_filter( 'display_post_states', [ $this, 'modify_post_states' ], 10, 2 );
        add_action( 'manage_posts_extra_tablenav', [ $this, 'display_no_filters_message' ], 10, 1 );
    }

    /**
     * Add admin menu page for FilterFlex settings.
     */
    public function add_admin_menu_settings_page() {
        add_submenu_page(
            'edit.php?post_type=filterflex_filter', // Parent slug (FilterFlex CPT)
            __( 'FilterFlex Settings', 'filterflex' ),    // Page title
            __( 'Settings', 'filterflex' ),             // Menu title
            'manage_options',                           // Capability
            'filterflex-settings',                      // Menu slug
            [ $this, 'render_settings_page' ]          // Callback function
        );
    }

    /**
     * Register plugin settings using the Settings API.
     */
    public function register_settings() {
        register_setting(
            'filterflex_settings_group', // Option group
            'filterflex_settings',       // Option name
            [ $this, 'sanitize_settings' ] // Sanitize callback
        );

        add_settings_section(
            'filterflex_taxonomy_settings_section', // ID
            __( 'Taxonomy Settings', 'filterflex' ), // Title
            null, // Callback for section description (optional)
            'filterflex-settings' // Page slug where this section will be shown
        );

        add_settings_field(
            'filterflex_enabled_taxonomies', // ID
            __( 'Enable Taxonomies for Output Tags', 'filterflex' ), // Title
            [ $this, 'render_enabled_taxonomies_field' ], // Callback to render the field
            'filterflex-settings', // Page slug
            'filterflex_taxonomy_settings_section' // Section ID
        );

        add_settings_section(
            'filterflex_uninstall_settings_section',
            __( 'Uninstall Settings', 'filterflex' ),
            null,
            'filterflex-settings'
        );
        add_settings_field(
            'filterflex_remove_data_on_uninstall',
            __( 'Remove all plugin data on uninstall', 'filterflex' ),
            [ $this, 'render_remove_data_on_uninstall_field' ],
            'filterflex-settings',
            'filterflex_uninstall_settings_section'
        );
    }

    /**
     * Sanitize settings.
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize_settings( $input ) {
        $new_input = [];
        if ( isset( $input['enabled_taxonomies'] ) && is_array( $input['enabled_taxonomies'] ) ) {
            $new_input['enabled_taxonomies'] = array_map( 'sanitize_key', $input['enabled_taxonomies'] );
        } else {
            $new_input['enabled_taxonomies'] = [];
        }
        $new_input['remove_data_on_uninstall'] = !empty($input['remove_data_on_uninstall']) ? 1 : 0;
        return $new_input;
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'FilterFlex Settings', 'filterflex' ); ?></h1>
            <?php wp_nonce_field( 'filterflex_admin_nonce' ); ?>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'filterflex_settings_group' ); // Output nonce, action, and option_page fields for a settings page.
                do_settings_sections( 'filterflex-settings' );  // Print out all settings sections added to a particular settings page.
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render the 'enabled_taxonomies' settings field.
     */
    public function render_enabled_taxonomies_field() {
        $options = get_option( 'filterflex_settings' );
        // Default to category and post_tag if the option hasn't been saved yet or is empty.
        $default_enabled = [ 'category', 'post_tag' ];
        if ( $options === false || !isset($options['enabled_taxonomies']) ) {
            // Option not set at all, or enabled_taxonomies key is missing
            $enabled_taxonomies = $default_enabled;
        } else {
            // Option is set, use its value. If it's an empty array from a previous save, respect that.
            $enabled_taxonomies = is_array($options['enabled_taxonomies']) ? $options['enabled_taxonomies'] : $default_enabled;
        }

        $taxonomies = get_taxonomies( [ 'public' => true ], 'objects' );
        // Exclude some common non-content taxonomies if desired
        $excluded_taxonomies = [ 'nav_menu', 'link_category', 'post_format' ]; 

        if ( empty( $taxonomies ) ) {
            esc_html_e( 'No public taxonomies found.', 'filterflex' );
            return;
        }
        
        echo '<ul>';
        foreach ( $taxonomies as $taxonomy ) {
            if ( in_array( $taxonomy->name, $excluded_taxonomies, true ) ) {
                continue;
            }
            echo '<li>';
            echo '<label>';
            echo '<input type="checkbox" name="filterflex_settings[enabled_taxonomies][]" value="' . esc_attr( $taxonomy->name ) . '"';
            checked( in_array( $taxonomy->name, $enabled_taxonomies, true ) );
            echo '> ';
            echo esc_html( $taxonomy->label ) . ' (<code>' . esc_html( $taxonomy->name ) . '</code>)';
            echo '</label>';
            echo '</li>';
        }
        echo '</ul>';
        echo '<p class="description">' . esc_html__( 'Select taxonomies to make them available as tags (e.g., {taxonomy:slug}) in the filter output builder.', 'filterflex' ) . '</p>';
    }

    /**
     * Render the 'remove_data_on_uninstall' settings field.
     */
    public function render_remove_data_on_uninstall_field() {
        $options = get_option('filterflex_settings');
        $checked = isset($options['remove_data_on_uninstall']) && $options['remove_data_on_uninstall'] ? 'checked' : '';
        echo '<label><input type="checkbox" name="filterflex_settings[remove_data_on_uninstall]" value="1" ' . esc_attr($checked) . '> ' . esc_html__('Remove all plugin data when the plugin is deleted.', 'filterflex') . '</label>';
        echo '<p class="description">' . esc_html__('If checked, all FilterFlex settings, filters, and data will be permanently deleted when you uninstall the plugin. This cannot be undone.', 'filterflex') . '</p>';
    }

    /**
     * Adds the main settings metabox to the filter edit screen.
     */
    public function add_settings_metabox() {
        add_meta_box(
            'filterflex_settings_metabox',          // Unique ID
            __( 'Filter Settings', 'filterflex' ),  // Box title
            [ $this, 'render_settings_metabox' ],   // Content callback, must be of type callable
            'filterflex_filter',                    // Post type
            'normal',                               // Context ('normal', 'side', 'advanced')
            'high'                                  // Priority ('high', 'low', 'default')
        );
    }

    /**
     * Renders the content of the settings metabox.
     *
     * @param WP_Post $post The post object currently being edited.
     */
    /**
     * Helper method to get all available tags for the builder, including dynamic ones.
     */
    private function get_all_available_tags_for_builder() {
        // Start with default tags
        $default_tags = [
            '{filtered_element}' => [ 'label' => __( 'Filtered Element', 'filterflex' ), 'type' => 'tag' ],
            '{categories}'       => [ 'label' => __( 'Categories', 'filterflex' ), 'type' => 'tag' ],
            '{tags}'             => [ 'label' => __( 'Tags (Post Tags)', 'filterflex' ), 'type' => 'tag' ],
            '{custom_field}'     => [ 'label' => __( 'Custom Field', 'filterflex' ), 'type' => 'tag', 'meta_prompt' => 'custom_field_key' ],
            '{date}'             => [ 'label' => __( 'Date', 'filterflex' ), 'type' => 'tag', 'has_options' => 'date_format' ],
            '{author}'           => [ 'label' => __( 'Author', 'filterflex' ), 'type' => 'tag' ],
            '[static_text]'      => [ 'label' => __( 'Static Text', 'filterflex' ), 'type' => 'text' ],
            '[separator]'        => [ 'label' => __( 'Separator', 'filterflex' ), 'type' => 'separator' ],
        ];

        // Get enabled custom taxonomies
        $plugin_settings = get_option( 'filterflex_settings', ['enabled_taxonomies' => []] );
        $enabled_tax_slugs = isset($plugin_settings['enabled_taxonomies']) && is_array($plugin_settings['enabled_taxonomies']) ? $plugin_settings['enabled_taxonomies'] : [];
        
        $dynamic_taxonomy_tags = [];
        if ( ! empty( $enabled_tax_slugs ) ) {
            foreach ( $enabled_tax_slugs as $slug ) {
                $taxonomy_obj = get_taxonomy( $slug );
                if ( $taxonomy_obj ) {
                    if ($slug === 'category' || $slug === 'post_tag') continue; 
                    $placeholder = '{taxonomy:' . $slug . '}';
                    
                    $dynamic_taxonomy_tags[$placeholder] = [
                        /* translators: %s is the taxonomy label, e.g. "Genre (Taxonomy)" */
                        'label' => sprintf( __( '%s (Taxonomy)', 'filterflex' ), $taxonomy_obj->label ),
                        'type'  => 'tag'
                    ];
                }
            }
        }
        
        $all_tags = array_merge( $default_tags, $dynamic_taxonomy_tags );
        return apply_filters( 'filterflex_available_tags_for_builder', $all_tags );
    }

    public function render_settings_metabox( $post ) {
        // Add a nonce field so we can check for it later.
        wp_nonce_field( 'filterflex_save_settings', 'filterflex_settings_nonce' );

        // Retrieve existing saved meta data
        $filterable_element = get_post_meta( $post->ID, '_filterflex_filterable_element', true );
        $priority           = get_post_meta( $post->ID, '_filterflex_priority', true );
        $location_rules     = get_post_meta( $post->ID, '_filterflex_location_rules', true );
        $output_config      = get_post_meta( $post->ID, '_filterflex_output_config', true );
        $transformations    = get_post_meta( $post->ID, '_filterflex_transformations', true );
        $apply_area         = get_post_meta( $post->ID, '_filterflex_apply_area', true );

    // Default values
    $priority = $priority ? intval( $priority ) : 10;
    $apply_area = ! empty( $apply_area ) ? $apply_area : 'frontend'; // Default to frontend
    if ( ! is_array( $location_rules ) ) $location_rules = [ [ [ 'param' => '', 'operator' => '==', 'value' => '' ] ] ]; // Default first rule group

    // Default Output Pattern if empty
    $default_pattern_array = [ [ 'type' => 'tag', 'value' => '{filtered_element}' ] ];
    $output_pattern_json = isset($output_config['pattern']) && !empty($output_config['pattern']) ? $output_config['pattern'] : json_encode($default_pattern_array);
    // Ensure it's valid JSON, fallback if not
    if (json_decode($output_pattern_json) === null) {
        $output_pattern_json = json_encode($default_pattern_array);
    }

    if ( ! is_array( $transformations ) ) $transformations = [];

    ?>
        <div class="filterflex-metabox-content">

            <!-- General Settings Section -->
            <div class="filterflex-section general-settings-section">
                 <h3><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'General Settings', 'filterflex' ); ?></h3>
                 <table class="form-table">
                     <tbody>
                         <tr>
                             <th scope="row">
                                 <label for="filterflex-filterable-element"><?php esc_html_e( 'Element to Filter:', 'filterflex' ); ?></label>
                             </th>
                             <td>
                                 <select id="filterflex-filterable-element" name="filterflex_filterable_element">
                                     <?php
                                     // Get filterable elements from the filter application class
                                     $all_filterable_elements = $this->filter_application->get_filterable_elements();
                                     foreach ( $all_filterable_elements as $key => $element ) {
                                         echo '<option value="' . esc_attr( $key ) . '" ' . selected( $filterable_element, $key, false ) . '>' . esc_html( $element['label'] ) . '</option>';
                                     }
                                     ?>
                                 </select>
                                 <p class="description"><?php esc_html_e( 'Select the WordPress element this filter should modify.', 'filterflex' ); ?></p>
                             </td>
                         </tr>
                         <tr>
                             <th scope="row">
                                 <label for="filterflex-priority"><?php esc_html_e( 'Priority:', 'filterflex' ); ?></label>
                             </th>
                             <td>
                                 <input type="number" id="filterflex-priority" name="filterflex_priority" value="<?php echo esc_attr( $priority ); ?>" min="1" step="1" class="small-text">
                                 <p class="description"><?php esc_html_e( 'Filters with higher numbers run later, potentially overriding filters with lower numbers. Default: 10.', 'filterflex' ); ?></p>
                             </td>
                         </tr>
                         <tr>
                             <th scope="row">
                                 <label for="filterflex-apply-area"><?php esc_html_e( 'Apply Filter To:', 'filterflex' ); ?></label>
                             </th>
                             <td>
                                 <select id="filterflex-apply-area" name="filterflex_apply_area">
                                     <option value="frontend" <?php selected( $apply_area, 'frontend' ); ?>><?php esc_html_e( 'Frontend Only', 'filterflex' ); ?></option>
                                     <option value="admin" <?php selected( $apply_area, 'admin' ); ?>><?php esc_html_e( 'Admin Area Only', 'filterflex' ); ?></option>
                                     <option value="both" <?php selected( $apply_area, 'both' ); ?>><?php esc_html_e( 'Both Frontend and Admin', 'filterflex' ); ?></option>
                                 </select>
                                 <p class="description"><?php esc_html_e( 'Choose where this filter should be applied.', 'filterflex' ); ?></p>
                             </td>
                         </tr>
                     </tbody>
                 </table>
            </div>

            <!-- Location Rules Section -->
            <div class="filterflex-section location-rules-section">
                <h3><span class="dashicons dashicons-location-alt"></span> <?php esc_html_e( 'Location Rules', 'filterflex' ); ?></h3>
                <p><em><?php esc_html_e( 'Show this filter if', 'filterflex' ); ?></em></p>
                <div id="filterflex-location-rules-container">
                    <?php
                    $location_config = $this->get_location_rules_config(); // Get config once

                    // Ensure location_rules is an array and has at least one group and one rule
                    if ( ! is_array( $location_rules ) || empty( $location_rules ) || ! is_array( $location_rules[0] ) || empty( $location_rules[0] ) ) {
                        // Add a default empty group and rule if none exist or structure is invalid
                        $location_rules = [ [ [ 'param' => '', 'operator' => '==', 'value' => '' ] ] ];
                    }

                    foreach ( $location_rules as $group_index => $rule_group ) :
                        // Ensure rule_group is an array
                        if ( ! is_array( $rule_group ) ) { continue; }
                    
                    // Add 'or' label before the group if it's not the first group
                    if ( $group_index > 0 ) {
                        echo '<div class="filterflex-or-label">or</div>';
                    }
                    ?>
                        <div class="filterflex-rule-group">
                            <?php foreach ( $rule_group as $rule_index => $rule ) :
                                // Ensure rule is an array
                                if ( ! is_array( $rule ) ) { continue; }

                                $param_name    = "filterflex_rules[{$group_index}][{$rule_index}][param]";
                                $operator_name = "filterflex_rules[{$group_index}][{$rule_index}][operator]";
                                $value_name    = "filterflex_rules[{$group_index}][{$rule_index}][value]";
                                $current_param = $rule['param'] ?? '';
                                $current_op    = $rule['operator'] ?? '==';
                                $current_val   = $rule['value'] ?? '';
                            ?>
                                <div class="filterflex-rule-row">
                                    <select name="<?php echo esc_attr( $param_name ); ?>" class="filterflex-rule-param">
                                        <option value=""><?php esc_html_e( '-- Select Parameter --', 'filterflex' ); ?></option>
                                        <?php foreach ( $location_config['params'] as $key => $label ) : ?>
                                            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current_param, $key ); ?>><?php echo esc_html( $label ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select name="<?php echo esc_attr( $operator_name ); ?>" class="filterflex-rule-operator">
                                        <?php foreach ( $location_config['operators'] as $key => $label ) : ?>
                                            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current_op, $key ); ?>><?php echo esc_html( $label ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php
                                    // Determine if the value dropdown should be initially hidden (it will be populated by AJAX)
                                    $value_style = 'style="display:none;"';
                                    // Check if a parameter is selected AND if we *expect* values for it (even if fetched later)
                                    $param_expects_values = !in_array($current_param, ['']); // Add params without values here if any
                                    if ($current_param && $param_expects_values) {
                                        $value_style = ''; // Show if param selected
                                    }
                                    ?>
                                    <select name="<?php echo esc_attr( $value_name ); ?>" class="filterflex-rule-value" data-saved-value="<?php echo esc_attr( $current_val ); ?>"<?php if ( $value_style ) { echo ' style=' . esc_attr( $value_style ); } ?>>
                                        <?php // Options will be populated by AJAX, render a placeholder initially ?>
                                        <option value=""><?php esc_html_e( '-- Loading... --', 'filterflex' ); ?></option>
                                    </select>
                                    <button type="button" class="button button-secondary filterflex-add-rule"><?php esc_html_e( 'and', 'filterflex' ); ?></button>
                                    <button type="button" class="button-link button-link-delete filterflex-remove-rule"><?php esc_html_e( 'Remove', 'filterflex' ); ?></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button button-secondary filterflex-add-rule-group"><?php esc_html_e( '+ Add rule group', 'filterflex' ); ?></button> <span class="filterflex-or-rule-group">(<?php esc_html_e( 'or', 'filterflex' ); ?>)</span>
            </div>

            <!-- Filter Output Builder Section -->
            <div class="filterflex-section filterflex-output-builder-section">
                <h3><span class="dashicons dashicons-slides"></span> <?php esc_html_e( 'Filter Output', 'filterflex' ); ?></h3>
                <div class="filterflex-output-builder-container">
                    <div class="filterflex-available-tags">
                        <h4><?php esc_html_e( 'Available Tags', 'filterflex' ); ?></h4>
                        <div class="filterflex-tags-list">
                            <?php
                            $all_available_tags_for_builder = $this->get_all_available_tags_for_builder();

                            foreach ( $all_available_tags_for_builder as $tag_placeholder => $tag_data ) :
                                $label = $tag_data['label'];
                                $tag_type = $tag_data['type'];

                                // Override type for special JS-handled builder elements
                                if ( $tag_placeholder === '[static_text]' ) {
                                    $tag_type = 'text';
                                } elseif ( $tag_placeholder === '[separator]' ) {
                                    $tag_type = 'separator';
                                }
                                // Add icon HTML for separator and static text
                                $icon_html = '';
                                $extra_class = '';
                                if ( $tag_placeholder === '[separator]' ) {
                                    $icon_html = '<span class="filterflex-tag-icon dashicons dashicons-minus"></span>';
                                    $extra_class = ' filterflex-tag-separator';
                                } elseif ( $tag_placeholder === '[static_text]' ) {
                                    $icon_html = '<span class="filterflex-tag-icon dashicons dashicons-edit"></span>';
                                    $extra_class = ' filterflex-tag-static-text';
                                } elseif ( $tag_placeholder === '{date}' ) {
                                    $icon_html = '<span class="filterflex-tag-icon dashicons dashicons-calendar"></span>';
                                    $extra_class = ' filterflex-tag-date';
                                }
                            ?>
                                <span class="filterflex-tag-item draggable-tag<?php echo esc_attr( $extra_class ); ?>"
                                    data-tag-type="<?php echo esc_attr( $tag_type ); ?>"
                                    data-tag-value="<?php echo esc_attr( $tag_placeholder ); ?>"
                                    draggable="true">
                                    <?php echo wp_kses_post( $icon_html ); ?><?php echo esc_html( $label ); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <p class="description"><?php esc_html_e( 'Click or drag tags into the builder area.', 'filterflex' ); ?></p>
                    </div>
                    <div class="filterflex-builder-area">
                        <h4><?php esc_html_e( 'Build Output Pattern', 'filterflex' ); ?></h4>
                        <?php // Hidden input stores the JSON representation of the pattern ?>
                        <input type="hidden" name="filterflex_output_pattern" id="filterflex-output-pattern-input" value="<?php echo esc_attr( $output_pattern_json ); ?>">
                        <div class="filterflex-builder-input" id="filterflex-builder-visual-input" tabindex="0">
                              <?php // JS will populate this based on the hidden input's JSON value ?>
                        </div>
                        <p class="description"><?php esc_html_e( 'Build the output using available tags and static text. Click tags to remove them.', 'filterflex' ); ?></p>

                        <!-- Transformations Section -->
                        <div class="filterflex-transformations">
                            <h4><?php esc_html_e( 'Apply Transformations (Optional)', 'filterflex' ); ?></h4>
                            <div id="filterflex-transformations-container">
                                <?php
                                // Ensure transformations is an array
                                $transformations = is_array($transformations) ? $transformations : [];
                                if ( empty( $transformations ) ) :
                                    // Render one empty row if none exist
                                    $transformations[] = ['type' => '', 'search' => '', 'replace' => '', 'limit' => ''];
                                endif;

                                foreach ( $transformations as $index => $transform ) :
                                    $type = $transform['type'] ?? '';
                                    $search = $transform['search'] ?? '';
                                    $replace = $transform['replace'] ?? '';
                                    $limit = $transform['limit'] ?? '';
                                    // Determine visibility based on type (JS will handle dynamic changes)
                                    $search_replace_style = ($type === 'search_replace') ? '' : 'style="display: none;"';
                                    $limit_style = ($type === 'limit_chars' || $type === 'limit_words') ? '' : 'style="display: none;"'; // Example
                                ?>
                                 <div class="filterflex-transformation-row">
                                    <select name="filterflex_transformations[<?php echo esc_attr($index); ?>][type]" class="filterflex-transformation-type">
                                        <option value=""><?php esc_html_e('-- Select Transformation --', 'filterflex'); ?></option>
                                        <option value="search_replace" <?php selected($type, 'search_replace'); ?>><?php esc_html_e('Search & Replace', 'filterflex'); ?></option>
                                        <option value="uppercase" <?php selected($type, 'uppercase'); ?>><?php esc_html_e('Uppercase', 'filterflex'); ?></option>
                                        <option value="lowercase" <?php selected($type, 'lowercase'); ?>><?php esc_html_e('Lowercase', 'filterflex'); ?></option>
                                        <?php // TODO: Add other transformation options ?>
                                    </select>
                                    <div class="filterflex-transformation-fields-row" style="margin-top:6px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                        <input type="text" name="filterflex_transformations[<?php echo esc_attr($index); ?>][search]" placeholder="<?php esc_attr_e('Search for...', 'filterflex'); ?>" value="<?php echo esc_attr($search); ?>" class="filterflex-transformation-search" <?php echo esc_attr($search_replace_style); ?>>
                                        <input type="text" name="filterflex_transformations[<?php echo esc_attr($index); ?>][replace]" placeholder="<?php esc_attr_e('Replace with...', 'filterflex'); ?>" value="<?php echo esc_attr($replace); ?>" class="filterflex-transformation-replace" <?php echo esc_attr($search_replace_style); ?>>
                                        <input type="number" name="filterflex_transformations[<?php echo esc_attr($index); ?>][limit]" placeholder="<?php esc_attr_e('Count', 'filterflex'); ?>" value="<?php echo esc_attr($limit); ?>" class="filterflex-transformation-limit small-text" <?php echo esc_attr($limit_style); ?>>
                                        <button type="button" class="button-link button-link-delete filterflex-remove-transformation"><?php esc_html_e('Remove', 'filterflex'); ?></button>
                                    </div>
                                  </div>
                                <?php endforeach; ?>
                              </div>
                              <button type="button" class="button button-secondary filterflex-add-transformation"><?php esc_html_e('+ Add Transformation', 'filterflex'); ?></button>
                         </div>

                         <!-- Preview Area -->
                         <div class="filterflex-preview-area">
                             <h4><?php esc_html_e( 'Live Preview', 'filterflex' ); ?></h4>
                             <div class="filterflex-preview-output" id="filterflex-preview-output">
                                 <?php esc_html_e( 'Preview will appear here...', 'filterflex' ); ?>
                             </div>
                         </div>
                     </div>
                 </div>
             </div>

        </div> <!-- End filterflex-metabox-content -->
        <?php
    }

    /**
     * Helper function to get configuration for location rules dropdowns.
     */
    private function get_location_rules_config() {
        $config = [
            'params' => [
                'post_type'     => __( 'Post Type', 'filterflex' ),
                'page_template' => __( 'Page Template', 'filterflex' ),
                'page'          => __( 'Page', 'filterflex' ),
                'post'          => __( 'Post', 'filterflex' ),
                'post_category' => __( 'Post Category', 'filterflex' ),
                'user_role'     => __( 'User Role', 'filterflex' ),
                'page_type'     => __( 'Page Type', 'filterflex' ),
            ],
            'operators' => [
                '==' => __('is equal to', 'filterflex'),
                '!=' => __('is not equal to', 'filterflex'),
            ],
        ];
        return [
            'params' => $config['params'],
            'operators' => $config['operators'],
        ];
    }

    /**
     * Helper function to get page templates.
     */
    private function get_page_templates() {
        $templates = wp_get_theme()->get_page_templates();
        $options = [];
        if ( ! empty( $templates ) ) {
            $options['default'] = apply_filters( 'default_page_template_title', __( 'Default Template', 'filterflex' ), 'filterflex' );
            foreach ( $templates as $file => $name ) {
                $options[ $file ] = $name;
            }
        }
        return $options;
    }

    /**
     * Save the meta box data when the post is saved.
     */
    public function save_settings_metabox( $post_id, $post ) {
        // Nonce check, permission check, autosave check...
        $nonce = isset( $_POST['filterflex_settings_nonce'] ) ? wp_unslash($_POST['filterflex_settings_nonce']) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'filterflex_save_settings' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;
        if ( 'filterflex_filter' !== $post->post_type ) return;

        // Ensure post status is preserved
        if (isset($_POST['post_status'])) {
            $post_status = sanitize_key(wp_unslash($_POST['post_status']));
            if (in_array($post_status, ['publish', 'draft'], true)) {
                remove_action('save_post_filterflex_filter', [$this, 'save_settings_metabox']);
                wp_update_post([
                    'ID' => $post_id,
                    'post_status' => $post_status
                ]);
                add_action('save_post_filterflex_filter', [$this, 'save_settings_metabox'], 10, 2);
            }
        }

        // --- Sanitize and Save Location Rules ---
        $sanitized_rules = [];
        if ( isset( $_POST['filterflex_rules'] ) && is_array( $_POST['filterflex_rules'] ) ) {
            $raw_rules = wp_unslash($_POST['filterflex_rules']); // Unslash the whole array
            if ( is_array( $raw_rules ) ) {
                foreach ( $raw_rules as $group_index => $rule_group ) {
                    if ( is_array( $rule_group ) ) {
                        $sanitized_group = [];
                        foreach ( $rule_group as $rule_index => $rule ) {
                            if ( is_array( $rule ) && isset( $rule['param'], $rule['operator'], $rule['value'] ) ) {
                                $sanitized_group[] = [
                                    'param'    => sanitize_text_field( $rule['param'] ),
                                    'operator' => sanitize_text_field( $rule['operator'] ),
                                    'value'    => sanitize_text_field( $rule['value'] ),
                                ];
                            }
                        }
                        if ( ! empty( $sanitized_group ) ) {
                            $sanitized_rules[] = $sanitized_group;
                        }
                    }
                }
            }
        }
        update_post_meta( $post_id, '_filterflex_location_rules', $sanitized_rules );

        // --- Sanitize and Save Filterable Element ---
        $selected_element = '';
        if ( isset( $_POST['filterflex_filterable_element'] ) ) {
            $raw_element = sanitize_text_field(wp_unslash( $_POST['filterflex_filterable_element'] ));
            $available_elements = $this->filter_application->get_filterable_elements();
            if ( array_key_exists( $raw_element, $available_elements ) ) {
                $selected_element = sanitize_key( $raw_element );
            }
        }
        update_post_meta( $post_id, '_filterflex_filterable_element', $selected_element );

        // --- Sanitize and Save Priority ---
        $priority = 10;
        if ( isset( $_POST['filterflex_priority'] ) ) {
            $priority = absint( wp_unslash( $_POST['filterflex_priority'] ) );
        }
        update_post_meta( $post_id, '_filterflex_priority', $priority );

        // --- Sanitize and Save Output Builder Pattern (JSON String) ---
        $output_pattern_json_to_save = '[]'; // Default to an empty valid JSON array string

        if ( isset( $_POST['filterflex_output_pattern'] ) ) {
            $raw_pattern_json = sanitize_text_field(wp_unslash( $_POST['filterflex_output_pattern'] ));
            $decoded_pattern = json_decode( $raw_pattern_json, true );

            // Check if JSON decoding was successful and resulted in an array
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded_pattern ) ) {
                $sanitized_pattern_data = [];
                foreach ( $decoded_pattern as $item_data ) {
                    // Ensure item_data is an array and 'type' key exists.
                    if ( is_array( $item_data ) && isset( $item_data['type'] ) && array_key_exists( 'value', $item_data ) ) {
                        $type = sanitize_key( $item_data['type'] );
                        $value = $item_data['value'];
                        $sanitized_item = [
                            'type' => $type,
                            'value' => sanitize_text_field( $value )
                        ];

                        // Handle custom field meta data
                        if ($type === 'tag' && $value === '{custom_field}' && isset($item_data['meta']) && is_array($item_data['meta'])) {
                            $sanitized_item['meta'] = [
                                'key' => sanitize_text_field($item_data['meta']['key'])
                            ];
                        }

                        // Handle date tag meta data
                        if ($type === 'tag' && $value === '{date}' && isset($item_data['meta']['format'])) {
                            // Ensure 'format' is a string and not empty.
                            if (is_string($item_data['meta']['format']) && !empty(trim($item_data['meta']['format']))) {
                                // Initialize 'meta' if it wasn't set by a previous condition (e.g. custom_field)
                                if (!isset($sanitized_item['meta'])) {
                                    $sanitized_item['meta'] = [];
                                }
                                $sanitized_item['meta']['format'] = sanitize_text_field(trim($item_data['meta']['format']));
                            }
                        }

                        // Handle separator special case
                        if ($type === 'separator') {
                            if ($value === "__{{SPACE}}__") {
                                $sanitized_item['value'] = "__{{SPACE}}__";
                            } else {
                                $allowed_separators = ["|", "[", "]", "(", ")", "-", "/", ":"];
                                if (in_array($value, $allowed_separators, true)) {
                                    $sanitized_item['value'] = $value;
                                } else {
                                    $sanitized_item['value'] = '';
                                }
                            }
                        }

                        $sanitized_pattern_data[] = $sanitized_item;
                    }
                }
                $output_pattern_json_to_save = wp_json_encode( $sanitized_pattern_data );
            }
        }
        update_post_meta( $post_id, '_filterflex_output_config', [ 'pattern' => $output_pattern_json_to_save ] );

        // --- Sanitize and Save Transformations ---
        $sanitized_transformations = [];
        if ( isset( $_POST['filterflex_transformations'] ) && is_array( $_POST['filterflex_transformations'] ) ) {
            $raw_transformations = wp_unslash( $_POST['filterflex_transformations'] );
            foreach ( $raw_transformations as $index => $transformation ) {
                if ( is_array( $transformation ) && isset( $transformation['type'] ) && !empty( $transformation['type'] ) ) {
                    $sanitized_transform = [ 'type' => sanitize_text_field( $transformation['type'] ) ];
                    if (isset($transformation['search'])) $sanitized_transform['search'] = sanitize_text_field( $transformation['search'] );
                    if (isset($transformation['replace'])) $sanitized_transform['replace'] = sanitize_text_field( $transformation['replace'] );
                    if (isset($transformation['limit'])) $sanitized_transform['limit'] = absint( $transformation['limit'] );
                    $sanitized_transformations[] = $sanitized_transform;
                }
            }
        }
        update_post_meta( $post_id, '_filterflex_transformations', $sanitized_transformations );

        // --- Sanitize and Save Apply Area ---
        $apply_area_value = 'frontend'; // Default value
        if ( isset( $_POST['filterflex_apply_area'] ) ) {
            $submitted_apply_area = sanitize_key( wp_unslash( $_POST['filterflex_apply_area'] ) );
            if ( in_array( $submitted_apply_area, [ 'frontend', 'admin', 'both' ], true ) ) {
                $apply_area_value = $submitted_apply_area;
            }
        }
        update_post_meta( $post_id, '_filterflex_apply_area', $apply_area_value );
    }

    /**
     * Register the custom post type.
     */
    public function register_post_type() {
        $labels = array(
            'name'               => _x( 'FilterFlex', 'post type general name', 'filterflex' ),
            'singular_name'      => _x( 'FilterFlex Filter', 'post type singular name', 'filterflex' ),
            'menu_name'          => _x( 'FilterFlex', 'admin menu', 'filterflex' ),
            'name_admin_bar'     => _x( 'FilterFlex Filter', 'add new on admin bar', 'filterflex' ),
            'add_new'            => _x( 'Add New', 'filterflex filter', 'filterflex' ), // Context changed for clarity
            'add_new_item'       => __( 'Add New FilterFlex Filter', 'filterflex' ),
            'new_item'           => __( 'New FilterFlex Filter', 'filterflex' ),
            'edit_item'          => __( 'Edit FilterFlex Filter', 'filterflex' ),
            'view_item'          => __( 'View FilterFlex Filter', 'filterflex' ),
            'all_items'          => __( 'All FilterFlex Filters', 'filterflex' ),
            'search_items'       => __( 'Search FilterFlex Filters', 'filterflex' ),
            'parent_item_colon'  => __( 'Parent FilterFlex Filters:', 'filterflex' ),
            'not_found'          => __( 'No FilterFlex filters found.', 'filterflex' ),
            'not_found_in_trash' => __( 'No FilterFlex filters found in Trash.', 'filterflex' )
        );
        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array( 'title' ), // Removed 'editor'
            'menu_icon'          => 'dashicons-filter',
        );
        register_post_type( 'filterflex_filter', $args );
    }
    
    /**
     * Enqueue scripts and styles.
     */
    public function enqueue_scripts() {
        wp_enqueue_style( 'filterflex-style', FILTERFLEX_PLUGIN_URL . 'public/css/filterflex.css', array(), $this->version );
        wp_enqueue_script( 'filterflex-script', FILTERFLEX_PLUGIN_URL . 'public/js/filterflex.js', array( 'jquery' ), $this->version, true );
    }
    
    /**
     * Enqueue admin scripts and styles.
     */
    public function enqueue_admin_assets( $hook_suffix ) {
        $screen = get_current_screen();
        
        // Check if we're on any FilterFlex screen
        if ( $screen && 'filterflex_filter' === $screen->post_type ) {
            // Always load the admin CSS on FilterFlex screens
            wp_enqueue_style( 'filterflex-admin-css', FILTERFLEX_PLUGIN_URL . 'admin/css/filterflex-admin.css', [], FILTERFLEX_VERSION );
            
            // Only load JS and other assets on the edit screen
            if ( 'post.php' === $hook_suffix || 'post-new.php' === $hook_suffix ) {
                wp_enqueue_script( 'filterflex-admin-js', FILTERFLEX_PLUGIN_URL . 'admin/js/filterflex-admin.js', [ 'jquery', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-droppable' ], FILTERFLEX_VERSION, true );

                // Get saved data, ensuring pattern is passed correctly
                $output_config = get_post_meta( get_the_ID(), '_filterflex_output_config', true );
                $default_pattern_array = [ [ 'type' => 'tag', 'value' => '{filtered_element}' ] ];
                $output_pattern_json = isset($output_config['pattern']) && !empty($output_config['pattern']) ? $output_config['pattern'] : json_encode($default_pattern_array);
                // Ensure it's valid JSON, fallback if not
                if (json_decode($output_pattern_json) === null) {
                    $output_pattern_json = json_encode($default_pattern_array);
                }

                wp_localize_script(
                    'filterflex-admin-js',
                    'filterFlexData',
                    [
                        'ajax_url'        => admin_url( 'admin-ajax.php' ),
                        'rest_url'        => rest_url(),
                        'nonce'           => wp_create_nonce( 'wp_rest' ),
                        'preview_nonce'   => wp_create_nonce( 'filterflex_preview_action' ),
                        'location_nonce'  => wp_create_nonce( 'filterflex_location_nonce' ),
                        'post_id'         => get_the_ID(),
                        'i18n'            => [ /* ... */ ],
                        'location_rules_config' => [ /* ... */ ],
                        'available_tags'        => $this->filter_application->get_available_tags(),
                        'available_transformations' => $this->filter_application->get_available_transformations(),
                        'saved_location_rules'  => get_post_meta( get_the_ID(), '_filterflex_location_rules', true ) ?: [],
                        'saved_output_pattern'  => $output_pattern_json,
                        'saved_transformations' => get_post_meta( get_the_ID(), '_filterflex_transformations', true ) ?: [],
                    ]
                );
            }
        }
    }
    
    /**
     * Run when the plugin is activated.
     */
    public function activate() {
        // Set default enabled taxonomies if the option doesn't exist.
        $settings = get_option( 'filterflex_settings' );
        if ( false === $settings || ! isset( $settings['enabled_taxonomies'] ) ) {
            $default_settings = [
                'enabled_taxonomies' => [ 'category', 'post_tag' ]
            ];
            if (false === $settings) { // Option does not exist at all
                 add_option( 'filterflex_settings', $default_settings );
            } else { // Option exists but 'enabled_taxonomies' key is missing
                 $settings['enabled_taxonomies'] = $default_settings['enabled_taxonomies'];
                 update_option('filterflex_settings', $settings);
            }
        }
        // Other activation actions can go here.
    }

    /**
     * Run when the plugin is deactivated.
     */
    public function deactivate() {
        // Actions to perform on plugin deactivation
    }

    // --- AJAX Handlers ---

    /**
     * AJAX handler to get location rule values.
     */
    public function ajax_get_location_values() {
        check_ajax_referer( 'filterflex_location_nonce', 'nonce' );

        $param = isset( $_POST['param'] ) ? sanitize_key( $_POST['param'] ) : '';
        $values = [];

        if ( ! $param ) {
            wp_send_json_error( [ 'message' => 'Parameter not specified.' ] );
        }

        switch ( $param ) {
            case 'post_type':
                $post_types = get_post_types( [ 'public' => true ], 'objects' );
                foreach ( $post_types as $pt ) { $values[ $pt->name ] = $pt->label; }
                break;
            case 'page_template':
                 $values = $this->get_page_templates();
                 break;
            case 'user_role':
                 global $wp_roles; $values = $wp_roles->get_names();
                 break;
            case 'page_type':
                 $values = [
                     'front_page' => __( 'Front Page', 'filterflex' ), 'home' => __( 'Posts Page', 'filterflex' ),
                     'single' => __( 'Single Post/Page/CPT', 'filterflex' ), 'archive' => __( 'Archive Page', 'filterflex' ),
                     '404' => __( '404 Not Found', 'filterflex' ),
                 ];
                 break;
             case 'page': case 'post':
                 $items = get_posts(['post_type' => ($param === 'page') ? 'page' : 'post', 'posts_per_page' => 50, 'orderby' => 'title', 'order' => 'ASC']);
                 foreach ($items as $item) { $values[$item->ID] = $item->post_title; }
                 break;
             case 'post_category':
                 $categories = get_categories(['hide_empty' => false]);
                 foreach ($categories as $category) { $values[$category->term_id] = $category->name; }
                 break;
            default:
                $values = apply_filters( "filterflex_location_values_{$param}", [] );
                break;
        }

        if ( empty( $values ) ) {
             wp_send_json_success( [ 'values' => null, 'message' => 'No options available.' ] );
        } else {
             wp_send_json_success( [ 'values' => $values ] );
        }
    }

    /**
     * AJAX handler for generating the live preview.
     */
    public function ajax_get_preview() {
        check_ajax_referer( 'filterflex_preview_action', 'security_token' );

        $pattern_json = isset( $_POST['pattern'] ) ? sanitize_text_field(wp_unslash( $_POST['pattern'] )) : '[]';
        $transformations_raw = isset( $_POST['transformations'] ) && is_array( $_POST['transformations'] ) ? sanitize_text_field(wp_unslash( $_POST['transformations'] )) : [];

        $pattern_data = json_decode( $pattern_json, true );
        // Use sanitize_text_field for sanitizing the select field value
        $filterable_element = isset( $_POST['filterable_element'] ) ? sanitize_text_field(wp_unslash($_POST['filterable_element'])) : '';

        $transformations = [];

        // Basic sanitization for transformations
        foreach ($transformations_raw as $trans) {
            if (is_array($trans) && !empty($trans['type'])) {
                $sanitized_trans = ['type' => sanitize_key($trans['type'])];
                if (isset($trans['search'])) $sanitized_trans['search'] = sanitize_text_field($trans['search']);
                if (isset($trans['replace'])) $sanitized_trans['replace'] = sanitize_text_field($trans['replace']);
                if (isset($trans['limit'])) $sanitized_trans['limit'] = absint($trans['limit']);
                $transformations[] = $sanitized_trans;
            }
        }

        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $pattern_data ) ) {
            wp_send_json_error( [ 'message' => 'Invalid pattern format.', 'received' => sanitize_text_field(wp_unslash($_POST['pattern'])) ] );
            return;
        }

        // Generate Preview String
        $preview_string = '';

        // Determine sample data for {filtered_element} based on selected filterable element
        $filtered_element_sample = 'Sample Content'; // Default sample
        switch ($filterable_element) {
            case 'the_title': // Assuming the value is the hook name
                $filtered_element_sample = 'Sample Post Title';
                break;
            case 'get_the_excerpt': // Assuming the value is the hook name
                $filtered_element_sample = 'Sample post excerpt goes here...';
                break;
            case 'the_content': // Assuming the value is the hook name
                $filtered_element_sample = 'Sample post content with some <strong>HTML</strong> and paragraphs.';
                break;
            case 'term_name': // Assuming this key is correct
                $filtered_element_sample = 'Sample Term Name';
                break;
            case 'user_display_name': // Assuming this key is correct
                $filtered_element_sample = 'Sample User Name';
                break;
            // Add more cases for other filterable elements as needed, using their actual values
        }

        $sample_data = [
            '{filtered_element}' => $filtered_element_sample,
            '{categories}'       => 'Category A, Category B',
            '{tags}'             => 'Tag1, Tag2',
            '{custom_field}'     => 'Some Custom Value',
            // '{date}' is now handled dynamically in the loop
            '{author}'           => 'Admin User',
            // Add sample data for other tags if necessary
        ];

        foreach ( $pattern_data as $item ) {
            if ( ! is_array( $item ) || ! isset( $item['type'], $item['value'] ) ) continue;

            if ( $item['type'] === 'tag' ) {
                // If the tag is {filtered_element}, use the dynamic sample based on the selected element.
                if ($item['value'] === '{filtered_element}') {
                    $preview_string .= $filtered_element_sample;
                } else {
                    // For other tags, use sample data if available, otherwise the tag value itself.
                    if ( $item['value'] === '{date}' ) {
                        $date_preview_format = get_option( 'date_format' ); // Default WordPress format
                        if ( isset( $item['meta']['format'] ) && ! empty( $item['meta']['format'] ) && is_string($item['meta']['format']) ) {
                            $date_preview_format = $item['meta']['format'];
                        }
                        // Use current time for preview, date_i18n for consistency if server times vary.
                        $preview_string .= date_i18n( $date_preview_format );
                    } else {
                        // For all other tags, use the existing sample_data lookup
                        $preview_string .= $sample_data[ $item['value'] ] ?? $item['value'];
                    }
                }
            } elseif ( $item['type'] === 'text' ) {
                // For static text, escape it and add to preview.
                $preview_string .= esc_html( $item['value'] );
            } elseif ( $item['type'] === 'separator' ) {
                // For separators, convert placeholder or escape the character and add to preview.
                $sep_val = $item['value'];
                if ( $sep_val === '__{{SPACE}}__' ) {
                    $preview_string .= ' '; // Convert placeholder to a regular space for preview
                } else {
                    $preview_string .= esc_html( $sep_val ); // Escape other separators
                }
            }
        }

        // Apply Transformations
        // Need the hook to correctly apply transformations, especially for HTML content
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $hook = '';
        if ( $post_id ) {
            $hook = get_post_meta( $post_id, '_filterflex_filterable_element', true );
        }

        if ( is_array( $transformations ) && ! empty( $transformations ) ) {
            // Pass the hook to apply_transformations
            $preview_string = $this->filter_application->apply_transformations( $preview_string, $transformations, $hook );
        }

        wp_send_json_success( [ 'preview' => $preview_string ] );
    }

    /**
     * Add custom columns to the FilterFlex post type listing.
     *
     * @param array $columns The default columns.
     * @return array Modified columns.
     */
    public function add_custom_columns( $columns ) {
        $new_columns = [];

        // Reorder columns - put checkbox and title first
        if (isset($columns['cb'])) {
            $new_columns['cb'] = $columns['cb'];
        }
        if (isset($columns['title'])) {
            $new_columns['title'] = $columns['title'];
        }

        // Add our custom columns
        $new_columns['filtered_element'] = __('Filtered Element', 'filterflex');
        $new_columns['priority'] = __('Priority', 'filterflex');
        $new_columns['apply_area'] = __('Applied To', 'filterflex');
        $new_columns['status'] = __('Status', 'filterflex');

        // Add any remaining columns
        foreach ($columns as $key => $value) {
            if (!isset($new_columns[$key])) {
                $new_columns[$key] = $value;
            }
        }

        return $new_columns;
    }

    /**
     * Render the content for custom columns.
     *
     * @param string $column The column name.
     * @param int    $post_id The post ID.
     */
    public function render_custom_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'filtered_element':
                $element = get_post_meta( $post_id, '_filterflex_filterable_element', true );
                if ( ! empty( $element ) && isset( $this->filter_application->get_filterable_elements()[$element] ) ) {
                    echo esc_html( $this->filter_application->get_filterable_elements()[$element]['label'] );
                } else {
                    echo '<span class="no-element">' . esc_html__( 'Not set', 'filterflex' ) . '</span>';
                }
                break;

            case 'priority':
                $priority = get_post_meta( $post_id, '_filterflex_priority', true );
                echo esc_html( $priority ? $priority : '10' );
                break;

            case 'apply_area':
                $area = get_post_meta( $post_id, '_filterflex_apply_area', true );
                switch ( $area ) {
                    case 'frontend':
                        echo '<span class="area-frontend">' . esc_html__( 'Frontend', 'filterflex' ) . '</span>';
                        break;
                    case 'admin':
                        echo '<span class="area-admin">' . esc_html__( 'Admin', 'filterflex' ) . '</span>';
                        break;
                    case 'both':
                        echo '<span class="area-both">' . esc_html__( 'Both', 'filterflex' ) . '</span>';
                        break;
                    default:
                        echo '<span class="area-frontend">' . esc_html__( 'Frontend', 'filterflex' ) . '</span>';
                }
                break;

            case 'status':
                $post_status = get_post_status($post_id);
                $status_class = $post_status === 'publish' ? 'status-active' : 'status-inactive';
                $status_text = $post_status === 'publish' ? __('Active', 'filterflex') : __('Inactive', 'filterflex');
                echo '<span class="' . esc_attr($status_class) . '">' . esc_html($status_text) . '</span>';
                break;
        }
    }

    /**
     * Make custom columns sortable.
     *
     * @param array $columns The sortable columns.
     * @return array Modified sortable columns.
     */
    public function make_custom_columns_sortable( $columns ) {
        $columns['priority'] = 'priority';
        $columns['filtered_element'] = 'filtered_element';
        $columns['apply_area'] = 'apply_area';
        return $columns;
    }

    /**
     * Handle custom column sorting.
     *
     * @param WP_Query $query The WordPress query object.
     */
    public function custom_columns_orderby( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() || $query->get( 'post_type' ) !== 'filterflex_filter' ) {
            return;
        }

        $orderby = $query->get( 'orderby' );

        switch ( $orderby ) {
            case 'priority':
                $query->set( 'meta_key', '_filterflex_priority' );
                $query->set( 'orderby', 'meta_value_num' );
                break;

            case 'filtered_element':
                $query->set( 'meta_key', '_filterflex_filterable_element' );
                $query->set( 'orderby', 'meta_value' );
                break;

            case 'apply_area':
                $query->set( 'meta_key', '_filterflex_apply_area' );
                $query->set( 'orderby', 'meta_value' );
                break;
        }
    }

    /**
     * Customize the status toggle in the publish box.
     *
     * @param WP_Post $post The post object.
     */
    public function custom_status_toggle( $post ) {
        if ( $post->post_type !== 'filterflex_filter' ) {
            return;
        }

        // Get the current post status
        $status = $post->post_status;
        ?>
        <div class="filterflex-status-toggle">
            <label>
                <span class="filterflex-status-label"><?php esc_html_e( 'Filter Status', 'filterflex' ); ?></span>
                <span class="filterflex-switch-wrapper" style="display: flex; align-items: center;">
                    <span class="filterflex-switch">
                        <input type="hidden" name="post_status" value="draft">
                        <input type="checkbox" 
                               id="post-status-checkbox"
                               class="post-status-toggle"
                               <?php checked( $status, 'publish' ); ?>
                               onchange="document.querySelector('input[name=\'post_status\']').value = this.checked ? 'publish' : 'draft';">
                        <span class="filterflex-slider"></span>
                    </span>
                    <span class="filterflex-status-text"><?php echo $status === 'publish' ? esc_html__( 'Active', 'filterflex' ) : esc_html__( 'Inactive', 'filterflex' ); ?></span>
                </span>
            </label>
            <p class="description">
                <?php esc_html_e( 'Toggle to activate or deactivate this filter.', 'filterflex' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Modify the post states display.
     *
     * @param array   $post_states An array of post states.
     * @param WP_Post $post        The post object.
     * @return array Modified post states.
     */
    public function modify_post_states( $post_states, $post ) {
        if ( $post->post_type === 'filterflex_filter' ) {
            // Clear existing states and return empty array for our post type
            return array();
        }
        return $post_states;
    }

    /**
     * Display a custom message when no filters are found.
     *
     * @param string $which The location of the extra table navigation ('top' or 'bottom').
     */
    public function display_no_filters_message( $which ) {
        if ( 'top' !== $which ) {
            return;
        }

        $current_screen = get_current_screen();
        if ( ! $current_screen || 'edit-filterflex_filter' !== $current_screen->id ) {
            return;
        }

        // Don't show the message on the trash screen
        if ( isset( $_GET['post_status'] ) && 'trash' === sanitize_key( wp_unslash( $_GET['post_status'] ) ) ) {
            return;
        }

        $counts = wp_count_posts( 'filterflex_filter' );
        $total_posts = $counts->publish + $counts->draft;

        if ( $total_posts === 0 ) {
            $add_new_url = admin_url( 'post-new.php?post_type=filterflex_filter' );
            ?>
            <div class='filterflex-no-filters-message'>
                <img src='<?php echo esc_url( FILTERFLEX_PLUGIN_URL . 'static/img/filterflex-logo.png' ); ?>' alt='FilterFlex Logo' class='filterflex-splash-logo'>
                <h2><?php esc_html_e( 'Add Your First Filter', 'filterflex' ); ?></h2>
                <p><?php esc_html_e( "It looks like you haven't created any filters yet. Get started by adding your first one!", 'filterflex' ); ?></p>
                <a href='<?php echo esc_url( $add_new_url ); ?>' class='button button-primary button-hero'>
                    <?php esc_html_e( '+ Add Filter', 'filterflex' ); ?>
                </a>
            </div>
            <?php
            // Prevent default "No items found" message or duplicate message
            exit();
        }
    }

} // End of FilterFlex class
