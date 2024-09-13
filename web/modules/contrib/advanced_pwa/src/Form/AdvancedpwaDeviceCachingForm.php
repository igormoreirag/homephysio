<?php

namespace Drupal\advanced_pwa\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class AdvancedpwaDeviceCachingForm extends ConfigFormBase {

  protected function getEditableConfigNames() {
    return ['advanced_pwa.settings'];
  }

  public function getFormId() {
    return 'advanced_pwa_device_caching_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('advanced_pwa.settings');

    $form['device_caching'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Device Caching'),
      '#default_value' => $config->get('device_caching'),
    ];

    $form['caching_user_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select User Roles'),
      '#options' => user_role_names(),
      '#default_value' => $config->get('caching_user_roles'),
      '#description' => $this->t('Select the roles that you want to activate device caching for.'),
    ];

    $form['specify_excluded_urls'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Specify Excluded URLs'),
      '#default_value' => $config->get('specify_excluded_urls') !== null ? $config->get('specify_excluded_urls') : TRUE,
      '#ajax' => [
        'callback' => '::toggleExcludedUrlsField',
        'wrapper' => 'excluded-urls-wrapper',
      ],
    ];

    $form['excluded_urls'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Excluded URLs'),
      '#description' => $this->t('You can use * as a wildcard. Please enter one URL per line. For example /node/*/edit or /admin/*<br>Please note that Drupal system paths <strong>do not</strong> work. For example, if you had an alias for /node/1, /node/* would not exclude it. If you didn\'t have an alias it would.<br>This affects the URL as it is in the browser.'),
      '#default_value' => $config->get('excluded_urls') !== null ? $config->get('excluded_urls') : "/admin/*\n/node/*/edit\n/node/*/delete\n/user/*/edit\n/user/*/delete\n/system/ajax\n/views/ajax",
      '#disabled' => !$config->get('specify_excluded_urls'),
      '#states' => [
        'disabled' => [
          ':input[name="specify_excluded_urls"]' => ['checked' => FALSE],
        ],
      ],
    ];



    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('advanced_pwa.settings')
      ->set('device_caching', $form_state->getValue('device_caching'))
      ->set('caching_user_roles', $form_state->getValue('caching_user_roles'))
      ->set('specify_excluded_urls', $form_state->getValue('specify_excluded_urls'))
      ->set('excluded_urls', $form_state->getValue('excluded_urls'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Ajax callback to toggle the excluded URLs field visibility and disable it.
   */
  public function toggleExcludedUrlsField(array &$form, FormStateInterface $form_state) {
    $form['excluded_urls']['#disabled'] = !$form_state->getValue('specify_excluded_urls');
    return $form['excluded_urls'];
  }
}
