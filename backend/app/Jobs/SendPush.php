<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SendPush implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $userId, public string $title, public string $body) {}

    public function handle(): void
    {
        if (! config('push.enabled')) {
            return;
        }
        $user = DB::table('users')->where('id', $this->userId)->where('status', 'active')->exists();
        $profile = DB::table('user_profiles')->where('user_id', $this->userId)->first();
        if (! $user || ! $profile || ! $profile->optional_notifications || ! $profile->encrypted_push_token) {
            return;
        }
        $client = Http::acceptJson()->timeout(15);
        if (config('push.access_token')) {
            $client = $client->withToken(config('push.access_token'));
        }
        $response = $client->post('https://exp.host/--/api/v2/push/send', ['to' => Crypt::decryptString($profile->encrypted_push_token), 'title' => $this->title, 'body' => $this->body]);
        $response->throw();
        if ($response->json('data.status') === 'error') {
            if ($response->json('data.details.error') === 'DeviceNotRegistered') {
                DB::table('user_profiles')->where('user_id', $this->userId)->update(['encrypted_push_token' => null]);
            } else {
                throw new \RuntimeException('Push provider rejected delivery.');
            }
        }
    }
}
