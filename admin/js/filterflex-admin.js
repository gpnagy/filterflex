// admin/js/filterflex-admin.js
jQuery(document).ready(function($) {

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
                .attr('data-tag', value)
                .text(label);
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
                "__{{SPACE}}__": " ", // Use a placeholder for space value, display text is still a space
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
            // Ensure $select.val(value) is called *after* all options are appended
            // and only if 'value' is not undefined or null (it can be " " which is fine).
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

    // Function to update the hidden input with the structured pattern (JSON)
    function updateHiddenPatternInput() {
        const patternData = [];
        $builderVisualInput.find('.filterflex-builder-item').each(function() {
            const $item = $(this);
            if ($item.hasClass('filterflex-tag-item')) {
                patternData.push({ type: 'tag', value: $item.data('tag') });
            } else if ($item.hasClass('filterflex-text-input-wrapper')) {
                const value = $item.find('.filterflex-static-text-input').val();
                patternData.push({ type: 'text', value: value }); // Save even if empty, PHP will sanitize
            } else if ($item.hasClass('filterflex-separator-wrapper')) {
                const value = $item.find('.filterflex-separator-select').val();
                patternData.push({ type: 'separator', value: value });
            }
        });
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
                let label = item.value; // Default label from value
                // Attempt to find a more descriptive label from available tags
                const $foundTag = $availableTagsContainer.find(`.draggable-tag[data-tag-value="${item.value}"]`);
                if ($foundTag.length) {
                    label = $foundTag.text();
                } else {
                     // Fallback for tags like {filtered_element} if not in draggable list or for custom ones
                     label = item.value.replace(/[{}]/g, '').replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                }
                $builderVisualInput.append(createBuilderElement('tag', item.value, label));
            } else if (item.type === 'text') {
                $builderVisualInput.append(createBuilderElement('text', item.value));
            } else if (item.type === 'separator') {
                $builderVisualInput.append(createBuilderElement('separator', item.value));
            }
        });

        // No longer add an initial empty text input. Builder starts empty.
        // if ($builderVisualInput.children().length === 0) {
        //      $builderVisualInput.append(createBuilderTextInputElement());
        // }
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
        accept: '.draggable-tag', // Accept only the tags from the list
        hoverClass: 'filterflex-drag-over-builder', // Use existing class for visual feedback
        drop: function(event, ui) {
            // $(this) is the droppable element (#filterflex-builder-visual-input)
            const $droppableContainer = $(this);
            // ui.draggable is the original draggable element from the list
            const $draggedTag = ui.draggable;

            const tagValue = $draggedTag.data('tag-value');
            const tagLabel = $draggedTag.text();
            const tagType = $draggedTag.data('tag-type');

            let $newElement;
            if (tagType === 'text') {
                $newElement = createBuilderElement('text', '');
            } else if (tagType === 'separator') {
                $newElement = createBuilderElement('separator', ' '); // Default to space
            } else { // 'tag'
                // Prevent adding duplicate {filtered_element} tags
                if (tagValue === '{filtered_element}' && $droppableContainer.find('.filterflex-tag-item[data-tag="{filtered_element}"]').length > 0) {
                    // A {filtered_element} tag already exists, do not add another
                    return;
                }
                $newElement = createBuilderElement('tag', tagValue, tagLabel);
            }

            // Append the new element to the droppable container (the builder input)
            // Note: This appends to the end. If position matters, more complex logic involving ui.position is needed.
            // For now, let's append to the end. The user can then sort it.
            $droppableContainer.append($newElement);

            // Update the hidden input
            updateHiddenPatternInput();

            // Optional: Refresh sortable to recognize the new item if needed
            // $droppableContainer.sortable('refresh');
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

    function updatePreview() {
        // 1. Get the current builder pattern from the hidden input
        const pattern = $patternHiddenInput.val();

        // 2. Get the current transformation settings
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

});
