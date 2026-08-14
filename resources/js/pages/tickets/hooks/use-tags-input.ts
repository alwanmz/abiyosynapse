import { useState, KeyboardEvent } from 'react';

interface UseTagsInputProps {
    currentTags: string[];
    onTagsChange: (tags: string[]) => void;
}

export function useTagsInput({ currentTags, onTagsChange }: UseTagsInputProps) {
    const [tagInput, setTagInput] = useState('');

    const handleAddTag = () => {
        const trimmedTag = tagInput.trim();
        if (trimmedTag && !currentTags.includes(trimmedTag)) {
            onTagsChange([...currentTags, trimmedTag]);
            setTagInput('');
        }
    };

    const handleTagKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            handleAddTag();
        }
    };

    const removeTag = (tagToRemove: string) => {
        onTagsChange(currentTags.filter((tag) => tag !== tagToRemove));
    };

    return {
        tagInput,
        setTagInput,
        handleAddTag,
        handleTagKeyDown,
        removeTag,
    };
}
