import { describe, expect, it } from 'vitest';
import { centerCropRect } from './avatar';

describe('centerCropRect', () => {
    it('正方形图片取全图', () => {
        expect(centerCropRect(256, 256)).toEqual({
            sx: 0,
            sy: 0,
            sWidth: 256,
            sHeight: 256,
        });
    });

    it('横图取中部等宽正方形', () => {
        expect(centerCropRect(400, 300)).toEqual({
            sx: 50,
            sy: 0,
            sWidth: 300,
            sHeight: 300,
        });
    });

    it('竖图取中部等高正方形', () => {
        expect(centerCropRect(200, 500)).toEqual({
            sx: 0,
            sy: 150,
            sWidth: 200,
            sHeight: 200,
        });
    });

    it('奇数差值时向下取整', () => {
        expect(centerCropRect(401, 300)).toEqual({
            sx: 50,
            sy: 0,
            sWidth: 300,
            sHeight: 300,
        });
    });
});
