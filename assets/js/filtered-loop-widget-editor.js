(function($) {
    $(window).on('elementor:init', function() {
        elementor.hooks.addAction('panel/open_editor/widget/dgcpf_filtered_loop', function(panel, model, view) {

            function applyPreset() {
                const presetKey = model.get('settings').attributes.layout_preset;

                if (!presetKey || !DgcpfEditorData || !DgcpfEditorData.presets[presetKey]) {
                    return;
                }

                const presetSettings = DgcpfEditorData.presets[presetKey].settings;
                
                model.off('change:settings', onSettingsChange);

                Object.keys(presetSettings).forEach(function(key) {
                    model.setSetting(key, presetSettings[key]);
                });
                
                model.on('change:settings', onSettingsChange);

                model.setSetting('layout_preset', '');
            }

            function onSettingsChange(changedModel) {
                if (changedModel.changed.hasOwnProperty('layout_preset')) {
                    applyPreset();
                }
            }

            model.on('change:settings', onSettingsChange);
        });
    });
})(jQuery);
