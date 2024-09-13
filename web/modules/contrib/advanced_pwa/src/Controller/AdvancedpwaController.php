<?php

namespace Drupal\advanced_pwa\Controller;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\advanced_pwa\Model\SubscriptionsDatastorage;
use Symfony\Component\HttpFoundation\Response;
use Drupal\image\Entity\ImageStyle;
use Psr\Log\LoggerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityStorageInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class AdvancedpwaController.
 */
class AdvancedpwaController extends ControllerBase {

  protected $database;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The state key/value store.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $fileStorage;

  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('database'),
        $container->get('current_user'),
        $container->get('logger.factory')->get('advanced_pwa'),
        $container->get('entity_type.manager'),
        $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(Connection $database, AccountInterface $current_user, LoggerInterface $logger, EntityTypeManagerInterface $entity_type, StateInterface $state) {
    $this->database = $database;
    $this->currentUser = $current_user;
    $this->logger = $logger;
    try {
      $this->fileStorage = $entity_type->getStorage('file');
    } catch (InvalidPluginDefinitionException $e) {
    } catch (PluginNotFoundException $e) {
    }
    $this->state = $state;
  }

  /**
   * Subscribe.
   *
   * @return string
   *   Return Hello string.
   */
  public function subscribe(Request $request) {
    if ($request) {
      $message = 'Subscribe: ' . $request->getContent();
      $this->logger->info($message);
      $account = \Drupal::currentUser()->id();

      $data = json_decode($request->getContent(), TRUE);
      $entry['subscription_endpoint'] = $data['endpoint'];
      $entry['subscription_data'] = serialize(['key' => $data['key'], 'token' => $data['token']]);
      $entry['registered_on'] = strtotime(date('Y-m-d H:i:s'));
      $entry['uid'] = $account;
      $success = SubscriptionsDatastorage::insert($entry);
      return new JsonResponse([$success]);
    }
  }

  /**
   * Un-subscribe.
   *
   * @return string
   *   Return Hello string.
   */
  public function unsubscribe(Request $request) {
    if ($request) {
      $message = 'Un-subscribe : ' . $request->getContent();
      $this->logger->info($message);

      $data = json_decode($request->getContent(), TRUE);
      $entry['subscription_endpoint'] = $data['endpoint'];
      $success = SubscriptionsDatastorage::delete($entry);
      return new JsonResponse([$success]);
    }
  }

  /**
   * List of all subscribed users.
   */
  public function subscriptionList() {
    // The table description.
    $header = [
      ['data' => $this->t('Id')],
      ['data' => $this->t('Subscription Endpoint')],
      ['data' => $this->t('Registeration Date')],
      ['data' => $this->t('UID')],
    ];
    $getFields = [
      'id',
      'subscription_endpoint',
      'registered_on',
      'uid',
    ];
    $query = $this->database->select(SubscriptionsDatastorage::$subscriptionTable);
    $query->fields(SubscriptionsDatastorage::$subscriptionTable, $getFields);
    $pager = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender');
    $result = $pager->execute();

    // Populate the rows.
    $rows = [];
    foreach ($result as $row) {
      $rows[] = [
        'data' => [
          'id' => $row->id,
          'register_id' => $row->subscription_endpoint,
          'date' => date('d/m/Y', $row->registered_on),
          'uid' => $row->uid,
        ],
      ];
    }
    if (empty($rows)) {
      $markup = $this->t('No record found.');
    }
    else {
      $markup = $this->t('List of All Subscribed Users.');
    }
    $build = [
      '#markup' => $markup,
    ];
    // Generate the table.
    $build['config_table'] = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];

    $build['pager'] = [
      '#type' => 'pager',
    ];
    return $build;
  }

  /**
   * Route generates the manifest file for the browser.
   */
  public function advancedpwaGetManifest() {
    $config = $this->config('advanced_pwa.settings');

    $manifest = [
      'name' => $config->get('name'),
      'short_name' => $config->get('short_name'),
      'start_url' => $config->get('start_url'),
      'background_color' => $config->get('background_color'),
      'theme_color' => $config->get('theme_color'),
      'display' => $config->get('display'),
      'orientation' => $config->get('orientation'),
      'icons' => $this->getIcons(),
      'id' => $config->get('app_id') ?? $this->requestContext->getCompleteBaseUrl(),
      'description' => $config->get('app_description'),
      'dir' => $config->get('dir') ?? 'auto',
      'screenshots' => $this->getScreenshots($config->get('screenshots')),
    ];

    return new JsonResponse(array_filter($manifest));
  }

  private function getIcons() {
    $icons = [];
    $icon_fid = $this->config('advanced_pwa.settings')->get('icons.icon');
    if ($icon_fid) {
      $file = $this->fileStorage->load($icon_fid[0]);
      if ($file) {
        $icons[] = [
          'src' => \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri()),
          'sizes' => '512x512',
          'type' => $file->getMimeType(),
        ];
      }
    }
    return $icons;
  }

  private function getScreenshots($screenshot_ids) {
    $screenshots = [];
    if (!empty($screenshot_ids)) {
        foreach ($screenshot_ids as $screenshot_id) {
            $file = $this->fileStorage->load($screenshot_id);
            if ($file) {
                $file_uri = $file->getFileUri();
                $absolute_file_path = \Drupal::service('file_system')->realpath($file_uri);

                // Load image dimensions using Drupal's image factory
                $image_factory = \Drupal::service('image.factory');
                $image = $image_factory->get($absolute_file_path);

                if ($image->isValid()) {
                    $width = $image->getWidth();
                    $height = $image->getHeight();
                    $sizes = "{$width}x{$height}";
                } else {
                    // Default size or handle error
                    $sizes = '540x720';  // Adjust default size if needed
                }

                $screenshots[] = [
                    'src' => \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri()),
                    'sizes' => $sizes,
                    'type' => $file->getMimeType(),
                ];
            }
        }
    }
    return $screenshots;
}

  /**
   * Import service worker js.
   */
  public function advancedpwaServiceWorkerFileData() {
    $query_string = $this->state->get('system.css_js_query_string') ?: 0;
    $path = \Drupal::service('extension.list.module')->getPath('advanced_pwa');
    $data = 'importScripts("' . $path . '/js/service_worker.js?' . $query_string . '");';

    return new Response($data, 200, [
      'Content-Type' => 'application/javascript',
      'Service-Worker-Allowed' => '/',
    ]);
  }

/**
  * Returns the settings data in JSON format.
  *
  * @return \Symfony\Component\HttpFoundation\JsonResponse
  *   JSON response containing settings data.
  */
  public function settings() {

    $config = \Drupal::config('advanced_pwa.settings');

    // Get the Excluded URL's setting.
    $excluded_urls_setting = $config->get('excluded_urls');

    // Split the URL's by lines
    $lines = explode("\n", $excluded_urls_setting);

    // Trim each URL to remove extra spaces
    $trimmed_lines = array_map('trim', $lines);

    // Store the URL's in an array
    $excluded_urls = [];
    if ($config->get('specify_excluded_urls')) {
      foreach ($trimmed_lines as $line) {
        $excluded_urls[] = $line;
      }
    }
    // Check if the page should be cached for current user
    $device_caching_setting = $config->get('device_caching');
    $role_enabled = $this->userHasSelectedRoles();
    if ($device_caching_setting && $role_enabled) {
      $cache_pages = TRUE;
    } else {
      $cache_pages = FALSE;
    }

    // Return settings as json
    $settings = [
      'excludeUrls' => $excluded_urls,
      'cachePages' => $cache_pages
    ];

    return new JsonResponse($settings);
  }

  /**
   * Checks if the current user has any of the selected roles.
   *
   * @return bool
   *   TRUE if the user has any of the selected roles, FALSE otherwise.
   */
  public function userHasSelectedRoles() {
    // Get the selected roles from the configuration.
    $selected_roles_config = \Drupal::config('advanced_pwa.settings')->get('caching_user_roles');
    // Get the roles of the current user.
    $user_roles = array_flip($this->currentUser->getRoles());

    // Check if the user has any of the selected roles.
    foreach ($selected_roles_config as $role => $checked) {
        if (isset($user_roles[$role]) && $checked) {
            return TRUE;
        }
    }

    return FALSE;
  }

  /**
   * Returns the Unix timestamp of the last updated time of a node.
   */
  public function readHistory($node_id) {
    $time = time();
    return new JsonResponse($time);
  }

}
