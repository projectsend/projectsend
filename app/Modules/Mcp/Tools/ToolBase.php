<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools;

use App\Modules\Mcp\Support\Account;
use App\Modules\Mcp\Support\McpContext;
use App\Modules\Mcp\Support\PlanGate;
use App\Modules\Mcp\Support\ToolError;
use App\Modules\Mcp\Support\ToolFailure;
use App\Modules\Mcp\Support\Web;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

/**
 * What every tool does before it does anything: the check order of
 * spec §8, in the order that decides which error the assistant sees
 * when several apply, because the first one has the better `action`.
 *
 *   1. plan       → feature_requires_plan   (a client tool on a free account)
 *   2. scope      → scope_insufficient      (a read-only grant on a write tool)
 *   3. permission → permission_missing      (an API token without the ability)
 *   4. the tool's own run(): resource, arguments, space and type
 *
 * A tool never throws at the assistant. Validation failures become
 * `validation_failed` with the fields named; a ToolFailure thrown by a
 * helper becomes its error; anything else is the framework's problem
 * and is left to it.
 */
abstract class ToolBase extends Tool
{
    /**
     * The tool's own work, once the account is known and allowed.
     */
    abstract protected function run(Request $request, Account $account): Response|ResponseFactory|ToolError;

    /**
     * Something the free plan does not have: listed to a free account like
     * everything else, and answered with the gate (spec §7). What the free
     * plan lacks is offered, never hidden.
     */
    protected function gatedForClients(): bool
    {
        return false;
    }

    /**
     * What the gate says the free plan lacks: client accounts, unless the
     * tool says otherwise.
     *
     * @return 'clients'|'folders'
     */
    protected function gateFeature(): string
    {
        return 'clients';
    }

    /**
     * The `next_call` a gate carries when the tool received a file id:
     * the same file, shared by link instead.
     *
     * @return array<string, mixed>|null
     */
    protected function gateNextCall(Request $request): ?array
    {
        $fileId = $request->get('file_id');

        return is_int($fileId) || (is_string($fileId) && ctype_digit($fileId))
            ? ['tool' => 'share_by_link', 'arguments' => ['file_id' => (int) $fileId]]
            : null;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $account = McpContext::account() ?? Account::current();

        if ($account === null) {
            return ToolError::permissionMissing('a signed-in account', Web::signIn())->toResponse();
        }

        if ($this->gatedForClients() && $account->isClient()) {
            return PlanGate::for($this->name(), $account, $this->gateNextCall($request), $this->gateFeature())->toResponse();
        }

        $refusal = $account->scope->refuses($this->name());

        if ($refusal === 'scope_insufficient') {
            return ToolError::scopeInsufficient(Web::aiConnections())->toResponse();
        }

        if ($refusal === 'permission_missing') {
            return ToolError::permissionMissing($account->scope->permissionFor($this->name()), Web::files($account))->toResponse();
        }

        try {
            $result = $this->run($request, $account);
        } catch (ValidationException $exception) {
            /** @var array<string, list<string>> $errors */
            $errors = $exception->errors();

            return ToolError::validationFailed($errors)->toResponse();
        } catch (ToolFailure $failure) {
            return $failure->error->toResponse();
        }

        return $result instanceof ToolError ? $result->toResponse() : $result;
    }

    /**
     * Listed for every grant; pruned only for an API token whose
     * abilities cannot reach the tool (spec §8, decisions 16 and 17).
     */
    public function shouldRegister(): bool
    {
        $account = McpContext::account() ?? Account::current();

        return $account === null || $account->scope->lists($this->name());
    }

    /**
     * The person's live permission, as the ceiling under any scope. On a
     * client account the app's policies do the same job per file, so
     * this is asked only of staff.
     */
    protected function requirePermission(Account $account, string $permission): void
    {
        if ($account->isClient() || $account->can($permission)) {
            return;
        }

        throw new ToolFailure(ToolError::permissionMissing($permission, Web::files($account)));
    }
}
