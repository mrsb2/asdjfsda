/* Custom Events Grid JS */
jQuery(document).ready(function($) {
    console.log('Custom Events Grid JS loaded');
    
    // Category filtering - make sure class names and categories match
    $('.filter-button').on('click', function(e) {
        e.preventDefault();
        var selectedCategory = $(this).data('category');
        console.log('Filter clicked:', selectedCategory);
        
        // Update active class
        $('.filter-button').removeClass('active');
        $(this).addClass('active');
        
        if (selectedCategory === 'all') {
            // Show all events
            $('.event-card').show();
            console.log('Showing all events');
        } else {
            // Hide all events then show only filtered ones
            $('.event-card').hide();
            $('.event-card.category-' + selectedCategory).show();
            console.log('Filtering to:', '.event-card.category-' + selectedCategory);
            console.log('Matching elements found:', $('.event-card.category-' + selectedCategory).length);
        }
    });
});