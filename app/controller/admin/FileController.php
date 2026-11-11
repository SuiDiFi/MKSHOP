<?php
// +----------------------------------------------------------------------
// | 后台 · 文件上传与附件管理（对齐权限点 file/*）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\admin;

use app\common\model\File;
use mks\util\Upload;

class FileController extends Base
{
    /**
     * 上传（multipart: file）
     */
    public function upload()
    {
        $file = $this->request->file('file');
        if ($file === null) {
            return $this->fail('请选择文件');
        }
        $dir   = (string) $this->request->param('dir', 'file');
        $saved = Upload::save($file, $dir);

        // 附件记录
        $row = File::create([
            'uid'       => $this->adminId(),
            'type'      => 1,
            'file_type' => str_starts_with((string) $saved['path'], '/uploads/') && preg_match('/\.(jpg|jpeg|png|gif|webp|bmp)$/i', $saved['path']) ? 1 : 3,
            'name'      => $saved['name'],
            'att_dir'   => $saved['path'],
            'satt_dir'  => $saved['path'],
            'att_size'  => (string) $saved['size'],
            'att_type'  => $file->getOriginalMime(),
            'real_name' => $saved['name'],
            'time'      => time(),
            'create_time' => time(),
            'update_time' => time(),
        ]);
        return $this->ok(['id' => $row->getAttr('id'), 'url' => $saved['url']] + $saved, '上传成功');
    }

    /**
     * 附件列表
     */
    public function index()
    {
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(100, max(1, (int) $this->request->param('limit', 30)));
        $ftype = (int) $this->request->param('file_type', 0);
        $query = File::where('is_del', 0)->order('id', 'desc');
        if ($ftype > 0) {
            $query->where('file_type', $ftype);
        }
        $total = (clone $query)->count();
        return $this->ok(['list' => $query->page($page, $limit)->select(), 'count' => $total]);
    }

    /**
     * 删除（软删 is_del）
     */
    public function delete()
    {
        $id  = (int) $this->request->param('id', 0);
        $row = File::findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('文件不存在');
        }
        $row->save(['is_del' => 1]);
        return $this->ok([], '删除成功');
    }
}
