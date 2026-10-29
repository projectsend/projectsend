<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools;

use App\Modules\Clients\ClientAccounts;
use App\Modules\Identity\Erasure\AvailableEmailRule;
use App\Modules\Mcp\Support\Account;
use App\Modules\Mcp\Support\ClientAccountsQuery;
use App\Modules\Mcp\Support\Envelope;
use App\Modules\Mcp\Support\Idempotency;
use App\Modules\Mcp\Support\IdempotencyConflict;
use App\Modules\Mcp\Support\Presenter;
use App\Modules\Mcp\Support\ToolError;
use App\Modules\Mcp\Support\ToolFailure;
use App\Modules\Mcp\Support\Uploads;
use App\Modules\Mcp\Support\Web;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('create_client')]
#[Title('Create a client account')]
#[Description('Creates a client account on this ProjectSend site: a person who will sign in to see the files shared with them. Use it when the person wants to give someone their own access, or before send_file_to_client for someone who is not a client yet. Not available on a free account, which shares by private link instead. If an account with that email already exists the response says so and returns its id: use it rather than creating another. When you generate the password, the response carries it once; relay it to the person, it is not shown again. After this, send_file_to_client puts something in the new account.')]
#[IsIdempotent]
class CreateClient extends ToolBase
{
    protected function gatedForClients(): bool
    {
        return true;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->max(120)->required()->description("The person's or company's name, as they should see it."),
            'email' => $schema->string()->max(255)->required()->description('Their sign-in address. One account per address.'),
            'password' => $schema->string()->min(12)->max(255)->description('Leave it out to have a strong one generated and returned once in the response.'),
            'storage_quota_mb' => $schema->integer()->min(0)->description("Space this client may upload, in MB. Omit for the site's default."),
            'notify' => $schema->boolean()->default(true)->description('Email the client that their account exists, as the web does.'),
            'idempotency_key' => $schema->string()->max(64)->description('Use the email address as the key when creating several clients from a list, so a retry never creates two.'),
        ];
    }

    protected function run(Request $request, Account $account): Response|ResponseFactory|ToolError
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'min:12', 'max:255'],
            'storage_quota_mb' => ['nullable', 'integer', 'min:0'],
            'notify' => ['nullable', 'boolean'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);

        $this->requirePermission($account, 'create_clients');

        $email = strtolower(trim((string) $validated['email']));
        $key = isset($validated['idempotency_key']) ? (string) $validated['idempotency_key'] : null;

        if ($key === null) {
            return Response::structured($this->create($account, (string) $validated['name'], $email, $validated));
        }

        // The answer is remembered for a retry, whose whole point is an
        // answer that never arrived, so the password has to survive it.
        // Encrypted with the app key, never as plain text: the cache is a
        // database table or Redis.
        $create = function () use ($account, $validated, $email): array {
            $payload = $this->create($account, (string) $validated['name'], $email, $validated);

            if (is_string($payload['password_once'] ?? null)) {
                $payload['password_once'] = Crypt::encryptString($payload['password_once']);
            }

            return $payload;
        };

        $fingerprint = Uploads::fingerprint(['name' => $validated['name'], 'email' => $email, 'storage_quota_mb' => $validated['storage_quota_mb'] ?? null]);

        try {
            [$payload] = app(Idempotency::class)->remember('mcp', 'mcp:'.$account->id().':create_client:'.$key, $fingerprint, $create);
        } catch (IdempotencyConflict) {
            return ToolError::idempotencyConflict(now()->toIso8601String());
        }

        if (is_string($payload['password_once'] ?? null)) {
            $payload['password_once'] = Crypt::decryptString($payload['password_once']);
        }

        return Response::structured($payload);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed> the whole answer, so a replay returns it verbatim
     */
    private function create(Account $account, string $name, string $email, array $validated): array
    {
        $existing = ClientAccountsQuery::byEmail($account, $email);

        if ($existing !== null) {
            throw new ToolFailure(ToolError::clientEmailTaken((int) $existing->getAttribute('id'), (string) $existing->getAttribute('name')));
        }

        // Any other account holding the address — staff, somebody else's
        // client, a pending request, a deleted account awaiting erasure —
        // is refused in the web form's own words, without naming it.
        $available = Validator::make(['email' => $email], ['email' => [new AvailableEmailRule]]);

        if ($available->fails()) {
            /** @var array<string, list<string>> $errors */
            $errors = $available->errors()->toArray();

            throw new ToolFailure(ToolError::validationFailed($errors));
        }

        $seats = $account->seats();

        if ($seats !== null && $seats['clients_limit'] !== null && $seats['clients_used'] >= $seats['clients_limit']) {
            throw new ToolFailure(ToolError::seatsFull($seats['clients_used'], $seats['clients_limit'], Web::portal()));
        }

        $generated = ! isset($validated['password']);
        $password = $generated ? Str::password(20) : (string) $validated['password'];
        $notify = (bool) ($validated['notify'] ?? true);

        try {
            $client = app(ClientAccounts::class)->create(
                name: $name,
                email: $email,
                password: $password,
                storageQuotaMb: (int) ($validated['storage_quota_mb'] ?? 0),
                welcome: $notify,
            );
        } catch (ValidationException $exception) {
            // The app refuses a taken address and a full site on the
            // same field, in the tenant's own words; both were asked
            // above, so what is left is a genuinely bad argument.
            /** @var array<string, list<string>> $errors */
            $errors = $exception->errors();

            throw new ToolFailure(ToolError::validationFailed($errors));
        }

        // As the web does: a client-scoped creator keeps the client they
        // made, rather than creating one they can then never reach.
        if ($account->user->isClientScoped()) {
            $account->user->assignedClients()->attach($client->id);
        }

        $id = (int) $client->getAttribute('id');
        $after = $account->seats();

        $factory = Envelope::success(
            [
                'client' => Presenter::client($client),
                'password_once' => $generated ? $password : null,
                'notified' => $notify,
                'seats' => $after === null ? null : ['clients_used' => $after['clients_used'], 'clients_limit' => $after['clients_limit']],
            ],
            Web::client($id),
            $generated
                ? "Give the person the sign-in URL and this password now; it will not be shown again. Then send_file_to_client with client_id {$id}."
                : "The client can sign in at sign_in_url. send_file_to_client with client_id {$id} puts something in their account.",
        );

        $structured = $factory->getStructuredContent();

        return is_array($structured) ? $structured : [];
    }
}
