(function($) {
    $(window).on('elementor:init', function() {
        elementor.hooks.addAction('panel/open_editor/widget/dgcpf_filtered_loop', function (panel, model)
        {
            
            let isApplyingPreset = false;

            function applyPreset(presetKey) {
                if (!presetKey || presetKey === 'custom' || !DgcpfEditorData || !DgcpfEditorData.presets[presetKey]) {
                    return;
                }
                const presetSettings = DgcpfEditorData.presets[presetKey].settings;
                
                isApplyingPreset = true;
                
                elementor.history.history.startAtomic();
                
                Object.keys(presetSettings).forEach(function(key) {
                    model.setSetting(key, presetSettings[key]);
                });

                elementor.history.history.endAtomic();
                
                setTimeout(function() {
                    isApplyingPreset = false;
                }, 10);
            }

            function onSettingsChange(changedModel) {
                if (isApplyingPreset) {
                    return;
                }

                const changedAttributes = changedModel.changed;

                if (changedAttributes.hasOwnProperty('layout_preset')) {
                    applyPreset(changedAttributes.layout_preset);
                } else {
                    if (model.getSetting('layout_preset') !== 'custom') {
                        model.setSetting('layout_preset', 'custom', { silent: true });
                        const controlView = panel.getControlView('layout_preset');
                        if (controlView) {
                            controlView.render();
                        }
                    }
                }
            }

            model.on('change:settings', onSettingsChange);

            // --- Start ACF Meta Query Repeater Logic ---
            panel.on('change', 'select[data-setting="acf_meta_key"]', function() {
                const $select = $(this);
                console.log($(this));
                alert($(this).text());
                const selectedLabel = $select.find('option:selected').text();
                const repeaterItemIndex = $select.closest('.elementor-repeater-row').data('model-cid');
                
                // Find the repeater item model
                const repeaterItems = model.get('acf_meta_query_repeater');
                const currentItem = repeaterItems.findWhere({ cid: repeaterItemIndex });

                if (currentItem) {
                    currentItem.set('acf_field_label', selectedLabel);
                }
            });
            // --- End ACF Meta Query Repeater Logic ---
        });
    });
})(jQuery);