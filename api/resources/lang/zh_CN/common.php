<?php

return [
    // ========== 查詢結果 ==========
    'No data found'                       => '查无资料',
    'User not found'                      => '查无使用者',
    'Order not found'                     => '查无订单',
    'Invalid order number'                => '单号错误',
    'Transaction not found'               => '查無此交易',
    'Transaction cannot be paid'          => '此筆交易無法付款',
    'Channel not found'                   => '通道不存在',
    'Third channel not found'             => '查无三方通道',
    'Agent not found'                     => '找不到代理',
    'Bank not found'                      => '银行不存在',
    
    // ========== 驗證錯誤 ==========
    'Signature error'                     => '签名错误',
    'Information is incorrect'            => '提交资料有误',
    'Information is incorrect: :attribute' => '提交资料有误：:attribute',
    'Format error'                        => '格式错误',
    'IP format error'                     => 'IP 格式错误',
    'Invalid qr-code'                     => '二维码格式错误',
    'Invalid format of system order number' => '系统订单号格式错误',
    
    // ========== 參數驗證 ==========
    'Missing parameter: :attribute'       => '缺少参数 :attribute',
    'Amount error'                        => '金额错误',
    'Amount below minimum: :amount'       => '金额低于下限：:amount',
    'Amount above maximum: :amount'       => '金额高于上限：:amount',
    'Decimal amount not allowed'          => '禁止提交小数点金额',
    'Wrong amount, please change and retry' => '金额错误，请更换金额重新发起',
    
    // ========== 訪問控制 ==========
    'IP access forbidden'                 => 'IP 禁止访问',
    'IP not whitelisted: :ip'             => 'IP 未加入白名单 :ip',
    'IP not whitelisted'                  => 'IP未加白',
    'Real name access forbidden'          => '该实名禁止访问',
    'Card holder access forbidden'        => '该持卡人禁止访问',
    'Please contact admin to add IP to whitelist' => '请联系管理员加入 API 白名单',
    
    // ========== 操作狀態 ==========
    'Success'                             => '成功',
    'Failed'                              => '失败',
    'Submit successful'                   => '提交成功',
    'Query successful'                    => '查询成功',
    'Match successful'                    => '匹配成功',
    'Match timeout'                       => '匹配超时',
    'Match timeout, please change amount and retry' => '匹配超时，请更换金额重新发起',
    'Payment timeout'                     => '支付超时',
    'Payment timeout, please change amount and retry' => '支付超时，请更换金额重新发起',
    'Please try again later'              => '请稍候重试',
    'System is busy'                      => '系统繁忙，请重试',
    'Please do not submit transactions too frequently' => '请勿频繁发起交易，请稍候再重新发起',
    
    // ========== 通知狀態 ==========
    'Not notified'                        => '未通知',
    'Waiting to send'                     => '等待发送',
    'Sending'                             => '发送中',
    'Success time'                        => '成功时间',
    
    // ========== 重複/衝突 ==========
    'Duplicate number'                    => '单号重复',
    'Duplicate number: :number'           => '订单号：:number 已存在',
    'Already duplicated'                  => '已重複',
    'Already exists'                      => '已存在',
    'Already manually processed'          => '已补单',
    'Existed'                             => '已存在',
    'Conflict! Please try again later'    => '冲突，请刷新后重试！',
    'Duplicate username'                  => '帐号已存在，请更换帐号重试',
    
    // ========== 帳號/使用者 ==========
    'Username can only be alphanumeric'   => '帐号只能填入数字、英文',
    'Agent functionality is not enabled'  => '代理功能未开启',
    'Merchant number error'               => '商户号错误',
    'Account deactivated'                 => '帐号已停用',
    
    // ========== 通道/銀行 ==========
    'Channel not enabled'                 => '该通道未启用',
    'Channel under maintenance'           => '通道维护中',
    'No matching channel'                 => '无对应通道',
    'Channel fee rate not set'            => '通道费率未设定',
    'Merchant channel not configured'     => '商户未配置该通道',
    'Bank not supported'                  => '不支援此银行',
    'Bank setting error'                  => '銀行設定錯誤',
    
    // ========== 系統訊息 ==========
    'Login failed'                        => '登入失败',
    'Old password incorrect'              => '旧密码错误',
    'Account or password incorrect'       => '帐号或密码错误',
    'Account blocked after too many failed attempts' => '登入失败次数过多将会被系统封锁，请再次确认帐号密码！',
    'Google verification code incorrect'  => '谷歌验证码错误',
    'Google verification code blocked'    => '失败次数过多将会被系统封锁，请务必再次确认！',
    
    // ========== 操作錯誤 ==========
    'Operation error, locked by different user' => '操作错误，与锁定人不符',
    'Cannot transfer to yourself'         => '禁止转点给自己',
    'File name error'                     => '档案名称错误',
    'Please check your input'             => '请检查提交内容是否正确',
    'Create transfer failed'              => '建立出款失败',
    'Account is processing transfer, please try later' => ':account 正在出款，请稍候再试',
    
    // ========== 聯絡客服 ==========
    'Please contact admin'                => '请联络客服',
    'Please contact customer service to create a subordinate account' => '请联络客服建立下级帐号',
    'Please contact customer service to modify' => '请联络客服修改',
    'Unable to send'                      => '无法送出，请联系客服',
    
    // ========== 錢包/餘額 ==========
    'Wallet update conflicts, please try again later' => '余额已被修改，请刷新后重试',
    'Balance exceeds limit, please withdraw first' => '余额超过设定上限，请先下发。',
    'Insufficient balance'                => '余额不足',
    
    // ========== 交易狀態 ==========
    'Transaction already refunded'        => '该笔交易已款',
    'Invalid Status'                      => '目前状态无法完成指定操作',
    'Status cannot be retried'            => '目前状态已无法重试',
    'Order timeout, please contact customer service' => '订单已超时，请联络客服补单',
    
    // ========== 時間相關 ==========
    'Time range cannot exceed one month'  => '时间区间最多一次筛选一个月，请重新调整时间',
    'Date range limited to one month'     => '时间区间最多一次筛选一个月，请重新调整时间',
    
    // ========== 其他 ==========
    'FailedToAdd'                         => '新增失败',
    'noRecord'                            => '查无资料', // 保留舊鍵值以向後兼容
    'Who are you?'                        => '你是谁？',
    'IP set successfully'                 => 'IP 设定成功',
];
