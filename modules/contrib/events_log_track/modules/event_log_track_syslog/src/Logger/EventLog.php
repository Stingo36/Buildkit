<?php

namespace Drupal\event_log_track_syslog\Logger;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Token;
use Psr\Log\LoggerInterface;

/**
 * Redirects logging messages to syslog.
 */
class EventLog implements LoggerInterface {
  use RfcLoggerTrait;
  use StringTranslationTrait;

  /**
   * A configuration object containing syslog settings.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected Config $syslogConfig;

  /**
   * A configuration object containing event_log_track settings.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected Config $config;

  /**
   * The message's placeholders parser.
   *
   * @var \Drupal\Core\Logger\LogMessageParserInterface
   */
  protected LogMessageParserInterface $parser;

  /**
   * Stores whether there is a system logger connection opened or not.
   *
   * @var bool
   */
  protected bool $connectionOpened = FALSE;

  /**
   * The logger factory service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected Token $token;

  /**
   * Constructs a SysLog object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory object.
   * @param \Drupal\Core\Logger\LogMessageParserInterface $parser
   *   The parser to use when extracting message variables.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LogMessageParserInterface $parser, LoggerChannelFactoryInterface $logger_factory, Token $token) {
    $this->syslogConfig = $config_factory->get('syslog.settings');
    $this->config = $config_factory->get('event_log_track.settings');
    $this->parser = $parser;
    $this->loggerFactory = $logger_factory;
    $this->token = $token;
  }

  /**
   * Opens a connection to the system logger.
   */
  protected function openConnection(): void {
    if (!$this->connectionOpened) {
      $facility = $this->syslogConfig->get('facility');
      $this->connectionOpened = openlog($this->syslogConfig->get('identity'), LOG_NDELAY, $facility);
    }
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
    $default_format = 'ELT [[event-log:type]] [[event-log:ref_char]] [[event-log:operation]] ON [[event-log:path]] BY [user:[event-log:user:uid]:[event-log:user:name]:[event-log:user:roles:join:,]] [[event-log:description]]';
    $message_format = $this->config->get('syslog.format');
    // Make sure the message format contain the following tokens,
    // - Log type.
    // - User ID.
    // - Log description.
    if (empty($message_format) ||
        !strpos($message_format, '[event-log:type]') ||
        !strpos($message_format, '[event-log:user:uid]') ||
        !strpos($message_format, '[event-log:description]')) {
      // Use the default format instead invalid format.
      $message_format = $default_format;
    }
    try {
      $message = $this->token->replace($message_format, ['event-log' => $entry], [], $bubbleable_metadata);
    }
    catch (\Exception $e) {
      $message = $this->t('Invalid token in syslog format.');
    }

    if ($this->config->get('syslog.output_type') == 'syslog') {
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

    // Ensure we have a connection available.
    $this->openConnection();

    // Populate the message placeholders and then replace them in the message.
    $message_placeholders = $this->parser->parseMessagePlaceholders($message, $context);
    $message = empty($message_placeholders) ? $message : strtr($message, $message_placeholders);

    $this->syslogWrapper($level, $message);
  }

  /**
   * A syslog wrapper to make syslog functionality testable.
   *
   * @param int $level
   *   The syslog priority.
   * @param string $entry
   *   The message to send to syslog function.
   */
  protected function syslogWrapper(int $level, string $entry): void {
    syslog($level, $entry);
  }

}
