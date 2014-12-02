<?php

/*
  
  LoggerAppenderSentry appender logs to a Sentry instance using a Raven_Client connection object.

  *******************************************
  * INSTANTIATE RAVEN CLIENT FOR THE LOGGER *
  *******************************************
  require('libs/Raven/Autoloader.php');
  Raven_Autoloader::register();
  $client = new Raven_Client('http://992910cccb47434d8f21dcf7e4614a04:666e3a20e763472d834e437c3399c776@sentrybox:9000/666');

  ************************
  * CONFIGURE THE LOGGER *
  ************************
  $logger_config = array(
    'rootLogger' => array(
      'level' => 'DEBUG',
      'appenders' => array('sentry'),
    ),
    'appenders' => array(
      'sentry' => array(
        'class' => 'LoggerAppenderSentry',
        'params' => array(
          'dsn' => $client,
        )
      )
    )
  );

  *************
  * LOG STUFF * 
  *************
  $logger = Logger::getLogger('sentry');

  // Log some messages
  $logger->info('Interesting...');
  $logger->warn('Watch out!');

  // Log a message with exception (provides backtrace)
  $logger->fatal('Terrible, just terrible...', new Exception('O NO'));

  https://github.com/getsentry/sentry
  https://github.com/getsentry/raven-php

 */

class LoggerAppenderSentry extends LoggerAppender {

  /**
   * A Raven_Client instance
   * @see https://github.com/getsentry/raven-php
   */
  protected $dsn;

	public function append(LoggerLoggingEvent $event) {
    
    $dsn = $this->getDsn();

    if (!isset($dsn))
    {
      $this->warn('Raven client is not defined; closing appender.');
      $this->closed = true;
      return;
    }

    $loggerLevel = $this->translateLoggerLevel($event->getLevel());

    // if an exception is passed in as part of the log then send it 
    if ($throwableInfo = $event->getThrowableInformation()) {
      $dsn->captureException($throwableInfo->getThrowable(), array('level' => $loggerLevel));
    // otherwise just send the message
    } else {
      // automatically get stacktrace for these log levels
      if (in_array($loggerLevel, array('warning', 'error', 'fatal'))) {
        $stack = array_splice(debug_backtrace(), 5); // remove log4php stuff from stack
      }
      $dsn->captureMessage($event->getMessage(), null, $loggerLevel, $stack);
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
