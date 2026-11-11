<?php
// +----------------------------------------------------------------------
// | MKSHOP 上传工具（红线：白名单收紧，随机文件名，禁执行目录策略由 nginx 承担）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace mks\util;

use think\File;
use think\facade\Config;

class Upload
{
    /**
     * 保存上传文件
     * @param File  $file 上传文件对象
     * @param string $dir 业务目录（如 goods/avatar）
     * @return array{url:string, path:string, name:string, size:int}
     */
    public static function save(File $file, string $dir = 'file'): array
    {
        $ext      = strtolower((string) $file->getOriginalExtension());
        $allowExt = array_map('trim', explode(',', (string) Config::get('mks.upload.image_ext') . ',' . Config::get('mks.upload.file_ext')));
        if (!in_array($ext, $allowExt, true)) {
            throw_fail('不允许的文件类型: ' . $ext, 422);
        }
        $maxSize = (int) Config::get('mks.upload.max_size', 52428800);
        if ($file->getSize() > $maxSize) {
            throw_fail('文件超过大小限制', 422);
        }

        // 目录：public/uploads/{dir}/{Ymd}
        $base = app()->getRootPath() . 'public' . DIRECTORY_SEPARATOR . 'uploads';
        $sub  = trim(str_replace(['..', '\\'], ['', '/'], $dir), '/.');
        $path = $base . DIRECTORY_SEPARATOR . ($sub !== '' ? $sub . DIRECTORY_SEPARATOR : '') . date('Ymd');
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $file->move($path, $name);

        $rel    = '/uploads/' . ($sub !== '' ? $sub . '/' : '') . date('Ymd') . '/' . $name;
        return [
            'url'  => $rel,
            'path' => $rel,
            'name' => $file->getOriginalName(),
            'size' => (int) $file->getSize(),
        ];
    }
}
