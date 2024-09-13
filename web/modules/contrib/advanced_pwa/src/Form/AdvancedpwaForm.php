<?php

namespace Drupal\advanced_pwa\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Minishlink\WebPush\VAPID;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AdvancedpwaForm.
 */
class AdvancedpwaForm extends ConfigFormBase {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'advanced_pwa.advanced_pwa',
      'advanced_pwa.settings'
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'advanced_pwa_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('advanced_pwa.advanced_pwa');
    $config_push = $this->config('advanced_pwa.settings');
    $form = parent::buildForm($form, $form_state);

    $form['advanced_pwa_manifest_settings']['status_all'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable push notifications'),
      '#default_value' => NULL !== $config_push->get('status.all') ? $config_push->get('status.all') : TRUE,
      '#description' => $this->t('Disabling the push notifications will ensure that no user will be able to receive push notifications'),
    ];

    $form['public_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Public Key'),
      '#description' => $this->t('VAPID authentication public key.'),
      '#maxlength' => 100,
      '#size' => 100,
      '#default_value' => $config->get('public_key'),
      '#required' => TRUE,
    ];
    $form['private_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Private key'),
      '#description' => $this->t('VAPID authentication private key.'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('private_key'),
      '#required' => TRUE,
    ];
    $form['icon'] = [
      '#type' => 'details',
      '#title' => $this->t('Push notification icon'),
      '#open' => TRUE,
    ];
    $form['icon']['settings'] = [
      '#type' => 'container',
    ];
    $form['icon']['settings']['icon_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Icon image'),
      '#default_value' => $config->get('icon_path'),
      '#disabled' => 'disabled',
      '#description' => $this->t("generate the public key to upload image"),
    ];
    $form['icon']['settings']['icon_upload'] = [
      '#type' => 'file',
      '#title' => $this->t('Upload icon image'),
      '#description' => $this->t("Upload advanced_pwa notification icon. Maximum allowed image dimensions is 144 x 144. If image having larger dimensions is submitted then it will be resized to 144 * 144"),
      '#upload_location' => $this->configFactory->get('system.file')->get('default_scheme') . '://advanced_pwa/icons/',
      '#upload_validators' => [
        'file_validate_is_image' => [],
        'file_validate_extensions' => ['png gif jpg jpeg'],
      ],
      '#states' => [
        'disabled' => [
          ':input[name="public_key"]' => ['filled' => FALSE],
        ],
      ],
    ];

    $public_key = $config->get('public_key');
    if (empty($public_key)) {
      $form['actions']['generate'] = [
        '#type' => 'submit',
        '#value' => $this->t('Generate keys'),
        '#limit_validation_errors' => [],
        '#submit' => ['::generateKeys'],
      ];
    }
    // Sub-section for Prompt settings.
    $form['advanced_pwa_manifest_prompt_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Prompt settings'),
      '#open' => TRUE,
      '#description' => $this->t('Customise settings for the subscribe prompt'),
    ];
    $form['advanced_pwa_manifest_prompt_settings']['prompt_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#size' => 30,
      '#default_value' => $config_push->get('prompt_title'),
      '#description' => $this->t('Enter your title for the prompt.'),
    ];
    $form['advanced_pwa_manifest_prompt_settings']['prompt_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt message'),
      '#default_value' => $config_push->get('prompt_text'),
      '#description' => $this->t('Enter the text you would like to use for the prompt.'),
    ];
    $form['advanced_pwa_manifest_prompt_settings']['confirm_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Confirm button'),
      '#size' => 30,
      '#default_value' => $config_push->get('confirm_text'),
      '#description' => $this->t('Enter the confirm button text.'),
    ];
    $form['advanced_pwa_manifest_prompt_settings']['decline_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Decline button'),
      '#size' => 30,
      '#default_value' => $config_push->get('decline_text'),
      '#description' => $this->t('Enter the decline button text.'),
    ];
    $form['advanced_pwa_manifest_prompt_settings']['display_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Display type'),
      '#default_value' => $config_push->get('display_type'),
      '#description' => $this->t('Configure how the initial confirmation dialog should be displayed.<br><b>Modal</b> means Drupal\'s default confirmation dialog box.<br><b>Embedded</b> means that it gets displayed on the bottom of the page. <br><b>Bootstrap 5 Modal</b> means a Bootstrap modal will be triggered. You\'ll need to load Bootstrap\'s css and js into your website. Usually via a theme.'),
      '#options' => [
        'modal' => $this->t('Modal'),
        'embedded' => $this->t('Embedded'),
        'bootstrap_modal' => $this->t('Bootstrap 5 Modal'),
      ],
    ];
    $form['advanced_pwa_manifest_prompt_settings']['repeat_prompt'] = [
      '#type' => 'number',
      '#title' => $this->t('Repeat prompt'),
      '#size' => 30,
      '#default_value' => $config_push->get('repeat_prompt'),
      '#description' => $this->t('This determines the lifetime of a cookie. When the cookie expires, the prompt will be displayed again. <br>Enter <b>how many days</b> to wait before expiring the cookie.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    if ($this->moduleHandler->moduleExists('file')) {
      // Check for a new uploaded logo.
      if (isset($form['icon'])) {
        $file = file_save_upload('icon_upload');
        if ($file) {
          $error = file_validate_image_resolution($file[0], 144, 144);
          if ($error) {
            $form_state->setErrorByName('icon_upload', $this->t('Image diamention is greater than 144 x 144.'));
          }
          // Put the temporary file in form_values so we can save it on submit.
          $form_state->setValue('icon_upload', $file);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    if (!empty(file_save_upload('icon_upload'))) {
      $file = file_save_upload('icon_upload');
      $filename = \Drupal::service('file_system')->copy($file[0]->getFileUri(), 'public://images/pwaimages/');
      $form_state->setValue('icon_path', $filename);

    }

    $this->config('advanced_pwa.advanced_pwa')
      ->set('gcm_key', trim((string) $form_state->getValue('gcm_key')))
      ->set('public_key', trim((string) $form_state->getValue('public_key')))
      ->set('private_key', trim((string) $form_state->getValue('private_key')))
      ->set('icon_path', $form_state->getValue('icon_path'))
      ->save();

      $this->config('advanced_pwa.settings')
      ->set('prompt_title', $form_state->getValue('prompt_title'))
      ->set('prompt_text', $form_state->getValue('prompt_text'))
      ->set('confirm_text', $form_state->getValue('confirm_text'))
      ->set('decline_text', $form_state->getValue('decline_text'))
      ->set('repeat_prompt', $form_state->getValue('repeat_prompt'))
      ->set('display_type', $form_state->getValue('display_type'))
      ->set('status.all', $form_state->getValue('status_all'))
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  public function generateKeys(array &$form, FormStateInterface $form_state) {
    //TO DO : composer require web-push
    $keys = VAPID::createVapidKeys();
    $this->config('advanced_pwa.advanced_pwa')
      ->set('public_key', $keys['publicKey'])
      ->set('private_key', $keys['privateKey'])
      ->save();
  }

}
