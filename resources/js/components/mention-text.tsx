import React from 'react';

interface MentionTextProps {
    text: string;
    users?: Array<{ id: number; name: string }>;
    className?: string;
}

export function MentionText({ text, users = [], className = '' }: MentionTextProps) {
    if (!text) return null;

    // Build regex from user names or standard @mention pattern
    // e.g. @[User Name] or @Name
    const parts: Array<string | React.ReactNode> = [];
    
    // Sort names by length descending so longer matches match first
    const sortedUserNames = [...users.map((u) => u.name)]
        .filter(Boolean)
        .sort((a, b) => b.length - a.length);

    // Escape special regex characters in names
    const escapeRegex = (str: string) => str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

    let patternString = '@[a-zA-Z0-9_.-]+';
    if (sortedUserNames.length > 0) {
        const namesPattern = sortedUserNames.map(escapeRegex).join('|');
        patternString = `@(?:${namesPattern}|[a-zA-Z0-9_.-]+)`;
    }

    const regex = new RegExp(`(${patternString})`, 'gi');
    const splitParts = text.split(regex);

    return (
        <span className={`whitespace-pre-wrap ${className}`}>
            {splitParts.map((part, index) => {
                if (part.startsWith('@')) {
                    return (
                        <span
                            key={index}
                            className="inline-flex items-center rounded-md bg-blue-500/10 px-1.5 py-0.5 text-xs font-semibold text-blue-600 dark:bg-blue-400/15 dark:text-blue-400"
                        >
                            {part}
                        </span>
                    );
                }
                return part;
            })}
        </span>
    );
}
