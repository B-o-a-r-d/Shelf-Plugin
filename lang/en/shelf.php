<?php

return [
    'plugin_description' => 'Turns a board into a document shelf: explorer tree, markdown notes and files under a quota.',
    'setting_default_quota' => 'Default storage quota (GB)',
    'setting_default_quota_help' => 'Applied to every Shelf board without a specific quota.',

    'name' => 'name',
    'quota' => 'quota',

    'back_to_dashboard' => 'Dashboard',
    'save' => 'Save',
    'open' => 'Open',

    'quota_usage' => ':used used of :quota',
    'quota_configure' => 'Configure the quota',
    'quota_override' => 'Quota for this board',
    'quota_override_help' => 'Leave empty to inherit the instance default quota (:default GB).',
    'gb' => 'GB',
    'mb' => 'MB',
    'kb' => 'KB',
    'bytes' => 'bytes',

    'new_folder' => 'New folder',
    'new_note' => 'New note',
    'folder_name_placeholder' => 'Folder name…',
    'note_name_placeholder' => 'Note title…',
    'tree_empty' => 'Nothing here yet. Create a folder or a note to get started.',
    'rename' => 'Rename',
    'move_to_root' => 'Move to root',
    'move_to_trash' => 'Move to trash',

    'empty_state_title' => 'Your shelf is ready.',
    'empty_state_hint' => 'Select an item in the tree, or create a folder or a note from the left panel.',
    'folder_empty' => 'This folder is empty.',
    'note_editor_soon' => 'Markdown note editing arrives in the next Shelf phase.',
    'file_preview_soon' => 'File previews arrive in an upcoming Shelf phase.',

    'trash' => 'Trash',
    'trash_hint' => 'Trashed items are permanently deleted after :days days. Restoring an item restores its whole branch.',
    'trash_empty' => 'The trash is empty.',
    'restore' => 'Restore',
    'delete_forever' => 'Delete forever',
    'delete_forever_confirm' => 'Permanently delete ":name" and everything inside it? This cannot be undone.',
];
