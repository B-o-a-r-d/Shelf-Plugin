<?php

return [
    'plugin_description' => 'Convierte un tablero en una estantería documental: árbol de carpetas, notas markdown y archivos bajo cuota.',
    'setting_default_quota' => 'Cuota de almacenamiento por defecto (GB)',
    'setting_default_quota_help' => 'Se aplica a cada tablero Shelf sin cuota específica.',

    'name' => 'nombre',
    'quota' => 'cuota',

    'back_to_dashboard' => 'Panel',
    'save' => 'Guardar',
    'open' => 'Abrir',

    'quota_usage' => ':used usados de :quota',
    'quota_configure' => 'Configurar la cuota',
    'quota_override' => 'Cuota de este tablero',
    'quota_override_help' => 'Dejar vacío para heredar la cuota por defecto de la instancia (:default GB).',
    'gb' => 'GB',
    'mb' => 'MB',
    'kb' => 'KB',
    'bytes' => 'bytes',

    'new_folder' => 'Nueva carpeta',
    'new_note' => 'Nueva nota',
    'folder_name_placeholder' => 'Nombre de la carpeta…',
    'note_name_placeholder' => 'Título de la nota…',
    'tree_empty' => 'Aún no hay nada. Crea una carpeta o una nota para empezar.',
    'rename' => 'Renombrar',
    'move_to_root' => 'Mover a la raíz',
    'move_to_trash' => 'Mover a la papelera',

    'empty_state_title' => 'Tu estantería está lista.',
    'empty_state_hint' => 'Selecciona un elemento del árbol, o crea una carpeta o una nota desde el panel izquierdo.',
    'folder_empty' => 'Esta carpeta está vacía.',
    'note_editor_soon' => 'La edición de notas markdown llega en la próxima fase de Shelf.',
    'file_preview_soon' => 'La previsualización de archivos llega en una próxima fase de Shelf.',

    'trash' => 'Papelera',
    'trash_hint' => 'Los elementos de la papelera se eliminan definitivamente tras :days días. Restaurar un elemento restaura toda su rama.',
    'trash_empty' => 'La papelera está vacía.',
    'restore' => 'Restaurar',
    'delete_forever' => 'Eliminar definitivamente',
    'delete_forever_confirm' => '¿Eliminar definitivamente «:name» y todo su contenido? Esta acción es irreversible.',
];
