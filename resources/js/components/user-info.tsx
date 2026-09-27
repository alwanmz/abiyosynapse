import { UserAvatar } from '@/components/user-avatar';
import { type User } from '@/types';

export function UserInfo({
    user,
    showEmail = false,
}: {
    user: User;
    showEmail?: boolean;
}) {
    const username = typeof user.username === 'string' ? user.username : null;
    const handleTag = username
        ? `@${username}`
        : user.email
          ? `@${user.email.split('@')[0]}`
          : '';
    const secondaryLabel = showEmail ? user.email : handleTag;

    return (
        <>
            <UserAvatar
                user={user}
                className="h-8 w-8 shrink-0"
                fallbackClassName="rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
            />
            <div className="grid flex-1 text-left text-sm leading-tight min-w-0">
                <span
                    className="line-clamp-2 break-words text-sm font-medium leading-snug"
                    title={user.name}
                >
                    {user.name}
                </span>
                {secondaryLabel && (
                    <span
                        className="truncate text-xs text-muted-foreground"
                        title={secondaryLabel}
                    >
                        {secondaryLabel}
                    </span>
                )}
            </div>
        </>
    );
}
