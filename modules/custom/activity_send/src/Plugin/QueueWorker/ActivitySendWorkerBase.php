<?php

namespace Drupal\activity_send\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Provides base functionality for the ActivitySendWorkers.
 */
abstract class ActivitySendWorkerBase extends QueueWorkerBase {

  /**
   * Create queue item.
   *
   * @param string $queue_name
   *   The queue name.
   * @param object $data
   *   The $data which should be stored in the queue item.
   */
  protected function createQueueItem(string $queue_name, object $data): void {
    $queue = \Drupal::queue($queue_name);
    $queue->createItem($data);
  }

}
