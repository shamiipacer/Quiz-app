<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    private function audit(string $action, string $type, int $id, array $metadata = []): void
    {
        DB::table('audit_logs')->insert(['admin_user_id' => Auth::guard('admin')->id(), 'action' => $action, 'resource_type' => $type, 'resource_id' => $id, 'metadata' => json_encode($metadata), 'created_at' => now()]);
    }

    public function login(Request $r)
    {
        $v = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::guard('admin')->attempt([...$v, 'active' => true])) {
            return back()->withErrors(['email' => 'Invalid credentials.']);
        }
        $r->session()->regenerate();

        return redirect('/admin');
    }

    public function dashboard()
    {
        $stats = ['Registered users' => DB::table('users')->where('status', '!=', 'deleted')->count(),
            'Registrations today' => DB::table('users')->whereDate('created_at', today())->count(),
            'Active users today' => DB::table('login_history')->whereDate('created_at', today())->distinct()->count('user_id'),
            'Questions' => DB::table('questions')->count(),
            'Answers today' => DB::table('user_answers')->whereDate('submitted_at', today())->count(),
            'Correct today' => DB::table('user_answers')->whereDate('submitted_at', today())->where('correct', true)->count(),
            'Incorrect today' => DB::table('user_answers')->whereDate('submitted_at', today())->where('correct', false)->count(),
            'Virtual coins awarded' => DB::table('coin_transactions')->where('amount', '>', 0)->sum('amount'),
            'Contact opt-ins' => DB::table('user_consents')->where('purpose', 'contacts')->whereNull('revoked_at')->count(),
            'Location opt-ins' => DB::table('user_consents')->where('purpose', 'location')->whereNull('revoked_at')->count()];
        $participation = DB::table('user_answers')->join('daily_question_assignments', 'assignment_id', '=', 'daily_question_assignments.id')->join('categories', 'categories.id', '=', DB::raw("JSON_EXTRACT(daily_question_assignments.snapshot, '$.category_id')"))->selectRaw('categories.name,COUNT(*) as answered')->groupBy('categories.id', 'categories.name')->get();
        $activity = DB::table('audit_logs')->select('action', 'created_at')->orderByDesc('id')->limit(10)->get();

        return view('admin.dashboard', compact('stats', 'participation', 'activity'));
    }

    public function categories()
    {
        return view('admin.categories', ['items' => DB::table('categories')->orderBy('display_order')->paginate(20)]);
    }

    public function saveCategory(Request $r, ?int $id = null)
    {
        $v = $r->validate(['name' => 'required|string|max:100|unique:categories,name,'.($id ?? 'NULL'), 'description' => 'nullable|string|max:2000', 'icon' => 'required|string|max:50', 'daily_limit' => 'required|integer|min:0|max:20', 'default_reward' => 'required|integer|min:0|max:10000', 'display_order' => 'required|integer|min:0|max:10000', 'active' => 'required|boolean']);
        DB::transaction(function () use ($v, $id) {
            if ($id) {
                abort_unless(DB::table('categories')->where('id', $id)->exists(), 404);
                DB::table('categories')->where('id', $id)->update([...$v, 'updated_at' => now()]);
            } else {
                $id = DB::table('categories')->insertGetId([...$v, 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->audit('category.saved', 'category', $id);
        });

        return back()->with('message', 'Category saved.');
    }

    public function deleteCategory(int $id)
    {
        abort_if(DB::table('questions')->where('category_id', $id)->exists(), 409, 'Deactivate categories containing questions.');
        DB::transaction(function () use ($id) {
            DB::table('categories')->where('id', $id)->delete();
            $this->audit('category.deleted', 'category', $id);
        });

        return back();
    }

    public function questions()
    {
        return view('admin.questions', ['items' => DB::table('questions')->orderByDesc('id')->paginate(20), 'categories' => DB::table('categories')->get()]);
    }

    public function saveQuestion(Request $r, ?int $id = null)
    {
        $v = $r->validate(['category_id' => 'required|integer|exists:categories,id', 'text' => 'required|string|max:5000', 'type' => 'required|in:multiple_choice,true_false,single_answer', 'difficulty' => 'required|in:easy,medium,hard', 'options' => 'nullable|string|max:4000', 'correct_answer' => 'required|string|max:500', 'explanation' => 'nullable|string|max:5000', 'coin_reward' => 'nullable|integer|min:0|max:10000', 'publication_at' => 'required|date', 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date|after:starts_at', 'active' => 'required|boolean']);
        $options = array_values(array_filter(array_map('trim', explode("\n", $v['options'] ?? ''))));
        if ($v['type'] !== 'single_answer') {
            abort_unless(count($options) >= 2 && count($options) <= 6 && count(array_unique($options)) === count($options) && in_array($v['correct_answer'], $options, true), 422, 'Provide 2–6 options and an exact correct option.');
            if ($v['type'] === 'true_false') {
                abort_unless($options === ['True', 'False'], 422, 'Use True and False options.');
            }
        }
        unset($v['options']);
        DB::transaction(function () use ($v, $options, $id) {
            if ($id) {
                abort_unless(DB::table('questions')->where('id', $id)->exists(), 404);
                DB::table('questions')->where('id', $id)->update([...$v, 'updated_at' => now()]);
                DB::table('question_options')->where('question_id', $id)->delete();
            } else {
                $id = DB::table('questions')->insertGetId([...$v, 'created_at' => now(), 'updated_at' => now()]);
            }
            foreach ($options as $i => $option) {
                DB::table('question_options')->insert(['question_id' => $id, 'text' => $option, 'is_correct' => $option === $v['correct_answer'], 'display_order' => $i, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('question_revisions')->insert(['question_id' => $id, 'admin_user_id' => Auth::guard('admin')->id(), 'snapshot' => json_encode([...$v, 'options' => $options]), 'created_at' => now()]);
            $this->audit('question.saved', 'question', $id);
        });

        return back()->with('message', 'Question saved. Existing assignments keep their original grading.');
    }

    public function deleteQuestion(int $id)
    {
        abort_if(DB::table('daily_question_assignments')->where('question_id', $id)->exists(), 409, 'Deactivate assigned questions.');
        DB::transaction(function () use ($id) {
            DB::table('questions')->where('id', $id)->delete();
            $this->audit('question.deleted', 'question', $id);
        });

        return back();
    }

    public function users(Request $r)
    {
        $query = DB::table('users')->select('id', 'name', 'email', 'status', 'created_at');
        if ($r->filled('search')) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.substr($r->input('search'), 0, 100).'%')->orWhere('email', 'like', '%'.substr($r->input('search'), 0, 100).'%'));
        }

        return view('admin.users', ['items' => $query->paginate(20)->withQueryString()]);
    }

    public function userStatus(Request $r, int $id)
    {
        $v = $r->validate(['status' => 'required|in:active,suspended', 'reason' => 'required|string|min:5|max:500']);
        DB::transaction(function () use ($v, $id) {
            abort_unless(DB::table('users')->where('id', $id)->lockForUpdate()->first(), 404);
            DB::table('users')->where('id', $id)->update(['status' => $v['status'], 'updated_at' => now()]);
            if ($v['status'] === 'suspended') {
                DB::table('api_tokens')->where('user_id', $id)->delete();
            } $this->audit('user.status', 'user', $id, $v);
        });

        return back();
    }

    public function rewards()
    {
        return view('admin.rewards', ['items' => DB::table('coin_transactions')->orderByDesc('id')->paginate(30), 'bonus' => DB::table('rewards')->where('key', 'daily_completion')->value('coins')]);
    }

    public function bonus(Request $r)
    {
        $v = $r->validate(['coins' => 'required|integer|min:0|max:10000']);
        DB::transaction(function () use ($v) {
            DB::table('rewards')->where('key', 'daily_completion')->update([...$v, 'updated_at' => now()]);
            $this->audit('reward.updated', 'reward', 0, $v);
        });

        return back();
    }

    public function adjust(Request $r)
    {
        $v = $r->validate(['user_id' => 'required|integer|exists:users,id', 'amount' => 'required|integer|not_in:0|min:-10000|max:10000', 'reason' => 'required|string|min:5|max:500', 'reference' => 'required|uuid']);
        DB::transaction(function () use ($v) {
            DB::table('users')->where('id', $v['user_id'])->lockForUpdate()->first();
            abort_if(DB::table('coin_transactions')->where('user_id', $v['user_id'])->sum('amount') + $v['amount'] < 0, 422, 'Insufficient balance.');
            abort_if(DB::table('coin_transactions')->where('reference', $v['reference'])->exists(), 409, 'Adjustment already recorded.');
            $id = DB::table('coin_transactions')->insertGetId([...$v, 'admin_user_id' => Auth::guard('admin')->id(), 'type' => 'adjustment', 'created_at' => now()]);
            $this->audit('coin.adjusted', 'transaction', $id, ['reason' => $v['reason']]);
        });

        return back();
    }
}
