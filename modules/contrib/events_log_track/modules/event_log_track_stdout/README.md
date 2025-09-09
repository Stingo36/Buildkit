# Event_log_track_stdout

## Presentation

Integrate ELT with [log_stdout](https://www.drupal.org/project/log_stdout) module.

Provide support to write ELT log on php://stdout or php://stderr for better log handling with [Docker](https://www.docker.com/).

## Installation

- Enable event_log_track_stdout.
- Configure message in stdout settings (admin/config/development/log_stdout).
- Go to event_log_track.settings_form (/admin/config/system/events-log-track).
- Disable logging to DB.
- Recommend uninstalling event_log_track_ui as it's not needed.
