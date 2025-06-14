// admin/js/filterflex-admin.js
jQuery(document).ready(function($) {

    // Function to dynamically adjust the width of a select element based on its selected option's text
    function adjustSelectWidth($selectElement) {
        // Create a temporary span to measure text width
        const $tempSpan = $('<span>').css({
            'position': 'absolute',
            'visibility': 'hidden',
            'white-space': 'nowrap',
            'font-family': $selectElement.css('font-family'),
            'font-size': $selectElement.css('font-size'),
            'font-weight': $selectElement.css('font-weight'),
            'letter-spacing': $selectElement.css('letter-spacing'),
            'text-transform': $selectElement.css('text-transform'),
            'padding': $selectElement.css('padding'),
            'border': $selectElement.css('border')
        }).appendTo('body');

        // Get the text of the selected option
        const selectedText = $selectElement.find('option:selected').text();
        $tempSpan.text(selectedText);

        // Calculate the width and add a buffer for the dropdown arrow and padding
        // A buffer of 30-40px is usually sufficient for the custom arrow and internal padding
        const calculatedWidth = $tempSpan.width() + 35; // Adjust buffer as needed

        // Apply the calculated width to the select element
        $selectElement.width(calculatedWidth);

        // Remove the temporary span
        $tempSpan.remove();
    }

    // Add console log to check available tags
    console.log('FilterFlex Available Tags:', filterFlexData.available_tags);

    // Add form submit handler for debugging
    $('#post').on('submit', function() {
        const patternData = JSON.parse($('#filterflex-output-pattern-input').val());
        console.log('Form submit - Pattern Data:', patternData);
    });

    // --- Metabox Toggle ---
    $('.filterflex-metabox-header').on('click', function() {
        const $header = $(this);
        const $content = $header.next('.filterflex-metabox-content');
        const $arrow = $header.find('.filterflex-toggle-arrow');
        const $metabox = $header.closest('.filterflex-metabox');

        $content.slideToggle(200, function() { // Add slide animation
            if ($content.is(':visible')) {
                $arrow.html('▲'); // Up arrow
                $metabox.removeClass('closed');
            } else {
                $arrow.html('▼'); // Down arrow
                $metabox.addClass('closed');
            }
        });
    });

    // --- Output Builder: Remove Filtered Element from Available Tags ---
    $('.filterflex-available-tags .filterflex-tags-list').find('.draggable-tag[data-tag-value="{filtered_element}"]').remove();


    // --- Location Rules ---
    const rulesContainer = $('#filterflex-location-rules-container');
    let groupIndex = rulesContainer.find('.filterflex-rule-group').length -1; // Start index based on existing groups

    // Function to update value dropdown options based on selected parameter using AJAX
    function updateValueOptions($row, initialLoad = false) {
        const param = $row.find('.filterflex-rule-param').val();
        const $valueSelect = $row.find('.filterflex-rule-value');
        // Read the saved value from the data attribute on initial load
        const savedVal = initialLoad ? $valueSelect.data('saved-value') : null;

        $valueSelect.empty().hide().prop('disabled', false); // Clear, hide, and ensure it's enabled

        if (!param) {
            $valueSelect.append($('<option>', { value: '', text: filterFlexData.i18n?.selectParam || '-- Select Parameter First --' }));
            // Don't show if no param selected
            // $valueSelect.show();
            return; // Exit if no parameter is selected
        }

        if (!param) {
            $valueSelect.append($('<option>', { value: '', text: filterFlexData.i18n?.selectParam || '-- Select Parameter First --' }));
            return; // Exit if no parameter is selected
        }

        // Add loading indicator
        $valueSelect.append($('<option>', { value: '', text: filterFlexData.i18n?.loading || 'Loading...' })).show();

        // Make AJAX request
        $.ajax({
            url: filterFlexData.ajax_url,
            type: 'POST',
            data: {
                action: 'filterflex_get_location_values',
                nonce: filterFlexData.location_nonce, // Use the specific nonce
                param: param
            },
            success: function(response) {
                $valueSelect.empty(); // Clear loading indicator

                if (response.success && response.data.values && Object.keys(response.data.values).length > 0) {
                    $valueSelect.append($('<option>', { value: '', text: filterFlexData.i18n?.selectValue || '-- Select Value --' }));
                    $.each(response.data.values, function(value, label) {
                        $valueSelect.append($('<option>', {
                            value: value,
                            text: label
                        }));
                    });

                    // Try to re-select the original saved value if it exists (only on initial load)
                    // OR set default 'Posts' if param is 'post_type' and 'post' option exists
                    if (initialLoad && savedVal !== null && response.data.values.hasOwnProperty(savedVal)) {
                        $valueSelect.val(savedVal);
                    } else if ((!initialLoad || (initialLoad && savedVal === null)) && param === 'post_type' && response.data.values.hasOwnProperty('post')) {
                         $valueSelect.val('post'); // Set default to 'Posts'
                    }
                    else {
                         $valueSelect.val(''); // Select default if no saved value or not found
                    }
                     $valueSelect.show();
                } else {
                    // No options returned or error
                    $valueSelect.append($('<option>', { value: '', text: filterFlexData.i18n?.noOptions || '-- N/A --' }));
                    // Keep it hidden or show N/A? Let's show N/A but keep it disabled-like
                     $valueSelect.show().prop('disabled', true); // Visually indicate no options
                 }
             },
             error: function(jqXHR, textStatus, errorThrown) {
                 // Log the actual error details
                 console.error('FilterFlex AJAX Error:', textStatus, errorThrown, jqXHR.responseText);
                 $valueSelect.empty();
                 // Display a user-friendly error message
                 const errorMsg = filterFlexData.i18n?.ajaxError || 'Error loading options';
                 $valueSelect.append($('<option>', { value: '', text: errorMsg })).show().prop('disabled', true);
             },
             complete: function() {
                 // Re-enable dropdown if it was disabled
                 if ($valueSelect.prop('disabled')) {
                     // Only re-enable if options were actually loaded successfully
                     if ($valueSelect.find('option').length > 1) { // More than just the default/error option
                         $valueSelect.prop('disabled', false);
                     }
                 }
                 // Clear the saved value data attribute after using it on initial load
                 // Although it might not be strictly necessary to remove it
                 // if (initialLoad) {
                 //    $valueSelect.removeData('saved-value');
                 // }
            }
        });
    }

    // Initial population of value dropdowns on page load using AJAX
    rulesContainer.find('.filterflex-rule-row').each(function() {
        const $row = $(this);
        // The saved value is now in 'data-saved-value', read by updateValueOptions
        updateValueOptions($row, true); // Pass true for initialLoad
    });

    // Change param dropdown - update value options via AJAX
    rulesContainer.on('change', '.filterflex-rule-param', function() {
        const $row = $(this).closest('.filterflex-rule-row');
        updateValueOptions($row, false); // Pass false for subsequent updates
    });

    // Add Rule (AND)
    rulesContainer.on('click', '.filterflex-add-rule', function() {
        const $group = $(this).closest('.filterflex-rule-group');
        const $lastRow = $group.find('.filterflex-rule-row:last');
        const $newRow = $lastRow.clone();
        const groupIdx = $group.index();
        const newRowIndex = $group.find('.filterflex-rule-row').length;

        // Update name attributes and set default values for the new row
        $newRow.find('select, input').each(function() {
            const $this = $(this);
            const name = $this.attr('name').replace(/\[\d+\]\[\d+\]/g, '[' + groupIdx + '][' + newRowIndex + ']');
            $this.attr('name', name);

            // Set default values
            if ($this.hasClass('filterflex-rule-param')) {
                $this.val('post_type'); // Default to Post Type
            } else if ($this.hasClass('filterflex-rule-operator')) {
                $this.val('=='); // Default to is equal to
            } else {
                $this.val(''); // Clear other inputs
            }
        });

        // Clear and hide the value dropdown initially
        $newRow.find('.filterflex-rule-value').empty().hide();
        $newRow.appendTo($group);

        // Trigger change on param to load value options and set default 'Posts'
        $newRow.find('.filterflex-rule-param').trigger('change');
    });

     // Remove Rule
     rulesContainer.on('click', '.filterflex-remove-rule', function() {
         const $row = $(this).closest('.filterflex-rule-row');
         const $group = $row.closest('.filterflex-rule-group');
         if ($group.find('.filterflex-rule-row').length > 1) {
             $row.remove();
         } else {
             // If it's the last rule in the group, remove the whole group? Or just clear it?
             // For now, just don't allow removing the last rule in a group if it's not the only group
             if (rulesContainer.find('.filterflex-rule-group').length > 1) {
                 $group.remove();
             } else {
                 alert('Cannot remove the last rule.'); // Or clear the fields
             }
         }
     });

    // Add Rule Group (OR)
    $('.filterflex-add-rule-group').on('click', function() {
        groupIndex++;
        const $firstGroup = rulesContainer.find('.filterflex-rule-group:first');
        const $newGroup = $('<div class="filterflex-rule-group"></div>');
        const $newRow = $firstGroup.find('.filterflex-rule-row:first').clone();

        // Update name attributes and set default values for the new row in the new group
        $newRow.find('select, input').each(function() {
            const $this = $(this);
            const name = $this.attr('name').replace(/\[\d+\]\[\d+\]/g, '[' + groupIndex + '][0]');
            $this.attr('name', name);

            // Set default values
            if ($this.hasClass('filterflex-rule-param')) {
                $this.val('post_type'); // Default to Post Type
            } else if ($this.hasClass('filterflex-rule-operator')) {
                $this.val('=='); // Default to is equal to
            } else {
                $this.val(''); // Clear other inputs
            }
        });

         // Clear and hide the value dropdown initially
        $newRow.find('.filterflex-rule-value').empty().hide();
        $newRow.appendTo($newGroup);

        // Add 'or' label after the last rule group if it's not the first group
        const $existingGroups = rulesContainer.find('.filterflex-rule-group');
        if ($existingGroups.length > 0) {
            $('<div class="filterflex-or-label">or</div>').insertAfter($existingGroups.last());
        }

        $newGroup.appendTo(rulesContainer);

        // Trigger change on param to load value options and set default 'Posts'
        $newRow.find('.filterflex-rule-param').trigger('change');
    });

    // --- Output Builder ---
    const $builderVisualInput = $('#filterflex-builder-visual-input');
    const $patternHiddenInput = $('#filterflex-output-pattern-input');
    const $availableTagsContainer = $('.filterflex-available-tags .filterflex-tags-list');

    // Function to create a tag element or a text input element for the builder
    function createBuilderElement(type, value, label = '') {
        const $itemWrapper = $('<span>')
            .addClass('filterflex-builder-item');

        if (type === 'tag') {
            $itemWrapper.addClass('filterflex-tag-item')
                .attr('data-tag', value);
            
            // Special handling for custom field tag
            if (value === '{custom_field}') {
                // Store the tag value as data attribute
                $itemWrapper.attr('data-tag', '{custom_field}');
                
                const $labelSpan = $('<span>').addClass('tag-label').text('Custom Field: ');
                const $metaInput = $('<input>')
                    .attr('type', 'text')
                    .addClass('custom-field-meta-input')
                    .attr('placeholder', 'Enter field name');
                
                $itemWrapper.append($labelSpan).append($metaInput);
            } else if (value === '{date}') {
                $itemWrapper.attr('data-tag', '{date}'); // Store the tag value
                const $labelSpan = $('<span>').addClass('tag-label').text('Date Format: ');
                const $formatSelect = $('<select>').addClass('date-format-select');

                // Define date format options
                const dateFormats = {
                    '': 'WordPress Default', // Default option
                    'Y-m-d': 'YYYY-MM-DD',
                    'm/d/Y': 'MM/DD/YYYY',
                    'd/m/Y': 'DD/MM/YYYY',
                    'F j, Y': 'Month D, YYYY',
                    'M j, y': 'Mon D, YY',
                    'H:i:s': 'HH:MM:SS (24 hour)',
                    'g:i a': 'HH:MM AM/PM'
                };

                // Populate the select options
                $.each(dateFormats, function(formatVal, formatText) {
                    $formatSelect.append($('<option>', { value: formatVal, text: formatText }));
                });

                // Set a default selected format if needed, e.g., WordPress Default
                $formatSelect.val(''); // WordPress Default selected by default

                $itemWrapper.append($labelSpan).append($formatSelect);
                // Adjust width immediately after creation
                adjustSelectWidth($formatSelect);
                // No remove item span here, it's added later for all tags except {filtered_element}
            } else {
                $itemWrapper.text(label);
            }
            
            if (value === '{filtered_element}') {
                $itemWrapper.addClass('non-removable');
            } else {
                $itemWrapper.append('<span class="filterflex-remove-item">×</span>');
            }
        } else if (type === 'text') {
            $itemWrapper.addClass('filterflex-text-input-wrapper');
            const $input = $('<input>')
                .attr('type', 'text')
                .addClass('filterflex-static-text-input')
                .attr('placeholder', 'Type static text...')
                .val(value);
            $itemWrapper.append($input);
            $itemWrapper.append('<span class="filterflex-remove-item">×</span>');
        } else if (type === 'separator') {
            $itemWrapper.addClass('filterflex-separator-wrapper');
            const $select = $('<select>').addClass('filterflex-separator-select');
            const options = {
                "__{{SPACE}}__": "␣",
                "|": "|",
                "[": "[",
                "]": "]",
                "(": "(",
                ")": ")",
                "-": "-",
                "/": "/",
                ":": ":"
            };
            $.each(options, function(val, text) {
                $select.append($('<option>', { value: val, text: text }));
            });
            if (typeof value !== 'undefined' && value !== null) {
                $select.val(value);
            }
            $itemWrapper.append($select);
            $itemWrapper.append('<span class="filterflex-remove-item">×</span>');
        }
        return $itemWrapper;
    }

    // This function might be deprecated or adapted if createBuilderElement handles all cases.
    // For now, let it create the new structure for text items loaded from pattern.
    function createBuilderTextInputElement(value = '') {
        return createBuilderElement('text', value);
    }

    // Modify updateHiddenPatternInput to properly handle custom field meta
    function updateHiddenPatternInput() {
        const patternData = [];
        $builderVisualInput.find('.filterflex-builder-item').each(function() {
            const $item = $(this);
            if ($item.hasClass('filterflex-tag-item')) {
                const tagValue = $item.data('tag');
                let tagData = { type: 'tag', value: tagValue };
                
                // Handle custom field meta
                if (tagValue === '{custom_field}') {
                    const metaKey = $item.find('.custom-field-meta-input').val().trim();
                    console.log('Custom field meta key:', metaKey); // Debug log
                    if (metaKey) {
                        tagData.meta = { key: metaKey };
                        console.log('Tag data with meta:', tagData); // Debug log
                    }
                } else if (tagValue === '{date}') {
                    const formatVal = $item.find('.date-format-select').val();
                    // Only add meta if a specific format is chosen (not the default WP one which is empty string)
                    if (formatVal !== '') {
                         tagData.meta = { format: formatVal };
                    }
                    // If formatVal is '', no meta.format is added, PHP will use default.
                }
                
                patternData.push(tagData);
            } else if ($item.hasClass('filterflex-text-input-wrapper')) {
                const value = $item.find('.filterflex-static-text-input').val();
                patternData.push({ type: 'text', value: value });
            } else if ($item.hasClass('filterflex-separator-wrapper')) {
                const value = $item.find('.filterflex-separator-select').val();
                patternData.push({ type: 'separator', value: value });
            }
        });

        // For debugging - log the pattern data before saving
        console.log('Saving pattern data:', patternData);
        
        $patternHiddenInput.val(JSON.stringify(patternData));
        updatePreview();
    }

    // Function to render the visual builder from the saved structured pattern (JSON)
    function renderBuilderFromPattern(patternJson) {
        $builderVisualInput.empty();
        let patternData = [];
        try {
            patternData = JSON.parse(patternJson || '[]');
        } catch (e) {
            console.error("Error parsing saved pattern JSON:", e);
            patternData = [];
        }

        if (!Array.isArray(patternData)) {
            console.error("Saved pattern is not an array:", patternData);
            patternData = [];
        }

        patternData.forEach(item => {
            if (item.type === 'tag') {
                let label = item.value;
                let tagValue = item.value;

                // Check if it's a custom field tag with saved meta
                if (tagValue === '{custom_field}') {
                    const metaKey = item.meta?.key || '';
                    const baseLabel = filterFlexData.available_tags['{custom_field}']?.label || 'Custom Field';
                    $newElement = createBuilderElement('tag', tagValue, baseLabel);
                    // Set the meta key value in the input
                    $newElement.find('.custom-field-meta-input').val(metaKey);
                } else if (tagValue === '{date}') {
                    const baseLabel = filterFlexData.available_tags['{date}']?.label || 'Date';
                    $newElement = createBuilderElement('tag', tagValue, baseLabel); // createBuilderElement will now add the select
                    const savedFormat = item.meta?.format || ''; // Default to empty string (WP Default) if not set
                    $newElement.find('.date-format-select').val(savedFormat);
                } else {
                    // For other tags
                    const $foundTag = $availableTagsContainer.find(`.draggable-tag[data-tag-value="${item.value}"]`);
                    if ($foundTag.length) {
                        label = $foundTag.text();
                    } else {
                        label = item.value.replace(/[{}]/g, '').replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                    }
                    $newElement = createBuilderElement('tag', tagValue, label);
                }
                $builderVisualInput.append($newElement);
            } else if (item.type === 'text') {
                $builderVisualInput.append(createBuilderElement('text', item.value));
            } else if (item.type === 'separator') {
                $builderVisualInput.append(createBuilderElement('separator', item.value));
            }
        });
    }

    // --- Drag and Drop & Sortable ---
    // Make available tags draggable
    $availableTagsContainer.find('.draggable-tag').draggable({
        helper: 'clone',
        cursor: 'grabbing',
        revert: 'invalid',
        // connectToSortable: '#filterflex-builder-visual-input', // REMOVED - Using Droppable instead
        start: function(event, ui) {
            $(ui.helper).css('z-index', 100);
        },
        stop: function(event, ui) {
             // Optional: Reset any styles on the original draggable if needed
        }
    });

    // Make the builder area sortable and droppable
    $builderVisualInput.sortable({
        placeholder: 'filterflex-sortable-placeholder', // CSS class for placeholder
        cursor: 'move',
        items: '.filterflex-builder-item', // Only allow sorting of builder items
        tolerance: 'pointer',
        start: function(event, ui) {
            ui.placeholder.height(ui.item.outerHeight());
            ui.placeholder.width(ui.item.outerWidth());
        },
        // receive: function(event, ui) { ... }, // REMOVED - Handled by Droppable now
        update: function(event, ui) { // Handle reordering *within* the builder
            updateHiddenPatternInput();
        }
    });

    // Make the builder area droppable to accept tags from the available list
    $builderVisualInput.droppable({
        accept: '.draggable-tag',
        hoverClass: 'filterflex-drag-over-builder',
        drop: function(event, ui) {
            const $droppableContainer = $(this);
            const $draggedTag = ui.draggable;
            const tagValue = $draggedTag.data('tag-value');
            const tagLabel = $draggedTag.text();
            const tagType = $draggedTag.data('tag-type');

            let $newElement;
            if (tagType === 'text') {
                $newElement = createBuilderElement('text', '');
            } else if (tagType === 'separator') {
                $newElement = createBuilderElement('separator', "__{{SPACE}}__");
            } else {
                if (tagValue === '{filtered_element}' && $droppableContainer.find('.filterflex-tag-item[data-tag="{filtered_element}"]').length > 0) {
                    return;
                }
                $newElement = createBuilderElement('tag', tagValue, tagLabel);
            }

            $droppableContainer.append($newElement);
            updateHiddenPatternInput();
        }
    });

    // REMOVED: addTextInputsAround
    // REMOVED: ensureTextInputsBetweenItems
    // REMOVED: cleanupConsecutiveTextInputs


    // --- Builder Item Interactions ---

    // Remove item (tag or text input wrapper)
    $builderVisualInput.on('click', '.filterflex-remove-item', function(e) {
        e.stopPropagation();
        const $itemToRemove = $(this).closest('.filterflex-builder-item');
        // Prevent removing the non-removable {filtered_element}
        if ($itemToRemove.hasClass('non-removable')) {
            return;
        }
        $itemToRemove.remove();
        // No longer need to ensureTextInputsBetweenItems after removal
        updateHiddenPatternInput();
    });

    // Update hidden input when text inputs (inside wrappers) change
    $builderVisualInput.on('keyup change', '.filterflex-static-text-input', function() {
        updateHiddenPatternInput();
    });
    $builderVisualInput.on('change', '.filterflex-separator-select', function() {
        updateHiddenPatternInput();
    });

    // Add a new text input when clicking in the empty space (optional usability enhancement)
    // $builderVisualInput.on('click', function(e) {
    //     if (e.target === this) { // Clicked directly on the container, not an item
    //         $(this).append(createBuilderTextInputElement());
    //         updateHiddenPatternInput();
    //     }
    // });


    // Initialize builder on page load
    // The default pattern is now just the filtered_element tag, without surrounding text inputs.
    // If saved_output_pattern is empty or '[]', it implies an empty builder unless we want a default.
    // Let's keep the {filtered_element} as a default if nothing is saved.
    if (filterFlexData.saved_output_pattern === '' || filterFlexData.saved_output_pattern === '[]') {
        // If you want a default item like {filtered_element} to always be there initially (even if removable later, except for this one)
        renderBuilderFromPattern('[{"type":"tag", "value":"{filtered_element}"}]');
    } else {
        renderBuilderFromPattern(filterFlexData.saved_output_pattern);
    }
    // No longer call ensureTextInputsBetweenItems() on load.
    // ensureTextInputsBetweenItems();


    // --- Transformations ---
    const $transContainer = $('#filterflex-transformations-container');
    let transIndex = $transContainer.find('.filterflex-transformation-row').length - 1;

    // Function to show/hide conditional inputs
    function toggleTransformationInputs($row) {
        const type = $row.find('select[name$="[type]"]').val();
        $row.find('input[name$="[search]"], input[name$="[replace]"]').toggle(type === 'search_replace');
        $row.find('input[name$="[limit]"]').toggle(type === 'limit_words');
        // Add conditions for other types
    }

    // Initial toggle for existing rows
    $transContainer.find('.filterflex-transformation-row').each(function() {
        toggleTransformationInputs($(this));
    });

     // Change transformation type
     $transContainer.on('change', 'select[name$="[type]"]', function() {
         toggleTransformationInputs($(this).closest('.filterflex-transformation-row'));
         updatePreview(); // Update preview on change
     });

      // Add Transformation
     $('.filterflex-add-transformation').on('click', function() {
         transIndex++;
         const $firstRow = $transContainer.find('.filterflex-transformation-row:first');
         // Clone the first row OR have a hidden template row
         let $newRow;
         if ($firstRow.length) {
            $newRow = $firstRow.clone();
         } else {
             // Create from scratch if none exist (better to have a template)
             $newRow = $('<div class="filterflex-transformation-row">' +
                         '<select name="filterflex_transformations['+transIndex+'][type]"><option value="">-- Select --</option>...</select>' +
                         '<input type="text" name="filterflex_transformations['+transIndex+'][search]" style="display:none;">' +
                         // ... other inputs ...
                         '<button type="button" class="button-link filterflex-remove-transformation">Remove</button>' +
                         '</div>');
         }

         // Update name attributes
         $newRow.find('select, input').each(function() {
             const name = $(this).attr('name').replace(/\[\d+\]/, '[' + transIndex + ']');
             $(this).attr('name', name);
             $(this).val(''); // Clear values
         });
         $newRow.find('input').hide(); // Hide conditional inputs initially
         $newRow.appendTo($transContainer);
     });

     // Remove Transformation
     $transContainer.on('click', '.filterflex-remove-transformation', function() {
         $(this).closest('.filterflex-transformation-row').remove();
         updatePreview();
     });

     // Update preview when transformation inputs change
     $transContainer.on('change keyup', 'input', function() {
         // Use debounce in a real app
         updatePreview();
     });


    // --- Live Preview ---
    const $previewOutput = $('#filterflex-preview-output');

    const $filterableElementSelect = $('#filterflex-filterable-element'); // Get the select element

    function updatePreview() {
        // 1. Get the current builder pattern from the hidden input
        const pattern = $patternHiddenInput.val();

        // 2. Get the selected filterable element
        const filterableElement = $filterableElementSelect.val();
        console.log('Selected filterable element:', filterableElement); // Log the selected element

        // 3. Get the current transformation settings
        const transformations = [];
        $transContainer.find('.filterflex-transformation-row').each(function() {
            const $row = $(this);
            const type = $row.find('select[name$="[type]"]').val();
            if (type) {
                let transformation = { type: type };
                 if (type === 'search_replace') {
                     transformation.search = $row.find('input[name$="[search]"]').val();
                     transformation.replace = $row.find('input[name$="[replace]"]').val();
                 } else if (type === 'limit_words') {
                      transformation.limit = $row.find('input[name$="[limit]"]').val();
                 }
                 // Add other types
                transformations.push(transformation);
            }
        });

        // 3. Make AJAX request to get preview data (or process client-side if possible)
        // Using AJAX is generally better as PHP handles WP data access
        $.ajax({
             url: filterFlexData.ajax_url,
             type: 'POST',
             data: {
                 action: 'filterflex_get_preview', // Need to register this AJAX action
                 security_token: filterFlexData.preview_nonce, // Use a specific nonce for this action
                 pattern: pattern,
                 transformations: transformations,
                 filterable_element: filterableElement, // Pass the selected element
                 // Optionally pass sample post ID or context
             },
             beforeSend: function() {
                  $previewOutput.html('<i>Loading preview...</i>');
             },
             success: function(response) {
                 if (response.success && response.data) {
                     $previewOutput.html(response.data.preview || '<i>Preview unavailable</i>');
                 } else {
                     // Handle cases where success is false or data is missing
                     $previewOutput.html('<i>Error generating preview.</i>');
                     // Check if data and message exist before logging
                     const errorMessage = response.data?.message || 'Unknown error';
                     console.error('Preview Error:', errorMessage, response);
                 }
             },
             error: function(jqXHR, textStatus, errorThrown) {
                 $previewOutput.html('<i>AJAX error loading preview.</i>');
                 console.error('Preview AJAX Error:', textStatus, errorThrown);
             }
         });
    }

    // Initial preview update on load
    updatePreview();

    // Update preview when the filterable element changes
    $filterableElementSelect.on('change', function() {
        updatePreview();
    });

    // Add event handler for custom field meta input changes
    $builderVisualInput.on('input change', '.custom-field-meta-input', function() {
        updateHiddenPatternInput();
    });

    // Add event handler for date format select changes
    $builderVisualInput.on('change', '.date-format-select', function() {
        adjustSelectWidth($(this)); // Adjust width on change
        updateHiddenPatternInput();
    });

    // Initial adjustment for all existing date format selects on page load
    $('.date-format-select').each(function() {
        adjustSelectWidth($(this));
    });

    // --- Status Toggle ---
    if ($('.filterflex-status-toggle').length) {
        // Hide the default post status dropdown
        $('#post-status-select').hide();
        
        // Update the post status when the toggle changes
        $('.post-status-toggle').on('change', function() {
            const isActive = $(this).prop('checked');
            const newStatus = isActive ? 'publish' : 'draft';
            
            // Update all WordPress status fields
            $('#hidden-post-status').val(newStatus);
            $('#post-status-display').text(isActive ? 'Published' : 'Draft');
            
            // Update our custom status text
            $(this).closest('.filterflex-switch-wrapper').find('.filterflex-status-text').text(isActive ? 'Active' : 'Inactive');

            // If the save button says "Publish", update it to "Update" when status changes
            if ($('#publish').val() === 'Publish') {
                $('#publish').val('Update');
            }
        });

        // Add form submit handler to ensure status is preserved
        $('#post').on('submit', function() {
            const isActive = $('.post-status-toggle').prop('checked');
            const currentStatus = isActive ? 'publish' : 'draft';
            
            // Ensure the status is set correctly before submission
            $('input[name="post_status"]').val(currentStatus);
            $('#hidden-post-status').val(currentStatus);
            
            // Don't prevent form submission
            return true;
        });
    }

    // Make the builder area a droppable target
    $builderVisualInput.droppable({
        over: function(event, ui) {
            // Add the drag-over class on dragover
            $(this).addClass('drag-over');
        },
        out: function(event, ui) {
            // Remove the drag-over class on dragleave
            $(this).removeClass('drag-over');
        },
        drop: function(event, ui) {
            // Remove the drag-over class on drop
            $(this).removeClass('drag-over');
            // Remove any existing drop indicators
            $('.drop-indicator').remove();

            const $draggedItem = ui.draggable;
            const itemType = $draggedItem.data('tag-type');
            const itemValue = $draggedItem.data('tag-value');
            const itemLabel = $draggedItem.text().trim(); // Get the text content for label

            // Determine where to insert the new element
            const $target = $(event.target);

            // If dropping onto an existing builder item, insert before it.
            if ($target.hasClass('filterflex-builder-item')) {
                const $newElement = createBuilderElement(itemType, itemValue, itemLabel);
                $newElement.insertBefore($target);
            } else {
                // Otherwise, append to the end of the builder area.
                const $newElement = createBuilderElement(itemType, itemValue, itemLabel);
                $builderVisualInput.append($newElement);
            }

            updateHiddenPatternInput();
        }
    });

}); // End jQuery(document).ready
