import { Toggle } from '@/components/ui/toggle';
import { cn } from '@/lib/utils';
import Link from '@tiptap/extension-link';
import { EditorContent, useEditor, type Editor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import DOMPurify from 'dompurify';
import {
    Bold,
    Heading2,
    Heading3,
    Italic,
    Link2,
    List,
    ListOrdered,
    Quote,
    Redo2,
    Strikethrough,
    Undo2,
} from 'lucide-react';
import { useEffect, useMemo } from 'react';

/**
 * Tiptap-based WYSIWYG editor used by the `native` guidebook content type.
 * Stores HTML so the read-only viewer can render it without a second parse.
 */

const editorProseClasses =
    'prose prose-sm dark:prose-invert max-w-none focus:outline-none ' +
    'prose-headings:font-semibold prose-a:text-primary prose-p:leading-relaxed';

function ToolbarButton({
    active,
    disabled,
    label,
    onClick,
    children,
}: {
    active?: boolean;
    disabled?: boolean;
    label: string;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <Toggle
            size="sm"
            pressed={active}
            disabled={disabled}
            title={label}
            aria-label={label}
            onPressedChange={onClick}
            className="h-8 w-8 p-0"
        >
            {children}
        </Toggle>
    );
}

function Toolbar({ editor }: { editor: Editor }) {
    const promptForLink = () => {
        const previous = editor.getAttributes('link').href as string | undefined;
        const href = window.prompt('URL tautan', previous ?? 'https://');

        if (href === null) return;
        if (href === '') {
            editor.chain().focus().extendMarkRange('link').unsetLink().run();
            return;
        }

        editor.chain().focus().extendMarkRange('link').setLink({ href }).run();
    };

    return (
        <div className="flex flex-wrap items-center gap-0.5 border-b bg-muted/40 p-1.5">
            <ToolbarButton
                label="Tebal"
                active={editor.isActive('bold')}
                onClick={() => editor.chain().focus().toggleBold().run()}
            >
                <Bold className="h-4 w-4" />
            </ToolbarButton>
            <ToolbarButton
                label="Miring"
                active={editor.isActive('italic')}
                onClick={() => editor.chain().focus().toggleItalic().run()}
            >
                <Italic className="h-4 w-4" />
            </ToolbarButton>
            <ToolbarButton
                label="Coret"
                active={editor.isActive('strike')}
                onClick={() => editor.chain().focus().toggleStrike().run()}
            >
                <Strikethrough className="h-4 w-4" />
            </ToolbarButton>

            <span className="mx-1 h-5 w-px bg-border" />

            <ToolbarButton
                label="Judul 2"
                active={editor.isActive('heading', { level: 2 })}
                onClick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()}
            >
                <Heading2 className="h-4 w-4" />
            </ToolbarButton>
            <ToolbarButton
                label="Judul 3"
                active={editor.isActive('heading', { level: 3 })}
                onClick={() => editor.chain().focus().toggleHeading({ level: 3 }).run()}
            >
                <Heading3 className="h-4 w-4" />
            </ToolbarButton>

            <span className="mx-1 h-5 w-px bg-border" />

            <ToolbarButton
                label="Daftar poin"
                active={editor.isActive('bulletList')}
                onClick={() => editor.chain().focus().toggleBulletList().run()}
            >
                <List className="h-4 w-4" />
            </ToolbarButton>
            <ToolbarButton
                label="Daftar bernomor"
                active={editor.isActive('orderedList')}
                onClick={() => editor.chain().focus().toggleOrderedList().run()}
            >
                <ListOrdered className="h-4 w-4" />
            </ToolbarButton>
            <ToolbarButton
                label="Kutipan"
                active={editor.isActive('blockquote')}
                onClick={() => editor.chain().focus().toggleBlockquote().run()}
            >
                <Quote className="h-4 w-4" />
            </ToolbarButton>
            <ToolbarButton label="Tautan" active={editor.isActive('link')} onClick={promptForLink}>
                <Link2 className="h-4 w-4" />
            </ToolbarButton>

            <span className="mx-1 h-5 w-px bg-border" />

            <ToolbarButton
                label="Urungkan"
                disabled={!editor.can().undo()}
                onClick={() => editor.chain().focus().undo().run()}
            >
                <Undo2 className="h-4 w-4" />
            </ToolbarButton>
            <ToolbarButton
                label="Ulangi"
                disabled={!editor.can().redo()}
                onClick={() => editor.chain().focus().redo().run()}
            >
                <Redo2 className="h-4 w-4" />
            </ToolbarButton>
        </div>
    );
}

export function RichTextEditor({
    value,
    onChange,
    placeholder,
    className,
}: {
    value: string;
    onChange: (html: string) => void;
    placeholder?: string;
    className?: string;
}) {
    const editor = useEditor({
        extensions: [
            StarterKit,
            Link.configure({ openOnClick: false, autolink: true }),
        ],
        content: value,
        editorProps: {
            attributes: {
                class: cn(editorProseClasses, 'min-h-[220px] px-3 py-2'),
                'data-placeholder': placeholder ?? '',
            },
        },
        onUpdate: ({ editor }) => onChange(editor.getHTML()),
        immediatelyRender: false,
    });

    // Menyinkronkan konten saat form direset atau dimuat ulang dari server,
    // tanpa mengganggu pengetikan yang sedang berjalan.
    useEffect(() => {
        if (editor && !editor.isFocused && value !== editor.getHTML()) {
            editor.commands.setContent(value || '', { emitUpdate: false });
        }
    }, [editor, value]);

    if (!editor) return null;

    return (
        <div className={cn('overflow-hidden rounded-md border bg-background', className)}>
            <Toolbar editor={editor} />
            <EditorContent editor={editor} />
        </div>
    );
}

/**
 * Read-only renderer for content produced by RichTextEditor. The stored HTML is
 * always sanitised before rendering — an editor with a link button is enough of
 * an injection surface that we never trust the column contents verbatim.
 */
export function RichTextViewer({ html, className }: { html: string; className?: string }) {
    const clean = useMemo(
        () =>
            DOMPurify.sanitize(html ?? '', {
                USE_PROFILES: { html: true },
                ADD_ATTR: ['target', 'rel'],
            }),
        [html],
    );

    return (
        <div
            className={cn(editorProseClasses, 'prose-base', className)}
            dangerouslySetInnerHTML={{ __html: clean }}
        />
    );
}
