<?php
// Isolated MySQL contention check. Never run against a development/production database.
require __DIR__.'/../backend/vendor/autoload.php';
$app=require __DIR__.'/../backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(getenv('DB_DATABASE')!=='quiz_test') { fwrite(STDERR,"Requires DB_DATABASE=quiz_test\n"); exit(1); }
use App\Models\User;
use App\Services\QuizService;
use Illuminate\Support\Facades\DB;
$mode=$argv[1]??'';
if($mode==='prepare') {
    (new Database\Seeders\DatabaseSeeder)->run();
    $u=User::create(['name'=>'Concurrency Test','email'=>bin2hex(random_bytes(8)).'@example.com','password'=>bin2hex(random_bytes(20))]);
    $q=app(QuizService::class)->today($u)[0]; echo json_encode(['user'=>$u->id,'assignment'=>$q['id']]);
} elseif($mode==='answer') {
    $id=(int)$argv[2]; $start=(float)$argv[3];
    $a=DB::table('daily_question_assignments')->where('id',$id)->first();$u=User::findOrFail($a->user_id);$s=json_decode($a->snapshot,true);
    while(microtime(true)<$start) usleep(1000);
    try { app(QuizService::class)->answer($u,$id,$s['correct_answer']); echo 'awarded'; }
    catch(Symfony\Component\HttpKernel\Exception\HttpException $e) { if($e->getStatusCode()!==409) throw $e; echo 'duplicate'; }
} elseif($mode==='verify') {
    $id=(int)$argv[2];$a=DB::table('daily_question_assignments')->where('id',$id)->first();
    if(DB::table('user_answers')->where('assignment_id',$id)->count()!==1 || (int)DB::table('coin_transactions')->where('user_id',$a->user_id)->sum('amount')!==10) exit(1);
    echo 'verified';
} else exit(1);
