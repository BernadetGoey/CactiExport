<?php

// autoload dependencies
require dirname(__FILE__) . '/../vendor/autoload.php';

/**
 * Create the local export table
 */
function cactiexport_setup_table_new()
{
    global $config;
    include_once($config["library_path"] . "/database.php");

    $data = array();
    $data['columns'][] = [
        'name' => 'timestamp',
        'type' => 'varchar(1024)',
        'NULL' => FALSE,
        'default' => '0'
    ];
    $data['columns'][] = [
        'name' => 'local_data_id',
        'type' => 'int(11)',
        'NULL' => FALSE,
        'default' => '0'
    ];
    $data['columns'][] = [
        'name' => 'key',
        'type' => 'varchar(1024)',
        'NULL' => FALSE,
        'default' => '0'
    ];
    $data['columns'][] = [
        'name' => 'value',
        'type' => 'varchar(1024)',
        'NULL' => FALSE,
        'default' => '0'
    ];
    $data['keys'][] = [
        'name' => 'local_data_id',
        'columns' => 'local_data_id'
    ];
    $data['keys'][] = [
        'name' => 'key',
        'columns' => 'key'
    ];
    $data['type'] = 'Memory';
    $data['comment'] = 'Cactiexport Data';
    api_plugin_db_table_create('cactiexport', 'plugin_cactiexport_data', $data);
}

/**
 * Function automatically called after polling
 * Inserts the polling data into the export table to be sent to external database
 * @param string[] $rrd_update_array
 * @return string[]
 */
function cactiexport_poller_output($rrd_update_array)
{
    foreach ($rrd_update_array as $item) {
        if (is_array($item)) {
            if (array_key_exists('times', $item)) {
                if (array_key_exists(key($item['times']), $item['times'])) {
                    $array = $item['times'][key($item['times'])];
                    foreach ($array as $key => $val) {
                        if (strlen($key) > 0) {
                            db_execute("
                                INSERT INTO `plugin_cactiexport_data` (
                                    `timestamp`, 
                                    `local_data_id`, 
                                    `key`, 
                                    `value`
                                ) VALUES (
                                    '" . key($item['times']) . "',
                                    " . $item['local_data_id'] . ",
                                    '" . $key . "','" . $val . "') 
                            ");
                        }
                    }
                }
            }
        }
    }
    return $rrd_update_array;
}

/**
 * Clear old polling data before each run, we don't want to export duplicate data
 */
function cactiexport_poller_top()
{
    db_execute("DELETE FROM `plugin_cactiexport_data`");
}

/**
 * Automatically called after polling, gathers data from our export table and sends it to external database
 */
function cactiexport_poller_bottom()
{
    $ds_lookup_query = <<<EOT
    SELECT
     ds.id
     ,host.hostname
     ,host.description
     ,data.name_cache
     ,data.data_template_id
     ,(CASE WHEN rrd.data_source_type_id=1 THEN 'gauge' WHEN rrd.data_source_type_id=2 THEN 'counter' WHEN rrd.data_source_type_id=3 THEN 'counter' WHEN rrd.data_source_type_id=4 THEN 'counter' END) AS rate
     ,data_template.name AS metric
     ,host_template.name AS host_type
     ,ds.host_id
     ,ds.snmp_query_id
     ,ds.snmp_index
    FROM data_template_data data
    INNER JOIN data_local ds ON ds.id=data.local_data_id
    INNER JOIN host ON host.id=ds.host_id
    INNER JOIN host_template ON host_template.id=(CASE host.host_template_id WHEN 0 THEN (SELECT id FROM host_template ORDER BY id LIMIT 1) ELSE host.host_template_id END)
    INNER JOIN data_template ON data_template.id=data.data_template_id 
    INNER JOIN data_template_rrd rrd ON rrd.local_data_id=data.local_data_id
    WHERE 
     data.local_data_template_data_id <> 0
     AND host.disabled <> 'on'
    GROUP BY ds.id
EOT;
    $ds_lookup = db_fetch_assoc($ds_lookup_query);

    $snmp = [];
    $snmp_query = <<<EOT
    SELECT field_name, field_value, host_id, snmp_query_id, snmp_index
    FROM host_snmp_cache
EOT;
    $snmp_values = db_fetch_assoc($snmp_query);
    foreach ($snmp_values as $snmp_value) {
        $snmp[$snmp_value['host_id']][$snmp_value['snmp_query_id']][$snmp_value['snmp_index']][$snmp_value['field_name']] = $snmp_value['field_value'];
    }

    $ds_info = array();
    foreach ($ds_lookup as $ds) {
        $ds_id = $ds['id'];
        $ds_info[$ds_id]['extra_fields']['cacti_data_id'] = (float)$ds['id'];
        $ds_info[$ds_id]['collector'] = 'cacti';
        $ds_info[$ds_id]['hostname'] = $ds['hostname'];
        $ds_info[$ds_id]['data_template_id'] = $ds['data_template_id'];
        $ds_info[$ds_id]['description'] = $ds['description'];
        $host = cactiexport_host($ds["hostname"], $ds["description"]);
        $ds_info[$ds_id]['host'] = strlen($host) > 0 ? $host : $ds['hostname'];
        $ds_info[$ds_id]['host_type'] = $ds['host_type'];
        $ds_info[$ds_id]['metric'] = cactiexport_templateToMetric($ds['metric']);
        $ds_info[$ds_id]['metric_text'] = $ds['metric'];
        $ds_info[$ds_id]['rate'] = $ds['rate'];
        $ds_info[$ds_id]['namecache'] = $ds['name_cache'];
        if ($ds['snmp_query_id'] != 0
            && isset($snmp[$ds['host_id']][$ds['snmp_query_id']])
            && isset($snmp[$ds['host_id']][$ds['snmp_query_id']][$ds['snmp_index']])) {
            foreach ($snmp[$ds['host_id']][$ds['snmp_query_id']][$ds['snmp_index']] as $key => $value) {
                if (is_numeric($value)) {
                    $value = (float)$value;
                    $ds_info[$ds_id]['extra_fields'][$key] = $value;
                } else {
                    $ds_info[$ds_id][$key] = $value;
                }
            }
        }
        $ds_info[$ds_id] = array_filter($ds_info[$ds_id]);
    }

    // Then we gather which of the local data ids are part of the last polling action
    $polling_data = db_fetch_assoc("
        SELECT 
            `timestamp`, 
            `local_data_id`, 
            `key`, 
            `value` 
        FROM plugin_cactiexport_data 
        ORDER BY `timestamp`,`local_data_id`,`key`
    ");

    // clear the data so it won't be exported more than once
    db_execute("DELETE FROM `plugin_cactiexport_data`");

    $old_hostname = '';
    $data_array = array();
    $timestamp = '';
    $host_name = '';

    $count = 0;

    // We add the polling data to an array to send to InfluxDB
    foreach ($polling_data as $item) {
        if (
            $item['timestamp'] > 0
            && isset($ds_info[$item['local_data_id']])
            && is_numeric($item['value'])
        ) {
            $templates = explode(',',read_config_option('cactiexport_data_templates_drop'));
            $id = $item['local_data_id'];
            if(empty($templates) || in_array($ds_info[$id]['data_template_id'], $templates)) {
                $key = $item['key'];
                $val = (float)$item['value'];
                $timestamp = (int)$item['timestamp'];
                $host_name = $ds_info[$id]['hostname'];
                // send data for each host separately
                if ($old_hostname <> $host_name) {
                    if (strlen($old_hostname) > 0) {
                        // send data to external database
                        $count = cactiexport_send_data_influx_db($data_array, $count);
                    }
                    $old_hostname = $host_name;
                    // reset data array
                    $data_array = array();
                }
                $point['metric'] = $ds_info[$id]['metric'];
                $point['timestamp'] = $timestamp;
                $point['value'] = $val;
                $point['tags'] = $ds_info[$id];
                $point['tags']['type'] = $key;
                if (isset($ds_info[$id]['extra_fields'])) {
                    $point['extra_fields'] = $ds_info[$id]['extra_fields'];
                }
                $point['extra_fields']['value'] = $val;

                unset($point['tags']['metric']); // delete unwanted data
                unset($point['tags']['id']); // delete unwanted data
                unset($point['tags']['extra_fields']); // delete unwanted data

                $data_array[] = $point;
            }
        }
    }

    // send data to external database. Only InfluxDB for now but could be expanded for other dbs.
    $count = cactiexport_send_data_influx_db($data_array, $count);

    cacti_log("Finished adding [" . $count . "] data points.", TRUE, "CACTI EXPORT:");
}

/**
 * Insert data into InfluxDB
 *
 * @param string[] $data_array
 */
function cactiexport_send_data_influx_db($data_array, $count)
{
    // vagrant local development: influxdb://export:export@localhost:8086/exportdb
    $db_username = read_config_option('cactiexport_influxdb_username');
    $db_password = read_config_option('cactiexport_influxdb_password');
    $db_host = read_config_option('cactiexport_influxdb_host');
    $db_port = read_config_option('cactiexport_influxdb_port');
    $db_database = read_config_option('cactiexport_influxdb_database');
    $db_url = 'influxdb://' . $db_username . ':' . $db_password . '@' . $db_host . ':' . $db_port . '/' . $db_database;

    try {
        $database = InfluxDB\Client::fromDSN($db_url);
    } catch (Exception $e) {
        cacti_log("ERROR: " . $e->getMessage(), TRUE, "CACTI EXPORT:");
        return;
    }

    $points = array();
    foreach ($data_array as $point) {
        if (strlen($point['metric']) > 0) {
            try {
                if (array_key_exists('metric_text', $point['tags'])) {
                    $point['tags']['metric_text'] = $point['tags']['metric_text'];
                }
                $points[] = new InfluxDB\Point(
                    $point['metric'],
                    (float)$point['value'],
                    $point['tags'],
                    $point['extra_fields'],
                    $point['timestamp']);
            } catch (Exception $e) {
                cacti_log("ERROR: " . $e->getMessage(), TRUE, "CACTI EXPORT:");
            }
        }
    }

    if (count($points) > 0) {
        $newPoints = '';
        try {
            $newPoints = $database->writePoints($points, InfluxDB\Database::PRECISION_SECONDS);
        } catch (Exception $e) {
            cacti_log("ERROR: " . $e->getMessage() . ' ' . $newPoints, TRUE, "CACTI EXPORT:");
        }

        $count += count($points);
        return $count;

    }
}

function cactiexport_host($hostname, $description)
{
    if (filter_var($hostname, FILTER_VALIDATE_IP) && strpos($description, $hostname) !== FALSE) {
        return preg_replace('/\s*' . preg_quote($hostname, '/') . '\s*/', '', $description);
    }
    return $hostname;
}

function cactiexport_templateToMetric($value)
{
    $value = trim($value);
    $value = preg_replace("/[^A-Za-z0-9_\.]+/", '.', $value);
    $value = rtrim($value, '.');
    $value = strtolower($value);
    return $value;
}