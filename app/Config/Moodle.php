<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Moodle integration settings. Values are overridden from .env (moodle.*).
 */
class Moodle extends BaseConfig
{
    /** Base URL of the Moodle site, no trailing slash. */
    public string $url = 'https://moodle.gapdbc.org';

    /** Server-side web service token (admin-level user) for reading cohorts, users and grades. */
    public string $wsToken = '';

    /** Web service short name used when exchanging a user's password for a token at login. */
    public string $loginService = 'moodle_mobile_app';

    /** HTTP timeout in seconds for Moodle calls. */
    public int $timeout = 20;
}
