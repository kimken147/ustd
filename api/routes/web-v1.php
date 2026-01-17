<?php

Route::match(['get', 'post'], 'transaction-demo', 'TransactionDemoController')
    ->middleware(['throttle:60,1'])
    ->name('transaction-demo');

// Route::match(['get', 'post'], 'json-transaction-demo', 'JsonTransactionDemoController')
//     ->middleware(['throttle:60,1'])
//     ->name('json-transaction-demo');

// Route::match(['get', 'post'], 'withdraw-demo', 'WithdrawDemoController')
//     ->middleware(['throttle:60,1'])
//     ->name('withdraw-demo');

// Route::match(['get', 'post'], 'agency-withdraw-demo', 'AgencyWithdrawDemoController')
//     ->middleware(['throttle:60,1'])
//     ->name('agency-withdraw-demo');

Route::get('create-transactions', 'CreateTransactionController')->name('create-transactions');

Route::get('api-document-download', 'ApiDocumentDownloadController');
Route::get('apk-download', 'ApkDownloadController');

