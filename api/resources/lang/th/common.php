<?php

return [
    // ========== ผลการค้นหา ==========
    'No data found'                       => 'ไม่พบข้อมูล',
    'User not found'                      => 'ไม่พบผู้ใช้',
    'Order not found'                     => 'ไม่พบคำสั่งซื้อ',
    'Transaction not found'               => 'ไม่พบธุรกรรม',
    'Transaction cannot be paid'          => 'ธุรกรรมนี้ไม่สามารถชำระเงินได้',
    'Channel not found'                   => 'ไม่พบช่องทาง',
    'Third channel not found'             => 'ไม่พบช่องทางบุคคลที่สาม',
    'Agent not found'                     => 'ไม่พบตัวแทน',
    'Bank not found'                      => 'ไม่พบธนาคาร',
    
    // ========== ข้อผิดพลาดในการตรวจสอบ ==========
    'Signature error'                     => 'ลายเซ็นผิดพลาด',
    'Information is incorrect'            => 'ข้อมูลที่ส่งมาไม่ถูกต้อง',
    'Information is incorrect: :attribute' => 'ข้อมูลที่ส่งมาไม่ถูกต้อง: :attribute',
    'Format error'                        => 'รูปแบบผิดพลาด',
    'IP format error'                     => 'รูปแบบ IP ผิดพลาด',
    'Invalid qr-code'                     => 'รูปแบบ QR Code ผิดพลาด',
    'Invalid format of system order number' => 'รูปแบบหมายเลขคำสั่งซื้อระบบผิดพลาด',
    
    // ========== การตรวจสอบพารามิเตอร์ ==========
    'Missing parameter: :attribute'       => 'ขาดพารามิเตอร์: :attribute',
    'Amount error'                        => 'จำนวนเงินผิดพลาด',
    'Amount below minimum: :amount'       => 'จำนวนเงินต่ำกว่าขั้นต่ำ: :amount',
    'Amount above maximum: :amount'       => 'จำนวนเงินเกินสูงสุด: :amount',
    'Decimal amount not allowed'          => 'ไม่อนุญาตให้ส่งจำนวนเงินทศนิยม',
    'Wrong amount, please change and retry' => 'จำนวนเงินผิดพลาด กรุณาเปลี่ยนจำนวนเงินและลองใหม่',
    
    // ========== การควบคุมการเข้าถึง ==========
    'IP access forbidden'                 => 'ไม่อนุญาตให้เข้าถึง IP',
    'IP not whitelisted: :ip'             => 'IP ไม่อยู่ในรายการอนุญาต: :ip',
    'IP not whitelisted'                  => 'IP ไม่อยู่ในรายการอนุญาต',
    'Real name access forbidden'          => 'ไม่อนุญาตให้เข้าถึงชื่อจริงนี้',
    'Card holder access forbidden'        => 'ไม่อนุญาตให้เข้าถึงผู้ถือบัตรนี้',
    'Please contact admin to add IP to whitelist' => 'กรุณาติดต่อผู้ดูแลระบบเพื่อเพิ่ม IP เข้ารายการอนุญาต',
    
    // ========== สถานะการดำเนินการ ==========
    'Success'                             => 'สำเร็จ',
    'Failed'                              => 'ล้มเหลว',
    'Submit successful'                   => 'ส่งสำเร็จ',
    'Query successful'                    => 'ค้นหาสำเร็จ',
    'Match successful'                    => 'จับคู่สำเร็จ',
    'Match timeout'                       => 'การจับคู่นานเกินไป',
    'Match timeout, please change amount and retry' => 'การจับคู่นานเกินไป กรุณาเปลี่ยนจำนวนเงินและลองใหม่',
    'Payment timeout'                     => 'การชำระเงินนานเกินไป',
    'Payment timeout, please change amount and retry' => 'การชำระเงินนานเกินไป กรุณาเปลี่ยนจำนวนเงินและลองใหม่',
    'Please try again later'              => 'กรุณาลองใหม่อีกครั้งในภายหลัง',
    'System is busy'                      => 'ระบบกำลังยุ่ง กรุณาลองใหม่อีกครั้ง',
    'Please do not submit transactions too frequently' => 'กรุณาอย่าส่งธุรกรรมบ่อยเกินไป กรุณารอสักครู่แล้วลองใหม่',
    
    // ========== สถานะการแจ้งเตือน ==========
    'Not notified'                        => 'ยังไม่ได้แจ้งเตือน',
    'Waiting to send'                     => 'รอส่ง',
    'Sending'                             => 'กำลังส่ง',
    'Success time'                        => 'เวลาที่สำเร็จ',
    
    // ========== ซ้ำ/ขัดแย้ง ==========
    'Duplicate number'                    => 'หมายเลขซ้ำ',
    'Duplicate number: :number'           => 'หมายเลขคำสั่งซื้อ: :number มีอยู่แล้ว',
    'Already duplicated'                  => 'ซ้ำแล้ว',
    'Already exists'                      => 'มีอยู่แล้ว',
    'Already manually processed'          => 'ประมวลผลด้วยตนเองแล้ว',
    'Existed'                             => 'มีอยู่แล้ว กรุณาลองใหม่อีกครั้งด้วยตัวเลือกอื่น',
    'Conflict! Please try again later'    => 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้งในภายหลัง',
    'Duplicate username'                  => 'รหัสเข้าสู่ระบบนี้มีอยู่แล้ว กรุณาเลือก ID อื่นและลองอีกครั้ง',
    
    // ========== บัญชี/ผู้ใช้ ==========
    'Username can only be alphanumeric'   => 'สามารถกรอกได้เฉพาะตัวเลขและตัวอักษร',
    'Agent functionality is not enabled'  => 'ฟังก์ชันตัวแทนไม่ได้เปิดใช้งาน',
    'Merchant number error'               => 'หมายเลขร้านค้าผิดพลาด',
    'Account deactivated'                 => 'บัญชีถูกปิดใช้งาน',
    
    // ========== ช่องทาง/ธนาคาร ==========
    'Channel not enabled'                 => 'ช่องทางนี้ไม่ได้เปิดใช้งาน',
    'Channel under maintenance'           => 'ช่องทางกำลังอยู่ระหว่างการบำรุงรักษา',
    'No matching channel'                 => 'ไม่พบช่องทางที่ตรงกัน',
    'Channel fee rate not set'            => 'ยังไม่ได้ตั้งค่าอัตราค่าธรรมเนียมช่องทาง',
    'Merchant channel not configured'     => 'ร้านค้ายังไม่ได้กำหนดค่าช่องทาง',
    'Bank not supported'                  => 'ไม่รองรับธนาคารนี้',
    'Bank setting error'                  => 'การตั้งค่าธนาคารผิดพลาด',
    
    // ========== ข้อความระบบ ==========
    'Login failed'                        => 'เข้าสู่ระบบล้มเหลว',
    'Old password incorrect'              => 'รหัสผ่านเดิมไม่ถูกต้อง',
    'Account or password incorrect'       => 'บัญชีหรือรหัสผ่านไม่ถูกต้อง',
    'Account blocked after too many failed attempts' => 'การเข้าสู่ระบบล้มเหลวหลายครั้ง บัญชีจะถูกบล็อก',
    'Google verification code incorrect'  => 'รหัสยืนยัน Google ไม่ถูกต้อง',
    'Google verification code blocked'    => 'การยืนยันล้มเหลวหลายครั้ง บัญชีจะถูกบล็อก',
    
    // ========== ข้อผิดพลาดในการดำเนินการ ==========
    'Operation error, locked by different user' => 'ข้อผิดพลาดในการดำเนินการ ถูกล็อกโดยผู้ใช้อื่น',
    'Cannot transfer to yourself'         => 'ไม่สามารถโอนให้ตัวเอง',
    'File name error'                     => 'ชื่อไฟล์ผิดพลาด',
    'Please check your input'             => 'กรุณาตรวจสอบว่าข้อมูลคำสั่งซื้อถูกต้องหรือไม่',
    
    // ========== ติดต่อฝ่ายบริการลูกค้า ==========
    'Please contact admin'                => 'กรุณาติดต่อฝ่ายบริการลูกค้า',
    'Please contact customer service to create a subordinate account' => 'การสร้างล้มเหลว กรุณาติดต่อฝ่ายบริการลูกค้าเพื่อสร้างบัญชีรอง',
    'Please contact customer service to modify' => 'กรุณาติดต่อฝ่ายบริการลูกค้าสำหรับการแก้ไข',
    'Unable to send'                      => 'ไม่สามารถส่งได้ กรุณาติดต่อฝ่ายบริการลูกค้า',
    
    // ========== กระเป๋าเงิน/ยอดเงิน ==========
    'Wallet update conflicts, please try again later' => 'ยอดเงินรวมถูกแก้ไขแล้ว กรุณารีเฟรชหน้าและตรวจสอบอีกครั้ง',
    'Balance exceeds limit, please withdraw first' => 'ยอดเงินเกินขีดจำกัดที่ตั้งไว้ กรุณาถอนเงินก่อน',
    'Insufficient balance'                => 'ยอดเงินไม่เพียงพอ',
    
    // ========== สถานะธุรกรรม ==========
    'Transaction already refunded'        => 'คำสั่งซื้อนี้เกิดข้อผิดพลาด',
    'Invalid Status'                      => 'ไม่สามารถแก้ไขคำสั่งซื้อได้ กรุณาติดต่อฝ่ายบริการลูกค้า',
    'Status cannot be retried'            => 'ไม่สามารถแก้ไขคำสั่งซื้อได้ กรุณาติดต่อฝ่ายบริการลูกค้า',
    'Order timeout, please contact customer service' => 'คำสั่งซื้อหมดเวลา กรุณาติดต่อฝ่ายบริการลูกค้า',
    
    // ========== เกี่ยวกับเวลา ==========
    'Time range cannot exceed one month'  => 'ช่วงเวลาไม่สามารถเกินหนึ่งเดือน กรุณาปรับใหม่',
    
    // ========== อื่น ๆ ==========
    'FailedToAdd'                         => 'การสร้างล้มเหลว',
    'noRecord'                            => 'ไม่พบข้อมูล', // คงคีย์เก่าไว้เพื่อความเข้ากันได้ย้อนหลัง
    'Who are you?'                        => 'คุณคือใคร?',
    'IP set successfully'                 => 'ตั้งค่า IP สำเร็จ',
];

