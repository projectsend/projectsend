<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

/**
 * The API reference, inside the admin UI.
 *
 * Rendered from the files that are already the source of truth — the
 * committed OpenAPI document, docs/api-guide.md and docs/api-zapier.md —
 * rather than embedding a third-party documentation UI. An iframe or a
 * CDN-hosted renderer would mean a page that ignores the app's theme,
 * breaks its links, and goes blank on an install with no outbound internet
 * access, which self-hosted installations regularly are.
 *
 * The markdown is converted server-side with league/commonmark, already a
 * framework dependency, so no JavaScript renderer joins the bundle.
 */
class ApiDocsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('api/docs', [
            'guide_html' => $this->markdown('docs/api-guide.md'),
            // A second page rather than a section of the guide: the guide
            // is written for someone building against the API, this is
            // written for someone wiring up a Zap and reading nothing else.
            'zapier_html' => $this->markdown('docs/api-zapier.md'),
            'endpoints' => $this->endpoints(),
            'spec_url' => route('api.openapi'),
            'version' => $this->spec()['info']['version'] ?? null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function spec(): array
    {
        $path = base_path(OpenApiController::PATH);

        if (! is_file($path)) {
            return [];
        }

        return json_decode((string) file_get_contents($path), true) ?: [];
    }

    /**
     * Flattened operation list for the summary table: one row per method
     * and path, with the abilities pulled back out of the description
     * where describeOpenApi() put them.
     *
     * @return list<array<string, mixed>>
     */
    private function endpoints(): array
    {
        $rows = [];

        foreach ($this->spec()['paths'] ?? [] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                if (! in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    continue;
                }

                preg_match_all('/`([a-z_]+)`/', $this->abilitySentence($operation), $matches);

                $rows[] = [
                    'method' => strtoupper($method),
                    'path' => '/api/v1'.$path,
                    'summary' => $operation['summary'] ?? '',
                    'abilities' => $matches[1],
                ];
            }
        }

        usort($rows, fn (array $a, array $b): int => [$a['path'], $a['method']] <=> [$b['path'], $b['method']]);

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function abilitySentence(array $operation): string
    {
        $description = is_string($operation['description'] ?? null) ? $operation['description'] : '';
        $position = strpos($description, 'Requires a token with');

        return $position === false ? '' : substr($description, $position);
    }

    /**
     * @param  string  $file  repository-relative path to a markdown file
     *                        shipped with the application
     */
    private function markdown(string $file): string
    {
        $path = base_path($file);

        if (! is_file($path)) {
            return '';
        }

        // A deliberately small extension set. These are files shipped with
        // the application, not user input — but rendering them with the
        // narrowest converter that does the job keeps it that way even if
        // someone later points this at something less trustworthy.
        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new TableExtension);

        return (string) (new MarkdownConverter($environment))->convert((string) file_get_contents($path));
    }
}
