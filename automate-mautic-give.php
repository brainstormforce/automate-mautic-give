<?php
/**
 * Plugin Name: AutomatePlug - Mautic for Give
 * Plugin URI: http://www.brainstormforce.com/
 * Description: Integrate Mautic with your Give donation forms. Add donors to Mautic segment when they donate to your Cause.
 * Version: 1.0.1
 * Author: Brainstorm Force
 * Author URI: http://www.brainstormforce.com/
 * Text Domain: automate-mautic-give
 *
 * @package automate-mautic-give
 * @author Brainstorm Force
 */

// exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}
require_once 'classes/class-apmautic-give.php';
