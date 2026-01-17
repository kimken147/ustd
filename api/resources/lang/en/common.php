<?php

return [
    // ========== Query Results ==========
    'No data found'                       => 'No Data Available',
    'User not found'                      => 'User Not Found',
    'Order not found'                     => 'No Order Data Found',
    'Transaction not found'               => 'Transaction Not Found',
    'Transaction cannot be paid'          => 'Transaction Cannot Be Paid',
    'Channel not found'                   => 'Channel Not Found',
    'Third channel not found'             => 'Third Channel Not Found',
    'Agent not found'                     => 'Agent Not Found',
    'Bank not found'                      => 'Bank Not Found',
    
    // ========== Validation Errors ==========
    'Signature error'                     => 'Signature Error',
    'Information is incorrect'            => 'Invalid Information Submission. Please Retry',
    'Information is incorrect: :attribute' => 'Invalid Information Submission: :attribute',
    'Format error'                        => 'Format Error',
    'IP format error'                     => 'IP Format Error',
    'Invalid qr-code'                     => 'QR Code Format Error',
    'Invalid format of system order number' => 'System Order Number Format Error',
    
    // ========== Parameter Validation ==========
    'Missing parameter: :attribute'       => 'Missing Parameter: :attribute',
    'Amount error'                        => 'Amount Error',
    'Amount below minimum: :amount'       => 'Amount Below Minimum: :amount',
    'Amount above maximum: :amount'       => 'Amount Above Maximum: :amount',
    'Decimal amount not allowed'          => 'Decimal Amount Not Allowed',
    'Wrong amount, please change and retry' => 'Amount Error, Please Change Amount and Retry',
    
    // ========== Access Control ==========
    'IP access forbidden'                 => 'IP Access Forbidden',
    'IP not whitelisted: :ip'             => 'IP Not Whitelisted: :ip',
    'IP not whitelisted'                  => 'IP Not Whitelisted',
    'Real name access forbidden'          => 'Real Name Access Forbidden',
    'Card holder access forbidden'        => 'Card Holder Access Forbidden',
    'Please contact admin to add IP to whitelist' => 'Please Contact Admin to Add IP to Whitelist',
    
    // ========== Operation Status ==========
    'Success'                             => 'Success',
    'Failed'                              => 'Failed',
    'Submit successful'                   => 'Submit Successful',
    'Query successful'                    => 'Query Successful',
    'Match successful'                    => 'Match Successful',
    'Match timeout'                       => 'Match Timeout',
    'Match timeout, please change amount and retry' => 'Match Timeout, Please Change Amount and Retry',
    'Payment timeout'                     => 'Payment Timeout',
    'Payment timeout, please change amount and retry' => 'Payment Timeout, Please Change Amount and Retry',
    'Please try again later'              => 'Please Try Again Later',
    'System is busy'                      => 'The System is Busy, Please Try Again Later',
    'Please do not submit transactions too frequently' => 'Please Do Not Submit Transactions Too Frequently',
    
    // ========== Notification Status ==========
    'Not notified'                        => 'Not Notified',
    'Waiting to send'                     => 'Waiting to Send',
    'Sending'                             => 'Sending',
    'Success time'                        => 'Success Time',
    
    // ========== Duplicate/Conflict ==========
    'Duplicate number'                    => 'The Order Number Already Exists',
    'Duplicate number: :number'           => 'Order Number: :number Already Exists',
    'Already duplicated'                  => 'Already Duplicated',
    'Already exists'                      => 'Already Exists',
    'Already manually processed'          => 'Already Manually Processed',
    'Existed'                             => 'Already Exists, Please Try Again with a Different One',
    'Conflict! Please try again later'    => 'There Was an Error, Please Try Again Later',
    'Duplicate username'                  => 'This Login ID Already Exists. Please Choose a Different ID and Try Again',
    
    // ========== Account/User ==========
    'Username can only be alphanumeric'   => 'Numbers and Letters Only',
    'Agent functionality is not enabled'  => 'Agent Status Not Enabled',
    'Merchant number error'               => 'Merchant Number Error',
    'Account deactivated'                 => 'Account Deactivated',
    
    // ========== Channel/Bank ==========
    'Channel not enabled'                 => 'Channel Not Enabled',
    'Channel under maintenance'           => 'Channel Under Maintenance',
    'No matching channel'                 => 'No Matching Channel',
    'Channel fee rate not set'            => 'Channel Fee Rate Not Set',
    'Merchant channel not configured'     => 'Merchant Channel Not Configured',
    'Bank not supported'                  => 'Bank Not Supported',
    'Bank setting error'                  => 'Bank Setting Error',
    
    // ========== System Messages ==========
    'Login failed'                        => 'Login Failed',
    'Old password incorrect'              => 'Old Password Incorrect',
    'Account or password incorrect'       => 'Account or Password Incorrect',
    'Account blocked after too many failed attempts' => 'Too Many Failed Login Attempts, Account Will Be Blocked',
    'Google verification code incorrect'  => 'Google Verification Code Incorrect',
    'Google verification code blocked'    => 'Too Many Failed Attempts, Account Will Be Blocked',
    
    // ========== Operation Errors ==========
    'Operation error, locked by different user' => 'Operation Error, Locked by Different User',
    'Cannot transfer to yourself'         => 'Cannot Transfer to Yourself',
    'File name error'                     => 'File Name Error',
    'Please check your input'             => 'Please Check If the Order Details Are Correct',
    
    // ========== Contact Customer Service ==========
    'Please contact admin'                => 'Please Contact Customer Service',
    'Please contact customer service to create a subordinate account' => 'Creation Failed, Please Contact Customer Service to Create Subordinate Account',
    'Please contact customer service to modify' => 'Please Contact Customer Service for Modifications',
    'Unable to send'                      => 'Unable to Send, Please Contact Customer Service',
    
    // ========== Wallet/Balance ==========
    'Wallet update conflicts, please try again later' => 'The Total Balance Has Been Edited, Please Refresh the Page and Check Again',
    'Balance exceeds limit, please withdraw first' => 'Balance Exceeds Set Limit, Please Withdraw First',
    'Insufficient balance'                => 'Insufficient Balance',
    
    // ========== Transaction Status ==========
    'Transaction already refunded'        => 'This Order is in Error',
    'Invalid Status'                      => 'Unable to Modify the Order, Please Contact Customer Service',
    'Status cannot be retried'            => 'Unable to Modify the Order, Please Contact Customer Service',
    'Order timeout, please contact customer service' => 'Order Has Timed Out, Please Contact Customer Service',
    
    // ========== Time Related ==========
    'Time range cannot exceed one month'  => 'Time Range Cannot Exceed One Month, Please Adjust',
    
    // ========== Others ==========
    'FailedToAdd'                         => 'Creation Failed',
    'noRecord'                            => 'No Data Available', // Keep old key for backward compatibility
    'Who are you?'                        => 'Who Are You?',
    'IP set successfully'                 => 'IP Set Successfully',
];
