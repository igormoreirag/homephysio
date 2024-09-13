# Advanced PWA
- Turn your website into a Progressive Web App
- Send push notifications to subscribed devices

## What is a Progressive Web App aka PWA?

Progressive web applications are web applications that load like regular web pages or websites but can offer the user functionality such as working offline, push notifications, and device hardware access traditionally available only to native applications.

Follow [Wikipedia](https://en.wikipedia.org/wiki/Progressive_web_applications) for more information.

Once you've set the module up, you go to [PWA Builder](https://www.pwabuilder.com/). Pop in your Drupal website URL and it'll create the files needed to be uploaded to various app stores.

If you know Drupal, you now know how to make mobile app's.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/advanced_pwa).

## Table of contents

- Requirements
- Recommended modules
- Installation
- Configuration
- Documentation
- Sending push notifications via our service
- Maintainers

## Requirements

- Site domain should be SSL enabled. Push notifications only works on SSL
enabled domains
- [minishlink/web-push](https://packagist.org/packages/minishlink/web-push#v9.0.0-rc2) library version 9.0 (installed automatically when using composer to install module)

## Recommended modules

[Markdown filter](https://www.drupal.org/project/markdown): When enabled,
display of the project's README.md help will be rendered with markdown.
Alternatively, see the [Advanced PWA project page on drupalcode.org](https://git.drupalcode.org/project/advanced_pwa)

[Advanced PWA Rules](https://www.drupal.org/project/advanced_pwa_rules): When enabled, you can send push notifications to all devices or devices belonging to a specific user via point and click configuration.

[Advanced PWA Rules Flag](https://www.drupal.org/project/advanced_pwa_rules_flag): When enabled, You can send a push notification to flagging users of a node or user via point and click configuration. Great for subscribing to content or following users.
 
## Installation

Install as you would normally install a contributed Drupal module. 

For further information, see [Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

## Configuration

Once you install the module, a link 'Advanced PWA Settings' will appear on '/admin/config' page under 'System'.

1. Go to "Manifest configuration" tab (/admin/config/system/advanced-pwa) to configure your App's manifest.
    - Short name: This is the name the user will see when they add your website to their homescreen.
    - App title: This is displayed when the install prompt is shown or in the app stores.
    - Unique App ID: Defaults to the site URL without the protocol.
    - App Description: This is used on the install prompt and App stores.
    - General App Icon: This is used for the App icon when installed.
    - Background Color: Select a background color for the launch screen. This is shown when the user opens the website from their homescreen.
    - Theme Color: This color is used to create a consistent experience in the browser when the users launch your website from their homescreen.
    - App screenshots: These are shown on the install prompt and App stores.
    - Start URL: When the App is launched, this is the URL that users are sent to.
    - Display: This is the way the App is launched. Detailed descriptions are on the form.
    - Orientation: All options are supported. Detailed descriptions are on the form.
  2. Go to "Push configuration" tab (/admin/config/system/advanced-pwa/config) to configure push notifications.    
      - It is required that you **generate keys first.** The button is on the bottom of the form.
      - Enable push notifications: Disabling the push notifications will ensure that no user will be able to receive push notifications
      - Push Notification Icon: This is the icon that is used on the push notifications.
      - Prompt Settings: Title, Prompt message, Confirm button, Decline button, Display type (Drupal Modal, Embedded and Bootstrap 5 Modal). Repeat prompt: How many days to wait until showing the prompt.
  3. Go to "Device caching" tab (/admin/config/system/advanced-pwa/device-caching) to configure device caching.
      - Enable Device Caching: On / Off
      - Select User Roles: Select who to enable caching for.
      - Specify Excluded URLs: The wildcard * is supported. Choose what you do not want cached.
  4. Go to "New content notifications" (/admin/config/system/advanced-pwa/config-subscription) to configure push notifications when a new piece of content is created.
      - Published content notifications: On / Off.
      - Available Content types: Select the content types to send notifications for
  5. Additionally, you can create a link to trigger the push prompt. Use: `<span class="push-prompt-link">Some plain text <span class="trigger-push-prompt">The link text</span></span>`. The link and text will only display if the user has permission, hasn't already subscribed and hasn't clicked block in the browser.

## Documentation
[Please watch this YouTube Video](https://youtu.be/2W24vsChDss)

## Sending push notifications via our service

You can use 'push_notification' functionality as a service from any of your custom modules, if needed.

These example's will add the push notifications to the queue to be sent on cron runs.

**Usage example:**

1. Send push notification to all users
 ```
  // Add this to the top of your file
  use Drupal\advanced_pwa\Model\SubscriptionsDatastorage;
  use Drupal\Core\Queue\QueueFactory;
  use Drupal\Core\Queue\QueueInterface;
  use Drupal\Component\Serialization\Json;

  // Inside your code, check if push notifications are turned on globally
    $status = \Drupal::config('advanced_pwa.settings')->get('status.all');
    if ($status) {
      $advanced_pwa_config = \Drupal::config('advanced_pwa.advanced_pwa');
      $icon = $advanced_pwa_config->get('icon_path');
      $icon_path = \Drupal::service('file_url_generator')->generateAbsoluteString($icon);
     
      $entry = [
        'title' => $notification_title, // A string
        'message' => $notification_message, // A string
        'icon' => $icon_path,
      ];
      if ($content_link) {
          $entry['url'] = $content_link; // A full URL inc protocol ie https://example.com
      }
      $notification_data = Json::encode($entry);
      $subscriptions = SubscriptionsDatastorage::loadAll();
      $advanced_pwa_public_key = $advanced_pwa_config->get('public_key');
      $advanced_pwa_private_key = $advanced_pwa_config->get('private_key');
      if (!empty($subscriptions) && !empty($advanced_pwa_public_key) && !empty($advanced_pwa_private_key)) {
        /** @var QueueFactory $queue_factory */
        $queue_factory = \Drupal::service('queue');
        $queue = $queue_factory->get('cron_send_notification');
        $item = new \stdClass();
        $item->subscriptions = $subscriptions;
        $item->notification_data = $notification_data;
        $queue->createItem($item);
      }
    }
```
2. Send notification to specific user:

```
// Add this to the top of your file
use Drupal\advanced_pwa\Model\SubscriptionsDatastorage;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;

// In your code
// Check if push notifications are turned on globally
    $status = \Drupal::config('advanced_pwa.settings')->get('status.all');
    if ($status) {
      $advanced_pwa_config = \Drupal::config('advanced_pwa.advanced_pwa');
      $icon = $advanced_pwa_config->get('icon_path');
      $icon_path = \Drupal::service('file_url_generator')->generateAbsoluteString($icon);
      $entry = [
        'title' => $notification_title, // A string
        'message' => $notification_message, // A string
        'icon' => $icon_path,
      ];
      if ($content_link) {
          $entry['url'] = $content_link; // A full URL inc protocol ie https://example.com
      }
      $notification_data = Json::encode($entry);
      $push_notification_service = \Drupal::service('advanced_pwa.push_notifications');
      $subscriptions = SubscriptionsDatastorage::loadAllByUID($uid); // You need to define $uid (User ID)
      $advanced_pwa_public_key = $advanced_pwa_config->get('public_key');
      $advanced_pwa_private_key = $advanced_pwa_config->get('private_key');
      if (!empty($subscriptions) && !empty($advanced_pwa_public_key) && !empty($advanced_pwa_private_key)) {
        /** @var QueueFactory $queue_factory */
        $queue_factory = \Drupal::service('queue');
        $queue = $queue_factory->get('cron_send_notification');
        $item = new \stdClass();
        $item->subscriptions = $subscriptions;
        $item->notification_data = $notification_data;
        $queue->createItem($item);
      }
    }

```

Check the file 'src/Plugin/QueueWorker/AdvancedpwaQueueProcessor.php' for more details.

## Maintainers

- Guy Doughty - [gMaximus](https://www.drupal.org/u/gmaximus)
- Mandar Bhagwat - [mandarmbhagwat78](https://www.drupal.org/u/mandarmbhagwat78)
- Rutuj Deshpande - [Rutuj](https://www.drupal.org/u/rutuj)
- Shailesh Bhosale - [shailesh.bhosale](https://www.drupal.org/u/shaileshbhosale)
