<?php

return [
    'plugin_description' => 'Transforme un board en étagère documentaire : arborescence, notes markdown et fichiers sous quota.',
    'setting_default_quota' => 'Quota de stockage par défaut (Go)',
    'setting_default_quota_help' => 'Appliqué à chaque board Shelf sans quota spécifique.',

    'name' => 'nom',
    'quota' => 'quota',

    'back_to_dashboard' => 'Tableau de bord',
    'save' => 'Enregistrer',
    'open' => 'Ouvrir',

    'quota_usage' => ':used utilisés sur :quota',
    'quota_configure' => 'Configurer le quota',
    'quota_override' => 'Quota de ce board',
    'quota_override_help' => 'Laisser vide pour hériter du quota par défaut de l\'instance (:default Go).',
    'gb' => 'Go',
    'mb' => 'Mo',
    'kb' => 'Ko',
    'bytes' => 'octets',

    'new_folder' => 'Nouveau dossier',
    'new_note' => 'Nouvelle note',
    'folder_name_placeholder' => 'Nom du dossier…',
    'note_name_placeholder' => 'Titre de la note…',
    'tree_empty' => 'Aucun élément. Créez un dossier ou une note pour commencer.',
    'rename' => 'Renommer',
    'move_to_root' => 'Déplacer à la racine',
    'move_to_trash' => 'Mettre à la corbeille',

    'empty_state_title' => 'Votre étagère est prête.',
    'empty_state_hint' => 'Sélectionnez un élément dans l\'arborescence, ou créez un dossier ou une note depuis le panneau de gauche.',
    'folder_empty' => 'Ce dossier est vide.',
    'note_editor_soon' => 'L\'édition des notes markdown arrive dans la prochaine phase de Shelf.',
    'file_preview_soon' => 'La prévisualisation des fichiers arrive dans une prochaine phase de Shelf.',

    'trash' => 'Corbeille',
    'trash_hint' => 'Les éléments de la corbeille sont supprimés définitivement après :days jours. Restaurer un élément restaure toute sa branche.',
    'trash_empty' => 'La corbeille est vide.',
    'restore' => 'Restaurer',
    'delete_forever' => 'Supprimer définitivement',
    'delete_forever_confirm' => 'Supprimer définitivement « :name » et tout son contenu ? Cette action est irréversible.',
];
