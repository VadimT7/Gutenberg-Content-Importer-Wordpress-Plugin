<?php
/**
 * Basic plugin test
 */

class Test_Plugin_Loading extends WP_UnitTestCase {

    public function test_plugin_is_loaded() {
        $this->assertTrue(class_exists('GCI\Core\Plugin'));
    }

    public function test_plugin_constants_are_defined() {
        $this->assertTrue(defined('GCI_VERSION'));
        $this->assertTrue(defined('GCI_PLUGIN_DIR'));
        $this->assertTrue(defined('GCI_PLUGIN_URL'));
    }

    public function test_progress_tracker_exists() {
        $this->assertTrue(class_exists('GCI\Core\Progress_Tracker'));
    }

    public function test_sse_controller_exists() {
        $this->assertTrue(class_exists('GCI\API\SSE_Controller'));
    }
}

