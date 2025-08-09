(function($) {
    $(window).on('elementor:init', function() {
        elementor.hooks.addAction('panel/open_editor/widget/dgcpf_filtered_loop', function(panel, model) {

            function applyPreset(presetKey) {
                if (!presetKey || presetKey === 'custom' || !DgcpfEditorData || !DgcpfEditorData.presets[presetKey]) {
                    return;
                }
                const presetSettings = DgcpfEditorData.presets[presetKey].settings;
                
                elementor.helpers.isInAtomicAction = true;
                Object.keys(presetSettings).forEach(function(key) {
                    model.setSetting(key, presetSettings[key]);
                });
                elementor.helpers.isInAtomicAction = false;
                
                // We need to re-render the widget preview after applying settings
                elementor.channels.editor.run('document/render/widget', { model: model });
            }

            function checkCustomPreset() {
                if (elementor.helpers.isInAtomicAction) {
                    return;
                }
                const currentSettings = model.get('settings').attributes;
                const presetKey = currentSettings.layout_preset;

                if (!presetKey || presetKey === 'custom' || !DgcpfEditorData || !DgcpfEditorData.presets[presetKey]) {
                    return;
                }

                const presetSettings = DgcpfEditorData.presets[presetKey].settings;
                let isModified = false;

                for (const key in presetSettings) {
                    if (JSON.stringify(currentSettings[key]) !== JSON.stringify(presetSettings[key])) {
                        isModified = true;
                        break;
                    }
                }

                if (isModified) {
                    model.setSetting('layout_preset', 'custom');
                }
            }

            function onSettingsChange(changedModel) {
                const changedAttributes = changedModel.changed;
                if (changedAttributes.hasOwnProperty('layout_preset')) {
                    applyPreset(changedAttributes.layout_preset);
                } else {
                    checkCustomPreset();
                }
            }

            model.on('change:settings', onSettingsChange);
        });
    });
})(jQuery);
