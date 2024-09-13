<?php

namespace Drupal\advanced_pwa\Element;

use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\file\Element\ManagedFile;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\file\Entity\File;

/**
 * Provides a custom managed file element.
 *
 * @FormElement("advanced_pwa_screenshot")
 */
class AdvancedpwaScreenshotUpload extends ManagedFile {

  /**
   * {@inheritdoc}
   */
  public static function uploadAjaxCallback(&$form, FormStateInterface &$form_state, Request $request) {
    // Ensure the form is rebuilt.
    $form_state->setRebuild();

    // Render the updated table.
    $table = $form['advanced_pwa_manifest_screenshots']['screenshots_table'];
    $table_element = \Drupal::service('renderer')->renderRoot($table);

    // Extend the response to update the table.
    $response = parent::uploadAjaxCallback($form, $form_state, $request);
    // Update table
    $response->addCommand(new HtmlCommand('#screenshots-table-wrapper', $table_element));
    // Re-apply table drag JavaScript behavior.
    $response->addCommand(new InvokeCommand('#screenshots-table-wrapper', 'trigger', ['drupalAttachBehaviors']));
    return $response;
  }
}
