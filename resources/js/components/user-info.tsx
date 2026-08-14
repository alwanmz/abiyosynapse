import { UserAvatar } from '@/components/user-avatar';
import { type User } from '@/types';

export function UserInfo({
    user,
    showEmail = false,
}: {
    user: User;
    showEmail?: boolean;
}) {
    const handleTag = (user as any).username
        ? `@${(user as any).username}`
        : user.email
          ? `@${user.email.split('@')[0]}`
          : '';

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
                {handleTag && (
                    <span
                        className="truncate text-xs text-muted-foreground"
                        title={handleTag}
                    >
                        {handleTag}
                    </span>
                )}
            </div>
        </>
    );
}
