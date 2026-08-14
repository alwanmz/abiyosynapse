import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { CSSProperties } from 'react';

type AvatarUser = {
    name?: string | null;
    avatar?: string | null;
    avatar_path?: string | null;
    avatar_url?: string | null;
};

interface UserAvatarProps {
    user?: AvatarUser | null;
    name?: string | null;
    className?: string;
    fallbackClassName?: string;
    fallbackStyle?: CSSProperties;
}

function storageUrl(path?: string | null): string | null {
    if (!path) return null;
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/')) {
        return path;
    }

    return `/storage/${path.replace(/^public\//, '')}`;
}

export function UserAvatar({
    user,
    name,
    className,
    fallbackClassName,
    fallbackStyle,
}: UserAvatarProps) {
    const getInitials = useInitials();
    const displayName = user?.name ?? name ?? '';
    const photoUrl = user?.avatar_url ?? user?.avatar ?? storageUrl(user?.avatar_path);

    return (
        <Avatar className={cn('overflow-hidden rounded-full', className)}>
            {photoUrl ? <AvatarImage src={photoUrl} alt={displayName} /> : null}
            <AvatarFallback
                className={cn('text-xs font-semibold', fallbackClassName)}
                style={fallbackStyle}
            >
                {displayName ? getInitials(displayName) : '?'}
            </AvatarFallback>
        </Avatar>
    );
}
