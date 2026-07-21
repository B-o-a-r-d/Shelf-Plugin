<h1 align="center">Shelf</h1>

<p align="center">A document <strong>board type</strong> for <a href="https://github.com/B-o-a-r-d/Board">Board</a> — a Trilium-like shelf living next to your kanban boards.</p>

---

Shelf is a Power-Up that contributes a new **board type**: instead of lists and
cards, a Shelf board is a file explorer with collaborative markdown notes and
file storage — while inheriting everything a board already has (per-board
members and roles, pinning, cross-workspace moves, the board switcher).

## Features

- **Explorer tree** — folders, notes and files: inline create/rename, drag &
  drop (with cycle guard), context menus, live updates for every viewer.
- **Markdown notes (TipTap)** — autosave, slash commands (`/`), tables,
  **realtime co-editing with named cursors** (Yjs CRDT synced over the host's
  Reverb websocket — no extra server), automatic revisions every 10 minutes of
  editing with a diff/restore panel.
- **Files** — multi-file dropzone (200 MB per file), inline previews (images
  via lightbox, PDF viewer, video/audio players), authorized streaming with
  hardened headers.
- **Quota** — instance-wide default (admin setting) with a per-board override;
  notes, revisions and files all count.
- **Trash** — soft delete with restore; permanent purge after 30 days.
- **Imports/exports** — drop `.md`/`.txt` to import as editable notes, unpack
  a `.zip` into a tree, export any note as raw markdown. No external binaries
  required, ever.
- **Search** — names and note contents, with snippets.
- **MCP tools** — list the tree, read/write/create/move notes and folders from
  an AI agent.
- **Activity log** — tree mutations journaled through the host activity system.

## Installation

Through the Board **Marketplace** (recommended — runtime install, no rebuild),
or via Composer:

```bash
composer require board/plugin-shelf
php artisan migrate && php artisan optimize:clear
```

Requires `board/plugin-sdk ^0.2.8` (plugin contract 1) and a Board host with
plugin-typed boards (Board ≥ the Shelf phase 0 release).

## Usage

Once the plugin is active, the dashboard's board creation form offers a type
selector (**Board / Shelf**). A Shelf board opens on `/shelf/{board}`.

## License

MIT
