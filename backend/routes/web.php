<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::pattern('id', '[0-9]+');

Route::get('/', fn () => response()->json(['service' => 'Quiz & Rewards API', 'version' => 'v1']));
Route::get('/email/verify/{id}/{hash}', function (Request $r, int $id, string $hash) {
    $user = User::findOrFail($id);
    abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);
    $user->markEmailAsVerified();

    return 'Email verified. Return to the app to sign in.';
})->middleware(['signed', 'throttle:6,1,signed-verification:'])->name('verification.verify');
Route::get('/reset-password/{token}', fn (string $token) => view('admin.reset-password', compact('token')))->name('password.reset');

use App\Http\Controllers\AdminController;
use App\Http\Middleware\AdminAccess;
use Illuminate\Support\Facades\Auth;

Route::get('/admin/login', fn () => view('admin.login'))->name('admin.login');
Route::post('/admin/login', [AdminController::class, 'login'])->middleware('throttle:5,1,admin:');
Route::prefix('admin')->middleware(AdminAccess::class)->group(function () {
    Route::get('/', [AdminController::class, 'dashboard']);
    Route::post('/logout', function (Request $r) {
        Auth::guard('admin')->logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/admin/login');
    });
    foreach (['categories' => 'categories.manage', 'questions' => 'questions.manage', 'users' => 'users.manage', 'rewards' => 'rewards.manage'] as $resource => $permission) {
        Route::get($resource, [AdminController::class, $resource])->middleware(AdminAccess::class.':'.$permission);
    }
    Route::middleware(AdminAccess::class.':categories.manage')->group(function () {
        Route::post('categories/{id?}', [AdminController::class, 'saveCategory']);
        Route::delete('categories/{id}', [AdminController::class, 'deleteCategory']);
    });
    Route::middleware(AdminAccess::class.':questions.manage')->group(function () {
        Route::post('questions/{id?}', [AdminController::class, 'saveQuestion']);
        Route::delete('questions/{id}', [AdminController::class, 'deleteQuestion']);
    });
    Route::post('users/{id}/status', [AdminController::class, 'userStatus'])->middleware(AdminAccess::class.':users.manage');
    Route::post('rewards/bonus', [AdminController::class, 'bonus'])->middleware(AdminAccess::class.':rewards.manage');
    Route::post('rewards/adjust', [AdminController::class, 'adjust'])->middleware(AdminAccess::class.':rewards.manage');
});

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OperationsController;

Route::prefix('admin')->group(function () {
    Route::middleware(AdminAccess::class.':privacy.manage')->group(function () {
        Route::get('privacy', [OperationsController::class, 'privacy']);
        Route::get('privacy/{type}/{id}', [OperationsController::class, 'sensitive']);
        Route::delete('privacy/{type}/{id}', [OperationsController::class, 'deleteSensitive']);
        Route::post('deletion/{id}', [OperationsController::class, 'processDeletion']);
    });
    Route::get('reports', [OperationsController::class, 'reports'])->middleware(AdminAccess::class.':reports.view');
    Route::post('questions/import/csv', [OperationsController::class, 'import'])->middleware(AdminAccess::class.':questions.manage');
    Route::post('questions/{id}/image', [OperationsController::class, 'image'])->middleware(AdminAccess::class.':questions.manage');
    Route::get('notifications', [OperationsController::class, 'notifications'])->middleware(AdminAccess::class.':notifications.manage');
    Route::post('notifications', [OperationsController::class, 'announce'])->middleware(AdminAccess::class.':notifications.manage');
});

Route::post('/reset-password', [AuthController::class, 'reset'])->middleware('throttle:5,1,admin:');

Route::post('admin/schedule/reassign', [OperationsController::class, 'reassign'])->middleware(AdminAccess::class.':questions.manage');
Route::get('admin/questions/{id}/history', [OperationsController::class, 'history'])->middleware(AdminAccess::class.':questions.manage');

Route::get('admin/users/{id}', [OperationsController::class, 'user'])->middleware(AdminAccess::class.':users.manage');
