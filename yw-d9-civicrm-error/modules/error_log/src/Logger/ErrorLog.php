<?php

namespace Drupal\error_log\Logger;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Logger\RfcLoggerTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Logs events to the PHP error log.
 */
class ErrorLog implements LoggerInterface {

  use DependencySerializationTrait;
  use RfcLoggerTrait;

  /**
   * Provides untranslated log levels.
   */
  const LOG_LEVELS = [
    RfcLogLevel::EMERGENCY => LogLevel::EMERGENCY,
    RfcLogLevel::ALERT => LogLevel::ALERT,
    RfcLogLevel::CRITICAL => LogLevel::CRITICAL,
    RfcLogLevel::ERROR => LogLevel::ERROR,
    RfcLogLevel::WARNING => LogLevel::WARNING,
    RfcLogLevel::NOTICE => LogLevel::NOTICE,
    RfcLogLevel::INFO => LogLevel::INFO,
    RfcLogLevel::DEBUG => LogLevel::DEBUG,
  ];

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The message's placeholders parser.
   *
   * @var \Drupal\Core\Logger\LogMessageParserInterface
   */
  protected $parser;

  /**
   * Constructs an Error Log object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory object.
   * @param \Drupal\Core\Logger\LogMessageParserInterface $parser
   *   The parser to use when extracting message variables.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LogMessageParserInterface $parser) {
    $this->configFactory = $config_factory;
    $this->parser = $parser;
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []): void {
    $config = $this->configFactory->get('error_log.settings');
    if (empty($config->get('log_levels')["level_$level"])) {
      return;
    }
    if (in_array($context['channel'], $config->get('ignored_channels') ?: [])) {
      return;
    }
    // Drush handles error logging for us, so disable redundant logging.
    if (function_exists('drush_main') && !ini_get('error_log')) {
      return;
    }
    $level = static::LOG_LEVELS[$level];
    $message_placeholders = $this->parser->parseMessagePlaceholders($message, $context);
    $message = empty($message_placeholders) ? $message : strtr($message, $message_placeholders);
    $message = "[$level] [{$context['channel']}] [{$context['ip']}] [uid:{$context['uid']}] [{$context['request_uri']}] [{$context['referer']}] $message";
    error_log($message);
  }

}
