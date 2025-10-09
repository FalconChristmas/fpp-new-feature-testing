<?
$skipJSsettings = 1;
require_once('common.php');

/////////////////////////////////////////////////////////////////////////////
// Set a sane audio device if not set already
if (
    (!isset($settings['AudioOutput'])) ||
    ($settings['AudioOutput'] == '')
) {

    exec($SUDO . " grep card /root/.asoundrc | head -n 1 | awk '{print $2}'", $output, $return_val);
    if ($return_val) {
        error_log("Error getting currently selected alsa card used!");
    } else {
        if (isset($output[0]))
            $settings['AudioOutput'] = $output[0];
        else
            $settings['AudioOutput'] = "0";
    }
    unset($output);
}

/////////////////////////////////////////////////////////////////////////////
// Set a sane audio mixer device if not set already
if (!isset($settings['AudioMixerDevice'])) {
    if ($settings['BeaglePlatform']) {
        $settings['AudioMixerDevice'] = exec($SUDO . " amixer -c " . $settings['AudioOutput'] . " scontrols | head -1 | cut -f2 -d\"'\"", $output, $return_val);
        if ($return_val) {
            $settings['AudioMixerDevice'] = "PCM";
        }
    } else {
        $settings['AudioMixerDevice'] = "PCM";
    }
}

/////////////////////////////////////////////////////////////////////////////

?>

<script>
$(document).ready(function() {
    // Function to show/hide the multi-channel device selector based on AudioLayout
    function updateMultiChannelDeviceVisibility() {
        var audioLayout = parseInt($('#AudioLayout').val());
        // The row is a div with class 'row' and ID ending in 'Row', not a tr
        var $alsaDeviceRow = $('#AlsaAudioDeviceRow');
        
        // Show the ALSA device selector only when multi-channel (> 2 channels) is selected
        // AudioLayout values: 0=Stereo (2ch), 1-3=3ch, 4-7=4ch, 8-10=5ch, 11-12=6ch, 13=6ch+LFE, 14-15=7-8ch
        if (audioLayout > 0 && !isNaN(audioLayout)) {
            // Multi-channel selected (3+ channels)
            $alsaDeviceRow.show();
            
            // Remove any existing info message first
            $alsaDeviceRow.next('.multichannel-info').remove();
            
            // Add helpful info message
            var channelCount = 2;
            if (audioLayout <= 3) channelCount = 3;
            else if (audioLayout <= 7) channelCount = 4;
            else if (audioLayout <= 10) channelCount = 5;
            else if (audioLayout <= 12) channelCount = 6;
            else if (audioLayout <= 14) channelCount = 7;
            else if (audioLayout <= 15) channelCount = 8;
            
            var infoHtml = '<div class="row multichannel-info"><div class="col-md" style="padding-left: 20px; font-size: 0.9em; color: #666;">' +
                '<i class="fas fa-info-circle"></i> Multi-channel audio requires a compatible sound card and ALSA device. ' +
                'If no devices are listed above, your sound card may not support ' + channelCount + '-channel output, ' +
                'or you may need to configure ALSA manually.</div></div>';
            $alsaDeviceRow.after(infoHtml);
        } else {
            // Stereo selected (or no valid selection) - hide the multi-channel device selector
            $alsaDeviceRow.hide();
            $alsaDeviceRow.next('.multichannel-info').remove();
        }
    }
    
    // Wait for settings to be fully loaded before running
    setTimeout(function() {
        updateMultiChannelDeviceVisibility();
    }, 100);
    
    // Run when AudioLayout changes
    $('#AudioLayout').on('change', function() {
        // Remove info immediately on change, will be re-added if needed
        $('#AlsaAudioDeviceRow').next('.multichannel-info').remove();
        updateMultiChannelDeviceVisibility();
    });
});
</script>


<?
PrintSettingGroup('generalAudio');
PrintSettingGroup('generalVideo');
?>
