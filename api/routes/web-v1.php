<?php

Route::get('create-transactions', 'CreateTransactionController')->name('create-transactions');

Route::get('api-document-download', 'ApiDocumentDownloadController');
Route::match(['get', 'post'], 'api-document', 'ApiDocumentController')
    ->middleware(['throttle:60,1'])
    ->name('api-document');
