<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap cbp-form-builder-wrap">
    <h1 style="font-weight:800; font-size:28px; margin-bottom:10px;"><?php _e('Form Builder', 'consultation-booking-pro'); ?></h1>
    <p style="color:#64748b; margin-bottom:30px;">Manage your frontend booking form, customize styles, and edit templates.</p>

    <!-- Tabs Navigation -->
    <h2 class="nav-tab-wrapper" style="margin-bottom:20px;">
        <a href="#form-editor" class="nav-tab nav-tab-active" data-tab="form-editor"><?php _e('Form Editor', 'consultation-booking-pro'); ?></a>
        <a href="#code-files" class="nav-tab" data-tab="code-files"><?php _e('Code Files', 'consultation-booking-pro'); ?></a>
        <a href="#email-templates" class="nav-tab" data-tab="email-templates"><?php _e('Email Templates', 'consultation-booking-pro'); ?></a>
        <a href="#form-preview" class="nav-tab" data-tab="form-preview"><?php _e('Preview', 'consultation-booking-pro'); ?></a>
    </h2>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.34.1/min/vs/loader.min.js"></script>
    <script>
        require.config({ paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.34.1/min/vs' } });
    </script>

    <!-- Tab 1: Form Editor -->
    <div id="tab-form-editor" class="cbp-tab-content active" style="display:flex; height: calc(100vh - 250px); background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden;">
        <!-- Left: Field Tree -->
        <div style="width:300px; border-right:1px solid #e2e8f0; background:#f8fafc; display:flex; flex-direction:column;">
            <div style="padding:15px; border-bottom:1px solid #e2e8f0; font-weight:700; font-size:12px; text-transform:uppercase; color:#64748b;">
                <?php _e('Form Structure', 'consultation-booking-pro'); ?>
            </div>
            <div id="cbp-field-tree" style="flex:1; overflow-y:auto; padding:10px;">
                <!-- Tree items will be injected here via JS -->
                 <div style="padding:20px; text-align:center; color:#94a3b8;">Loading structure...</div>
            </div>
            <div style="padding:15px; border-top:1px solid #e2e8f0; display:flex; gap:10px;">
                <button class="button" style="flex:1;" onclick="app.addRow()">+ Add Row</button>
                <button class="button" style="flex:1;" onclick="app.addField()">+ Add Field</button>
            </div>
        </div>

        <!-- Center: Field Config -->
        <div style="flex:1; overflow-y:auto; padding:30px; background:#fff;">
            <div id="cbp-field-config-panel">
                <div style="text-align:center; padding:100px 50px; color:#cbd5e1;">
                    <span class="dashicons dashicons-edit" style="font-size:48px; width:48px; height:48px;"></span>
                    <p>Select a field from the tree to edit its properties.</p>
                </div>
            </div>
        </div>

        <!-- Right: Small Live Preview -->
        <div style="width:380px; border-left:1px solid #e2e8f0; background:#f1f5f9; display:flex; flex-direction:column;">
            <div style="padding:15px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
                <span style="font-weight:700; font-size:12px; text-transform:uppercase; color:#64748b;">Live Preview</span>
                <div style="display:flex; gap:5px;">
                    <button class="button button-small icon-btn" title="Desktop"><span class="dashicons dashicons-desktop"></span></button>
                    <button class="button button-small icon-btn" title="Mobile"><span class="dashicons dashicons-smartphone"></span></button>
                </div>
            </div>
            <div style="flex:1; padding:15px; overflow-y:auto;" id="cbp-mini-preview">
                <div style="background:#fff; border-radius:8px; border:1px solid #e2e8f0; padding:15px; transform: scale(0.6); transform-origin: top center; width: 150%;">
                    <!-- Mini preview iFrame or clone -->
                     <div id="preview-container"></div>
                </div>
            </div>
            <div style="padding:15px; border-top:1px solid #e2e8f0; background:#fff;">
                <button class="button button-primary button-large" style="width:100%; border-radius:8px; font-weight:700;" onclick="app.saveAllChanges()">SAVE ALL CHANGES</button>
            </div>
        </div>
    </div>

    <!-- Tab 2: Code Files (Existing functionality) -->
    <div id="tab-code-files" class="cbp-tab-content" style="display:none;">
        <div style="display:flex; height: calc(100vh - 250px); background:#1e1e1e; border-radius:12px; overflow:hidden;">
            <div id="cbp-file-explorer" style="width:250px; border-right:1px solid #333; padding:15px; background:#252526; color:#cccccc; display:flex; flex-direction:column;">
                <h4 style="margin-top:0; font-size:11px; text-transform:uppercase; opacity:0.5;">Files</h4>
                <div id="file-list" style="flex:1; overflow-y:auto;"></div>
                <div style="padding-top:15px; border-top:1px solid #333; display:flex; flex-direction:column; gap:8px;">
                    <button class="button button-secondary button-small" style="width:100%; background:#333; border:none; color:#ddd;" onclick="app.downloadFiles()">Download ZIP</button>
                    <button class="button button-secondary button-small" style="width:100%; background:#333; border:none; color:#ddd;" onclick="app.uploadFiles()">Upload Files</button>
                </div>
            </div>
            <div id="cbp-monaco-container" style="flex:1; position:relative;">
                <div id="cbp-editor-placeholder" style="position:absolute; top:50%; left:50%; translate:-50% -50%; color:#555;">Select a file to edit</div>
            </div>
        </div>
    </div>

    <!-- Tab 3: Email Templates -->
    <div id="tab-email-templates" class="cbp-tab-content" style="display:none;">
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
            <!-- Student Email -->
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; display:flex; flex-direction:column;">
                <div style="padding:15px; border-bottom:1px solid #e2e8f0; font-weight:700;">Student Confirmation Email</div>
                <div style="padding:15px; flex:1;">
                    <label style="display:block; font-weight:600; margin-bottom:5px;">Subject Line</label>
                    <input type="text" id="email-student-subject" class="widefat" value="Your Appointment Confirmed!">
                    <div id="editor-student-email" style="height:400px; border:1px solid #e2e8f0; margin-top:15px; border-radius:8px; overflow:hidden;"></div>
                </div>
                <div style="padding:15px; border-top:1px solid #e2e8f0; display:flex; justify-content:space-between;">
                    <button class="button" onclick="app.previewEmail('student')">Preview</button>
                    <button class="button button-primary" onclick="app.saveEmail('student')">Save Template</button>
                </div>
            </div>

            <!-- Counselor Email -->
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; display:flex; flex-direction:column;">
                <div style="padding:15px; border-bottom:1px solid #e2e8f0; font-weight:700;">Counselor Notification Email</div>
                <div style="padding:15px; flex:1;">
                    <label style="display:block; font-weight:600; margin-bottom:5px;">Subject Line</label>
                    <input type="text" id="email-counselor-subject" class="widefat" value="New Consultation Request!">
                    <div id="editor-counselor-email" style="height:400px; border:1px solid #e2e8f0; margin-top:15px; border-radius:8px; overflow:hidden;"></div>
                </div>
                <div style="padding:15px; border-top:1px solid #e2e8f0; display:flex; justify-content:space-between;">
                    <button class="button" onclick="app.previewEmail('counselor')">Preview</button>
                    <button class="button button-primary" onclick="app.saveEmail('counselor')">Save Template</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab 4: Full Preview -->
    <div id="tab-form-preview" class="cbp-tab-content" style="display:none; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:12px; padding:40px;">
        <div style="display:flex; justify-content:center; gap:20px; margin-bottom:30px;">
            <div style="background:#fff; padding:10px 20px; border-radius:30px; display:flex; gap:10px; box-shadow:0 10px 15px -3px rgba(0,0,0,0.1);">
                <button class="button" onclick="app.setPreviewSize('desktop')">Desktop</button>
                <button class="button" onclick="app.setPreviewSize('tablet')">Tablet</button>
                <button class="button" onclick="app.setPreviewSize('mobile')">Mobile</button>
            </div>
            <button class="button" onclick="app.copyShortcode()">Copy Shortcode</button>
            <a href="<?php echo home_url('/'); ?>" target="_blank" class="button">View on Frontend</a>
        </div>
        <div id="preview-frame-container" style="max-width:1100px; margin:0 auto; box-shadow: 0 40px 100px -20px rgba(0,0,0,0.2); border-radius:24px; overflow:hidden; transition: all 0.3s ease-out; background:#fff;">
             <iframe src="<?php echo add_query_arg(['cbp_preview' => 1], home_url('/')); ?>" id="cbp-full-preview-frame" style="width:100%; height:800px; border:none;"></iframe>
        </div>
    </div>
</div>

<style>
.cbp-tab-content { display: none; }
.cbp-tab-content.active { display: block; }
.nav-tab { cursor: pointer; }
.icon-btn .dashicons { font-size: 16px; width: 16px; height: 16px; margin-top: 4px; }
</style>
