<?php
// +----------------------------------------------------------------------
// | 商户服务（M6-B）：入驻申请 / 审核打标 / 商户资料
// | 红线：审核通过时「建商户 + 会员打标 + 申请状态」同事务
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\service;

use app\common\model\Member;
use app\common\model\Merchant;
use app\common\model\MerchantApply;
use think\facade\Db;

class MerchantService
{
    /**
     * 提交入驻申请（已在商户/有待审申请时拒绝）
     */
    public static function apply(int $uid, array $data): MerchantApply
    {
        $member = Member::where('id', $uid)->where('delete_time', 0)->findOrEmpty();
        if ($member->isEmpty()) {
            throw_fail('请先登录', 1);
        }
        if ((int) $member->getAttr('has_merchant') === 1) {
            throw_fail('当前账号已是商户', 1);
        }
        $pending = MerchantApply::where('uid', $uid)->where('status', MerchantApply::ST_WAIT)->count();
        if ($pending > 0) {
            throw_fail('已有待审核的入驻申请，请耐心等待', 1);
        }
        $name    = mb_substr(trim((string) ($data['merchant_name'] ?? '')), 0, 100, 'UTF-8');
        $contact = mb_substr(trim((string) ($data['contact'] ?? '')), 0, 50, 'UTF-8');
        $mobile  = mb_substr(trim((string) ($data['mobile'] ?? '')), 0, 30, 'UTF-8');
        if ($name === '' || $contact === '' || $mobile === '') {
            throw_fail('请填写店铺名、联系人和联系电话', 1);
        }
        return MerchantApply::create([
            'uid'          => $uid,
            'merchant_name' => $name,
            'contact'      => $contact,
            'mobile'       => $mobile,
            'intro'        => mb_substr(trim((string) ($data['intro'] ?? '')), 0, 1000, 'UTF-8'),
            'license_img'  => mb_substr(trim((string) ($data['license_img'] ?? '')), 0, 500, 'UTF-8'),
            'status'       => MerchantApply::ST_WAIT,
            'create_time'  => time(),
            'update_time'  => time(),
        ]);
    }

    /**
     * 审核入驻申请（仅待审核可处理；通过则建商户并给会员打标）
     */
    public static function audit(int $id, int $status, int $adminId, string $adminName, string $mark = ''): void
    {
        if (!in_array($status, [MerchantApply::ST_APPROVED, MerchantApply::ST_REJECTED], true)) {
            throw_fail('审核状态错误', 1);
        }
        Db::startTrans();
        try {
            $apply = MerchantApply::lock(true)->findOrEmpty($id);
            if ($apply->isEmpty()) {
                throw_fail('入驻申请不存在', 1);
            }
            if ((int) $apply->getAttr('status') !== MerchantApply::ST_WAIT) {
                throw_fail('该申请已处理', 1);
            }
            $uid = (int) $apply->getAttr('uid');
            if ($status === MerchantApply::ST_APPROVED) {
                $member = Member::lock(true)->findOrEmpty($uid);
                if ($member->isEmpty()) {
                    throw_fail('申请人账号不存在', 1);
                }
                $merchant = Merchant::where('uid', $uid)->where('delete_time', 0)->findOrEmpty();
                if ($merchant->isEmpty()) {
                    $merchant = Merchant::create([
                        'uid'             => $uid,
                        'name'            => (string) $apply->getAttr('merchant_name'),
                        'intro'           => (string) $apply->getAttr('intro'),
                        'contact'         => (string) $apply->getAttr('contact'),
                        'mobile'          => (string) $apply->getAttr('mobile'),
                        'settlement_type' => Merchant::SETTLE_COST,
                        'status'          => 1,
                        'create_time'     => time(),
                        'update_time'     => time(),
                    ]);
                } else {
                    $merchant->save(['status' => 1, 'update_time' => time()]);
                }
                $member->force()->save([
                    'has_merchant' => 1,
                    'merchant_id'  => (int) $merchant->getAttr('id'),
                ]);
            }
            $apply->save([
                'status'         => $status,
                'audit_admin_id' => $adminId,
                'audit_time'     => time(),
                'audit_mark'     => mb_substr($mark, 0, 500, 'UTF-8'),
                'update_time'    => time(),
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 平台直接创建商户（会员必须无商户身份）
     */
    public static function createForMember(int $uid, array $data): Merchant
    {
        $member = Member::where('id', $uid)->where('delete_time', 0)->findOrEmpty();
        if ($member->isEmpty()) {
            throw_fail('会员不存在', 1);
        }
        if (Merchant::where('uid', $uid)->where('delete_time', 0)->count() > 0) {
            throw_fail('该会员已是商户', 1);
        }
        $name = mb_substr(trim((string) ($data['name'] ?? '')), 0, 100, 'UTF-8');
        if ($name === '') {
            throw_fail('请填写店铺名', 1);
        }
        Db::startTrans();
        try {
            $merchant = Merchant::create([
                'uid'             => $uid,
                'name'            => $name,
                'logo'            => mb_substr(trim((string) ($data['logo'] ?? '')), 0, 255, 'UTF-8'),
                'intro'           => mb_substr(trim((string) ($data['intro'] ?? '')), 0, 1000, 'UTF-8'),
                'contact'         => mb_substr(trim((string) ($data['contact'] ?? '')), 0, 50, 'UTF-8'),
                'mobile'          => mb_substr(trim((string) ($data['mobile'] ?? '')), 0, 30, 'UTF-8'),
                'settlement_type' => Merchant::SETTLE_COST,
                'status'          => 1,
                'create_time'     => time(),
                'update_time'     => time(),
            ]);
            $member->force()->save([
                'has_merchant' => 1,
                'merchant_id'  => (int) $merchant->getAttr('id'),
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
        return $merchant;
    }
}
