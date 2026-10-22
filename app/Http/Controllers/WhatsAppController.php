<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\WhatsAppMessage;
use App\Services\AuditService;
use App\Services\WhatsApp\NotificationOutbox;
use App\Services\WhatsApp\PhoneNumber;
use App\Services\WhatsApp\SendMessage;
use App\Services\WhatsApp\WhatsAppSettings;
use App\Support\Database\WriteTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppController extends Controller
{
    public function index(Request $request, WhatsAppSettings $settings): Response
    {
        Gate::authorize('view-whatsapp');
        $filters = $request->validate(['status' => ['nullable', 'in:pending,retrying,processing,accepted,sent,delivered,read,failed,uncertain,skipped,cancelled,held'],
            'page' => ['nullable', 'integer', 'between:1,1000000']]);
        $messages = WhatsAppMessage::query()->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('id')->paginate(15)->withQueryString();
        $messages->through(fn ($message) => $message->only(['id', 'event', 'customer_name', 'source_type', 'source_id', 'source_number',
            'target', 'body', 'status', 'reason', 'attempts', 'created_at', 'provider_id']));

        return Inertia::render('WhatsApp/Index', ['messages' => $messages, 'filters' => $filters,
            'settings' => $settings->values(), 'configured' => $settings->configured(),
            'connection' => $request->user()->can('manage-settings') ? $settings->connection() : null,
            'webhookConfigured' => preg_match('/\A[A-Za-z0-9_-]{32,128}\z/', (string) config('whatsapp.webhook_secret')) === 1,
            'canManage' => $request->user()->can('manage-settings')]);
    }

    public function update(Request $request, WhatsAppSettings $settings): RedirectResponse
    {
        Gate::authorize('manage-settings');
        $data = $request->validate(['enabled' => ['required', 'boolean'], 'weighing_enabled' => ['required', 'boolean'], 'payment_enabled' => ['required', 'boolean']]);
        if ($data['enabled'] && ! $settings->configured()) {
            throw ValidationException::withMessages(['enabled' => 'Simpan token dan pastikan uji koneksi berhasil terlebih dahulu.']);
        }
        app(WriteTransaction::class)->run(function () use ($request, $data) {
            Gate::forUser($request->user()->fresh())->authorize('manage-settings');
            $record = AppSetting::firstOrNew(['key' => 'whatsapp']);
            $before = $record->exists ? app(AuditService::class)->snapshot($record) : null;
            $record->fill(['value_json' => $data, 'updated_by' => $request->user()->id])->save();
            app(AuditService::class)->record($request->user(), 'whatsapp-settings', $record, $before, app(AuditService::class)->snapshot($record));
        });

        return back()->with('success', 'Pengaturan WhatsApp disimpan.');
    }

    public function test(Request $request, WhatsAppSettings $settings): RedirectResponse
    {
        Gate::authorize('manage-settings');
        $data = $request->validate(['target' => ['required', 'string', 'max:30'], 'idempotency_key' => ['required', 'uuid']]);
        $target = PhoneNumber::normalize($data['target']);
        if ($target === null || ! $settings->active('test')) {
            throw ValidationException::withMessages(['target' => $target === null ? 'Isi nomor WhatsApp Indonesia yang valid.' : 'Aktifkan pengiriman WhatsApp terlebih dahulu.']);
        }
        app(WriteTransaction::class)->run(function () use ($request, $target, $data) {
            Gate::forUser($request->user()->fresh())->authorize('manage-settings');
            app(NotificationOutbox::class)->test($target, $request->user()->id, $data['idempotency_key']);
        });

        return back()->with('success', 'Pesan tes masuk antrean pengiriman.');
    }

    public function retry(Request $request, WhatsAppMessage $message): RedirectResponse
    {
        Gate::authorize('manage-settings');
        app(WriteTransaction::class)->run(function () use ($request, $message) {
            Gate::forUser($request->user()->fresh())->authorize('manage-settings');
            $message = WhatsAppMessage::lockForUpdate()->findOrFail($message->id);
            // Accepted, uncertain and restored messages cannot be retried from this screen.
            if ($message->status !== 'failed' || $message->provider_id !== null) {
                throw ValidationException::withMessages(['operation' => 'Pesan ini tidak aman untuk dicoba ulang. Periksa riwayat Fonnte.']);
            }
            if ($reason = app(SendMessage::class)->ineligible($message)) {
                throw ValidationException::withMessages(['operation' => $reason]);
            }
            $before = $message->only(['status', 'attempts', 'source_number']);
            $message->update(['status' => 'pending', 'next_attempt_at' => now(), 'queued_at' => null, 'reason' => null]);
            app(AuditService::class)->record($request->user(), 'whatsapp-retry', $message, $before, ['status' => 'pending']);
            DB::afterCommit(fn () => app(NotificationOutbox::class)->dispatchSafely($message->id));
        });

        return back()->with('success', 'Pesan dimasukkan kembali ke antrean.');
    }

    public function show(WhatsAppMessage $message): JsonResponse
    {
        Gate::authorize('view-whatsapp');

        return response()->json(['attempts' => DB::table('whatsapp_attempts')->where('whatsapp_message_id', $message->id)
            ->orderBy('number')->get(['number', 'status', 'reason', 'created_at', 'finished_at'])
            ->map(fn ($attempt) => [
                'number' => $attempt->number, 'status' => $attempt->status, 'reason' => $attempt->reason,
                'created_at' => CarbonImmutable::parse($attempt->created_at, 'UTC')->toIso8601String(),
                'finished_at' => $attempt->finished_at === null ? null : CarbonImmutable::parse($attempt->finished_at, 'UTC')->toIso8601String(),
            ])]);
    }
}
