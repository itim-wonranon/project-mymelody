/* js/script.js */
$(document).ready(function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    // Fade out alerts after 5 seconds
    setTimeout(function() {
        $('.alert-dismissible').fadeOut('slow', function() {
            $(this).remove();
        });
    }, 5000);
});
