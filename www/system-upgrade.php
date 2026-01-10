<!DOCTYPE html>
<html>

<head>
    <?php
    require_once 'config.php';
    require_once 'common.php';

    include 'common/menuHead.inc';

    $fppVersion = getFPPVersion();
    $localGitVersion = get_local_git_version();
    $remoteGitVersion = get_remote_git_version();

    $uploadDirectory = $mediaDirectory . "/upload";
    $freeSpace = disk_free_space($uploadDirectory);
    $osUpdateFiles = getFileList($uploadDirectory, "fppos");

    if (file_exists("/proc/cpuinfo")) {
        $serialNumber = exec("sed -n 's/^Serial.*: //p' /proc/cpuinfo", $output, $return_val);
        if ($return_val != 0) {
            unset($serialNumber);
        }
    }
    if ((!isset($serialNumber) || $serialNumber == "") && $settings['Variant'] == "PocketBeagle2") {
        $serialNumber = exec("dd if=/sys/bus/i2c/devices/0-0050/eeprom count=16 skip=40 bs=1 2>/dev/null", $output, $return_val);
        if ($return_val != 0) {
            unset($serialNumber);
        }
    }
    unset($output);
    ?>
    <style>
        .upgrade-card {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background-color: #f8f9fa;
        }

        .upgrade-card h3 {
            color: #333;
            margin-bottom: 15px;
        }

        .upgrade-button {
            width: 100%;
            padding: 12px;
            font-size: 1.1em;
            margin-top: 10px;
        }

        .version-info {
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .version-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .version-row:last-child {
            border-bottom: none;
        }

        .version-label {
            font-weight: 600;
            color: #666;
        }

        .version-value {
            color: #333;
        }

        .update-badge {
            font-size: 0.75em;
            margin-left: 5px;
        }

        .faq-accordion .accordion-button {
            font-size: 0.85em;
            padding: 0.6rem 0.85rem;
            background-color: #fff;
            border: none;
            font-weight: 600;
            color: #333;
            position: relative;
            padding-right: 2.5rem;
        }

        .faq-accordion .accordion-button:not(.collapsed) {
            background-color: #f8f9fa;
            color: #000;
            box-shadow: none;
        }

        .faq-accordion .accordion-button:focus {
            box-shadow: none;
            border-color: rgba(0, 0, 0, .125);
        }

        .faq-accordion .accordion-button::after {
            content: '\f078';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            right: 0.85rem;
            transition: transform 0.2s ease-in-out;
            font-size: 0.75em;
        }

        .faq-accordion .accordion-button:not(.collapsed)::after {
            transform: rotate(180deg);
        }

        .faq-accordion .accordion-body {
            font-size: 0.85em;
            padding: 0.85rem 1rem;
            line-height: 1.5;
            background-color: #f8f9fa;
            color: #555;
        }

        .faq-accordion .accordion-item {
            margin-bottom: 0.35rem;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            overflow: hidden;
        }

        .faq-accordion .accordion-item:last-child {
            margin-bottom: 0;
        }

        .resources-card {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
        }

        .resources-card h3 {
            margin-bottom: 15px;
        }

        .resources-card ul {
            list-style: none;
            padding-left: 0;
        }

        .resources-card ul li {
            padding: 5px 0;
        }

        .resources-card ul li i {
            width: 20px;
            text-align: center;
        }

        .version-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .version-header h2 {
            margin: 0;
            font-size: 1.8em;
        }

        .version-header .git-hash {
            font-family: monospace;
            opacity: 0.9;
            font-size: 0.9em;
        }

        .update-banner {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .update-banner.fpp-update {
            background-color: #d4edda;
            border: 2px solid #28a745;
            color: #155724;
        }

        .update-banner.os-update {
            background-color: #fff3cd;
            border: 2px solid #ffc107;
            color: #856404;
        }

        .update-banner i {
            font-size: 2em;
        }

        .update-banner .banner-content h4 {
            margin: 0 0 5px 0;
        }

        .update-banner .banner-content p {
            margin: 0;
        }

        .version-indicator {
            background-color: #e9ecef;
            border: 2px solid #28a745;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .version-indicator:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-color: #1e7e34;
        }

        .version-indicator.os-version {
            border-color: #ffc107;
        }

        .version-indicator.os-version:hover {
            border-color: #e0a800;
        }

        .version-indicator .version-progress {
            display: flex;
            align-items: center;
            gap: 15px;
            font-family: monospace;
            font-weight: bold;
        }

        .version-indicator .version-arrow {
            color: #6c757d;
        }

        .version-indicator .commits-behind {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9em;
            color: #6c757d;
            margin-top: 5px;
        }

        .comparison-table {
            margin: 30px 0;
        }

        .comparison-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .comparison-table th {
            background-color: #f8f9fa;
            padding: 15px;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
        }

        .comparison-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #dee2e6;
        }

        .comparison-table tr:last-child td {
            border-bottom: none;
        }

        .comparison-table .feature-name {
            font-weight: bold;
        }

        .advanced-card {
            background-color: #f8f9fa;
            border: 2px solid #6c757d;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }

        .advanced-card h4 {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .badge.recommended {
            background-color: #28a745;
        }

        .badge.advanced {
            background-color: #6c757d;
        }
    </style>
    <script>
        var osAssetMap = {};

        function OpenChangelogModal() {
            DoModalDialog({
                id: 'changelogModal',
                title: 'FPP Changelog',
                body: '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading changelog...</div>',
                class: 'modal-xl',
                keyboard: true,
                backdrop: true
            });

            $.get('api/git/originLog', function (data) {
                var html = '<div style="max-height: 70vh; overflow-y: auto;">';
                html += '<p class="alert alert-info"><i class="fas fa-info-circle"></i> This shows the git commit history for your current branch. Each entry represents a change to the FPP software.</p>';
                html += '<table class="table table-striped table-sm">';
                html += '<thead><tr><th width="100px">Commit</th><th width="150px">Author</th><th width="150px">Date</th><th>Message</th></tr></thead>';
                html += '<tbody>';

                if (data.rows && data.rows.length > 0) {
                    data.rows.forEach(function (row) {
                        html += '<tr>';
                        html += '<td><code>' + row.hash.substring(0, 8) + '</code></td>';
                        html += '<td>' + row.author + '</td>';
                        html += '<td style="font-size: 0.85em; color: #666;">' + (row.date || '') + '</td>';
                        html += '<td>' + row.msg + '</td>';
                        html += '</tr>';
                    });
                } else {
                    html += '<tr><td colspan="4" class="text-center">No changelog entries found</td></tr>';
                }

                html += '</tbody></table>';
                html += '<div class="text-center mt-3">';
                html += '<a href="https://github.com/FalconChristmas/fpp/commits" target="_blank" class="btn btn-outline-primary">';
                html += '<i class="fas fa-external-link-alt"></i> View Full History on GitHub';
                html += '</a>';
                html += '</div>';
                html += '</div>';

                $('#changelogModal .modal-body').html(html);
            }).fail(function () {
                $('#changelogModal .modal-body').html('<div class="alert alert-danger">Failed to load changelog</div>');
            });
        }

        function ViewOSReleaseNotes() {
            var osVersion = $('#osSelect option:selected').text();

            if (!osVersion || osVersion == '-- Choose an OS Version --') {
                DialogError('No OS Selected', 'Please select an OS version first to view its release notes.');
                return;
            }

            DoModalDialog({
                id: 'osReleaseNotesModal',
                title: 'OS Release Notes: ' + osVersion,
                body: '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading release notes...</div>',
                class: 'modal-lg',
                keyboard: true,
                backdrop: true
            });

            // Extract version tag from OS filename (e.g., FPP-v9.0-Pi.img -> v9.0)
            var versionMatch = osVersion.match(/v(\d+\.\d+(?:\.\d+)?(?:-[a-z]+\d*)?)/);
            if (!versionMatch) {
                $('#osReleaseNotesModal .modal-body').html('<div class="alert alert-warning">Could not determine version number from selected OS.</div>');
                return;
            }

            var version = 'v' + versionMatch[1];

            // Fetch release notes from GitHub API
            $.ajax({
                url: 'https://api.github.com/repos/FalconChristmas/fpp/releases/tags/' + version,
                dataType: 'json',
                success: function (release) {
                    var html = '<div style="max-height: 70vh; overflow-y: auto;">';

                    if (release.name) {
                        html += '<h4>' + release.name + '</h4>';
                    }

                    if (release.published_at) {
                        var date = new Date(release.published_at);
                        html += '<p class="text-muted"><i class="fas fa-calendar"></i> Published: ' + date.toLocaleDateString() + '</p>';
                    }

                    if (release.body) {
                        // Convert markdown to HTML (basic conversion)
                        var body = release.body
                            .replace(/#{3} (.+)/g, '<h5>$1</h5>')
                            .replace(/#{2} (.+)/g, '<h4>$1</h4>')
                            .replace(/# (.+)/g, '<h3>$1</h3>')
                            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                            .replace(/\*(.+?)\*/g, '<em>$1</em>')
                            .replace(/^- (.+)/gm, '<li>$1</li>')
                            .replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>')
                            .replace(/\n\n/g, '</p><p>')
                            .replace(/^(?!<[hul])/gm, '<p>')
                            .replace(/(?<![>])$/gm, '</p>');

                        html += '<div class="release-notes-body">' + body + '</div>';
                    } else {
                        html += '<p class="text-muted">No release notes available for this version.</p>';
                    }

                    html += '<div class="mt-3">';
                    html += '<a href="' + release.html_url + '" target="_blank" class="btn btn-outline-primary">';
                    html += '<i class="fas fa-external-link-alt"></i> View Full Release on GitHub';
                    html += '</a>';
                    html += '</div>';
                    html += '</div>';

                    $('#osReleaseNotesModal .modal-body').html(html);
                },
                error: function () {
                    var html = '<div class="alert alert-warning">';
                    html += '<i class="fas fa-info-circle"></i> Release notes not found for version ' + version + '.';
                    html += '<br><br>This may be because:';
                    html += '<ul><li>The version hasn\'t been officially released yet</li>';
                    html += '<li>It\'s a development or beta build</li>';
                    html += '<li>The release notes are not available on GitHub</li></ul>';
                    html += '<a href="https://github.com/FalconChristmas/fpp/releases" target="_blank" class="btn btn-outline-primary mt-2">';
                    html += '<i class="fas fa-external-link-alt"></i> Browse All Releases on GitHub';
                    html += '</a>';
                    html += '</div>';
                    $('#osReleaseNotesModal .modal-body').html(html);
                }
            });
        }

        function GetGitOriginLog() {
            DoModalDialog({
                id: 'gitOriginLogModal',
                title: 'Pending Git Changes',
                body: '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading changes...</div>',
                class: 'modal-lg',
                keyboard: true,
                backdrop: true
            });

            $.get('api/git/originLog', function (data) {
                var html = '<table class="table table-striped table-sm"><thead><tr><th width="80px">Commit</th><th width="150px">Author</th><th>Message</th></tr></thead><tbody>';
                if (data.rows && data.rows.length > 0) {
                    data.rows.forEach(function (row) {
                        html += '<tr>';
                        html += '<td><code>' + row.hash.substring(0, 8) + '</code></td>';
                        html += '<td>' + row.author + '</td>';
                        html += '<td>' + row.msg + '</td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table>';
                } else {
                    html += '<tr><td colspan="3" class="text-center">';
                    html += '<div class="alert alert-info mb-0">';
                    html += '<i class="fas fa-info-circle"></i> ';
                    html += 'Unable to determine pending changes. This may occur when working with feature branches or forks. ';
                    html += 'You can still update to get the latest changes using the "Update FPP Now" button.';
                    html += '</div>';
                    html += '</td></tr>';
                    html += '</tbody></table>';
                }
                $('#gitOriginLogModal .modal-body').html(html);
            }).fail(function () {
                $('#gitOriginLogModal .modal-body').html('<div class="alert alert-danger">Failed to load git changes</div>');
            });
        }

        function UpdateVersionInfo() {
            $.get('api/system/status', function (data) {
                if (data.advancedView) {
                    if (data.advancedView.Version) {
                        $('#fppVersionValue').text(data.advancedView.Version);
                    }
                    if (data.advancedView.Platform) {
                        $('#platformValue').text(data.advancedView.Platform);
                    }
                    if (data.advancedView.Variant) {
                        $('#variantValue').text(data.advancedView.Variant);
                    }
                    if (data.advancedView.Mode) {
                        $('#modeValue').text(data.advancedView.Mode);
                    }
                    if (data.advancedView.HostName) {
                        $('#hostnameValue').text(data.advancedView.HostName);
                    }
                    if (data.advancedView.HostDescription) {
                        $('#hostDescValue').text(data.advancedView.HostDescription);
                    }
                    if (data.advancedView.OSVersion) {
                        $('#osVersionValue').text(data.advancedView.OSVersion);
                    }
                    if (data.advancedView.OSRelease) {
                        $('#osReleaseValue').text(data.advancedView.OSRelease);
                    }
                    if (data.advancedView.Kernel) {
                        $('#kernelValue').text(data.advancedView.Kernel);
                    }

                    var localVer = data.advancedView.LocalGitVersion;
                    var remoteVer = data.advancedView.RemoteGitVersion;

                    if (data.advancedView.LocalGitVersion) {
                        $('#localGitValue').html(data.advancedView.LocalGitVersion);
                        $('#localGitShort').text(data.advancedView.LocalGitVersion);
                    }

                    if (remoteVer && remoteVer !== "Unknown" && remoteVer !== "" && remoteVer !== localVer) {
                        $('#remoteGitValue').html(remoteVer);
                        $('#remoteGitShort').text(remoteVer);
                        $('#gitUpdateBadge').show();
                        $('#fppVersionIndicator').show();
                        $('#fppUpdateBanner').show();
                        $('#fppVersionStatusBadge').removeClass('bg-secondary bg-success').addClass('bg-warning').text('Update Available');

                        // Show commit badges in header
                        $('#currentCommitBadge').text(localVer);
                        $('#targetCommitBadge').text(remoteVer);

                        // Fetch commit count from git origin log
                        $.get('api/git/originLog', function (gitData) {
                            if (gitData.rows && gitData.rows.length > 0) {
                                $('#commitCount').text(gitData.rows.length);
                            }
                        });

                        $('#gitCommitBadges').show();
                    } else {
                        $('#remoteGitValue').html(remoteVer || '<span class="text-muted">Unknown</span>');
                        $('#gitUpdateBadge').hide();
                        $('#fppVersionIndicator').hide();
                        $('#fppUpdateBanner').hide();
                        $('#fppVersionStatusBadge').removeClass('bg-secondary bg-warning').addClass('bg-success').text('Up to Date');
                        $('#gitCommitBadges').hide();
                    }

                    // Show OS version badge
                    $('#osVersionStatusBadge').show();
                }
            });
        }

        function UpgradeFPP() {
            var options = {
                id: "upgradePopupStatus",
                title: "FPP Upgrade",
                body: "<textarea style='max-width:100%; max-height:100%; width: 100%; height:100%;' disabled id='streamedUpgradeText'></textarea>",
                class: "modal-dialog-scrollable",
                noClose: true,
                keyboard: false,
                backdrop: "static",
                footer: "",
                buttons: {
                    "Close": {
                        id: 'fppUpgradeCloseDialogButton',
                        click: function () {
                            CloseFPPUpgradeDialog();
                        },
                        disabled: true,
                        class: 'btn-success'
                    }
                }
            };
            $("#fppUpgradeCloseDialogButton").prop("disabled", true);
            DoModalDialog(options);
            StreamURL('manualUpdate.php?wrapped=1', 'streamedUpgradeText', 'FPPUpgradeDone');
        }

        function FPPUpgradeDone() {
            $('#fppUpgradeCloseDialogButton').prop("disabled", false);
            EnableModalDialogCloseButton("upgradePopupStatus");
            UpdateVersionInfo();
        }

        function CloseFPPUpgradeDialog() {
            CloseModalDialog('upgradePopupStatus');
            location.reload();
        }

        function PopulateOSSelect() {
            <? if ($freeSpace > 1000000000) { ?>

                var allPlatforms = '';
                if ($('#allPlatforms').is(':checked')) {
                    allPlatforms = 'api/git/releases/os/all';
                } else {
                    allPlatforms = 'api/git/releases/os';
                }

                //cleanup previous load values
                $('#osSelect option').filter(function () { return parseInt(this.value) > 0; }).remove();

                $.get(allPlatforms, function (data) {
                    var devMode = (settings['uiLevel'] && (parseInt(settings['uiLevel']) == 3));
                    if ("files" in data) {
                        for (const file of data["files"]) {
                            osAssetMap[file["asset_id"]] = {
                                name: file["filename"],
                                url: file["url"]
                            };

                            if (!file["downloaded"] && (devMode || !file['filename'].match(/-v?(4\.|5\.[0-4])/))) {
                                $('#osSelect').append($('<option>', {
                                    value: file["asset_id"],
                                    text: file["filename"] + " (download)"
                                }));
                            }
                            if (file["downloaded"]) {
                                $('#osSelect').append($('<option>', {
                                    value: file["asset_id"],
                                    text: file["filename"]
                                }));
                            }
                        }
                    }

                    //handle what age OS to display
                    if ($('#LegacyOS').is(':checked')) {
                        //leave all avail options in place
                    } else {
                        //remove legacy files (n-1) - git assetid needs manually updating over time
                        $('#osSelect option').filter(function () { return parseInt(this.value) < 103024154; }).remove();
                    }

                    //only show alpha and beta images in Advanced ui
                    if (settings['uiLevel'] && (parseInt(settings['uiLevel']) >= 1)) {
                        //leave all avail options in place
                    } else {
                        $('#osSelect option').filter(function () { return (/beta/i.test(this.text)); }).remove();
                        $('#osSelect option').filter(function () { return (/alpha/i.test(this.text)); }).remove();
                    }

                    //insert files already downloaded if we haven't got them from the git api call
                    var osUpdateFiles = <?php echo json_encode($osUpdateFiles); ?>;
                    var select = $('#osSelect');
                    osUpdateFiles.forEach(element => {
                        if (select.has('option:contains("' + element + '")').length == 0) {
                            $('#osSelect').append($('<option>', {
                                value: element,
                                text: element
                            }));
                        }
                    });
                });

            <? } ?>
        }

        function UpgradeOS() {
            var os = $('#osSelect').val();
            var osName = os;

            if (os == '') {
                DialogError('No OS Selected', 'Please select an OS version to upgrade to.');
                return;
            }
            if (os in osAssetMap) {
                osName = osAssetMap[os].name;
                os = osAssetMap[os].url;
            }

            //override file location from git to local if already downloaded
            if ($('#osSelect option:selected').text().toLowerCase().indexOf('(download)') === -1) {
                os = $('#osSelect option:selected').text();
                osName = $('#osSelect option:selected').text();
            }

            if (confirm('Upgrade the OS using ' + osName +
                '?\nThis can take a long time. It is also strongly recommended to run FPP backup first.')) {

                var options = {
                    id: "upgradeOSPopupStatus",
                    title: "FPP OS Upgrade",
                    body: "<textarea style='max-width:100%; max-height:100%; width: 100%; height:100%;' disabled id='streamedUpgradeOSText'></textarea>",
                    class: "modal-dialog-scrollable",
                    noClose: true,
                    keyboard: false,
                    backdrop: "static",
                    footer: "",
                    buttons: {
                        "Close": {
                            id: 'fppUpgradeOSCloseDialogButton',
                            click: function () {
                                CloseModalDialog("upgradeOSPopupStatus");
                                location.reload();
                            },
                            disabled: true,
                            class: 'btn-success'
                        }
                    }
                };
                $("#fppUpgradeOSCloseDialogButton").prop("disabled", true);
                DoModalDialog(options);

                StreamURL('upgradeOS.php?wrapped=1&os=' + os + keepOptFPP, 'streamedUpgradeOSText', 'UpgradeDone', 'UpgradeDone');
            }
        }

        function UpgradeDone() {
            $("#fppUpgradeOSCloseDialogButton").prop("disabled", false);
            EnableModalDialogCloseButton("upgradeOSPopupStatus");
            UpdateVersionInfo();
        }

        function DownloadOS() {
            var os = $('#osSelect').val();
            var osName = os;

            if (os == '')
                return;

            if (os in osAssetMap) {
                osName = osAssetMap[os].name;
                os = osAssetMap[os].url;

                var options = {
                    id: "downloadPopupStatus",
                    title: "FPP Download OS Image",
                    body: "<textarea style='max-width:100%; max-height:100%; width: 100%; height:100%;' disabled id='streamedUDownloadText'></textarea>",
                    class: "modal-dialog-scrollable",
                    noClose: true,
                    keyboard: false,
                    backdrop: "static",
                    footer: "",
                    buttons: {
                        "Close": {
                            id: 'fppDownloadCloseDialogButton',
                            click: function () {
                                CloseModalDialog("downloadPopupStatus");
                                location.reload();
                            },
                            disabled: true,
                            class: 'btn-success'
                        }
                    }
                };
                $("#fppDownloadCloseDialogButton").prop("disabled", true);
                DoModalDialog(options);

                StreamURL('upgradeOS.php?wrapped=1&downloadOnly=1&os=' + os, 'streamedUDownloadText', 'DownloadDone');
            } else {
                alert('This fppos image has already been downloaded.');
            }
        }

        function DownloadDone() {
            $("#fppDownloadCloseDialogButton").prop("disabled", false);
            EnableModalDialogCloseButton("downloadPopupStatus");
            PopulateOSSelect();
        }

        function ViewOSReleaseNotes() {
            var osVersion = $('#osSelect option:selected').text();

            if (!osVersion || osVersion == '-- Choose an OS Version --') {
                DialogError('No OS Selected', 'Please select an OS version first to view its release notes.');
                return;
            }

            DoModalDialog({
                id: 'osReleaseNotesModal',
                title: 'OS Release Notes: ' + osVersion,
                body: '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading release notes...</div>',
                class: 'modal-lg',
                keyboard: true,
                backdrop: true
            });

            // Extract version tag from OS filename (e.g., BBB-9.3_2025-11.fppos -> 9.3)
            var versionMatch = osVersion.match(/(v?\d+\.\d+(?:\.\d+)?(?:-[a-z]+\d*)?)/i);
            if (!versionMatch) {
                $('#osReleaseNotesModal .modal-body').html('<div class=\"alert alert-warning\">Could not determine version number from selected OS.</div>');
                return;
            }

            var version = versionMatch[1];
            // Remove 'v' prefix if present - GitHub tags use numeric version (e.g., 9.3, not v9.3)
            if (version.startsWith('v') || version.startsWith('V')) {
                version = version.substring(1);
            }

            // Fetch release notes from GitHub API
            $.ajax({
                url: 'https://api.github.com/repos/FalconChristmas/fpp/releases/tags/' + version,
                dataType: 'json',
                success: function (release) {
                    var html = '<div style=\"max-height: 70vh; overflow-y: auto;\">';

                    if (release.name) {
                        html += '<h4>' + release.name + '</h4>';
                    }

                    if (release.published_at) {
                        var date = new Date(release.published_at);
                        html += '<p class=\"text-muted\"><i class=\"fas fa-calendar\"></i> Published: ' + date.toLocaleDateString() + '</p>';
                    }

                    if (release.body) {
                        // Convert markdown to HTML
                        var body = release.body
                            // Escape HTML
                            .replace(/&/g, '&amp;')
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;')
                            // Headers
                            .replace(/^### (.+)$/gm, '<h5>$1</h5>')
                            .replace(/^## (.+)$/gm, '<h4>$1</h4>')
                            .replace(/^# (.+)$/gm, '<h3>$1</h3>')
                            // Bold
                            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                            .replace(/__(.+?)__/g, '<strong>$1</strong>')
                            // Italic
                            .replace(/\*(.+?)\*/g, '<em>$1</em>')
                            .replace(/_(.+?)_/g, '<em>$1</em>')
                            // Code blocks
                            .replace(/```(.+?)```/gs, '<pre><code>$1</code></pre>')
                            .replace(/`(.+?)`/g, '<code>$1</code>')
                            // Links
                            .replace(/\[(.+?)\]\((.+?)\)/g, '<a href="$2" target="_blank">$1</a>')
                            // Line breaks and paragraphs
                            .replace(/\n\n/g, '</p><p>')
                            .replace(/\n/g, '<br>');

                        // Wrap lists in ul tags
                        body = body.replace(/(<br>)?- (.+?)(<br>|<\/p>)/g, function (match, br1, content, br2) {
                            return '<li>' + content + '</li>';
                        });
                        body = body.replace(/(<li>.*?<\/li>)+/g, function (match) {
                            return '<ul>' + match + '</ul>';
                        });

                        html += '<div class=\"release-notes-body\"><p>' + body + '</p></div>';
                    } else {
                        html += '<p class=\"text-muted\">No release notes available for this version.</p>';
                    }

                    html += '<div class=\"mt-3\">';
                    html += '<a href=\"' + release.html_url + '\" target=\"_blank\" class=\"btn btn-outline-primary\">';
                    html += '<i class=\"fas fa-external-link-alt\"></i> View Full Release on GitHub';
                    html += '</a>';
                    html += '</div>';
                    html += '</div>';

                    $('#osReleaseNotesModal .modal-body').html(html);
                },
                error: function () {
                    var html = '<div class=\"alert alert-warning\">';
                    html += '<i class=\"fas fa-info-circle\"></i> Release notes not found for version ' + version + '.';
                    html += '<br><br>This may be because:';
                    html += '<ul><li>The version hasn\'t been officially released yet</li>';
                    html += '<li>It\'s a development or beta build</li>';
                    html += '<li>The release notes are not available on GitHub</li></ul>';
                    html += '<a href=\"https://github.com/FalconChristmas/fpp/releases\" target=\"_blank\" class=\"btn btn-outline-primary mt-2\">';
                    html += '<i class=\"fas fa-external-link-alt\"></i> Browse All Releases on GitHub';
                    html += '</a>';
                    html += '</div>';
                    $('#osReleaseNotesModal .modal-body').html(html);
                }
            });
        }

        function OSSelectChanged() {
            var os = $('#osSelect').val();
            <?
            // we want at least a 200MB in order to be able to apply the fppos
            if ($freeSpace < 200000000) {
                echo "os = '';\n";
            } ?>
            if (os == '') {
                $('#osUpgradeButton').attr('disabled', 'disabled');
                $('#osDownloadButton').attr('disabled', 'disabled');
                $('#osReleaseNotesButton').attr('disabled', 'disabled');
            } else {
                $('#osUpgradeButton').removeAttr('disabled');
                $('#osReleaseNotesButton').removeAttr('disabled');
                if (os in osAssetMap) {
                    $('#osDownloadButton').removeAttr('disabled');
                } else {
                    $('#osDownloadButton').attr('disabled', 'disabled');
                }
            }
        }

        $(document).ready(function () {
            UpdateVersionInfo();
            PopulateOSSelect();
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
            <h1 class="title">System Upgrade</h1>
            <div class="pageContent">

                <!-- Update Banners (conditionally shown) -->
                <div id="fppUpdateBanner" class="update-banner fpp-update" style="display: none;">
                    <i class="fas fa-check-circle"></i>
                    <div class="banner-content">
                        <h4>FPP Software Update Available!</h4>
                        <p>A new version of the FPP software is ready to install. Updates typically complete in under 5
                            minutes and keep all your settings.</p>
                    </div>
                </div>

                <div id="osUpdateBanner" class="update-banner os-update" style="display: none;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div class="banner-content">
                        <h4>Operating System Upgrade Available</h4>
                        <p>A major OS version is available. OS upgrades include security patches, new hardware support,
                            and system improvements. Always backup first!</p>
                    </div>
                </div>

                <? if (!isset($settings['cape-info']) || !isset($settings['cape-info']['verifiedKeyId']) || ($settings['cape-info']['verifiedKeyId'] != 'fp')) { ?>
                    <div id="donateBanner"
                        style="text-align: center; padding: 30px; background-color: #343a40; color: #ffffff; border-radius: 8px; margin-bottom: 20px;">
                        <h3><i class="fas fa-heart" style="color: #e74c3c;"></i> Support FPP Development</h3>
                        <p style="margin-bottom: 10px;">
                            If you would like to donate to the Falcon Player development team to help support the continued
                            development of FPP, you can use the donate button below.
                        </p>
                        <form action="https://www.paypal.com/donate" method="post" target="_top"
                            style="display: inline-block;">
                            <input type="hidden" name="hosted_button_id" value="ASF9XYZ2V2F5G" />
                            <input style="height: 75px;" type="image"
                                src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit"
                                title="Donate to the Falcon Player" alt="Donate to the Falcon Player" />
                            <img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1"
                                height="1" />
                        </form>
                        <p style="margin-bottom: 10px;">
                            It takes a lot of time, equipment and coffee to drive the backbone of your shows so any love you
                            can share much appreciated by the project team!
                        </p>
                    </div>
                <? } ?>

                <!-- Version Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h3><i class="fas fa-info-circle"></i> Version Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4" style="border-right: 1px solid #dee2e6;">
                                <div class="version-row">
                                    <span class="version-label">FPP Version:</span>
                                    <span class="version-value">
                                        <span id="fppVersionStatusBadge" class="badge bg-secondary"
                                            style="font-size: 0.7em; margin-right: 5px;">Checking...</span>
                                        <span id="fppVersionValue"><?= $fppVersion ?></span>
                                    </span>
                                </div>
                                <div class="version-row">
                                    <span class="version-label">Platform:</span>
                                    <span class="version-value" id="platformValue">
                                        <?php
                                        echo $settings['Platform'];
                                        if (($settings['Variant'] != '') && ($settings['Variant'] != $settings['Platform'])) {
                                            echo " (" . $settings['Variant'] . ")";
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>

                            <div class="col-md-4" style="border-right: 1px solid #dee2e6;">
                                <div class="version-row">
                                    <span class="version-label">OS Build:</span>
                                    <span class="version-value" id="osReleaseValue">--</span>
                                </div>
                                <div class="version-row">
                                    <span class="version-label">OS Version:</span>
                                    <span class="version-value">
                                        <span id="osVersionStatusBadge" class="badge bg-secondary"
                                            style="font-size: 0.7em; margin-right: 5px; display: none;">Up to
                                            Date</span>
                                        <span id="osVersionValue">--</span>
                                    </span>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <? if (isset($serialNumber) && $serialNumber != "") { ?>
                                    <div class="version-row">
                                        <span class="version-label">Serial Number:</span>
                                        <span class="version-value"><?= $serialNumber ?></span>
                                    </div>
                                <? } ?>
                                <div class="version-row">
                                    <span class="version-label">Kernel:</span>
                                    <span class="version-value" id="kernelValue">--</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upgrade Options -->
                <div class="row">
                    <!-- FPP Software Update -->
                    <div class="col-md-6">
                        <div class="upgrade-card">
                            <h3>
                                <i class="fas fa-sync-alt"></i> FPP Software Update
                                <span id="gitUpdateBadge" class="badge bg-success update-badge"
                                    style="display: none;">Update Available</span>
                                <span id="gitCommitBadges"
                                    style="display: none; font-size: 0.65em; font-weight: normal; margin-left: 10px;">
                                    <span class="badge bg-secondary" id="currentCommitBadge"
                                        style="font-family: monospace;"></span>
                                    <i class="fas fa-arrow-right" style="margin: 0 5px; color: #6c757d;"></i>
                                    <span class="badge bg-primary" id="targetCommitBadge"
                                        style="font-family: monospace;"></span>
                                    <span class="badge bg-info" id="commitCountBadge" style="margin-left: 5px;">
                                        <i class="fas fa-code-branch"></i> <span id="commitCount">0</span> commits
                                    </span>
                                </span>
                            </h3>

                            <p style="font-size: 0.9em; color: #666;">
                                Updates the FPP application software (fppd daemon, web interface, scripts, and plugins)
                                from your current git branch.
                                This is a lightweight update that preserves all your settings and typically completes in
                                under a minute.
                                A reboot is only required if system-level changes are included.
                            </p>

                            <!-- Clickable Version Indicator -->
                            <div id="fppVersionIndicator" class="version-indicator" style="display: none;"
                                onclick="GetGitOriginLog();">
                                <div class="version-progress">
                                    <span id="localGitShort"></span>
                                    <span class="version-arrow"><i class="fas fa-arrow-right"></i></span>
                                    <span id="remoteGitShort"></span>
                                </div>
                                <div class="commits-behind">
                                    <i class="fas fa-search"></i>
                                    <span id="commitsBehindText">Click to preview changes</span>
                                </div>
                            </div>

                            <div class="version-info">
                                <div class="version-row">
                                    <span class="version-label">Local Git:</span>
                                    <span class="version-value" id="localGitValue"><?= $localGitVersion ?></span>
                                </div>
                                <div class="version-row">
                                    <span class="version-label">Remote Git:</span>
                                    <span class="version-value"
                                        id="remoteGitValue"><?= $remoteGitVersion !== 'Unknown' ? $remoteGitVersion : '<span class="text-muted">Unknown</span>' ?></span>
                                </div>
                            </div>

                            <?
                            if ($settings['uiLevel'] > 0) {
                                $upgradeSources = array();
                                $remotes = getKnownFPPSystems();

                                if ($settings["Platform"] != "MacOS") {
                                    $IPs = explode("\n", trim(shell_exec("/sbin/ifconfig -a | cut -f1 | cut -f1 -d' ' | grep -v ^$ | grep -v lo | grep -v eth0:0 | grep -v usb | grep -v SoftAp | grep -v 'can.' | sed -e 's/://g' | while read iface ; do /sbin/ifconfig \$iface | grep 'inet ' | awk '{print \$2}'; done")));
                                } else {
                                    $IPs = explode("\n", trim(shell_exec("/sbin/ifconfig -a | grep 'inet ' | awk '{print \$2}'")));
                                }
                                $found = 0;
                                foreach ($remotes as $desc => $host) {
                                    if ((!in_array($host, $IPs)) && (!preg_match('/^169\.254\./', $host))) {
                                        $upgradeSources[$desc] = $host;
                                        if (isset($settings['UpgradeSource']) && ($settings['UpgradeSource'] == $host)) {
                                            $found = 1;
                                        }
                                    }
                                }
                                if (!$found && isset($settings['UpgradeSource']) && ($settings['UpgradeSource'] != 'github.com')) {
                                    $upgradeSources = array($settings['UpgradeSource'] . ' (Unreachable)' => $settings['UpgradeSource'], 'github.com' => 'github.com') + $upgradeSources;
                                } else {
                                    $upgradeSources = array("github.com" => "github.com") + $upgradeSources;
                                }
                                ?>
                                <div style="margin: 15px 0;">
                                    <label><i class='fas fa-fw fa-graduation-cap ui-level-1'></i> Upgrade Source:</label>
                                    <? PrintSettingSelect("FPP Upgrade Source", "UpgradeSource", 0, 0, "github.com", $upgradeSources); ?>
                                </div>
                            <? } ?>

                            <button class="btn btn-primary upgrade-button" onclick="UpgradeFPP();">
                                <i class="fas fa-download"></i> Update FPP Software
                            </button>

                            <button class="btn btn-outline-secondary upgrade-button" onclick="OpenChangelogModal();">
                                <i class="fas fa-list"></i> View Changelog
                            </button>
                        </div>
                    </div>

                    <!-- Operating System Upgrade -->
                    <div class="col-md-6">
                        <div class="upgrade-card">
                            <h3><i class="fas fa-compact-disc"></i> Operating System Upgrade
                                <span class="badge bg-warning update-badge">Backup First!</span>
                            </h3>

                            <p style="font-size: 0.9em; color: #666;">
                                Replaces the entire operating system with a new FPP OS image. This includes the base
                                Linux OS, kernel, drivers, and FPP software.
                                Use this for major version upgrades or when switching hardware platforms.
                                <strong>Always backup your configuration first!</strong> The process typically takes
                                15-30 minutes and will automatically reboot your system.
                            </p>

                            <div class="form-group">
                                <label for="osSelect"><b>Select OS Version:</b></label>
                                <select id="osSelect" class="form-control" onChange="OSSelectChanged();">
                                    <option value="">-- Choose an OS Version --</option>
                                </select>
                            </div>

                            <div style="margin: 15px 0; padding: 10px; background-color: #fff3cd; border-radius: 5px;">
                                <i class="fas fa-exclamation-triangle text-warning"></i>
                                <b>Warning:</b> OS upgrade will reboot your system. Ensure no shows are running.
                            </div>

                            <? if (isset($settings['uiLevel']) && $settings['uiLevel'] >= 1) { ?>
                                <div style="margin: 10px 0;">
                                    <label style="display: block; margin: 5px 0;">
                                        <i class='fas fa-fw fa-graduation-cap ui-level-1'></i>
                                        <input type="checkbox" id="allPlatforms" onChange="PopulateOSSelect();">
                                        Show All Platforms
                                        <img title='Show both BBB & Pi downloads' src='images/redesign/help-icon.svg'
                                            class='icon-help'>
                                    </label>
                                    <label style="display: block; margin: 5px 0;">
                                        <i class='fas fa-fw fa-graduation-cap ui-level-1'></i>
                                        <input type="checkbox" id="LegacyOS" onChange="PopulateOSSelect();">
                                        Show Legacy OS Versions
                                        <img title='Include historic OS releases in listing'
                                            src='images/redesign/help-icon.svg' class='icon-help'>
                                    </label>
                                </div>
                            <? } ?>

                            <? if (isset($settings['uiLevel']) && $settings['uiLevel'] >= 3) { ?>
                                <div style="margin: 10px 0;">
                                    <label>
                                        <i class='fas fa-fw fa-code ui-level-3'></i>
                                        <input type="checkbox" id="keepOptFPP">
                                        Keep /opt/fpp <span class="badge bg-danger">Dev Only</span>
                                        <img title='WARNING: This will upgrade the OS but will not upgrade the FPP version running in /opt/fpp.  This is useful for developers who are developing the code in /opt/fpp and just want the underlying OS upgraded.'
                                            src='images/redesign/help-icon.svg' class='icon-help'>
                                    </label>
                                </div>
                            <? } ?>

                            <button class="btn btn-warning" id="osUpgradeButton" onclick="UpgradeOS();" disabled>
                                <i class="fas fa-sync-alt"></i> Upgrade OS
                            </button>
                            <button class="btn btn-info" id="osDownloadButton" onclick="DownloadOS();"
                                style="margin-left: 10px;" disabled>
                                <i class="fas fa-download"></i> Download Only
                            </button>
                            <button class="btn btn-outline-secondary" id="osReleaseNotesButton"
                                onclick="ViewOSReleaseNotes();" style="margin-left: 10px;" disabled>
                                <i class="fas fa-file-alt"></i> Release Notes
                            </button>
                        </div>
                    </div>
                </div>



                <? if (isset($settings['uiLevel']) && $settings['uiLevel'] >= 1) { ?>
                    <!-- Advanced Options Card -->
                    <div class="advanced-card">
                        <h4>
                            <i class="fas fa-code"></i> Advanced Options
                            <span class="badge advanced">UI Level 1+</span>
                        </h4>
                        <p style="margin-top: 10px;">
                            For users who need to roll back changes, you can revert to a previous git commit using the
                            changelog page.
                            This allows you to undo problematic updates while keeping your configuration.
                        </p>
                        <button class="btn btn-secondary" onclick="window.location.href='changelog.php';">
                            <i class="fas fa-history"></i> View Changelog & Revert Options
                        </button>
                    </div>
                <? } ?>

                <!-- FAQ and Resources Row -->
                <div class="row mt-4">
                    <!-- FAQ Section -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-question-circle"></i> Frequently Asked Questions</h3>
                            </div>
                            <div class="card-body">
                                <div class="accordion faq-accordion" id="faqAccordion">
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="faq1Header">
                                            <button class="accordion-button collapsed" type="button"
                                                data-bs-toggle="collapse" data-bs-target="#faq1" aria-expanded="false">
                                                What's the difference between FPP Software Update and OS Upgrade?
                                            </button>
                                        </h2>
                                        <div id="faq1" class="accordion-collapse collapse"
                                            data-bs-parent="#faqAccordion">
                                            <div class="accordion-body">
                                                <b>FPP Software Update</b> updates only the FPP application code (fppd,
                                                web
                                                interface, scripts) from your current git branch. This is quick and
                                                doesn't
                                                require a reboot unless there are system-level changes.
                                                <br><br>
                                                <b>OS Upgrade</b> replaces the entire operating system image, including
                                                the base
                                                OS, kernel, drivers, and FPP. This is more comprehensive but takes
                                                longer and
                                                always requires a reboot.
                                            </div>
                                        </div>
                                    </div>

                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="faq2Header">
                                            <button class="accordion-button collapsed" type="button"
                                                data-bs-toggle="collapse" data-bs-target="#faq2" aria-expanded="false">
                                                Will my settings and sequences be preserved?
                                            </button>
                                        </h2>
                                        <div id="faq2" class="accordion-collapse collapse"
                                            data-bs-parent="#faqAccordion">
                                            <div class="accordion-body">
                                                Yes! Both update methods preserve your configuration files, sequences,
                                                playlists, and media files. Your settings are stored in /home/fpp/media
                                                which is
                                                not affected by updates.
                                            </div>
                                        </div>
                                    </div>

                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="faq3Header">
                                            <button class="accordion-button collapsed" type="button"
                                                data-bs-toggle="collapse" data-bs-target="#faq3" aria-expanded="false">
                                                How long does an OS upgrade take?
                                            </button>
                                        </h2>
                                        <div id="faq3" class="accordion-collapse collapse"
                                            data-bs-parent="#faqAccordion">
                                            <div class="accordion-body">
                                                OS upgrades typically take 15-30 minutes depending on your internet
                                                connection
                                                speed and SD card/storage speed. The system will download the image,
                                                write it to
                                                storage, and automatically reboot when complete.
                                            </div>
                                        </div>
                                    </div>

                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="faq4Header">
                                            <button class="accordion-button collapsed" type="button"
                                                data-bs-toggle="collapse" data-bs-target="#faq4" aria-expanded="false">
                                                Can I downgrade to an older version?
                                            </button>
                                        </h2>
                                        <div id="faq4" class="accordion-collapse collapse"
                                            data-bs-parent="#faqAccordion">
                                            <div class="accordion-body">
                                                Yes, if "Show Legacy OS Versions" is enabled (UI Level 1+), you can
                                                select and
                                                install older OS versions. However, this is generally not recommended
                                                unless
                                                you're troubleshooting a specific issue.
                                            </div>
                                        </div>
                                    </div>

                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="faq5Header">
                                            <button class="accordion-button collapsed" type="button"
                                                data-bs-toggle="collapse" data-bs-target="#faq5" aria-expanded="false">
                                                What does "Keep /opt/fpp" do?
                                            </button>
                                        </h2>
                                        <div id="faq5" class="accordion-collapse collapse"
                                            data-bs-parent="#faqAccordion">
                                            <div class="accordion-body">
                                                This developer-only option (UI Level 3) preserves your /opt/fpp
                                                directory during
                                                an OS upgrade. This allows you to keep a custom-built FPP installation
                                                rather
                                                than using the version included in the OS image. <b>Only use this if
                                                    you're
                                                    actively developing FPP!</b>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Resources -->
                    <div class="col-md-4">
                        <div class="resources-card">
                            <h3><i class="fas fa-link"></i> Resources</h3>
                            <ul>
                                <li><i class="fas fa-code-branch"></i> <a href="https://github.com/FalconChristmas/fpp"
                                        target="_blank">GitHub Repository</a></li>
                                <li><i class="fas fa-book"></i> <a
                                        href="https://github.com/FalconChristmas/fpp/blob/master/README.md"
                                        target="_blank">Documentation</a></li>
                                <li><i class="fas fa-users"></i> <a href="https://www.facebook.com/groups/falconplayer"
                                        target="_blank">Facebook
                                        Group</a></li>
                                <li><i class="fas fa-comments"></i> <a href="http://forums.falconchristmas.com"
                                        target="_blank">Forums</a></li>
                                <li><i class="fas fa-bug"></i> <a href="https://github.com/FalconChristmas/fpp/issues"
                                        target="_blank">Report Issues</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <!-- Comparison Table -->
                <div class="comparison-table card mt-4">
                    <div class="card-header">
                        <h3><i class="fas fa-balance-scale"></i> Comparison: FPP Update vs OS Upgrade</h3>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th class="feature-name">Feature</th>
                                    <th style="text-align: center;"><i class="fas fa-sync-alt text-success"></i> FPP
                                        Software Update</th>
                                    <th style="text-align: center;"><i class="fas fa-compact-disc text-warning"></i> OS
                                        Upgrade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="feature-name">Time Required</td>
                                    <td style="text-align: center;">2-5 minutes</td>
                                    <td style="text-align: center;">15-30+ minutes</td>
                                </tr>
                                <tr>
                                    <td class="feature-name">Reboot Required</td>
                                    <td style="text-align: center;"><i class="fas fa-question text-warning"></i>
                                        Sometimes
                                    </td>
                                    <td style="text-align: center;"><i class="fas fa-check text-warning"></i> Yes</td>
                                </tr>
                                <tr>
                                    <td class="feature-name">Settings Preserved</td>
                                    <td style="text-align: center;"><i class="fas fa-check text-success"></i> Kept</td>
                                    <td style="text-align: center;"><i
                                            class="fas fa-exclamation-triangle text-warning"></i> Backup Recommended*
                                    </td>
                                </tr>
                                <tr>
                                    <td class="feature-name">Media Files</td>
                                    <td style="text-align: center;"><i class="fas fa-check text-success"></i> Kept</td>
                                    <td style="text-align: center;"><i class="fas fa-check text-success"></i> Kept</td>
                                </tr>
                                <tr>
                                    <td class="feature-name">Risk Level</td>
                                    <td style="text-align: center;"><span class="badge bg-success">Low</span></td>
                                    <td style="text-align: center;"><span class="badge bg-warning">Medium</span></td>
                                </tr>
                                <tr>
                                    <td class="feature-name">OS Security Patches</td>
                                    <td style="text-align: center;"><i class="fas fa-times text-danger"></i> No</td>
                                    <td style="text-align: center;"><i class="fas fa-check text-success"></i> Yes</td>
                                </tr>
                                <tr>
                                    <td class="feature-name">Major Version Jump</td>
                                    <td style="text-align: center;"><i class="fas fa-times text-danger"></i> No</td>
                                    <td style="text-align: center;"><i class="fas fa-check text-success"></i> Yes</td>
                                </tr>
                                <tr>
                                    <td class="feature-name">New Hardware Support</td>
                                    <td style="text-align: center;"><i class="fas fa-times text-danger"></i> No</td>
                                    <td style="text-align: center;"><i class="fas fa-check text-success"></i> Yes</td>
                                </tr>
                            </tbody>
                        </table>
                        <p class="text-muted" style="font-size: 0.85em; margin-top: 10px;">
                            <i class="fas fa-info-circle"></i> * OS upgrades preserve settings in most cases, but a
                            backup is always recommended for safety.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include 'common/footer.inc'; ?>
</body>

</html>