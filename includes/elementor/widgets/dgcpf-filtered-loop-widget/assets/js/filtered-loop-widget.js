/*
 * START: DomGats Filtered Loop Elementor Widget Frontend Handler v1.3.1
 */
(function ($, elementor) {

    // Ensure Elementor frontend is ready.
    $(window).on('elementor/frontend/init', function () {

        /**
         * DomGats_Filtered_Loop_Widget_Handler
         *
         * This class handles the frontend logic for the DomGats Filtered Loop Elementor Widget.
         * It manages filter interactions, AJAX calls, UI updates, and carousel initialization.
         */
        const DomGats_Filtered_Loop_Widget_Handler = function ($scope) {
            const self = this;

            // Cache jQuery elements for performance.
            self.$widgetContainer = $scope;
            self.$filtersWrapper = self.$widgetContainer.find('.dgcpf-filters-wrapper');
            self.$loopContainer = self.$widgetContainer.find('.dgcpf-loop-container');
            self.$loadMoreContainer = self.$widgetContainer.find('.dgcpf-load-more-container');
            self.$loadMoreButton = self.$loadMoreContainer.find('.dgcpf-load-more-button');
            self.$clearAllButton = self.$filtersWrapper.find('.dgcpf-clear-all-filters-button');


            // Retrieve widget settings from data attributes.
            self.settings = self.$widgetContainer.data('settings') || {};
            self.templateId = self.$widgetContainer.data('template-id');
            self.widgetId = self.$widgetContainer.data('widget-id');

            // Internal state variables.
            self.selectedTermsByTaxonomy = {}; // Stores selected terms for each taxonomy. e.g., { 'product_tag': ['tag-slug-1', 'tag-slug-2'] }
            self.selectedAcfFields = {}; // Stores selected values for ACF fields. e.g., { 'my_text_field': 'some text', 'my_select_field': 'option-value' }
            self.currentPage = 1;
            self.maxPages = parseInt(self.$loadMoreButton.data('max-pages') || 1);
            self.isLoading = false;
            self.flickityInstance = null; // To store the Flickity carousel instance.

            /**
             * Initializes the handler.
             * Binds events and performs initial setup.
             */
            self.init = function () {
                self.bindEvents();
                self.setupInitialFilterState();
                self.initializeCarousel(); // Initialize carousel on load if applicable.
                self.updateLoadMoreButtonVisibility();
                self.applyInitialFilterSelection(); // Apply selections from URL/initial state
            };

            /**
             * Binds all necessary event listeners.
             */
            self.bindEvents = function () {
                // Event for dropdown filters (taxonomy and ACF).
                self.$filtersWrapper.on('change', '.dgcpf-filter-dropdown', self.onFilterChange);
                // Event for checkbox filters (taxonomy and ACF).
                self.$filtersWrapper.on('change', '.dgcpf-filter-checkbox', self.onFilterChange);
                // Event for radio button filters (taxonomy and ACF).
                self.$filtersWrapper.on('change', '.dgcpf-filter-radio', self.onFilterChange);
                // Event for text/number input filters (ACF).
                self.$filtersWrapper.on('input', '.dgcpf-filter-text-input, .dgcpf-filter-number-input', self.onFilterChangeDebounced);
                // Event for load more button.
                self.$loadMoreButton.on('click', self.onLoadMoreClick);
                // Event for clear all filters button.
                self.$clearAllButton.on('click', self.onClearAllFiltersClick);
                // Listen for Elementor's refresh event in editor.
                elementor.hooks.addAction('panel/open_editor/widget/dgcpf_filtered_loop', function (panel, model, view) {
                    // Re-initialize if widget settings change in editor.
                    self.destroyCarousel();
                    self.init();
                    self.fetchProducts(true); // Re-fetch products on editor refresh
                });

                // History API: Listen for browser back/forward buttons.
                if (self.settings.enable_history_api === 'yes' && window.history && window.history.pushState) {
                    $(window).on('popstate', self.onPopState);
                }
            };

            /**
             * Debounce function for input fields to prevent excessive AJAX calls.
             * @param {function} func - The function to debounce.
             * @param {number} delay - The delay in milliseconds.
             * @returns {function} - The debounced function.
             */
            self.debounce = function(func, delay) {
                let timeout;
                return function(...args) {
                    const context = this;
                    clearTimeout(timeout);
                    timeout = setTimeout(() => func.apply(context, args), delay);
                };
            };

            self.onFilterChangeDebounced = self.debounce(self.onFilterChange, 500); // Debounce text/number inputs by 500ms

            /**
             * Sets up the initial state of the filters based on URL parameters (if History API is enabled).
             * This runs once on widget initialization.
             */
            self.setupInitialFilterState = function () {
                self.selectedTermsByTaxonomy = {}; // Reset state
                self.selectedAcfFields = {}; // Reset state

                // Initialize selectedTermsByTaxonomy structure for all taxonomy filters
                self.settings.filters_repeater.forEach(filter => {
                    if (filter.filter_type === 'taxonomy') {
                        const taxonomyName = filter.taxonomy_name;
                        if (taxonomyName) {
                            self.selectedTermsByTaxonomy[taxonomyName] = [];
                        }
                    }
                });

                if (self.settings.enable_history_api === 'yes' && window.location.search) {
                    const urlParams = new URLSearchParams(window.location.search);
                    self.$filtersWrapper.find('.dgcpf-filter-group [data-filter-name]').each(function() { // Target inner div with data-filter-name
                        const $groupInner = $(this);
                        const filterName = $groupInner.data('filter-name'); // e.g., dgcpf_tax_product_tag or dgcpf_acf_my_field

                        if (filterName && urlParams.has(filterName)) {
                            const filterValue = urlParams.get(filterName);

                            if (filterName.startsWith('dgcpf_tax_')) {
                                const taxonomy = $groupInner.data('taxonomy');
                                const termsInUrl = filterValue.split(',').map(s => s.trim()).filter(s => s);
                                if (termsInUrl.length > 0) {
                                    self.selectedTermsByTaxonomy[taxonomy] = termsInUrl;
                                }
                            } else if (filterName.startsWith('dgcpf_acf_')) {
                                const acfFieldKey = $groupInner.data('acf-field-key');
                                self.selectedAcfFields[acfFieldKey] = filterValue;
                            }
                        }
                    });
                }
            };

            /**
             * Applies the initial filter selections to the UI elements.
             * This is called after setupInitialFilterState.
             */
            self.applyInitialFilterSelection = function() {
                // Apply Taxonomy selections
                for (const taxonomy in self.selectedTermsByTaxonomy) {
                    const selectedTerms = self.selectedTermsByTaxonomy[taxonomy];
                    const $filterGroup = self.$filtersWrapper.find('.dgcpf-filter-group [data-taxonomy="' + taxonomy + '"]'); // Target the inner div with data-taxonomy
                    const displayAs = $filterGroup.data('display-as');

                    if (displayAs === 'dropdown') {
                        $filterGroup.find('.dgcpf-filter-dropdown').val(selectedTerms.length > 0 ? selectedTerms[0] : '');
                    } else if (displayAs === 'checkbox') {
                        $filterGroup.find('.dgcpf-filter-checkbox').prop('checked', false); // Uncheck all first
                        selectedTerms.forEach(term => {
                            $filterGroup.find('.dgcpf-filter-checkbox[value="' + term + '"]').prop('checked', true);
                        });
                    } else if (displayAs === 'radio') {
                        $filterGroup.find('.dgcpf-filter-radio').prop('checked', false); // Uncheck all first
                        if (selectedTerms.length > 0) {
                            $filterGroup.find('.dgcpf-filter-radio[value="' + selectedTerms[0] + '"]').prop('checked', true);
                        } else {
                            $filterGroup.find('.dgcpf-filter-radio[value=""]').prop('checked', true); // Select 'All'
                        }
                    }
                }

                // Apply ACF selections
                for (const acfFieldKey in self.selectedAcfFields) {
                    const selectedValue = self.selectedAcfFields[acfFieldKey];
                    const $filterGroup = self.$filtersWrapper.find('.dgcpf-filter-group [data-acf-field-key="' + acfFieldKey + '"]'); // Target the inner div with data-acf-field-key
                    const displayAs = $filterGroup.data('display-as');

                    if (displayAs === 'dropdown') {
                        $filterGroup.find('.dgcpf-filter-dropdown').val(selectedValue);
                    } else if (displayAs === 'checkbox') {
                        $filterGroup.find('.dgcpf-filter-checkbox').prop('checked', false);
                        // Assuming selectedValue for checkbox is a comma-separated string or array
                        const values = Array.isArray(selectedValue) ? selectedValue : selectedValue.split(',').map(s => s.trim()).filter(s => s);
                        values.forEach(val => {
                            $filterGroup.find('.dgcpf-filter-checkbox[value="' + val + '"]').prop('checked', true);
                        });
                    } else if (displayAs === 'radio') {
                        $filterGroup.find('.dgcpf-filter-radio').prop('checked', false);
                        $filterGroup.find('.dgcpf-filter-radio[value="' + selectedValue + '"]').prop('checked', true);
                    } else if (displayAs === 'text') {
                        $filterGroup.find('.dgcpf-filter-text-input').val(selectedValue);
                    } else if (displayAs === 'number') {
                        $filterGroup.find('.dgcpf-filter-number-input').val(selectedValue);
                    }
                }

                // Initial fetch based on parsed URL or default state.
                self.fetchProducts(true);
            };

            /**
             * Handles changes to any filter element (dropdown, checkbox, radio, text, number).
             */
            self.onFilterChange = function () {
                const $this = $(this);
                const $filterGroupInner = $this.closest('[data-filter-name]'); // Find the inner div with filter-name
                const filterType = $filterGroupInner.closest('.dgcpf-filter-group').data('filter-type');
                const displayAs = $filterGroupInner.data('display-as');

                if (filterType === 'taxonomy') {
                    const taxonomy = $filterGroupInner.data('taxonomy');
                    if (displayAs === 'dropdown') {
                        self.selectedTermsByTaxonomy[taxonomy] = $this.val() ? [$this.val()] : [];
                    } else if (displayAs === 'checkbox') {
                        self.selectedTermsByTaxonomy[taxonomy] = [];
                        $filterGroupInner.find('.dgcpf-filter-checkbox:checked').each(function() {
                            self.selectedTermsByTaxonomy[taxonomy].push($(this).val());
                        });
                    } else if (displayAs === 'radio') {
                        self.selectedTermsByTaxonomy[taxonomy] = $this.val() ? [$this.val()] : [];
                    }
                } else if (filterType === 'acf') {
                    const acfFieldKey = $filterGroupInner.data('acf-field-key');
                    if (displayAs === 'dropdown' || displayAs === 'text' || displayAs === 'number' || displayAs === 'radio') {
                        self.selectedAcfFields[acfFieldKey] = $this.val();
                    } else if (displayAs === 'checkbox') {
                        const selectedValues = [];
                        $filterGroupInner.find('.dgcpf-filter-checkbox:checked').each(function() {
                            selectedValues.push($(this).val());
                        });
                        self.selectedAcfFields[acfFieldKey] = selectedValues;
                    }
                }
                
                self.currentPage = 1; // Reset to first page on filter change.
                self.fetchProducts(); // Fetch new products.
            };

            /**
             * Handles the click event for the "Load More" button.
             */
            self.onLoadMoreClick = function (e) {
                e.preventDefault();
                if (!self.isLoading && self.currentPage < self.maxPages) {
                    self.currentPage++;
                    self.fetchProducts(false, true); // Fetch next page, append results.
                }
            };

            /**
             * Handles the click event for the "Clear All Filters" button.
             */
            self.onClearAllFiltersClick = function (e) {
                e.preventDefault();
                self.clearAllFilters();
            };

            /**
             * Clears all active filters and re-fetches products.
             */
            self.clearAllFilters = function () {
                // Clear selected taxonomy terms
                for (const taxonomy in self.selectedTermsByTaxonomy) {
                    self.selectedTermsByTaxonomy[taxonomy] = [];
                    const $filterGroup = self.$filtersWrapper.find('.dgcpf-filter-group [data-taxonomy="' + taxonomy + '"]');
                    const displayAs = $filterGroup.data('display-as');
                    if (displayAs === 'dropdown') {
                        $filterGroup.find('.dgcpf-filter-dropdown').val(''); // Reset dropdown
                    } else if (displayAs === 'checkbox') {
                        $filterGroup.find('.dgcpf-filter-checkbox').prop('checked', false); // Uncheck all
                    } else if (displayAs === 'radio') {
                        $filterGroup.find('.dgcpf-filter-radio[value=""]').prop('checked', true); // Select "All" option
                    }
                }

                // Clear selected ACF field values
                for (const acfFieldKey in self.selectedAcfFields) {
                    self.selectedAcfFields[acfFieldKey] = ''; // Clear value
                    const $filterGroup = self.$filtersWrapper.find('.dgcpf-filter-group [data-acf-field-key="' + acfFieldKey + '"]');
                    const displayAs = $filterGroup.data('display-as');
                    if (displayAs === 'dropdown') {
                        $filterGroup.find('.dgcpf-filter-dropdown').val('');
                    } else if (displayAs === 'checkbox') {
                        $filterGroup.find('.dgcpf-filter-checkbox').prop('checked', false);
                    } else if (displayAs === 'radio') {
                        $filterGroup.find('.dgcpf-filter-radio[value=""]').prop('checked', true);
                    } else if (displayAs === 'text' || displayAs === 'number') {
                        $filterGroup.find('.dgcpf-filter-text-input, .dgcpf-filter-number-input').val('');
                    }
                }

                self.currentPage = 1; // Reset to first page
                self.fetchProducts(); // Fetch products with no filters
                self.updateClearAllButtonVisibility(); // Hide button
            };

            /**
             * Updates the visibility of the "Clear All Filters" button.
             */
            self.updateClearAllButtonVisibility = function () {
                const hasActiveTaxonomyFilters = Object.values(self.selectedTermsByTaxonomy).some(terms => terms.length > 0);
                const hasActiveAcfFilters = Object.values(self.selectedAcfFields).some(value => value !== null && value !== undefined && value !== '' && (Array.isArray(value) ? value.length > 0 : true));

                if (hasActiveTaxonomyFilters || hasActiveAcfFilters) {
                    self.$clearAllButton.show();
                } else {
                    self.$clearAllButton.hide();
                }
            };

            /**
             * Handles browser history changes (back/forward buttons).
             */
            self.onPopState = function (event) {
                // Only act if the popstate event is related to our widget's URL changes.
                // A simple check is to see if our filter parameters are in the URL.
                const urlParams = new URLSearchParams(window.location.search);
                let hasOurParams = false;
                self.$filtersWrapper.find('.dgcpf-filter-group [data-filter-name]').each(function() {
                    const filterName = $(this).data('filter-name');
                    if (filterName) { // Check if filterName exists before trying to use it
                        if (urlParams.has(filterName)) {
                            hasOurParams = true;
                            return false; // Break loop
                        }
                    }
                });

                // Also check if there are no params but we previously had filters applied (clearing filters via back button)
                const hasActiveFilters = Object.keys(self.selectedTermsByTaxonomy).some(key => self.selectedTermsByTaxonomy[key].length > 0) ||
                                         Object.keys(self.selectedAcfFields).some(key => self.selectedAcfFields[key]);

                if (hasOurParams || (hasActiveFilters && !urlParams.toString())) {
                    // Re-apply filters from URL and fetch products.
                    self.setupInitialFilterState(); // Reparse URL
                    self.applyInitialFilterSelection(); // Update UI and fetch
                }
            };

            /**
             * Updates the browser URL with current filter parameters.
             */
            self.updateUrl = function () {
                if (self.settings.enable_history_api !== 'yes' || !window.history || !window.history.pushState) {
                    return;
                }

                const url = new URL(window.location.href);
                // Clear existing filter parameters to avoid duplicates.
                self.$filtersWrapper.find('.dgcpf-filter-group [data-filter-name]').each(function() {
                    const filterName = $(this).data('filter-name');
                    if (filterName) {
                        url.searchParams.delete(filterName);
                    }
                });
                url.searchParams.delete('dgcpf_page'); // Remove page parameter

                // Add current filter parameters for taxonomies.
                for (const taxonomy in self.selectedTermsByTaxonomy) {
                    const selectedTerms = self.selectedTermsByTaxonomy[taxonomy];
                    if (selectedTerms.length > 0) {
                        const $filterGroup = self.$filtersWrapper.find('.dgcpf-filter-group [data-taxonomy="' + taxonomy + '"]');
                        const filterName = $filterGroup.data('filter-name');
                        if (filterName) {
                            url.searchParams.set(filterName, selectedTerms.join(','));
                        }
                    }
                }

                // Add current filter parameters for ACF fields.
                for (const acfFieldKey in self.selectedAcfFields) {
                    const selectedValue = self.selectedAcfFields[acfFieldKey];
                    if (selectedValue !== null && selectedValue !== undefined && selectedValue !== '' && (Array.isArray(selectedValue) ? selectedValue.length > 0 : true)) {
                        const $filterGroup = self.$filtersWrapper.find('.dgcpf-filter-group [data-acf-field-key="' + acfFieldKey + '"]');
                        const filterName = $filterGroup.data('filter-name');
                        if (filterName) {
                             // For checkboxes, join array values with comma
                            const valueToSet = Array.isArray(selectedValue) ? selectedValue.join(',') : selectedValue;
                            url.searchParams.set(filterName, valueToSet);
                        }
                    }
                }

                // Add current page if not the first page and load more is enabled.
                if (self.currentPage > 1 && self.settings.enable_load_more === 'yes') {
                    url.searchParams.set('dgcpf_page', self.currentPage);
                }

                // Use replaceState if it's the initial load or a page refresh, pushState for filter changes.
                // For simplicity, we'll use pushState for all changes for now, but replaceState is better for initial load.
                window.history.pushState(self.selectedTermsByTaxonomy, '', url.toString());
            };

            /**
             * Initializes the Flickity carousel if the layout is set to carousel.
             */
            self.initializeCarousel = function () {
                if (self.settings.layout_type === 'carousel' && $.fn.flickity) {
                    // Destroy existing instance before re-initializing.
                    self.destroyCarousel();
                    
                    const columns = self.settings.columns_carousel || 3; // Default to 3 if not set.
                    const isRTL = $('body').hasClass('rtl'); // Check for RTL support.

                    // Construct Flickity options from widget settings
                    const flickityOptions = {
                        cellSelector: '.elementor-loop-item',
                        prevNextButtons: self.settings.carousel_nav_buttons === 'yes',
                        pageDots: self.settings.carousel_page_dots === 'yes',
                        wrapAround: self.settings.carousel_wrap_around === 'yes',
                        adaptiveHeight: self.settings.carousel_adaptive_height === 'yes',
                        draggable: self.settings.carousel_draggable === 'yes',
                        groupCells: columns, // Use responsive columns setting
                        imagesLoaded: true, // Always wait for images to load.
                        rightToLeft: isRTL, // RTL support.
                        cellAlign: self.settings.carousel_cell_align || 'left',
                    };

                    // Add autoplay if enabled
                    if (self.settings.carousel_autoplay === 'yes') {
                        flickityOptions.autoPlay = self.settings.carousel_autoplay_interval || 3000;
                    } else {
                        flickityOptions.autoPlay = false; // Explicitly disable if not 'yes'
                    }

                    self.flickityInstance = new Flickity(self.$loopContainer[0], flickityOptions);
                }
            };

            /**
             * Destroys the current Flickity carousel instance.
             */
            self.destroyCarousel = function () {
                if (self.flickityInstance) {
                    self.flickityInstance.destroy();
                    self.flickityInstance = null;
                }
            };

            /**
             * Updates the visibility of the "Load More" button.
             */
            self.updateLoadMoreButtonVisibility = function () {
                if (self.settings.layout_type === 'grid' && self.settings.enable_load_more === 'yes' && self.currentPage < self.maxPages) {
                    self.$loadMoreContainer.show();
                } else {
                    self.$loadMoreContainer.hide();
                }
            };

            /**
             * Updates the state of filter options (e.g., enable/disable based on available products).
             * Also updates dynamic counts.
             * @param {object} availableFilterOptions - An object mapping filter keys (taxonomy/ACF field) to their available options.
             */
            self.updateFilterOptionsState = function (availableFilterOptions) {
                // Handle Taxonomy filters
                self.$filtersWrapper.find('.dgcpf-filter-group [data-taxonomy]').each(function() {
                    const $groupInner = $(this);
                    const taxonomy = $groupInner.data('taxonomy');
                    const displayAs = $groupInner.data('display-as');
                    const availableTerms = availableFilterOptions[taxonomy] || {};

                    if (displayAs === 'dropdown') {
                        $groupInner.find('option').each(function() {
                            const $option = $(this);
                            const termSlug = $option.val();
                            if (termSlug === '') { // "All" option
                                $option.prop('disabled', false);
                                return true; // Continue to next option
                            }
                            if (availableTerms.hasOwnProperty(termSlug) && availableTerms[termSlug].count > 0) {
                                $option.prop('disabled', false).text(availableTerms[termSlug].name + ' (' + availableTerms[termSlug].count + ')');
                            } else {
                                $option.prop('disabled', true).text($option.text().split(' (')[0] + ' (0)');
                            }
                        });
                    } else if (displayAs === 'checkbox' || displayAs === 'radio') {
                        $groupInner.find('label').each(function() {
                            const $label = $(this);
                            const $input = $label.find('input');
                            const termSlug = $input.val();

                            if (termSlug === '' && displayAs === 'radio') { // "All" option for radio
                                $label.removeClass('disabled');
                                $input.prop('disabled', false);
                                return true;
                            }

                            if (availableTerms.hasOwnProperty(termSlug) && availableTerms[termSlug].count > 0) {
                                $label.removeClass('disabled');
                                $input.prop('disabled', false);
                                $label.find('span').text(availableTerms[termSlug].name + ' (' + availableTerms[termSlug].count + ')');
                            } else {
                                // Keep selected checkboxes enabled but show 0 count
                                if ($input.is(':checked')) {
                                    $label.removeClass('disabled'); // Don't disable selected
                                    $input.prop('disabled', false);
                                } else {
                                    $label.addClass('disabled');
                                    $input.prop('disabled', true);
                                }
                                $label.find('span').text($label.find('span').text().split(' (')[0] + ' (0)');
                            }
                        });
                    }
                });

                // Handle ACF filters
                self.$filtersWrapper.find('.dgcpf-filter-group [data-acf-field-key]').each(function() {
                    const $groupInner = $(this);
                    const acfFieldKey = $groupInner.data('acf-field-key');
                    const displayAs = $groupInner.data('display-as');
                    const acfFieldType = $groupInner.data('acf-field-type');
                    const availableAcfOptions = availableFilterOptions[acfFieldKey] || {};

                    if (displayAs === 'dropdown' && (acfFieldType === 'select' || acfFieldType === 'radio' || acfFieldType === 'checkbox' || acfFieldType === 'true_false')) {
                        $groupInner.find('option').each(function() {
                            const $option = $(this);
                            const value = $option.val();
                            if (value === '') { // "All" option
                                $option.prop('disabled', false);
                                return true;
                            }
                            if (availableAcfOptions.values && availableAcfOptions.values.hasOwnProperty(value) && availableAcfOptions.values[value].count > 0) {
                                $option.prop('disabled', false).text(availableAcfOptions.values[value].name + ' (' + availableAcfOptions.values[value].count + ')');
                            } else {
                                $option.prop('disabled', true).text($option.text().split(' (')[0] + ' (0)');
                            }
                        });
                    } else if ((displayAs === 'checkbox' || displayAs === 'radio') && (acfFieldType === 'checkbox' || acfFieldType === 'select' || acfFieldType === 'radio' || acfFieldType === 'true_false')) {
                        $groupInner.find('label').each(function() {
                            const $label = $(this);
                            const $input = $label.find('input');
                            const value = $input.val();

                            if (value === '' && displayAs === 'radio') { // "All" option for radio
                                $label.removeClass('disabled');
                                $input.prop('disabled', false);
                                return true;
                            }

                            if (availableAcfOptions.values && availableAcfOptions.values.hasOwnProperty(value) && availableAcfOptions.values[value].count > 0) {
                                $label.removeClass('disabled');
                                $input.prop('disabled', false);
                                $label.find('span').text(availableAcfOptions.values[value].name + ' (' + availableAcfOptions.values[value].count + ')');
                            } else {
                                // Keep selected checkboxes enabled but show 0 count
                                if ($input.is(':checked')) {
                                    $label.removeClass('disabled'); // Don't disable selected
                                    $input.prop('disabled', false);
                                } else {
                                    $label.addClass('disabled');
                                    $input.prop('disabled', true);
                                }
                                $label.find('span').text($label.find('span').text().split(' (')[0] + ' (0)');
                            }
                        });
                    }
                    // Text and number inputs don't have discrete options to enable/disable or count.
                });

                self.updateClearAllButtonVisibility(); // Update clear button visibility after filter state is updated
            };

            /**
             * Fetches products via AJAX based on current filters and pagination.
             * @param {boolean} isInitialLoad - True if this is the first load of the widget.
             * @param {boolean} appendResults - True if new results should be appended (for Load More).
             */
            self.fetchProducts = function (isInitialLoad = false, appendResults = false) {
                if (self.isLoading) return; // Prevent multiple concurrent requests.
                self.isLoading = true;
                self.$loopContainer.addClass('loading'); // Add loading indicator.
                self.$loopContainer.attr('aria-live', 'polite').attr('aria-busy', 'true'); // Accessibility: Announce loading

                // Prepare filters_data from widget settings (repeater control).
                const filtersData = self.settings.filters_repeater.map(filter => ({
                    filter_type: filter.filter_type,
                    taxonomy_name: filter.taxonomy_name,
                    acf_field_key: filter.acf_field_key,
                    display_as: filter.display_as,
                }));

                // Prepare selected terms for AJAX.
                const selectedTermsForAjax = {};
                for (const taxonomy in self.selectedTermsByTaxonomy) {
                    if (self.selectedTermsByTaxonomy[taxonomy].length > 0) {
                        selectedTermsForAjax[taxonomy] = self.selectedTermsByTaxonomy[taxonomy];
                    }
                }

                // Prepare selected ACF fields for AJAX.
                const selectedAcfFieldsForAjax = {};
                for (const acfFieldKey in self.selectedAcfFields) {
                    const value = self.selectedAcfFields[acfFieldKey];
                    if (value !== null && value !== undefined && value !== '' && (Array.isArray(value) ? value.length > 0 : true)) {
                        selectedAcfFieldsForAjax[acfFieldKey] = value;
                    }
                }

                $.ajax({
                    url: ahh_maa_filter_params.ajax_url, // Global AJAX URL from main plugin file.
                    type: 'POST',
                    data: {
                        action: 'filter_products_by_tag',
                        nonce: ahh_maa_filter_params.nonce,
                        template_id: self.templateId,
                        post_type: self.settings.post_type,
                        posts_include_by_ids: self.settings.posts_include_by_ids,
                        posts_exclude_by_ids: self.settings.posts_exclude_by_ids,
                        terms_include: self.settings.terms_include,
                        terms_exclude: self.settings.terms_exclude,
                        post_status: self.settings.post_status,
                        orderby: self.settings.orderby,
                        order: self.settings.order,
                        filters_data: filtersData, // Send repeater data
                        filter_logic: self.settings.filter_logic,
                        selected_terms_by_taxonomy: selectedTermsForAjax,
                        selected_acf_fields: selectedAcfFieldsForAjax,
                        page: self.currentPage,
                        posts_per_page: self.settings.posts_per_page,
                    },
                    success: function (response) {
                        if (response.success) {
                            self.maxPages = response.data.max_pages;
                            const newHtml = response.data.html;

                            if (self.settings.layout_type === 'carousel') {
                                self.destroyCarousel(); // Destroy before updating content.
                                self.$loopContainer.html(newHtml);
                                // Re-initialize carousel after a slight delay to ensure DOM is ready.
                                setTimeout(function () {
                                    self.initializeCarousel();
                                }, 100);
                            } else { // Grid layout
                                if (appendResults) {
                                    self.$loopContainer.append(newHtml);
                                } else {
                                    self.$loopContainer.html(newHtml);
                                }
                            }

                            self.updateLoadMoreButtonVisibility();
                            self.updateFilterOptionsState(response.data.available_filter_options);
                            self.updateUrl(); // Update URL after successful fetch

                            // Scroll to top of the widget on first page load after filter change.
                            if (!isInitialLoad && !appendResults && self.currentPage === 1) {
                                $('html, body').animate({
                                    scrollTop: self.$widgetContainer.offset().top - 50
                                }, 500);
                            }
                        } else {
                            // Handle error response from AJAX.
                            console.error('AJAX Error:', response.data.message);
                            if (!appendResults) {
                                self.$loopContainer.html('<p class="dgcpf-error-message">' + (response.data.message || 'An unknown error occurred.') + '</p>');
                            }
                        }
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        // Handle network or server errors.
                        console.error('AJAX Request Failed:', textStatus, errorThrown);
                        if (!appendResults) {
                            self.$loopContainer.html('<p class="dgcpf-error-message">Failed to load products. Please try again.</p>');
                        }
                    },
                    complete: function () {
                        self.isLoading = false;
                        self.$loopContainer.removeClass('loading'); // Remove loading indicator.
                        self.$loopContainer.attr('aria-busy', 'false'); // Accessibility: Loading complete
                    }
                });
            };

            // Initialize the handler.
            self.init();
        };

        // Register the Elementor Frontend Handler for our widget.
        elementor.frontend.hooks.addAction(
            'frontend/element_ready/dgcpf_filtered_loop.default',
            DomGats_Filtered_Loop_Widget_Handler
        );
    });
})(jQuery, window.elementor);
/*
 * END: DomGats Filtered Loop Elementor Widget Frontend Handler v1.3.1
 */
