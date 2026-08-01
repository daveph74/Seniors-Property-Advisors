import { useEffect } from 'react';
import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import { Table, TableCell, TableHeader, TableRow } from '@tiptap/extension-table';

/**
 * A what-you-see editor, because the people writing these articles are not typing markup.
 * Bold looks bold. The toolbar carries words as well as symbols, and only the formats scope
 * §5 asks for — there is no source view, since §17 excludes editing raw HTML.
 *
 * Whatever this produces is purified server-side by App\Content\Html before it is stored,
 * so the allowlist there, not this configuration, is what keeps a reader safe.
 */
export default function RichTextEditor({ value, onChange, onPickImage }) {
    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3, 4] },
                codeBlock: false,
                link: {
                    openOnClick: false,
                    autolink: true,
                    defaultProtocol: 'https',
                },
            }),
            Image.configure({ inline: false }),
            Table.configure({ resizable: false }),
            TableRow,
            TableHeader,
            TableCell,
        ],
        content: value || '',
        editorProps: {
            attributes: {
                class: 'cms-rt__surface',
                'aria-label': 'Article content',
            },
        },
        onUpdate: ({ editor }) => onChange(editor.getHTML()),
    });

    /**
     * Only reset the document when the incoming value is a different article — writing
     * `value` back on every keystroke would fight the cursor.
     */
    useEffect(() => {
        if (! editor) return;

        const incoming = value || '';

        if (incoming !== editor.getHTML()) {
            editor.commands.setContent(incoming, { emitUpdate: false });
        }
    }, [editor, value]);

    if (! editor) {
        return <div className="cms-rt"><div className="cms-rt__surface">Loading the editor…</div></div>;
    }

    const on = (check, args) => (editor.isActive(check, args) ? ' cms-rt__tool--on' : '');

    const addLink = () => {
        const existing = editor.getAttributes('link').href || '';
        const href = window.prompt('Web address to link to', existing || 'https://');

        if (href === null) return;

        if (href.trim() === '') {
            editor.chain().focus().unsetLink().run();

            return;
        }

        editor.chain().focus().extendMarkRange('link').setLink({ href: href.trim() }).run();
    };

    return (
        <div className="cms-rt">
            <div className="cms-rt__bar">
                <select
                    className="cms-rt__style"
                    value={
                        editor.isActive('heading', { level: 2 }) ? 'h2'
                            : editor.isActive('heading', { level: 3 }) ? 'h3'
                                : editor.isActive('heading', { level: 4 }) ? 'h4'
                                    : 'p'
                    }
                    onChange={(e) => {
                        const choice = e.target.value;

                        if (choice === 'p') {
                            editor.chain().focus().setParagraph().run();

                            return;
                        }

                        editor.chain().focus().setHeading({ level: Number(choice.slice(1)) }).run();
                    }}
                >
                    <option value="p">Normal text</option>
                    <option value="h2">Big heading</option>
                    <option value="h3">Smaller heading</option>
                    <option value="h4">Small heading</option>
                </select>

                <span className="cms-rt__divider" />

                <button
                    type="button"
                    className={`cms-rt__tool${on('bold')}`}
                    style={{ fontWeight: 700 }}
                    title="Bold"
                    onClick={() => editor.chain().focus().toggleBold().run()}
                >
                    B
                </button>
                <button
                    type="button"
                    className={`cms-rt__tool${on('italic')}`}
                    style={{ fontStyle: 'italic' }}
                    title="Italic"
                    onClick={() => editor.chain().focus().toggleItalic().run()}
                >
                    I
                </button>

                <span className="cms-rt__divider" />

                <button
                    type="button"
                    className={`cms-rt__tool${on('bulletList')}`}
                    onClick={() => editor.chain().focus().toggleBulletList().run()}
                >
                    • Bullets
                </button>
                <button
                    type="button"
                    className={`cms-rt__tool${on('orderedList')}`}
                    onClick={() => editor.chain().focus().toggleOrderedList().run()}
                >
                    1. Numbers
                </button>
                <button
                    type="button"
                    className={`cms-rt__tool${on('blockquote')}`}
                    onClick={() => editor.chain().focus().toggleBlockquote().run()}
                >
                    “ ” Quote
                </button>

                <span className="cms-rt__divider" />

                <button type="button" className={`cms-rt__tool${on('link')}`} onClick={addLink}>
                    Link
                </button>
                <button
                    type="button"
                    className="cms-rt__tool"
                    onClick={() => onPickImage?.((src) => editor.chain().focus().setImage({ src }).run())}
                >
                    Picture
                </button>
                <button
                    type="button"
                    className="cms-rt__tool"
                    onClick={() => editor.chain().focus()
                        .insertTable({ rows: 3, cols: 2, withHeaderRow: true }).run()}
                >
                    Table
                </button>

                {editor.isActive('table') ? (
                    <>
                        <button type="button" className="cms-rt__tool" onClick={() => editor.chain().focus().addRowAfter().run()}>
                            + Row
                        </button>
                        <button type="button" className="cms-rt__tool" onClick={() => editor.chain().focus().addColumnAfter().run()}>
                            + Column
                        </button>
                        <button type="button" className="cms-rt__tool" onClick={() => editor.chain().focus().deleteTable().run()}>
                            Remove table
                        </button>
                    </>
                ) : null}

                <span className="cms-rt__spacer" />

                <button
                    type="button"
                    className="cms-rt__tool"
                    title="Undo"
                    disabled={! editor.can().undo()}
                    onClick={() => editor.chain().focus().undo().run()}
                >
                    Undo
                </button>
                <button
                    type="button"
                    className="cms-rt__tool"
                    title="Redo"
                    disabled={! editor.can().redo()}
                    onClick={() => editor.chain().focus().redo().run()}
                >
                    Redo
                </button>
            </div>

            <EditorContent editor={editor} />
        </div>
    );
}
