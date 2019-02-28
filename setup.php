<?php

require_once dirname(__FILE__) . "/includes/plugin-hooks.php";

function plugin_cactiexport_install()
{
    api_plugin_register_hook( 'cactiexport', 'poller_top', 'cactiexport_poller_top', 'setup.php' );
    api_plugin_register_hook( 'cactiexport', 'poller_bottom', 'cactiexport_poller_bottom', 'setup.php' );
    api_plugin_register_hook( 'cactiexport', 'poller_output', 'cactiexport_poller_output', 'setup.php' );
    api_plugin_register_hook( 'cactiexport', 'config_settings', 'cactiexport_config_settings', 'setup.php' );

    cactiexport_setup_table_new();
}

function cactiexport_config_settings()
{
    global $tabs, $settings;

    $tabs[ "export" ] = "Cacti Export";

    $temp = array(
        "cactiexport_influxdb_header"             => array(
            "friendly_name" => "InfluxDB User Settings",
            "method"        => "spacer",
        ),
        "cactiexport_influxdb_username"         => array(
            "friendly_name" => "Username",
            "description"   => "InfluxDB Username",
            "method"        => "textbox",
            "default"       => "",
            "max_length"    => 100,
        ),
        "cactiexport_influxdb_password"         => array(
            "friendly_name" => "Password",
            "description"   => "InfluxDB Password",
            "method"        => "textbox_password",
            "default"       => "",
            "max_length"    => 100,
        ),
        "cactiexport_influxdb_db_header"             => array(
            "friendly_name" => "InfluxDB Database Settings",
            "method"        => "spacer",
        ),
        "cactiexport_influxdb_host"         => array(
            "friendly_name" => "Host",
            "description"   => "InfluxDB Host",
            "method"        => "textbox",
            "default"       => "",
            "max_length"    => 100,
        ),
        "cactiexport_influxdb_port"         => array(
            "friendly_name" => "Port",
            "description"   => "InfluxDB Port",
            "method"        => "textbox",
            "default"       => "",
            "max_length"    => 10,
        ),
        "cactiexport_influxdb_database"         => array(
            "friendly_name" => "Database name",
            "description"   => "InfluxDB Database name",
            "method"        => "textbox",
            "default"       => "",
            "max_length"    => 100,
        ),
    );

    if ( isset( $settings[ "export" ] ) ) {
        $settings[ "export" ] = array_merge( $settings[ "export" ], $temp );
    }
    else {
        $settings[ "export" ] = $temp;
    }
}

function plugin_cactiexport_version()
{
    global $config;
    $info = parse_ini_file($config['base_path'] . '/plugins/cactiexport/INFO', true);
    return $info['info'];
}

function plugin_cactiexport_uninstall()
{
    return true;
}

function cactiexport_version()
{
    return plugin_cactiexport_version();
}

function plugin_cactiexport_check_config()
{
    return true;
}

function plugin_cactiexport_upgrade()
{
    return false;
}