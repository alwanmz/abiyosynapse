import { type Auth } from '@/types';

export function userHasPermission(auth: Auth | undefined, permission: string): boolean {
    const role = auth?.role;
    const roleName = role?.name ?? '';

    if (roleName === 'super_admin' || roleName === 'admin') {
        return true;
    }

    return role?.permissions?.some((item) => item.name === permission) ?? false;
}
