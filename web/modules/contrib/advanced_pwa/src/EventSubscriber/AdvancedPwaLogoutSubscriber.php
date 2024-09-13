<?php

namespace Drupal\advanced_pwa\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Event subscriber to run JavaScript when a user logs out.
 */
class AdvancedPwaLogoutSubscriber implements EventSubscriberInterface {

  /**
   * Adds JavaScript to the response on user logout.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The kernel event.
   */
  public function onLogoutResponse(ResponseEvent $event) {
    $route_name = \Drupal::routeMatch()->getRouteName();
    if ($route_name == 'user.logout') {
      $response = $event->getResponse();

      if ($response instanceof RedirectResponse) {
        // Add a query parameter to signal the logout action
        $url = $response->getTargetUrl();
        $url .= (parse_url($url, PHP_URL_QUERY) ? '&' : '?') . 'pwa_logout=1';
        $response->setTargetUrl($url);
      }
    }

    // Check if the request has the logout query parameter and add the script
    if ($event->getRequest()->query->get('pwa_logout')) {
      $response = $event->getResponse();
      if ($response->headers->get('Content-Type') === 'text/html; charset=UTF-8') {
        $content = $response->getContent();
        $script = "
        <script>
        (function() {
          // Clear cache first
          clearCache().then(function() {
            // Now send logout action to service worker
            if ('serviceWorker' in navigator) {
              navigator.serviceWorker.ready.then(function (registration) {
                registration.active.postMessage({ action: 'logout' });
              });
            }
          }).catch(function(error) {
            console.error('Error clearing cache:', error);
          });

          // Function to clear device cache
          function clearCache() {
            return caches.keys().then(function(cacheNames) {
              return Promise.all(
                cacheNames.map(function(cacheName) {
                  return caches.delete(cacheName);
                })
              );
            });
          }

          // Remove query parameter to prevent recursion
          if (window.history.replaceState) {
            var url = new URL(window.location);
            url.searchParams.delete('pwa_logout');
            window.history.replaceState({}, document.title, url.toString());
          }
        })();
        </script>
        ";
        $content = str_replace('</body>', $script . '</body>', $content);
        $response->setContent($content);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::RESPONSE => 'onLogoutResponse',
    ];
  }

}
