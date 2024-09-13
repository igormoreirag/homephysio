document.addEventListener('DOMContentLoaded', function() {
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.getRegistrations().then(function(registrations) {
      registrations.forEach(function(registration) {
        // Check if the URL of the active service worker matches the expected URL.
        if (registration.active && registration.active.scriptURL === window.location.origin + '/serviceworker-advanced_pwa_js') {
          registration.unregister().then(function(success) {
            console.log('Service worker unregistered:', success);
          }).catch(function(error) {
            console.error('Failed to unregister service worker:', error);
          });
        }
      });
    });
  }
});
