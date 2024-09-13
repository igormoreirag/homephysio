<?php

namespace Drupal\advanced_pwa\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Processes Node Tasks.
 *
 * @QueueWorker(
 *   id = "cron_send_notification",
 *   title = @Translation("Task Worker: Push notification"),
 *   cron = {"time" = 10}
 * )
 */
class AdvancedpwaQueueProcessor extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($response) {
    $str = json_decode($response->notification_data, TRUE);
    $sendNotificationService = \Drupal::service('advanced_pwa.push_notifications');
    return $sendNotificationService::sendNotificationStart($response->subscriptions, $response->notification_data);
  }

}
