<!DOCTYPE html>
<html>

<head>
    <?php
    require_once 'config.php';
    require_once 'common.php';

    include 'common/menuHead.inc';
    ?>
    <style>
        .gauge-container {
            text-align: center;
            padding: 15px;
        }

        .gauge-svg {
            max-width: 150px;
            height: 150px;
            margin: 0 auto;
        }

        .gauge-label {
            font-size: 0.9em;
            color: #666;
            margin-top: 8px;
        }

        .gauge-value {
            font-size: 1.5em;
            font-weight: bold;
            margin-top: 5px;
        }

        .disk-row {
            margin-bottom: 15px;
        }

        .disk-label {
            font-size: 0.9em;
            margin-bottom: 5px;
            display: flex;
            justify-content: space-between;
        }

        .disk-device {
            color: #666;
            font-weight: 600;
        }

        .disk-info {
            color: #999;
            font-size: 0.85em;
        }

        .uptime-display {
            text-align: center;
            padding: 20px;
        }

        .uptime-counters {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 10px;
        }

        .uptime-segment {
            text-align: center;
        }

        .uptime-number {
            font-size: 2em;
            font-weight: bold;
            color: #007bff;
        }

        .uptime-label {
            font-size: 0.75em;
            color: #666;
            text-transform: uppercase;
        }

        .uptime-started {
            color: #999;
            font-size: 0.85em;
        }

        .load-bar-container {
            margin-bottom: 12px;
        }

        .load-label {
            font-size: 0.9em;
            margin-bottom: 3px;
            display: flex;
            justify-content: space-between;
        }

        .stat-link {
            display: flex;
            justify-content: space-between;
            padding: 8px 12px;
            margin-bottom: 5px;
            border-radius: 4px;
            text-decoration: none;
            color: inherit;
            transition: background-color 0.2s;
        }

        .stat-link:hover {
            background-color: #f8f9fa;
            color: inherit;
        }

        .stat-name {
            color: #666;
        }

        .stat-count {
            font-weight: bold;
            color: #007bff;
        }

        .warning-banner {
            margin-bottom: 20px;
        }

        .file-breakdown {
            margin-top: 15px;
        }

        .file-type-row {
            margin-bottom: 10px;
        }

        .file-type-label {
            font-size: 0.85em;
            margin-bottom: 3px;
            display: flex;
            justify-content: space-between;
        }

        .compact-card {
            margin-bottom: 15px;
        }

        .compact-card .card-body {
            padding: 15px;
        }

        .compact-card .card-header h3 {
            font-size: 1.1em;
            margin: 0;
        }
    </style>
    <script>
        var uptimeInterval;
        var uptimeSeconds = 0;

        function HealthCheckDone() {
            SetButtonState('#btnStartHealthCheck', 'enable');
        }

        function StartHealthCheck() {
            SetButtonState('#btnStartHealthCheck', 'disable');
            $('#healthCheckOutput').html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Running health checks...</div>');

            $.ajax({
                url: "healthCheckHelper.php?output=php&timestamp=" + (Date.parse(Date()) / 1000),
                method: "GET",
                dataType: "HTML"
            }).done(function (html) {
                $("#healthCheckOutput").html(html);
                HealthCheckDone();
            }).fail(function () {
                $("#healthCheckOutput").html('<div class="alert alert-danger">Failed to run health check</div>');
                HealthCheckDone();
            });
        }

        function drawGauge(elementId, value, label, unit, thresholds) {
            var svg = document.getElementById(elementId);
            if (!svg) return;

            var radius = 60;
            var centerX = 75;
            var centerY = 75;
            var startAngle = -Math.PI * 0.75;
            var endAngle = Math.PI * 0.75;
            var totalAngle = endAngle - startAngle;

            // Use custom thresholds or defaults (for percentage-based gauges)
            var yellowThreshold = thresholds ? thresholds.yellow : 60;
            var redThreshold = thresholds ? thresholds.red : 80;

            // Color based on value
            var color = '#28a745'; // green
            if (value > redThreshold) color = '#dc3545'; // red
            else if (value > yellowThreshold) color = '#ffc107'; // yellow

            // Background arc
            var bgPath = describeArc(centerX, centerY, radius, startAngle, endAngle);
            svg.innerHTML = '<path d="' + bgPath + '" fill="none" stroke="#e9ecef" stroke-width="12"/>';

            // For temperature, normalize to 0-100 scale for display
            var displayValue = value;
            var normalizedValue = value;
            if (thresholds && thresholds.max) {
                normalizedValue = Math.min((value / thresholds.max) * 100, 100);
            }

            // Value arc
            var valueAngle = startAngle + (totalAngle * (normalizedValue / 100));
            var valuePath = describeArc(centerX, centerY, radius, startAngle, valueAngle);
            svg.innerHTML += '<path d="' + valuePath + '" fill="none" stroke="' + color + '" stroke-width="12"/>';

            // Center text
            svg.innerHTML += '<text x="' + centerX + '" y="' + (centerY - 5) + '" text-anchor="middle" font-size="24" font-weight="bold" fill="' + color + '">' + displayValue.toFixed(0) + '</text>';
            svg.innerHTML += '<text x="' + centerX + '" y="' + (centerY + 15) + '" text-anchor="middle" font-size="14" fill="#666">' + unit + '</text>';
        }

        function describeArc(x, y, radius, startAngle, endAngle) {
            var start = polarToCartesian(x, y, radius, endAngle);
            var end = polarToCartesian(x, y, radius, startAngle);
            var largeArcFlag = endAngle - startAngle <= Math.PI ? "0" : "1";
            return "M " + start.x + " " + start.y + " A " + radius + " " + radius + " 0 " + largeArcFlag + " 0 " + end.x + " " + end.y;
        }

        function polarToCartesian(centerX, centerY, radius, angleInRadians) {
            return {
                x: centerX + (radius * Math.cos(angleInRadians)),
                y: centerY + (radius * Math.sin(angleInRadians))
            };
        }

        function updateUptimeDisplay() {
            uptimeSeconds++;
            var days = Math.floor(uptimeSeconds / 86400);
            var hours = Math.floor((uptimeSeconds % 86400) / 3600);
            var minutes = Math.floor((uptimeSeconds % 3600) / 60);
            var seconds = uptimeSeconds % 60;

            $('#uptime-days').text(days.toString().padStart(2, '0'));
            $('#uptime-hours').text(hours.toString().padStart(2, '0'));
            $('#uptime-minutes').text(minutes.toString().padStart(2, '0'));
            $('#uptime-seconds').text(seconds.toString().padStart(2, '0'));
        }

        function formatBytes(bytes, decimals = 1) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(decimals)) + ' ' + sizes[i];
        }

        function updateStats() {
            $.get('api/system/status', function (data) {
                // Temperature
                if (data.sensors && data.sensors.length > 0) {
                    var temp = parseFloat(data.sensors[0].value);
                    <?php if (isset($settings['temperatureInF']) && $settings['temperatureInF'] == 1) { ?>
                        temp = (temp * 9 / 5) + 32;
                        // Fahrenheit thresholds: 140°F (60°C), 176°F (80°C), max 212°F (100°C)
                        drawGauge('tempGauge', temp, 'Temperature', '°F', { yellow: 140, red: 176, max: 212 });
                    <?php } else { ?>
                        // Celsius thresholds: 60°C, 80°C, max 100°C
                        drawGauge('tempGauge', temp, 'Temperature', '°C', { yellow: 60, red: 80, max: 100 });
                    <?php } ?>
                }

                // Uptime
                if (data.uptimeTotalSeconds) {
                    uptimeSeconds = parseInt(data.uptimeTotalSeconds);
                    updateUptimeDisplay();
                    if (!uptimeInterval) {
                        uptimeInterval = setInterval(updateUptimeDisplay, 1000);
                    }
                }

                if (data.uptimeStr) {
                    $('#uptime-started').text('Started: ' + data.uptimeStr);
                }

                // Load Average - get from PHP
                <?php
                $loadavg = sys_getloadavg();
                $cores = 4; // default
                if (file_exists('/proc/cpuinfo')) {
                    $cpuinfo = file_get_contents('/proc/cpuinfo');
                    preg_match_all('/^processor/m', $cpuinfo, $matches);
                    $cores = count($matches[0]);
                }
                ?>
                var loads = [<?= implode(',', $loadavg) ?>];
                var cores = <?= $cores ?>;

                if (loads.length >= 3) {
                    var load1 = parseFloat(loads[0]);
                    var pct1 = Math.min((load1 / cores) * 100, 100);
                    $('#load-1min-value').text(load1.toFixed(2));
                    $('#load-1min-bar').css('width', pct1 + '%').attr('aria-valuenow', pct1);
                    updateLoadColor($('#load-1min-bar'), pct1);

                    var load5 = parseFloat(loads[1]);
                    var pct5 = Math.min((load5 / cores) * 100, 100);
                    $('#load-5min-value').text(load5.toFixed(2));
                    $('#load-5min-bar').css('width', pct5 + '%').attr('aria-valuenow', pct5);
                    updateLoadColor($('#load-5min-bar'), pct5);

                    var load15 = parseFloat(loads[2]);
                    var pct15 = Math.min((load15 / cores) * 100, 100);
                    $('#load-15min-value').text(load15.toFixed(2));
                    $('#load-15min-bar').css('width', pct15 + '%').attr('aria-valuenow', pct15);
                    updateLoadColor($('#load-15min-bar'), pct15);
                }

                $('#cpu-cores').text(cores);
            });

            $.get('api/system/info', function (data) {
                // CPU Usage
                if (data.Utilization && data.Utilization.CPU !== undefined) {
                    drawGauge('cpuGauge', parseFloat(data.Utilization.CPU), 'CPU', '%');
                }

                // Memory Usage
                if (data.Utilization && data.Utilization.Memory !== undefined) {
                    drawGauge('memGauge', parseFloat(data.Utilization.Memory), 'Memory', '%');
                }
            });

            // Update disk storage - using server-side data
            updateDiskStorage();

            // Player Statistics
            updatePlayerStats();
        }

        function updateDiskStorage() {
            <?php
            // Get root disk info
            $rootTotal = disk_total_space("/");
            $rootFree = disk_free_space("/");
            $rootUsed = $rootTotal - $rootFree;
            $rootPct = ($rootUsed / $rootTotal) * 100;

            // Get root device name
            $rootDevice = 'unknown';
            if (file_exists("/bin/findmnt")) {
                exec('findmnt -n -o SOURCE / | colrm 1 5', $output, $return_val);
                if ($return_val == 0 && !empty($output[0])) {
                    $rootDevice = trim($output[0]);
                }
            }

            // Check if media is on different device
            $mediaDirectory = $settings['mediaDirectory'] ?? '/home/fpp/media';
            $mediaTotal = disk_total_space($mediaDirectory);
            $mediaFree = disk_free_space($mediaDirectory);
            $mediaSeparate = false;

            if (file_exists("/bin/findmnt")) {
                exec('findmnt -n -o SOURCE ' . $mediaDirectory . ' | colrm 1 5', $output2, $return_val2);
                if ($return_val2 == 0 && !empty($output2[0])) {
                    $mediaDevice = trim($output2[0]);
                    $mediaSeparate = ($mediaDevice !== $rootDevice);
                }
            }
            ?>

            // Root partition
            var rootUsed = <?= $rootUsed ?>;
            var rootTotal = <?= $rootTotal ?>;
            var rootFree = <?= $rootFree ?>;
            var rootPct = <?= $rootPct ?>;

            $('#root-device').text('<?= addslashes($rootDevice) ?>');
            $('#root-info').text(formatBytes(rootUsed) + ' / ' + formatBytes(rootTotal) + ' (' + formatBytes(rootFree) + ' free)');
            $('#root-bar').css('width', rootPct + '%').attr('aria-valuenow', rootPct);
            updateDiskColor($('#root-bar'), rootPct);

            <?php if ($mediaSeparate) {
                $mediaUsed = $mediaTotal - $mediaFree;
                $mediaPct = ($mediaUsed / $mediaTotal) * 100;
                ?>
                // Media partition
                var mediaUsed = <?= $mediaUsed ?>;
                var mediaTotal = <?= $mediaTotal ?>;
                var mediaFree = <?= $mediaFree ?>;
                var mediaPct = <?= $mediaPct ?>;

                $('#media-row').show();
                $('#media-device').text('<?= addslashes($mediaDevice) ?>');
                $('#media-info').text(formatBytes(mediaUsed) + ' / ' + formatBytes(mediaTotal) + ' (' + formatBytes(mediaFree) + ' free)');
                $('#media-bar').css('width', mediaPct + '%').attr('aria-valuenow', mediaPct);
                updateDiskColor($('#media-bar'), mediaPct);

                if (mediaPct > 85 || rootPct > 85) {
                    $('#disk-warning-content').html('<strong><i class="fas fa-exclamation-triangle"></i> High Disk Usage Detected!</strong> Your disk is running low on space. Consider cleaning up old files in the <a href="filemanager.php" class="alert-link">File Manager</a>.');
                    $('#disk-warning').show();
                } else {
                    $('#disk-warning').hide();
                }
            <?php } else { ?>
                $('#media-row').hide();

                if (rootPct > 85) {
                    $('#disk-warning-content').html('<strong><i class="fas fa-exclamation-triangle"></i> High Disk Usage Detected!</strong> Your disk is running low on space. Consider cleaning up old files in the <a href="filemanager.php" class="alert-link">File Manager</a>.');
                    $('#disk-warning').show();
                } else {
                    $('#disk-warning').hide();
                }
            <?php } ?>
        }

        function updateDiskColor(element, percent) {
            element.removeClass('bg-success bg-warning bg-danger');
            if (percent > 85) {
                element.addClass('bg-danger');
            } else if (percent > 70) {
                element.addClass('bg-warning');
            } else {
                element.addClass('bg-success');
            }
        }

        function updateLoadColor(element, percent) {
            element.removeClass('bg-success bg-warning bg-danger');
            if (percent > 80) {
                element.addClass('bg-danger');
            } else if (percent > 60) {
                element.addClass('bg-warning');
            } else {
                element.addClass('bg-success');
            }
        }

        function updatePlayerStats() {
            var stats = {
                schedules: 0,
                playlists: 0,
                sequences: 0,
                audio: 0,
                videos: 0,
                effects: 0,
                scripts: 0
            };

            $.get('api/playlists', function (data) {
                if (data && Array.isArray(data)) {
                    stats.playlists = data.length;
                }
                $('#stat-playlists').text(stats.playlists);
            });

            $.get('api/configfile/schedule.json', function (data) {
                if (data && data.entries && Array.isArray(data.entries)) {
                    stats.schedules = data.entries.length;
                }
                $('#stat-schedules').text(stats.schedules);
            });

            $.get('api/files/sequences', function (data) {
                if (data && Array.isArray(data)) {
                    stats.sequences = data.length;
                }
                $('#stat-sequences').text(stats.sequences);
            });

            $.get('api/files/music', function (data) {
                if (data && Array.isArray(data)) {
                    stats.audio = data.length;
                }
                $('#stat-audio').text(stats.audio);
            });

            $.get('api/files/videos', function (data) {
                if (data && Array.isArray(data)) {
                    stats.videos = data.length;
                }
                $('#stat-videos').text(stats.videos);
            });

            $.get('api/files/effects', function (data) {
                if (data && Array.isArray(data)) {
                    stats.effects = data.length;
                }
                $('#stat-effects').text(stats.effects);
            });

            $.get('api/files/scripts', function (data) {
                if (data && Array.isArray(data)) {
                    stats.scripts = data.length;
                }
                $('#stat-scripts').text(stats.scripts);
            });
        }

        $(document).ready(function () {
            StartHealthCheck(); updateStats();
            setInterval(function () {
                updateStats();
            }, 5000);
        });
    </script>
</head>

<body>
    <div id="bodyWrapper">
        <?php
        $activeParentMenuItem = 'status';
        include 'menu.inc';
        ?>
        <div class="mainContainer">
            <h1 class="title">Health and Status</h1>
            <div class="pageContent">

                <?php
                if (isset($settings["UnpartitionedSpace"]) && $settings["UnpartitionedSpace"] > 0) {
                    ?>
                    <div id='upgradeFlag' class="alert alert-danger" role="alert">
                        SD card has unused space. Go to
                        <a href="settings.php#settings-storage">Storage Settings</a> to expand the
                        file system or create a new storage partition.
                    </div>
                    <?php
                }
                ?>

                <!-- Health Check Section -->
                <div class="card compact-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3><i class="fas fa-heartbeat"></i> Health Check</h3>
                        <button id='btnStartHealthCheck' class='btn btn-sm btn-primary'
                            onClick='StartHealthCheck();'>Run Health Check</button>
                    </div>
                    <div class="card-body">
                        <div id='healthCheckOutput'></div>
                    </div>
                </div>

                <!-- Disk Space Warning Banner -->
                <div id="disk-warning" class="alert alert-warning alert-dismissible fade show warning-banner"
                    role="alert" style="display: none;">
                    <div id="disk-warning-content"></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>

                <!-- System Monitoring Gauges -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="card compact-card">
                            <div class="card-body gauge-container">
                                <svg id="cpuGauge" class="gauge-svg" viewBox="0 0 150 150"></svg>
                                <div class="gauge-label">CPU Usage</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card compact-card">
                            <div class="card-body gauge-container">
                                <svg id="memGauge" class="gauge-svg" viewBox="0 0 150 150"></svg>
                                <div class="gauge-label">Memory Usage</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card compact-card">
                            <div class="card-body gauge-container">
                                <svg id="tempGauge" class="gauge-svg" viewBox="0 0 150 150"></svg>
                                <div class="gauge-label">Temperature</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Disk Utilization -->
                <div class="card compact-card">
                    <div class="card-header">
                        <h3><i class="fas fa-hdd"></i> Disk Utilization</h3>
                    </div>
                    <div class="card-body">
                        <div class="disk-row">
                            <div class="disk-label">
                                <span class="disk-device">Root Partition (<span id="root-device">--</span>)</span>
                                <span class="disk-info" id="root-info">-- / --</span>
                            </div>
                            <div class="progress">
                                <div id="root-bar" class="progress-bar bg-success" role="progressbar" style="width: 0%"
                                    aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                        <div class="disk-row" id="media-row" style="display: none;">
                            <div class="disk-label">
                                <span class="disk-device">Media Partition (<span id="media-device">--</span>)</span>
                                <span class="disk-info" id="media-info">-- / --</span>
                            </div>
                            <div class="progress">
                                <div id="media-bar" class="progress-bar bg-success" role="progressbar" style="width: 0%"
                                    aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Second Row: Uptime, Load Average, Player Stats -->
                <div class="row">
                    <!-- System Uptime -->
                    <div class="col-md-4">
                        <div class="card compact-card">
                            <div class="card-header">
                                <h3><i class="fas fa-clock"></i> System Uptime</h3>
                            </div>
                            <div class="card-body uptime-display">
                                <div class="uptime-counters">
                                    <div class="uptime-segment">
                                        <div class="uptime-number" id="uptime-days">00</div>
                                        <div class="uptime-label">Days</div>
                                    </div>
                                    <div class="uptime-segment">
                                        <div class="uptime-number">:</div>
                                    </div>
                                    <div class="uptime-segment">
                                        <div class="uptime-number" id="uptime-hours">00</div>
                                        <div class="uptime-label">Hours</div>
                                    </div>
                                    <div class="uptime-segment">
                                        <div class="uptime-number">:</div>
                                    </div>
                                    <div class="uptime-segment">
                                        <div class="uptime-number" id="uptime-minutes">00</div>
                                        <div class="uptime-label">Minutes</div>
                                    </div>
                                    <div class="uptime-segment">
                                        <div class="uptime-number">:</div>
                                    </div>
                                    <div class="uptime-segment">
                                        <div class="uptime-number" id="uptime-seconds">00</div>
                                        <div class="uptime-label">Seconds</div>
                                    </div>
                                </div>
                                <div class="uptime-started" id="uptime-started">--</div>
                            </div>
                        </div>
                    </div>

                    <!-- Load Average -->
                    <div class="col-md-4">
                        <div class="card compact-card">
                            <div class="card-header">
                                <h3>
                                    <i class="fas fa-tachometer-alt"></i> Load Average
                                    <i class="fas fa-question-circle" data-bs-toggle="popover" data-bs-trigger="hover"
                                        data-bs-placement="top"
                                        data-bs-content="Load average represents the average system load over 1, 5, and 15 minute periods. Values are normalized to CPU core count. Green = healthy, Yellow = moderate load, Red = high load."
                                        style="font-size: 0.8em; cursor: help;"></i>
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="load-bar-container">
                                    <div class="load-label">
                                        <span>1 minute</span>
                                        <span id="load-1min-value">--</span>
                                    </div>
                                    <div class="progress">
                                        <div id="load-1min-bar" class="progress-bar bg-success" role="progressbar"
                                            style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                        </div>
                                    </div>
                                </div>
                                <div class="load-bar-container">
                                    <div class="load-label">
                                        <span>5 minutes</span>
                                        <span id="load-5min-value">--</span>
                                    </div>
                                    <div class="progress">
                                        <div id="load-5min-bar" class="progress-bar bg-success" role="progressbar"
                                            style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                        </div>
                                    </div>
                                </div>
                                <div class="load-bar-container">
                                    <div class="load-label">
                                        <span>15 minutes</span>
                                        <span id="load-15min-value">--</span>
                                    </div>
                                    <div class="progress">
                                        <div id="load-15min-bar" class="progress-bar bg-success" role="progressbar"
                                            style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                        </div>
                                    </div>
                                </div>
                                <div class="text-center mt-2">
                                    <small class="text-muted"><span id="cpu-cores">--</span> CPU cores available</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Player Statistics -->
                    <div class="col-md-4">
                        <div class="card compact-card">
                            <div class="card-header">
                                <h3><i class="fas fa-chart-bar"></i> Player Statistics</h3>
                            </div>
                            <div class="card-body" style="padding: 8px;">
                                <a href="scheduler.php" class="stat-link">
                                    <span class="stat-name">Schedules</span>
                                    <span class="stat-count" id="stat-schedules">0</span>
                                </a>
                                <a href="playlists.php" class="stat-link">
                                    <span class="stat-name">Playlists</span>
                                    <span class="stat-count" id="stat-playlists">0</span>
                                </a>
                                <a href="filemanager.php" class="stat-link">
                                    <span class="stat-name">Sequences</span>
                                    <span class="stat-count" id="stat-sequences">0</span>
                                </a>
                                <a href="filemanager.php#tab-audio" class="stat-link">
                                    <span class="stat-name">Audio Files</span>
                                    <span class="stat-count" id="stat-audio">0</span>
                                </a>
                                <a href="filemanager.php#tab-video" class="stat-link">
                                    <span class="stat-name">Videos</span>
                                    <span class="stat-count" id="stat-videos">0</span>
                                </a>
                                <a href="filemanager.php#tab-effects" class="stat-link">
                                    <span class="stat-name">Effects</span>
                                    <span class="stat-count" id="stat-effects">0</span>
                                </a>
                                <a href="filemanager.php#tab-scripts" class="stat-link">
                                    <span class="stat-name">Scripts</span>
                                    <span class="stat-count" id="stat-scripts">0</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <?php include 'common/footer.inc'; ?>
    <script>
        // Initialize popovers
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });
    </script>
</body>

</html>