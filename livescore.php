<?php
/*
Plugin Name: Kuvilam IPL Livescore
Plugin URI: http://wordpress.org/plugins/kuvilam-ipl-livescore/
Description: A plugin to show the live IPL cricket score in your wordpress blog using shortcode and widget.
Author:Kuvilam Technologies
Version: 1.0
Author URI: https://www.kuvilam.com/
*/

if (!defined('ABSPATH')) {
    exit;
}

define('KWILS_MIN_REFRESH', 5);
define('KWILS_DEFAULT_REFRESH', 15);
class Kuvilam_WP_KWILS_Settings
{

    public function __construct()
    {

        add_action('admin_menu', array($this, 'add_settings_page'));

        add_action('admin_init', array($this, 'register_settings'));

    }

    public function add_settings_page()
    {

        add_submenu_page(

            'options-general.php',

            'Kuvilam IPL Livescore',

            'Kuvilam IPL Livescore',

            'manage_options',

            'kuvilam_ipl_livescore',

            array($this, 'slils_settings_page_content')

        );

    }

    public function slils_settings_page_content()
    {

        ?>

        <div class="wrap">

        <h2>Livescore Settings</h2>



        <form method="post" action="options.php">

        <?php

        settings_fields('settings-group');

        do_settings_sections('kwils-livescore');

        ?>

        <?php submit_button('Save Changes');?>

        </form>

    </div>

        <?php

    }

    public function kwils_config_fields()
    {

        return array(

            'tab1_text' => array('type' => 'text', 'label' => 'Tab1 Text', 'default' => 'Live'),

            'tab2_text' => array('type' => 'text', 'label' => 'Tab2 Text', 'default' => 'Upcoming'),

            'tab3_text' => array('type' => 'text', 'label' => 'Tab3 Text', 'default' => 'Past'),

            'refresh_interval' => array('type' => 'number', 'min' => KWILS_MIN_REFRESH, 'label' => 'Score Refresh Interval(in Secs)', 'default' => KWILS_DEFAULT_REFRESH),

            'theme_color' => array('type' => 'color', 'label' => 'Theme Color', 'default' => '#005177'),

            'text_color' => array('type' => 'color', 'label' => 'Text Color', 'default' => '#FFFFFF'),

        );

    }

    public function register_settings()
    {

        add_settings_section(

            'main-settings-section',

            'Score Widget Settings',

            array($this, 'print_kwils_plugin_section_info'),

            'kwils-livescore'

        );

        $all_fields = $this->kwils_config_fields();

        foreach ($all_fields as $field_key => $field) {

            add_settings_field(

                $field_key,

                $field['label'],

                array($this, 'create_input_some_setting'),

                'kwils-livescore',

                'main-settings-section',

                array($field_key)

            );

        }

        register_setting('settings-group', 'kwils_score_config', array($this, 'kwils_config_validate'));

    }

    public function print_kwils_plugin_section_info()
    {

        echo '<p>You can use the shortcode [live_ipl_score] in any of your post/page or in widget to show the IPL cricket live score.</p>';

    }

    public function create_input_some_setting($args)
    {

        $field_key = $args[0];

        $all_fields = $this->kwils_config_fields();

        $options = get_option('kwils_score_config');
        if (!$options) {
            $options = array();
        }

        $value = array_key_exists($field_key, $options) ? $options[$field_key] : $all_fields[$field_key]['default'];
        $min = isset($all_fields[$field_key]['min']) ? ' min="' . $all_fields[$field_key]['min'] . '" ' : '';
        echo '<input type="' . $all_fields[$field_key]['type'] . '" name="kwils_score_config[' . $field_key . ']" value="' . $value . '" ' . $min . '/>';

    }

    public function kwils_config_validate($arr_input)
    {

        $options = get_option('kwils_score_config');

        foreach ($arr_input as $key => $value) {

            $options[$key] = trim($value);

        }

        return $options;

    }

}

new Kuvilam_WP_KWILS_Settings();

class Kuvilam_WP_KWILS_Widget
{

    public function __construct()
    {

        add_action('init', array($this, 'kwils_shortcodes_init'));

        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));

        add_action('wp_footer', array($this, 'kwils_footer'));

        $this->widgetCounter = 0;

    }

    public function enqueue_scripts()
    {

        wp_register_script('kwils_custom', plugin_dir_url(__FILE__) . 'js/kwils.js', array('jquery'), '1.0', true);

        wp_enqueue_script('kwils_vendor_script', plugin_dir_url(__FILE__) . 'vendor/jquery.responsiveTabs.min.js', array('jquery'), '1.0', true);

        wp_enqueue_script('kwils_custom');

    }

    public function enqueue_styles()
    {

        wp_enqueue_style('kwils_vendor_theme', plugin_dir_url(__FILE__) . 'vendor/responsive-tabs.css');

    }

    public function kwils_shortcodes_init()
    {

        add_shortcode('live_ipl_score', array($this, 'kwils_handle_shortcode'));

    }

    public function kwils_handle_shortcode($atts, $content = '', $tag)
    {
        
        return $this->kwils_rendered_score_card();
    }

    public function kwils_get_ssr_json()
    {
        $contents = false;
        $api_url = 'http://pub.wpdemo.biz/ils.json';
        $wp_response = wp_remote_get($api_url);
        if($wp_response){
            $contents = wp_remote_retrieve_body($wp_response);
        }
        if ($contents) {
            $json = json_decode($contents, 1);
            if ($json) {
                return $json;
            }
        }
        return false;
    }

    public function kwils_rendered_score_card()
    {

        $options = get_option('kwils_score_config');
        if (!$options) {
            $options = array();
        }
        $tab1_title = array_key_exists('tab1_text', $options) ? $options['tab1_text'] : 'Live';

        $tab2_title = array_key_exists('tab2_text', $options) ? $options['tab2_text'] : 'Upcoming';

        $tab3_title = array_key_exists('tab3_text', $options) ? $options['tab3_text'] : 'Past';

        $str_html = '';

        $json = $this->kwils_get_ssr_json();
        if ($json) {
            $this->widgetCounter++;
            $str_html .= '<div id="kwils_tabs">';
            $str_html .= '<ul>';
            $str_html .= '<li><a href="#kwils_tabs-1">' . $tab1_title . '</a></li>';
            $str_html .= '<li><a href="#kwils_tabs-2">' . $tab2_title . '</a></li>';
            $str_html .= '<li><a href="#kwils_tabs-3">' . $tab3_title . '</a></li>';
            $str_html .= '</ul>';

            //tab1
            $str_html .= '<div id="kwils_tabs-1">';
            $str_html .= '<table class="wp-block-table is-style-stripes" id="kwils_live_score">';
            $str_html .= '<tbody>';
            $str_html .= '<tr><th style="text-align: center;" colspan="2">' . $json['l']['t'] . '</th></tr>';

            //innings
            foreach ($json['l']['i'] as $inning) {
                $str_html .= '<tr class="team-innings"><td>' . $inning['t'] . '</td><td>' . $inning['s'] . '</td></tr>';
            }

            //mini scorecard
            $str_html .= '<tr><td style="text-align: center;" colspan="2">Summary</td></tr>';
            foreach ($json['l']['b'] as $bruns) {
                $str_html .= '<tr class="bat-summary"><td>' . $bruns['n'] . '</td><td>' . $bruns['s'] . '</td></tr>';
            }

            //match meta
            $str_html .= '<tr><td style="text-align: center;" colspan="2"><a href="https://www.iplcricketmatch.com/today-ipl-match-live-score/" target="_blank" title="View Full Scorecard"><b>Full Scorecard</b></a></td></tr>';

            $str_html .= '<tr><td>Venue: ' . $json['l']['v'] . '</td><td>Date &amp; time : ' . $json['l']['d'] . '</td></tr>';

            $str_html .= '<tr><td style="text-align: center;" colspan="2">Toss: ' . $json['l']['ts'] . '</td></tr>';

            $str_html .= '</tbody></table></div>';

            //tab2
            $str_html .= '<div id="kwils_tabs-2">';
            $str_html .= '<table class="wp-block-table is-style-stripes"><tbody>';
            $str_html .= '<tr><th>Match</th><th>Date &amp; Time</th><th>Place</th></tr>';
            foreach ($json['f'] as $upcoming) {
                $str_html .= '<tr><td>' . $upcoming['m'] . '</td><td>' . $upcoming['d'] . '</td><td>' . $upcoming['c'] . '</td></tr>';
            }

            $str_html .= '</tbody></table></div>';

            //tab3
            $str_html .= '<div id="kwils_tabs-3">';
            $str_html .= '<table class="wp-block-table is-style-stripes"><tbody>';
            $str_html .= '<tr><th>Match</th><th>Date &amp; Time</th><th>Result</th></tr>';

            foreach ($json['p'] as $past) {
                $str_html .= '<tr><td>' . $past['m'] . '</td><td>' . $past['d'] . '</td><td>' . $past['r'] . '</td></tr>';
            }

            $str_html .= '</tbody></table></div></div>';

        } else {
            if (current_user_can('editor') || current_user_can('administrator')) {
                $str_html = 'Failed to connect with IPL Livescore API, check your network connection...';
            }
        }
        return $str_html;

    }

    public function get_kwils_inline_css()
    {

        $options = get_option('kwils_score_config');

        $theme_color = array_key_exists('theme_color', $options) ? $options['theme_color'] : '#005177';

        $font_color = array_key_exists('text_color', $options) ? $options['text_color'] : 'white';

        $css_str = '

        <style>

        .r-tabs {

            position: relative;

            background-color: ' . $theme_color . ';

            border-top: 1px solid ' . $theme_color . ';

            border-right: 1px solid ' . $theme_color . ';

            border-left: 1px solid ' . $theme_color . ';

            border-bottom: 4px solid ' . $theme_color . ';

            border-radius: 4px;

        }

        .r-tabs .r-tabs-nav .r-tabs-tab {

            position: relative;

            background-color: ' . $theme_color . ';

        }

        .r-tabs .r-tabs-nav .r-tabs-anchor {

            display: inline-block;

            padding: 10px 12px;

            text-decoration: none;

            text-shadow: 0 1px rgba(0, 0, 0, 0.4);

            font-size: 14px;

            font-weight: bold;

            color: #fff;

        }

        .r-tabs .r-tabs-nav .r-tabs-state-active .r-tabs-anchor {

            color: ' . $theme_color . ';

            text-shadow: none;

            background-color: ' . $font_color . ';

            border-top-right-radius: 4px;

            border-top-left-radius: 4px;

        }

        .r-tabs .r-tabs-panel {

            background-color: ' . $font_color . ';

            border-bottom: 4px solid ' . $font_color . ';

            border-bottom-right-radius: 4px;

            border-bottom-left-radius: 4px;

        }

        .r-tabs .r-tabs-accordion-title .r-tabs-anchor {

            display: block;

            padding: 10px;



            background-color: ' . $theme_color . ';

            color: ' . $font_color . ';

            font-weight: bold;

            text-decoration: none;

            text-shadow: 0 1px rgba(0, 0, 0, 0.4);

            font-size: 14px;



            border-top-right-radius: 4px;

            border-top-left-radius: 4px;

        }

        .r-tabs .r-tabs-accordion-title.r-tabs-state-active .r-tabs-anchor {

            background-color: ' . $font_color . ';

            color: ' . $theme_color . ';

            text-shadow: none;

        }

        </style>

        ';

        return $css_str;

    }

    public function kwils_footer()
    {
        $options = get_option('kwils_score_config');
        $interval_sec = $options && $options['refresh_interval'] ? $options['refresh_interval'] : KWILS_DEFAULT_REFRESH;
        if (!is_numeric($interval_sec) || $interval_sec < KWILS_MIN_REFRESH) {
            $interval_sec = KWILS_MIN_REFRESH;
        }
        $interval_sec = $interval_sec * 1000;

        $kwis_obj_js = array(
            'kwils_widget_added' => false,
            'kwils_refresh_interval' => $interval_sec,
            'kwils_api_url' => 'http://pub.wpdemo.biz/ilsmin.json',
        );

        if ($this->widgetCounter > 0) {
            $kwis_obj_js['kwils_widget_added'] = true;
            echo $this->get_kwils_inline_css();
        }

        wp_localize_script('kwils_custom', 'kwils_config_js', $kwis_obj_js);

    }

}

new Kuvilam_WP_KWILS_Widget();
?>