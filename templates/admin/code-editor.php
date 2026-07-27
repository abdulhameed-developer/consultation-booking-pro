<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
    <h1><?php _e('Code Editor', 'consultation-booking-pro'); ?></h1>
    <p class="description"><?php _e('Modify plugin files directly. Be careful with PHP files.', 'consultation-booking-pro'); ?></p>

    <div id="cbp-code-editor-container" class="mt-4" style="display: grid; grid-template-columns: 250px 1fr; gap: 20px; height: 700px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; overflow: hidden;">
        <div id="cbp-file-explorer" style="border-right: 1px solid #ccd0d4; padding: 10px; overflow-y: auto; background: #f6f7f7;">
            <div style="font-weight: bold; margin-bottom: 10px; font-size: 11px; text-transform: uppercase; color: #646970;">File Explorer</div>
            <div id="cbp-file-tree">Loading...</div>
        </div>
        <div id="cbp-editor-main" style="display: flex; flex-direction: column;">
            <div id="cbp-editor-toolbar" style="padding: 10px; border-bottom: 1px solid #ccd0d4; background: #fff; display: flex; justify-content: space-between; align-items: center;">
                <span id="cbp-current-file" style="font-family: monospace; font-size: 12px; color: #1d2327;">No file selected</span>
                <button id="cbp-save-file-btn" class="button button-primary" disabled><?php _e('Save Changes', 'consultation-booking-pro'); ?></button>
            </div>
            <div id="cbp-monaco-editor" style="flex: 1; border: none;"></div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.34.1/min/vs/loader.min.js"></script>
<script>
    require.config({ paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.34.1/min/vs' }});
</script>
