import { useEffect } from 'react';
import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { fromEditor } from '../builder/richTextBody';

export default function InlineRichTextEditor({ value, onChange }) {
    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                heading: false,
                codeBlock: false,
                blockquote: false,
                horizontalRule: false,
                link: {
                    openOnClick: false,
                    autolink: true,
                    defaultProtocol: 'https',
                },
            }),
        ],
        content: value || '',
        editorProps: {
            attributes: {
                class: 'cms-rt__surface cms-rt__surface--inline',
                'aria-label': 'Text',
            },
        },
        onUpdate: ({ editor }) => onChange(fromEditor(editor)),
    });

    useEffect(() => {
        if (! editor || editor.isDestroyed) return;

        const incoming = value || '';

        if (incoming !== fromEditor(editor)) {
            editor.chain().setMeta('addToHistory', false).setContent(incoming, { emitUpdate: false }).run();
        }
    }, [editor, value]);

    if (! editor) {
        return <div className="cms-rt"><div className="cms-rt__surface cms-rt__surface--inline">Loading the editor…</div></div>;
    }

    const on = (check) => (editor.isActive(check) ? ' cms-rt__tool--on' : '');

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

                <span className="cms-rt__divider" />

                <button type="button" className={`cms-rt__tool${on('link')}`} onClick={addLink}>
                    Link
                </button>
            </div>

            <EditorContent editor={editor} />
        </div>
    );
}
