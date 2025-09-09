<?php

namespace Drupal\event_log_track_stdout\Logger;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Utility\Token;
use Psr\Log\LoggerInterface;

/**
 * Redirects logging messages to stdout.
 */
class EventLogStdout implements LoggerInterface {
  use RfcLoggerTrait;

  /**
   * A configuration object containing log_stdout settings.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected Config $stdoutConfig;

  /**
   * A configuration object containing event_log_track settings.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected Config $config;

  /**
   * Constructs a SysLog object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory object.
   * @param \Drupal\Core\Logger\LogMessageParserInterface $parser
   *   The parser to use when extracting message variables.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory service.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    protected LogMessageParserInterface $parser,
    protected LoggerChannelFactoryInterface $loggerFactory,
    protected Token $token,
  ) {
    $this->stdoutConfig = $config_factory->get('log_stdout.settings');
    $this->config = $config_factory->get('event_log_track.settings');
  }

  /**
   * Log the event.
   *
   * @throws \Psr\Log\InvalidArgumentException
   */
  public function logEvent($log): void {

    $id = $log['ref_numeric'] ?: $log['ref_char'] ?: '';

    $entry = $log + [
      'id' => $id,
      'description' => $log['description'],
    ];

    if ($log['operation'] == 'fail') {
      $entry['severity'] = RfcLogLevel::WARNING;
    }
    else {
      $entry['severity'] = RfcLogLevel::NOTICE;
    }

    $bubbleable_metadata = new BubbleableMetadata();
    $message = $this->token->replace($this->config->get('stdout.format'), ['event-log' => $entry], [], $bubbleable_metadata);

    // Manage multi-lines message.
    $message = str_replace(["\n", "\r"], ['#012', '#015'], $message);

    if ($this->config->get('stdout.output_type') == 'stdout') {
      $this->log($entry['severity'], $message, $log);
    }
    else {
      $this->loggerFactory->get('events_log_track')->log($entry['severity'], $message, $log);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []): void {

    // Populate the message placeholders and then replace them in the message.
    $message_placeholders = $this->parser->parseMessagePlaceholders($message, $context);
    $message = empty($message_placeholders) ? $message : strtr($message, $message_placeholders);

    $this->syslogWrapper($level, $message);
  }

  /**
   * A stdout wrapper to make stdout functionality testable.
   *
   * @param int $level
   *   The priority.
   * @param string $entry
   *   The message to send to syslog function.
   */
  protected function syslogWrapper(int $level, string $entry): void {
    if ($this->stdoutConfig->get('use_stderr') == '1' && $level <= RfcLogLevel::WARNING) {
      $output = fopen('php://stderr', 'w');
    }
    else {
      $output = fopen('php://stdout', 'w');
    }

    fwrite($output, $entry . "\r\n");
    fclose($output);
  }

}
