<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Files\Scanning\ScannerStatus;
use App\Modules\Files\Scanning\ScanVerdict;
use App\Modules\Files\Scanning\VirusScanner;

/**
 * A scanner that answers whatever the test says.
 *
 * Every question worth asking about scanning is about what happens
 * *after* a verdict — whether the file can be downloaded, who is told,
 * what the policy does with a file nobody could open. Producing a real
 * file that provokes each of those from ClamAV would mean shipping
 * malware samples and an encrypted archive in the repository, and would
 * still not let a test say "the scanner is down".
 *
 * The real client has its own test against a live clamd, which skips
 * unless one is reachable.
 */
class FakeVirusScanner implements VirusScanner
{
    /** @var list<ScanVerdict> */
    private array $verdicts = [];

    public int $scans = 0;

    /** @var list<int> the sizes it was asked to read, in order */
    public array $sizes = [];

    private ScannerStatus $status;

    public function __construct(?ScanVerdict $verdict = null)
    {
        if ($verdict !== null) {
            $this->verdicts[] = $verdict;
        }

        $this->status = new ScannerStatus(true, 'FakeAV 1.0', 1, now());
    }

    /** Answer this next. Queued, so a test can say "down, then up". */
    public function willAnswer(ScanVerdict ...$verdicts): self
    {
        foreach ($verdicts as $verdict) {
            $this->verdicts[] = $verdict;
        }

        return $this;
    }

    public function reports(ScannerStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function scan(mixed $stream, int $size): ScanVerdict
    {
        $this->scans++;
        $this->sizes[] = $size;

        // The last answer stands once the queue runs dry: a test that
        // says "infected" once means it, however many times the job is
        // retried.
        return count($this->verdicts) > 1
            ? array_shift($this->verdicts)
            : ($this->verdicts[0] ?? ScanVerdict::clean('FakeAV 1.0'));
    }

    public function status(): ScannerStatus
    {
        return $this->status;
    }
}
