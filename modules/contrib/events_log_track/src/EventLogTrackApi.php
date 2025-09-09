<?php

namespace Drupal\event_log_track;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Service's functions.
 *
 * @package Drupal\event_log_track
 */
class EventLogTrackApi {

  use StringTranslationTrait;

  /**
   * This module's configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * EventLogTrackApi constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(ConfigFactoryInterface $config_factory, Connection $database) {
    $this->config = $config_factory->get('event_log_track.settings');
    $this->database = $database;
  }

  /**
   * Method to get the old records.
   */
  public function getOldRecords(): array {
    $timespan_days = $this->config->get('timespan_limit');
    $timespan = strtotime('-' . $timespan_days . 'days midnight');
    $query = $this->database->select('event_log_track', 'e')
      ->fields('e', ['lid'])
      ->condition('e.created', $timespan, '<');
    return $query->execute()->fetchCol();
  }

  /**
   * Helper function to create batches.
   */
  public function deleteOldRecords($records): void {
    if (!empty($records)) {
      $data_chunks = array_chunk($records, $this->config->get('batch_size'));
      $operations = [];
      foreach ($data_chunks as $data_chunk) {
        $operations[] = ['_event_log_track_process_old_records', [$data_chunk]];
      }
      // Define your batch operation here.
      $batch = [
        'title' => $this->t('Deleting events track logs'),
        'operations' => $operations,
      ];
      batch_set($batch);
    }
  }

  /**
   * Helper function to get options titles from handler hooks.
   *
   * @return array
   *   Handler options returned in an associative array format.
   */
  public static function getHandlerOptions(): array {
    $handlers = drupal_static(__FUNCTION__);
    if ($handlers === NULL) {
      $handlers = \Drupal::moduleHandler()->invokeAll('event_log_track_handlers');
      \Drupal::moduleHandler()->alter('event_log_track_handlers', $handlers);
    }

    $options = [];
    foreach ($handlers as $type => $handler) {
      $options[$type] = $handler['title'];
    }
    return $options;
  }

  /**
   * Helper function to get options operations from handler hooks.
   *
   * @return array
   *   Handler options returned in an associative array format.
   */
  public static function getHandlerOptionsOperations(): array {
    $handlers = drupal_static(__FUNCTION__);
    if ($handlers === NULL) {
      $handlers = \Drupal::moduleHandler()->invokeAll('event_log_track_handlers');
      \Drupal::moduleHandler()->alter('event_log_track_handlers', $handlers);
    }

    $options = [];
    foreach ($handlers as $handler) {
      if (!empty($handler['operations'])) {
        foreach ($handler['operations'] as $operation) {
          $options[$operation] = $operation;
        }
      }
    }
    return $options;
  }

}
