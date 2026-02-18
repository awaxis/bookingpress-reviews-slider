/**
 * BookingPress Reviews Slider JavaScript
 * Version: 1.0.0
 */

(function($) {
    'use strict';

    class ReviewsSlider {
        constructor(element) {
            this.$wrapper = $(element);
            this.$slider = this.$wrapper.find('.bpr-reviews-slider');
            this.$slides = this.$slider.find('.bpr-review-slide');
            this.$prevBtn = this.$wrapper.find('.bpr-slider-prev');
            this.$nextBtn = this.$wrapper.find('.bpr-slider-next');
            this.$dotsContainer = this.$wrapper.find('.bpr-slider-dots');

            // Get configuration
            this.config = this.$wrapper.data('slider-config') || {};
            this.columns = parseInt(this.config.columns) || 3;
            this.autoplay = this.config.autoplay !== false;
            this.autoplaySpeed = parseInt(this.config.autoplay_speed) || 5000;

            // State
            this.currentPage = 0;
            this.totalPages = 0;
            this.autoplayTimer = null;
            this.isTransitioning = false;

            this.init();
        }

        init() {
            // Calculate total pages
            this.calculatePages();

            // Set initial columns attribute
            this.$slider.attr('data-columns', this.columns);

            // Create pagination dots
            this.createDots();

            // Show first page
            this.showPage(0);

            // Bind events
            this.bindEvents();

            // Start autoplay
            if (this.autoplay) {
                this.startAutoplay();
            }

            // Handle responsive
            this.handleResize();
        }

        calculatePages() {
            const totalSlides = this.$slides.length;
            this.totalPages = Math.ceil(totalSlides / this.columns);
        }

        createDots() {
            this.$dotsContainer.empty();

            for (let i = 0; i < this.totalPages; i++) {
                const $dot = $('<button>')
                    .addClass('bpr-slider-dot')
                    .attr('aria-label', 'Go to page ' + (i + 1))
                    .data('page', i);

                if (i === 0) {
                    $dot.addClass('active');
                }

                this.$dotsContainer.append($dot);
            }
        }

        showPage(pageIndex, direction = 'next') {
            if (this.isTransitioning || pageIndex < 0 || pageIndex >= this.totalPages) {
                return;
            }

            this.isTransitioning = true;
            this.currentPage = pageIndex;

            // Hide all slides
            this.$slides.removeClass('active');

            // Show slides for current page
            const startIndex = pageIndex * this.columns;
            const endIndex = Math.min(startIndex + this.columns, this.$slides.length);

            for (let i = startIndex; i < endIndex; i++) {
                this.$slides.eq(i).addClass('active');
            }

            // Update dots
            this.$dotsContainer.find('.bpr-slider-dot')
                .removeClass('active')
                .eq(pageIndex)
                .addClass('active');

            // Update navigation buttons
            this.updateNavButtons();

            // Reset transition flag
            setTimeout(() => {
                this.isTransitioning = false;
            }, 500);
        }

        updateNavButtons() {
            // Update previous button
            if (this.currentPage === 0) {
                this.$prevBtn.prop('disabled', true);
            } else {
                this.$prevBtn.prop('disabled', false);
            }

            // Update next button
            if (this.currentPage >= this.totalPages - 1) {
                this.$nextBtn.prop('disabled', true);
            } else {
                this.$nextBtn.prop('disabled', false);
            }
        }

        nextPage() {
            if (this.currentPage < this.totalPages - 1) {
                this.showPage(this.currentPage + 1, 'next');
            } else if (this.autoplay) {
                // Loop back to start in autoplay mode
                this.showPage(0, 'next');
            }
        }

        prevPage() {
            if (this.currentPage > 0) {
                this.showPage(this.currentPage - 1, 'prev');
            }
        }

        goToPage(pageIndex) {
            const direction = pageIndex > this.currentPage ? 'next' : 'prev';
            this.showPage(pageIndex, direction);
        }

        startAutoplay() {
            this.stopAutoplay();

            if (this.totalPages <= 1) {
                return;
            }

            this.autoplayTimer = setInterval(() => {
                this.nextPage();
            }, this.autoplaySpeed);
        }

        stopAutoplay() {
            if (this.autoplayTimer) {
                clearInterval(this.autoplayTimer);
                this.autoplayTimer = null;
            }
        }

        bindEvents() {
            // Previous button
            this.$prevBtn.on('click', (e) => {
                e.preventDefault();
                this.prevPage();
                this.stopAutoplay();
            });

            // Next button
            this.$nextBtn.on('click', (e) => {
                e.preventDefault();
                this.nextPage();
                this.stopAutoplay();
            });

            // Dots
            this.$dotsContainer.on('click', '.bpr-slider-dot', (e) => {
                e.preventDefault();
                const pageIndex = $(e.currentTarget).data('page');
                this.goToPage(pageIndex);
                this.stopAutoplay();
            });

            // Pause on hover
            this.$wrapper.on('mouseenter', () => {
                this.stopAutoplay();
            });

            this.$wrapper.on('mouseleave', () => {
                if (this.autoplay) {
                    this.startAutoplay();
                }
            });

            // Keyboard navigation
            $(document).on('keydown', (e) => {
                if (!this.$wrapper.is(':hover')) {
                    return;
                }

                if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    this.prevPage();
                    this.stopAutoplay();
                } else if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    this.nextPage();
                    this.stopAutoplay();
                }
            });

            // Touch/swipe support
            let touchStartX = 0;
            let touchEndX = 0;

            this.$slider.on('touchstart', (e) => {
                touchStartX = e.originalEvent.touches[0].clientX;
            });

            this.$slider.on('touchend', (e) => {
                touchEndX = e.originalEvent.changedTouches[0].clientX;
                this.handleSwipe(touchStartX, touchEndX);
            });
        }

        handleSwipe(startX, endX) {
            const swipeThreshold = 50;
            const diff = startX - endX;

            if (Math.abs(diff) > swipeThreshold) {
                if (diff > 0) {
                    // Swipe left - next
                    this.nextPage();
                } else {
                    // Swipe right - previous
                    this.prevPage();
                }
                this.stopAutoplay();
            }
        }

        handleResize() {
            let resizeTimer;

            $(window).on('resize', () => {
                clearTimeout(resizeTimer);

                resizeTimer = setTimeout(() => {
                    // Recalculate on resize if needed
                    const oldColumns = this.columns;

                    // Update columns based on screen size
                    const windowWidth = $(window).width();
                    let newColumns = this.columns;

                    if (windowWidth <= 480) {
                        newColumns = 1;
                    } else if (windowWidth <= 768) {
                        newColumns = Math.min(this.columns, 2);
                    } else if (windowWidth <= 1024) {
                        newColumns = Math.min(this.columns, 3);
                    } else {
                        newColumns = parseInt(this.config.columns) || 3;
                    }

                    if (oldColumns !== newColumns) {
                        this.columns = newColumns;
                        this.$slider.attr('data-columns', this.columns);
                        this.calculatePages();
                        this.createDots();

                        // Adjust current page if needed
                        if (this.currentPage >= this.totalPages) {
                            this.currentPage = this.totalPages - 1;
                        }

                        this.showPage(this.currentPage);
                    }
                }, 250);
            });
        }

        destroy() {
            this.stopAutoplay();
            this.$prevBtn.off('click');
            this.$nextBtn.off('click');
            this.$dotsContainer.off('click');
            this.$wrapper.off('mouseenter mouseleave');
            this.$slider.off('touchstart touchend');
            $(window).off('resize');
        }
    }

    // Initialize all sliders on page load
    $(document).ready(function() {
        $('.bpr-reviews-slider-wrapper').each(function() {
            new ReviewsSlider(this);
        });
    });

    // Make it available globally if needed
    window.ReviewsSlider = ReviewsSlider;

    /**
     * Reviews List with Filter Functionality
     */
    class ReviewsList {
        constructor(element) {
            this.$wrapper = $(element);
            this.$filterSidebar = this.$wrapper.find('.bpr-reviews-filter');
            this.$reviewsContent = this.$wrapper.find('.bpr-reviews-content');
            this.$reviewsList = this.$wrapper.find('.bpr-reviews-list');
            this.$loadMoreBtn = this.$wrapper.find('.bpr-load-more-btn');
            this.$noReviews = this.$wrapper.find('.bpr-no-reviews');
            
            // Configuration
            this.staffId = this.$wrapper.data('staff-id') || 0;
            this.perPage = parseInt(this.$wrapper.data('per-page')) || 5;
            this.currentPage = 1;
            this.hasMore = this.$wrapper.data('has-more') === 'true' || this.$wrapper.data('has-more') === true;
            this.isLoading = false;
            
            // Selected ratings
            this.selectedRatings = [];
            
            this.init();
        }
        
        init() {
            this.bindEvents();
            this.updateFilterCheckboxes();
        }
        
        bindEvents() {
            // Filter checkbox change
            this.$filterSidebar.on('change', '.bpr-filter-checkbox', (e) => {
                this.handleFilterChange(e);
            });
            
            // Load more button
            this.$loadMoreBtn.on('click', (e) => {
                e.preventDefault();
                this.loadMoreReviews();
            });
            
            // Mobile filter toggle (if needed)
            this.$wrapper.on('click', '.bpr-filter-toggle', (e) => {
                e.preventDefault();
                this.$filterSidebar.toggleClass('active');
            });
        }
        
        updateFilterCheckboxes() {
            // Get all checked checkboxes
            this.selectedRatings = [];
            this.$filterSidebar.find('.bpr-filter-checkbox:checked').each((index, checkbox) => {
                this.selectedRatings.push(parseInt($(checkbox).val()));
            });
        }
        
        handleFilterChange(e) {
            const $checkbox = $(e.target);
            const rating = parseInt($checkbox.val());
            
            // Update selected ratings
            if ($checkbox.is(':checked')) {
                if (!this.selectedRatings.includes(rating)) {
                    this.selectedRatings.push(rating);
                }
            } else {
                this.selectedRatings = this.selectedRatings.filter(r => r !== rating);
            }
            
            // Reset pagination
            this.currentPage = 1;
            
            // Filter reviews
            this.filterReviews();
        }
        
        filterReviews() {
            if (this.isLoading) {
                return;
            }
            
            this.isLoading = true;
            this.showLoading();
            
            $.ajax({
                url: bprAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'bpr_filter_reviews',
                    nonce: bprAjax.nonce,
                    staff_id: this.staffId,
                    ratings: this.selectedRatings,
                    per_page: this.perPage
                },
                success: (response) => {
                    this.isLoading = false;
                    this.hideLoading();
                    
                    if (response.success) {
                        // Replace reviews list
                        this.$reviewsList.html(response.data.html);
                        
                        // Update has more flag
                        this.hasMore = response.data.has_more;
                        
                        // Show/hide load more button
                        if (this.hasMore) {
                            this.$loadMoreBtn.show();
                        } else {
                            this.$loadMoreBtn.hide();
                        }
                        
                        // Show/hide no reviews message
                        if (response.data.html.trim() === '') {
                            this.$noReviews.show();
                            this.$reviewsList.hide();
                        } else {
                            this.$noReviews.hide();
                            this.$reviewsList.show();
                        }
                        
                        // Animate in new reviews
                        this.animateReviews();
                    } else {
                        console.error('Filter failed:', response.data);
                    }
                },
                error: (xhr, status, error) => {
                    this.isLoading = false;
                    this.hideLoading();
                    console.error('AJAX error:', error);
                }
            });
        }
        
        loadMoreReviews() {
            if (this.isLoading || !this.hasMore) {
                return;
            }
            
            this.isLoading = true;
            this.$loadMoreBtn.addClass('loading').prop('disabled', true);
            
            // Update button text
            const originalText = this.$loadMoreBtn.text();
            this.$loadMoreBtn.text('Loading...');
            
            this.currentPage++;

            // Calculate offset based on current page
            const offset = (this.currentPage - 1) * this.perPage;

            $.ajax({
                url: bprAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'bpr_load_more_reviews',
                    nonce: bprAjax.nonce,
                    staff_id: this.staffId,
                    ratings: this.selectedRatings,
                    offset: offset,
                    per_page: this.perPage
                },
                success: (response) => {
                    this.isLoading = false;
                    this.$loadMoreBtn.removeClass('loading').prop('disabled', false);
                    this.$loadMoreBtn.text(originalText);
                    
                    if (response.success) {
                        // Append new reviews
                        this.$reviewsList.append(response.data.html);
                        
                        // Update has more flag
                        this.hasMore = response.data.has_more;
                        
                        // Hide load more button if no more reviews
                        if (!this.hasMore) {
                            this.$loadMoreBtn.hide();
                        }
                        
                        // Animate in new reviews
                        this.animateReviews();
                    } else {
                        console.error('Load more failed:', response.data);
                        // Revert page number
                        this.currentPage--;
                    }
                },
                error: (xhr, status, error) => {
                    this.isLoading = false;
                    this.$loadMoreBtn.removeClass('loading').prop('disabled', false);
                    this.$loadMoreBtn.text(originalText);
                    console.error('AJAX error:', error);
                    // Revert page number
                    this.currentPage--;
                }
            });
        }
        
        showLoading() {
            // Add loading class to wrapper
            this.$wrapper.addClass('loading');
            
            // Optionally show loading overlay
            if (!this.$wrapper.find('.bpr-loading-overlay').length) {
                this.$reviewsContent.append('<div class="bpr-loading-overlay"><div class="bpr-spinner"></div></div>');
            }
        }
        
        hideLoading() {
            this.$wrapper.removeClass('loading');
            this.$wrapper.find('.bpr-loading-overlay').remove();
        }
        
        animateReviews() {
            // Fade in reviews that were just added
            const $newReviews = this.$reviewsList.find('.bpr-review-item').not('.animated');
            
            $newReviews.each(function(index) {
                const $review = $(this);
                
                setTimeout(() => {
                    $review.addClass('animated');
                }, index * 50);
            });
        }
        
        destroy() {
            this.$filterSidebar.off('change');
            this.$loadMoreBtn.off('click');
            this.$wrapper.off('click');
        }
    }
    
    /**
     * Average Reviews Animation
     */
    class AverageReviews {
        constructor(element) {
            this.$wrapper = $(element);
            this.$ratingBars = this.$wrapper.find('.bpr-rating-bar');
            this.$statNumbers = this.$wrapper.find('.bpr-stat-number');
            
            this.animated = false;
            
            this.init();
        }
        
        init() {
            // Check if element is in viewport
            this.checkViewport();
            
            // Bind scroll event
            $(window).on('scroll', () => {
                this.checkViewport();
            });
        }
        
        checkViewport() {
            if (this.animated) {
                return;
            }
            
            const elementTop = this.$wrapper.offset().top;
            const elementBottom = elementTop + this.$wrapper.outerHeight();
            const viewportTop = $(window).scrollTop();
            const viewportBottom = viewportTop + $(window).height();
            
            // Check if element is in viewport
            if (elementBottom > viewportTop && elementTop < viewportBottom) {
                this.animate();
                this.animated = true;
            }
        }
        
        animate() {
            // Animate rating bars
            this.$ratingBars.each(function() {
                const $bar = $(this);
                const targetWidth = $bar.data('percentage') + '%';
                
                // Trigger animation
                setTimeout(() => {
                    $bar.css('width', targetWidth);
                }, 100);
            });
            
            // Animate numbers (count up effect)
            this.$statNumbers.each(function() {
                const $number = $(this);
                const targetValue = parseInt($number.text());
                
                if (isNaN(targetValue)) {
                    return;
                }
                
                // Count up animation
                let currentValue = 0;
                const increment = Math.ceil(targetValue / 30);
                const duration = 1000;
                const stepTime = duration / (targetValue / increment);
                
                const counter = setInterval(() => {
                    currentValue += increment;
                    
                    if (currentValue >= targetValue) {
                        $number.text(targetValue);
                        clearInterval(counter);
                    } else {
                        $number.text(currentValue);
                    }
                }, stepTime);
            });
        }
        
        destroy() {
            $(window).off('scroll');
        }
    }
    
    // Initialize new components on page load
    $(document).ready(function() {
        // Initialize Reviews List
        $('.bpr-reviews-list-container').each(function() {
            new ReviewsList(this);
        });
        
        // Initialize Average Reviews
        $('.bpr-average-reviews').each(function() {
            new AverageReviews(this);
        });
    });
    
    // Make classes available globally
    window.ReviewsList = ReviewsList;
    window.AverageReviews = AverageReviews;

})(jQuery);
