(function ($) {

    function updateAcfRepeaterTitle($selectElement) {
        // Get the text of the selected option (e.g., "Color")
        const selectedText = $selectElement.find('option:selected').text();
        
        // Find the title element for this specific repeater item
        const $titleElement = $selectElement.closest('.elementor-repeater-row').find('.elementor-repeater-row-item-title');

        // If a valid option is selected, update the title. Otherwise, use a default.
        if (selectedText && $selectElement.val()) {
            $titleElement.text(selectedText);
        } else {
            $titleElement.text('ACF Filter'); // Default text
        }
    }

    function onPanelOpen($panel) {
        // Set initial titles when the editor panel is first opened
        $panel.find('select[data-setting="acf_meta_key"]').each(function () {
            updateAcfRepeaterTitle($(this));
        });

        // Add a delegated event listener for when the select is changed
        $panel.on('change', 'select[data-setting="acf_meta_key"]', function () {
            updateAcfRepeaterTitle($(this));
        });
        
        // Also handle when a new repeater item is added
        $panel.on('repeater:add', function() {
            setTimeout(function() {
                $panel.find('select[data-setting="acf_meta_key"]').each(function () {
                    if ($(this).closest('.elementor-repeater-row').find('.elementor-repeater-row-item-title').text() !== $(this).find('option:selected').text()) {
                        updateAcfRepeaterTitle($(this));
                    }
                });
            }, 100);
        });
    }

    $(window).on('elementor:init', function () {
        elementor.hooks.addAction('panel/open_editor/widget/dgcpf_filtered_loop', function (panel, model, view) {
            onPanelOpen(panel.$el);
        });
    });

})(jQuery);