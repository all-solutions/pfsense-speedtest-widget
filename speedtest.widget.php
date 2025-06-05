<?php

/*
 * speedtest.widget.php
 *
 * Copyright (c) 2020 Alon Noy (only works with the not official speedtest cli that is no longer supported and does give wrong results)
 * The original by Alon Noy can be found here: https://github.com/aln-1/pfsense-speedtest-widget
 * Copyright (c) 2024 Leon Straathof (modified version to work with official speedtest cli)
 *
 * Copyright (c) 2025 all-solutions IT-Consulting (forked from Leon Straathof due to non mantained repo)
 *
 * Licensed under the GPL, Version 3.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     https://www.gnu.org/licenses/gpl-3.0.txt
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License and agreed to in writing on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * EXAMPLE JSON DATA FORMAT FROM THE OFFICIAL SPEEDTEST CLI (VERSION 1.2.0.84-1)
 *   {"type":"result",
 *    "timestamp":"2024-01-05T10:09:25Z",
 *    "ping":{"jitter":0.043,"latency":4.752,"low":4.727,"high":4.799},
 *    "download":{"bandwidth":117318968,"bytes":692084632,"elapsed":5914,"latency":{"iqm":21.800,"low":4.676,"high":24.308,"jitter":0.680}},
 *    "upload":{"bandwidth":110904809,"bytes":1097799285,"elapsed":10311,"latency":{"iqm":4.714,"low":3.697,"high":7.739,"jitter":0.416}},
 *    "packetLoss":0,
 *    "isp":"T-Mobile Netherlands",
 *    "interface":{"internalIp":"123.123.123.123","name":"vmx0","macAddr":"0A:1B:2C:3D:4E:5F","isVpn":false,"externalIp":"123.123.123.123"},
 *    "server":{"id":13883,"host":"speedtest.trined.nl","port":8080,"name":"TriNed B.V.","location":"Sint-Oedenrode","country":"Netherlands","ip":"45.145.108.44"},
 *    "result":{"id":"12345678-abcd-1234-abcd-123456789abc","url":"https://www.speedtest.net/result/c/12345678-abcd-1234-abcd-123456789abc","persisted":true}}
 *
 * INSTALL
 * -------
 * Goto https://www.speedtest.net/apps/cli
 * Click FreeBSD and find the URL of the newest version.
 * Diagnostics → Command Prompt → Execute Shell Command:
 *     env ABI=FreeBSD:13:x86:64 pkg add "https://install.speedtest.net/app/cli/ookla-speedtest-1.2.0-freebsd13-x86_64.pkg"
 *     (Use the URL found on the speedtest.net website; the FreeBSD ABI must match.)
 * Diagnostics → Command Prompt → Execute Shell Command:
 *     speedtest --accept-license
 * Diagnostics → Command Prompt → Execute Shell Command:
 *     speedtest --accept-gdpr
 * Diagnostics → Command Prompt → Upload File:
 *     speedtest.widget.php
 * Diagnostics → Command Prompt → Execute Shell Command:
 *     mv -f /tmp/speedtest.widget.php /usr/local/www/widgets/widgets/
 * Status → Dashboard:
 *     Add the speedtest widget.
 *
 * UNINSTALL
 * ----------
 * Diagnostics → Command Prompt → Execute Shell Command:
 *     pkg info | grep speedtest
 * Diagnostics → Command Prompt → Execute Shell Command:
 *     pkg delete -y speedtest-1.2.0.84-1.ea6b6773cf
 *     (use the package name from the first step)
 * Status → Dashboard:
 *     Remove the speedtest widget.
 * Diagnostics → Command Prompt → Execute Shell Command:
 *     rm -f /usr/local/www/widgets/widgets/speedtest.widget.php
 */

require_once("guiconfig.inc");

// If the old single-result file exists, delete it to avoid conflicts.
$legacy_file = '/var/db/speedtest_result.json';
if (file_exists($legacy_file)) {
    @unlink($legacy_file);
}

// Path for saving the history file (JSON array with up to 10 entries).
define('SPEEDTEST_HISTORY_FILE', '/var/db/speedtest_history.json');

// Helper function: Load existing history as a PHP array (or return an empty array).
function load_history() {
    if (!file_exists(SPEEDTEST_HISTORY_FILE)) {
        return [];
    }
    $raw = @file_get_contents(SPEEDTEST_HISTORY_FILE);
    if ($raw === false) {
        return [];
    }
    $arr = json_decode($raw, true);
    if (!is_array($arr)) {
        return [];
    }
    return $arr;
}

// Helper function: Save a PHP array back to the file (in JSON format).
function save_history(array $history) {
    // Limit to a maximum of 10 entries: if more, remove the oldest.
    while (count($history) > 10) {
        array_shift($history);
    }
    @file_put_contents(SPEEDTEST_HISTORY_FILE, json_encode($history));
}

if (is_numeric($_REQUEST['serverid'])) {
    // Compose interface selection switch (if specified by the user).
    $ifaceipswitch = "";
    if ($_REQUEST['iface'] !== "0.0.0.0") {
        $ifaceipswitch = " --ip=" . $_REQUEST['iface'];
    }

    if ($_REQUEST['serverid'] == 0) {
        // AUTOSELECT
        $results = shell_exec("speedtest -f json --selection-details --accept-license --accept-gdpr" . $ifaceipswitch);
    } else {
        // MANUAL SERVER SELECTION
        $serverlist = shell_exec("speedtest -f json --servers --accept-license --accept-gdpr" . $ifaceipswitch);
        $results    = shell_exec("speedtest -f json --server-id=" . $_REQUEST['serverid'] . $ifaceipswitch . " --selection-details --accept-license --accept-gdpr");
        $resultsobj = json_decode($results, true);
        $serverlistobj = json_decode($serverlist, true);

        foreach ($serverlistobj['servers'] as &$server) {
            $latency = shell_exec("ping -c 1 -W 1 " . $server['host'] . " 2>&1 | awk -F'/' 'END{ print (/^round-trip/? $5:\"99999\") }'");
            $server  = (object) ['latency' => (float)$latency, 'server' => $server];
        }
        $resultsobj['serverSelection']['servers'] = $serverlistobj['servers'];
        $results = json_encode($resultsobj);
    }

    if (($results !== null) && (json_decode($results) !== null)) {
        // Add a "retrieved_at" timestamp to each result so history shows when it ran.
        $decoded = json_decode($results, true);
        $decoded['retrieved_at'] = date('c');
        $entry = $decoded;

        // Load existing history, append new entry, and save.
        $history = load_history();
        $history[] = $entry;
        save_history($history);

        // Output the newly produced result (including "retrieved_at").
        echo json_encode($entry);
    } else {
        echo json_encode(null);
    }
    exit;
}

// If the "history" parameter is set, return the last 10 tests as a JSON array.
if (isset($_REQUEST['history'])) {
    $history = load_history();
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($history);
    exit;
}

//
// In normal use (no POST with serverid & no history request):
// Return only the most recent result (last element of history)
// so the dashboard widget works as before.
//
$history = [];
if (file_exists(SPEEDTEST_HISTORY_FILE)) {
    $history = load_history();
    if (count($history) > 0) {
        $results = json_encode(end($history));
    } else {
        $results = null;
    }
} else {
    $results = null;
}
?>
<table class="table">
    <tr>
        <td><h4>Ping <i class="fa fa-exchange"></i></h4></td>
        <td><h4>Download <i class="fa fa-download"></i></h4></td>
        <td><h4>Upload <i class="fa fa-upload"></i></h4></td>
    </tr>
    <tr>
        <td><h4 id="speedtest-ping">N/A</h4></td>
        <td><h4 id="speedtest-download">N/A</h4></td>
        <td><h4 id="speedtest-upload">N/A</h4></td>
    </tr>
    <tr>
        <td>Packet Loss</td>
        <td colspan="2" id="speedtest-packetloss">N/A</td>
    </tr>
    <tr>
        <td>ISP</td>
        <td colspan="2" id="speedtest-isp">N/A</td>
    </tr>
    <tr>
        <td>Interface</td>
        <td colspan="2">
            <select name="speedtest-iface" id="speedtest-iface" style="width: 100%">
                <option value="0.0.0.0">Auto</option>
<?php
                // Build interface list for widget use
                $ifdescrs = get_configured_interface_with_descr();

                // First only WAN interfaces (have a gateway IP)
                $filtered_ifdescrs = [];
                foreach ($ifdescrs as $ifdescr => $ifname) {
                    $ifinfo = get_interface_info($ifdescr);
                    if (!empty($ifinfo['gateway'])) {
                        $filtered_ifdescrs[$ifdescr] = $ifname;
                    }
                }

                // Then additional VPN interfaces (tun/tap/ovpn)
                if (!empty($config['interfaces'])) {
                    foreach ($config['interfaces'] as $ifdescr => $ifcfg) {
                        if (isset($filtered_ifdescrs[$ifdescr])) {
                            continue;
                        }
                        if (preg_match('/^(tun|tap|ovpn)/i', $ifcfg['if'])) {
                            // Use description from get_configured_interface_with_descr or from config
                            $vpn_name = isset($ifdescrs[$ifdescr]) ? $ifdescrs[$ifdescr] : ($ifcfg['descr'] ?: $ifdescr);
                            $filtered_ifdescrs[$ifdescr] = $vpn_name;
                        }
                    }
                }

                // Output the filtered interfaces
                foreach ($filtered_ifdescrs as $ifdescr => $ifname) {
                    $ifinfo = get_interface_info($ifdescr);
                    echo '<option value="' . htmlspecialchars($ifinfo['ipaddr']) . '">'
                       . htmlspecialchars($ifname) . ' (' . htmlspecialchars($ifinfo['ipaddr']) . ')</option>';
                }
?>
            </select>
        </td>
    </tr>
    <tr>
        <td>Host</td>
        <td colspan="2">
            <select name="speedtest-host" id="speedtest-host" style="width: 100%">
                <option value="0">AUTOSELECT AND REFRESH CLOSEST SERVER LIST</option>
            </select>
        </td>
    </tr>
    <tr>
        <td colspan="3" id="speedtest-ts" style="font-size: 0.8em;">&nbsp;</td>
    </tr>
</table>

<?php
// Build a map from device name to interface description
$ifdescrs = get_configured_interface_with_descr();
$deviceToDescr = [];
foreach ($ifdescrs as $ifdescr => $descr) {
    $info = get_interface_info($ifdescr);
    if (!empty($info['if'])) {
        $deviceToDescr[$info['if']] = $descr;
    }
}

// Display collapsible history below the main widget if available
if (!empty($history)) {
    echo '<h5><span id="toggleHistory" style="cursor:pointer;"><i class="fa fa-chevron-right"></i></span> Recent Speedtest Results</h5>';
    echo '<div id="historyContent" style="display:none;">';
    echo '<table class="table table-condensed">';
    echo '<tr><th>Date<br>Time</th><th>Interface</th><th>Ping (ms)</th><th>Download (Mbps)</th><th>Upload (Mbps)</th><th></th></tr>';
    // Iterate in reverse so newest entries appear first
    foreach (array_reverse($history) as $entry) {
        // Extract date, time, and full offset directly from the ISO8601 string
        // Format is "YYYY-MM-DDTHH:MM:SS±HH:MM", e.g. "2025-06-05T14:17:51+02:00"
        $raw = $entry['retrieved_at'];
        $dateIso = substr($raw, 0, 10);           // "YYYY-MM-DD"
        $timeIso = substr($raw, 11, 8);           // "HH:MM:SS"
        $offsetRaw = substr($raw, 19, 6);         // "+02:00", "-04:00", "+03:30", "+12:45", etc.

        // Convert date to "DD.MM.YYYY"
        $datePart = date('d.m.Y', strtotime($dateIso));
        $timePart = $timeIso;

        // Build the GMT offset label using the full offset string
        $tzLabel = "(GMT{$offsetRaw})";

        // Use <div> tags for separate lines, with smaller font-size
        $dtDisplay = "<div style=\"font-size:0.8em;\">{$datePart}</div>"
                   . "<div style=\"font-size:0.8em;\">{$timePart} {$tzLabel}</div>";

        // Determine the user-friendly interface description
        $deviceName = isset($entry['interface']['name']) ? $entry['interface']['name'] : '';
        $ifaceLabel = isset($deviceToDescr[$deviceName])
            ? htmlspecialchars($deviceToDescr[$deviceName])
            : htmlspecialchars($deviceName);

        $ping = isset($entry['ping']['latency'])
            ? number_format($entry['ping']['latency'], 2)
            : 'N/A';
        $down = isset($entry['download']['bandwidth'])
            ? number_format($entry['download']['bandwidth'] / 1000000 * 8, 2)
            : 'N/A';
        $up   = isset($entry['upload']['bandwidth'])
            ? number_format($entry['upload']['bandwidth'] / 1000000 * 8, 2)
            : 'N/A';
        $url  = isset($entry['result']['url'])
            ? htmlspecialchars($entry['result']['url'])
            : '';
        echo "<tr>\n";
        echo "<td style=\"white-space: normal;\">{$dtDisplay}</td>";
        echo "<td>{$ifaceLabel}</td>";
        echo "<td>{$ping}</td>";
        echo "<td>{$down}</td>";
        echo "<td>{$up}</td>";
        if ($url) {
            echo "<td><a href=\"{$url}\" target=\"_blank\"><i class=\"fa fa-external-link\"></i></a></td>";
        } else {
            echo "<td></td>";
        }
        echo "</tr>\n";
    }
    echo '</table>';
    echo '</div>';
}
?>

<a id="updspeed" href="#" class="fa fa-refresh" style="display: none;"></a>
<a id="Ookla" href="#" target="_blank" style="display: none;"> <i class="fa fa-external-link"></i></a>
<script type="text/javascript">
function update_result(results) {
    if (results != null) {
        var date = new Date(results.timestamp);
        $("#speedtest-ts").html(date);
        $("#speedtest-ping").html(results.ping === undefined
            ? "Undefined"
            : results.ping.latency.toFixed(2) + "<small> ms</small>");
        $("#speedtest-download").html(results.download === undefined
            ? "Undefined"
            : (results.download.bandwidth / 1000000 * 8).toFixed(2) + "<small> Mbps</small><h5>(" + results.download.latency.iqm + "<small> ms</small>)</h5>");
        $("#speedtest-upload").html(results.upload === undefined
            ? "Undefined"
            : (results.upload.bandwidth / 1000000 * 8).toFixed(2) + "<small> Mbps</small><h5>(" + results.upload.latency.iqm + "<small> ms</small>)</h5>");
        $("#speedtest-packetloss").html(results.packetLoss === undefined
            ? "Undefined"
            : results.packetLoss);
        $("#speedtest-isp").html(results.isp === undefined
            ? "Undefined"
            : results.isp + "<small> (" + results.interface.externalIp + ")</small>");

        if (results.server === undefined) {
            $('#speedtest-host').empty().append($('<option>', {
                value: 0,
                text: 'AUTOSELECT AND REFRESH CLOSEST SERVER LIST'
            }));
        } else {
            $('#speedtest-host').empty()
                .append($('<option>', {
                    value: results.server.id,
                    text: results.server.name + " " + results.server.location + " " + results.server.country + " id=" + results.server.id + " (" + results.ping.latency.toFixed(2) + "ms)"
                }))
                .val(results.server.id)
                .append($('<option>', {
                    value: 0,
                    text: 'AUTOSELECT AND REFRESH CLOSEST SERVER LIST'
                }));
            var servers = results.serverSelection.servers;
            servers.sort(function(a, b) {
                if (typeof a.latency === "undefined" || typeof b.latency === "undefined") return 0;
                return a.latency - b.latency;
            });
            $.each(servers, function(i, server) {
                if (results.server.id !== server.server.id) {
                    var latency = server.latency === 99999
                        ? "Request timed out"
                        : server.latency.toFixed(2) + "ms";
                    $('#speedtest-host').append($('<option>', {
                        value: server.server.id,
                        text: server.server.name + " " + server.server.location + " " + server.server.country + " id=" + server.server.id + " (" + latency + ")"
                    }));
                }
            });
        }
        $("#Ookla").attr("href", results.result === undefined ? "" : results.result.url).show();
    } else {
        $("#speedtest-ts").html("Speedtest failed");
        $("#speedtest-ping, #speedtest-download, #speedtest-upload, #speedtest-packetloss, #speedtest-isp")
            .html("N/A");
        $('#speedtest-host').empty().append($('<option>', {
            value: 0,
            text: 'AUTOSELECT AND REFRESH CLOSEST SERVER LIST'
        }));
    }
}

function update_speedtest() {
    $('#updspeed').off("click").blur().addClass("fa-spin").click(function() {
        $('#updspeed').blur();
        return false;
    });
    $.ajax({
        type: 'POST',
        url: "/widgets/widgets/speedtest.widget.php",
        dataType: 'json',
        data: {
            serverid: $("#speedtest-host").val(),
            iface:    $("#speedtest-iface").val()
        },
        success: function(data) {
            update_result(data);
            // After a successful test, reload so history is updated
            location.reload();
        },
        error: function() {
            update_result(null);
        },
        complete: function() {
            $('#updspeed').off("click").removeClass("fa-spin").click(function() {
                update_speedtest();
                return false;
            });
        }
    });
}

events.push(function() {
    // Toggle behavior for the collapsible history
    $('#toggleHistory').click(function() {
        var icon = $(this).find('i');
        $('#historyContent').slideToggle('fast');
        icon.toggleClass('fa-chevron-right fa-chevron-down');
    });

    var target = $("#updspeed").closest(".panel").find(".widget-heading-icon");
    $("#Ookla").prependTo(target);
    $("#updspeed").prependTo(target).show().click(function() {
        update_speedtest();
        return false;
    });
    update_result(<?php echo ($results === null ? "null" : $results); ?>);
});
</script>
