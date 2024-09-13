(function ($) {
  Drupal.behaviors.advancedPwaLogout = {
    attach: function (context, settings) {
      // Add event listener to logout button or trigger logout based on your UI
      $('a[href="/user/logout"]').click(function () {
        // Check if service workers are supported by the browser
        if ('serviceWorker' in navigator) {
          // Register the service worker
          navigator.serviceWorker.register('/serviceworker-advanced_pwa_js').then(function (registration) {
            // Once the service worker is registered, you can access its controller
            var serviceWorker = registration.active || registration.installing || registration.waiting;

            // Check if the service worker is active
            if (serviceWorker) {
              // Send a message to the service worker to trigger logout
              serviceWorker.postMessage({ type: 'logout' });
            }
          }).catch(function (error) {
            // Handle registration errors
            console.error('Service worker registration failed:', error);
          });
        }

      });
    }
  };
})(jQuery);