@assets
<script>
(() => {
    // Shelf's TipTap extension set, namespaced in the host registry: other
    // plugins register their own key and never interfere — an editor only
    // gets the sets it explicitly asks for (extensionsFor).
    const registerExtensions = () => window.boardTiptap?.register('shelf', ({ tables }) => [
        tables.Table.configure({ resizable: false }),
        tables.TableRow,
        tables.TableHeader,
        tables.TableCell,
    ])

    // The TipTap instance lives on the DOM element (NOT the Alpine object):
    // Alpine's reactive Proxy breaks ProseMirror's transaction identity checks.
    const register = () => window.Alpine.data('shelfNoteEditor', (opts) => ({
        status: 'idle', // idle | dirty | saving | saved | conflict | quota
        version: opts.version,
        savedAt: null,
        canWrite: opts.canWrite,
        i18n: opts.i18n,
        members: [],
        typing: {},
        slash: { open: false, query: '', index: 0, x: 0, y: 0, from: 0 },
        _timer: null,
        _whisperAt: 0,
        _server: null,

        async init() {
            if (! window.boardTiptap) {
                return
            }

            const { Editor, StarterKit, Markdown } = await window.boardTiptap.load()
            const extra = await window.boardTiptap.extensionsFor('shelf')
            const mount = this.$root.querySelector('.js-note-mount')

            this.$root._tiptap = new Editor({
                element: mount,
                editable: this.canWrite,
                extensions: [
                    StarterKit.configure({ heading: { levels: [1, 2, 3] } }),
                    Markdown.configure({ html: false, linkify: true, breaks: true, transformPastedText: true }),
                    ...extra,
                ],
                content: opts.markdown,
                editorProps: {
                    attributes: {
                        class: 'tiptap markdown mx-auto min-h-full max-w-3xl px-6 py-4 text-sm focus:outline-none',
                    },
                    handleKeyDown: (view, event) => this.onKeyDown(event),
                },
                onUpdate: () => this.onUpdate(),
            })

            if (window.Echo) {
                this._channel = window.Echo.join('shelf-note.' + opts.nodeId)
                    .here((users) => this.members = users)
                    .joining((user) => this.members.push(user))
                    .leaving((user) => this.members = this.members.filter((m) => m.id !== user.id))
                    .listenForWhisper('editing', (e) => {
                        this.typing[e.id] = { name: e.name, at: Date.now() }
                    })
            }

            // Prune stale "is typing" marks (whispers every 2.5 s, expiry 6 s).
            this._prune = setInterval(() => {
                const now = Date.now()
                for (const id of Object.keys(this.typing)) {
                    if (now - this.typing[id].at > 6000) {
                        delete this.typing[id]
                    }
                }
            }, 2000)

            window.addEventListener('shelf-note-restored', this._onRestore = (event) => {
                const detail = event.detail ?? {}
                if (detail.nodeId !== opts.nodeId) {
                    return
                }
                this.editor()?.commands.setContent(detail.markdown)
                this.version = detail.version
                this.status = 'saved'
                this.savedAt = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
            })
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

        // --- Autosave -------------------------------------------------------

        onUpdate() {
            this.updateSlash()

            if (! this.canWrite || this.status === 'conflict') {
                return
            }

            this.status = 'dirty'
            clearTimeout(this._timer)
            this._timer = setTimeout(() => this.save(), 1200)

            const now = Date.now()
            if (this._channel && now - this._whisperAt > 2500) {
                this._whisperAt = now
                this._channel.whisper('editing', { id: opts.userId, name: '{{ addslashes(auth()->user()->name) }}' })
            }
        },

        async save() {
            const editor = this.editor()
            if (! editor || ! this.canWrite || this.status === 'conflict') {
                return
            }

            this.status = 'saving'
            const markdown = editor.storage.markdown.getMarkdown()
            const result = await this.$wire.saveNote(opts.nodeId, markdown, this.version)

            if (result.ok) {
                this.version = result.version
                this.status = 'saved'
                this.savedAt = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
            } else if (result.reason === 'conflict') {
                this._server = result
                this.status = 'conflict'
            } else if (result.reason === 'quota') {
                this.status = 'quota'
            }
        },

        async reload() {
            const server = this._server ?? await this.$wire.reloadNote(opts.nodeId)
            this.editor()?.commands.setContent(server.markdown)
            this.version = server.version
            this._server = null
            this.status = 'saved'
            this.savedAt = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        },

        overwrite() {
            if (this._server) {
                this.version = this._server.version
                this._server = null
            }
            this.status = 'dirty'
            this.save()
        },

        // --- Toolbar --------------------------------------------------------

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

        // --- Slash commands ---------------------------------------------------

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

            if (window.Echo && this._channel) {
                window.Echo.leave('shelf-note.' + opts.nodeId)
            }

            this.editor()?.destroy()
            this.$root._tiptap = null
        },
    }))

    registerExtensions()

    if (window.Alpine) {
        register()
    } else {
        document.addEventListener('alpine:init', register)
    }
})()
</script>
@endassets
