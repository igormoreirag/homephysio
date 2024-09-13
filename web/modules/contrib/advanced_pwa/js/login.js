
(function ($, Drupal) {
  Drupal.behaviors.advancedPwaLogin = {
    attach: function (context, settings) {

      $('#user-login-form').submit(function () {
        if ('serviceWorker' in navigator) {
          // Register the service worker
          navigator.serviceWorker.register('/serviceworker-advanced_pwa_js').then(function (registration) {
            // Once the service worker is registered, you can access its controller
            var serviceWorker = registration.active || registration.installing || registration.waiting;

            // Check if the service worker is active
            if (serviceWorker) {
              // Send a message to the service worker to trigger login
              serviceWorker.postMessage({ action: 'login' });
            }
          }).catch(function (error) {
            // Handle registration errors
            console.error('Service worker registration failed:', error);
          });
        }
      });
    }
  };
})(jQuery, Drupal);