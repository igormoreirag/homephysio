<?php

namespace Drupal\advanced_pwa\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\file\Entity\File;
use Drupal\Core\Url;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;

/**
 * Configure advanced_pwa Manifest.
 */
class ManifestConfigurationForm extends ConfigFormBase {

  /**
   * The file storage service.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $fileStorage;

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The path alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The path validator.
   *
   * @var \Drupal\Core\Path\PathValidatorInterface
   */
  protected $pathValidator;

  /**
   * The request context.
   *
   * @var \Drupal\Core\Routing\RequestContext
   */
  protected $requestContext;

  /**
   * The file usage service.
   *
   * @var \Drupal\file\FileUsage\FileUsageInterface
   */
  protected $fileUsage;

  /**
   * Constructs a SiteInformationForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   The path alias manager.
   * @param \Drupal\Core\Path\PathValidatorInterface $path_validator
   *   The path validator.
   * @param \Drupal\Core\Routing\RequestContext $request_context
   *   The request context.
   * @param \Drupal\Core\Entity\EntityStorageInterface $file_storage
   *   The request file_storage.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The request currentUser.
   * @param \Drupal\file\FileUsage\FileUsageInterface $fileUsage
   */
  public function __construct(ConfigFactoryInterface $config_factory, AliasManagerInterface $alias_manager, PathValidatorInterface $path_validator, RequestContext $request_context, EntityStorageInterface $file_storage, AccountProxyInterface $currentUser, FileUsageInterface $fileUsage) {
    parent::__construct($config_factory);
    $this->aliasManager = $alias_manager;
    $this->pathValidator = $path_validator;
    $this->requestContext = $request_context;
    $this->fileStorage = $file_storage;
    $this->currentUser = $currentUser;
    $this->fileUsage = $fileUsage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('path_alias.manager'),
      $container->get('path.validator'),
      $container->get('router.request_context'),
      $container->get('entity_type.manager')->getStorage('file'),
      $container->get('current_user'),
      $container->get('file.usage')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'advanced_pwa_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['advanced_pwa.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('advanced_pwa.settings');

    // Attach libraries for the screenshots table.
    $form['#attached']['library'][] = 'core/jquery.once';
    $form['#attached']['library'][] = 'advanced_pwa/advanced_pwa.hide_screenshot_file_list';

    $form['advanced_pwa_manifest_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Manifest configuration'),
      '#open' => FALSE,
    ];
    $form['advanced_pwa_manifest_settings']['description'] = [
      '#markup' => $this->t('This is where you can configue your Progressive Web App\'s manifest file. This file is used to specify the details of your individual App. After saving your changes you have to <B>clear site cache</B>'),
    ];
    $form['advanced_pwa_manifest_settings']['short_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Short name'),
      '#size' => 12,
      '#default_value' => $config->get('short_name'),
      '#required' => TRUE,
      '#description' => $this->t('This is the name the user will see when they add your website to their homescreen. You might want to keep this short.'),
    ];
    $form['advanced_pwa_manifest_settings']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('App title'),
      '#size' => 30,
      '#default_value' => $config->get('name'),
      '#description' => $this->t('Enter the full title for your app. This is displayed when the install prompt is shown or in the app stores.'),
    ];
    $form['advanced_pwa_manifest_settings']['app_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('App ID'),
      '#default_value' => $config->get('app_id') ?? $this->getBaseUrlWithoutProtocol(),
      '#description' => $this->t('Enter the unique App ID. Defaults to the site URL without the protocol.'),
    ];
    $form['advanced_pwa_manifest_settings']['app_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('App Description'),
      '#default_value' => $config->get('app_description'),
      '#maxlength' => 300,
      '#description' => $this->t('Enter the description for your PWA (maximum 300 characters).'),
    ];

    $icon = $config->get('icons.icon');
    $fid = is_array($icon) ? $icon[0] : null;

    $form['advanced_pwa_manifest_settings']['icon'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('General App Icon'),
      '#description' => $this->t('Provide a square (.png) image. This image serves as your icon when the user adds the website to their home screen. <i>Minimum dimensions are 512px x 512px.</i> If a larger image is uploaded, it will be resized to 512px x 512px.'),
      '#default_value' => $fid? [$fid] : [],
      '#required' => TRUE,
      '#upload_location' => $this->configFactory->get('system.file')->get('default_scheme'). '://advanced_pwa/icons/',
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg'],
        'file_validate_image_resolution' => ['512x512', '512x512'],
      ],
    ];
    $form['advanced_pwa_manifest_settings']['background_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Background Color'),
      '#default_value' => $config->get('background_color'),
      '#description' => $this->t('Select a background color for the launch screen. This is shown when the user opens the website from their homescreen.'),
    ];
    $form['advanced_pwa_manifest_settings']['theme_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Theme Color'),
      '#default_value' => $config->get('theme_color'),
      '#description' => $this->t('This color is used to create a consistent experience in the browser when the users launch your website from their homescreen.'),
    ];

    $form['advanced_pwa_manifest_settings']['dir'] = [
      '#type' => 'select',
      '#title' => $this->t('Text direction'),
      '#description' => $this->t('Select the text direction.'),
      '#options' => [
        'ltr' => $this->t('Left to right'),
        'rtl' => $this->t('Right to left'),
        'auto' => $this->t('Auto'),
      ],
      '#default_value' => $config->get('dir'),
    ];
    
    // Section for Screenshots

    $form['#attached']['drupalSettings']['tableDrag']['edit-uploaded-screenshots'] = [
      'action' => 'order',
      'relationship' => 'sibling',
      'group' => 'screenshots-order-weight',
    ];

    $form['advanced_pwa_manifest_screenshots'] = [
      '#type' => 'details',
      '#title' => $this->t('App Screenshots'),
      '#open' => TRUE,
    ];
    $form['advanced_pwa_manifest_screenshots']['description'] = [
      '#markup' => $this->t('Here you can upload the screenshots for your app. You have to upload up to 8 images.
        For image size, 1080x1920px is strongly recommeded.<br><br>
        The official specs don\'t require a specific size. The width and height of your screenshots must be at
        least 370px and at most 3840px. <br><br>
        The maximum dimension can\'t be more than 2.3 times as long as the minimum dimension.
        So screenshots can be landscape, square or portrait.<br><br>
        However, every screenshot in a set must have the same aspect ratio. Only JPG and PNG image formats
        are supported.'),
    ];

    $stored_fids = $form_state->getValue('screenshots') ?: $config->get('screenshots') ?: [];
    $files = File::loadMultiple($stored_fids);

    // Container for the table.
    $form['advanced_pwa_manifest_screenshots']['screenshots_table'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'screenshots-table-wrapper'],
    ];

    // Table to reorder images.
    $form['advanced_pwa_manifest_screenshots']['screenshots_table']['uploaded_screenshots'] = [
      '#type' => 'table',
      '#header' => [$this->t('Screenshots'), $this->t('Operations')],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'screenshots-order-weight',
        ],
      ],
      '#id' => 'edit-uploaded-screenshots', // Ensure this ID is consistent
      '#empty' => $this->t('No screenshots uploaded yet.'),
      '#attached' => [
        'library' => [
          'core/drupal.tabledrag',
          'advanced_pwa/advanced_pwa.draggable',
        ],
      ],
    ];

    $weight = 0;
    foreach ($files as $file) {
      $form['advanced_pwa_manifest_screenshots']['screenshots_table']['uploaded_screenshots'][$file->id()] = [
        '#attributes' => ['class' => ['draggable']],
      ];
      $form['advanced_pwa_manifest_screenshots']['screenshots_table']['uploaded_screenshots'][$file->id()]['image'] = [
        '#theme' => 'image',
        '#uri' => $file->getFileUri(),
        '#width' => 100,
        '#height' => 100,
      ];
      $form['advanced_pwa_manifest_screenshots']['screenshots_table']['uploaded_screenshots'][$file->id()]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for row'),
        '#title_display' => 'invisible',
        '#default_value' => $weight,
        '#attributes' => ['class' => ['screenshots-order-weight']],
      ];
      $form['advanced_pwa_manifest_screenshots']['screenshots_table']['uploaded_screenshots'][$file->id()]['operations'] = [
        '#type' => 'actions',
        'remove' => [
          '#type' => 'submit',
          '#value' => $this->t('Remove'),
          '#name' => 'remove_screenshot_' . $file->id(),
          '#submit' => [[$this, 'removeScreenshot']],
          '#ajax' => [
            'callback' => '::ajaxFileUpload',
            'wrapper' => 'screenshots-table-wrapper',
            'effect' => 'fade',
          ],
          '#file_id' => $file->id(),
        ],
      ];
      $weight++;
    }
    $form['advanced_pwa_manifest_screenshots']['screenshots'] = [
      '#type' => 'advanced_pwa_screenshot',
      '#title' => $this->t('Screenshots'),
      '#description' => $this->t('Upload your screenshots.'),
      '#upload_location' => $this->configFactory->get('system.file')->get('default_scheme') . '://advanced_pwa/screenshots/',
      '#default_value' => $stored_fids,
      '#multiple' => TRUE,
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg'],
      ],
    ];
    
    // Sub-section for Advanced Settings.
    $form['advanced_pwa_manifest_advanced_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced settings'),
      '#open' => FALSE,
    ];
    $form['advanced_pwa_manifest_advanced_settings']['notice'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Please notice:'),
      '#open' => FALSE,
      '#description' => $this->t('These settings have been set automatically to serve the most common use cases. Only change these settings if you know what you are doing.'),
    ];
    $form['advanced_pwa_manifest_advanced_settings']['start_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Start URL'),
      '#size' => 15,
      '#disabled' => FALSE,
      '#description' => $this->t('This is the URL to send people to when they open your App. <br><br>
        I use /user. That way they start at the login page or user profile.'),
      '#default_value' => $config->get('start_url'),
      '#field_prefix' => $this->requestContext->getCompleteBaseUrl(),
    ];
    $form['advanced_pwa_manifest_advanced_settings']['display'] = [
      '#type' => 'select',
      '#title' => $this->t('Display'),
      '#default_value' => $config->get('display'),
      '#description' => $this->t('<u>When the site is being launched from the homescreen, you can launch it in:</u></br>
      <b>Fullscreen:</b><i> This will cover up the entire display of the device.</i></br>
      <b>Standalone:</b> <i>(default) Kind of the same as Fullscreen, but only shows the top info bar of the device. (Telecom provider, time, battery etc.)</i></br>
      <b>Browser:</b> <i>It will simply just run from the browser on your device with all the user interface elements of the browser.</i>'),
      '#options' => [
        'fullscreen' => $this->t('Fullscreen'),
        'standalone' => $this->t('Standalone (Default)'),
        'browser' => $this->t('Browser'),
      ],
    ];
    $form['advanced_pwa_manifest_advanced_settings']['orientation'] = [
      '#type' => 'select',
      '#title' => $this->t('Orientation'),
      '#default_value' => $config->get('orientation'),
      '#description' => $this->t('Configures if the site should run in: '
              . '<br><b>Portrait (default)</b> The screen aspect ratio has a height greater than the width.'
              . '<br><b>Landscape</b> The screen aspect ratio has a width greater than the height.'
              . '<br><b>Any</b> The screen can be rotated by the user to any orientation allowed by the device operating system or by the end-user.'
              . '<br><b>Natural</b> The natural orientation for the device display as determined by the user agent, the user, the operating system, or the screen itself. For example, a device viewed, or held upright in the users hand, with the screen facing the user. A computer monitor are commonly naturally landscape, while a mobile phones are commonly naturally portrait.'
              . '<br><b>Primary</b> The device screens natural orientation for either portrait or landscape.'
              . '<br><b>Secondary</b> The opposite of the device screens primary orientation for portrait or landscape.'),
      '#options' => [
        'portrait' => $this->t('Portrait'),
        'landscape' => $this->t('Landscape'),
        'any' => $this->t('Any'),
        'natural' => $this->t('Natural'),
        'primary' => $this->t('Primary'),
        'secondary' => $this->t('Secondary'),
      ],
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate front page path.
    if (($value = $form_state->getValue('start_url')) && $value[0] !== '/') {
      $form_state->setErrorByName('start_url', $this->t("The path '%path' has to start with a slash.", ['%path' => $form_state->getValue('start_url')]));
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * Helper function to get the base URL without the protocol.
   */
  private function getBaseUrlWithoutProtocol() {
    $base_url = $this->requestContext->getCompleteBaseUrl();
    $parsed_url = parse_url($base_url);
    return $parsed_url['host'] . ($parsed_url['path'] ?? '');
  }

  /**
   * Custom AJAX callback for updating the screenshots table.
   */
  public function ajaxFileUpload(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
    return $form['advanced_pwa_manifest_screenshots']['screenshots_table'];
  }

  public function removeScreenshot(array &$form, FormStateInterface $form_state) {
    $file_id = $form_state->getTriggeringElement()['#file_id'];
    if ($file = $this->fileStorage->load($file_id)) {
      $this->fileUsage->delete($file, 'advanced_pwa', 'screenshot', $file_id);
      $file->delete();
    }
    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $current_user_id = $this->currentUser->id();
    $config = $this->config('advanced_pwa.settings');

    // App Icon
    $icon = $form_state->getValue('icon');
    // Load the object of the file by its fid.
    /** @var \Drupal\file\FileInterface $file */
    $file = $this->fileStorage->load($icon[0]);
    // Set the status flag permanent of the file object.
    if (!empty($file)) {
      // Flag the file permanent.
      $file->setPermanent();
      // Save the file in the database.
      $file->save();
      $this->fileUsage->add($file, 'advanced_pwa', 'icon', $current_user_id);
    }

   // Screenshots
   $screenshots = $form_state->getValue('screenshots');
    foreach ($screenshots as $screenshot) {
      $file = $this->fileStorage->load($screenshot);
      if (!empty($file)) {
        $file->setPermanent();
        $file->save();
        $this->fileUsage->add($file, 'advanced_pwa', 'screenshot', $current_user_id);
      }
    }

    // Get the ordered list of screenshots from the table
    $ordered_screenshots = [];
    if ($form_state->hasValue(['uploaded_screenshots'])) {
      foreach ($form_state->getValue(['uploaded_screenshots']) as $file_id => $values) {
        $ordered_screenshots[] = $file_id;
      }
    } else {
      $ordered_screenshots = $screenshots;
    }

    // Add newly uploaded screenshots to table
    foreach ($screenshots as $screenshot) {
      if (!in_array($screenshot, $ordered_screenshots)) {
        $ordered_screenshots[] = $screenshot;
      }
    }


    $config->set('status.all', $form_state->getValue('status_all'))
      ->set('name', $form_state->getValue('name'))
      ->set('short_name', $form_state->getValue('short_name'))
      ->set('start_url', $form_state->getValue('start_url'))
      ->set('background_color', $form_state->getValue('background_color'))
      ->set('theme_color', $form_state->getValue('theme_color'))
      ->set('display', $form_state->getValue('display'))
      ->set('orientation', $form_state->getValue('orientation'))
      ->set('icons.icon', $form_state->getValue('icon'))
      ->set('app_id', $form_state->getValue('app_id'))
      ->set('app_description', $form_state->getValue('app_description'))
      ->set('dir', $form_state->getValue('dir'))
      ->set('screenshots', $ordered_screenshots)
      ->save();
  }

}
