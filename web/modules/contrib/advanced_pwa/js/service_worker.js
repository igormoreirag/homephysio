self.addEventListener('message', function(event) {
  if (event.data.action === 'logout') {
    clearCacheAndReloadPages().then(() => {
      event.source.postMessage({ type: 'reload' });
    });
  } else if (event.data.action === 'login') {
    clearCache().then(() => {
      event.source.postMessage({ result: 'Cache cleared' });
    });
  }
});

// Function to clear device cache and reload all pages
function clearCacheAndReloadPages() {
  return caches.keys().then(function(cacheNames) {
    return Promise.all(
      cacheNames.map(function(cacheName) {
        return caches.delete(cacheName);
      })
    );
  }).then(function() {
    console.log('All caches cleared!');
    setTimeout(function() {
      self.clients.matchAll().then(function(clients) {
        clients.forEach(function(client) {
          client.navigate(client.url);
        });
      });
    }, 1000);
  }).catch(function(error) {
    console.error('Error clearing cache:', error);
  });
}

// Function to clear device cache
function clearCache() {
  return caches.keys().then(function(cacheNames) {
    return Promise.all(
      cacheNames.map(function(cacheName) {
        return caches.delete(cacheName);
      })
    );
  }).then(function() {
    console.log('All caches cleared!');
  }).catch(function(error) {
    console.error('Error clearing caches:', error);
  });
}

var cacheName = 'syn-advanced_pwa-cache-v1';
var filesToCache = [
  'manifest.json'
];


// Global variables to store settings
let excludedUrls = [];
let cacheEnabled = false;

// Function to fetch and cache settings data from Drupal
function fetchAndCacheSettings() {
  return fetch('/advanced-pwa-settings-endpoint')
    .then(response => {
      if (!response.ok) {
        throw new Error('Failed to fetch settings data');
      }
      return response.json();
    })
    .then(settings => {
      // Store settings
      excludedUrls = settings.excludeUrls || [];
      cacheEnabled = settings.cachePages;
      // Cache settings for future use
      return caches.open('settings-cache').then(cache => {
        return cache.put('settings', new Response(JSON.stringify(settings)));
      });
    })
    .catch(error => {
      console.error('Error fetching settings:', error);
    });
}

// Function to check if a URL should be excluded from cache
function shouldExcludeFromCache(url) {
  return excludedUrls.some(pattern => {
    // Convert wildcard pattern to regular expression
    const regexPattern = pattern.replace(/\*/g, '.*');
    const regex = new RegExp('^' + regexPattern + '$');
    return regex.test(url);
  });
}

// Listen for installation event
self.addEventListener('install', event => {
  event.waitUntil(
    Promise.all([
      fetchAndCacheSettings(),
      caches.open(cacheName).then(cache => cache.addAll(filesToCache))
    ]).then(() => self.skipWaiting())
  );
});

// Fetch event to respect excluded URLs and cacheEnabled setting
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // Check if caching is enabled and if the request URL matches any excluded URL pattern
  if (cacheEnabled && !shouldExcludeFromCache(url.pathname)) {
    event.respondWith(
      caches.open(cacheName).then(cache => {
        return cache.match(event.request).then(cachedResponse => {
          if (cachedResponse) {
            return cachedResponse;
          }

          return fetch(event.request).then(networkResponse => {
            // Cache new responses (excluding POST requests)
            if (event.request.method === 'GET') {
              cache.put(event.request, networkResponse.clone());
              console.log('Cached.');
            }
            return networkResponse;
          });
        });
      })
    );
  } else {
    // If caching is disabled or the URL is excluded, fetch from network without caching
    event.respondWith(fetch(event.request));
    console.log('Not cached.');
  }
});

/**
 * Chat messages, emails, document updates, settings changes, photo uploads…
 * anything that you want to reach the server even if user navigates away or closes the tab.
 */
self.addEventListener('sync', function (event) {
  'use strict';
  if (event.tag === 'synFirstSync') {
    event.waitUntil(
      caches.open(cacheName).then(function (cache) {
        return cache.addAll(filesToCache);
      }).then(function () {
        return self.skipWaiting();
      })
    );
  }
});

self.addEventListener('push', function (event) {
  'use strict';
  // console.log('[Service Worker] Push Received.');
  var body;
  if (event.data) {
    body = event.data.text();
  }
  else {
    body = 'Push message no payload';
  }

  // console.log(`[Service Worker] Push had this data: "${body}"`);
  var str = JSON.parse(body);
  var options = {
    body: str['message'],
    icon: str['icon'],
    badge: str['icon'],
    vibrate: [100, 50, 100],
    data: {
      dateOfArrival: Date.now(),
      primaryKey: '1',
      url: str['url']
    },
    actions: [
      {action: 'close', title: 'Close'}
    ]
  };
  event.waitUntil(
    self.registration.showNotification(str['title'], options)
  );
});

self.addEventListener('notificationclick', function (event) {
  'use strict';
  // console.log('[Service Worker] Notification click Received.');

  var notification = event.notification;
  var action = event.action;
  var url;

  if (notification.data.url) {
    url = notification.data.url;
  }
  else {
    url = '/';
  }

  if (action === 'close') {
    notification.close();
  }
  else {
    event.waitUntil(
      clients.openWindow(url)
    );
  }
});

self.addEventListener('notificationclose', function (event) {
  'use strict';
  // console.log('Closed notification: ' + primaryKey);
});
