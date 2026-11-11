document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.has-submenu > a[href="#"]').forEach(function(link) {
        link.addEventListener('click', function(event) {
            event.preventDefault();
            this.parentElement.classList.toggle('open');
        });
    });
});
