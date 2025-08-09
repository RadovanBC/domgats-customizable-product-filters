(function ($, elementor) {
    $(window).on('elementor/frontend/init', function () {
        const DomGats_Filtered_Loop_Widget_Handler = function ($scope) {
            const self = this;
            self.$widgetContainer = $scope;
            self.$filtersWrapper = self.$widgetContainer.find('.dgcpf-filters-wrapper');
            self.$loopContainer = self.$widgetContainer.find('.dgcpf-loop-container');
            self.$loadMoreContainer = self.$widgetContainer.find('.dgcpf-load-more-container');
            self.$loadMoreButton = self.$loadMoreContainer.find('.dgcpf-load-more-button');
            self.$clearAllButton = self.$filtersWrapper.find('.dgcpf-clear-all-filters-button');
            self.settings = self.$widgetContainer.data('settings') || {};
            self.templateId = self.$widgetContainer.data('template-id');
            self.widgetId = self.$widgetContainer.data('widget-id');
            self.selectedTermsByTaxonomy = {};
            self.selectedAcfFields = {};
            self.currentPage = 1;
            self.maxPages = parseInt(self.$loadMoreButton.data('max-pages') || 1);
            self.isLoading = false;
            self.flickityInstance = null;

            self.init = function () {
                self.bindEvents();
                self.setupInitialFilterState();
                self.initializeCarousel();
                self.updateLoadMoreButtonVisibility();
                self.applyInitialFilterSelection();
            };

            self.bindEvents = function () {
                self.$filtersWrapper.on('change', '.dgcpf-filter-dropdown, .dgcpf-filter-checkbox, .dgcpf-filter-radio', self.onFilterChange);
                self.$filtersWrapper.on('input', '.dgcpf-filter-text-input, .dgcpf-filter-number-input', self.debounce(self.onFilterChange, 500));
                self.$loadMoreButton.on('click', self.onLoadMoreClick);
                self.$clearAllButton.on('click', self.onClearAllFiltersClick);
                if (self.settings.enable_history_api === 'yes') {
                    $(window).on('popstate', self.onPopState);
                }
            };

            self.debounce = function(func, delay) {
                let timeout;
                return function(...args) {
                    const context = this;
                    clearTimeout(timeout);
                    timeout = setTimeout(() => func.apply(context, args), delay);
                };
            };

            self.setupInitialFilterState = function () {
                self.selectedTermsByTaxonomy = {};
                self.selectedAcfFields = {};

                self.settings.filters_repeater.forEach(filter => {
                    if (filter.filter_type === 'taxonomy' && filter.taxonomy_name) {
                        self.selectedTermsByTaxonomy[filter.taxonomy_name] = [];
                    }
                });

                if (self.settings.enable_history_api === 'yes') {
                    const urlParams = new URLSearchParams(window.location.search);
                    urlParams.forEach((value, key) => {
                        if (key.startsWith('dgcpf_tax_')) {
                            const taxonomy = key.replace('dgcpf_tax_', '');
                            self.selectedTermsByTaxonomy[taxonomy] = value.split(',');
                        } else if (key.startsWith('dgcpf_acf_')) {
                            const acfFieldKey = key.replace('dgcpf_acf_', '');
                            self.selectedAcfFields[acfFieldKey] = value;
                        }
                    });
                }
            };

            self.applyInitialFilterSelection = function() {
                Object.keys(self.selectedTermsByTaxonomy).forEach(taxonomy => {
                    const terms = self.selectedTermsByTaxonomy[taxonomy];
                    const $group = self.$filtersWrapper.find(`[data-taxonomy="${taxonomy}"]`);
                    if ($group.length) {
                        if ($group.data('display-as') === 'dropdown') $group.find('select').val(terms[0] || '');
                        else if ($group.data('display-as') === 'checkbox') terms.forEach(term => $group.find(`input[value="${term}"]`).prop('checked', true));
                        else if ($group.data('display-as') === 'radio') $group.find(`input[value="${terms[0] || ''}"]`).prop('checked', true);
                    }
                });
                Object.keys(self.selectedAcfFields).forEach(fieldKey => {
                    const value = self.selectedAcfFields[fieldKey];
                    const $group = self.$filtersWrapper.find(`[data-acf-field-key="${fieldKey}"]`);
                    if ($group.length) {
                        if ($group.data('display-as') === 'dropdown' || $group.data('display-as') === 'text' || $group.data('display-as') === 'number') $group.find('input, select').val(value);
                        else if ($group.data('display-as') === 'checkbox') value.split(',').forEach(val => $group.find(`input[value="${val}"]`).prop('checked', true));
                        else if ($group.data('display-as') === 'radio') $group.find(`input[value="${value || ''}"]`).prop('checked', true);
                    }
                });
                self.fetchProducts(true);
            };

            self.onFilterChange = function () {
                const $input = $(this);
                const $group = $input.closest('[data-taxonomy], [data-acf-field-key]');
                const displayAs = $group.data('display-as');

                if ($group.data('taxonomy')) {
                    const taxonomy = $group.data('taxonomy');
                    if (displayAs === 'checkbox') {
                        self.selectedTermsByTaxonomy[taxonomy] = $group.find('input:checked').map((_, el) => $(el).val()).get();
                    } else {
                        self.selectedTermsByTaxonomy[taxonomy] = $input.val() ? [$input.val()] : [];
                    }
                } else if ($group.data('acf-field-key')) {
                    const fieldKey = $group.data('acf-field-key');
                    if (displayAs === 'checkbox') {
                        self.selectedAcfFields[fieldKey] = $group.find('input:checked').map((_, el) => $(el).val()).get();
                    } else {
                        self.selectedAcfFields[fieldKey] = $input.val();
                    }
                }
                
                self.currentPage = 1;
                self.fetchProducts();
            };

            self.onLoadMoreClick = function (e) {
                e.preventDefault();
                if (!self.isLoading && self.currentPage < self.maxPages) {
                    self.currentPage++;
                    self.fetchProducts(false, true);
                }
            };

            self.onClearAllFiltersClick = function (e) {
                e.preventDefault();
                self.$filtersWrapper.find('select').val('');
                self.$filtersWrapper.find('input[type="checkbox"], input[type="radio"]').prop('checked', false);
                self.$filtersWrapper.find('input[type="text"], input[type="number"]').val('');
                self.$filtersWrapper.find('input[type="radio"][value=""]').prop('checked', true);
                self.setupInitialFilterState();
                self.currentPage = 1;
                self.fetchProducts();
            };

            self.updateLoadMoreButtonVisibility = function () {
                const show = self.settings.layout_type === 'grid' && self.settings.enable_load_more === 'yes';
                if (show && self.currentPage < self.maxPages) {
                    self.$loadMoreButton.text(self.settings.load_more_button_text || 'Load More').prop('disabled', false);
                    self.$loadMoreContainer.show();
                } else if (show) {
                    self.$loadMoreButton.text(self.settings.no_more_products_text || 'No More Products').prop('disabled', true);
                    self.$loadMoreContainer.show();
                } else {
                    self.$loadMoreContainer.hide();
                }
            };

            self.updateClearAllButtonVisibility = function () {
                const hasTaxFilters = Object.values(self.selectedTermsByTaxonomy).some(t => t.length > 0);
                const hasAcfFilters = Object.values(self.selectedAcfFields).some(v => v && v.length > 0);
                self.$clearAllButton.toggle(hasTaxFilters || hasAcfFilters);
            };

            self.onPopState = function () {
                self.setupInitialFilterState();
                self.applyInitialFilterSelection();
            };

            self.updateUrl = function () {
                if (self.settings.enable_history_api !== 'yes') return;
                const url = new URL(window.location.href);
                url.search = ''; 
                Object.keys(self.selectedTermsByTaxonomy).forEach(tax => {
                    if (self.selectedTermsByTaxonomy[tax].length > 0) url.searchParams.set(`dgcpf_tax_${tax}`, self.selectedTermsByTaxonomy[tax].join(','));
                });
                Object.keys(self.selectedAcfFields).forEach(key => {
                    const value = self.selectedAcfFields[key];
                    if (value && value.length > 0) url.searchParams.set(`dgcpf_acf_${key}`, Array.isArray(value) ? value.join(',') : value);
                });
                window.history.pushState({}, '', url);
            };

            self.initializeCarousel = function () {
                if (self.settings.layout_type !== 'carousel' || !$.fn.flickity) return;
                self.destroyCarousel();
                const flickityOptions = {
                    cellSelector: '.elementor-loop-item',
                    prevNextButtons: self.settings.carousel_nav_buttons === 'yes',
                    pageDots: self.settings.carousel_page_dots === 'yes',
                    wrapAround: self.settings.carousel_wrap_around === 'yes',
                    adaptiveHeight: self.settings.carousel_adaptive_height === 'yes',
                    draggable: self.settings.carousel_draggable === 'yes',
                    groupCells: self.settings.carousel_slides_to_move > 1 ? self.settings.carousel_slides_to_move : false,
                    imagesLoaded: true,
                    rightToLeft: $('body').hasClass('rtl'),
                    cellAlign: self.settings.carousel_cell_align || 'left',
                    autoPlay: self.settings.carousel_autoplay === 'yes' ? (self.settings.carousel_autoplay_interval || 3000) : false,
                };
                self.flickityInstance = new Flickity(self.$loopContainer[0], flickityOptions);
            };

            self.destroyCarousel = function () {
                if (self.flickityInstance) {
                    self.flickityInstance.destroy();
                    self.flickityInstance = null;
                }
            };

            self.updateFilterOptionsState = function (availableFilterOptions) {
                self.updateClearAllButtonVisibility();
            };

            self.fetchProducts = function (isInitialLoad = false, appendResults = false) {
                if (self.isLoading) return;
                self.isLoading = true;
                self.$loopContainer.addClass('loading');

                $.ajax({
                    url: ahh_maa_filter_params.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'filter_products_by_tag',
                        nonce: ahh_maa_filter_params.nonce,
                        template_id: self.templateId,
                        page: self.currentPage,
                        ...self.settings,
                        selected_terms_by_taxonomy: self.selectedTermsByTaxonomy,
                        selected_acf_fields: self.selectedAcfFields,
                    },
                    success: function (response) {
                        if (response.success) {
                            self.maxPages = response.data.max_pages;
                            const newHtml = $(response.data.html);
                            if (self.settings.layout_type === 'carousel') {
                                self.destroyCarousel();
                                self.$loopContainer.html(newHtml);
                                self.initializeCarousel();
                            } else {
                                appendResults ? self.$loopContainer.append(newHtml) : self.$loopContainer.html(newHtml);
                            }
                            self.updateLoadMoreButtonVisibility();
                            self.updateFilterOptionsState(response.data.available_filter_options);
                            if (!isInitialLoad) self.updateUrl();
                        }
                    },
                    error: function () {
                        self.$loopContainer.html('<p class="dgcpf-error-message">Error loading products.</p>');
                    },
                    complete: function () {
                        self.isLoading = false;
                        self.$loopContainer.removeClass('loading');
                    }
                });
            };

            self.init();
        };

        elementor.frontend.hooks.addAction(
            'frontend/element_ready/dgcpf_filtered_loop.default',
            DomGats_Filtered_Loop_Widget_Handler
        );
    });
})(jQuery, window.elementor);
