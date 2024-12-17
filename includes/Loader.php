<?php
namespace MyWPReact\Core;

use MyWPReact\Admin\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Loader {
    private $admin;

    public function __construct() {
        // error_log('Loader constructed');
    }

    public function init() {
        // error_log('Loader init started');
        $this->admin = new Admin();
        $this->admin->init();
        // error_log('Loader init completed');
    }
}