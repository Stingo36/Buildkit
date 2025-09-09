<?php

namespace Drupal\Tests\event_log_track_syslog\Functional;

use Drupal\Tests\event_log_track\Functional\EventsLogTrackTestBase;

/**
 * Class for event_log_track_syslog functional browser tests.
 */
class EventsLogTrackSyslogTest extends EventsLogTrackTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'dblog',
    'event_log_track',
    'event_log_track_auth',
    'event_log_track_config',
    'event_log_track_syslog',
    'event_log_track_ui',
    'syslog',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Create users with specific permissions.
    $this->adminUser = $this->drupalCreateUser([
      'administer site configuration',
      'access event log track',
      'access site reports',
    ]);
  }

  /**
   * Tests logging cli actions.
   */
  public function testSyslog() {
    // Log in the admin user.
    $this->drupalLogin($this->adminUser);

    // Try to remove the message format.
    $this->drupalGet('admin/config/development/logging');
    $this->submitForm(['event_log_track_format' => ''], 'Save configuration');
    $this->assertSession()->pageTextContains('Events Log Track format field is required.');
    // Set an invalid format.
    $this->submitForm(['event_log_track_format' => 'invalid format.'], 'Save configuration');

    // Verify the config action is logged.
    $this->drupalGet('admin/reports/dblog');
    $this->assertSession()->responseContains('ELT [config] [event_log_track.settings] [save] ON [admin/config/development/logging] BY [user:');

    // Set the message format to a empty string via config.
    $this->config('event_log_track.settings')->set('syslog.format', '')->save();
    // Create an 403 error.
    $this->drupalGet('user/1/edit/');
    // Verify the cli action is logged.
    $this->drupalGet('admin/reports/dblog');
    $this->assertSession()->responseContains('ELT [authorization] [] [fail] ON [user/1/edit] BY [user:');
  }

}
