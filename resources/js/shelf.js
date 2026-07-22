/**
 * Shelf note editor — the Alpine 'shelfNoteEditor' component and the plugin's
 * TipTap extension registration. Uses host globals (window.Alpine,
 * window.boardTiptap, window.Echo); it never bundles its own copy of them.
 *
 * Shipped as a pre-built asset (this file IS the build output — no bundler
 * needed, it only uses browser + host globals) and served by the host via
 * ProvidesAssets. Kept in resources/js as the maintained source.
 */
(() => {
    // Shelf's TipTap extension set, namespaced in the host registry: other
    // plugins register their own key and never interfere — an editor only
    // gets the sets it explicitly asks for (extensionsFor).
    //
    // Registered from init() (not at module load): the script runs whenever the
    // host injects it, possibly BEFORE window.boardTiptap exists — a load-time
    // register() would then no-op and the tables extension would be missing.
    // Registering inside init(), after boardTiptap.load() resolves, guarantees
    // the registry is present; register() is idempotent so re-mounts are free.
    const registerShelfExtensions = () => window.boardTiptap.register('shelf', ({ tables }) => [
        tables.Table.configure({ resizable: false }),
        tables.TableRow,
        tables.TableHeader,
        tables.TableCell,
    ])

    const COLORS = ['#f97316', '#8b5cf6', '#06b6d4', '#10b981', '#ef4444', '#eab308', '#ec4899', '#3b82f6']

    const b64encode = (u8) => {
        let s = ''
        u8.forEach((b) => s += String.fromCharCode(b))
        return btoa(s)
    }
    const b64decode = (s) => Uint8Array.from(atob(s), (c) => c.charCodeAt(0))

    // Reverb caps client events (~10 KB): payloads above the threshold are
    // split into ordered chunks and reassembled on the other side.
    const CHUNK = 6000

    // The TipTap/Yjs instances live on the DOM element (NOT the Alpine
    // object): Alpine's reactive Proxy breaks ProseMirror's and Yjs' identity
    // checks ("mismatched transaction").
    const register = () => window.Alpine.data('shelfNoteEditor', (opts) => ({
        status: 'idle', // idle | dirty | saving | saved | conflict | quota
        version: opts.version,
        savedAt: null,
        canWrite: opts.canWrite,
        i18n: opts.i18n,
        collab: false,
        members: [],
        typing: {},
        slash: { open: false, query: '', index: 0, x: 0, y: 0, from: 0 },
        _timer: null,
        _whisperAt: 0,
        _server: null,
        _lastSavedMd: opts.markdown,
        _seeded: false,
        _synced: false,
        _seeding: false,
        _chunks: {},

        async init() {
            if (! window.boardTiptap) {
                return
            }

            const mods = await window.boardTiptap.load()
            registerShelfExtensions()
            const extra = await window.boardTiptap.extensionsFor('shelf')
            const mount = this.$root.querySelector('.js-note-mount')

            // Collaborative whenever Echo is up: the CRDT syncs over the
            // note's presence channel (Reverb whispers) — no dedicated server.
            this.collab = !! window.Echo

            const extensions = [
                mods.StarterKit.configure({
                    heading: { levels: [1, 2, 3] },
                    ...(this.collab ? { undoRedo: false } : {}),
                }),
                mods.Markdown.configure({ html: false, linkify: true, breaks: true, transformPastedText: true }),
                ...extra,
            ]

            if (this.collab) {
                const { Y, Awareness, Collaboration, CollaborationCaret } = mods.collab
                const ydoc = new Y.Doc()
                const awareness = new Awareness(ydoc)

                this.$root._y = { ydoc, awareness, mods: mods.collab }

                extensions.push(Collaboration.configure({ document: ydoc }))
                extensions.push(CollaborationCaret.configure({
                    provider: { awareness },
                    user: { name: opts.userName, color: COLORS[opts.userId % COLORS.length] },
                }))
            }

            this.$root._tiptap = new mods.Editor({
                element: mount,
                editable: this.canWrite,
                extensions,
                // In collab mode content comes from seeding/peer sync — never
                // from the Editor constructor (it would bypass the CRDT).
                ...(this.collab ? {} : { content: opts.markdown }),
                editorProps: {
                    attributes: {
                        class: 'tiptap markdown mx-auto min-h-full max-w-3xl px-6 py-4 text-sm focus:outline-none',
                    },
                    handleKeyDown: (view, event) => this.onKeyDown(event),
                },
                onUpdate: () => this.onUpdate(),
            })

            if (this.collab) {
                this.joinCollab()
            }

            window.addEventListener('shelf-note-restored', this._onRestore = (event) => {
                const detail = event.detail ?? {}
                if (detail.nodeId !== opts.nodeId) {
                    return
                }
                this._seeding = true
                this.editor()?.commands.setContent(detail.markdown)
                this._seeding = false
                this.version = detail.version
                this._lastSavedMd = detail.markdown
                this.markSaved()
                this.sendJson('shelf-saved', { version: detail.version })
            })

            this._prune = setInterval(() => {
                const now = Date.now()
                for (const id of Object.keys(this.typing)) {
                    if (now - this.typing[id].at > 6000) {
                        delete this.typing[id]
                    }
                }
            }, 2000)
        },

        editor() {
            return this.$root._tiptap ?? null
        },

        others() {
            return this.members.filter((m) => m.id !== opts.userId)
        },

        editingNames() {
            return Object.entries(this.typing)
                .filter(([id]) => Number(id) !== opts.userId)
                .map(([, entry]) => entry.name)
        },

        markSaved() {
            this.status = 'saved'
            this.savedAt = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        },

        // --- Collaboration over the presence channel -------------------------

        joinCollab() {
            const { ydoc, awareness, mods } = this.$root._y

            const bin = (type, handler) => {
                this._channel.listenForWhisper(type, (e) => handler(e))
                this._channel.listenForWhisper(type + '-chunk', (e) => {
                    const box = (this._chunks[e.id] ??= { head: e.head, parts: [], got: 0 })
                    if (box.parts[e.seq] === undefined) {
                        box.parts[e.seq] = e.part
                        box.got++
                    }
                    if (box.got === e.total) {
                        delete this._chunks[e.id]
                        handler({ ...box.head, data: box.parts.join('') })
                    }
                })
            }

            this._channel = window.Echo.join('shelf-note.' + opts.nodeId)
                .here((users) => {
                    this.members = users
                    this.startSync()
                })
                .joining((user) => this.members.push(user))
                .leaving((user) => this.members = this.members.filter((m) => m.id !== user.id))
                .listenForWhisper('editing', (e) => {
                    this.typing[e.id] = { name: e.name, at: Date.now() }
                })
                .listenForWhisper('shelf-sync-req', (e) => this.onSyncRequest(e))
                .listenForWhisper('shelf-saved', (e) => {
                    this.version = Math.max(this.version, e.version)
                    this._lastSavedMd = this.editor()?.storage.markdown.getMarkdown() ?? this._lastSavedMd
                    if (this.status === 'dirty' || this.status === 'saving') {
                        this.markSaved()
                    }
                })

            bin('shelf-update', (e) => this.applyRemote(e))
            bin('shelf-reply', (e) => {
                if (e.to !== opts.userId) {
                    return
                }
                this.applyRemote(e)
                this._synced = true
            })
            bin('shelf-awareness', (e) => mods.applyAwarenessUpdate(awareness, b64decode(e.data), 'remote'))

            ydoc.on('update', (update, origin) => {
                if (origin !== 'remote') {
                    this.sendBinary('shelf-update', update)
                }
            })

            awareness.on('update', ({ added, updated, removed }, origin) => {
                if (origin === 'remote') {
                    return
                }
                const ids = added.concat(updated).concat(removed)
                this.sendBinary('shelf-awareness', mods.encodeAwarenessUpdate(awareness, ids))
            })
        },

        startSync() {
            const { ydoc, mods } = this.$root._y
            const Y = mods.Y

            if (this.others().length === 0) {
                this.seed()

                return
            }

            this.sendJson('shelf-sync-req', { from: opts.userId, sv: b64encode(Y.encodeStateVector(ydoc)) })

            // Nobody answered with state (all peers empty too): the lowest
            // user id seeds from the stored markdown, everyone else converges.
            setTimeout(() => {
                if (this._synced || this._seeded) {
                    return
                }
                const ids = this.members.map((m) => m.id)
                if (ids.length === 0 || opts.userId === Math.min(...ids)) {
                    this.seed()
                }
            }, 2500)
        },

        onSyncRequest(e) {
            if (e.from === opts.userId || (! this._seeded && ! this._synced)) {
                return
            }

            const { ydoc, awareness, mods } = this.$root._y
            const update = mods.Y.encodeStateAsUpdate(ydoc, b64decode(e.sv))

            this.sendBinary('shelf-reply', update, { to: e.from })
            this.sendBinary('shelf-awareness', mods.encodeAwarenessUpdate(awareness, [awareness.clientID]))
        },

        applyRemote(e) {
            const { ydoc, mods } = this.$root._y

            mods.Y.applyUpdate(ydoc, b64decode(e.data), 'remote')
        },

        seed() {
            if (this._seeded || this._synced) {
                return
            }

            this._seeded = true

            if (opts.markdown) {
                this._seeding = true
                this.editor()?.commands.setContent(opts.markdown)
                this._seeding = false
            }
        },

        sendBinary(type, u8, meta = {}) {
            this.sendJson(type, { ...meta, data: b64encode(u8) })
        },

        sendJson(type, obj) {
            if (! this._channel) {
                return
            }

            const data = obj.data ?? ''

            if (JSON.stringify(obj).length <= CHUNK + 1500) {
                this._channel.whisper(type, obj)

                return
            }

            const head = { ...obj }
            delete head.data
            const id = Math.random().toString(36).slice(2)
            const total = Math.ceil(data.length / CHUNK)

            for (let i = 0; i < total; i++) {
                this._channel.whisper(type + '-chunk', {
                    id,
                    seq: i,
                    total,
                    head,
                    part: data.slice(i * CHUNK, (i + 1) * CHUNK),
                })
            }
        },

        // --- Autosave ---------------------------------------------------------

        onUpdate() {
            this.updateSlash()

            if (! this.canWrite || this._seeding || this.status === 'conflict') {
                return
            }

            this.status = 'dirty'
            clearTimeout(this._timer)
            this._timer = setTimeout(() => this.save(), 1200)

            const now = Date.now()
            if (this._channel && now - this._whisperAt > 2500) {
                this._whisperAt = now
                this._channel.whisper('editing', { id: opts.userId, name: opts.userName })
            }
        },

        async save() {
            const editor = this.editor()
            if (! editor || ! this.canWrite || this.status === 'conflict') {
                return
            }

            const markdown = editor.storage.markdown.getMarkdown()

            // A collaborator already persisted the converged content.
            if (markdown === this._lastSavedMd) {
                this.markSaved()

                return
            }

            this.status = 'saving'
            const result = await this.$wire.saveNote(opts.nodeId, markdown, this.version)

            if (result.ok) {
                this.version = result.version
                this._lastSavedMd = markdown
                this.markSaved()
                this.sendJson('shelf-saved', { version: result.version })
            } else if (result.reason === 'conflict') {
                if (this.collab && this.others().length > 0) {
                    // CRDT peers converge on content; just adopt the server
                    // version and persist the shared truth.
                    this.version = result.version
                    if (result.markdown === markdown) {
                        this._lastSavedMd = markdown
                        this.markSaved()
                    } else {
                        this.status = 'dirty'
                        clearTimeout(this._timer)
                        this._timer = setTimeout(() => this.save(), 400)
                    }
                } else {
                    this._server = result
                    this.status = 'conflict'
                }
            } else if (result.reason === 'quota') {
                this.status = 'quota'
            }
        },

        async reload() {
            const server = this._server ?? await this.$wire.reloadNote(opts.nodeId)
            this._seeding = true
            this.editor()?.commands.setContent(server.markdown)
            this._seeding = false
            this.version = server.version
            this._lastSavedMd = server.markdown
            this._server = null
            this.markSaved()
        },

        overwrite() {
            if (this._server) {
                this.version = this._server.version
                this._server = null
            }
            this.status = 'dirty'
            this.save()
        },

        // --- Toolbar ----------------------------------------------------------

        run(command, ...args) {
            this.editor()?.chain().focus()[command](...args).run()
        },

        isActive(name, attrs = {}) {
            return this.editor() ? this.editor().isActive(name, attrs) : false
        },

        toggleLink() {
            const editor = this.editor()
            if (! editor) {
                return
            }
            if (editor.isActive('link')) {
                editor.chain().focus().unsetLink().run()
                return
            }
            const url = window.prompt(this.i18n.linkPrompt)
            if (url) {
                editor.chain().focus().setLink({ href: url }).run()
            }
        },

        // --- Slash commands -----------------------------------------------------

        filteredSlash() {
            const query = this.slash.query.toLowerCase()
            return opts.slashItems.filter((item) =>
                item.label.toLowerCase().includes(query) || item.command.startsWith(query))
        },

        updateSlash() {
            const editor = this.editor()
            if (! editor || ! this.canWrite) {
                return
            }

            const { state } = editor
            const from = state.selection.from
            const before = state.doc.textBetween(Math.max(0, from - 50), from, '\n', '\n')
            const match = before.match(/(?:^|\s)\/([\p{L}\p{N}-]*)$/u)

            if (! match) {
                this.slash.open = false
                return
            }

            this.slash.query = match[1]
            this.slash.from = from - match[1].length - 1
            this.slash.index = 0

            const coords = editor.view.coordsAtPos(from)
            this.slash.x = Math.min(coords.left, window.innerWidth - 240)
            this.slash.y = coords.bottom + 6
            this.slash.open = this.filteredSlash().length > 0
        },

        onKeyDown(event) {
            if (! this.slash.open) {
                return false
            }

            const items = this.filteredSlash()

            if (event.key === 'ArrowDown') {
                this.slash.index = (this.slash.index + 1) % items.length
                return true
            }
            if (event.key === 'ArrowUp') {
                this.slash.index = (this.slash.index - 1 + items.length) % items.length
                return true
            }
            if (event.key === 'Enter') {
                if (items[this.slash.index]) {
                    this.applySlash(items[this.slash.index])
                }
                return true
            }
            if (event.key === 'Escape') {
                this.slash.open = false
                return true
            }

            return false
        },

        applySlash(item) {
            const editor = this.editor()
            if (! editor) {
                return
            }

            const to = editor.state.selection.from
            let chain = editor.chain().focus().deleteRange({ from: this.slash.from, to })

            switch (item.command) {
                case 'h1': chain = chain.toggleHeading({ level: 1 }); break
                case 'h2': chain = chain.toggleHeading({ level: 2 }); break
                case 'h3': chain = chain.toggleHeading({ level: 3 }); break
                case 'bullet': chain = chain.toggleBulletList(); break
                case 'ordered': chain = chain.toggleOrderedList(); break
                case 'quote': chain = chain.toggleBlockquote(); break
                case 'code': chain = chain.toggleCodeBlock(); break
                case 'hr': chain = chain.setHorizontalRule(); break
                case 'table': chain = chain.insertTable({ rows: 3, cols: 3, withHeaderRow: true }); break
            }

            chain.run()
            this.slash.open = false
        },

        destroy() {
            clearTimeout(this._timer)
            clearInterval(this._prune)
            window.removeEventListener('shelf-note-restored', this._onRestore)

            if (this.$root._y) {
                this.$root._y.awareness.destroy()
                this.$root._y.ydoc.destroy()
                this.$root._y = null
            }

            if (window.Echo && this._channel) {
                window.Echo.leave('shelf-note.' + opts.nodeId)
            }

            this.editor()?.destroy()
            this.$root._tiptap = null
        },
    }))

    if (window.Alpine) {
        register()
    } else {
        document.addEventListener('alpine:init', register)
    }
})()
