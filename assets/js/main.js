/*
 * START: AHH MAA - ELEMENTOR LOOP GRID AJAX FILTER v3.2
 */
jQuery(function ($) {
    $(window).on('load', function () {
        if (window.ahhMaaFilterInitialized) {
            return;
        }
        window.ahhMaaFilterInitialized = true;

        console.log("Ahh Maa Filter Initializing...");

        function initializeFilters() {
            const filterInstances = $('.ahh-maa-filters');
            const gridInstances = $('.ahh-maa-product-grid');

            console.log(filterInstances.length + " filter instance(s) found.");
            console.log(gridInstances.length + " grid instance(s) found.");

            filterInstances.each(function () {
                const filterContainer = $(this);
                const filterType = filterContainer.data('filter-type');

                // --- FIX: Removed the `:visible` selector to find initially hidden grids ---
                const loopGrid = gridInstances.filter('[data-filter-type="' + filterType + '"]');

                if (loopGrid.length) {
                    console.log("Initializing filter for:", filterType);
                    new ProductFilter(filterContainer, loopGrid);
                } else {
                    // This error should no longer appear for the 'addons' grid.
                    console.error("Could not find a matching product grid for filter type:", filterType);
                }
            });
        }

        function ProductFilter(filterContainerEl, loopGridEl) {
            const self = this;
            this.filterContainer = filterContainerEl;
            this.loopGrid = loopGridEl;
            this.loopContainer = this.loopGrid.find('.elementor-loop-container');
            this.filterType = this.filterContainer.data('filter-type');
            this.isCarousel = this.loopGrid.hasClass('ahh-maa-desktop-carousel') && $(window).width() >= 768;

            this.toggleButton = this.filterContainer.find('.filter-toggle-button');
            this.filterDropdown = this.filterContainer.find('.filter-dropdown');
            this.tagList = this.filterContainer.find('.filter-tag-list');
            this.selectedTagsArea = this.filterContainer.find('.selected-tags-area');
            this.clearAllButton = this.filterContainer.find('.clear-all-filters');

            this.selectedTags = [];
            this.isLoading = false;
            this.currentPage = 1;
            this.maxPages = 1;
            this.flickityInstance = null;

            this.init = function () {
                self.bindEvents();
                self.setupInitialView();
                self.fetchProducts(true);
            };

            this.setupInitialView = function () {
                if (!self.isCarousel) {
                    self.setupLoadMore();
                }
            };

            this.bindEvents = function () {
                self.toggleButton.on('click', self.onToggleButtonClick);
                self.tagList.on('click', 'li:not(.disabled)', self.onTagClick);
                self.selectedTagsArea.on('click', '.selected-tag', self.onSelectedTagClick);
                self.clearAllButton.on('click', self.onClearAllClick);
            };

            this.onToggleButtonClick = function (e) {
                e.preventDefault();
                self.filterDropdown.slideToggle(200);
            };

            this.onTagClick = function () {
                const tag = $(this);
                const slug = tag.data('slug');
                const index = self.selectedTags.indexOf(slug);

                if (index === -1) {
                    self.selectedTags.push(slug);
                    tag.addClass('active');
                } else {
                    self.selectedTags.splice(index, 1);
                    tag.removeClass('active');
                }
                self.handleFilterChange();
            };

            this.onSelectedTagClick = function () {
                const slugToRemove = $(this).data('slug');
                const index = self.selectedTags.indexOf(slugToRemove);
                if (index > -1) {
                    self.selectedTags.splice(index, 1);
                }
                self.tagList.find('li[data-slug="' + slugToRemove + '"]').removeClass('active');
                self.handleFilterChange();
            };

            this.onClearAllClick = function (e) {
                e.preventDefault();
                self.selectedTags = [];
                self.tagList.find('li').removeClass('active');
                self.handleFilterChange();
            };

            this.handleFilterChange = function () {
                self.currentPage = 1;
                self.updateSelectedTagsUI();
                self.fetchProducts();
            };

            this.initializeCarousel = function () {
                if ( !$.fn.flickity ) return; // Prevent errors if Flickity isn't loaded
                self.flickityInstance = new Flickity(self.loopContainer[0], {
                    cellSelector: '.e-loop-item',
                    prevNextButtons: true,
                    pageDots: false,
                    wrapAround: true,
                    adaptiveHeight: false,
                    draggable: false,
                    groupCells: 3,
                    imagesLoaded: true // FIX: Wait for images to load before initializing.
                });
            };

            this.setupLoadMore = function () {
                if (self.loopGrid.next('.load-more-container').length === 0) {
                    self.loopGrid.after('<div class="load-more-container"><a href="#" class="load-more-button elementor-button">Load More</a></div>');
                    const loadMoreButton = self.loopGrid.next('.load-more-container').find('.load-more-button');

                    loadMoreButton.on('click', function (e) {
                        e.preventDefault();
                        if (!self.isLoading && self.currentPage < self.maxPages) {
                            self.currentPage++;
                            self.fetchProducts();
                        }
                    });
                }
            };

            this.updateLoadMoreButton = function () {
                const loadMoreButton = self.loopGrid.next('.load-more-container').find('.load-more-button');
                if (self.currentPage >= self.maxPages) {
                    loadMoreButton.hide();
                } else {
                    loadMoreButton.show();
                }
            };

            this.updateSelectedTagsUI = function () {
                self.selectedTagsArea.empty();
                if (self.selectedTags.length > 0) {
                    self.selectedTags.forEach(function (slug) {
                        const tagName = self.tagList.find('li[data-slug="' + slug + '"]').text();
                        self.selectedTagsArea.append('<span class="selected-tag" data-slug="' + slug + '">' + tagName + ' <i class="eicon-close"></i></span>');
                    });
                    self.clearAllButton.show();
                } else {
                    self.clearAllButton.hide();
                }
            };

            this.updateAvailableTags = function (availableTags) {
                const allTags = self.tagList.find('li');
                allTags.removeClass('disabled');

                if (self.selectedTags.length > 0) {
                    allTags.each(function () {
                        const tagSlug = $(this).data('slug');
                        if (!availableTags.hasOwnProperty(tagSlug) && !self.selectedTags.includes(tagSlug)) {
                            $(this).addClass('disabled');
                        }
                    });
                }
            };

            this.fetchProducts = function (isInitialLoad = false) {
                if (self.isLoading) return;
                self.isLoading = true;
                self.loopGrid.addClass('loading');

                $.ajax({
                    url: ahh_maa_filter_params.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'filter_products_by_tag',
                        nonce: ahh_maa_filter_params.nonce,
                        tags: self.selectedTags,
                        page: self.currentPage,
                        page_id: ahh_maa_filter_params.page_id,
                        widget_id: self.loopGrid.data('id'),
                        filter_type: self.filterType
                    },
                    success: function (response) {
                        if (response.success) {
                            self.maxPages = response.data.max_pages;
                            const newHtml = response.data.html;

                            if (self.isCarousel) {
                                if (self.flickityInstance) {
                                    self.flickityInstance.destroy();
                                }
                                self.loopContainer.html(newHtml);
                                setTimeout(function () {
                                    self.initializeCarousel();
                                }, 100);
                            } else {
                                if (self.currentPage === 1) {
                                    self.loopContainer.html(newHtml);
                                } else {
                                    self.loopContainer.append(newHtml);
                                }
                                self.updateLoadMoreButton();
                            }

                            self.updateAvailableTags(response.data.available_tags);

                            if (!isInitialLoad && $('#menuSection').length && self.currentPage === 1) {
                                $('html, body').animate({
                                    scrollTop: $('#menuSection').offset().top - 50
                                }, 500);
                            }
                        }
                    },
                    error: function () {
                        self.loopContainer.html('<p>An error occurred. Please try again.</p>');
                    },
                    complete: function () {
                        self.isLoading = false;
                        self.loopGrid.removeClass('loading');
                    }
                });
            };

            this.init();
        }

        initializeFilters();
    });
});
/*
 * END: AHH MAA - ELEMENTOR LOOP GRID AJAX FILTER v3.2
 */
