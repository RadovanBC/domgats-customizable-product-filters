/*
 * START: DomGats Filtered Loop Elementor Widget Editor Handler v1.3.13
 */
(function ($) {
    /**
     * This script handles the editor-side logic for the DomGats Filtered Loop widget,
     * specifically for applying layout presets.
     */
    const DgcpfEditor = {
        /**
         * Initializes the editor hooks for the widget.
         */
        init: function () {
            // Hook into the Elementor editor's initialization process.
            elementor.hooks.addAction('panel/open_editor/widget/dgcpf_filtered_loop', DgcpfEditor.onPanelOpen);
        },

        /**
         * Callback function executed when the widget's editor panel is opened.
         *
         * @param {object} panel - The editor panel object.
         * @param {object} model - The widget's data model.
         */
        onPanelOpen: function (panel, model) {
            const layoutPresetControl = panel.$el.find('select[data-setting="layout_preset"]');

            // Bind the change event to the layout_preset control.
            layoutPresetControl.on('change', function () {
                DgcpfEditor.applyPreset(model);
            });
        },

        /**
         * Applies the selected layout preset's settings to the widget's controls.
         *
         * @param {object} model - The widget's data model.
         */
        applyPreset: function (model) {
            const presetKey = model.get('settings').attributes.layout_preset;
            if (!presetKey) {
                return; // Do nothing if no preset is selected.
            }

            // Find the widget view to get the preset data from the data attribute.
            const widgetView = elementor.getpreviewView().children.findByModelCid(model.cid);
            if (!widgetView || !widgetView.el) {
                console.warn('DGCPF Editor: Widget view not found.');
                return;
            }

            const presetsData = $(widgetView.el).data('layout-presets');
            if (!presetsData || !presetsData[presetKey]) {
                console.warn('DGCPF Editor: Layout presets data not found or invalid key.');
                return;
            }

            const presetSettings = presetsData[presetKey].settings;
            const newSettings = {};

            // Prepare the settings object for the Elementor model.
            for (const settingKey in presetSettings) {
                if (presetSettings.hasOwnProperty(settingKey)) {
                    newSettings[settingKey] = presetSettings[settingKey];
                }
            }

            // Use Elementor's API to update the widget settings.
            // This will automatically update the controls in the panel.
            elementor.channels.editor.request('document/elements/settings', {
                container: model.getContainer(),
                settings: newSettings,
                options: {
                    external: true // This ensures the change is registered properly in the editor history.
                }
            });

            // After applying, it's good practice to reset the preset dropdown
            // so it can be selected again if needed.
            setTimeout(() => {
                elementor.channels.editor.request('document/elements/settings', {
                    container: model.getContainer(),
                    settings: { layout_preset: '' },
                    options: { external: true }
                });
            }, 100);
        }
    };

    // Initialize the editor script when the document is ready.
    $(document).ready(DgcpfEditor.init);

})(jQuery);
/*
 * END: DomGats Filtered Loop Elementor Widget Editor Handler v1.3.13
 */
