import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    formatRelativeTime,
    progressPercent,
    RATINGS,
    RATING_INFO,
} from './recitation';

describe('formatRelativeTime', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-08-29T12:00:00Z'));
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('返回「刚刚」当时间差不足一分钟', () => {
        expect(formatRelativeTime('2026-08-29T11:59:40Z')).toBe('刚刚');
    });

    it('返回 N 分钟前', () => {
        expect(formatRelativeTime('2026-08-29T11:30:00Z')).toBe('30 分钟前');
    });

    it('返回 N 小时前', () => {
        expect(formatRelativeTime('2026-08-29T09:00:00Z')).toBe('3 小时前');
    });

    it('24 小时内跨天仍算小时数', () => {
        expect(formatRelativeTime('2026-08-28T20:00:00Z')).toBe('16 小时前');
    });

    it('昨天与 N 天前', () => {
        expect(formatRelativeTime('2026-08-28T08:00:00Z')).toBe('昨天');
        expect(formatRelativeTime('2026-08-26T08:00:00Z')).toBe('3 天前');
    });
});

describe('progressPercent', () => {
    it('总数为 0 时返回 0 而非除零', () => {
        expect(progressPercent({ evaluated: 0, total: 0 })).toBe(0);
    });

    it('按已评比例四舍五入', () => {
        expect(progressPercent({ evaluated: 1, total: 2 })).toBe(50);
        expect(progressPercent({ evaluated: 1, total: 3 })).toBe(33);
        expect(progressPercent({ evaluated: 3, total: 3 })).toBe(100);
    });
});

describe('RATING_INFO', () => {
    it('三个评价档位各有展示语义', () => {
        expect(RATINGS).toEqual(['known', 'fuzzy', 'forgotten']);

        for (const rating of RATINGS) {
            expect(RATING_INFO[rating]).toMatchObject({
                label: expect.any(String),
                barClass: expect.stringContaining('bg-'),
                className: expect.stringContaining('bg-'),
            });
        }
    });

    it('标签与后端 Rating::label() 对齐（认识/模糊/忘记）', () => {
        expect(RATING_INFO.known.label).toBe('认识');
        expect(RATING_INFO.fuzzy.label).toBe('模糊');
        expect(RATING_INFO.forgotten.label).toBe('忘记');
    });
});
