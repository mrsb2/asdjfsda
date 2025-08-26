jQuery(document).ready(function($) {
    console.log('Admin JavaScript loaded');
    
    let galleryImages = [];
    
    // Initialize gallery from existing data
    const existingGalleryData = $('#gallery_images_input').val();
    console.log('Existing gallery data:', existingGalleryData);
    
    if (existingGalleryData && existingGalleryData.trim() !== '') {
        try {
            galleryImages = JSON.parse(existingGalleryData);
            console.log('Parsed existing gallery images:', galleryImages);
        } catch (e) {
            console.error('Error parsing existing gallery data:', e);
            galleryImages = [];
        }
    }
    
    // Also initialize from existing preview items
    $('#gallery-preview .gallery-item').each(function() {
        const imageUrl = $(this).data('url');
        console.log('Found existing preview item:', imageUrl);
        if (imageUrl && galleryImages.indexOf(imageUrl) === -1) {
            galleryImages.push(imageUrl);
        }
    });
    
    console.log('Final initial gallery images:', galleryImages);
    
    // Check if WordPress media uploader is available
    if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
        console.error('WordPress media uploader not available');
        $('#upload-gallery-btn').prop('disabled', true).text('Media uploader not available');
        return;
    }
    
    // Media uploader
    $('#upload-gallery-btn').on('click', function(e) {
        e.preventDefault();
        console.log('Upload button clicked');
        
        const mediaUploader = wp.media({
            title: 'Select Images for Gallery',
            button: {
                text: 'Add to Gallery'
            },
            multiple: true,
            library: {
                type: 'image'
            }
        });
        
        mediaUploader.on('select', function() {
            console.log('Media selected');
            const attachments = mediaUploader.state().get('selection').toJSON();
            console.log('Selected attachments:', attachments);
            
            attachments.forEach(function(attachment) {
                // Use attachment.url which should be properly escaped
                const imageUrl = attachment.url;
                console.log('Processing attachment URL:', imageUrl);
                
                if (galleryImages.indexOf(imageUrl) === -1) {
                    galleryImages.push(imageUrl);
                    addImageToPreview(imageUrl, galleryImages.length - 1);
                    console.log('Added image to gallery:', imageUrl);
                }
            });
            
            updateGalleryInput();
            console.log('Updated gallery images after upload:', galleryImages);
        });
        
        mediaUploader.open();
    });
    
    // Add image to preview
    function addImageToPreview(imageUrl, index) {
        console.log('Adding image to preview:', imageUrl, 'at index:', index);
        
        // Escape the URL for HTML attributes
        const escapedUrl = $('<div>').text(imageUrl).html();
        
        const galleryItem = $(`
            <div class="gallery-item" data-url="${escapedUrl}">
                <img src="${escapedUrl}" alt="Gallery Image" style="max-width: 100%; height: auto;">
                <button type="button" class="remove-image">×</button>
                <div class="image-order">${index + 1}</div>
            </div>
        `);
        
        $('#gallery-preview').append(galleryItem);
        console.log('Image added to preview');
    }
    
    // Remove image
    $(document).on('click', '.remove-image', function(e) {
        e.preventDefault();
        console.log('Remove image clicked');
        const galleryItem = $(this).closest('.gallery-item');
        const imageUrl = galleryItem.data('url');
        const index = galleryImages.indexOf(imageUrl);
        
        console.log('Removing image:', imageUrl, 'at index:', index);
        
        if (index > -1) {
            galleryImages.splice(index, 1);
        }
        
        galleryItem.remove();
        updateGalleryInput();
        updateImageOrder();
        console.log('Removed image, updated gallery:', galleryImages);
    });
    
    // Make gallery sortable if jQuery UI is available
    if ($.fn.sortable) {
        $('#gallery-preview').sortable({
            items: '.gallery-item',
            cursor: 'move',
            opacity: 0.7,
            placeholder: 'gallery-item-placeholder',
            update: function(event, ui) {
                console.log('Gallery reordered');
                // Update the galleryImages array based on new order
                const newOrder = [];
                $('#gallery-preview .gallery-item').each(function() {
                    newOrder.push($(this).data('url'));
                });
                galleryImages = newOrder;
                updateGalleryInput();
                updateImageOrder();
                console.log('Reordered gallery images:', galleryImages);
            }
        });
        console.log('Gallery sortable initialized');
    } else {
        console.warn('jQuery UI sortable not available');
    }
    
    // Update gallery input value with proper JSON encoding
    function updateGalleryInput() {
        // Clean the array - remove any empty or invalid URLs
        const cleanedImages = galleryImages.filter(function(url) {
            return url && typeof url === 'string' && url.trim() !== '';
        });
        
        let jsonValue = '';
        if (cleanedImages.length > 0) {
            try {
                // Ensure proper JSON encoding
                jsonValue = JSON.stringify(cleanedImages);
                console.log('Generated JSON:', jsonValue);
                
                // Validate the JSON we just created
                JSON.parse(jsonValue);
                console.log('JSON validation successful');
            } catch (e) {
                console.error('JSON encoding error:', e);
                console.error('Problem with array:', cleanedImages);
                jsonValue = '[]'; // Fallback to empty array
            }
        } else {
            jsonValue = '[]';
        }
        
        $('#gallery_images_input').val(jsonValue);
        console.log('Gallery input updated with value:', jsonValue);
        
        // Trigger change event
        $('#gallery_images_input').trigger('change');
    }
    
    // Update image order numbers
    function updateImageOrder() {
        $('#gallery-preview .gallery-item').each(function(index) {
            $(this).find('.image-order').text(index + 1);
            
            // Add "MAIN" indicator to first image
            $(this).removeClass('main-image');
            if (index === 0) {
                $(this).addClass('main-image');
            }
        });
        console.log('Image order updated');
    }
    
    // Form submission handler with validation
    $('form').on('submit', function(e) {
        console.log('Form submitting...');
        
        // Force update of gallery input
        updateGalleryInput();
        
        const finalValue = $('#gallery_images_input').val();
        console.log('Form submitting with gallery data:', finalValue);
        
        // Validate JSON before submission
        if (finalValue && finalValue !== '[]') {
            try {
                const parsed = JSON.parse(finalValue);
                console.log('Final JSON validation successful, contains', parsed.length, 'images');
            } catch (e) {
                console.error('Final JSON validation failed:', e);
                alert('Gallery data is corrupted. Please try uploading images again.');
                e.preventDefault();
                return false;
            }
        }
        
        // Show what we're submitting for debugging
        console.log('Submitting form with gallery_images value:', finalValue);
    });
    
    // Initialize existing images
    updateImageOrder();
    updateGalleryInput(); // Ensure the input is properly populated
    
    // Debug: Monitor input changes
    $('#gallery_images_input').on('change', function() {
        console.log('Gallery input changed to:', $(this).val());
    });
    
    // Debug: Check if input value is being set
    console.log('Initial gallery input field value:', $('#gallery_images_input').val());
    
    // Debug: Add visual indicator for debugging
    if (galleryImages.length > 0) {
        console.log('Gallery has', galleryImages.length, 'images loaded');
        
        // Add debug info to the page
        if ($('#gallery-debug-info').length === 0) {
            $('#gallery-preview').after('<div id="gallery-debug-info" style="background: #f0f0f0; padding: 10px; margin: 10px 0; border-radius: 4px;"><strong>Debug Info:</strong> <span id="debug-image-count">' + galleryImages.length + '</span> images loaded</div>');
        }
    } else {
        console.log('No gallery images found');
    }
    
    // Real-time debug info update
    function updateDebugInfo() {
        const debugElement = $('#debug-image-count');
        if (debugElement.length > 0) {
            debugElement.text(galleryImages.length);
        }
    }
    
    // Update debug info whenever images change
    const originalUpdateGalleryInput = updateGalleryInput;
    updateGalleryInput = function() {
        originalUpdateGalleryInput();
        updateDebugInfo();
    };
});