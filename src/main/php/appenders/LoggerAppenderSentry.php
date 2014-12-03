<?php

/*
  
  LoggerAppenderSentry appender logs to a Sentry instance using a Raven_Client connection object.

  *******************************************
  * INSTANTIATE RAVEN CLIENT FOR THE LOGGER *
  *******************************************
  require('libs/Raven/Autoloader.php');
  Raven_Autoloader::register();

  ************************
  * CONFIGURE THE LOGGER *
  ************************
  // Using a configuration object
  $logger_config = array(
    'rootLogger' => array(
      'level' => 'DEBUG',
      'appenders' => array('sentry'),
    ),
    'appenders' => array(
      'sentry' => array(
        'class' => 'LoggerAppenderSentry',
        'params' => array(
          'dsn' => 'http://992910cccb47434d8f21dcf7e4614a04:666e3a20e763472d834e437c3399c776@sentrybox:9000/666',
        )
      )
    )
  );

  // Or using an XML configuration file
  <?xml version="1.0" encoding="UTF-8"?>
  <log4php:configuration xmlns:log4php="http://logging.apache.org/log4php/">

      <appender name="SentryAppender" class="LoggerAppenderSentry" threshold="warn">
        <param name="dsn" value="http://992910cccb47434d8f21dcf7e4614a04:666e3a20e763472d834e437c3399c776@sentrybox:9000/666" />
      </appender>
      
      <root>
          <appender_ref ref="SentryAppender" />
      </root>
  </log4php:configuration>

  *************
  * LOG STUFF * 
  *************
  $logger = Logger::getLogger('sentry');

  // Log some messages
  $logger->info('Interesting...');
  $logger->warn('Watch out!');

  // Log a message with exception (provides backtrace)
  $logger->fatal('Terrible, just terrible...', new Exception('O NO'));

  *************
  * MORE INFO *
  *************
  https://github.com/getsentry/sentry
  https://github.com/getsentry/raven-php
 */


class LoggerAppenderSentry extends LoggerAppender {
  /**
   * A Raven_Client instance
   * @see https://github.com/getsentry/raven-php
   *
   * ## Configurable parameters: ##
   *
   * - dsn             - The Data Source Name (DSN) used to connect to sentry.
   */
  protected $dsn;

  protected $client;

  public function __construct($name = '') {
    parent::__construct($name);
    $this->requiresLayout = false;
  }

  /**
   * Setup a sentry connection.
   * Based on defined options, this method runs the client and
   * creates a {@link $collection}. 
   */
  public function activateOptions() {
    $dsn = $this->getDsn();
    if (!isset($dsn))
    {
      $this->warn('DSN is not defined; closing appender.');
      $this->closed = true;
      return;
    }
    // Parse DSN as a test
    try {
      $parsed = Raven_Client::parseDSN($dsn);
    } catch (InvalidArgumentException $ex) {
      $this->warn("There was an error parsing your DSN:\n  " . $ex->getMessage());
      $this->closed = true;
      return;
    }
    $this->warn('Connecting to Raven client with DSN ' . $dsn);
    $this->client = new Raven_Client($dsn);
  }

	public function append(LoggerLoggingEvent $event) {
    $loggerLevel = $this->translateLoggerLevel($event->getLevel());

    // if an exception is passed in as part of the log then send it 
    if ($throwableInfo = $event->getThrowableInformation()) {
      $this->client->captureException($throwableInfo->getThrowable(), array('level' => $loggerLevel));
    // otherwise just send the message
    } else {
      // automatically get stacktrace for these log levels
      if (in_array($loggerLevel, array('warning', 'error', 'fatal'))) {
        $stack = array_splice(debug_backtrace(), 5); // remove log4php stuff from stack
      }
      $this->client->captureMessage($event->getMessage(), null, $loggerLevel, $stack);
    }

    // raven logging handling
    $last_error = $this->client->getLastError();
    if (!empty($last_error)) {
        exit("There was an error sending the test event:\n  " . $last_error);
    }
  }
  private function translateLoggerLevel($level) {
    switch ($level) {
      case 'DEBUG': return 'debug';
      case 'INFO': return 'info';
      case 'WARN': return 'warning';
      case 'ERROR': return 'error';
      case 'FATAL': return 'fatal';
      default: return 'error';
    }
  }
  public function setDsn($dsn) {
    $this->dsn = $dsn;
  }
  public function getDsn() {
    return $this->dsn;
  }
}

