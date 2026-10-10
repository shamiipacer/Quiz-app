<?php
require __DIR__.'/../backend/vendor/autoload.php';
$app=require __DIR__.'/../backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(app()->environment('production')) exit(1);
Illuminate\Support\Facades\URL::forceRootUrl('http://127.0.0.1:8000');
$u=App\Models\User::findOrFail((int)$argv[1]);
echo Illuminate\Support\Facades\URL::temporarySignedRoute('verification.verify',now()->addMinutes(10),['id'=>$u->id,'hash'=>sha1($u->email)]);
