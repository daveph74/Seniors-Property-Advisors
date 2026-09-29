const escape = (text) => text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

export function toEditorHtml(body) {
    const text = String(body || '');

    if (text.includes('<')) return text;

    return text
        .split(/\n{2,}/)
        .map((p) => p.trim())
        .filter(Boolean)
        .map((p) => `<p>${escape(p).replace(/\n/g, '<br>')}</p>`)
        .join('');
}

export function fromEditor(editor) {
    return editor.isEmpty ? '' : editor.getHTML();
}
