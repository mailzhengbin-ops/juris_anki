import { useCallback } from 'react';

export type GetInitialsFn = (fullName: string) => string;

function getInitial(name: string): string {
    return Array.from(name)[0] ?? '';
}

/**
 * 取姓名首字母：单字取首字符，多字取首尾字符。
 */
export function getInitials(fullName: string): string {
    const names = fullName.trim().split(/\s+/u).filter(Boolean);

    if (names.length === 0) {
        return '';
    }

    if (names.length === 1) {
        return getInitial(names[0]).toUpperCase();
    }

    const firstInitial = getInitial(names[0]);
    const lastInitial = getInitial(names[names.length - 1]);

    return `${firstInitial}${lastInitial}`.toUpperCase();
}

export function useInitials(): GetInitialsFn {
    return useCallback((fullName: string) => getInitials(fullName), []);
}
