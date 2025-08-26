// Auction Frontend JavaScript - Complete 2 Column Layout
(function($) {
    'use strict';
    
    let auctionTimers = {};
    let updateIntervals = {};
    let sliderCaptchas = {};
    let lastUpdateTimes = {};
    let currentUserId = null;
    
    // Get current user ID if available
    if (typeof auction_ajax !== 'undefined' && auction_ajax.user_id) {
        currentUserId = parseInt(auction_ajax.user_id);
    }
    
window.initializeAuction = function(auctionId) {
    console.log('Initializing auction:', auctionId);

    const hasNickname = $(`#auction-container-${auctionId} .nickname-display`).length > 0;
    lastUpdateTimes[auctionId] = Math.floor(Date.now() / 1000);

    if (hasNickname) {
        $(`#auction-container-${auctionId} .rules-section`).show();
        $(`#auction-container-${auctionId} .captcha-container`).show();
    }

    initializeGallery(auctionId);
    startTimer(auctionId);
    startUpdateInterval(auctionId);
    bindEvents(auctionId);
    bindCaptchaEvents(auctionId); // <-- ensures slider captcha events are bound
    updateFormStates(auctionId);
};
    function initializeGallery(auctionId) {
        console.log('Initializing gallery for auction:', auctionId);
        
        // Find gallery in the left column
        const galleryEl = $('.left-column .auction-gallery');
        
        if (galleryEl.length === 0) {
            console.log('No gallery found for auction:', auctionId);
            return;
        }
        
        const mainImage = galleryEl.find('.main-image');
        const thumbnails = galleryEl.find('.thumbnail-wrapper');
        const modal = galleryEl.find('.image-modal');
        const modalImage = modal.find('img');
        
        console.log('Gallery elements found:', {
            mainImage: mainImage.length,
            thumbnails: thumbnails.length,
            modal: modal.length
        });
        
        // Handle thumbnail clicks
        thumbnails.off('click.gallery').on('click.gallery', function() {
            const imageUrl = $(this).data('image');
            console.log('Thumbnail clicked, image URL:', imageUrl);
            
            if (imageUrl && mainImage.length) {
                mainImage.attr('src', imageUrl);
                thumbnails.removeClass('active');
                $(this).addClass('active');
            }
        });

        // Handle main image click (zoom)
        mainImage.add(galleryEl.find('.zoom-overlay')).off('click.gallery').on('click.gallery', function(e) {
            e.preventDefault();
            const imageUrl = mainImage.attr('src');
            console.log('Main image clicked for zoom, URL:', imageUrl);
            
            if (imageUrl && modal.length && modalImage.length) {
                modalImage.attr('src', imageUrl);
                modal.show();
            }
        });

        // Handle modal close
        modal.find('.modal-close').off('click.gallery').on('click.gallery', function(e) {
            e.preventDefault();
            console.log('Modal close clicked');
            modal.hide();
        });
        
        modal.off('click.gallery').on('click.gallery', function(e) {
            if (e.target === this) {
                console.log('Modal background clicked');
                modal.hide();
            }
        });

        // Handle modal navigation
        modal.find('.modal-prev').off('click.gallery').on('click.gallery', function(e) {
            e.stopPropagation();
            navigateGallery(galleryEl, -1);
        });
        
        modal.find('.modal-next').off('click.gallery').on('click.gallery', function(e) {
            e.stopPropagation();
            navigateGallery(galleryEl, 1);
        });

        // Keyboard navigation
        $(document).off('keydown.gallery').on('keydown.gallery', function(e) {
            if (modal.is(':visible')) {
                if (e.key === 'ArrowLeft') {
                    navigateGallery(galleryEl, -1);
                } else if (e.key === 'ArrowRight') {
                    navigateGallery(galleryEl, 1);
                } else if (e.key === 'Escape') {
                    modal.hide();
                }
            }
        });

        // Set first thumbnail as active if none is active
        if (thumbnails.length && !thumbnails.filter('.active').length) {
            thumbnails.first().addClass('active');
        }
    }

    function navigateGallery(galleryEl, direction) {
        const thumbnails = galleryEl.find('.thumbnail-wrapper');
        const mainImage = galleryEl.find('.main-image');
        const modalImage = galleryEl.find('.image-modal img');

        let activeThumbnail = thumbnails.filter('.active');
        if (activeThumbnail.length === 0) {
            activeThumbnail = thumbnails.first();
        }
        
        let currentIndex = thumbnails.index(activeThumbnail);
        let newIndex = currentIndex + direction;

        if (newIndex < 0) newIndex = thumbnails.length - 1;
        if (newIndex >= thumbnails.length) newIndex = 0;

        const newThumbnail = thumbnails.eq(newIndex);
        const newImageUrl = newThumbnail.data('image');

        if (newImageUrl) {
            mainImage.attr('src', newImageUrl);
            modalImage.attr('src', newImageUrl);

            thumbnails.removeClass('active');
            newThumbnail.addClass('active');
        }
    }
    
    // Fixed Captcha JavaScript - Improved behavior and integration

function initializeSliderCaptcha(auctionId) {
    const captchaElement = document.getElementById(`slider-captcha-${auctionId}`);
    if (captchaElement && !sliderCaptchas[auctionId]) {
        console.log('Initializing slider captcha for auction:', auctionId);
        
        try {
            sliderCaptchas[auctionId] = sliderCaptcha({
                id: `slider-captcha-${auctionId}`,
                width: 280,
                height: 155,
                barText: 'Slide to verify',
                failedText: 'Verification failed, try again',
                loadingText: 'Loading verification...',
                
                onSuccess: function() {
                    console.log('Captcha verified for auction', auctionId);
                    
                    // Hide the slider captcha and show success message
                    const captchaWrapper = $(`#slider-captcha-${auctionId}`).closest('.slider-captcha-wrapper');
                    const captchaContainer = $(`#auction-container-${auctionId} .captcha-container`);
                    
                    // Add completed class for styling
                    captchaWrapper.addClass('completed');
                    
                    // Show success message
                    if (captchaContainer.find('.captcha-success').length === 0) {
                        captchaContainer.append(`
                            <div class="captcha-success">
                                <span class="captcha-success-icon">✅</span>
                                <span>Security verification completed successfully!</span>
                            </div>
                        `);
                    }
                    
                    // Hide the slider after a brief delay to show success
                    setTimeout(function() {
                        captchaWrapper.fadeOut(300, function() {
                            // Keep the success message visible
                            captchaContainer.find('.captcha-success').show();
                        });
                    }, 1500);
                    
                    updateFormStates(auctionId);
                },
                
                onFail: function() {
                    console.log('Captcha failed for auction', auctionId);
                    
                    // Remove any existing success messages
                    $(`#auction-container-${auctionId} .captcha-success`).remove();
                    
                    // Add shake animation to indicate failure
                    const captchaWrapper = $(`#slider-captcha-${auctionId}`).closest('.slider-captcha-wrapper');
                    captchaWrapper.removeClass('completed').addClass('failed');
                    
                    setTimeout(function() {
                        captchaWrapper.removeClass('failed');
                    }, 500);
                    
                    updateFormStates(auctionId);
                },
                
                onRefresh: function() {
                    console.log('Captcha refreshed for auction', auctionId);
                    
                    // Remove success messages when refreshed
                    $(`#auction-container-${auctionId} .captcha-success`).remove();
                    
                    const captchaWrapper = $(`#slider-captcha-${auctionId}`).closest('.slider-captcha-wrapper');
                    captchaWrapper.removeClass('completed failed');
                    
                    updateFormStates(auctionId);
                }
            });
            
        } catch (error) {
            console.error('Error initializing slider captcha:', error);
            
            // Fallback: show a simple success message if captcha fails to load
            $(`#auction-container-${auctionId} .captcha-container`).append(`
                <div class="captcha-success">
                    <span class="captcha-success-icon">⚠️</span>
                    <span>Captcha loading failed. Verification bypassed for testing.</span>
                </div>
            `);
            
            // Allow form submission if captcha fails to load (for testing)
            updateFormStates(auctionId);
        }
    }
}

// Updated robot checkbox event handler
function bindCaptchaEvents(auctionId) {
    // Robot verification checkbox event
    $(`#not-robot-${auctionId}`).off('change.captcha').on('change.captcha', function() {
        const isChecked = $(this).is(':checked');
        const captchaWrapper = $(`#slider-captcha-${auctionId}`).closest('.slider-captcha-wrapper');
        const captchaContainer = $(`#auction-container-${auctionId} .captcha-container`);
        
        if (isChecked) {
            // Show the slider captcha with animation
            captchaWrapper.removeClass('completed failed').addClass('show').slideDown(300);
            
            // Initialize captcha if not already done
            if (!sliderCaptchas[auctionId]) {
                // Small delay to ensure element is visible before initializing
                setTimeout(function() {
                    initializeSliderCaptcha(auctionId);
                }, 100);
            } else {
                // Reset existing captcha
                if (sliderCaptchas[auctionId].reset) {
                    sliderCaptchas[auctionId].reset();
                }
            }
            
            // Remove any existing success messages
            captchaContainer.find('.captcha-success').remove();
            
        } else {
            // Hide the slider captcha
            captchaWrapper.removeClass('show completed failed').slideUp(300);
            
            // Remove success messages
            captchaContainer.find('.captcha-success').remove();
            
            // Reset captcha if it exists
            if (sliderCaptchas[auctionId] && sliderCaptchas[auctionId].reset) {
                sliderCaptchas[auctionId].reset();
            }
        }
        
        updateFormStates(auctionId);
    });
}

// Updated form state checker
function isCaptchaVerified(auctionId) {
    // Check if captcha was completed successfully
    const hasSuccessMessage = $(`#auction-container-${auctionId} .captcha-success`).length > 0;
    const captchaContainer = $(`#slider-captcha-${auctionId}`);
    const hasSliderSuccess = captchaContainer.find('.sliderContainer_success').length > 0;
    
    // Also check if robot checkbox is unchecked (meaning no captcha required)
    const robotChecked = $(`#not-robot-${auctionId}`).is(':checked');
    
    return hasSuccessMessage || hasSliderSuccess || !robotChecked;
}

// Updated HTML structure for the captcha section
function getCaptchaHTML(auctionId, hasNickname) {
    return `
        <div class="captcha-container" style="${!hasNickname ? 'display: none;' : ''}">
            <label class="captcha-label">Security Verification:</label>
            <div class="captcha-trigger">
                <label class="robot-checkbox-label">
                    <input type="checkbox" id="not-robot-${auctionId}" class="robot-checkbox">
                    <span class="checkbox-custom"></span>
                    I'm not a robot
                </label>
            </div>
            <div class="slider-captcha-wrapper">
                <div id="slider-captcha-${auctionId}" class="slider-captcha"></div>
            </div>
        </div>
    `;
}

// Enhanced update form states function
function updateFormStates(auctionId) {
    const hasNickname = $(`#auction-container-${auctionId} .nickname-display`).length > 0;
    const rulesAccepted = $(`#accept-rules-${auctionId}`).is(':checked');
    const robotChecked = $(`#not-robot-${auctionId}`).is(':checked');
    const captchaVerified = isCaptchaVerified(auctionId);
    const bidAmount = parseFloat($(`#bid-amount-${auctionId}`).val());

    if (hasNickname) {
        $(`#bid-amount-${auctionId}`).prop('disabled', false);
    }

    if (hasNickname && bidAmount > 0) {
        $(`#auction-container-${auctionId} .rules-section`).slideDown(300);
        $(`#auction-container-${auctionId} .captcha-container`).slideDown(300);
    }

    const allConditionsMet = hasNickname &&
        bidAmount > 0 &&
        rulesAccepted &&
        (!robotChecked || captchaVerified);

    $(`#auction-container-${auctionId} .place-bid-btn`).prop('disabled', !allConditionsMet);

    const button = $(`#auction-container-${auctionId} .place-bid-btn`);
    if (!hasNickname) {
        button.text('Set nickname first');
    } else if (bidAmount <= 0) {
        button.text('Enter bid amount');
    } else if (!rulesAccepted) {
        button.text('Accept rules');
    } else if (robotChecked && !captchaVerified) {
        button.text('Complete verification');
    } else {
        button.text('Place Bid');
    }
}



// CSS for additional animations
const additionalCaptchaCSS = `
<style>
.slider-captcha-wrapper.failed {
    animation: shake 0.5s ease-in-out;
    border-color: #dc3545;
    background: #f8d7da;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

.captcha-container .captcha-success {
    margin-top: 15px;
}

/* Ensure smooth transitions */
.slider-captcha-wrapper {
    overflow: hidden;
    transition: all 0.3s ease;
}

.slider-captcha-wrapper.show {
    max-height: 200px;
}

.slider-captcha-wrapper:not(.show) {
    max-height: 0;
    padding-top: 0;
    padding-bottom: 0;
    margin-top: 0;
    margin-bottom: 0;
}
</style>
`;

// Add the additional CSS to the page
if (typeof jQuery !== 'undefined') {
    jQuery(document).ready(function() {
        if (jQuery('head').find('#additional-captcha-css').length === 0) {
            jQuery('head').append('<style id="additional-captcha-css">' + additionalCaptchaCSS.replace(/<\/?style[^>]*>/g, '') + '</style>');
        }
    });
}
    

    
    function isCaptchaVerified(auctionId) {
        const captchaContainer = $(`#slider-captcha-${auctionId}`);
        return captchaContainer.find('.sliderContainer_success').length > 0;
    }
    
    function startTimer(auctionId) {
        console.log('Starting timer for auction:', auctionId);
        
        const timerElement = $(`#auction-timer-${auctionId}`);
        if (timerElement.length === 0) {
            console.error('Timer element not found for auction:', auctionId);
            return;
        }
        
        const endTime = parseInt(timerElement.data('end-time'));
        if (!endTime || isNaN(endTime)) {
            console.error('Invalid end time for auction:', auctionId, endTime);
            return;
        }
        
        const endTimeMs = endTime * 1000;
        console.log('Timer end time:', new Date(endTimeMs));
        
        if (auctionTimers[auctionId]) {
            clearInterval(auctionTimers[auctionId]);
        }
        
        // Update timer immediately
        updateTimerDisplay(auctionId, endTimeMs);
        
        auctionTimers[auctionId] = setInterval(function() {
            updateTimerDisplay(auctionId, endTimeMs);
        }, 1000);
    }
    
    function updateTimerDisplay(auctionId, endTimeMs) {
        const timerElement = $(`#auction-timer-${auctionId}`);
        const now = new Date().getTime();
        const distance = endTimeMs - now;
        
        if (distance < 0) {
            clearInterval(auctionTimers[auctionId]);
            timerElement.find('.timer-display').html('<span class="ended">ENDED</span>');
            $(`#auction-container-${auctionId} .bidding-section`).hide();
            console.log('Auction ended:', auctionId);
            return;
        }
        
        const days = Math.floor(distance / (1000 * 60 * 60 * 24));
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);
        
        let display = '';
        if (days > 0) display += `${days}d `;
        if (hours > 0 || days > 0) display += `${hours}h `;
        display += `${minutes}m ${seconds}s`;
        
        timerElement.find('.timer-display').text(display);
        
        // Add urgency styling for last 5 minutes
        if (distance < 5 * 60 * 1000) {
            timerElement.addClass('urgent');
        } else {
            timerElement.removeClass('urgent');
        }
    }
    
    function startUpdateInterval(auctionId) {
        if (updateIntervals[auctionId]) {
            clearInterval(updateIntervals[auctionId]);
        }
        
        // Update auction data every 3 seconds for more responsive bidding
        updateIntervals[auctionId] = setInterval(function() {
            updateAuctionData(auctionId);
        }, 3000);
    }
    
    function updateAuctionData(auctionId) {
        const lastUpdate = lastUpdateTimes[auctionId] || 0;
        
        $.get(auction_ajax.ajax_url, {
            action: 'get_auction_updates',
            auction_id: auctionId,
            last_update: lastUpdate
        }, function(response) {
            if (response.success) {
                const data = response.data;
                
                // Check for new bids since last update
                if (data.new_bids && data.new_bids.length > 0) {
                    handleNewBids(auctionId, data.new_bids);
                }
                
                // Update current price
                const currentPriceElement = $(`#auction-container-${auctionId} .current-price strong`);
                const oldPrice = parseFloat(currentPriceElement.text().replace(/[^\d.,]/g, '').replace(',', '.'));
                const newPrice = parseFloat(data.current_price);
                
                if (newPrice !== oldPrice && !isNaN(newPrice)) {
                    currentPriceElement.html(
                        parseFloat(data.current_price).toLocaleString('ro-RO', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }) + ' RON'
                    );
                    
                    // Highlight price change
                    currentPriceElement.parent().addClass('price-updated');
                    setTimeout(function() {
                        currentPriceElement.parent().removeClass('price-updated');
                    }, 2000);
                }
                
                // Update minimum bid
                const newMinBid = parseFloat(data.current_price) + 0.01;
                $(`#bid-amount-${auctionId}`).attr('min', newMinBid);
                
                // Update timer if needed
                const timerElement = $(`#auction-timer-${auctionId}`);
                const currentEndTime = parseInt(timerElement.data('end-time'));
                if (currentEndTime !== data.end_time) {
                    timerElement.data('end-time', data.end_time);
                    startTimer(auctionId);
                }
                
                // Update top bids table in left column
                updateTopBidsTable(auctionId, data.top_bids);
                
                // Handle auction status
                if (data.status !== 'active') {
                    $(`#auction-container-${auctionId} .bidding-section`).hide();
                    clearInterval(updateIntervals[auctionId]);
                    clearInterval(auctionTimers[auctionId]);
                }
                
                // Update last update time
                lastUpdateTimes[auctionId] = data.timestamp;
            }
        }).fail(function() {
            console.log('Failed to update auction data');
        });
    }
    
    function handleNewBids(auctionId, newBids) {
        newBids.forEach(function(bid) {
            // Skip notifications for current user's own bids
            if (currentUserId && parseInt(bid.user_id) === currentUserId) {
                return;
            }
            
            showBidNotification(auctionId, bid);
        });
    }
    
    function showBidNotification(auctionId, bid) {
        const bidAmount = parseFloat(bid.bid_amount).toLocaleString('ro-RO', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        
        const message = `🔔 ${bid.nickname} just bid ${bidAmount} RON! You've been outbid!`;
        
        // Create notification element
        const notification = $(`
            <div class="bid-notification outbid-notification">
                <div class="notification-content">
                    <span class="notification-icon">⚠️</span>
                    <span class="notification-text">${message}</span>
                    <button class="notification-close">×</button>
                </div>
            </div>
        `);
        
        // Add to container (right column)
        $(`#auction-container-${auctionId}`).prepend(notification);
        
        // Auto-remove after 8 seconds
        setTimeout(function() {
            notification.fadeOut(500, function() {
                $(this).remove();
            });
        }, 8000);
        
        // Manual close
        notification.find('.notification-close').on('click', function() {
            notification.fadeOut(500, function() {
                $(this).remove();
            });
        });
        
        // Play notification sound if available
        try {
            const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBTWJ0fPTgjMGHm7C7+OZSAR');
            audio.volume = 0.3;
            audio.play().catch(function() {
                // Ignore audio play errors
            });
        } catch (e) {
            // Ignore audio errors
        }
    }
    
    function updateTopBidsTable(auctionId, topBids) {
        // Update the table in the left column
        const bidsListDiv = $('.left-column .top-bids .bids-list');
        
        if (topBids && topBids.length > 0) {
            let html = '<table class="bids-table"><thead><tr><th>Rank</th><th>Bidder</th><th>Amount</th><th>Time</th></tr></thead><tbody>';
            
            topBids.forEach(function(bid, index) {
                const bidTime = new Date(bid.bid_time);
                const timeString = bidTime.toLocaleTimeString('ro-RO', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
                
                html += `<tr>
                    <td>${index + 1}</td>
                    <td title="${escapeHtml(bid.nickname)}">${escapeHtml(bid.nickname)}</td>
                    <td>${parseFloat(bid.bid_amount).toLocaleString('ro-RO', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    })} RON</td>
                    <td>${timeString}</td>
                </tr>`;
            });
            
            html += '</tbody></table>';
            bidsListDiv.html(html);
        } else {
            bidsListDiv.html('<p style="text-align: center; color: #666; font-style: italic; padding: 20px;">No bids yet. Be the first to bid!</p>');
        }
    }
    
    function bindEvents(auctionId) {
        // Robot verification checkbox event
        $(`#not-robot-${auctionId}`).off('change.auction').on('change.auction', function() {
            if ($(this).is(':checked')) {
                $(`#slider-captcha-${auctionId}`).show();
                if (!sliderCaptchas[auctionId]) {
                    initializeSliderCaptcha(auctionId);
                }
            } else {
                $(`#slider-captcha-${auctionId}`).hide();
                if (sliderCaptchas[auctionId] && sliderCaptchas[auctionId].reset) {
                    sliderCaptchas[auctionId].reset();
                }
            }
            updateFormStates(auctionId);
        });
        
        // Rules acceptance checkbox event
        $(`#accept-rules-${auctionId}`).off('change.auction').on('change.auction', function() {
            updateFormStates(auctionId);
        });
        
        // Bid amount input event
        $(`#bid-amount-${auctionId}`).off('input.auction').on('input.auction', function() {
            updateFormStates(auctionId);
        });
        
        // Edit nickname button event
        $(`#auction-container-${auctionId} .edit-nickname-btn`).off('click.auction').on('click.auction', function() {
            $(`#auction-container-${auctionId} .nickname-display`).hide();
            $(`#auction-container-${auctionId} .nickname-edit-form`).show();
        });
        
        // Cancel nickname edit event
        $(`#auction-container-${auctionId} .cancel-nickname-btn`).off('click.auction').on('click.auction', function() {
            // Reset the input to original value
            const originalNickname = $(`#auction-container-${auctionId} .current-nickname`).text();
            $(`#user-nickname-${auctionId}`).val(originalNickname);
            
            $(`#auction-container-${auctionId} .nickname-edit-form`).hide();
            $(`#auction-container-${auctionId} .nickname-display`).show();
        });
        
        // Save nickname event
        $(`#auction-container-${auctionId} .save-nickname-btn`).off('click.auction').on('click.auction', function() {
            const nickname = $(`#user-nickname-${auctionId}`).val().trim();
            
            if (nickname.length < 3 || nickname.length > 50) {
                showMessage(auctionId, 'Nickname must be between 3-50 characters', 'error');
                return;
            }
            
            const button = $(this);
            const originalText = button.text();
            button.prop('disabled', true).text('Saving...');
            
            $.post(auction_ajax.ajax_url, {
                action: 'save_nickname',
                nickname: nickname,
                nonce: auction_ajax.nonce
            }, function(response) {
                if (response.success) {
                    showMessage(auctionId, 'Nickname saved successfully!', 'success');
                    
                    // Update the display nickname
                    $(`#auction-container-${auctionId} .current-nickname`).text(nickname);
                    
                    // Hide edit form and show display
                    $(`#auction-container-${auctionId} .nickname-edit-form`).hide();
                    $(`#auction-container-${auctionId} .nickname-input-form`).hide();
                    
                    // Create display if it doesn't exist
                    if ($(`#auction-container-${auctionId} .nickname-display`).length === 0) {
                        const displayHtml = `
                            <div class="nickname-display">
                                <span class="current-nickname">${escapeHtml(nickname)}</span>
                                <button type="button" class="edit-nickname-btn" data-auction-id="${auctionId}">Edit</button>
                            </div>
                            <div class="nickname-edit-form" style="display: none;">
                                <input type="text" id="user-nickname-${auctionId}" value="${escapeHtml(nickname)}">
                                <button type="button" class="save-nickname-btn" data-auction-id="${auctionId}">Save</button>
                                <button type="button" class="cancel-nickname-btn" data-auction-id="${auctionId}">Cancel</button>
                            </div>
                        `;
                        $(`#auction-container-${auctionId} .nickname-setup`).html(`
                            <label>Your Display Nickname:</label>
                            ${displayHtml}
                        `);
                        
                        // Re-bind the edit button event
                        bindEvents(auctionId);
                    } else {
                        $(`#auction-container-${auctionId} .nickname-display`).show();
                    }
                    
                    // Show rules and captcha sections
                    $(`#auction-container-${auctionId} .rules-section`).show();
                    $(`#auction-container-${auctionId} .captcha-container`).show();
                    
                    // Initialize captcha if not already done
                    if (!sliderCaptchas[auctionId]) {
                        initializeSliderCaptcha(auctionId);
                    }
                    
                    updateFormStates(auctionId);
                } else {
                    showMessage(auctionId, response.data || 'Failed to save nickname', 'error');
                }
            }).always(function() {
                button.prop('disabled', false).text(originalText);
            });
        });
        
        // Place bid event
        $(`#auction-container-${auctionId} .place-bid-btn`).off('click.auction').on('click.auction', function() {
            const bidAmount = parseFloat($(`#bid-amount-${auctionId}`).val());
            const rulesAccepted = $(`#accept-rules-${auctionId}`).is(':checked');
            const captchaVerified = isCaptchaVerified(auctionId);
            const hasNickname = $(`#auction-container-${auctionId} .nickname-display`).length > 0;
            
            // Validation
            if (!hasNickname) {
                showMessage(auctionId, 'Please set your nickname first', 'error');
                return;
            }
            
            if (!bidAmount || bidAmount <= 0) {
                showMessage(auctionId, 'Please enter a valid bid amount', 'error');
                return;
            }
            
            const minBid = parseFloat($(`#bid-amount-${auctionId}`).attr('min'));
            if (bidAmount < minBid) {
                showMessage(auctionId, `Bid must be at least ${minBid.toFixed(2)} RON`, 'error');
                return;
            }
            
            if (!rulesAccepted) {
                showMessage(auctionId, 'Please accept the auction rules and terms', 'error');
                return;
            }
            
            if (!$(`#not-robot-${auctionId}`).is(':checked')) {
                showMessage(auctionId, 'Please check "I\'m not a robot"', 'error');
                return;
            }
            
            if (!captchaVerified) {
                showMessage(auctionId, 'Please complete the security verification', 'error');
                return;
            }
            
            const button = $(this);
            button.prop('disabled', true).text('Placing Bid...');
            
            $.post(auction_ajax.ajax_url, {
                action: 'place_bid',
                auction_id: auctionId,
                bid_amount: bidAmount,
                captcha_verified: captchaVerified,
                rules_accepted: rulesAccepted,
                nonce: auction_ajax.nonce
            }, function(response) {
                if (response.success) {
                    const responseData = response.data || {};
                    
                    if (responseData.your_bid) {
                        showMessage(auctionId, '✅ ' + (responseData.message || 'Bid placed successfully!'), 'success');
                        
                        // Show success notification
                        const successNotification = $(`
                            <div class="bid-notification success-notification">
                                <div class="notification-content">
                                    <span class="notification-icon">🎉</span>
                                    <span class="notification-text">You're currently the highest bidder!</span>
                                    <button class="notification-close">×</button>
                                </div>
                            </div>
                        `);
                        
                        $(`#auction-container-${auctionId}`).prepend(successNotification);
                        
                        setTimeout(function() {
                            successNotification.fadeOut(500, function() {
                                $(this).remove();
                            });
                        }, 5000);
                        
                        successNotification.find('.notification-close').on('click', function() {
                            successNotification.fadeOut(500, function() {
                                $(this).remove();
                            });
                        });
                    }
                    
                    $(`#bid-amount-${auctionId}`).val('');
                    
                    // Uncheck rules (user needs to accept again for next bid)
                    $(`#accept-rules-${auctionId}`).prop('checked', false);
                    
                    // Uncheck robot verification and hide captcha
                    $(`#not-robot-${auctionId}`).prop('checked', false);
                    $(`#slider-captcha-${auctionId}`).hide();
                    
                    // Reset captcha for next bid
                    if (sliderCaptchas[auctionId] && sliderCaptchas[auctionId].reset) {
                        sliderCaptchas[auctionId].reset();
                    }
                    updateFormStates(auctionId);
                    
                    // Immediately update auction data
                    setTimeout(function() {
                        updateAuctionData(auctionId);
                    }, 1000);
                } else {
                    const errorData = response.data || {};
                    
                    if (errorData.outbid) {
                        // Handle outbid scenario
                        showMessage(auctionId, '⚠️ ' + errorData.message, 'error');
                        
                        // Update the minimum bid amount to the new price
                        if (errorData.new_price) {
                            const newMinBid = parseFloat(errorData.new_price) + 0.01;
                            $(`#bid-amount-${auctionId}`).attr('min', newMinBid);
                            $(`#bid-amount-${auctionId}`).attr('placeholder', `Minimum: ${newMinBid.toFixed(2)} RON`);
                        }
                        
                        // Force immediate update
                        updateAuctionData(auctionId);
                    } else {
                        showMessage(auctionId, response.data || 'Failed to place bid', 'error');
                    }
                }
            }).always(function() {
                button.prop('disabled', false).text('Place Bid');
            });
        });
        
        // Enter key support for bid input
        $(`#bid-amount-${auctionId}`).off('keypress.auction').on('keypress.auction', function(e) {
            if (e.which === 13) {
                $(`#auction-container-${auctionId} .place-bid-btn`).click();
            }
        });
        
        // Nickname input validation for initial setup
        $(`#user-nickname-${auctionId}`).off('input.auction').on('input.auction', function() {
            const value = $(this).val().trim();
            const saveBtn = $(`#auction-container-${auctionId} .save-nickname-btn`);
            
            if (value.length >= 3 && value.length <= 50) {
                saveBtn.prop('disabled', false);
                $(this).removeClass('invalid');
            } else {
                saveBtn.prop('disabled', true);
                $(this).addClass('invalid');
            }
        });
    }
    
    function showMessage(auctionId, message, type) {
        // Remove existing messages
        $(`#auction-container-${auctionId} .auction-message`).remove();
        
        const messageClass = type === 'error' ? 'auction-error' : 'auction-success';
        const messageHtml = `<div class="auction-message ${messageClass}">${escapeHtml(message)}</div>`;
        
        $(`#auction-container-${auctionId}`).prepend(messageHtml);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $(`#auction-container-${auctionId} .auction-message`).fadeOut(500, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Auto-initialize all galleries on page load
    $(document).ready(function() {
        $('.auction-gallery').each(function() {
            const galleryEl = $(this);
            
            // Set first thumbnail active if none is active
            const thumbs = galleryEl.find('.thumbnail-wrapper');
            if (thumbs.length && !thumbs.filter('.active').length) {
                thumbs.first().addClass('active');
            }
        });
    });
    
    // Cleanup intervals when page unloads
    $(window).on('beforeunload', function() {
        Object.keys(auctionTimers).forEach(function(auctionId) {
            if (auctionTimers[auctionId]) {
                clearInterval(auctionTimers[auctionId]);
            }
        });
        
        Object.keys(updateIntervals).forEach(function(auctionId) {
            if (updateIntervals[auctionId]) {
                clearInterval(updateIntervals[auctionId]);
            }
        });
    });
    
    // Handle page visibility changes (pause updates when tab is inactive)
    if (typeof document.hidden !== 'undefined') {
        $(document).on('visibilitychange', function() {
            Object.keys(updateIntervals).forEach(function(auctionId) {
                if (document.hidden) {
                    // Page is hidden, clear update intervals
                    if (updateIntervals[auctionId]) {
                        clearInterval(updateIntervals[auctionId]);
                        updateIntervals[auctionId] = null;
                    }
                } else {
                    // Page is visible again, restart update intervals
                    if (!updateIntervals[auctionId]) {
                        startUpdateInterval(auctionId);
                        // Immediate update when returning to tab
                        updateAuctionData(auctionId);
                    }
                }
            });
        });
    }

})(jQuery);